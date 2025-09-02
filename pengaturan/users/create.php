<?php 
require_once __DIR__ . '/../../header.php';
guard('user_manage'); 
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
            --primary-color: #198754; /* Warna hijau khas 'create' */
            --text-dark: #1e293b;
            --text-light: #64748b;
            --border-color: #e2e8f0;
            --card-bg: #ffffff;
            --form-bg: #f8fafc;
        }
        .create-user-container {
            max-width: 600px;
            margin: 0 auto;
            padding: 1rem;
        }
        .page-header {
            text-align: left;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--border-color);
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
            margin-top: 0.5rem;
        }
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(25, 135, 84, 0.15);
        }
        .password-wrapper {
            position: relative;
        }
        .password-toggle {
            position: absolute;
            right: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-light);
            cursor: pointer;
        }
        .btn-submit {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            font-weight: 600;
            padding: 0.75rem 1.5rem;
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

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success_message']) ?></div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error_message']) ?></div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <form action="process-create.php" method="POST" class="card card-body">
            <div class="mb-3">
                <label for="nama_lengkap" class="form-label fw-bold">Nama Lengkap</label>
                <input type="text" class="form-control form-control-lg" id="nama_lengkap" name="nama_lengkap" required>
            </div>

            <div class="mb-3">
                <label for="username" class="form-label fw-bold">Username</label>
                <input type="text" class="form-control form-control-lg" id="username" name="username" required>
            </div>

            <!-- === BAGIAN BARU: PILIH ROLE === -->
            <div class="mb-3">
                <label for="role" class="form-label fw-bold">Jabatan (Role)</label>
                <select class="form-select form-select-lg" id="role" name="role" required>
                    <option value="" disabled selected>-- Pilih Jabatan --</option>
                    <option value="user">User Biasa</option>
                    <option value="pengabdian">Pengabdian</option>
                    <option value="kesantrian">Kesantrian</option>
                    <option value="bahasa">Bahasa</option>
                    <option value="diniyyah">Diniyyah</option>
                </select>
            </div>
            <!-- =============================== -->

            <div class="mb-3">
                <label for="password" class="form-label fw-bold">Password</label>
                <div class="password-wrapper">
                    <input type="password" class="form-control form-control-lg" id="password" name="password" required>
                    <button type="button" class="password-toggle" onclick="togglePassword()">
                        <i class="fas fa-eye" id="toggle-icon"></i>
                    </button>
                </div>
            </div>

            <div class="d-flex justify-content-end mt-3">
                <button type="submit" class="btn btn-primary btn-lg btn-submit">
                    <i class="fas fa-plus-circle me-2"></i>Buat Akun User
                </button>
            </div>
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