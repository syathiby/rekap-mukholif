<?php
// Cek dulu: kalau session belum aktif, baru kita atur dan mulai
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_lifetime', 0); // Auto logout saat browser ditutup
    session_start();
}

// Panggil file config sakti kita buat dapetin BASE_URL
require_once __DIR__ . '/config/app.php';

// --- START: KODE OTOMATIS VERSI LOGO ---
// Path ini harus nunjuk ke file di server, BUKAN URL.
// Asumsi /assets/img/logo.png ada di folder assets/img/
// Kalau project lu di subfolder (misal: htdocs/aplikasi-santri), ganti path-nya jadi:
// $logo_server_path = $_SERVER['DOCUMENT_ROOT'] . '/aplikasi-santri/assets/img/logo.png';
$logo_server_path = $_SERVER['DOCUMENT_ROOT'] . '/assets/img/logo.png';
$logo_version = file_exists($logo_server_path)
    ? filemtime($logo_server_path)
    : '1'; // Fallback jika file tidak ditemukan
// --- END: KODE OTOMATIS VERSI LOGO ---

require_once __DIR__ . '/config/database.php';

$error = '';
if (isset($_GET['timeout'])) {
    $error = "🔒 Sesi Anda telah berakhir demi keamanan (maksimal 1 jam). Silakan masuk kembali.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        if (hash('sha256', $password) === $user['password']) {
            
            // Simpan semua info penting ke session, termasuk kantong tiketnya
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
            $_SESSION['role'] = $user['role']; // Kita simpen role buat ngecek admin
            $_SESSION['login_time'] = time(); // Set login timestamp for 1 hour auto-logout
            
            // Buat "kantong tiket" buat user
            $_SESSION['permissions'] = $user['permissions'] ? explode(',', $user['permissions']) : [];

            // REVISI: Arahkan ke halaman index pake BASE_URL
            header("Location: " . BASE_URL . "/index.php");
            exit;
        } else {
            $error = "❌ Password salah!";
        }
    } else {
        $error = "❌ Username tidak ditemukan!";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AsuhTrack</title>
    <!-- REVISI: Path favicon jadi dinamis + otomatis cache bust -->
    <link rel="icon" type="image/png" sizes="64x64" href="<?= BASE_URL ?>/assets/img/logo.png?v=<?= $logo_version ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= time() ?>">
</head>
<body class="login-body">
    <!-- Subtle gradient blobs for modern glassmorphism background -->
    <div class="login-bg-blob login-bg-blob-1"></div>
    <div class="login-bg-blob login-bg-blob-2"></div>

    <div class="login-card-wrapper">
        <div class="login-header">
            <!-- REVISI: Path logo jadi dinamis + otomatis cache bust -->
            <img src="<?= BASE_URL ?>/assets/img/logo.png?v=<?= $logo_version ?>" alt="Logo Aplikasi" class="login-logo">
            <h4 class="mb-1">AsuhTrack</h4>
            <p class="text-muted text-sm mb-4">Sistem Kepengasuhan Santri</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2 px-3 text-sm rounded-3 mb-3"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="mb-3">
                <label for="username" class="form-label text-sm fw-medium text-secondary mb-1">Username</label>
                <input type="text" class="form-control" id="username" name="username" required placeholder="Masukkan username">
            </div>

            <div class="mb-4">
                <label for="password" class="form-label text-sm fw-medium text-secondary mb-1">Password</label>
                <div class="password-wrapper">
                    <input type="password" class="form-control" id="password" name="password" required placeholder="••••••••">
                    <button type="button" class="password-toggle" onclick="togglePassword()">
                        <span id="toggle-icon"><i class="fas fa-eye text-muted"></i></span>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2.5 rounded-3 fw-semibold shadow-sm" style="background: var(--primary); border: none; font-size: 0.95rem;">
                Masuk Sistem
            </button>
        </form>
    </div>

    <script>
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
    </script>
</body>
</html>