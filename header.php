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

// 🔹 Role helper
function currentRole() {
    if (isset($_SESSION['role'])) return $_SESSION['role'];
    if (isset($_SESSION['user']['role'])) return $_SESSION['user']['role'];
    return null;
}

function checkRole(array $allowedRoles = []) {
    $role = currentRole();
    if (!$role) {
        header("Location: /login.php");
        exit;
    }

    if (!in_array($role, $allowedRoles)) {
        // Redirect balik ke dashboard kalau role gak cocok
        header("Location: ../index.php");
        exit;
    }
}

function isAdmin() {
    return currentRole() === 'admin';
}

// Set default timezone to Asia/Jakarta
date_default_timezone_set('Asia/Jakarta');

// --- FIX: pastikan koneksi DB siap ---
if (!isset($conn) || !($conn instanceof mysqli)) {
    // db.php ada di root /rekap-mukholif/
    include_once __DIR__ . '/db.php';
}

// --- Ambil periode aktif dengan aman ---
$periode_aktif = '2000-01-01'; // default kalau belum diatur
if (isset($conn) && ($conn instanceof mysqli)) {
    $q_periode = mysqli_query($conn, "SELECT nilai FROM pengaturan WHERE nama='periode_mulai' LIMIT 1");
    if ($q_periode) {
        $row_periode = mysqli_fetch_assoc($q_periode);
        if ($row_periode && !empty($row_periode['nilai'])) {
            $periode_aktif = $row_periode['nilai'];
        }
    }
}


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
    /* Warna default ikon sidebar (desktop & mobile) */
    .sidebar-desktop .nav-link i,
    .offcanvas .nav-link i {
        color: #0d6efd;
        margin-right: 0.5rem;
    }

    /* Warna per halaman - desktop */
    .sidebar-desktop .nav-link[href*='../index.php'] i { color: #0d6efd !important; }
    .sidebar-desktop .nav-link[href*='../santri'] i { color: #20c997 !important; }
    .sidebar-desktop .nav-link[href*='../jenis-pelanggaran'] i { color: #fd7e14 !important; }
    .sidebar-desktop .nav-link[href*='../pelanggaran'] i { color: #6c757d !important; }
    .sidebar-desktop .nav-link[href*='../rekap'] i { color: #6f42c1 !important; }
    .sidebar-desktop .nav-link[href*='../eksekusi'] i { color: #e83e8c !important; }

    /* Warna per halaman - mobile */
    .offcanvas .nav-link[href*='../index.php'] i { color: #0d6efd !important; }
    .offcanvas .nav-link[href*='../santri'] i { color: #20c997 !important; }
    .offcanvas .nav-link[href*='../jenis-pelanggaran'] i { color: #fd7e14 !important; }
    .offcanvas .nav-link[href*='../pelanggaran'] i { color: #6c757d !important; }
    .offcanvas .nav-link[href*='../rekap'] i { color: #6f42c1 !important; }
    .offcanvas .nav-link[href*='../eksekusi'] i { color: #e83e8c !important; }

    /* Hover effect */
    .sidebar-desktop .nav-link:hover i,
    .offcanvas .nav-link:hover i {
        transform: scale(1.1);
        transition: 0.2s ease-in-out;
    }

    /* Styling link sidebar */
    .sidebar-desktop .nav-link,
    .offcanvas .nav-link {
        padding-left: 1rem;
        color: #333 !important;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-family: 'Poppins', sans-serif;
        font-size: 15px;
    }

    .sidebar-desktop .nav-link:hover,
    .offcanvas .nav-link:hover {
        background-color: #e9ecef;
        color: #000 !important;
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

    /* Logo */
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

    .btn-logout {
        font-size: 0.85rem;
        padding: 0.3rem 0.6rem;
        gap: 0.3rem;
        box-shadow: 0 1px 4px rgba(0,0,0,0.1);
    }

    /* Sidebar Desktop */
    .sidebar-desktop {
        position: fixed;
        top: 0;
        left: 0;
        height: 100vh;
        overflow-y: auto;
        border-right: 1px solid #dee2e6;
        z-index: 1020;
        padding-top: 50px;
    }

    .main-content {
        margin-left: 0;
        padding-top: 1rem;
    }

    @media (min-width: 992px) {
        .main-content {
            margin-left: 16.67%; /* ~2/12 dari 12 kolom */
        }
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

    <!-- Sidebar Mobile (Offcanvas) -->
        <div class="offcanvas offcanvas-start bg-light" tabindex="-1" id="sidebarOffcanvas">

            <div class="offcanvas-header">
                <h5 class="offcanvas-title">Menu</h5>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
            </div>
            <div class="offcanvas-body d-flex flex-column">
                <?php include __DIR__ . '/sidebar.php'; ?>
            </div>
        </div>

    <div class="container-fluid">
    <!-- System Time Info -->
    <div class="datetime-info text-end pe-3">
        Sistem: <span id="live-time"></span>
    </div>

    <div class="row">
        <!-- Sidebar -->
        <div class="col-lg-2 d-none d-lg-flex flex-column bg-light sidebar-desktop pt-3">
            <?php include __DIR__ . '/sidebar.php'; ?>
        </div>

        <!-- Main Content -->
    <div class="col-lg-10 ms-sm-auto px-md-4 main-content">
        <nav class="navbar navbar-expand-lg mb-4 shadow-sm rounded bg-white border border-success position-sticky top-0" style="z-index: 1030;">
            <div class="container-fluid d-flex align-items-center px-2 px-sm-3">
                <!-- Burger Menu -->
                <button class="btn btn-success d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas">
                    <i class="fas fa-bars text-white"></i>
                </button>

                <!-- Logo & Judul -->
                <div class="mx-auto d-flex align-items-center text-success fw-bold">
                    <img src="/assets/logo.png?v=2" alt="Logo" class="header-logo me-2">
                    <span class="d-none d-sm-inline">Pendataan Mukholif</span>
                    <span class="d-inline d-sm-none">Mukholif</span>
                </div>

                <!-- Logout -->
                <div class="ms-auto">
                    <a class="btn btn-sm btn-danger text-white d-flex align-items-center btn-logout" href="/logout.php">
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