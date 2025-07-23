<?php
// Start session
if (session_status() === PHP_SESSION_NONE) {
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
        .navbar-brand {
            font-weight: bold;
        }
        .sidebar {
            min-height: 100vh;
            background-color: #f8f9fa;
            padding-top: 20px;
        }
        .sidebar .nav-link {
            color: #333;
            border-radius: 5px;
            margin-bottom: 5px;
        }
        .sidebar .nav-link:hover {
            background-color: #e9ecef;
        }
        .sidebar .nav-link.active {
            background-color: #0d6efd;
            color: white;
        }
        .main-content {
            padding: 20px;
        }
        .card {
            margin-bottom: 20px;
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
        }
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1100;
        }
        .datetime-info {
            font-size: 0.8rem;
            color: #6c757d;
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
                <nav class="navbar navbar-expand-lg navbar-light bg-light mb-4">
                    <div class="container-fluid">
                        <!-- Toggle Button untuk Mobile Sidebar -->
                        <button class="btn btn-outline-secondary d-lg-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas">
                            <i class="fas fa-bars"></i>
                        </button>
                        <a class="navbar-brand" href="../">
                            <i class="fas fa-user-shield me-2"></i>Sistem Pelanggaran Santri
                        </a>
                        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                            <span class="navbar-toggler-icon"></span>
                        </button>
                        <div class="collapse navbar-collapse" id="navbarNav">
                            <ul class="navbar-nav ms-auto align-items-center">
                                <li class="nav-item me-3 text-dark">
                                    <i class="fas fa-user-circle me-1"></i> <?= $_SESSION['username'] ?? 'Admin' ?>
                                </li>
                                <li class="nav-item">
                                    <a class="btn btn-outline-danger btn-sm" href="/rekap-mukholif/logout.php">
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