<?php
// =================================================================
// MARKAS KOMANDO APLIKASI
// =================================================================

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_lifetime', 0);
    session_start();
}

// Protokol 1: Panggil file config sakti kita, ini yang paling pertama!
require_once __DIR__ . '/config.php'; 

// Protokol 2: Panggil file dari folder yang sama
require_once __DIR__ . '/db.php';
// Protokol 3: Panggil file dari folder yang sama
require_once __DIR__ . '/auth.php';
// ✅ Protokol 4: Panggil kotak perkakas helper kita!
require_once __DIR__ . '/helpers.php';

// Protokol 5: Siapin data-data umum
date_default_timezone_set('Asia/Jakarta');

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Pelanggaran Santri</title>
    
    <!-- Aset & Font -->
    <!-- REVISI: Path favicon jadi dinamis pake BASE_URL -->
    <link rel="icon" type="image/png" sizes="64x64" href="<?= BASE_URL ?>/assets/logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
    /* =================================================================
    STYLESHEET UTAMA APLIKASI
    ================================================================= */
    :root {
        --sidebar-width: 260px;
        --header-height: 70px;
        --main-bg-color: #f4f7fa;
        --border-color: #e9ecef;
        --blue: #0d6efd; --green: #198754; --orange: #fd7e14;
        --purple: #6f42c1; --teal: #20c997; --indigo: #6610f2;
        --red: #dc3545; --gray: #6c757d; --cyan: #0dcaf0;
    }
    body {
        background-color: var(--main-bg-color);
        font-family: 'Poppins', sans-serif;
        display: flex;
        min-height: 100vh;
    }
    
    /* Layout Utama */
    .sidebar {
        width: var(--sidebar-width);
        position: fixed;
        top: 0;
        left: 0;
        height: 100vh;
        background-color: #fff;
        border-right: 1px solid var(--border-color);
        padding: 1rem;
        z-index: 1021;
        display: flex; 
        flex-direction: column; 
    }
    .main-content {
        flex-grow: 1;
        padding: 1.5rem;
        width: calc(100% - var(--sidebar-width));
        margin-left: var(--sidebar-width);
    }
    .header {
        height: var(--header-height);
        background-color: #fff;
        border: 1px solid var(--border-color);
        z-index: 1020;
    }
    
    /* Tampilan Mobile & Tablet */
    @media (max-width: 991.98px) {
        .main-content {
            margin-left: 0;
            width: 100%;
            padding: 1rem;
        }
        #sidebarOffcanvas {
            width: 300px !important; 
        }
    }
    @media (max-width: 576px) {
        .app-name {
            font-size: 1.05rem !important;
            font-weight: 600;
            letter-spacing: 0.5px;
            color: #25396f;
            margin-top: 2px;
        }
    }

    /* Styling Navigasi Sidebar & Offcanvas */
    .sidebar .nav-link, .offcanvas .nav-link {
        color: #5a6a85 !important;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        font-size: 15px;
        padding: 0.75rem 1rem;
        border-radius: 8px;
        margin-bottom: 0.25rem;
        transition: none; 
    }
    .sidebar .nav-link i, .offcanvas .nav-link i {
        font-size: 1.1rem;
        width: 20px;
        text-align: center;
    }
    .sidebar .nav-link.active {
        background-color: transparent !important;
        font-weight: 600;
    }
    .sidebar .nav-link:hover { background-color: transparent; }

    /* Efek Warna Hover & Aktif Lengkap */
    .apply-color-hover-active.color-blue { color: var(--blue) !important; }
    .apply-color-hover-active.color-blue i { color: var(--blue) !important; }
    .apply-color-hover-active.color-green { color: var(--green) !important; }
    .apply-color-hover-active.color-green i { color: var(--green) !important; }
    .apply-color-hover-active.color-orange { color: var(--orange) !important; }
    .apply-color-hover-active.color-orange i { color: var(--orange) !important; }
    .apply-color-hover-active.color-purple { color: var(--purple) !important; }
    .apply-color-hover-active.color-purple i { color: var(--purple) !important; }
    .apply-color-hover-active.color-teal { color: var(--teal) !important; }
    .apply-color-hover-active.color-teal i { color: var(--teal) !important; }
    .apply-color-hover-active.color-indigo { color: var(--indigo) !important; }
    .apply-color-hover-active.color-indigo i { color: var(--indigo) !important; }
    .apply-color-hover-active.color-red { color: var(--red) !important; }
    .apply-color-hover-active.color-red i { color: var(--red) !important; }
    .apply-color-hover-active.color-gray { color: var(--gray) !important; }
    .apply-color-hover-active.color-gray i { color: var(--gray) !important; }
    .apply-color-hover-active.color-cyan { color: var(--cyan) !important; }
    .apply-color-hover-active.color-cyan i { color: var(--cyan) !important; }
    
    /* Info User & Tombol Logout */
    .header-logo { height: 45px; }
    .app-name { font-size: 1.25rem; }
    .user-info .user-details { line-height: 1.3; }
    .user-info .user-name {
        font-weight: 600;
        color: #334155;
        font-size: 0.9rem;
    }
    .user-info .user-role { font-size: 0.75rem; }
    .user-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background-color: #e0e7ff;
        color: #4f46e5;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 1rem;
        text-transform: uppercase;
    }
    .btn-logout {
        border: none !important;
        background-color: #fee2e2 !important; 
        color: #dc2626 !important; 
    }
    .btn-logout:hover {
        background-color: #dc2626 !important;
        color: #fff !important; 
    }
    .offcanvas-user-info {
        padding: 1rem;
        padding-top: 0.75rem;
        margin-top: 0; 
        border-top: 1px solid var(--border-color);
        flex-shrink: 0;
    }
    .offcanvas-body {
        display: flex;
        flex-direction: column;
        padding: 0;
    }
    .sidebar-menu-wrapper {
        flex-grow: 1;
        overflow-y: auto;
        padding: 1rem;
    }
    .offcanvas .user-avatar {
        width: 36px; height: 36px; font-size: 0.9rem;
    }
    .offcanvas .user-name { font-size: 0.85rem; }
    .offcanvas .user-role { font-size: 0.7rem; }
    .offcanvas .mb-3 {
        margin-bottom: 0.75rem !important;
    }
