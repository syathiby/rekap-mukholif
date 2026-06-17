<?php
require_once __DIR__ . '/bootstrap/init.php';

// ─── CACHE BUSTING UNTUK ASET GAMBAR ───────────────────────────────
$_img_base = $_SERVER['DOCUMENT_ROOT'];
$_favicon_path = file_exists($_img_base . '/rekap-mukholif/assets/img/logo_favicon.png')
    ? $_img_base . '/rekap-mukholif/assets/img/logo_favicon.png'
    : $_img_base . '/assets/img/logo_favicon.png';
$_appicon_path = file_exists($_img_base . '/rekap-mukholif/assets/img/logo_aplikasi.png')
    ? $_img_base . '/rekap-mukholif/assets/img/logo_aplikasi.png'
    : $_img_base . '/assets/img/logo_aplikasi.png';

$favicon_v = file_exists($_favicon_path) ? filemtime($_favicon_path) : '1';
$appicon_v = file_exists($_appicon_path) ? filemtime($_appicon_path) : '1';
$style_v   = time();
// ───────────────────────────────────────────────────

$error = '';
$info = '';
if (isset($_GET['timeout'])) {
    $info = "Sesi Anda ter-logout otomatis sebagai bentuk keamanan karena login terlalu lama.";
}

// =================================================================
// RATE LIMITING — Anti Brute-Force (Tanpa Database)
// Menyimpan hitungan percobaan gagal per IP di folder cache/
// =================================================================
define('LOGIN_MAX_ATTEMPTS', 5);   // Maks percobaan gagal
define('LOGIN_LOCKOUT_SEC', 900);  // Waktu kunci: 15 menit
define('RATE_LIMIT_DIR', __DIR__ . '/cache/rate_limit/');

if (!is_dir(RATE_LIMIT_DIR)) {
    mkdir(RATE_LIMIT_DIR, 0755, true);
    // Buat .htaccess pengaman agar folder tidak bisa diakses dari browser
    file_put_contents(RATE_LIMIT_DIR . '.htaccess', 'Deny from all');
}

function getRateLimitFile($ip) {
    return RATE_LIMIT_DIR . md5($ip) . '.json';
}

function checkRateLimit($ip) {
    $file = getRateLimitFile($ip);
    if (!file_exists($file)) return ['blocked' => false, 'attempts' => 0, 'remaining' => 0];

    $data = json_decode(file_get_contents($file), true);
    if (!$data) return ['blocked' => false, 'attempts' => 0, 'remaining' => 0];

    $elapsed = time() - ($data['first_attempt'] ?? time());
    if ($elapsed > LOGIN_LOCKOUT_SEC) {
        // Waktu kunci sudah lewat, reset
        unlink($file);
        return ['blocked' => false, 'attempts' => 0, 'remaining' => 0];
    }

    $blocked = ($data['attempts'] ?? 0) >= LOGIN_MAX_ATTEMPTS;
    $remaining = LOGIN_LOCKOUT_SEC - $elapsed;
    return ['blocked' => $blocked, 'attempts' => $data['attempts'] ?? 0, 'remaining' => $remaining];
}

function recordFailedAttempt($ip) {
    $file = getRateLimitFile($ip);
    $data = ['attempts' => 1, 'first_attempt' => time()];
    if (file_exists($file)) {
        $existing = json_decode(file_get_contents($file), true);
        if ($existing && (time() - ($existing['first_attempt'] ?? time())) <= LOGIN_LOCKOUT_SEC) {
            $data = ['attempts' => ($existing['attempts'] ?? 0) + 1, 'first_attempt' => $existing['first_attempt']];
        }
    }
    file_put_contents($file, json_encode($data));
}

function resetRateLimit($ip) {
    $file = getRateLimitFile($ip);
    if (file_exists($file)) unlink($file);
}

