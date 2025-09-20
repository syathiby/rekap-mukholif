<?php
ob_start(); // Tahan semua output dulu, biar aman pas redirect

require_once __DIR__ . '/../../header.php';
guard('user_manage');

// --- LOGIKA PHP ---
$is_edit_mode = false;
$user_id = null;
$user_data = [
    'nama_lengkap' => '',
    'username' => '',
    'role' => ''
];
$page_title = 'Tambah User Baru';
$page_subtitle = 'Buat akun baru untuk pengguna sistem.';
$button_text = 'Buat Akun User';
$form_action = 'process-user.php';

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $is_edit_mode = true;
    $user_id = (int)$_GET['id'];
    $stmt = $conn->prepare("SELECT nama_lengkap, username, role FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user_data = $result->fetch_assoc();

        // ====================================================================
        // ===== INI DIA PENJAGA PINTUNYA! =====
        // ====================================================================
        // Cek: Apakah kita mau edit user 'admin', TAPI yang login BUKAN 'admin'?
        if (
            strtolower($user_data['role']) === 'admin' &&
            (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'admin')
        ) {
            // Kalau iya, usir dia dan kasih pesan error!
            $_SESSION['error_message'] = "❌ Anda tidak memiliki izin untuk mengedit user Admin.";
            header("Location: index.php");
            exit; // Langsung hentikan script
        }
        // ====================================================================

        $page_title = 'Edit User';
        $page_subtitle = 'Ubah detail untuk user ' . htmlspecialchars($user_data['username']);
        $button_text = 'Simpan Perubahan';
    } else {
        $is_edit_mode = false; 
    }
    $stmt->close();
}

$existing_roles = [];
$sql_roles = "SELECT DISTINCT role FROM users WHERE role IS NOT NULL AND role != '' ORDER BY role ASC";
$result_roles = mysqli_query($conn, $sql_roles);
if ($result_roles) {
    $existing_roles = mysqli_fetch_all($result_roles, MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <!-- Semua kode di dalam <head> TIDAK DIUBAH SAMA SEKALI -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Semua STYLE TIDAK DIUBAH SAMA SEKALI */
        :root {
            --primary-color: #198754;
            --primary-hover: #157347;
            --text-dark: #334155;
            --text-light: #64748b;
            --border-color: #e2e8f0;
            --card-bg: #ffffff;
            --body-bg: #f1f5f9;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background-color: var(--body-bg); color: var(--text-dark); }
        .main-container { display: flex; justify-content: center; align-items: flex-start; min-height: 100vh; padding: 2rem 1rem; }
        .form-content-wrapper { max-width: 550px; width: 100%; }
        .page-header { text-align: left; margin-bottom: 1.5rem; }
        .page-header h1 { font-size: 2rem; font-weight: 700; color: var(--text-dark); display: flex; align-items: center; gap: 0.75rem; }
        .page-header h1 i { color: var(--primary-color); }
        .page-header p { font-size: 1rem; color: var(--text-light); margin-top: 0.25rem; }
        .form-card { border: 1px solid var(--border-color); border-radius: 0.75rem; box-shadow: 0 4px 25px rgba(0,0,0,0.07); padding: 2.5rem; background-color: var(--card-bg); }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="form-content-wrapper">
            <!-- Semua kode HTML di dalam <body> TIDAK DIUBAH SAMA SEKALI -->
            <div class="page-header">
                <h1>
                    <i class="fas <?= $is_edit_mode ? 'fa-user-pen' : 'fa-user-plus' ?>"></i>
                    <?= $page_title ?>
                </h1>
                <p><?= $page_subtitle ?></p>
            </div>

            <?php if (isset($_SESSION['success_message']) && $_SESSION['success_message']): ?>
                <div class="alert alert-success"><?= $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error_message']) && $_SESSION['error_message']): ?>
                <div class="alert alert-danger"><?= $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
            <?php endif; ?>

            <form action="<?= $form_action ?>" method="POST" class="form-card">
                
                <?php if ($is_edit_mode): ?>
                    <input type="hidden" name="user_id" value="<?= $user_id ?>">
                <?php endif; ?>

                <div class="mb-3">
                    <label for="nama_lengkap" class="form-label">Nama Lengkap</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user fa-fw"></i></span>
                        <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" value="<?= htmlspecialchars($user_data['nama_lengkap']) ?>" placeholder="Masukkan nama lengkap" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-at fa-fw"></i></span>
                        <input type="text" class="form-control" id="username" name="username" value="<?= htmlspecialchars($user_data['username']) ?>" placeholder="Masukkan username" required>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="role" class="form-label">Jabatan (Role)</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-shield-halved fa-fw"></i></span>
                        <input class="form-control" list="role-list" id="role" name="role" value="<?= htmlspecialchars($user_data['role']) ?>" placeholder="Pilih atau ketik role baru..." required>
                    </div>
                    <datalist id="role-list">
                        <?php foreach ($existing_roles as $role_row): ?>
                            <option value="<?= htmlspecialchars($role_row['role']) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>

                <div class="mb-4">
                    <label for="password" class="form-label">Password</label>
                    <div class="password-wrapper">
                        <input type="password" class="form-control" id="password" name="password" 
                               placeholder="<?= $is_edit_mode ? 'Kosongkan jika tidak diubah' : 'Masukkan password' ?>" 
                               <?= !$is_edit_mode ? 'required' : '' ?>
                               autocomplete="new-password"> 
                        <button type="button" class="password-toggle" onclick="togglePassword()">
                            <i class="fas fa-eye" id="toggle-icon"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 btn-submit">
                    <i class="fas <?= $is_edit_mode ? 'fa-save' : 'fa-plus-circle' ?> me-2"></i>
                    <?= $button_text ?>
                </button>
            </form>
        </div>
    </div>

    <script>
        // Javascript TIDAK DIUBAH SAMA SEKALI
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggle-icon');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }
    </script>
</body>
</html>

<?php
require_once __DIR__ . '/../../footer.php';
ob_end_flush(); // Kirim output yang tadi ditahan
?>