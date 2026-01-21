<?php
// AMBIL VERSI LOGO OTOMATIS (CACHE BUSTING)
// Path ini harus nunjuk ke file di server, BUKAN URL.
// Kita asumsi /assets/logo.png ada di folder root (htdocs/public_html)
// Kalau project lu di subfolder (misal: htdocs/aplikasi-santri), ganti path-nya jadi:
// $logo_server_path = $_SERVER['DOCUMENT_ROOT'] . '/aplikasi-santri/assets/logo.png';
$logo_server_path = $_SERVER['DOCUMENT_ROOT'] . '/assets/logo.png';
$logo_version = file_exists($logo_server_path)
    ? filemtime($logo_server_path)
    : '1'; // Fallback jika file tidak ditemukan
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AsuhTrack</title>
    
    <!-- Aset & Font -->
    <!-- REVISI: Path favicon jadi dinamis + otomatis cache bust -->
    <link rel="icon" type="image/png" sizes="64x64" href="<?= BASE_URL ?>/assets/logo.png?v=<?= $logo_version ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    
    <!-- Custom Style (Centralized) -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/style.css?v=<?= time() ?>">
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
                            <!-- REVISI: Path logo jadi dinamis + otomatis cache bust -->
                            <img src="<?= BASE_URL ?>/assets/logo.png?v=<?= $logo_version ?>" alt="Logo" class="header-logo me-2">
                            <span class="fw-bold app-name align-middle" style="color: #25396f;">AsuhTrack</span>
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