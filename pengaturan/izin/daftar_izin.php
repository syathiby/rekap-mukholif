<?php 
// 1. Panggil Inisialisasi Aplikasi
require_once __DIR__ . '/../../bootstrap/init.php';

// 2. Proteksi Halaman: Wajib memiliki izin izin_manage
guard('izin_manage');    

// 3. Panggil Header Tampilan
require_once __DIR__ . '/../../layouts/header.php'; 

// --- PENGAMBILAN DATA TEROPTIMASI (SINGLE PASS QUERY) ---

// 1. Ambil semua Role
$rolesMap = [];
$resRoles = $conn->query("SELECT id, role_name FROM roles ORDER BY role_name ASC");
if ($resRoles) {
    while ($r = $resRoles->fetch_assoc()) {
        $rolesMap[$r['id']] = $r['role_name'];
    }
}

// 2. Ambil semua Pengguna
$usersMap = [];
$adminUsers = [];
$resUsers = $conn->query("SELECT id, nama_lengkap, username, role, is_active FROM users ORDER BY nama_lengkap ASC");
if ($resUsers) {
    while ($u = $resUsers->fetch_assoc()) {
        $roleName = $rolesMap[$u['role']] ?? ucfirst($u['role']);
        $u['role_name'] = $roleName;
        $usersMap[$u['id']] = $u;
        if (strtolower(trim($u['role'])) === 'admin') {
            $adminUsers[] = $u;
        }
    }
}

// 3. Ambil Mapping Izin Role (Role Permissions)
$rolePermMap = [];
$resRolePerm = $conn->query("SELECT role, permission_id FROM role_permissions");
if ($resRolePerm) {
    while ($rp = $resRolePerm->fetch_assoc()) {
        // Hanya sertakan role yang benar-benar aktif/terdaftar di tabel roles
        if (isset($rolesMap[$rp['role']])) {
            $rolePermMap[$rp['permission_id']][] = $rp['role'];
        }
    }
}

// 4. Ambil Mapping Izin User (User Permissions)
$userPermMap = [];
$resUserPerm = $conn->query("SELECT user_id, permission_id FROM user_permissions");
if ($resUserPerm) {
    while ($up = $resUserPerm->fetch_assoc()) {
        if (isset($usersMap[$up['user_id']])) {
            $userPermMap[$up['permission_id']][] = $usersMap[$up['user_id']];
        }
    }
}

// 5. Ambil Semua Permissions dikelompokkan per Grup
$permissions = [];
$allPermissionsList = [];
$grupList = [];

$permResult = $conn->query("SELECT id, nama_izin, deskripsi, grup FROM permissions ORDER BY grup ASC, deskripsi ASC");
if ($permResult) {
    while ($row = $permResult->fetch_assoc()) {
        $pId = (int)$row['id'];
        $row['roles'] = $rolePermMap[$pId] ?? [];
        $row['users'] = $userPermMap[$pId] ?? [];
        
        $permissions[$row['grup']][] = $row;
        $allPermissionsList[] = $row;
        if (!in_array($row['grup'], $grupList)) {
            $grupList[] = $row['grup'];
        }
    }
}

$totalPermissions = count($allPermissionsList);
$totalGroups = count($grupList);
$totalRoles = count($rolesMap);
$totalUsers = count($usersMap);
?>

