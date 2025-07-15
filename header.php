<?php
// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
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

    <div class="container-fluid">
        <!-- System Time Info -->
        <div class="datetime-info text-end pe-3">
            Sistem: <?= date('d/m/Y H:i:s') ?> WIB
        </div>
        
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 d-md-block sidebar collapse bg-light">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == '/rekap-mukholif/index.php' ? 'active' : '' ?>" href="/rekap-mukholif/index.php">
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
            <div class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <nav class="navbar navbar-expand-lg navbar-light bg-light mb-4">
                    <div class="container-fluid">
                        <a class="navbar-brand" href="../">
                            <i class="fas fa-user-shield me-2"></i>Sistem Pelanggaran Santri
                        </a>
                        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                            <span class="navbar-toggler-icon"></span>
                        </button>
                        <div class="collapse navbar-collapse" id="navbarNav">
                            <ul class="navbar-nav ms-auto">
                                <li class="nav-item dropdown">
                                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                                        <i class="fas fa-user-circle me-1"></i><?= $_SESSION['username'] ?? 'Admin' ?>
                                    </a>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><a class="dropdown-item" href="#"><i class="fas fa-user me-2"></i>Profil</a></li>
                                        <li><a class="dropdown-item" href="#"><i class="fas fa-cog me-2"></i>Pengaturan</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <a class="dropdown-item" href="../logout.php">
                                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                                            </a>
                                        </li>
                                    </ul>
                                </li>
                            </ul>
                        </div>
                    </div>
                </nav>
                <!-- Content akan dimasukkan di sini -->
                <div class="container-fluid">