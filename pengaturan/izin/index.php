<?php 
require_once __DIR__ . '/../../header.php';
guard('izin_manage'); 
?>

<?php

// Ambil semua user untuk dropdown
$usersResult = $conn->query("SELECT id, nama_lengkap, username FROM users WHERE role != 'admin' ORDER BY nama_lengkap ASC");

// Ambil ID user yang mau di-edit dari URL (jika ada)
$selectedUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$permissions = [];
$userPermissions = [];
$selectedUserName = '';

if ($selectedUserId) {
    // Ambil nama user yang dipilih buat ditampilin di judul
    $userQuery = $conn->prepare("SELECT nama_lengkap FROM users WHERE id = ?");
    $userQuery->bind_param("i", $selectedUserId);
    $userQuery->execute();
    $selectedUserName = $userQuery->get_result()->fetch_assoc()['nama_lengkap'] ?? '';

    // Ambil semua jenis tiket dari "Buku Menu", dikelompokkan
    $permResult = $conn->query("SELECT id, nama_izin, deskripsi, grup FROM permissions ORDER BY grup, nama_izin ASC");
    while($row = $permResult->fetch_assoc()) {
        $permissions[$row['grup']][] = $row;
    }

    // Ambil semua tiket yang sudah dimiliki user terpilih
    $userPermResult = $conn->query("SELECT permission_id FROM user_permissions WHERE user_id = $selectedUserId");
    while($row = $userPermResult->fetch_assoc()) {
        $userPermissions[] = $row['permission_id'];
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan Izin Akses</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary-color: #fd7e14; /* Warna oranye yang khas buat izin */
            --text-dark: #1e293b;
            --text-light: #64748b;
            --border-color: #e2e8f0;
            --card-bg: #ffffff;
            --form-bg: #f8fafc;
        }
        .permission-container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 1rem;
        }
        .page-header {
            text-align: left;
            margin-bottom: 2.5rem;
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
        .user-select-form {
            background-color: var(--form-bg);
            border: 1px solid var(--border-color);
            padding: 1.5rem;
            border-radius: 0.75rem;
            margin-bottom: 2.5rem;
        }
        .user-select-form .form-select {
            font-weight: 500;
        }
        .permission-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 1.5rem;
        }
        .permission-group-card {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 0.75rem;
            overflow: hidden; /* Biar header cardnya rapi */
        }
        .permission-group-header {
            padding: 1rem 1.25rem;
            background-color: var(--form-bg);
            border-bottom: 1px solid var(--border-color);
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .permission-group-body {
            padding: 1.25rem;
        }
        
        /* ✅✅✅ INI DIA PERBAIKANNYA ✅✅✅ */
        .permission-item {
            display: flex; /* 1. Jadikan Flexbox */
            align-items: flex-start; /* 2. Semua item rata atas */
            gap: 0.75rem; /* 3. Kasih jarak antara tombol & teks */
            margin-bottom: 1rem;
        }
        .permission-item .form-check-input {
            flex-shrink: 0; /* Biar tombolnya gak menciut */
            margin-top: 0.2em; /* Sedikit turunin biar pas sama baris pertama teks */
        }
        .permission-item:last-child {
            margin-bottom: 0;
        }
        /* ✅✅✅ SELESAI ✅✅✅ */

        .form-check-label {
            font-weight: 500;
            cursor: pointer;
        }
        .form-check-label small {
            font-weight: 400;
        }
        .form-switch .form-check-input {
            cursor: pointer;
            width: 3em;
            height: 1.5em;
        }
        .form-switch .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        .save-button-wrapper {
            position: sticky;
            bottom: 1rem;
            display: flex;
            justify-content: flex-end;
            margin-top: 2rem;
            z-index: 10; /* Biar tombol di atas konten lain */
        }
        .btn-save {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            font-weight: 600;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            box-shadow: 0 4px 12px rgba(253, 126, 20, 0.3);
            transition: all 0.2s ease-in-out;
            color: white;
        }
        .btn-save:hover {
            background-color: #e67012;
            border-color: #e67012;
            transform: translateY(-2px);
            color: white;
        }
        .placeholder-text {
            text-align: center;
            padding: 3rem;
            background-color: var(--form-bg);
            border-radius: 0.75rem;
            border: 2px dashed var(--border-color);
        }
        .placeholder-text i {
            font-size: 3rem;
            color: var(--border-color);
            margin-bottom: 1rem;
        }

        @media (max-width: 767.98px) {
            .permission-container {
                padding: 0.75rem;
            }
            .page-header h1 {
                font-size: 1.6rem;
            }
            .page-header p {
                font-size: 0.9rem;
            }
            .permission-grid {
                grid-template-columns: 1fr;
            }
            .save-button-wrapper {
                position: sticky;
                bottom: 0;
                left: 0;
                width: 100%;
                background: linear-gradient(to top, white 70%, transparent); 
                padding: 1rem 0.75rem;
                margin: 1.5rem -0.75rem -0.75rem -0.75rem;
                border-top: 1px solid var(--border-color);
                justify-content: center;
            }
            .btn-save {
                width: 100%;
                font-size: 1.1rem;
            }
        }
    </style>
</head>
<body>
    <div class="permission-container">
        <div class="page-header">
            <h1><i class="fas fa-ticket-alt"></i>Loket Pengaturan Izin</h1>
            <p>Pilih user, lalu atur hak akses atau "tiket" yang mereka miliki untuk setiap fitur.</p>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success"><?= $_SESSION['success_message'] ?></div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger"><?= $_SESSION['error_message'] ?></div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <div class="user-select-form">
            <form method="GET">
                <div class="input-group">
                    <label class="input-group-text fw-bold" for="user_id"><i class="fas fa-user-shield me-2"></i>Pilih User:</label>
                    <select class="form-select" name="user_id" id="user_id" onchange="this.form.submit()">
                        <option value="">-- Pilih User untuk Diatur --</option>
                        <?php mysqli_data_seek($usersResult, 0); while($user = $usersResult->fetch_assoc()): ?>
                            <option value="<?= $user['id'] ?>" <?= ($selectedUserId == $user['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($user['nama_lengkap']) ?> (<?= htmlspecialchars($user['username']) ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </form>
        </div>
        
        <?php if ($selectedUserId && !empty($permissions)): ?>
            <form action="process.php" method="POST">
                <input type="hidden" name="user_id" value="<?= $selectedUserId ?>">
                
                <h4 class="mb-3">Atur Izin untuk: <strong class="text-primary"><?= htmlspecialchars($selectedUserName) ?></strong></h4>
                
                <div class="permission-grid">
                    <?php foreach ($permissions as $grup => $items): ?>
                        <div class="permission-group-card">
                            <div class="permission-group-header">
                                <i class="fas fa-folder-open"></i><?= htmlspecialchars($grup) ?>
                            </div>
                            <div class="permission-group-body">
                                <?php foreach ($items as $perm): ?>
                                    <!-- ✅ STRUKTUR HTML-NYA SEDIKIT DIUBAH BIAR LEBIH GAMPANG DI-STYLE -->
                                    <div class="permission-item form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="permissions[]" value="<?= $perm['id'] ?>" id="perm_<?= $perm['id'] ?>"
                                            <?= in_array($perm['id'], $userPermissions) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="perm_<?= $perm['id'] ?>">
                                            <?= htmlspecialchars($perm['deskripsi']) ?>
                                            <small class="text-muted d-block">(Kode: <?= htmlspecialchars($perm['nama_izin']) ?>)</small>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="save-button-wrapper">
                    <button type="submit" class="btn btn-primary btn-lg btn-save">
                        <i class="fas fa-save me-2"></i>Simpan Perubahan
                    </button>
                </div>
            </form>
        <?php elseif ($selectedUserId): ?>
            <div class="placeholder-text">
                <i class="fas fa-exclamation-circle"></i>
                <p class="h5 text-muted">Tidak ada data izin (tiket) yang tersedia untuk diatur.</p>
            </div>
        <?php else: ?>
            <div class="placeholder-text">
                <i class="fas fa-mouse-pointer"></i>
                <p class="h5 text-muted">Pilih seorang user dari daftar di atas untuk mulai mengatur izin aksesnya.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

<?php require_once __DIR__ . '/../../footer.php'; ?>