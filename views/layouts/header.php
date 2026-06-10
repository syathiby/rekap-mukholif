<?php
declare(strict_types=1);

use App\Helpers\AuthHelper;
use App\Core\Database;

if (!defined('BASE_URL')) {
    define('BASE_URL', $_ENV['APP_URL'] ?? '');
}

$_img_base = ROOT_PATH;
$_favicon_path = $_img_base . '/public/assets/img/logo_favicon.png';
$_appicon_path = $_img_base . '/public/assets/img/logo_aplikasi.png';
$favicon_v  = file_exists($_favicon_path) ? filemtime($_favicon_path) : '1';
$appicon_v  = file_exists($_appicon_path) ? filemtime($_appicon_path) : '1';
$style_v    = time();

$current_role_id = $_SESSION['role'] ?? 'admin';
$role_name_display = ucfirst($current_role_id);
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare('SELECT role_name FROM roles WHERE id = :id');
    $stmt->execute([':id' => $current_role_id]);
    if ($row = $stmt->fetch()) {
        $role_name_display = $row['role_name'];
    }
} catch (\Exception $e) {}

$req_path = $_SERVER['REQUEST_URI'];
$basePath = parse_url(BASE_URL, PHP_URL_PATH) ?? '';
if ($basePath && strpos($req_path, $basePath) === 0) {
    $req_path = substr($req_path, strlen($basePath));
}
if (empty($req_path)) $req_path = '/';

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>AsuhTrack</title>

    <link rel="manifest" href="<?= BASE_URL ?>/manifest.json">
    <link rel="icon" type="image/png" sizes="16x16"  href="<?= BASE_URL ?>/assets/img/logo_favicon.png?v=<?= $favicon_v ?>">
    <link rel="icon" type="image/png" sizes="32x32"  href="<?= BASE_URL ?>/assets/img/logo_favicon.png?v=<?= $favicon_v ?>">
    <link rel="icon" type="image/png" sizes="48x48"  href="<?= BASE_URL ?>/assets/img/logo_favicon.png?v=<?= $favicon_v ?>">
    <link rel="icon" type="image/png" sizes="96x96"  href="<?= BASE_URL ?>/assets/img/logo_favicon.png?v=<?= $favicon_v ?>">
    <link rel="shortcut icon" type="image/png"       href="<?= BASE_URL ?>/assets/img/logo_favicon.png?v=<?= $favicon_v ?>">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="AsuhTrack">
    <link rel="apple-touch-icon"              href="<?= BASE_URL ?>/assets/img/logo_aplikasi.png?v=<?= $appicon_v ?>">
    
    <meta name="theme-color" content="#16a34a">
    <meta name="mobile-web-app-capable" content="yes">
    
    <!-- HTMX -->
    <script src="https://unpkg.com/htmx.org@1.9.11"></script>
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= $style_v ?>">
    <style>
    #sidebarOverlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.5);
        z-index: 1039;
        backdrop-filter: blur(2px);
        opacity: 0;
        transition: opacity 0.25s ease;
    }
    #sidebarOverlay.visible { opacity: 1; }
    </style>
