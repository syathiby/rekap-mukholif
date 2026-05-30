<?php
// ─── CACHE BUSTING UNTUK ASET GAMBAR ───────────────────────────────
$_img_base = $_SERVER['DOCUMENT_ROOT'];
// Coba path dengan subfolder dulu, fallback ke root
$_favicon_path = file_exists($_img_base . '/rekap-mukholif/assets/img/logo_favicon.png')
    ? $_img_base . '/rekap-mukholif/assets/img/logo_favicon.png'
    : $_img_base . '/assets/img/logo_favicon.png';
$_appicon_path = file_exists($_img_base . '/rekap-mukholif/assets/img/logo_aplikasi.png')
    ? $_img_base . '/rekap-mukholif/assets/img/logo_aplikasi.png'
    : $_img_base . '/assets/img/logo_aplikasi.png';

$favicon_v  = file_exists($_favicon_path) ? filemtime($_favicon_path) : '1';
$appicon_v  = file_exists($_appicon_path) ? filemtime($_appicon_path) : '1';
$style_v    = time(); // selalu fresh
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <!-- viewport-fit=cover penting untuk iPhone X+ (notch & safe area) -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>AsuhTrack</title>

    <!-- ═══════════════════════════════════════════════════
         PWA — MANIFEST
    ═══════════════════════════════════════════════════ -->
    <link rel="manifest" href="<?= BASE_URL ?>/manifest.json">

    <!-- ═══════════════════════════════════════════════════
         PWA — FAVICON (logo_favicon.png)
    ═══════════════════════════════════════════════════ -->
    <link rel="icon" type="image/png" sizes="16x16"  href="<?= BASE_URL ?>/assets/img/logo_favicon.png?v=<?= $favicon_v ?>">
    <link rel="icon" type="image/png" sizes="32x32"  href="<?= BASE_URL ?>/assets/img/logo_favicon.png?v=<?= $favicon_v ?>">
    <link rel="icon" type="image/png" sizes="48x48"  href="<?= BASE_URL ?>/assets/img/logo_favicon.png?v=<?= $favicon_v ?>">
    <link rel="icon" type="image/png" sizes="96x96"  href="<?= BASE_URL ?>/assets/img/logo_favicon.png?v=<?= $favicon_v ?>">
    <link rel="shortcut icon" type="image/png"       href="<?= BASE_URL ?>/assets/img/logo_favicon.png?v=<?= $favicon_v ?>">

    <!-- ═══════════════════════════════════════════════════
         PWA — APPLE / iOS (logo_aplikasi.png sebagai home screen icon)
         Wajib untuk iOS Safari agar icon bagus saat "Add to Home Screen"
    ═══════════════════════════════════════════════════ -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="AsuhTrack">
    <link rel="apple-touch-icon"              href="<?= BASE_URL ?>/assets/img/logo_aplikasi.png?v=<?= $appicon_v ?>">
    <link rel="apple-touch-icon" sizes="120x120" href="<?= BASE_URL ?>/assets/img/logo_aplikasi.png?v=<?= $appicon_v ?>">
    <link rel="apple-touch-icon" sizes="152x152" href="<?= BASE_URL ?>/assets/img/logo_aplikasi.png?v=<?= $appicon_v ?>">
    <link rel="apple-touch-icon" sizes="167x167" href="<?= BASE_URL ?>/assets/img/logo_aplikasi.png?v=<?= $appicon_v ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= BASE_URL ?>/assets/img/logo_aplikasi.png?v=<?= $appicon_v ?>">

    <!-- ═══════════════════════════════════════════════════
         PWA — THEME & GENERAL META
    ═══════════════════════════════════════════════════ -->
    <meta name="theme-color" content="#16a34a">
    <meta name="msapplication-TileColor" content="#16a34a">
    <meta name="msapplication-TileImage" content="<?= BASE_URL ?>/assets/img/logo_aplikasi.png?v=<?= $appicon_v ?>">
    <meta name="msapplication-config" content="none">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="application-name" content="AsuhTrack">
    <meta name="description" content="Sistem informasi kepengasuhan santri — catat pelanggaran, reward, dan rekap secara digital.">

    <!-- ═══════════════════════════════════════════════════
         ASET & FONT
    ═══════════════════════════════════════════════════ -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">

    <!-- Custom Style -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= $style_v ?>">

    <!-- ═══════════════════════════════════════════════════
         SAFARI / IOS BACK BUTTON (BFCACHE) PROTECTION
         Memaksa browser melakukan reload jika halaman dipanggil dari cache browser (misal klik Back setelah logout)
    ═══════════════════════════════════════════════════ -->
    <script>
    window.addEventListener('pageshow', function(event) {
        if (event.persisted || (window.performance && window.performance.navigation.type === 2)) {
            window.location.reload();
        }
    });
    </script>

    <!-- ═══════════════════════════════════════════════════
         PWA — SERVICE WORKER REGISTRATION
         Ditempatkan di <head> agar SW terdaftar secepat mungkin
    ═══════════════════════════════════════════════════ -->
    <script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function() {
            navigator.serviceWorker
                .register('<?= BASE_URL ?>/sw.js', { scope: '<?= BASE_URL ?>/' })
                .then(function(reg) {
                    // SW berhasil terdaftar — cek update
                    reg.addEventListener('updatefound', function() {
                        var newWorker = reg.installing;
                        newWorker.addEventListener('statechange', function() {
                            if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                // Versi baru tersedia — refresh otomatis
                                newWorker.postMessage({ type: 'SKIP_WAITING' });
                                window.location.reload();
                            }
                        });
                    });
                })
                .catch(function(err) {
                    console.warn('[PWA] Service Worker gagal terdaftar:', err);
                });
        });
    }
    </script>
