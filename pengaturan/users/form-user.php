<?php
ob_start(); // Tahan semua output dulu, biar aman pas redirect

require_once __DIR__ . '/../../header.php';
guard('user_manage');

// --- LOGIKA PHP INI SUDAH BENAR DAN TIDAK DIUBAH ---
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

        if (
            strtolower($user_data['role']) === 'admin' &&
            (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'admin')
        ) {
            $_SESSION['error_message'] = "❌ Anda tidak memiliki izin untuk mengedit user Admin.";
            header("Location: index.php");
            exit;
        }

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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* ===== BAGIAN CSS YANG HILANG SUDAH GW KEMBALIKAN UTUH DI SINI ===== */
        :root {
            --primary-color: #198754;
            --primary-hover: #157347;
            --text-dark: #334155;
            --text-light: #64748b;
            --border-color: #e2e8f0;
            --card-bg: #ffffff;
            --body-bg: #f1f5f9;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--body-bg);
            color: var(--text-dark);
        }

        .main-container {
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: 100vh;
            padding: 2rem 1rem;
        }
        
        .form-content-wrapper { 
            max-width: 550px;
            width: 100%;
        }

        .page-header { 
            text-align: left; 
            margin-bottom: 1.5rem; 
        }
        .page-header h1 { 
            font-size: 2rem; 
            font-weight: 700; 
            color: var(--text-dark); 
            display: flex; 
            align-items: center; 
            gap: 0.75rem; 
        }
        .page-header h1 i { 
            color: var(--primary-color); 
        }
        .page-header p { 
            font-size: 1rem; 
            color: var(--text-light); 
            margin-top: 0.25rem; 
        }
        .form-card { 
            border: 1px solid var(--border-color); 
            border-radius: 0.75rem; 
            box-shadow: 0 4px 25px rgba(0,0,0,0.07); 
            padding: 2.5rem;
            background-color: var(--card-bg);
        }
        .form-label { 
            font-weight: 600; 
            color: var(--text-dark); 
            margin-bottom: 0.5rem; 
            display: block; /* Tambahan agar label rapi */
        }
        .input-group {
            position: relative;
        }
        .input-group .input-group-text {
            position: absolute;
            left: 1px; top: 1px; bottom: 1px;
            z-index: 10;
            display: flex;
            align-items: center;
            padding: 0 1rem;
            background-color: transparent;
            border: none;
            color: var(--text-light);
        }
        .input-group .form-control {
            padding-left: 3rem;
            border-radius: 0.5rem !important;
            border: 1px solid var(--border-color);
            transition: all 0.2s ease;
            height: 48px;
            width: 100%; /* Tambahan agar input full width */
            padding-top: 0.5rem; /* Perbaikan padding */
            padding-bottom: 0.5rem; /* Perbaikan padding */
            font-size: 1rem; /* Tambahan agar konsisten */
        }
        .form-control:focus { 
            border-color: var(--primary-color); 
            box-shadow: 0 0 0 3px rgba(25, 135, 84, 0.1);
        }
        .password-wrapper { 
            position: relative; 
        }
        .password-wrapper .form-control {
            padding-left: 3rem;
        }
        .password-toggle { 
            position: absolute; 
            right: 12px; 
            top: 50%; 
            transform: translateY(-50%); 
            background: none; 
            border: none; 
            color: var(--text-light); 
            cursor: pointer; 
            z-index: 11;
        }
        .btn-submit { 
            background-color: var(--primary-color); 
            color: white; /* Tambahan warna teks */
            border: none; 
            font-weight: 600; 
            padding: 0.8rem; 
            font-size: 1rem; 
            transition: all 0.2s ease;
            border-radius: 0.5rem;
            box-shadow: 0 4px 10px rgba(25, 135, 84, 0.2);
            width: 100%; /* Tambahan agar full width */
            cursor: pointer; /* Tambahan */
        }
        .btn-submit:hover { 
            background-color: var(--primary-hover); 
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(25, 135, 84, 0.3);
        }
        .mb-3 { margin-bottom: 1.5rem !important; } /* Konsistensi margin */
        .mb-4 { margin-bottom: 2rem !important; } /* Konsistensi margin */
        .alert { padding: 1rem; margin-bottom: 1.5rem; border-radius: 0.5rem; } /* Style notifikasi */
        .alert-success { background-color: #d1e7dd; color: #0f5132; border: 1px solid #badbcc; }
        .alert-danger { background-color: #f8d7da; color: #842029; border: 1px solid #f5c2c7; }

        .form-text {
            display: block;
            margin-top: 0.5rem;
            font-size: 0.85rem;
            color: var(--text-light);
        }
    </style>
</head>
<body>  
    <div class="main-container">
        <div class="form-content-wrapper">
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
                            placeholder="<?= $is_edit_mode ? 'Ubah password' : 'Masukkan password' ?>" 
                            <?= !$is_edit_mode ? 'required' : '' ?>
                            autocomplete="new-password"> 
                        <button type="button" class="password-toggle" onclick="togglePassword()">
                            <i class="fas fa-eye" id="toggle-icon"></i>
                        </button>
                    </div>
                    
                    <?php if ($is_edit_mode): ?>
                        <small class="form-text">Kosongkan jika tidak ingin mengubah password.</small>
                    <?php endif; ?>

                </div>

                <button type="submit" class="btn-submit">
                    <i class="fas <?= $is_edit_mode ? 'fa-save' : 'fa-plus-circle' ?>"></i>
                    <?= $button_text ?>
                </button>
            </form>
        </div>
    </div>

    <script>
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