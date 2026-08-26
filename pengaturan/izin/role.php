<?php 
// 1. Panggil 'Otak' aplikasi dulu
require_once __DIR__ . '/../../bootstrap/init.php';

// 2. Jalankan 'SATPAM' buat ngejaga halaman
guard('izin_manage');

// Ambil role yang mau di-edit dari URL (jika ada)
$selectedRole = isset($_GET['role']) ? strtolower(trim($_GET['role'])) : null;

// Proteksi akses role admin atau pengelola (khusus non-admin)
$is_admin = (isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'admin');
if ($selectedRole === 'admin') {
    http_response_code(403);
    require __DIR__ . '/../../bootstrap/access_denied.php';
    exit;
} elseif ($selectedRole === 'pengelola') {
    if (!$is_admin) {
        http_response_code(403);
        require __DIR__ . '/../../bootstrap/access_denied.php';
        exit;
    }
}

// Daftar role yang bisa diatur izin defaultnya
$availableRoles = [];
$role_condition = $is_admin ? "id != 'admin'" : "id NOT IN ('admin', 'pengelola')";
$resRoles = $conn->query("SELECT id, role_name FROM roles WHERE $role_condition ORDER BY role_name ASC");
while($r = $resRoles->fetch_assoc()) {
    $availableRoles[$r['id']] = $r['role_name'];
}

$permissions = [];
$rolePermissions = [];
$selectedRoleName = '';
$totalPermCount = 0;
$activePermCount = 0;

if ($selectedRole && array_key_exists($selectedRole, $availableRoles)) {
    $selectedRoleName = $availableRoles[$selectedRole];

    // Ambil semua izin, dikelompokkan per grup
    $permResult = $conn->query("SELECT id, nama_izin, deskripsi, grup FROM permissions ORDER BY grup ASC, deskripsi ASC");
    while($row = $permResult->fetch_assoc()) {
        $permissions[$row['grup']][] = $row;
        $totalPermCount++;
    }

    // Ambil semua izin yang sudah aktif pada role terpilih
    $stmt = $conn->prepare("SELECT permission_id FROM role_permissions WHERE role = ?");
    $stmt->bind_param("s", $selectedRole);
    $stmt->execute();
    $rolePermResult = $stmt->get_result();
    while($row = $rolePermResult->fetch_assoc()) {
        $rolePermissions[] = (int)$row['permission_id'];
    }
    $stmt->close();

    $activePermCount = count($rolePermissions);
}

// 3. Panggil Header Tampilan
require_once __DIR__ . '/../../layouts/header.php'; 
?>