</head>
<body>
    <style>
    /* SyathibyFood Mobile Overlay */
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

    <!-- Overlay backdrop untuk menutup sidebar di mobile -->
    <div id="sidebarOverlay" onclick="closeSidebarMobile()"></div>

    <!-- Unified Sidebar -->
    <nav id="sidebar">
        <!-- Mobile Close Button -->
        <button class="d-md-none btn btn-sm text-white position-absolute end-0 top-0 me-2"
                id="sidebarCloseBtn"
                style="margin-top: calc(1rem + env(safe-area-inset-top, 0px)); background: rgba(255,255,255,0.1); border: none; border-radius: 50%; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; z-index: 1050;"
                onclick="closeSidebarMobile()">
            <i class="fas fa-times"></i>
        </button>

        <!-- Brand -->
        <a href="<?= BASE_URL ?>/index.php" class="sb-brand">
            <div class="sb-brand-icon"><i class="fas fa-shield-alt"></i></div>
            <div class="sb-brand-name">Asuh<span>Track</span></div>
        </a>

        <!-- Nav -->
        <?php include __DIR__ . '/sidebar.php'; ?>

        <!-- Footer -->
        <div class="sb-footer">

            <a href="<?= BASE_URL ?>/logout.php" onclick="confirmLogout(event)" class="sb-link" style="margin-bottom:6px; background:rgba(239,68,68,0.08); border-color:rgba(239,68,68,0.12);">
                <i class="fas fa-sign-out-alt" style="color:#f87171;"></i>
                <span style="color:#f87171;">Logout</span>
            </a>
            <div class="sb-footer-info overflow-hidden">
                <div class="sb-avatar" style="background: #7c3aed;">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div class="overflow-hidden">
                    <div class="sb-user-name text-truncate"><?= htmlspecialchars($_SESSION['nama_lengkap'] ?? 'Pengguna') ?></div>
                    <div class="sb-user-role text-truncate" style="color:#94a3b8;"><?= htmlspecialchars(ucfirst($_SESSION['role'] ?? 'Admin')) ?></div>
                </div>
            </div>
        </div>
    </nav>


    <!-- Konten Utama (Kanan) -->
    <main class="main-content d-flex flex-column min-vh-100">
        <!-- Header Minimalis ala SyathibyFood -->
        <header class="header sticky-top bg-white mb-4" style="border-bottom: 1px solid var(--border-color); z-index: 1020;">
            <nav class="navbar h-100 px-lg-4 px-3 py-2">
                <div class="container-fluid d-flex align-items-center justify-content-between p-0">
                    <!-- Kiri: Burger (Mobile) & Page Title (Mobile & Desktop) -->
                    <div class="d-flex align-items-center">
                        <button class="btn btn-light d-lg-none me-3" type="button" onclick="openSidebarMobile()">
                            <i class="fas fa-bars"></i>
                        </button>
                        
                        <!-- Title -->
                        <div class="d-flex flex-column">
                            <h5 class="mb-0 fw-bold text-dark" style="font-size: 1.15rem;">
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
                                elseif (strpos($req_path, '/pengaturan/log-aktifitas') === 0 || strpos($req_path, '/pengaturan/log_aktifitas') === 0) $page_title = 'Log Aktivitas';
                                elseif (strpos($req_path, '/pengaturan') === 0) $page_title = 'Pengaturan';
                                echo $page_title;
                                ?>
                            </h5>
                            <span class="text-muted d-none d-sm-block" style="font-size: 0.8rem;">Sistem Informasi Kepengasuhan Santri</span>
                        </div>
                    </div>
                    
                    <!-- Kanan: Date & Role Badges (Desktop Only) -->
                    <div class="d-none d-lg-flex align-items-center gap-2">
                        <div class="badge bg-white text-secondary border px-3 py-2 rounded-3 fw-medium d-flex align-items-center">
                            <i class="far fa-calendar-alt me-2 text-muted"></i>
                            <?= date('d M Y') ?>
                        </div>
                        <div class="badge bg-primary text-white border px-3 py-2 rounded-3 fw-medium d-flex align-items-center">
                            <i class="fas fa-user-shield me-2"></i>
                            <?= htmlspecialchars(ucfirst($_SESSION['role'] ?? 'Admin')) ?>
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
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'Keluar dari Sistem?',
            text: 'Sesi Anda akan diakhiri dan seluruh jejak browser akan dibersihkan demi keamanan.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#22c55e',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Ya, Keluar',
            cancelButtonText: 'Batal',
            reverseButtons: true,
            background: '#1e293b',
            color: '#f8fafc',
            iconColor: '#f59e0b'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = logoutUrl;
            }
        });
    } else {
        if (confirm('Apakah Anda yakin ingin keluar? Sesi Anda akan berakhir.')) {
            window.location.href = logoutUrl;
        }
    }
}
</script>

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