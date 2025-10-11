<?php
// Cek dulu: kalau session belum aktif, baru kita atur dan mulai
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_lifetime', 0); // Auto logout saat browser ditutup
    session_start();
}

// Panggil file config sakti kita buat dapetin BASE_URL
require_once __DIR__ . '/config.php';
include 'db.php';

$error = '';

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
    <title>Login Sistem</title>
    <!-- REVISI: Path favicon jadi dinamis pake BASE_URL -->
    <link rel="icon" type="image/png" sizes="64x64" href="<?= BASE_URL ?>/assets/logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body {
            background-color: #f4f7fa;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            font-family: 'Poppins', sans-serif;
        }
        .login-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.08);
            padding: 2.5rem;
            width: 100%;
            max-width: 400px;
        }
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .login-logo {
            max-width: 60px;
            margin-bottom: 1rem;
        }
        .login-header h4 {
            font-weight: 600;
            color: #1e293b;
        }
        .form-control {
            border-radius: 8px;
            padding: 0.75rem 1rem;
            border: 1px solid #e2e8f0;
        }
        .form-control:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.1);
        }
        .btn-login {
            background-color: #0d6efd;
            border: none;
            padding: 0.75rem;
            font-weight: 500;
            border-radius: 8px;
        }
        .btn-login:hover {
            background-color: #0b5ed7;
        }
        .password-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }
        .password-wrapper .form-control {
            padding-right: 40px;
        }
        .password-toggle {
            position: absolute;
            right: 12px;
            cursor: pointer;
            background: none;
            border: none;
            color: #6c757d;
            padding: 0;
            line-height: 1;
        }
        .alert {
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <!-- REVISI: Path logo jadi dinamis pake BASE_URL -->
            <img src="<?= BASE_URL ?>/assets/logo.png" alt="Logo Aplikasi" class="login-logo">
            <h4>Login Sistem</h4>
            <p class="text-muted">Masukkan kredensial Anda untuk melanjutkan</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" class="form-control" id="username" name="username" required placeholder="Contoh: admin">
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <div class="password-wrapper">
                    <input type="password" class="form-control" id="password" name="password" required placeholder="••••••••">
                    <button type="button" class="password-toggle" onclick="togglePassword()">
                        <span id="toggle-icon">👁️</span>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-login w-100 mt-3">Masuk</button>
        </form>
    </div>

    <script>
    function togglePassword() {
        const passwordInput = document.getElementById('password');
        const toggleIcon = document.getElementById('toggle-icon');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            toggleIcon.textContent = '🙈';
        } else {
            passwordInput.type = 'password';
            toggleIcon.textContent = '👁️';
        }
    }
    </script>
</body>
</html>