</head>
<body>
    <div id="sidebarOverlay" onclick="closeSidebarMobile()"></div>

    <nav id="sidebar">
        <button class="d-md-none btn btn-sm text-white position-absolute end-0 top-0 me-2"
                id="sidebarCloseBtn"
                style="margin-top: calc(1rem + env(safe-area-inset-top, 0px)); background: rgba(255,255,255,0.1); border: none; border-radius: 50%; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; z-index: 1050;"
                onclick="closeSidebarMobile()">
            <i class="fas fa-times"></i>
        </button>

        <a href="<?= BASE_URL ?>/" class="sb-brand">
            <div class="sb-brand-icon"><i class="fas fa-shield-alt"></i></div>
            <div class="sb-brand-name">Asuh<span>Track</span></div>
        </a>

        <?php require_once VIEW_PATH . '/layouts/sidebar.php'; ?>

        <div class="sb-footer">
            <a href="<?= BASE_URL ?>/logout" onclick="confirmLogout(event)" class="sb-link" style="margin-bottom:6px; background:rgba(239,68,68,0.08); border-color:rgba(239,68,68,0.12);">
                <i class="fas fa-sign-out-alt" style="color:#f87171;"></i>
                <span style="color:#f87171;">Logout</span>
            </a>
            <a href="<?= BASE_URL ?>/pengaturan/users/profil" class="sb-footer-info overflow-hidden text-decoration-none" style="display:flex; color:inherit; transition: background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.05)'" onmouseout="this.style.background='transparent'">
                <div class="sb-avatar" style="background: #7c3aed;">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div class="overflow-hidden">
                    <div class="sb-user-name text-truncate"><?= htmlspecialchars($_SESSION['nama_lengkap'] ?? 'Pengguna') ?></div>
                    <div class="sb-user-role text-truncate" style="color:#94a3b8;"><?= htmlspecialchars($role_name_display) ?></div>
                </div>
            </a>
        </div>
    </nav>

    <main class="main-content d-flex flex-column min-vh-100">
        <header class="header sticky-top bg-white mb-4" style="border-bottom: 1px solid var(--border-color); z-index: 1020;">
            <nav class="navbar h-100 px-lg-4 px-3 py-2">
                <div class="container-fluid d-flex align-items-center justify-content-between p-0">
                    <div class="d-flex align-items-center">
                        <button class="btn btn-light d-lg-none me-3" type="button" onclick="openSidebarMobile()">
                            <i class="fas fa-bars"></i>
                        </button>
                        
                        <div class="d-flex flex-column">
                            <h5 class="mb-0 fw-bold text-dark" style="font-size: 1.15rem;" id="header-page-title">
                                <?php 
                                $page_title = 'Dashboard';
                                if (strpos($req_path, '/santri') === 0) $page_title = 'Data Santri';
                                elseif (strpos($req_path, '/jenis-pelanggaran') === 0) $page_title = 'Jenis Pelanggaran';
                                elseif (strpos($req_path, '/pelanggaran') === 0) $page_title = 'Catatan Pelanggaran';
                                elseif (strpos($req_path, '/reward') === 0) $page_title = 'Reward & Prestasi';
                                elseif (strpos($req_path, '/eksekusi') === 0) $page_title = 'Eksekusi Kebersihan';
                                elseif (strpos($req_path, '/arsip') === 0) $page_title = 'Arsip';
                                elseif (strpos($req_path, '/rekap') === 0) $page_title = 'Rekap Pelanggaran';
                                elseif (strpos($req_path, '/rapot') === 0) $page_title = 'Rapot Kepengasuhan';
                                elseif (strpos($req_path, '/export') === 0) $page_title = 'Export Data';
                                elseif (strpos($req_path, '/pengaturan/log-aktifitas') === 0) $page_title = 'Log Aktivitas';
                                elseif (strpos($req_path, '/pengaturan') === 0) $page_title = 'Pengaturan';
                                echo $page_title;
                                ?>
                            </h5>
                            <span class="text-muted d-none d-sm-block" style="font-size: 0.8rem;">Sistem Informasi Kepengasuhan Santri</span>
                        </div>
                    </div>
                    
                    <div class="d-none d-lg-flex align-items-center gap-2">
                        <div class="badge bg-white text-secondary border px-3 py-2 rounded-3 fw-medium d-flex align-items-center">
                            <i class="far fa-calendar-alt me-2 text-muted"></i>
                            <?= date('d M Y') ?>
                        </div>
                        <div class="badge bg-primary text-white border px-3 py-2 rounded-3 fw-medium d-flex align-items-center">
                            <i class="fas fa-user-shield me-2"></i>
                            <?= htmlspecialchars($role_name_display) ?>
                        </div>
                    </div>
                </div>
            </nav>
        </header>

        <script>
        function openSidebarMobile() {
            var side = document.getElementById('sidebar');
            var overlay = document.getElementById('sidebarOverlay');
            if(side) side.classList.add('toggled');
            if(overlay) {
                overlay.style.display = 'block';
                requestAnimationFrame(() => overlay.classList.add('visible'));
            }
            document.body.style.overflow = 'hidden';
        }

        function closeSidebarMobile() {
            var side = document.getElementById('sidebar');
            var overlay = document.getElementById('sidebarOverlay');
            if(side) side.classList.remove('toggled');
            if(overlay) {
                overlay.classList.remove('visible');
                setTimeout(() => { overlay.style.display = 'none'; }, 250);
            }
            document.body.style.overflow = '';
        }

        function confirmLogout(event) {
            event.preventDefault();
            const logoutUrl = event.currentTarget.href;
            if (confirm('Apakah Anda yakin ingin keluar? Sesi Anda akan berakhir.')) {
                window.location.href = logoutUrl;
            }
        }
        </script>

        <div id="flash-message-container">
        <?php
        if (isset($_SESSION['flash_message'])) {
            $type = $_SESSION['flash_message']['type'] === 'success' ? 'success' : 'error';
            $msg = htmlspecialchars($_SESSION['flash_message']['message']);
            echo "
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                const Toast = Swal.mixin({
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true,
                    didOpen: (toast) => {
                        toast.onmouseenter = Swal.stopTimer;
                        toast.onmouseleave = Swal.resumeTimer;
                    }
                });
                Toast.fire({
                    icon: '{$type}',
                    title: '{$msg}'
                });
            });
            </script>
            ";
            unset($_SESSION['flash_message']);
        }
        ?>
        </div>
