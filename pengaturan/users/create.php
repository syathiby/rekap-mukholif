<?php 
require_once __DIR__ . '/../../header.php';
guard('user_manage'); 

// Karena header.php sudah memanggil db.php, variabel $conn sudah ada.
// Kita tinggal pakai saja. Tidak ada yang perlu diubah di file db.php lu.

// Ambil semua role unik yang SUDAH ADA di tabel users, PAKE MYSQLI
$existing_roles = []; // Siapkan array kosong untuk jaga-jaga kalau query gagal
$sql = "SELECT DISTINCT role FROM users WHERE role IS NOT NULL AND role != '' ORDER BY role ASC";
$result = mysqli_query($conn, $sql);

if ($result) {
    // Ambil semua baris hasilnya dan masukkan ke array
    $existing_roles = mysqli_fetch_all($result, MYSQLI_ASSOC);
}
// --------------------------------------------------------------------

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah User Baru</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary-color: #198754;
            --text-dark: #1e293b;
            --text-light: #64748b;
            --border-color: #e2e8f0;
            --card-bg: #ffffff;
        }
        .create-user-container {
            max-width: 550px;
            margin: 0 auto;
            padding: 1rem;
        }
        .page-header {
            text-align: left;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
        }
        .page-header h1 {
            font-size: 1.8rem;
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
            font-size: 0.95rem;
            color: var(--text-light);
            margin-top: 0.25rem;
        }

        /* Styling Form yang Disederhanakan dan Diperbaiki */
        .form-card {
            border: none;
            border-radius: 0.75rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            padding: 2rem;
        }
        
        .form-label {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }
        
        /* Menggunakan input-group untuk ikon di kiri */
        .input-group .form-control {
            border-radius: 0.375rem !important;
            border-top-left-radius: 0 !important;
            border-bottom-left-radius: 0 !important;
        }
        .input-group .input-group-text {
            border-radius: 0.375rem !important;
            border-top-right-radius: 0 !important;
            border-bottom-right-radius: 0 !important;
            background-color: #f8fafc;
            border-right: none;
            color: var(--text-light);
        }
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(25, 135, 84, 0.2);
        }

        /* Wrapper untuk password toggle */
        .password-wrapper {
            position: relative;
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
            z-index: 5;
        }
        /* Penyesuaian padding untuk input password agar tidak tertimpa ikon mata */
        .password-wrapper .form-control {
            padding-right: 40px;
        }

        .btn-submit {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            font-weight: 600;
            padding: 0.75rem;
            font-size: 1rem;
            transition: background-color 0.2s ease;
        }
        .btn-submit:hover {
            background-color: #157347;
            border-color: #146c43;
        }
    </style>
</head>
<body>
    <div class="create-user-container">
        <div class="page-header">
            <h1><i class="fas fa-user-plus"></i>Tambah User Baru</h1>
            <p>Buat akun baru untuk pengguna sistem dan tentukan jabatannya.</p>
        </div>

        <?php if (isset($success_message) && $success_message): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>
        <?php if (isset($error_message) && $error_message): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <form action="process-create.php" method="POST" class="form-card">
            
            <!-- Nama Lengkap -->
            <div class="mb-3">
                <label for="nama_lengkap" class="form-label">Nama Lengkap</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-user fa-fw"></i></span>
                    <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" placeholder="Masukkan nama lengkap" required>
                </div>
            </div>

            <!-- Username -->
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-at fa-fw"></i></span>
                    <input type="text" class="form-control" id="username" name="username" placeholder="Masukkan username" required>
                </div>
            </div>
            
            <!-- Jabatan (Role) -->
            <div class="mb-3">
                <label for="role" class="form-label">Jabatan (Role)</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-shield-halved fa-fw"></i></span>
                    <input class="form-control" list="role-list" id="role" name="role" placeholder="Pilih atau ketik role baru..." required>
                </div>
                <datalist id="role-list">
                    <?php foreach ($existing_roles as $role_row): ?>
                        <option value="<?= htmlspecialchars($role_row['role']) ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>

            <!-- Password -->
            <div class="mb-4">
                <label for="password" class="form-label">Password</label>
                <div class="password-wrapper">
                    <input type="password" class="form-control" id="password" name="password" placeholder="Masukkan password" required>
                    <button type="button" class="password-toggle" onclick="togglePassword()">
                        <i class="fas fa-eye" id="toggle-icon"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 btn-submit">
                <i class="fas fa-plus-circle me-2"></i>Buat Akun User
            </button>
        </form>
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
?>