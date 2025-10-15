<?php
// 1. Panggil 'Otak' aplikasi dulu
require_once __DIR__ . '/../../init.php';

// 2. Jalankan 'SATPAM' buat ngejaga halaman
guard('user_manage'); 

// 3. Kalau lolos, baru panggil Tampilan
require_once __DIR__ . '/../../header.php';

// --- LOGIKA PHP TIDAK DIUBAH SAMA SEKALI ---
$sql = "SELECT id, nama_lengkap, username, role FROM users ORDER BY nama_lengkap ASC";
$result = mysqli_query($conn, $sql);

$users = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $users[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen User</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* ==================================================================== */
        /* ===== BAGIAN CSS YANG HILANG SUDAH GW BALIKIN LAGI DI SINI ===== */
        /* ==================================================================== */
        :root {
            --primary-color: #0d6efd;
            --primary-hover: #0b5ed7;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --text-dark: #212529;
            --text-light: #6c757d;
            --border-color: #dee2e6;
            --card-bg: #ffffff;
            --body-bg: #f1f5f9;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--body-bg);
            color: var(--text-dark);
        }

        .user-management-container {
            max-width: 950px;
            margin: 0 auto;
            padding: 2rem 1.5rem;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 2rem;
            gap: 1rem;
        }

        .page-header h1 {
            font-size: 2rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin: 0;
            color: #334155;
        }
        
        .page-header .subtitle {
            font-size: 1rem;
            color: var(--text-light);
            margin-top: 0.25rem;
        }

        .btn-add-user {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: linear-gradient(145deg, var(--primary-color), var(--primary-hover));
            color: white;
            padding: 0.75rem 1.25rem;
            border-radius: 0.5rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease-in-out;
            box-shadow: 0 4px 10px rgba(13, 110, 253, 0.3);
            border: none;
            white-space: nowrap;
        }
        .btn-add-user:hover {
            transform: translateY(-3px);
            box-shadow: 0 7px 15px rgba(13, 110, 253, 0.4);
        }

        .table-wrapper {
            background-color: var(--card-bg);
            border-radius: 0.75rem;
            box-shadow: 0 4px 25px rgba(0, 0, 0, 0.07);
            border: 1px solid var(--border-color);
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        .table {
            margin-bottom: 0;
            border-collapse: collapse;
            width: 100%;
            min-width: 700px; 
        }

        .table thead th {
            background-color: #f8f9fa;
            color: var(--text-light);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.75rem;
            padding: 1rem 1.5rem;
            border-bottom: 2px solid var(--border-color);
        }

        .table tbody td {
            vertical-align: middle;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #f1f1f1;
        }
        
        .table tbody tr:last-child td {
            border-bottom: none;
        }

        .table .nama-lengkap {
            font-weight: 600;
            color: #343a40;
        }
        
        .table .username {
            color: var(--text-light);
            font-family: monospace;
            font-size: 0.9rem;
        }
        
        .table .badge-role {
            background-color: #198754e3;
            color: #ffffff;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.4em 0.8em;
            border-radius: 9999px;
            text-shadow: 0 1px 1px rgba(0,0,0,0.1);
        }
        
        .action-buttons {
            display: flex;
            gap: 1rem;
        }

        .action-buttons a {
            font-size: 1.1rem;
            text-decoration: none;
            transition: transform 0.2s ease, color 0.2s ease;
        }
        .action-buttons a.btn-edit:hover {
            transform: scale(1.2);
            color: #e6a800;
        }
        .action-buttons a.btn-delete:hover {
            transform: scale(1.2);
            color: #b02a37;
        }

        .btn-edit { color: var(--warning-color); }
        .btn-delete { color: var(--danger-color); }

        @media (max-width: 576px) {
            .user-management-container {
                padding: 1.5rem 1rem;
            }
            .page-header {
                flex-direction: column; 
                align-items: flex-start; 
            }
            .page-header .header-title {
                margin-bottom: 1rem;
            }
            .btn-add-user {
                width: 100%; 
                justify-content: center; 
            }
        }
    </style>
</head>

<body>
    <div class="user-management-container">
        <div class="page-header">
            <div class="header-title">
                <h1><i class="fas fa-users-cog"></i>Manajemen User</h1>
                <p class="subtitle">Daftar semua pengguna yang terdaftar di sistem.</p>
            </div>
            <a href="form-user.php" class="btn-add-user">
                <i class="fas fa-user-plus"></i>Tambah User Baru
            </a>
        </div>

        <?php if (isset($_SESSION['success_message']) && $_SESSION['success_message']) : ?>
            <div class="alert alert-success"><?= $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error_message']) && $_SESSION['error_message']) : ?>
            <div class="alert alert-danger"><?= $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
        <?php endif; ?>

        <div class="table-wrapper">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th scope="col">No</th>
                            <th scope="col">Nama Lengkap</th>
                            <th scope="col">Username</th>
                            <th scope="col">Jabatan (Role)</th>
                            <th scope="col">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)) : ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-5">
                                    <h4>Belum Ada Data User</h4>
                                    <p>Silakan klik tombol "Tambah User Baru" untuk memulai.</p>
                                </td>
                            </tr>
                        <?php else : ?>
                            <?php $nomor = 1; ?>
                            <?php foreach ($users as $user) : ?>
                                <tr>
                                    <td class="text-center"><strong><?= $nomor++ ?></strong></td>
                                    <td class="nama-lengkap"><?= htmlspecialchars($user['nama_lengkap']) ?></td>
                                    <td class="username"><?= htmlspecialchars($user['username']) ?></td>
                                    <td>
                                        <span class="badge-role">
                                            <?= htmlspecialchars(ucfirst($user['role'])) ?>
                                        </span>
                                    </td>
                                    <td class="action-buttons">
                                        <?php
                                        // Tombol edit muncul jika: baris ini BUKAN admin, ATAU yang login adalah admin.
                                        if (strtolower($user['role']) != 'admin' || (isset($_SESSION['role']) && strtolower($_SESSION['role']) == 'admin')) :
                                        ?>
                                            <a href="form-user.php?id=<?= $user['id'] ?>" class="btn-edit" title="Edit User">
                                                <i class="fas fa-pen-to-square"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if (strtolower($user['role']) != 'admin') : ?>
                                            <a href="delete-user.php?id=<?= $user['id'] ?>" class="btn-delete" title="Hapus User" onclick="return confirm('Yakin mau hapus user <?= htmlspecialchars($user['username']) ?>? Tindakan ini tidak bisa dibatalkan!');">
                                                <i class="fas fa-trash-alt"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>

</html>

<?php
require_once __DIR__ . '/../../footer.php';
?>