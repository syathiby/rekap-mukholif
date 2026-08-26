<?php 
// 1. Panggil 'Otak' aplikasi dulu
require_once __DIR__ . '/../../bootstrap/init.php';

// 2. Jalankan 'SATPAM' buat ngejaga halaman
guard('izin_manage');    

$isAjax = isset($_GET['ajax']) && $_GET['ajax'] == '1';

// Ambil ID user yang mau di-edit dari URL (jika ada)
$selectedUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;

// Cegah manipulasi URL ke ID diri sendiri
$loggedInUserId = (int)($_SESSION['user_id'] ?? 0);
if ($selectedUserId && $selectedUserId === $loggedInUserId) {
    http_response_code(403);
    require __DIR__ . '/../../bootstrap/access_denied.php';
    exit;
}

// Ambil semua user untuk dropdown (dikelompokkan per role)
$is_admin = (isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'admin');
$role_condition = $is_admin ? "u.role != 'admin'" : "u.role NOT IN ('admin', 'pengelola')";
$usersQuery = $conn->query("
    SELECT u.id, u.nama_lengkap, u.username, u.role, r.role_name 
    FROM users u
    LEFT JOIN roles r ON u.role = r.id
    WHERE $role_condition AND u.id != $loggedInUserId 
    ORDER BY COALESCE(r.role_name, u.role) ASC, u.nama_lengkap ASC
");

$groupedUsers = [];
if ($usersQuery) {
    while ($user = $usersQuery->fetch_assoc()) {
        $roleName = !empty($user['role_name']) ? $user['role_name'] : ucfirst($user['role']);
        $groupedUsers[$roleName][] = $user;
    }
}

$permissions = [];
$rolePermissions = []; // Izin bawaan role (array of permission_id)
$userOverrides = [];   // Override khusus: [permission_id => 1 (allow) / 0 (deny)]
$selectedUserData = null;
$customOverrideCount = 0;
$totalPermCount = 0;

if ($selectedUserId) {
    // Ambil data user yang dipilih
    $userQuery = $conn->prepare("
        SELECT u.id, u.nama_lengkap, u.username, u.role, r.role_name 
        FROM users u 
        LEFT JOIN roles r ON u.role = r.id 
        WHERE u.id = ?
    ");
    $userQuery->bind_param("i", $selectedUserId);
    $userQuery->execute();
    $selectedUserData = $userQuery->get_result()->fetch_assoc();
    $userQuery->close();

    if ($selectedUserData) {
        $targetRole = strtolower(trim((string)$selectedUserData['role']));

        // Proteksi role pengelola jika bukan admin
        if (($targetRole === 'admin' || $targetRole === 'pengelola') && !$is_admin) {
            http_response_code(403);
            require __DIR__ . '/../../bootstrap/access_denied.php';
            exit;
        }

        // Ambil semua izin dari database, dikelompokkan berdasarkan grup
        $permResult = $conn->query("SELECT id, nama_izin, deskripsi, grup FROM permissions ORDER BY grup ASC, deskripsi ASC");
        while($row = $permResult->fetch_assoc()) {
            $permissions[$row['grup']][] = $row;
            $totalPermCount++;
        }

        // Ambil izin default dari Role user terpilih
        $stmtRole = $conn->prepare("SELECT permission_id FROM role_permissions WHERE role = ?");
        $stmtRole->bind_param("s", $targetRole);
        $stmtRole->execute();
        $resRole = $stmtRole->get_result();
        while($row = $resRole->fetch_assoc()) {
            $rolePermissions[] = (int)$row['permission_id'];
        }
        $stmtRole->close();

        // Ambil override khusus milik user ini
        $stmtUp = $conn->prepare("SELECT permission_id, is_allowed FROM user_permissions WHERE user_id = ?");
        $stmtUp->bind_param("i", $selectedUserId);
        $stmtUp->execute();
        $resUp = $stmtUp->get_result();
        while($row = $resUp->fetch_assoc()) {
            $userOverrides[(int)$row['permission_id']] = (int)$row['is_allowed'];
        }
        $stmtUp->close();

        $customOverrideCount = count($userOverrides);
    }
}

// ── FUNGSI MERENDER ISI KONTEN PERMISSIONS ──────────────────────────────────
function renderPermissionsContent($selectedUserData, $permissions, $rolePermissions, $userOverrides, $customOverrideCount, $totalPermCount, $selectedUserId) {
    if ($selectedUserData && !empty($permissions)): ?>
        <!-- Info Banner User Terpilih -->
        <div class="card shadow-sm border-0 rounded-4 mb-4 user-info-banner" style="background: #ffffff;">
            <div class="card-body p-3 p-md-4">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-stretch align-items-md-center gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="user-avatar-circle flex-shrink-0">
                            <i class="fas fa-user-gear"></i>
                        </div>
                        <div class="min-w-0">
                            <h5 class="fw-bold mb-1 text-dark text-truncate"><?= htmlspecialchars($selectedUserData['nama_lengkap']) ?> <span class="text-muted fw-normal" style="font-size: 0.82rem;">(@<?= htmlspecialchars($selectedUserData['username']) ?>)</span></h5>
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <span class="badge badge-user-role">
                                    <i class="fas fa-id-badge me-1"></i> Role: <?= htmlspecialchars($selectedUserData['role_name'] ?: ucfirst($selectedUserData['role'])) ?>
                                </span>
                                <span class="badge" id="bannerOverrideBadge" style="<?= ($customOverrideCount > 0) ? 'background:#fffbeb; color:#b45309; border:1px solid #fde68a;' : 'background:#ecfdf5; color:#047857; border:1px solid #a7f3d0;' ?> font-size:0.75rem; font-weight:600;">
                                    <i class="fas fa-sliders me-1"></i> <span id="bannerOverrideText"><?= ($customOverrideCount > 0) ? "$customOverrideCount Izin Khusus Aktif" : "100% Mengikuti Default Role" ?></span>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Tombol 1-Klik Reset ke Default Role (Mobile-First Pill) -->
                    <form action="process.php" method="POST" id="formResetAll" class="m-0">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? csrf_generate() ?>">
                        <input type="hidden" name="user_id" value="<?= $selectedUserId ?>">
                        <input type="hidden" name="action" value="reset_all">
                        <button type="button" class="btn-reset-overrides" onclick="confirmResetAll(event)" title="Hapus semua izin khusus dan kembalikan ke default role">
                            <span class="btn-icon-circle"><i class="fas fa-rotate-left"></i></span>
                            <span>Reset Semua ke Default Role</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="card shadow-sm border-0 rounded-4 mb-4" style="background: #ffffff;">
            <div class="card-body p-3">
                <div class="d-flex flex-column flex-md-row align-items-stretch align-items-md-center justify-content-between gap-2">
                    <div class="perm-search-box">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" id="filterInput" placeholder="Cari nama izin atau kode teknis (misal: arsip, santri)..." autocomplete="off" oninput="applyFilters()">
                        <i class="fas fa-times clear-btn" id="clearFilterBtn" onclick="clearFilter()" title="Hapus filter"></i>
                    </div>

                    <div class="d-flex align-items-center gap-2 flex-wrap justify-content-between justify-content-md-end">
                        <div class="d-flex gap-2">
                            <button type="button" class="filter-btn-pill active" id="btnFilterAll" onclick="setFilterMode('all')">
                                <i class="fas fa-list-ul"></i>
                                <span>Semua Izin</span>
                                <span class="badge-count" id="totalVisibleCount"><?= $totalPermCount ?></span>
                            </button>
                            <button type="button" class="filter-btn-pill" id="btnFilterCustom" onclick="setFilterMode('custom')" title="Filter hanya izin khusus yang diubah">
                                <i class="fas fa-sliders"></i>
                                <span>Izin Khusus</span>
                                <span class="badge-count" id="customFilterCount"><?= $customOverrideCount ?></span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Simpan Izin -->
        <form action="process.php" method="POST" id="form-user-permissions">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? csrf_generate() ?>">
            <input type="hidden" name="user_id" value="<?= $selectedUserId ?>">
            <input type="hidden" name="action" value="save_overrides">

            <!-- Empty State jika Filter "Izin Khusus" Kosong -->
            <div id="noCustomPermsState" class="card shadow-sm border-0 rounded-4 p-5 text-center my-4" style="display: none; background: #ffffff;">
                <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3 mx-auto" style="width: 64px; height: 64px; background: #ecfdf5; color: #047857;">
                    <i class="fas fa-check-circle fa-2x"></i>
                </div>
                <h5 class="fw-bold text-dark mb-1">Tidak Ada Izin Khusus</h5>
                <p class="text-muted small mb-3">Pengguna ini 100% mengikuti hak akses bawaan rolenya. Belum ada izin yang ditambah (Allow) atau dicabut (Deny) secara khusus.</p>
                <div>
                    <button type="button" class="btn btn-sm btn-outline-primary px-3.5 py-2 rounded-pill" onclick="setFilterMode('all')">
                        <i class="fas fa-eye me-1"></i> Tampilkan Semua Izin
                    </button>
                </div>
            </div>

            <!-- Empty State jika Pencarian Tidak Ada -->
            <div id="noSearchPermsState" class="card shadow-sm border-0 rounded-4 p-5 text-center my-4" style="display: none; background: #ffffff;">
                <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3 bg-light mx-auto" style="width: 64px; height: 64px;">
                    <i class="fas fa-search fa-2x text-muted opacity-50"></i>
                </div>
                <h5 class="fw-bold text-dark mb-1">Izin Tidak Ditemukan</h5>
                <p class="text-muted small mb-3">Tidak ada nama izin atau kode yang cocok dengan kata kunci pencarian Anda.</p>
                <div>
                    <button type="button" class="btn btn-sm btn-outline-secondary px-3.5 py-2 rounded-pill" onclick="clearFilter()">
                        <i class="fas fa-times me-1"></i> Hapus Kata Kunci
                    </button>
                </div>
            </div>

            <div class="row g-4 px-1" id="permGroupsRow">
                <?php foreach ($permissions as $grup => $items): ?>
                    <div class="col-xl-6 col-xxl-4 group-column" data-group-name="<?= htmlspecialchars($grup) ?>">
                        <div class="card shadow-sm border-0 rounded-4 h-100" style="position: relative; overflow: hidden; background: #ffffff;">
                            <div style="position:absolute; top:0; left:0; width:100%; height:4px; background: linear-gradient(90deg, #f97316, #fb923c);"></div>
                            
                            <div class="card-header bg-white border-bottom-0 pt-4 pb-2">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="fw-bold text-dark mb-0"><i class="fas fa-folder-open text-warning me-2 opacity-75"></i><?= htmlspecialchars($grup) ?></h5>
                                    <span class="badge bg-light text-muted border px-2 py-1" style="font-size: 0.72rem; font-weight:600;"><?= count($items) ?> Izin</span>
                                </div>
                            </div>
                            
                            <div class="card-body pt-2 pb-4">
                                <div class="d-flex flex-column gap-2.5">
                                    <?php foreach ($items as $perm): 
                                        $permId = (int)$perm['id'];
                                        $isInherited = in_array($permId, $rolePermissions, true);
                                        
                                        // Current state: 'allow', 'deny', or 'default'
                                        $currentState = 'default';
                                        if (isset($userOverrides[$permId])) {
                                            $currentState = ($userOverrides[$permId] === 1) ? 'allow' : 'deny';
                                        }

                                        $boxClass = '';
                                        if ($currentState === 'allow') $boxClass = 'is-allow';
                                        elseif ($currentState === 'deny') $boxClass = 'is-deny';

                                        $searchKeywords = strtolower($perm['deskripsi'] . ' ' . $perm['nama_izin'] . ' ' . $grup);
                                    ?>
                                        <div class="perm-item-box <?= $boxClass ?>" id="box_perm_<?= $permId ?>" data-search="<?= htmlspecialchars($searchKeywords) ?>" data-state="<?= $currentState ?>">
                                            <!-- Row 1: Title (Full width, no cutoff!) -->
                                            <div class="fw-bold text-dark" style="font-size: 0.91rem; line-height: 1.35;">
                                                <?= htmlspecialchars($perm['deskripsi']) ?>
                                            </div>

                                            <!-- Row 2: Metadata (Code Tag on Left + Role Default Badge on Right) -->
                                            <div class="perm-meta-bar">
                                                <span class="perm-code-tag">
                                                    <i class="fas fa-code fa-xs opacity-50"></i> <?= htmlspecialchars($perm['nama_izin']) ?>
                                                </span>

                                                <div>
                                                    <?php if ($isInherited): ?>
                                                        <span class="badge-role-status badge-role-on" title="Role <?= htmlspecialchars($selectedUserData['role']) ?> memiliki izin ini secara default">
                                                            <i class="fas fa-check-circle fa-xs"></i> Role: Aktif
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge-role-status badge-role-off" title="Role <?= htmlspecialchars($selectedUserData['role']) ?> tidak memiliki izin ini secara default">
                                                            <i class="fas fa-minus-circle fa-xs"></i> Role: Nonaktif
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <!-- Row 3: Smart Contextual Segmented Control -->
                                            <div class="smart-perm-control">
                                                <?php if ($isInherited): ?>
                                                    <!-- KASUS A: Bawaan Role adalah AKTIF -->
                                                    <input type="radio" name="overrides[<?= $permId ?>]" id="perm_<?= $permId ?>_def" value="default" <?= ($currentState !== 'deny') ? 'checked' : '' ?> onchange="onPermStateChange(<?= $permId ?>, 'default')">
                                                    <label for="perm_<?= $permId ?>_def" class="lbl-smart-default" title="Gunakan setelan bawaan role (Aktif)">
                                                        <i class="fas fa-circle-check text-success fa-xs"></i> Default (Aktif)
                                                    </label>

                                                    <input type="radio" name="overrides[<?= $permId ?>]" id="perm_<?= $permId ?>_deny" value="deny" <?= ($currentState === 'deny') ? 'checked' : '' ?> onchange="onPermStateChange(<?= $permId ?>, 'deny')">
                                                    <label for="perm_<?= $permId ?>_deny" class="lbl-smart-deny" title="Cabut/blokir izin ini khusus untuk user ini">
                                                        <i class="fas fa-ban fa-xs"></i> Cabut Khusus
                                                    </label>
                                                <?php else: ?>
                                                    <!-- KASUS B: Bawaan Role adalah NONAKTIF -->
                                                    <input type="radio" name="overrides[<?= $permId ?>]" id="perm_<?= $permId ?>_def" value="default" <?= ($currentState !== 'allow') ? 'checked' : '' ?> onchange="onPermStateChange(<?= $permId ?>, 'default')">
                                                    <label for="perm_<?= $permId ?>_def" class="lbl-smart-default" title="Gunakan setelan bawaan role (Nonaktif)">
                                                        <i class="fas fa-circle-minus text-muted fa-xs"></i> Default (Nonaktif)
                                                    </label>

                                                    <input type="radio" name="overrides[<?= $permId ?>]" id="perm_<?= $permId ?>_allow" value="allow" <?= ($currentState === 'allow') ? 'checked' : '' ?> onchange="onPermStateChange(<?= $permId ?>, 'allow')">
                                                    <label for="perm_<?= $permId ?>_allow" class="lbl-smart-allow" title="Berikan izin tambahan ini khusus untuk user ini">
                                                        <i class="fas fa-plus-circle fa-xs"></i> Berikan Khusus
                                                    </label>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Non-Sticky Bottom Action Bar (Mobile Responsive) -->
            <div class="mt-4 pt-3 pb-5 text-center px-1">
                <button type="submit" class="btn btn-primary btn-lg rounded-pill px-4 px-md-5 py-2.5 shadow-sm fw-bold w-100" style="background: var(--iz-primary); border: none; font-size: 0.96rem; max-width: 420px; transition: all 0.2s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
                    <i class="fas fa-save me-2"></i>Simpan Perubahan Izin User
                </button>
            </div>
        </form>
    <?php elseif ($selectedUserId): ?>
        <div class="card shadow-sm border-0 rounded-4 p-5 text-center my-5">
            <i class="fas fa-box-open fa-4x text-muted mb-3 opacity-25"></i>
            <h4 class="fw-bold text-dark">User Tidak Ditemukan</h4>
            <p class="text-muted">Data pengguna yang dipilih tidak valid atau belum memiliki perizinan.</p>
        </div>
    <?php else: ?>
        <div class="card shadow-sm border-0 rounded-4 p-5 text-center my-5" style="background: linear-gradient(180deg, #ffffff, #f8fafc);">
            <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-4 bg-white shadow-sm mx-auto" style="width: 90px; height: 90px;">
                <i class="fas fa-hand-pointer fa-3x opacity-75" style="color: var(--iz-primary);"></i>
            </div>
            <h4 class="fw-bold text-dark mb-2">Pilih Pengguna Dahulu</h4>
            <p class="text-muted" style="max-width: 500px; margin: 0 auto;">Pilih seorang pengguna dari kotak pencarian di atas untuk mulai mengatur pengecualian izin (penambahan atau pencabutan khusus).</p>
        </div>
    <?php endif;
}

// ── JIKA REQUEST ADALAH AJAX: TAMPILKAN LANGSUNG & SELESAI ────────────────
if ($isAjax) {
    renderPermissionsContent($selectedUserData, $permissions, $rolePermissions, $userOverrides, $customOverrideCount, $totalPermCount, $selectedUserId);
    exit;
}

// ── TAMPILAN STANDAR (FULL PAGE LOAD) ──────────────────────────────────────
require_once __DIR__ . '/../../layouts/header.php'; 
?>

<style>
    /* Scoped Style untuk Halaman Loket Izin User */
    :root {
        --iz-primary: #2563eb;
        --iz-primary-hover: #1d4ed8;
        --iz-green: #16a34a;
        --iz-red: #dc2626;
        --iz-border: #e2e8f0;
        --iz-bg-soft: #f8fafc;
    }

    .nav-segmented-control {
        display: inline-flex;
        background: #f1f5f9;
        padding: 4px;
        border-radius: 14px;
        border: 1px solid #e2e8f0;
        gap: 3px;
        max-width: 100%;
        flex-wrap: nowrap;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
    }
    .nav-segmented-control::-webkit-scrollbar {
        display: none;
    }
    .nav-segment-link {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 0.45rem 0.95rem;
        border-radius: 10px;
        font-size: 0.82rem;
        font-weight: 600;
        color: #64748b;
        text-decoration: none;
        white-space: nowrap;
        flex-shrink: 0;
        transition: all 0.18s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .nav-segment-link:hover {
        color: #0f172a;
        background: rgba(255, 255, 255, 0.7);
    }
    .nav-segment-link.active {
        background: #ffffff;
        color: var(--iz-primary);
        box-shadow: 0 2px 6px rgba(15, 23, 42, 0.08), 0 0 0 1px rgba(226, 232, 240, 0.8);
    }

    /* Perm Item Box */
    .perm-item-box {
        background: #ffffff;
        border: 1.5px solid #edf2f7;
        border-radius: 14px;
        padding: 0.95rem 1rem;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        flex-direction: column;
        gap: 0.65rem;
    }
    .perm-item-box:hover {
        border-color: #cbd5e1;
        box-shadow: 0 4px 12px -2px rgba(15, 23, 42, 0.04);
    }
    .perm-item-box.is-allow {
        border-color: #bbf7d0 !important;
        background: #f0fdf4 !important;
        box-shadow: 0 2px 8px -2px rgba(22, 163, 74, 0.1);
    }
    .perm-item-box.is-deny {
        border-color: #fecaca !important;
        background: #fef2f2 !important;
        box-shadow: 0 2px 8px -2px rgba(220, 38, 38, 0.1);
    }

    /* Meta Bar (Code + Status Badge) */
    .perm-meta-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        flex-wrap: wrap;
    }

    .perm-code-tag {
        font-family: 'JetBrains Mono', 'Fira Code', monospace;
        font-size: 0.73rem;
        background: #f1f5f9;
        color: #475569;
        padding: 2px 8px;
        border-radius: 6px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        border: 1px solid #e2e8f0;
        word-break: break-all;
    }
    .perm-item-box.is-allow .perm-code-tag {
        background: #dcfce7;
        color: #166534;
        border-color: #bbf7d0;
    }
    .perm-item-box.is-deny .perm-code-tag {
        background: #fee2e2;
        color: #991b1b;
        border-color: #fecaca;
    }

    /* Role Status Badges */
    .badge-role-status {
        font-size: 0.72rem;
        font-weight: 600;
        padding: 3px 8px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        white-space: nowrap;
        line-height: 1.2;
    }
    .badge-role-on {
        background: #eff6ff;
        color: #1d4ed8;
        border: 1px solid #bfdbfe;
    }
    .badge-role-off {
        background: #f8fafc;
        color: #64748b;
        border: 1px solid #e2e8f0;
    }

    /* Contextual Smart Segmented Control */
    .smart-perm-control {
        display: grid;
        grid-template-columns: 1fr 1fr;
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 3px;
        gap: 4px;
        width: 100%;
    }
    .perm-item-box.is-allow .smart-perm-control {
        background: #dcfce7;
        border-color: #bbf7d0;
    }
    .perm-item-box.is-deny .smart-perm-control {
        background: #fee2e2;
        border-color: #fecaca;
    }

    .smart-perm-control input[type="radio"] {
        display: none;
    }
    .smart-perm-control label {
        text-align: center;
        padding: 0.44rem 0.4rem;
        font-size: 0.79rem;
        font-weight: 600;
        color: #64748b;
        cursor: pointer;
        border-radius: 8px;
        transition: all 0.16s ease;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
        user-select: none;
        line-height: 1.2;
        border: 1px solid transparent;
        white-space: nowrap;
    }
    .smart-perm-control label:hover {
        background: rgba(255, 255, 255, 0.7);
        color: #0f172a;
    }

    /* Active States */
    .smart-perm-control input:checked + .lbl-smart-default {
        background: #ffffff;
        color: #1e293b;
        border-color: #cbd5e1;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
    }
    .smart-perm-control input:checked + .lbl-smart-allow {
        background: var(--iz-green);
        color: #ffffff;
        border-color: #15803d;
        box-shadow: 0 2px 6px rgba(22, 163, 74, 0.28);
    }
    .smart-perm-control input:checked + .lbl-smart-allow i {
        color: #ffffff !important;
    }
    .smart-perm-control input:checked + .lbl-smart-deny {
        background: var(--iz-red);
        color: #ffffff;
        border-color: #b91c1c;
        box-shadow: 0 2px 6px rgba(220, 38, 38, 0.28);
    }
    .smart-perm-control input:checked + .lbl-smart-deny i {
        color: #ffffff !important;
    }

    /* Filter Toolbar & Pills */
    .perm-search-box {
        position: relative;
        flex: 1;
        min-width: 240px;
    }
    .perm-search-box input {
        width: 100%;
        border-radius: 12px;
        padding: 0.55rem 2.4rem 0.55rem 2.4rem;
        font-size: 0.86rem;
        border: 1.5px solid #e2e8f0;
        background: #f8fafc;
        color: #1e293b;
        transition: all 0.2s ease;
    }
    .perm-search-box input:focus {
        background: #ffffff;
        border-color: var(--iz-primary);
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
        outline: none;
    }
    .perm-search-box .search-icon {
        position: absolute;
        left: 0.9rem;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 0.85rem;
        pointer-events: none;
    }
    .perm-search-box .clear-btn {
        position: absolute;
        right: 0.85rem;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 0.82rem;
        cursor: pointer;
        display: none;
    }

    .filter-btn-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 0.45rem 0.9rem;
        border-radius: 10px;
        font-size: 0.8rem;
        font-weight: 600;
        border: 1.5px solid #e2e8f0;
        background: #ffffff;
        color: #64748b;
        cursor: pointer;
        transition: all 0.15s ease;
        user-select: none;
    }
    .filter-btn-pill:hover {
        background: #f8fafc;
        border-color: #cbd5e1;
        color: #0f172a;
    }
    .filter-btn-pill.active {
        background: var(--iz-primary) !important;
        border-color: var(--iz-primary) !important;
        color: #ffffff !important;
        box-shadow: 0 2px 8px rgba(37, 99, 235, 0.25);
    }
    .filter-btn-pill.active i {
        color: #ffffff !important;
    }
    .filter-btn-pill.active .badge-count {
        background: rgba(255, 255, 255, 0.25) !important;
        color: #ffffff !important;
    }
    .filter-btn-pill .badge-count {
        background: #f1f5f9;
        color: #475569;
        font-size: 0.72rem;
        padding: 2px 6px;
        border-radius: 6px;
        font-weight: 700;
    }

    /* User Info Banner */
    .user-info-banner {
        border: 1.5px solid #edf2f7 !important;
        position: relative;
        overflow: hidden;
    }
    .user-avatar-circle {
        width: 46px;
        height: 46px;
        border-radius: 14px;
        background: #eff6ff;
        color: var(--iz-primary);
        font-size: 1.25rem;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .badge-user-role {
        background: #f1f5f9;
        color: #475569;
        border: 1px solid #e2e8f0;
        font-size: 0.75rem;
        font-weight: 600;
        padding: 3px 8px;
        border-radius: 8px;
    }

    /* Modern Reset Overrides Button (Mobile & Desktop) */
    .btn-reset-overrides {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        background: #fff1f2;
        color: #e11d48;
        border: 1.5px solid #fecdd3;
        border-radius: 12px;
        padding: 0.52rem 1.1rem;
        font-size: 0.82rem;
        font-weight: 600;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        white-space: nowrap;
        cursor: pointer;
        box-shadow: 0 1px 3px rgba(225, 29, 72, 0.08);
    }
    .btn-reset-overrides:hover {
        background: #ffe4e6;
        border-color: #fda4af;
        color: #be123c;
        box-shadow: 0 3px 10px rgba(225, 29, 72, 0.16);
        transform: translateY(-1px);
    }
    .btn-reset-overrides:active {
        transform: translateY(0);
    }
    .btn-reset-overrides .btn-icon-circle {
        width: 22px;
        height: 22px;
        border-radius: 50%;
        background: rgba(225, 29, 72, 0.12);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.72rem;
        transition: transform 0.25s ease;
    }
    .btn-reset-overrides:hover .btn-icon-circle {
        transform: rotate(-60deg);
    }

    @media (max-width: 991px) {
        .nav-segmented-control {
            width: 100%;
            overflow-x: auto;
            flex-wrap: nowrap;
            -webkit-overflow-scrolling: touch;
        }
        .nav-segment-link {
            flex-shrink: 0;
            padding: 0.45rem 0.75rem;
            font-size: 0.78rem;
        }
    }

    @media (max-width: 767.98px) {
        .btn-reset-overrides {
            width: 100% !important;
            padding: 0.62rem 1rem;
            font-size: 0.84rem;
            margin-top: 0.35rem;
        }
    }

    /* ─── SELECT2 MOBILE-FIRST MODERN CUSTOMIZATION ─── */
    .user-picker-card {
        border: 1.5px solid #edf2f7 !important;
        background: linear-gradient(180deg, #ffffff, #f8fafc) !important;
    }

    .select2-container--bootstrap-5 .select2-selection--single,
    .select2-container .select2-selection--single {
        height: 50px !important;
        border-radius: 14px !important;
        border: 1.5px solid #e2e8f0 !important;
        background-color: #ffffff !important;
        padding: 0.55rem 3.2rem 0.55rem 1.1rem !important;
        display: flex !important;
        align-items: center !important;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04) !important;
    }

    .select2-container--bootstrap-5 .select2-selection--single:hover,
    .select2-container .select2-selection--single:hover {
        border-color: #cbd5e1 !important;
        background-color: #f8fafc !important;
    }

    .select2-container--bootstrap-5.select2-container--open .select2-selection--single,
    .select2-container--bootstrap-5.select2-container--focus .select2-selection--single {
        border-color: var(--iz-primary) !important;
        background-color: #ffffff !important;
        box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12) !important;
    }

    .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered,
    .select2-container .select2-selection--single .select2-selection__rendered {
        padding: 0 !important;
        font-size: 0.88rem !important;
        font-weight: 600 !important;
        color: #1e293b !important;
        line-height: 1.3 !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        white-space: nowrap !important;
        padding-right: 0.5rem !important;
    }

    .select2-container--bootstrap-5 .select2-selection--single .select2-selection__placeholder {
        color: #94a3b8 !important;
        font-weight: 500 !important;
        font-size: 0.86rem !important;
    }

    .select2-container--bootstrap-5 .select2-selection--single .select2-selection__arrow,
    .select2-container .select2-selection--single .select2-selection__arrow {
        height: 100% !important;
        width: 36px !important;
        right: 8px !important;
        top: 0 !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
    }

    .select2-container--bootstrap-5 .select2-selection--single .select2-selection__clear {
        right: 36px !important;
        font-size: 1.25rem !important;
        color: #94a3b8 !important;
        font-weight: bold !important;
        margin-right: 2px !important;
        line-height: 1 !important;
    }
    .select2-container--bootstrap-5 .select2-selection--single .select2-selection__clear:hover {
        color: #e11d48 !important;
    }

    .select2-dropdown {
        border: 1.5px solid #e2e8f0 !important;
        border-radius: 16px !important;
        box-shadow: 0 16px 36px -6px rgba(15, 23, 42, 0.16), 0 0 0 1px rgba(226, 232, 240, 0.8) !important;
        overflow: hidden !important;
        margin-top: 6px !important;
        background: #ffffff !important;
        z-index: 1060 !important;
    }

    .select2-search--dropdown {
        padding: 8px 10px !important;
        background: #ffffff !important;
        border-bottom: 1px solid #f1f5f9 !important;
    }

    .select2-container--bootstrap-5 .select2-dropdown .select2-search .select2-search__field {
        border: 1.5px solid #e2e8f0 !important;
        border-radius: 10px !important;
        padding: 0.55rem 0.85rem !important;
        font-size: 0.85rem !important;
        background: #f8fafc !important;
        box-shadow: none !important;
    }
    .select2-container--bootstrap-5 .select2-dropdown .select2-search .select2-search__field:focus {
        border-color: var(--iz-primary) !important;
        background: #ffffff !important;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12) !important;
        outline: none !important;
    }

    /* Only root UL gets max-height & overflow scrolling */
    .select2-results > .select2-results__options {
        padding: 4px !important;
        max-height: 280px !important;
        overflow-y: auto !important;
        overflow-x: hidden !important;
        -webkit-overflow-scrolling: touch;
        list-style: none !important;
        margin: 0 !important;
    }

    /* Group container must be block flow without max-height */
    .select2-results__option--group {
        padding: 0 !important;
        margin: 4px 0 6px 0 !important;
        background: transparent !important;
        border: none !important;
        display: block !important;
        list-style: none !important;
    }

    .select2-container--bootstrap-5 .select2-results__group,
    .select2-results__group {
        display: block !important;
        width: 100% !important;
        box-sizing: border-box !important;
        padding: 0.45rem 0.85rem !important;
        font-size: 0.72rem !important;
        font-weight: 700 !important;
        text-transform: uppercase !important;
        letter-spacing: 0.6px !important;
        color: #475569 !important;
        background: #f1f5f9 !important;
        border-radius: 6px !important;
        margin: 4px 0 3px 0 !important;
        border-left: 3px solid var(--iz-primary) !important;
    }

    /* Nested UL inside optgroups MUST NOT have max-height or inner scroll */
    .select2-results__options--nested,
    .select2-results__options .select2-results__options {
        padding: 0 !important;
        margin: 0 !important;
        max-height: none !important;
        overflow: visible !important;
        list-style: none !important;
    }

    .select2-container--bootstrap-5 .select2-results__options--nested > .select2-results__option,
    .select2-results__options--nested > .select2-results__option,
    .select2-container--bootstrap-5 .select2-results__option:not(.select2-results__option--group) {
        padding: 0.55rem 0.85rem !important;
        border-radius: 8px !important;
        margin: 2px 0 !important;
        font-size: 0.86rem !important;
        color: #1e293b !important;
        background-color: transparent !important;
        display: block !important;
        transition: background 0.12s ease !important;
    }

    .select2-container--bootstrap-5 .select2-results__options--nested > .select2-results__option--highlighted,
    .select2-container--bootstrap-5 .select2-results__options--nested > .select2-results__option--highlighted[aria-selected],
    .select2-container--bootstrap-5 .select2-results__option--highlighted:not(.select2-results__option--group),
    .select2-container--bootstrap-5 .select2-results__option--highlighted:not(.select2-results__option--group)[aria-selected],
    .select2-results__option--highlighted {
        background-color: #f1f5f9 !important;
        color: #0f172a !important;
    }

    .select2-container--bootstrap-5 .select2-results__options--nested > .select2-results__option[aria-selected="true"],
    .select2-container--bootstrap-5 .select2-results__option:not(.select2-results__option--group)[aria-selected="true"],
    .select2-results__option[aria-selected="true"] {
        background-color: #eff6ff !important;
        color: #1d4ed8 !important;
        font-weight: 600 !important;
    }

    .select2-container--bootstrap-5 .select2-results__options--nested > .select2-results__option--highlighted.select2-results__option--selected,
    .select2-container--bootstrap-5 .select2-results__option--highlighted:not(.select2-results__option--group)[aria-selected="true"] {
        background-color: #e0e7ff !important;
        color: #1e40af !important;
        font-weight: 600 !important;
    }

    @media (max-width: 576px) {
        .select2-container--bootstrap-5 .select2-selection--single,
        .select2-container .select2-selection--single {
            height: 48px !important;
            padding: 0.5rem 3rem 0.5rem 0.9rem !important;
        }
        .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered,
        .select2-container .select2-selection--single .select2-selection__rendered {
            font-size: 0.85rem !important;
        }
    }
</style>

<div class="dashboard-wrapper container-fluid px-0 px-md-2 mt-2 mb-5">
    
    <!-- Header Page -->
    <div class="d-flex flex-column flex-xl-row justify-content-between align-items-start align-items-xl-center mb-4 px-1 gap-3">
        <div class="d-flex align-items-center">
            <div class="d-flex align-items-center justify-content-center rounded-3 me-3 shadow-sm flex-shrink-0" style="width: 48px; height: 48px; background: linear-gradient(135deg, #2563eb, #3b82f6); color: white;">
                <i class="fas fa-user-shield fa-lg"></i>
            </div>
            <div>
                <h3 class="fw-bold mb-0 text-dark" style="letter-spacing: -0.3px; font-size: 1.35rem;">Loket Pengaturan Izin User</h3>
                <p class="text-muted mb-0" style="font-size: 0.85rem;">Atur hak akses kustom (tambahan/cabut) khusus per pengguna.</p>
            </div>
        </div>
        
        <!-- Segmented Navigation Control -->
        <nav class="nav-segmented-control" aria-label="Navigasi Pengaturan Izin">
            <a href="index.php" class="nav-segment-link active">
                <i class="fas fa-user-shield"></i>
                <span>Izin User</span>
            </a>
            <a href="role.php" class="nav-segment-link">
                <i class="fas fa-layer-group"></i>
                <span>Default Role</span>
            </a>
            <a href="manage_roles.php" class="nav-segment-link">
                <i class="fas fa-tags"></i>
                <span>Kelola Role</span>
            </a>
            <a href="bulk.php" class="nav-segment-link">
                <i class="fas fa-users-cog"></i>
                <span>Edit Massal</span>
            </a>
            <a href="daftar_izin.php" class="nav-segment-link">
                <i class="fas fa-list-check"></i>
                <span>Daftar Izin</span>
            </a>
        </nav>
    </div>

    <!-- Form Pilih User -->
    <div class="card shadow-sm border-0 rounded-4 mb-4 user-picker-card">
        <div class="card-body p-3 p-md-4">
            <label class="form-label fw-bold text-dark mb-2.5 d-flex align-items-center gap-2" style="font-size: 0.95rem;">
                <span class="d-inline-flex align-items-center justify-content-center rounded-circle flex-shrink-0" style="width: 30px; height: 30px; background: #eff6ff; color: var(--iz-primary); font-size: 0.85rem;">
                    <i class="fas fa-user-shield"></i>
                </span>
                <span>Pilih Akun Pengguna</span>
            </label>
            <div>
                <select class="form-select form-select-lg" name="user_id" id="user_id" onchange="loadUserPermissions(this.value)" style="cursor:pointer;">
                    <option value=""></option>
                    <?php foreach ($groupedUsers as $roleName => $users): ?>
                        <optgroup label="<?= htmlspecialchars($roleName) ?>">
                            <?php foreach ($users as $user): 
                                $userRoleLabel = htmlspecialchars($user['role_name'] ?: ucfirst($user['role']));
                                $userName = htmlspecialchars($user['nama_lengkap']);
                                $userUsername = htmlspecialchars($user['username']);
                            ?>
                                <option value="<?= $user['id'] ?>" 
                                        data-name="<?= $userName ?>" 
                                        data-username="<?= $userUsername ?>" 
                                        data-role="<?= $userRoleLabel ?>"
                                        <?= ($selectedUserId == $user['id']) ? 'selected' : '' ?>>
                                    <?= $userName ?> (@<?= $userUsername ?>) - <?= $userRoleLabel ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <!-- Dynamic Permissions Container (AJAX Loaded) -->
    <div id="user-permissions-container">
        <?php renderPermissionsContent($selectedUserData, $permissions, $rolePermissions, $userOverrides, $customOverrideCount, $totalPermCount, $selectedUserId); ?>
    </div>
</div>

<?php 
// 4. Panggil Footer (Di sinilah jQuery dan Bootstrap di-load!)
require_once __DIR__ . '/../../layouts/footer.php'; 
?>

<!-- 5. Script Khusus Halaman Diletakkan SETELAH Footer agar jQuery dan Select2 sudah siap! -->
<script>
    var currentFilterMode = 'all'; // 'all' or 'custom'
    var lastLoadedUserId = <?= $selectedUserId ? (int)$selectedUserId : 'null' ?>;

    function loadUserPermissions(userId) {
        var uid = String(userId || '').trim();

        if (!uid || uid === "" || uid === "0") {
            lastLoadedUserId = null;
            if (window.history.pushState) {
                history.pushState(null, '', 'index.php');
            }
            var container = document.getElementById('user-permissions-container');
            if (container) {
                container.innerHTML = '\
                    <div class="card shadow-sm border-0 rounded-4 p-5 text-center my-5" style="background: linear-gradient(180deg, #ffffff, #f8fafc);">\
                        <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-4 bg-white shadow-sm mx-auto" style="width: 90px; height: 90px;">\
                            <i class="fas fa-hand-pointer fa-3x text-warning opacity-75"></i>\
                        </div>\
                        <h4 class="fw-bold text-dark mb-2">Pilih Pengguna Dahulu</h4>\
                        <p class="text-muted" style="max-width: 500px; margin: 0 auto;">Pilih seorang pengguna dari kotak pencarian di atas untuk mulai mengatur pengecualian izin (penambahan atau pencabutan khusus).</p>\
                    </div>';
            }
            return;
        }

        if (uid === String(lastLoadedUserId)) {
            return;
        }
        lastLoadedUserId = uid;

        var container = document.getElementById('user-permissions-container');
        if (!container) return;

        // Update URL tanpa reload halaman
        if (window.history.pushState) {
            history.pushState(null, '', 'index.php?user_id=' + encodeURIComponent(uid));
        }

        // Loading indicator
        container.innerHTML = '\
            <div class="card shadow-sm border-0 rounded-4 p-5 text-center my-4" style="background: #ffffff;">\
                <div class="spinner-border mb-3" style="width: 3rem; height: 3rem; color: #f97316;" role="status">\
                    <span class="visually-hidden">Memuat...</span>\
                </div>\
                <h5 class="fw-bold text-dark mb-1">Memuat Izin Pengguna...</h5>\
                <p class="text-muted small mb-0">Sedang memuat data izin role dan override secara instan</p>\
            </div>';

        fetch('index.php?user_id=' + encodeURIComponent(uid) + '&ajax=1', {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(function(response) {
            if (!response.ok) throw new Error('Status respon server: ' + response.status);
            return response.text();
        })
        .then(function(html) {
            container.innerHTML = html;
            currentFilterMode = 'all';
            updateCustomCount();
            applyFilters();
        })
        .catch(function(error) {
            lastLoadedUserId = null;
            container.innerHTML = '\
                <div class="alert alert-danger shadow-sm rounded-4 p-4 text-center my-4">\
                    <i class="fas fa-exclamation-triangle fa-2x mb-2 text-danger"></i>\
                    <div class="fw-bold">Gagal Memuat Izin</div>\
                    <div class="small text-muted">' + error.message + '. Silakan coba pilih ulang pengguna dari dropdown di atas.</div>\
                </div>';
        });
    }

    function onPermStateChange(permId, state) {
        var box = document.getElementById('box_perm_' + permId);
        if (!box) return;

        box.classList.remove('is-allow', 'is-deny');
        box.setAttribute('data-state', state);

        if (state === 'allow') {
            box.classList.add('is-allow');
        } else if (state === 'deny') {
            box.classList.add('is-deny');
        }

        updateCustomCount();
        applyFilters();
    }

    function updateCustomCount() {
        var customBoxes = document.querySelectorAll('.perm-item-box[data-state="allow"], .perm-item-box[data-state="deny"]');
        var count = customBoxes.length;

        var countBadge = document.getElementById('customFilterCount');
        if (countBadge) countBadge.textContent = count;

        var bannerText = document.getElementById('bannerOverrideText');
        var bannerBadge = document.getElementById('bannerOverrideBadge');

        if (bannerText && bannerBadge) {
            if (count > 0) {
                bannerText.textContent = count + ' Izin Khusus Aktif';
                bannerBadge.style.background = '#fffbeb';
                bannerBadge.style.color = '#b45309';
                bannerBadge.style.borderColor = '#fde68a';
            } else {
                bannerText.textContent = '100% Mengikuti Default Role';
                bannerBadge.style.background = '#ecfdf5';
                bannerBadge.style.color = '#047857';
                bannerBadge.style.borderColor = '#a7f3d0';
            }
        }
    }

    function setFilterMode(mode) {
        currentFilterMode = mode;
        var btnAll = document.getElementById('btnFilterAll');
        var btnCustom = document.getElementById('btnFilterCustom');

        if (btnAll) {
            if (mode === 'all') btnAll.classList.add('active');
            else btnAll.classList.remove('active');
        }

        if (btnCustom) {
            if (mode === 'custom') btnCustom.classList.add('active');
            else btnCustom.classList.remove('active');
        }

        applyFilters();
    }

    function applyFilters() {
        var filterInput = document.getElementById('filterInput');
        var query = filterInput ? filterInput.value.toLowerCase().trim() : '';
        var clearBtn = document.getElementById('clearFilterBtn');
        if (clearBtn) {
            clearBtn.style.display = query ? 'block' : 'none';
        }

        var items = document.querySelectorAll('.perm-item-box');
        var visibleCount = 0;
        var totalCustomCount = 0;

        items.forEach(function(box) {
            var searchData = (box.getAttribute('data-search') || '').toLowerCase();
            var state = box.getAttribute('data-state') || 'default';
            var isCustom = (state === 'allow' || state === 'deny');

            if (isCustom) totalCustomCount++;

            var matchesQuery = !query || searchData.includes(query);
            var matchesMode = (currentFilterMode === 'all') || (currentFilterMode === 'custom' && isCustom);

            if (matchesQuery && matchesMode) {
                box.style.display = '';
                visibleCount++;
            } else {
                box.style.display = 'none';
            }
        });

        // Hide/Show empty group columns secara aman
        document.querySelectorAll('.group-column').forEach(function(col) {
            var hasVisibleChild = false;
            col.querySelectorAll('.perm-item-box').forEach(function(box) {
                if (box.style.display !== 'none') {
                    hasVisibleChild = true;
                }
            });
            col.style.display = hasVisibleChild ? '' : 'none';
        });

        var countElem = document.getElementById('totalVisibleCount');
        if (countElem) {
            countElem.textContent = (currentFilterMode === 'all') ? visibleCount : items.length;
        }

        // Empty state handling
        var noCustomState = document.getElementById('noCustomPermsState');
        var noSearchState = document.getElementById('noSearchPermsState');

        if (noCustomState) {
            if (currentFilterMode === 'custom' && totalCustomCount === 0) {
                noCustomState.style.display = 'block';
            } else {
                noCustomState.style.display = 'none';
            }
        }

        if (noSearchState) {
            if (query && visibleCount === 0) {
                noSearchState.style.display = 'block';
            } else {
                noSearchState.style.display = 'none';
            }
        }
    }

    function clearFilter() {
        var input = document.getElementById('filterInput');
        if (input) {
            input.value = '';
            applyFilters();
            input.focus();
        }
    }

    function confirmResetAll(e) {
        if (e) e.preventDefault();
        var form = document.getElementById('formResetAll');
        if (!form) return;

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Reset ke Default Role?',
                text: 'Semua izin khusus (tambahan maupun pencabutan) untuk pengguna ini akan dihapus dan dikembalikan 100% murni mengikuti hak akses bawaan rolenya.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-rotate-left me-1"></i> Ya, Reset Sekarang',
                cancelButtonText: 'Batal',
                reverseButtons: true,
                width: '26em',
                padding: '1.4em',
                customClass: {
                    popup: 'rounded-4 shadow-lg border-0',
                    title: 'fs-5 fw-bold text-dark mb-2',
                    htmlContainer: 'text-secondary small mb-3',
                    confirmButton: 'btn btn-danger rounded-pill px-4 py-2 fw-semibold shadow-sm',
                    cancelButton: 'btn btn-light rounded-pill border px-3.5 py-2 fw-medium text-secondary me-2'
                },
                buttonsStyling: false
            }).then(function(result) {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        } else {
            if (confirm('Apakah Anda yakin ingin menghapus semua izin khusus user ini dan mengembalikannya 100% murni mengikuti default rolenya?')) {
                form.submit();
            }
        }
    }

    // Inisialisasi Select2 setelah jQuery dan Footer siap
    if (typeof jQuery !== 'undefined') {
        jQuery(function($) {
            var $userSelect = $('#user_id');
            if ($userSelect.length && typeof $.fn.select2 !== 'undefined') {
                function formatUserResult(item) {
                    if (!item.id || item.children) {
                        return item.text;
                    }
                    var $el = $(item.element);
                    var name = $el.data('name') || item.text;
                    var username = $el.data('username') ? '@' + $el.data('username') : '';
                    var role = $el.data('role') || '';

                    var $row = $(
                        '<div class="d-flex justify-content-between align-items-center py-0.5 w-100">' +
                            '<div class="min-w-0 me-2">' +
                                '<div class="fw-bold text-dark text-truncate" style="font-size: 0.88rem;"></div>' +
                                '<div class="text-muted small" style="font-size: 0.75rem;"></div>' +
                            '</div>' +
                            '<span class="badge flex-shrink-0" style="background:#f1f5f9; color:#475569; border:1px solid #e2e8f0; font-size:0.7rem; font-weight:600; padding: 3px 7px; border-radius: 6px;"></span>' +
                        '</div>'
                    );

                    $row.find('.fw-bold').text(name);
                    $row.find('.text-muted').text(username);
                    $row.find('.badge').text(role);

                    return $row;
                }

                function formatUserSelection(item) {
                    if (!item.id) return item.text;
                    var $el = $(item.element);
                    var name = $el.data('name') || item.text;
                    var username = $el.data('username') ? ' (@' + $el.data('username') + ')' : '';
                    var role = $el.data('role') ? ' • ' + $el.data('role') : '';

                    return name + username + role;
                }

                $userSelect.select2({
                    theme: 'bootstrap-5',
                    width: '100%',
                    placeholder: "Ketik nama atau username...",
                    allowClear: true,
                    templateResult: formatUserResult,
                    templateSelection: formatUserSelection
                });

                $userSelect.on('select2:select', function(e) {
                    var val = e.params.data.id;
                    loadUserPermissions(val);
                });

                $userSelect.on('select2:unselect', function() {
                    loadUserPermissions('');
                });
            }
        });
    }
</script>