<style>
    /* Scoped Style untuk Halaman Izin Role */
    :root {
        --iz-primary: #2563eb;
        --iz-primary-hover: #1d4ed8;
        --iz-border: #e2e8f0;
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

    /* Role Banner Card */
    .role-info-banner {
        border: none !important;
        position: relative;
        overflow: hidden;
    }
    .role-avatar-circle {
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

    /* Perm Item Card (Clean & Borderless) */
    .perm-item-box {
        background: #ffffff;
        border: none !important;
        border-radius: 12px;
        padding: 0.85rem 1rem;
        transition: all 0.18s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        flex-direction: column;
        gap: 0.55rem;
    }
    .perm-item-box:hover {
        background: #f8fafc;
    }
    .perm-item-box.is-active {
        background: #eff6ff !important;
    }

    /* Perm Code Tag */
    .perm-code-tag {
        font-family: 'JetBrains Mono', 'Fira Code', monospace;
        font-size: 0.72rem;
        background: #f1f5f9;
        color: #64748b;
        padding: 2px 7px;
        border-radius: 6px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        border: none;
        word-break: break-all;
    }
    .perm-item-box.is-active .perm-code-tag {
        background: #dbeafe;
        color: #1e40af;
    }

    /* Custom Switch / Checkbox Styling */
    .form-check-input:checked {
        background-color: var(--iz-primary) !important;
        border-color: var(--iz-primary) !important;
    }
    .form-check-input:focus {
        border-color: var(--iz-primary);
        box-shadow: 0 0 0 0.25rem rgba(37, 99, 235, 0.25);
    }

    /* Filter Toolbar & Pills (Royal Blue & Clean) */
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
        padding: 0.45rem 0.95rem;
        border-radius: 10px;
        font-size: 0.82rem;
        font-weight: 600;
        border: 1px solid #e2e8f0;
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

    /* ─── SELECT2 MOBILE-FIRST MODERN CUSTOMIZATION ─── */
    .role-picker-card {
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

    .select2-results__options {
        padding: 4px !important;
        max-height: 260px !important;
    }

    .select2-results__option--group {
        padding: 0 !important;
    }

    .select2-container--bootstrap-5 .select2-results__group,
    .select2-results__group {
        display: block !important;
        width: 100% !important;
        box-sizing: border-box !important;
        padding: 0.5rem 0.85rem !important;
        font-size: 0.72rem !important;
        font-weight: 700 !important;
        text-transform: uppercase !important;
        letter-spacing: 0.5px !important;
        color: #64748b !important;
        background: #f8fafc !important;
        border-radius: 6px !important;
        margin: 4px 0 2px 0 !important;
    }

    .select2-results__options--nested {
        padding: 2px 4px !important;
    }

    .select2-container--bootstrap-5 .select2-results__option,
    .select2-container .select2-results__option {
        padding: 0.55rem 0.85rem !important;
        border-radius: 8px !important;
        margin: 2px 0 !important;
        font-size: 0.86rem !important;
        font-weight: 500 !important;
        color: #1e293b !important;
        background-color: transparent !important;
        transition: background 0.12s ease !important;
    }

    .select2-container--bootstrap-5 .select2-results__option--highlighted,
    .select2-container--bootstrap-5 .select2-results__option--highlighted.select2-results__option--selectable,
    .select2-container .select2-results__option--highlighted,
    .select2-results__option--highlighted {
        background-color: #f1f5f9 !important;
        color: #0f172a !important;
        font-weight: 600 !important;
    }

    .select2-container--bootstrap-5 .select2-results__option[aria-selected="true"],
    .select2-container .select2-results__option[aria-selected="true"],
    .select2-results__option[aria-selected="true"] {
        background-color: #eff6ff !important;
        color: #1d4ed8 !important;
        font-weight: 600 !important;
    }

    .select2-container--bootstrap-5 .select2-results__option--highlighted.select2-results__option--selected,
    .select2-container--bootstrap-5 .select2-results__option--highlighted[aria-selected="true"],
    .select2-results__option--highlighted[aria-selected="true"] {
        background-color: #e0e7ff !important;
        color: #1e40af !important;
        font-weight: 600 !important;
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
                <i class="fas fa-layer-group fa-lg"></i>
            </div>
            <div>
                <h3 class="fw-bold mb-0 text-dark" style="letter-spacing: -0.3px; font-size: 1.35rem;">Pengaturan Izin Default Role</h3>
                <p class="text-muted mb-0" style="font-size: 0.85rem;">Atur hak akses bawaan otomatis untuk seluruh pengguna berdasarkan rolenya.</p>
            </div>
        </div>
        
        <!-- Segmented Navigation Control (Standardized) -->
        <nav class="nav-segmented-control" aria-label="Navigasi Pengaturan Izin">
            <a href="index.php" class="nav-segment-link">
                <i class="fas fa-user-shield"></i>
                <span>Izin User</span>
            </a>
            <a href="role.php" class="nav-segment-link active">
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

    <!-- Form Pilih Role -->
    <div class="card shadow-sm border-0 rounded-4 mb-4 role-picker-card">
        <div class="card-body p-3 p-md-4">
            <form method="GET" id="formSelectRole">
                <label class="form-label fw-bold text-dark mb-2.5 d-flex align-items-center gap-2" style="font-size: 0.95rem;">
                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle flex-shrink-0" style="width: 30px; height: 30px; background: #eff6ff; color: var(--iz-primary); font-size: 0.85rem;">
                        <i class="fas fa-layer-group"></i>
                    </span>
                    <span>Pilih Role (Jabatan)</span>
                </label>
                <div>
                    <select class="form-select form-select-lg" name="role" id="role_select" onchange="this.form.submit()" style="cursor:pointer;">
                        <option value="">-- Pilih role / jabatan --</option>
                        <?php foreach($availableRoles as $roleKey => $roleLabel): ?>
                            <option value="<?= $roleKey ?>" <?= ($selectedRole == $roleKey) ? 'selected' : '' ?>>
                                💼 Role: <?= htmlspecialchars($roleLabel) ?> (<?= htmlspecialchars($roleKey) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <?php if ($selectedRole && !empty($permissions)): ?>
        <!-- Info Banner Role Terpilih -->
        <div class="card shadow-sm border-0 rounded-4 mb-4 role-info-banner" style="background: #ffffff;">
            <div class="card-body p-3 p-md-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="role-avatar-circle flex-shrink-0">
                        <i class="fas fa-id-badge"></i>
                    </div>
                    <div class="min-w-0 flex-grow-1">
                        <h5 class="fw-bold mb-1 text-dark text-truncate">Role: <?= htmlspecialchars($selectedRoleName) ?> <span class="text-muted fw-normal font-monospace" style="font-size: 0.82rem;">(<?= htmlspecialchars($selectedRole) ?>)</span></h5>
                        <div class="d-flex flex-wrap align-items-center gap-1.5">
                            <span class="badge" id="bannerActiveCountBadge" style="background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; font-size:0.75rem; font-weight:600;">
                                <i class="fas fa-check-circle me-1"></i> <span id="bannerActiveCountText"><?= $activePermCount ?> Izin Aktif</span>
                            </span>
                            <span class="badge bg-light text-secondary border text-wrap text-start lh-sm" style="font-size:0.72rem; font-weight:500; white-space: normal;">
                                <i class="fas fa-info-circle me-1"></i> Berlaku otomatis ke semua user dengan role ini
                            </span>
                        </div>
                    </div>
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
                            <button type="button" class="filter-btn-pill" id="btnFilterActive" onclick="setFilterMode('active')" title="Filter hanya izin yang sedang aktif pada role ini">
                                <i class="fas fa-check-circle" style="color:var(--iz-primary);"></i>
                                <span>Izin Aktif</span>
                                <span class="badge-count" id="activeFilterCount"><?= $activePermCount ?></span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Simpan Izin Role -->
        <form action="process_role.php" method="POST" id="form-role-permissions">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? csrf_generate() ?>">
            <input type="hidden" name="role" value="<?= htmlspecialchars($selectedRole) ?>">

            <!-- Empty State jika Pencarian Tidak Ada -->
            <div id="noSearchPermsState" class="card shadow-sm border-0 rounded-4 p-5 text-center my-4" style="display: none; background: #ffffff;">
                <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3 bg-light mx-auto" style="width: 64px; height: 64px;">
                    <i class="fas fa-search fa-2x text-muted opacity-50"></i>
                </div>
                <h5 class="fw-bold text-dark mb-1">Tidak Ada Izin yang Cocok</h5>
                <p class="text-muted small mb-0">Coba gunakan kata kunci pencarian yang lain.</p>
            </div>

            <!-- Permission Groups Accordion / Cards List -->
            <div class="row g-3" id="permGroupsContainer">
                <?php foreach ($permissions as $grup => $items): ?>
                    <div class="col-12 perm-group-wrapper" data-group="<?= htmlspecialchars($grup) ?>">
                        <div class="card shadow-sm border-0 rounded-4 overflow-hidden" style="background: #ffffff;">
                            <div class="card-header bg-white py-3 px-3.5 d-flex align-items-center justify-content-between border-0">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge rounded-circle p-2" style="background: #eff6ff; color: var(--iz-primary);">
                                        <i class="fas fa-folder-open fa-sm"></i>
                                    </span>
                                    <h6 class="fw-bold mb-0 text-dark" style="font-size: 0.95rem; letter-spacing: -0.2px;">
                                        Kategori: <?= htmlspecialchars($grup) ?>
                                    </h6>
                                </div>
                                <span class="badge bg-light text-secondary px-2.5 py-1" style="font-size: 0.75rem; font-weight: 600;">
                                    <?= count($items) ?> Izin
                                </span>
                            </div>
                            
                            <div class="card-body pt-1 pb-3 px-3">
                                <div class="d-flex flex-column gap-1.5">
                                    <?php foreach ($items as $perm): 
                                        $permId = (int)$perm['id'];
                                        $isChecked = in_array($permId, $rolePermissions, true);
                                        $searchKeywords = strtolower($perm['deskripsi'] . ' ' . $perm['nama_izin'] . ' ' . $grup);
                                    ?>
                                        <div class="perm-item-box <?= $isChecked ? 'is-active' : '' ?>" id="box_perm_<?= $permId ?>" data-search="<?= htmlspecialchars($searchKeywords) ?>" data-active="<?= $isChecked ? '1' : '0' ?>">
                                            <div class="d-flex align-items-start justify-content-between gap-3">
                                                <label class="form-check-label w-100 m-0" for="perm_<?= $permId ?>" style="cursor: pointer;">
                                                    <!-- Title -->
                                                    <div class="fw-bold text-dark" style="font-size: 0.91rem; line-height: 1.35;">
                                                        <?= htmlspecialchars($perm['deskripsi']) ?>
                                                    </div>
                                                    <!-- Code Tag -->
                                                    <div class="mt-1.5">
                                                        <span class="perm-code-tag">
                                                            <i class="fas fa-code fa-xs opacity-50"></i> <?= htmlspecialchars($perm['nama_izin']) ?>
                                                        </span>
                                                    </div>
                                                </label>

                                                <!-- Custom Switch -->
                                                <div class="form-check form-switch pt-1 pe-1">
                                                    <input class="form-check-input" type="checkbox" role="switch" name="permissions[]" value="<?= $permId ?>" id="perm_<?= $permId ?>" <?= $isChecked ? 'checked' : '' ?> onchange="onRolePermToggle(<?= $permId ?>, this.checked)" style="width: 2.75rem; height: 1.4rem; cursor: pointer;">
                                                </div>
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
                    <i class="fas fa-save me-2"></i>Simpan Perubahan Izin Role
                </button>
            </div>
        </form>
    <?php elseif ($selectedRole): ?>
        <div class="card shadow-sm border-0 rounded-4 p-5 text-center my-5">
            <i class="fas fa-box-open fa-4x text-muted mb-3 opacity-25"></i>
            <h4 class="fw-bold text-dark">Role Tidak Ditemukan</h4>
            <p class="text-muted">Role yang dipilih tidak valid atau belum terdaftar di sistem.</p>
        </div>
    <?php else: ?>
        <div class="card shadow-sm border-0 rounded-4 p-5 text-center my-5" style="background: linear-gradient(180deg, #ffffff, #f8fafc);">
            <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-4 bg-white shadow-sm mx-auto" style="width: 90px; height: 90px;">
                <i class="fas fa-hand-pointer fa-3x opacity-75" style="color:var(--iz-primary);"></i>
            </div>
            <h4 class="fw-bold text-dark mb-2">Pilih Role Dahulu</h4>
            <p class="text-muted" style="max-width: 500px; margin: 0 auto;">Pilih salah satu role dari dropdown di atas untuk mulai mengatur paket izin standar bawaannya.</p>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>

<!-- Script Khusus Halaman Diletakkan SETELAH Footer -->
<script>
    var currentFilterMode = 'all'; // 'all' or 'active'

    function onRolePermToggle(permId, isChecked) {
        var box = document.getElementById('box_perm_' + permId);
        if (!box) return;

        if (isChecked) {
            box.classList.add('is-active');
            box.setAttribute('data-active', '1');
        } else {
            box.classList.remove('is-active');
            box.setAttribute('data-active', '0');
        }

        updateRoleActiveCount();
        applyFilters();
    }

    function updateRoleActiveCount() {
        var activeBoxes = document.querySelectorAll('.perm-item-box[data-active="1"]');
        var count = activeBoxes.length;

        var countBadge = document.getElementById('activeFilterCount');
        if (countBadge) countBadge.textContent = count;

        var bannerText = document.getElementById('bannerActiveCountText');
        if (bannerText) bannerText.textContent = count + ' Izin Aktif';
    }

    function setFilterMode(mode) {
        currentFilterMode = mode;
        var btnAll = document.getElementById('btnFilterAll');
        var btnActive = document.getElementById('btnFilterActive');

        if (btnAll) {
            if (mode === 'all') btnAll.classList.add('active');
            else btnAll.classList.remove('active');
        }

        if (btnActive) {
            if (mode === 'active') btnActive.classList.add('active');
            else btnActive.classList.remove('active');
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

        items.forEach(function(box) {
            var searchData = (box.getAttribute('data-search') || '').toLowerCase();
            var isActive = (box.getAttribute('data-active') === '1');

            var matchesQuery = !query || searchData.includes(query);
            var matchesMode = (currentFilterMode === 'all') || (currentFilterMode === 'active' && isActive);

            if (matchesQuery && matchesMode) {
                box.style.display = '';
                visibleCount++;
            } else {
                box.style.display = 'none';
            }
        });

        // Hide/Show empty group columns
        document.querySelectorAll('.perm-group-wrapper, .group-column').forEach(function(col) {
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

        // Empty search state
        var noSearchState = document.getElementById('noSearchPermsState');
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

    // Inisialisasi Select2
    if (typeof jQuery !== 'undefined') {
        jQuery(function($) {
            var $roleSelect = $('#role_select');
            if ($roleSelect.length && typeof $.fn.select2 !== 'undefined') {
                $roleSelect.select2({
                    theme: 'bootstrap-5',
                    width: '100%',
                    placeholder: "Pilih role / jabatan...",
                    allowClear: true
                });
            }
        });
    }
</script>
