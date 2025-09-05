<?php
// =================================================================
// MARKAS KOMANDO APLIKASI
// =================================================================

// Protokol 1: Nyalain HT (Mulai Session)
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_lifetime', 0);
    session_start();
}

// Protokol 2: Ambil Peta Denah Dufan (Koneksi Database)
require_once __DIR__ . '/db.php';

// Protokol 3: Panggil Kepala Satpam (File Otorisasi)
require_once __DIR__ . '/auth.php';

// Protokol 4: Siapin data-data umum
date_default_timezone_set('Asia/Jakarta');
$success_message = $_SESSION['success_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']);

// MARKAS KOMANDO SELESEI
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Pelanggaran Santri</title>
    <link rel="icon" type="image/png" sizes="64x64" href="/rekap-mukholif/assets/logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <!-- Google Fonts (Poppins) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome (Ikon) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
<style>
    /* =================================================================
        KODE DASAR (TIDAK DIUBAH)
    ================================================================= */
    :root {
        --sidebar-width: 260px;
        --header-height: 70px;
        --main-bg-color: #f4f7fa;
        --border-color: #e9ecef;
    }
    body {
        background-color: var(--main-bg-color);
        font-family: 'Poppins', sans-serif;
        display: flex;
        min-height: 100vh;
    }
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
        display: flex; /* Tambahan untuk layouting footer */
        flex-direction: column; /* Tambahan */
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
    @media (max-width: 991.98px) {
        .main-content {
            margin-left: 0;
            width: 100%;
            padding: 1rem;
        }
    }
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
        transition: none; /* MATIKAN SEMUA TRANSISI */
    }
    .sidebar .nav-link i, .offcanvas .nav-link i {
        font-size: 1.1rem;
        width: 20px;
        text-align: center;
    }
    .toast-container { z-index: 1100 !important; }
    .header-logo { height: 45px; }
    .app-name { font-size: 1.25rem; }

    @media (max-width: 576px) {
        .app-name { font-size: 1rem; }
        .offcanvas.offcanvas-start {
            width: 85%;
            max-width: 340px;
        }
    }

    /* =================================================================
        STYLING SIDEBAR (TIDAK DIUBAH)
    ================================================================= */
    :root {
        --blue: #0d6efd; --green: #198754; --orange: #fd7e14;
        --purple: #6f42c1; --teal: #20c997; --indigo: #6610f2;
        --red: #dc3545; --gray: #6c757d; --cyan: #0dcaf0;
    }
    .sidebar .nav-link.active {
        background-color: transparent !important;
        font-weight: 600;
    }
    .sidebar .nav-link:hover { background-color: transparent; }
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

    /* =================================================================
        ✅ CSS BARU: INFO USER DI HEADER & SIDEBAR MOBILE ✅
    ================================================================= */
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
        border: none;
        background-color: #fee2e2;
        color: #dc2626;
    }
    .btn-logout:hover {
        background-color: #dc2626;
        color: #fff;
    }
    
    /* Style untuk info user di dalam offcanvas (sidebar mobile) */
    .offcanvas-user-info {
        padding-top: 1rem;
        margin-top: auto; /* Ini kunci buat nempelin ke bawah */
        border-top: 1px solid var(--border-color);
    }
    .offcanvas-user-info .user-details {
        line-height: 1.4;
    }
    .offcanvas-body {
        display: flex;
        flex-direction: column;
    }
</style>
</head>
<body>
    <!-- Sidebar untuk Desktop -->
    <nav class="sidebar d-none d-lg-block shadow-sm">
        <!-- Taruh menu utama di sini -->
        <?php include __DIR__ . '/sidebar.php'; ?>
    </nav>

    <!-- Sidebar untuk Mobile (Offcanvas) -->
    <div class="offcanvas offcanvas-start" tabindex="-1" id="sidebarOffcanvas">
        <div class="offcanvas-header border-bottom">
            <h5 class="offcanvas-title fw-bold" style="color: #25396f;">Menu Navigasi</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body">
            <!-- Menu utama, sama seperti desktop -->
            <?php include __DIR__ . '/sidebar.php'; ?>
            
            <!-- ✅ BAGIAN INFO USER KHUSUS MOBILE ✅ -->
            <div class="offcanvas-user-info">
                <div class="d-flex align-items-center mb-3">
                    <div class="user-avatar me-3">
                        <span><?= htmlspecialchars(substr($_SESSION['nama_lengkap'] ?? 'P', 0, 1)) ?></span>
                    </div>
                    <div class="user-details">
                        <span class="user-name"><?= htmlspecialchars($_SESSION['nama_lengkap'] ?? 'Pengguna') ?></span>
                        <span class="user-role text-muted"><?= htmlspecialchars(ucfirst($_SESSION['role'] ?? 'Role')) ?></span>
                    </div>
                </div>
                <a class="btn btn-sm d-flex align-items-center justify-content-center rounded-pill px-3 py-2 btn-logout w-100" href="/rekap-mukholif/logout.php">
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
                            <img src="/rekap-mukholif/assets/logo.png?v=2" alt="Logo" class="header-logo me-2">
                            <span class="fw-bold app-name" style="color: #25396f;">Pendataan Mukholif</span>
                        </div>
                    </div>
                    
                    <!-- ✅ BAGIAN KANAN HEADER (HANYA UNTUK DESKTOP) ✅ -->
                    <div class="d-none d-lg-flex align-items-center">
                        <!-- Info User -->
                        <div class="user-info d-flex align-items-center">
                            <div class="user-details text-end me-3">
                                <span class="user-name"><?= htmlspecialchars($_SESSION['nama_lengkap'] ?? 'Pengguna') ?></span>
                                <span class="user-role text-muted"><?= htmlspecialchars(ucfirst($_SESSION['role'] ?? 'Role')) ?></span>
                            </div>
                            <div class="user-avatar">
                                <span><?= htmlspecialchars(substr($_SESSION['nama_lengkap'] ?? 'P', 0, 1)) ?></span>
                            </div>
                        </div>
                        <!-- Pemisah -->
                        <div class="vr mx-3"></div>
                        <!-- Tombol Logout -->
                        <a class="btn btn-sm d-flex align-items-center rounded-pill px-3 py-2 btn-logout" href="/rekap-mukholif/logout.php" title="Logout">
                            <i class="fas fa-sign-out-alt"></i>
                            <span class="ms-2">Keluar</span>
                        </a>
                    </div>
                </div>
            </nav>
        </header>

        <!-- KONTEN HALAMAN DIMULAI DI SINI -->