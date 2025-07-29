<?php
// ✅ Set session lifetime DULU sebelum session dimulai
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_lifetime', 0); // Auto logout saat browser ditutup
    session_start();
}

if (!isset($_SESSION['user']) && basename($_SERVER['PHP_SELF']) != 'login.php') {
    header("Location: login.php");
    exit;
}

// Set default timezone to Asia/Jakarta
date_default_timezone_set('Asia/Jakarta');

// Check for session messages
$success_message = $_SESSION['success'] ?? null;
$error_message = $_SESSION['error'] ?? null;

// Clear session messages after displaying
unset($_SESSION['success']);
unset($_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="id">    
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Pelanggaran Santri</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.css">
<style>
    /* === Sidebar Link Style === */
    .sidebar .nav-link[href='../index.php'] i { color: #0d6efd !important; }
    .sidebar .nav-link[href='../santri/'] i { color: #20c997 !important; }
    .sidebar .nav-link[href='../jenis-pelanggaran'] i { color: #fd7e14 !important; }
    .sidebar .nav-link[href='../pelanggaran'] i { color: #6c757d !important; }
    .sidebar .nav-link[href='../rekap'] i { color: #6f42c1 !important; }
    .sidebar .nav-link[href='../eksekusi'] i { color: #e83e8c !important; }

    .sidebar .nav-link:hover i {
        transform: scale(1.1);
        transition: 0.2s ease-in-out;
    }

    .sidebar .nav-link {
        padding-left: 1rem;
        color: #333 !important;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-family: 'Poppins', sans-serif;
        font-size: 15px;
    }

    .sidebar .nav-link:hover {
        background-color: #e9ecef;
        color: #000 !important;
    }

    .sidebar .nav-link i {
        margin-right: 0.5rem;
    }

    /* === Navbar Style === */
    .navbar {
        padding-top: 0.5rem;
        padding-bottom: 0.5rem;
    }

    .navbar-brand {
        font-size: 1rem;
    }

    @media (min-width: 576px) {
        .navbar-brand { font-size: 1.15rem; }
        .apk-title { font-size: 1rem; }
        .header-logo { height: 48px; }
    }

    @media (min-width: 768px) {
        .apk-title { font-size: 1.2rem; }
    }

    @media (min-width: 992px) {
        .navbar-brand { font-size: 1.25rem; }
        .header-logo { height: 58px; }
    }

    /* === Logo & Layout === */
    .header-logo {
        height: 40px;
        width: auto;
    }

    .datetime-info {
        font-size: 0.85rem;
    }

    @media (max-width: 991.98px) {
        .main-content {
            padding-left: 0.5rem;
            padding-right: 0.5rem;
        }
    }

    /* Ukuran dan spacing tombol logout */
    .navbar .btn-logout {
        font-size: 0.85rem;
        padding: 0.3rem 0.6rem;
        gap: 0.3rem;
    }

    .navbar .btn-logout {
        font-size: 0.85rem;
        padding: 0.3rem 0.6rem;
        gap: 0.3rem;
        box-shadow: 0 1px 4px rgba(0,0,0,0.1); /* opsional */
    }
</style>
</head>
<body>
    <!-- Toast Notifications -->
    <div class="toast-container">
        <?php if ($success_message): ?>
        <div class="toast show" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header bg-success text-white">
                <strong class="me-auto">Sukses</strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body">
                <?= $success_message ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
        <div class="toast show" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header bg-danger text-white">
                <strong class="me-auto">Error</strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body">
                <?= $error_message ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Sidebar as Offcanvas -->
    <div class="offcanvas offcanvas-start sidebar bg-light" tabindex="-1" id="sidebarOffcanvas">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title">Menu</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>" href="../index.php">
                        <i class="fas fa-home me-2"></i>Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= strpos($_SERVER['REQUEST_URI'], 'santri') !== false ? 'active' : '' ?>" href="../santri/">
                        <i class="fas fa-users me-2"></i>Data Santri
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= strpos($_SERVER['REQUEST_URI'], 'jenis-pelanggaran') !== false ? 'active' : '' ?>" href="../jenis-pelanggaran/">
                        <i class="fas fa-exclamation-triangle me-2"></i>Jenis Pelanggaran
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= strpos($_SERVER['REQUEST_URI'], 'pelanggaran') !== false ? 'active' : '' ?>" href="../pelanggaran/">
                        <i class="fas fa-clipboard-list me-2"></i>Catatan Pelanggaran
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= strpos($_SERVER['REQUEST_URI'], 'rekap') !== false ? 'active' : '' ?>" href="../rekap/">
                        <i class="fas fa-chart-bar me-2"></i>Rekap Pelanggaran
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= strpos($_SERVER['REQUEST_URI'], 'eksekusi') !== false ? 'active' : '' ?>" href="../eksekusi">
                        <i class="fas fa-broom me-2"></i>Eksekusi Kebersihan
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <div class="container-fluid">
        <!-- System Time Info -->
       <div class="datetime-info text-end pe-3">
            Sistem: <span id="live-time"></span>
        </div>


        <div class="row">
            <!-- Static sidebar visible on large screens -->
            <div class="col-lg-2 d-none d-lg-block bg-light sidebar">
                <div class="pt-3">
                    <ul class="nav flex-column">
                        <!-- Sidebar list duplicate untuk desktop -->
                        <li class="nav-item">
                            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>" href="../index.php">
                                <i class="fas fa-home me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= strpos($_SERVER['REQUEST_URI'], 'santri') !== false ? 'active' : '' ?>" href="../santri/">
                                <i class="fas fa-users me-2"></i>Data Santri
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= strpos($_SERVER['REQUEST_URI'], 'jenis-pelanggaran') !== false ? 'active' : '' ?>" href="../jenis-pelanggaran">
                                <i class="fas fa-exclamation-triangle me-2"></i>Jenis Pelanggaran
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= strpos($_SERVER['REQUEST_URI'], 'pelanggaran') !== false ? 'active' : '' ?>" href="../pelanggaran">
                                <i class="fas fa-clipboard-list me-2"></i>Catatan Pelanggaran
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= strpos($_SERVER['REQUEST_URI'], 'rekap') !== false ? 'active' : '' ?>" href="../rekap">
                                <i class="fas fa-chart-bar me-2"></i>Rekap Pelanggaran
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= strpos($_SERVER['REQUEST_URI'], 'eksekusi') !== false ? 'active' : '' ?>" href="../eksekusi">
                                <i class="fas fa-broom me-2"></i>Eksekusi Kebersihan
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-lg-10 ms-sm-auto px-md-4 main-content">
<!-- Replace your current navbar section with this improved version -->
<nav class="navbar navbar-expand-lg mb-4 shadow-sm rounded bg-white border border-success">
  <div class="container-fluid px-2 px-sm-3 position-relative">

    <!-- POJOK KIRI: Burger -->
    <div class="position-absolute top-50 translate-middle-y" style="left: 1rem;">
      <button class="btn btn-success d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas">
        <i class="fas fa-bars text-white"></i>
      </button>
    </div>

    <!-- TENGAH: Logo + Judul -->
    <div class="mx-auto d-flex align-items-center text-success fw-bold">
      <img src="/assets/logo.png?v=2" alt="Logo" class="header-logo me-2">
      <span class="d-none d-sm-inline">Pendataan Mukholif</span>
      <span class="d-inline d-sm-none">Mukholif</span>
    </div>

    <!-- POJOK KANAN: Logout -->
    <div class="position-absolute top-50 translate-middle-y pe-3" style="right: 0;">
      <a class="btn btn-sm btn-danger text-white d-flex align-items-center btn-logout" href="../logout.php">
        <i class="fas fa-sign-out-alt"></i>
        <span class="d-none d-sm-inline ms-2">Logout</span>
      </a>
    </div>

  </div>
</nav>

<!-- Update your existing time script to include mobile time -->
<script>
  function updateTime() {
    const now = new Date();
    const options = {
      day: '2-digit', month: '2-digit', year: 'numeric',
      hour: '2-digit', minute: '2-digit', second: '2-digit',
      hour12: false,
      timeZone: 'Asia/Jakarta'
    };
    const formatter = new Intl.DateTimeFormat('id-ID', options);
    document.getElementById('live-time').textContent = formatter.format(now);
  }

  setInterval(updateTime, 1000);
  updateTime();
</script>
</body>
</html>