// Cek apakah IP ini sedang diblokir
$visitor_ip   = $_SERVER['REMOTE_ADDR'];
$rate_check   = checkRateLimit($visitor_ip);
if ($rate_check['blocked']) {
    $menit_tersisa = ceil($rate_check['remaining'] / 60);
    $error = "⛔ Terlalu banyak percobaan login gagal. Coba lagi dalam {$menit_tersisa} menit.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // PERBAIKAN: Tolak langsung jika IP sedang diblokir
    if ($rate_check['blocked']) {
        $menit_tersisa = ceil($rate_check['remaining'] / 60);
        $error = "⛔ Terlalu banyak percobaan login gagal. Coba lagi dalam {$menit_tersisa} menit.";
        // Jangan lanjut proses login
        goto end_login_process;
    }

    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    // Query baru buat ngambil data user + SEMUA TIKET yang dia punya
    $stmt = $conn->prepare("
        SELECT 
            u.id, u.username, u.password, u.nama_lengkap, u.role, 
            GROUP_CONCAT(p.nama_izin) AS permissions
        FROM users u
        LEFT JOIN user_permissions up ON u.id = up.user_id
        LEFT JOIN permissions p ON up.permission_id = p.id
        WHERE u.username = ?
        GROUP BY u.id
    ");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        $password_correct = false;
        $needs_rehash = false;

        // Cek gaya Bcrypt (Baru) ATAU gaya SHA-256 (Lama)
        if (password_verify($password, $user['password'])) {
            $password_correct = true;
            // Coba lihat apakah algoritma ini perlu rehash (misal dari bcrypt cost 10 ke 12 di masa depan)
            if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
                $needs_rehash = true;
            }
        } elseif (hash('sha256', $password) === $user['password']) {
            $password_correct = true;
            $needs_rehash = true; // Format lama, wajib convert!
        }

        if ($password_correct) {
            // Login berhasil: Reset hitungan percobaan gagal
            resetRateLimit($visitor_ip);

            // TRANSISI HALUS: Update password di database ke Bcrypt secara diam-diam!
            if ($needs_rehash) {
                $new_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt_upd = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt_upd->bind_param("si", $new_hash, $user['id']);
                $stmt_upd->execute();
                $stmt_upd->close();
            }

            // Regenerasi session ID untuk mencegah serangan Session Fixation
            session_regenerate_id(true);
            
            // Simpan semua info penting ke session, termasuk kantong tiketnya
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
            $_SESSION['role'] = strtolower(trim($user['role'])); // Pastikan role huruf kecil untuk validasi
            $_SESSION['login_time'] = time(); // Set login timestamp for 1 hour auto-logout
            
            // Buat "kantong tiket" buat user
            $_SESSION['permissions'] = $user['permissions'] ? explode(',', $user['permissions']) : [];

            // Catat log login
            write_activity_log('LOGIN', 'auth', "User '" . $user['username'] . "' berhasil login ke sistem");

            // REVISI: Arahkan ke halaman index pake BASE_URL
            header("Location: " . BASE_URL . "/dashboard.php");
            exit;
        } else {
            // Password salah: Catat percobaan gagal
            recordFailedAttempt($visitor_ip);
            $attempts_left = LOGIN_MAX_ATTEMPTS - ($rate_check['attempts'] + 1);
            $error = "❌ Password salah!" . ($attempts_left > 0 ? " (Sisa {$attempts_left} percobaan sebelum diblokir)" : "");
        }
    } else {
        // Username tidak ditemukan: Catat percobaan gagal
        recordFailedAttempt($visitor_ip);
        $attempts_left = LOGIN_MAX_ATTEMPTS - ($rate_check['attempts'] + 1);
        $error = "❌ Username tidak ditemukan!" . ($attempts_left > 0 ? " (Sisa {$attempts_left} percobaan sebelum diblokir)" : "");
    }

    end_login_process:
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <!-- viewport-fit=cover penting untuk iPhone X+ (notch & safe area) -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>AsuhTrack — Login</title>

    <!-- ══════════════════════════════════════
         PWA — MANIFEST
    ══════════════════════════════════════ -->
    <link rel="manifest" href="<?= BASE_URL ?>/manifest.json">

    <!-- ══════════════════════════════════════
         PWA — FAVICON (logo_favicon.png)
    ══════════════════════════════════════ -->
    <link rel="icon" type="image/png" sizes="16x16"  href="<?= BASE_URL ?>/assets/img/logo_favicon.png?v=<?= $favicon_v ?>">
    <link rel="icon" type="image/png" sizes="32x32"  href="<?= BASE_URL ?>/assets/img/logo_favicon.png?v=<?= $favicon_v ?>">
    <link rel="icon" type="image/png" sizes="48x48"  href="<?= BASE_URL ?>/assets/img/logo_favicon.png?v=<?= $favicon_v ?>">
    <link rel="icon" type="image/png" sizes="96x96"  href="<?= BASE_URL ?>/assets/img/logo_favicon.png?v=<?= $favicon_v ?>">
    <link rel="shortcut icon" type="image/png"       href="<?= BASE_URL ?>/assets/img/logo_favicon.png?v=<?= $favicon_v ?>">

    <!-- ══════════════════════════════════════
         PWA — APPLE / iOS
    ══════════════════════════════════════ -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="AsuhTrack">
    <link rel="apple-touch-icon"               href="<?= BASE_URL ?>/assets/img/logo_aplikasi.png?v=<?= $appicon_v ?>">
    <link rel="apple-touch-icon" sizes="120x120" href="<?= BASE_URL ?>/assets/img/logo_aplikasi.png?v=<?= $appicon_v ?>">
    <link rel="apple-touch-icon" sizes="152x152" href="<?= BASE_URL ?>/assets/img/logo_aplikasi.png?v=<?= $appicon_v ?>">
    <link rel="apple-touch-icon" sizes="167x167" href="<?= BASE_URL ?>/assets/img/logo_aplikasi.png?v=<?= $appicon_v ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= BASE_URL ?>/assets/img/logo_aplikasi.png?v=<?= $appicon_v ?>">

    <!-- ══════════════════════════════════════
         PWA — THEME & GENERAL META
    ══════════════════════════════════════ -->
    <meta name="theme-color" content="#16a34a">
    <meta name="msapplication-TileColor" content="#16a34a">
    <meta name="msapplication-TileImage" content="<?= BASE_URL ?>/assets/img/logo_aplikasi.png?v=<?= $appicon_v ?>">
    <meta name="msapplication-config" content="none">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="application-name" content="AsuhTrack">
    <meta name="description" content="Sistem informasi kepengasuhan santri — catat pelanggaran, reward, dan rekap secara digital.">

    <!-- ══════════════════════════════════════
         ASET & FONT
    ══════════════════════════════════════ -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= $style_v ?>">

    <!-- ══════════════════════════════════════
         PWA — SERVICE WORKER REGISTRATION
    ══════════════════════════════════════ -->
    <script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function() {
            navigator.serviceWorker
                .register('<?= BASE_URL ?>/sw.js', { scope: '<?= BASE_URL ?>/' })
                .then(function(reg) {
                    reg.addEventListener('updatefound', function() {
                        var nw = reg.installing;
                        nw.addEventListener('statechange', function() {
                            if (nw.state === 'installed' && navigator.serviceWorker.controller) {
                                nw.postMessage({ type: 'SKIP_WAITING' });
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
<body class="login-body">
    <div class="login-card-wrapper">
        <div class="login-header">
            <img src="<?= BASE_URL ?>/assets/img/logo_aplikasi.png?v=<?= $appicon_v ?>" alt="Logo AsuhTrack" class="login-logo">
            <h4 class="mb-1">AsuhTrack</h4>
            <p class="text-muted text-sm mb-4">Sistem Kepengasuhan Santri</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger d-flex align-items-center py-2 px-3 text-sm mb-4" style="border-radius: 0.75rem; border: none; background: #fee2e2; color: #991b1b; font-weight: 500;">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($info): ?>
            <div class="alert alert-info d-flex align-items-center py-2 px-3 text-sm mb-4" style="border-radius: 0.75rem; border: none; background: #e0f2fe; color: #075985; font-weight: 500;">
                <i class="fas fa-info-circle me-2"></i>
                <?= htmlspecialchars($info) ?>
            </div>
        <?php endif; ?>

        <form method="post">
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

    <!-- ── Tombol Install PWA (muncul otomatis jika bisa di-install) ── -->
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
    // ── Pembersihan Storage Sisi Client (Fallback Keamanan) ──────────
    try {
        localStorage.clear();
        sessionStorage.clear();
    } catch (e) {
        console.warn('[PWA] Gagal membersihkan storage:', e);
    }

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

    // ── PWA Install Button Logic ──────────────────────────────────────
    let _deferredPrompt = null;
    const installBar = document.getElementById('pwa-install-bar');
    const installBtn = document.getElementById('pwa-install-btn');

    // Tangkap event install dari browser (Android Chrome, Edge, dsb)
    window.addEventListener('beforeinstallprompt', function(e) {
        e.preventDefault();
        _deferredPrompt = e;
        // Tampilkan tombol dengan animasi slide-up
        installBar.style.display = 'block';
    });

    // Klik tombol → munculkan dialog install native browser
    if (installBtn) {
        installBtn.addEventListener('click', async function() {
            if (!_deferredPrompt) return;
            _deferredPrompt.prompt();
            const { outcome } = await _deferredPrompt.userChoice;
            _deferredPrompt = null;
            // Sembunyikan tombol setelah user pilih (install atau tidak)
            installBar.style.display = 'none';
        });
    }

    // Sembunyikan tombol jika app sudah ter-install
    window.addEventListener('appinstalled', function() {
        installBar.style.display = 'none';
        _deferredPrompt = null;
    });
    </script>
</body>
</html>