</style>
</head>
<body>
    <!-- Sidebar untuk Desktop -->
    <nav class="sidebar d-none d-lg-block shadow-sm">
        <?php include __DIR__ . '/sidebar.php'; ?>
    </nav>

    <!-- Sidebar untuk Mobile (Offcanvas) -->
    <div class="offcanvas offcanvas-start" tabindex="-1" id="sidebarOffcanvas">
        <div class="offcanvas-header border-bottom">
            <h5 class="offcanvas-title fw-bold" style="color: #25396f;">Menu Navigasi</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body">
            <!-- Wrapper untuk menu yang bisa di-scroll -->
            <div class="sidebar-menu-wrapper">
                <?php include __DIR__ . '/sidebar.php'; ?>
            </div>
            
            <!-- Info User & Logout di Offcanvas -->
            <div class="offcanvas-user-info">
                <div class="d-flex align-items-center mb-3">
                    <div class="user-avatar me-3">
                        <span><?= htmlspecialchars(substr($_SESSION['nama_lengkap'] ?? 'P', 0, 1)) ?></span>
                    </div>
                    <div class="user-details">
                        <span class="user-name"><?= htmlspecialchars($_SESSION['nama_lengkap'] ?? 'Pengguna') ?></span>
                        <span class="user-role text-muted ms-1"> <?= htmlspecialchars(ucfirst($_SESSION['role'] ?? 'Role')) ?> </span>
                    </div>
                </div>
                <!-- REVISI: Path logout jadi dinamis pake BASE_URL -->
                <a class="btn btn-sm d-flex align-items-center justify-content-center rounded-pill px-3 py-2 btn-logout w-100" href="<?= BASE_URL ?>/logout.php">
                    <i class="fas fa-sign-out-alt me-2"></i>Keluar
                </a>
            </div>
        </div>
    </div>

    <!-- Konten Utama (Kanan) -->
    <main class="main-content">
        <!-- Header yang nempel di atas -->
        <header class="header sticky-top mb-4 shadow-sm rounded">
            <nav class="navbar h-100">
                <div class="container-fluid d-flex align-items-center justify-content-between">
                    <!-- Kiri: Burger (Mobile) & Logo (Semua Tampilan) -->
                    <div class="d-flex align-items-center">
                        <button class="btn btn-light d-lg-none me-3" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas">
                            <i class="fas fa-bars"></i>
                        </button>
                        <div class="d-flex align-items-center">
                            <!-- REVISI: Path logo jadi dinamis pake BASE_URL -->
                            <img src="<?= BASE_URL ?>/assets/logo.png" alt="Logo" class="header-logo me-2">
                            <span class="fw-bold app-name align-middle" style="color: #25396f;">Pendataan Mukholif</span>
                        </div>
                    </div>
                    
                    <!-- Kanan Header (Desktop) -->
                    <div class="d-none d-lg-flex align-items-center">
                        <div class="user-info d-flex align-items-center">
                            <div class="user-details text-end me-3">
                                <span class="user-name"><?= htmlspecialchars($_SESSION['nama_lengkap'] ?? 'Pengguna') ?></span>
                                <span class="user-role text-muted ms-1"> <?= htmlspecialchars(ucfirst($_SESSION['role'] ?? 'Role')) ?> </span>
                            </div>
                            <div class="user-avatar">
                                <span><?= htmlspecialchars(substr($_SESSION['nama_lengkap'] ?? 'P', 0, 1)) ?></span>
                            </div>
                        </div>
                        <div class="vr mx-3"></div>
                        <!-- REVISI: Path logout jadi dinamis pake BASE_URL -->
                        <a class="btn btn-sm d-flex align-items-center rounded-pill px-3 py-2 btn-logout" href="<?= BASE_URL ?>/logout.php" title="Logout">
                            <i class="fas fa-sign-out-alt"></i>
                            <span class="ms-2 d-none d-xl-inline">Keluar</span>
                        </a>
                    </div>
                </div>
            </nav>
        </header>

        <!-- ✅ LOGIKA NOTIFIKASI GANDA -->
        <?php
        // Prioritaskan sistem notifikasi BARU dari helpers.php
        display_flash_message();

        // Sistem notifikasi LAMA (untuk kompatibilitas mundur)
        if (isset($_SESSION['success_message'])) {
            $message = $_SESSION['success_message'];
            unset($_SESSION['success_message']);
            echo "
            <div class='alert alert-success alert-dismissible fade show' role='alert'>
                <i class='fas fa-check-circle me-2'></i> {$message}
                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
            </div>
            ";
        }
        if (isset($_SESSION['error_message'])) {
            $message = $_SESSION['error_message'];
            unset($_SESSION['error_message']);
            echo "
            <div class='alert alert-danger alert-dismissible fade show' role='alert'>
                <i class='fas fa-times-circle me-2'></i> {$message}
                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
            </div>
            ";
        }
        ?>
        <!-- KONTEN HALAMAN DIMULAI DI SINI -->