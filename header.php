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
    .sidebar .nav-link[href='/rekap-mukholif/index.php'] i {
        color: #0d6efd !important; /* biru - dashboard */
    }
    .sidebar .nav-link[href='/rekap-mukholif/santri/'] i {
        color: #20c997 !important; /* teal - data santri */
    }
    .sidebar .nav-link[href='/rekap-mukholif/jenis-pelanggaran/'] i {
        color: #fd7e14 !important; /* oranye - jenis pelanggaran */
    }
    .sidebar .nav-link[href='/rekap-mukholif/pelanggaran/'] i {
        color: #6c757d !important; /* abu abu - catatan pelanggaran */
    }
    .sidebar .nav-link[href='/rekap-mukholif/rekap/'] i {
        color: #6f42c1 !important; /* ungu - rekap */
    }
    .sidebar .nav-link[href='/rekap-mukholif/pelanggaran/kebersihan-kamar/eksekusi'] i {
        color: #e83e8c !important; /* pink - eksekusi */
    }
    .sidebar .nav-link:hover i {
        transform: scale(1.1);
        transition: 0.2s ease-in-out;
    }
    .sidebar .nav-link {
        padding-left: 1rem;
    }
    .sidebar .nav-link {
        color: #333 !important; /* warna teks: abu gelap, netral */
        font-weight: 500;
    }

    .sidebar .nav-link:hover {
        background-color: #e9ecef;
        color: #000 !important; /* saat hover, teks tetap netral */
    }

    .sidebar .nav-link i {
        margin-right: 0.5rem;
    }
    .sidebar .nav-link {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-family: 'Poppins', sans-serif;
        font-size: 15px;
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
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>" href="/rekap-mukholif/index.php">
                        <i class="fas fa-home me-2"></i>Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= strpos($_SERVER['REQUEST_URI'], 'santri') !== false ? 'active' : '' ?>" href="/rekap-mukholif/santri/">
                        <i class="fas fa-users me-2"></i>Data Santri
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= strpos($_SERVER['REQUEST_URI'], 'jenis-pelanggaran') !== false ? 'active' : '' ?>" href="/rekap-mukholif/jenis-pelanggaran/">
                        <i class="fas fa-exclamation-triangle me-2"></i>Jenis Pelanggaran
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= strpos($_SERVER['REQUEST_URI'], 'pelanggaran') !== false ? 'active' : '' ?>" href="/rekap-mukholif/pelanggaran/">
                        <i class="fas fa-clipboard-list me-2"></i>Catatan Pelanggaran
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= strpos($_SERVER['REQUEST_URI'], 'rekap') !== false ? 'active' : '' ?>" href="/rekap-mukholif/rekap/">
                        <i class="fas fa-chart-bar me-2"></i>Rekap Pelanggaran
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= strpos($_SERVER['REQUEST_URI'], 'eksekusi') !== false ? 'active' : '' ?>" href="/rekap-mukholif/pelanggaran/kebersihan-kamar/eksekusi">
                        <i class="fas fa-broom me-2"></i>Eksekusi Kebersihan
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <div class="container-fluid">
        <!-- System Time Info -->
        <div class="datetime-info text-end pe-3">
            Sistem: <span id="live-time"></span> WIB
        </div>


        <div class="row">
            <!-- Static sidebar visible on large screens -->
            <div class="col-lg-2 d-none d-lg-block bg-light sidebar">
                <div class="pt-3">
                    <ul class="nav flex-column">
                        <!-- Sidebar list duplicate untuk desktop -->
                        <li class="nav-item">
                            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>" href="/rekap-mukholif/index.php">
                                <i class="fas fa-home me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= strpos($_SERVER['REQUEST_URI'], 'santri') !== false ? 'active' : '' ?>" href="/rekap-mukholif/santri/">
                                <i class="fas fa-users me-2"></i>Data Santri
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= strpos($_SERVER['REQUEST_URI'], 'jenis-pelanggaran') !== false ? 'active' : '' ?>" href="/rekap-mukholif/jenis-pelanggaran/">
                                <i class="fas fa-exclamation-triangle me-2"></i>Jenis Pelanggaran
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= strpos($_SERVER['REQUEST_URI'], 'pelanggaran') !== false ? 'active' : '' ?>" href="/rekap-mukholif/pelanggaran/">
                                <i class="fas fa-clipboard-list me-2"></i>Catatan Pelanggaran
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= strpos($_SERVER['REQUEST_URI'], 'rekap') !== false ? 'active' : '' ?>" href="/rekap-mukholif/rekap/">
                                <i class="fas fa-chart-bar me-2"></i>Rekap Pelanggaran
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= strpos($_SERVER['REQUEST_URI'], 'eksekusi') !== false ? 'active' : '' ?>" href="/rekap-mukholif/pelanggaran/kebersihan-kamar/eksekusi">
                                <i class="fas fa-broom me-2"></i>Eksekusi Kebersihan
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-lg-10 ms-sm-auto px-md-4 main-content">
<nav class="navbar navbar-expand-lg mb-4 shadow-sm rounded bg-white border border-success">
    <div class="container-fluid">
        <!-- Toggle Sidebar Button (Mobile) -->
        <button class="btn btn-success d-lg-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas">
            <i class="fas fa-bars text-white"></i>
        </button>

        <!-- Logo & Judul Aplikasi -->
        <a class="navbar-brand text-success fw-bold d-flex align-items-center" href="../">
            <img src="/rekap-mukholif/assets/logo.png?v=2" alt="Logo" style="height:58px;" class="me-2">
            Aplikasi Pendataan Mukholif
        </a>

        <!-- Mobile Toggle Button -->
        <button class="navbar-toggler border-success" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- User Info + Logout -->
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item me-3 text-success fw-semibold">
                    <i class="fas fa-user-circle me-1"></i> <?= $_SESSION['username'] ?? 'Admin' ?>
                </li>
                <li class="nav-item">
                    <a class="btn btn-sm btn-danger text-white" href="/rekap-mukholif/logout.php">
                        <i class="fas fa-sign-out-alt me-1"></i>Logout
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

                <!-- Content dimulai di sini -->
                <div class="container-fluid">

    <!-- Bootstrap Bundle JS (Wajib buat toggle sidebar mobile) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

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

  setInterval(updateTime, 1000); // Update setiap 1 detik
  updateTime(); // Tampilkan langsung saat halaman dibuka
</script>
</body>
</html>