<style>
    /* ==========================================================================
       SCOPED STYLES: DAFTAR & MATRIKS IZIN (SUPERIOR UI/UX & LIGHTWEIGHT)
       ========================================================================== */
    
    :root {
        --di-primary: #0284c7;
        --di-primary-dark: #0369a1;
        --di-primary-light: #e0f2fe;
        --di-primary-border: #bae6fd;
        --di-slate-50: #f8fafc;
        --di-slate-100: #f1f5f9;
        --di-slate-200: #e2e8f0;
        --di-slate-300: #cbd5e1;
        --di-slate-600: #475569;
        --di-slate-700: #334155;
        --di-slate-900: #0f172a;
    }

    /* ─── SEGMENTED NAVIGATION CONTROL ─── */
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
        transition: all 0.18s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .nav-segment-link:hover {
        color: #0f172a;
        background: rgba(255, 255, 255, 0.7);
    }
    .nav-segment-link.active {
        background: #ffffff;
        color: var(--di-primary);
        box-shadow: 0 2px 6px rgba(15, 23, 42, 0.08), 0 0 0 1px rgba(226, 232, 240, 0.8);
    }

    /* ─── STAT CARDS ─── */
    .stat-card-pro {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 0.9rem 1.15rem;
        display: flex;
        align-items: center;
        gap: 0.9rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        height: 100%;
    }
    .stat-card-pro:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px -2px rgba(15, 23, 42, 0.06);
        border-color: #cbd5e1;
    }
    .stat-icon-pro {
        width: 42px;
        height: 42px;
        min-width: 42px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.15rem;
    }
    .stat-val-pro {
        font-size: 1.25rem;
        font-weight: 700;
        color: #0f172a;
        line-height: 1.2;
        letter-spacing: -0.3px;
    }
    .stat-lbl-pro {
        font-size: 0.74rem;
        color: #64748b;
        font-weight: 500;
    }

    /* ─── SEARCH & FILTER TOOLBAR ─── */
    .filter-panel-pro {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 18px;
        padding: 1.1rem 1.25rem;
        box-shadow: 0 2px 8px -1px rgba(15, 23, 42, 0.04);
        margin-bottom: 1.5rem;
    }
    .search-wrapper-pro {
        position: relative;
        flex: 1;
    }
    .search-wrapper-pro input {
        width: 100%;
        border-radius: 12px;
        padding: 0.6rem 2.5rem 0.6rem 2.5rem;
        font-size: 0.86rem;
        border: 1.5px solid #e2e8f0;
        background: #f8fafc;
        color: #1e293b;
        font-family: inherit;
        transition: all 0.2s ease;
    }
    .search-wrapper-pro input:focus {
        background: #ffffff;
        border-color: var(--di-primary);
        box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.12);
        outline: none;
    }
    .search-wrapper-pro .search-icon {
        position: absolute;
        left: 0.95rem;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 0.88rem;
        pointer-events: none;
    }
    .search-wrapper-pro .clear-icon {
        position: absolute;
        right: 0.85rem;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 0.85rem;
        cursor: pointer;
        display: none;
        padding: 3px 5px;
        border-radius: 50%;
    }
    .search-wrapper-pro .clear-icon:hover {
        color: #ef4444;
        background: #fee2e2;
    }

    /* ─── CATEGORY PILL FILTER ─── */
    .cat-scroll-track {
        display: flex;
        align-items: center;
        gap: 6px;
        overflow-x: auto;
        padding-bottom: 4px;
        padding-top: 2px;
        scrollbar-width: thin;
        -webkit-overflow-scrolling: touch;
    }
    .cat-scroll-track::-webkit-scrollbar {
        height: 4px;
    }
    .cat-scroll-track::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 4px;
    }
    .cat-chip {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 0.38rem 0.8rem;
        border-radius: 20px;
        font-size: 0.78rem;
        font-weight: 500;
        color: #475569;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        cursor: pointer;
        transition: all 0.15s ease;
        user-select: none;
        white-space: nowrap;
    }
    .cat-chip:hover {
        background: #f1f5f9;
        color: #0f172a;
        border-color: #cbd5e1;
    }
    .cat-chip.active {
        background: var(--di-primary);
        color: #ffffff;
        border-color: var(--di-primary);
        box-shadow: 0 2px 6px rgba(2, 132, 199, 0.28);
    }
    .cat-chip .count-badge {
        background: rgba(0,0,0,0.06);
        border-radius: 10px;
        padding: 1px 6px;
        font-size: 0.7rem;
        font-weight: 600;
    }
    .cat-chip.active .count-badge {
        background: rgba(255,255,255,0.25);
        color: #ffffff;
    }

    /* ─── GROUP BADGE (HARMONIZED WITH FILTER PILLS) ─── */
    .badge-group-pill {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 0.22rem 0.65rem;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        text-decoration: none;
        background: #f1f5f9;
        color: #475569;
        border: 1px solid #e2e8f0;
        cursor: pointer;
        transition: all 0.18s cubic-bezier(0.4, 0, 0.2, 1);
        user-select: none;
        white-space: nowrap;
        line-height: 1.25;
    }
    .badge-group-pill:hover {
        background: #e0f2fe;
        color: #0284c7;
        border-color: #bae6fd;
        transform: translateY(-1px);
    }
    .badge-group-pill.active {
        background: var(--di-primary);
        color: #ffffff;
        border-color: var(--di-primary);
        box-shadow: 0 2px 6px rgba(2, 132, 199, 0.25);
    }

    /* ─── VIEW SWITCHER BUTTONS ─── */
    .btn-view-toggle {
        padding: 0.45rem 0.75rem;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        background: #ffffff;
        color: #64748b;
        font-size: 0.8rem;
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        transition: all 0.15s ease;
    }
    .btn-view-toggle:hover {
        background: #f8fafc;
        color: #0f172a;
    }
    .btn-view-toggle.active {
        background: var(--di-primary);
        color: #ffffff;
        border-color: var(--di-primary);
        box-shadow: 0 2px 6px rgba(2, 132, 199, 0.2);
    }

    /* ─── PERMISSION CARD PRO ─── */
    .perm-card-pro {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 18px;
        box-shadow: 0 2px 6px -1px rgba(15, 23, 42, 0.03);
        transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.2s cubic-bezier(0.4, 0, 0.2, 1), border-color 0.2s;
        height: 100%;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }
    .perm-card-pro:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 22px -3px rgba(15, 23, 42, 0.08);
        border-color: #cbd5e1;
    }
    .perm-card-pro-header {
        padding: 1.1rem 1.25rem 0.9rem;
        background: #ffffff;
        border-bottom: 1px solid #f8fafc;
    }
    .perm-card-pro-body {
        padding: 0.9rem 1.25rem 1.1rem;
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 0.9rem;
        background: #ffffff;
    }

    .perm-code-tag {
        font-family: 'JetBrains Mono', 'Fira Code', monospace;
        font-size: 0.72rem;
        background: #f8fafc;
        color: #475569;
        padding: 2px 7px;
        border-radius: 6px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        border: 1px solid #e2e8f0;
    }
    .btn-copy-code {
        background: none;
        border: none;
        color: #94a3b8;
        padding: 0 2px;
        cursor: pointer;
        font-size: 0.7rem;
        transition: color 0.15s;
    }
    .btn-copy-code:hover {
        color: var(--di-primary);
    }

    .section-label-pro {
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #94a3b8;
        margin-bottom: 0.4rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    /* ─── ROLE & USER CHIPS ─── */
    .chip-role-default {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 0.22rem 0.6rem;
        border-radius: 7px;
        font-size: 0.74rem;
        font-weight: 600;
        text-decoration: none;
        background: #f5f3ff;
        color: #7c3aed;
        border: 1px solid #ddd6fe;
        transition: all 0.15s ease;
    }
    .chip-role-default:hover {
        background: #ede9fe;
        color: #6d28d9;
        transform: translateY(-1px);
    }
    .chip-role-admin {
        background: #fff7ed;
        color: #c2410c;
        border: 1px solid #fed7aa;
        cursor: default;
    }

    .chip-user-pro {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 0.28rem 0.65rem;
        border-radius: 8px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        font-size: 0.76rem;
        color: #1e293b;
        text-decoration: none;
        transition: all 0.15s ease;
    }
    .chip-user-pro:hover {
        background: #e0f2fe;
        color: #0369a1;
        border-color: #bae6fd;
        transform: translateY(-1px);
    }
    .chip-user-avatar {
        width: 18px;
        height: 18px;
        border-radius: 50%;
        background: #e2e8f0;
        color: #475569;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.62rem;
        font-weight: 700;
    }
    .chip-user-pro:hover .chip-user-avatar {
        background: var(--di-primary);
        color: #ffffff;
    }

    .btn-expand-users {
        font-size: 0.72rem;
        font-weight: 600;
        color: var(--di-primary);
        background: var(--di-primary-light);
        border: 1px solid var(--di-primary-border);
        border-radius: 7px;
        padding: 0.2rem 0.55rem;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        transition: all 0.15s;
    }
    .btn-expand-users:hover {
        background: #bae6fd;
        color: var(--di-primary-dark);
    }

    /* ─── MATRIX TABLE PRO ─── */
    .table-matrix-pro {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }
    .table-matrix-pro th {
        background: #f8fafc;
        color: #475569;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 0.85rem 1rem;
        border-bottom: 1px solid #e2e8f0;
    }
    .table-matrix-pro td {
        padding: 0.85rem 1rem;
        border-bottom: 1px solid #f1f5f9;
        font-size: 0.84rem;
        vertical-align: middle;
    }
    .table-matrix-pro tr:last-child td {
        border-bottom: none;
    }
    .table-matrix-pro tr:hover td {
        background: #f8fafc;
    }

    /* ─── RESPONSIVE BREAKPOINTS ─── */
    @media (max-width: 991px) {
        .nav-segmented-control {
            width: 100%;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            text-align: center;
        }
        .nav-segment-link {
            justify-content: center;
            padding: 0.45rem 0.4rem;
            font-size: 0.76rem;
        }
    }

    @media (max-width: 576px) {
        .nav-segmented-control {
            grid-template-columns: repeat(2, 1fr);
            gap: 4px;
        }
        .nav-segment-link {
            padding: 0.45rem 0.5rem;
            font-size: 0.74rem;
        }
        .stat-card-pro {
            padding: 0.75rem 0.85rem;
            gap: 0.65rem;
        }
        .stat-icon-pro {
            width: 36px;
            height: 36px;
            min-width: 36px;
            font-size: 1rem;
        }
        .stat-val-pro {
            font-size: 1.1rem;
        }
        .filter-panel-pro {
            padding: 0.85rem;
        }
    }
</style>

<div class="dashboard-wrapper container-fluid px-0 px-md-2 mt-2 mb-5">
    
    <!-- ─── HEADER PAGE & SEGMENTED NAV ─── -->
    <div class="d-flex flex-column flex-xl-row justify-content-between align-items-start align-items-xl-center mb-4 px-1 gap-3">
        <div class="d-flex align-items-center">
            <div class="d-flex align-items-center justify-content-center rounded-3 me-3 shadow-sm flex-shrink-0" style="width: 48px; height: 48px; background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%); color: white;">
                <i class="fas fa-network-wired fa-lg"></i>
            </div>
            <div>
                <h3 class="fw-bold mb-0 text-dark" style="letter-spacing: -0.3px; font-size: 1.35rem;">Daftar & Matriks Izin</h3>
                <p class="text-muted mb-0" style="font-size: 0.84rem;">Inspeksi hak akses: cek role dan pengguna mana saja yang memiliki izin tertentu.</p>
            </div>
        </div>

        <!-- Segmented Navigation Control -->
        <nav class="nav-segmented-control" aria-label="Navigasi Pengaturan Izin">
            <a href="index.php" class="nav-segment-link">
                <i class="fas fa-user-shield"></i>
                <span>Izin User</span>
            </a>
            <a href="role.php" class="nav-segment-link">
                <i class="fas fa-layer-group"></i>
                <span>Default Role</span>
            </a>
            <a href="bulk.php" class="nav-segment-link">
                <i class="fas fa-users-cog"></i>
                <span>Edit Massal</span>
            </a>
            <a href="daftar_izin.php" class="nav-segment-link active">
                <i class="fas fa-list-check"></i>
                <span>Daftar Izin</span>
            </a>
        </nav>
    </div>

    <!-- ─── STATISTIK RINGKAS ─── -->
    <div class="row g-2 g-md-3 mb-4 px-1">
        <div class="col-6 col-lg-3">
            <div class="stat-card-pro">
                <div class="stat-icon-pro" style="background: #e0f2fe; color: #0284c7;">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <div>
                    <div class="stat-lbl-pro">Total Tiket Izin</div>
                    <div class="stat-val-pro"><?= $totalPermissions ?> <span class="text-muted fw-normal" style="font-size: 0.72rem;">izin</span></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card-pro">
                <div class="stat-icon-pro" style="background: #f3e8ff; color: #9333ea;">
                    <i class="fas fa-folder-open"></i>
                </div>
                <div>
                    <div class="stat-lbl-pro">Kategori Grup</div>
                    <div class="stat-val-pro"><?= $totalGroups ?> <span class="text-muted fw-normal" style="font-size: 0.72rem;">grup</span></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card-pro">
                <div class="stat-icon-pro" style="background: #fef3c7; color: #d97706;">
                    <i class="fas fa-id-badge"></i>
                </div>
                <div>
                    <div class="stat-lbl-pro">Role Terdaftar</div>
                    <div class="stat-val-pro"><?= $totalRoles ?> <span class="text-muted fw-normal" style="font-size: 0.72rem;">role</span></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card-pro">
                <div class="stat-icon-pro" style="background: #dcfce7; color: #16a34a;">
                    <i class="fas fa-users"></i>
                </div>
                <div>
                    <div class="stat-lbl-pro">Pengguna Aktif</div>
                    <div class="stat-val-pro"><?= $totalUsers ?> <span class="text-muted fw-normal" style="font-size: 0.72rem;">user</span></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ─── TOOLBAR PENCARIAN & FILTER ─── -->
    <div class="filter-panel-pro">
        <div class="d-flex flex-column flex-md-row align-items-stretch align-items-md-center justify-content-between gap-2 mb-3">
            <div class="search-wrapper-pro">
                <i class="fas fa-search search-icon"></i>
                <input type="text" id="liveSearchInput" placeholder="Cari nama izin, kode teknis (misal: arsip_delete), role, atau pengguna..." autocomplete="off">
                <i class="fas fa-times clear-icon" id="clearSearchBtn" onclick="clearSearch()" title="Hapus pencarian"></i>
            </div>
            
            <div class="d-flex align-items-center justify-content-between justify-content-md-end gap-2">
                <span id="resultCountBadge" class="badge bg-light text-secondary border px-2.5 py-1.5" style="font-size: 0.75rem; font-weight: 500;">
                    Menampilkan <strong id="visibleCountNum" class="text-dark"><?= $totalPermissions ?></strong> izin
                </span>
                <div class="d-flex gap-1">
                    <button type="button" class="btn-view-toggle active" id="btnViewCards" onclick="switchViewMode('cards')" title="Tampilan Kartu">
                        <i class="fas fa-th-large"></i> <span class="d-none d-sm-inline">Kartu</span>
                    </button>
                    <button type="button" class="btn-view-toggle" id="btnViewTable" onclick="switchViewMode('table')" title="Tampilan Tabel Matriks">
                        <i class="fas fa-table"></i> <span class="d-none d-sm-inline">Tabel</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Filter Category Horizontal Scroll Track -->
        <div class="cat-scroll-track" id="categoryPillContainer">
            <button type="button" class="cat-chip active" data-cat="all" onclick="filterCategory('all', this)">
                <i class="fas fa-border-all fa-xs"></i> Semua Grup <span class="count-badge" id="count_all"><?= $totalPermissions ?></span>
            </button>
            <?php foreach ($grupList as $g): 
                $countInGroup = count($permissions[$g] ?? []);
            ?>
                <button type="button" class="cat-chip" data-cat="<?= htmlspecialchars($g) ?>" onclick="filterCategory('<?= htmlspecialchars(addslashes($g)) ?>', this)">
                    <?= htmlspecialchars($g) ?> <span class="count-badge" data-group-count="<?= htmlspecialchars($g) ?>"><?= $countInGroup ?></span>
                </button>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ─── CONTAINER VIEW 1: KARTU GRID INTERAKTIF ─── -->
    <div id="containerCardView">
        <div class="row g-3 px-1" id="permCardsGrid">
            <?php foreach ($allPermissionsList as $perm): 
                $permId = $perm['id'];
                $rolesGranted = $perm['roles'];
                $usersGranted = $perm['users'];
                $totalGrantedUsers = count($usersGranted);
                
                $searchContent = strtolower($perm['nama_izin'] . ' ' . $perm['deskripsi'] . ' ' . $perm['grup']);
                foreach ($rolesGranted as $rKey) {
                    $searchContent .= ' ' . strtolower($rolesMap[$rKey] ?? $rKey);
                }
                foreach ($usersGranted as $uObj) {
                    $searchContent .= ' ' . strtolower($uObj['nama_lengkap'] . ' ' . $uObj['username']);
                }
            ?>
                <div class="col-md-6 col-xl-4 perm-item" data-group="<?= htmlspecialchars($perm['grup']) ?>" data-search="<?= htmlspecialchars($searchContent) ?>">
                    <div class="perm-card-pro">
                        <!-- Header Kartu -->
                        <div class="perm-card-pro-header">
                            <h5 class="fw-bold text-dark mb-2" style="font-size: 0.94rem; line-height: 1.35;">
                                <?= htmlspecialchars($perm['deskripsi']) ?>
                            </h5>
                            <div class="d-flex align-items-center gap-1">
                                <span class="perm-code-tag">
                                    <i class="fas fa-terminal text-muted fa-xs"></i>
                                    <span><?= htmlspecialchars($perm['nama_izin']) ?></span>
                                </span>
                                <button type="button" class="btn-copy-code" onclick="copyPermCode('<?= htmlspecialchars(addslashes($perm['nama_izin'])) ?>', this)" title="Salin kode izin">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Body Kartu -->
                        <div class="perm-card-pro-body">
                            <!-- Bagian 1: Role Default -->
                            <div>
                                <div class="section-label-pro">
                                    <span><i class="fas fa-layer-group text-purple me-1"></i> Role Default</span>
                                    <span class="badge bg-light text-muted border px-1.5 py-0.5" style="font-size: 0.68rem; font-weight: 600;"><?= count($rolesGranted) + 1 ?> Role</span>
                                </div>
                                <div class="d-flex flex-wrap gap-1 mt-1">
                                    <span class="chip-role-default chip-role-admin" title="Admin selalu memiliki akses root ke semua izin">
                                        <i class="fas fa-crown text-warning fa-xs"></i> Admin
                                    </span>
                                    
                                    <?php if (!empty($rolesGranted)): ?>
                                        <?php foreach ($rolesGranted as $rKey): 
                                            $rName = $rolesMap[$rKey] ?? ucfirst($rKey);
                                        ?>
                                            <a href="role.php?role=<?= urlencode($rKey) ?>" class="chip-role-default" title="Klik untuk atur default izin role <?= htmlspecialchars($rName) ?>">
                                                <i class="fas fa-tag fa-xs opacity-75"></i> <?= htmlspecialchars($rName) ?>
                                            </a>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <hr class="my-0" style="border-color: #f1f5f9;">

                            <!-- Bagian 2: Pengguna Khusus yang Memiliki Izin -->
                            <div>
                                <div class="section-label-pro">
                                    <span><i class="fas fa-user-check text-success me-1"></i> Pengguna Memiliki Izin</span>
                                    <span class="badge bg-light text-muted border px-1.5 py-0.5" style="font-size: 0.68rem; font-weight: 600;"><?= $totalGrantedUsers ?> User</span>
                                </div>
                                
                                <?php if (!empty($usersGranted)): ?>
                                    <div class="d-flex flex-wrap gap-1 mt-1">
                                        <?php 
                                        $initialLimit = 6;
                                        $displayUsers = array_slice($usersGranted, 0, $initialLimit);
                                        $hiddenUsers = array_slice($usersGranted, $initialLimit);
                                        ?>
                                        <?php foreach ($displayUsers as $u): 
                                            $initial = strtoupper(substr($u['nama_lengkap'], 0, 1));
                                        ?>
                                            <a href="index.php?user_id=<?= (int)$u['id'] ?>" class="chip-user-pro" title="Atur izin <?= htmlspecialchars($u['nama_lengkap']) ?> (@<?= htmlspecialchars($u['username']) ?>)">
                                                <span class="chip-user-avatar"><?= $initial ?></span>
                                                <span class="fw-semibold"><?= htmlspecialchars($u['nama_lengkap']) ?></span>
                                            </a>
                                        <?php endforeach; ?>

                                        <?php if (!empty($hiddenUsers)): ?>
                                            <?php foreach ($hiddenUsers as $u): 
                                                $initial = strtoupper(substr($u['nama_lengkap'], 0, 1));
                                            ?>
                                                <a href="index.php?user_id=<?= (int)$u['id'] ?>" class="chip-user-pro user-extra-chip user-extra-<?= $permId ?>" style="display: none !important;" title="Atur izin <?= htmlspecialchars($u['nama_lengkap']) ?> (@<?= htmlspecialchars($u['username']) ?>)">
                                                    <span class="chip-user-avatar"><?= $initial ?></span>
                                                    <span class="fw-semibold"><?= htmlspecialchars($u['nama_lengkap']) ?></span>
                                                </a>
                                            <?php endforeach; ?>
                                            <button type="button" class="btn-expand-users" data-count="<?= count($hiddenUsers) ?>" onclick="toggleExtraUsers(<?= $permId ?>, this)">
                                                +<?= count($hiddenUsers) ?> lainnya
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-muted small py-1" style="font-size: 0.75rem;">
                                        <i class="fas fa-check-circle text-muted opacity-75 me-1"></i> Berlaku otomatis melalui penugasan role default.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ─── CONTAINER VIEW 2: TABEL MATRIKS PRO ─── -->
    <div id="containerTableView" style="display: none;">
        <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
            <div class="table-responsive">
                <table class="table-matrix-pro mb-0">
                    <thead>
                        <tr>
                            <th style="width: 25%;">Deskripsi Izin</th>
                            <th style="width: 16%;">Kode Izin</th>
                            <th style="width: 12%;">Grup</th>
                            <th style="width: 20%;">Role Default</th>
                            <th style="width: 20%;">Pengguna Izin Khusus</th>
                            <th style="width: 7%;" class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="permTableBody">
                        <?php foreach ($allPermissionsList as $perm): 
                            $permId = $perm['id'];
                            $rolesGranted = $perm['roles'];
                            $usersGranted = $perm['users'];
                            $searchContent = strtolower($perm['nama_izin'] . ' ' . $perm['deskripsi'] . ' ' . $perm['grup']);
                            foreach ($rolesGranted as $rKey) { $searchContent .= ' ' . strtolower($rolesMap[$rKey] ?? $rKey); }
                            foreach ($usersGranted as $uObj) { $searchContent .= ' ' . strtolower($uObj['nama_lengkap'] . ' ' . $uObj['username']); }
                        ?>
                            <tr class="perm-item" data-group="<?= htmlspecialchars($perm['grup']) ?>" data-search="<?= htmlspecialchars($searchContent) ?>">
                                <td>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($perm['deskripsi']) ?></div>
                                </td>
                                <td>
                                    <span class="perm-code-tag"><?= htmlspecialchars($perm['nama_izin']) ?></span>
                                </td>
                                <td>
                                    <span class="badge" style="background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; font-size: 0.72rem; font-weight: 600;">
                                        <?= htmlspecialchars($perm['grup']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex flex-wrap gap-1">
                                        <span class="chip-role-default chip-role-admin py-0 px-2" style="font-size: 0.7rem;">Admin</span>
                                        <?php foreach ($rolesGranted as $rKey): 
                                            $rName = $rolesMap[$rKey] ?? ucfirst($rKey);
                                        ?>
                                            <a href="role.php?role=<?= urlencode($rKey) ?>" class="chip-role-default py-0 px-2" style="font-size: 0.7rem;"><?= htmlspecialchars($rName) ?></a>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($usersGranted)): ?>
                                        <div class="d-flex flex-wrap gap-1">
                                            <?php foreach (array_slice($usersGranted, 0, 4) as $u): ?>
                                                <a href="index.php?user_id=<?= (int)$u['id'] ?>" class="badge bg-light text-dark border text-decoration-none" style="font-size: 0.72rem;" title="Atur izin <?= htmlspecialchars($u['nama_lengkap']) ?>">
                                                    <?= htmlspecialchars($u['nama_lengkap']) ?>
                                                </a>
                                            <?php endforeach; ?>
                                            <?php if (count($usersGranted) > 4): ?>
                                                <span class="badge bg-secondary-subtle text-secondary" style="font-size: 0.7rem;">+<?= count($usersGranted) - 4 ?> lainnya</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted small" style="font-size: 0.75rem;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <a href="bulk.php" class="btn btn-sm btn-outline-primary py-1 px-2 rounded-3" style="font-size: 0.75rem;" title="Edit Massal">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Empty State jika filter/pencarian tidak ditemukan -->
    <div id="noResultsState" class="card shadow-sm border-0 rounded-4 p-5 text-center my-4" style="display: none;">
        <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3 bg-light mx-auto" style="width: 64px; height: 64px;">
            <i class="fas fa-search fa-2x text-muted opacity-50"></i>
        </div>
        <h5 class="fw-bold text-dark mb-1">Tiket Izin Tidak Ditemukan</h5>
        <p class="text-muted small mb-3">Tidak ada tiket izin atau pengguna yang cocok dengan kriteria pencarian.</p>
        <div>
            <button type="button" class="btn btn-sm btn-outline-primary px-3 rounded-pill" onclick="resetAllFilters()">
                <i class="fas fa-sync-alt me-1"></i> Reset Filter
            </button>
        </div>
    </div>

</div>

<!-- ─── LIGHTWEIGHT JAVASCRIPT: FILTER, SEARCH & UX HELPERS ─── -->
<script>
let currentCategory = 'all';
let currentSearchQuery = '';

function switchViewMode(mode) {
    const cardView = document.getElementById('containerCardView');
    const tableView = document.getElementById('containerTableView');
    const btnCards = document.getElementById('btnViewCards');
    const btnTable = document.getElementById('btnViewTable');

    if (mode === 'table') {
        cardView.style.display = 'none';
        tableView.style.display = 'block';
        btnTable.classList.add('active');
        btnCards.classList.remove('active');
    } else {
        cardView.style.display = 'block';
        tableView.style.display = 'none';
        btnCards.classList.add('active');
        btnTable.classList.remove('active');
    }
}

function selectCategoryByName(catName) {
    const targetPill = document.querySelector(`.cat-chip[data-cat="${catName}"]`);
    if (targetPill) {
        filterCategory(catName, targetPill);
    }
}

function filterCategory(category, element) {
    currentCategory = category;
    
    document.querySelectorAll('.cat-chip').forEach(el => el.classList.remove('active'));
    if (element) {
        element.classList.add('active');
        // Scroll active chip into view smoothly on mobile/desktop
        element.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
    }

    applyFilters();
}

function applyFilters() {
    const cardItems = document.querySelectorAll('#permCardsGrid .perm-item');
    const tableItems = document.querySelectorAll('#permTableBody .perm-item');
    let visibleCount = 0;
    const query = currentSearchQuery.toLowerCase();

    // Hitung kecocokan per grup untuk filter pill badges
    const groupMatches = {};
    let totalSearchMatches = 0;

    cardItems.forEach(item => {
        const itemGroup = item.getAttribute('data-group');
        const itemSearch = item.getAttribute('data-search') || '';

        const matchSearch = (query === '' || itemSearch.includes(query));
        if (matchSearch) {
            groupMatches[itemGroup] = (groupMatches[itemGroup] || 0) + 1;
            totalSearchMatches++;
        }

        const matchCat = (currentCategory === 'all' || itemGroup === currentCategory);
        if (matchCat && matchSearch) {
            item.style.display = '';
            visibleCount++;
        } else {
            item.style.display = 'none';
        }
    });

    tableItems.forEach(item => {
        const itemGroup = item.getAttribute('data-group');
        const itemSearch = item.getAttribute('data-search') || '';

        const matchCat = (currentCategory === 'all' || itemGroup === currentCategory);
        const matchSearch = (query === '' || itemSearch.includes(query));

        if (matchCat && matchSearch) {
            item.style.display = '';
        } else {
            item.style.display = 'none';
        }
    });

    // Sinkronkan badge angka pada pill kategori di atas sesuai pencarian
    const allCountBadge = document.getElementById('count_all');
    if (allCountBadge) {
        allCountBadge.textContent = totalSearchMatches;
    }
    document.querySelectorAll('.cat-chip[data-cat]').forEach(chip => {
        const catName = chip.getAttribute('data-cat');
        if (catName !== 'all') {
            const countBadge = chip.querySelector('.count-badge');
            if (countBadge) {
                const count = groupMatches[catName] || 0;
                countBadge.textContent = count;
                if (count === 0 && query !== '') {
                    chip.style.opacity = '0.45';
                } else {
                    chip.style.opacity = '1';
                }
            }
        }
    });

    // Update Counter Hasil
    const visibleCounter = document.getElementById('visibleCountNum');
    if (visibleCounter) {
        visibleCounter.textContent = visibleCount;
    }

    // Toggle Empty State
    const noResults = document.getElementById('noResultsState');
    if (visibleCount === 0) {
        noResults.style.display = 'block';
    } else {
        noResults.style.display = 'none';
    }
}

function clearSearch() {
    const input = document.getElementById('liveSearchInput');
    const clearBtn = document.getElementById('clearSearchBtn');
    if (input) {
        input.value = '';
        currentSearchQuery = '';
        if (clearBtn) clearBtn.style.display = 'none';
        applyFilters();
        input.focus();
    }
}

function resetAllFilters() {
    clearSearch();
    const allPill = document.querySelector('.cat-chip[data-cat="all"]');
    if (allPill) {
        filterCategory('all', allPill);
    }
}

function toggleExtraUsers(permId, btnElement) {
    const extraChips = document.querySelectorAll('.user-extra-' + permId);
    if (!extraChips || extraChips.length === 0) return;

    const firstChip = extraChips[0];
    const isHidden = (firstChip.style.display === 'none' || window.getComputedStyle(firstChip).display === 'none');

    extraChips.forEach(chip => {
        chip.style.setProperty('display', isHidden ? 'inline-flex' : 'none', 'important');
    });

    if (isHidden) {
        btnElement.innerHTML = '<i class="fas fa-chevron-up fa-xs"></i> Ciutkan';
    } else {
        const count = btnElement.getAttribute('data-count') || '';
        btnElement.innerHTML = '+' + count + ' lainnya';
    }
}

function copyPermCode(code, btnElement) {
    navigator.clipboard.writeText(code).then(() => {
        const icon = btnElement.querySelector('i');
        if (icon) {
            icon.className = 'fas fa-check text-success';
            setTimeout(() => {
                icon.className = 'fas fa-copy';
            }, 1500);
        }
    }).catch(err => {
        console.error('Gagal menyalin kode: ', err);
    });
}

document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('liveSearchInput');
    const clearBtn = document.getElementById('clearSearchBtn');

    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            currentSearchQuery = e.target.value.trim();
            if (clearBtn) {
                clearBtn.style.display = currentSearchQuery.length > 0 ? 'block' : 'none';
            }
            applyFilters();
        });
    }

    // Keyboard shortcut: Press "/" to focus search (if not already focused on an input)
    document.addEventListener('keydown', function(e) {
        if (e.key === '/' && !['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) {
            e.preventDefault();
            if (searchInput) searchInput.focus();
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
