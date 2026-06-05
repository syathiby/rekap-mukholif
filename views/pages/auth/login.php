<?php
$_img_base = ROOT_PATH;
$_appicon_path = $_img_base . '/public/assets/img/logo_aplikasi.png';
$_favicon_path = $_img_base . '/public/assets/img/logo_favicon.png';
$appicon_v = file_exists($_appicon_path) ? filemtime($_appicon_path) : '1';
$favicon_v = file_exists($_favicon_path) ? filemtime($_favicon_path) : '1';
$style_v = time();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>AsuhTrack — Login</title>

    <link rel="manifest" href="<?= BASE_URL ?>/manifest.json">

    <link rel="icon" type="image/png" sizes="16x16"  href="<?= BASE_URL ?>/assets/img/logo_favicon.png?v=<?= $favicon_v ?>">
    <link rel="icon" type="image/png" sizes="32x32"  href="<?= BASE_URL ?>/assets/img/logo_favicon.png?v=<?= $favicon_v ?>">
    <link rel="icon" type="image/png" sizes="48x48"  href="<?= BASE_URL ?>/assets/img/logo_favicon.png?v=<?= $favicon_v ?>">
    <link rel="icon" type="image/png" sizes="96x96"  href="<?= BASE_URL ?>/assets/img/logo_favicon.png?v=<?= $favicon_v ?>">
    <link rel="shortcut icon" type="image/png"       href="<?= BASE_URL ?>/assets/img/logo_favicon.png?v=<?= $favicon_v ?>">

    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="AsuhTrack">
    <link rel="apple-touch-icon"               href="<?= BASE_URL ?>/assets/img/logo_aplikasi.png?v=<?= $appicon_v ?>">

    <meta name="theme-color" content="#16a34a">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="application-name" content="AsuhTrack">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= $style_v ?>">

</head>
<body class="login-body">
    <div class="login-card-wrapper">
        <div class="login-header">
            <img src="<?= BASE_URL ?>/assets/img/logo_aplikasi.png?v=<?= $appicon_v ?>" alt="Logo AsuhTrack" class="login-logo">
            <h4 class="mb-1">AsuhTrack</h4>
            <p class="text-muted text-sm mb-4">Sistem Kepengasuhan Santri</p>
        </div>

        <?php if (isset($_SESSION['flash_message'])): ?>
            <?php 
                $type = $_SESSION['flash_message']['type'] === 'success' ? 'success' : 'danger';
                $bg = $type === 'success' ? '#dcfce7' : '#fee2e2';
                $color = $type === 'success' ? '#166534' : '#991b1b';
                $icon = $type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
            ?>
            <div class="alert d-flex align-items-center py-2 px-3 text-sm mb-4" style="border-radius: 0.75rem; border: none; background: <?= $bg ?>; color: <?= $color ?>; font-weight: 500;">
                <i class="fas <?= $icon ?> me-2"></i>
                <?= htmlspecialchars($_SESSION['flash_message']['message']) ?>
            </div>
            <?php unset($_SESSION['flash_message']); ?>
        <?php endif; ?>

        <form method="post" action="<?= BASE_URL ?>/login">
            <div class="mb-4">
                <label for="username" class="form-label text-sm fw-medium text-secondary mb-1">Username</label>
                <div class="input-group-modern">
                    <i class="fas fa-user"></i>
                    <input type="text" class="form-control input-modern" id="username" name="username" required placeholder="Masukkan username">
                </div>
            </div>

            <div class="mb-4">
                <label for="password" class="form-label text-sm fw-medium text-secondary mb-1">Password</label>
                <div class="input-group-modern password-wrapper">
                    <i class="fas fa-lock"></i>
                    <input type="password" class="form-control input-modern" id="password" name="password" required placeholder="••••••••">
                    <button type="button" class="password-toggle" onclick="togglePassword()">
                        <span id="toggle-icon"><i class="fas fa-eye text-muted"></i></span>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-login w-100 mt-2">
                Masuk Sistem
            </button>
        </form>
    </div>

    <!-- ── Tombol Install PWA ── -->
    <div id="pwa-install-bar" style="
        display: none;
        position: fixed;
        bottom: max(20px, env(safe-area-inset-bottom, 20px));
        left: 50%;
        transform: translateX(-50%);
        z-index: 9999;
        animation: pwaSlideUp 0.35s ease;
    ">
        <button id="pwa-install-btn" style="
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,255,255,0.92);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(22,163,74,0.2);
            border-radius: 50px;
            padding: 10px 20px;
            font-family: 'Poppins', sans-serif;
            font-size: 0.82rem;
            font-weight: 600;
            color: #15803d;
            cursor: pointer;
            box-shadow: 0 4px 20px rgba(22,163,74,0.15), 0 1px 4px rgba(0,0,0,0.08);
            transition: all 0.2s ease;
            white-space: nowrap;
        ">
            <img src="<?= BASE_URL ?>/assets/img/logo_aplikasi.png" alt="" style="width:22px;height:22px;border-radius:6px;object-fit:cover;flex-shrink:0;">
            <span>Pasang Aplikasi</span>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                <polyline points="7 10 12 15 17 10"/>
                <line x1="12" y1="15" x2="12" y2="3"/>
            </svg>
        </button>
    </div>
    <style>
        @keyframes pwaSlideUp {
            from { opacity: 0; transform: translateX(-50%) translateY(16px); }
            to   { opacity: 1; transform: translateX(-50%) translateY(0); }
        }
        #pwa-install-btn:hover {
            background: #f0fdf4;
            box-shadow: 0 6px 24px rgba(22,163,74,0.22), 0 1px 4px rgba(0,0,0,0.08);
            transform: translateY(-1px);
        }
        #pwa-install-btn:active {
            transform: scale(0.97) translateY(0);
        }
    </style>

    <script>
    try {
        localStorage.clear();
        sessionStorage.clear();
    } catch (e) {}

    function togglePassword() {
        const passwordInput = document.getElementById('password');
        const toggleIcon = document.getElementById('toggle-icon');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            toggleIcon.innerHTML = '<i class="fas fa-eye-slash text-muted"></i>';
        } else {
            passwordInput.type = 'password';
            toggleIcon.innerHTML = '<i class="fas fa-eye text-muted"></i>';
        }
    }

    let _deferredPrompt = null;
    const installBar = document.getElementById('pwa-install-bar');
    const installBtn = document.getElementById('pwa-install-btn');

    window.addEventListener('beforeinstallprompt', function(e) {
        e.preventDefault();
        _deferredPrompt = e;
        installBar.style.display = 'block';
    });

    if (installBtn) {
        installBtn.addEventListener('click', async function() {
            if (!_deferredPrompt) return;
            _deferredPrompt.prompt();
            const { outcome } = await _deferredPrompt.userChoice;
            _deferredPrompt = null;
            installBar.style.display = 'none';
        });
    }

    window.addEventListener('appinstalled', function() {
        installBar.style.display = 'none';
        _deferredPrompt = null;
    });
    </script>
</body>
</html>
