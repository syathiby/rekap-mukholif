<?php
// 1. Panggil 'Otak' aplikasi dulu
require_once __DIR__ . '/../../bootstrap/init.php';

// 2. Jalankan 'SATPAM' buat ngejaga halaman
guard('user_manage'); 

// 3. Kalau lolos, baru panggil Tampilan
require_once __DIR__ . '/../../layouts/header.php';

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

<style>
    :root {
        --bg-card: #ffffff;
        --border: #e2e8f0;
        --text-main: #0f172a;
        --text-muted: #64748b;
    }
    
    .table-container {
        background-color: var(--bg-card);
        padding: 0;
        border-radius: 1rem;
        margin-bottom: 2rem;
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05), 0 1px 2px -1px rgba(0, 0, 0, 0.05);
        border: 1px solid var(--border);
        overflow: hidden;
    }
    
    .table thead {
        background-color: #f8fafc;
        color: var(--text-muted);
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.05em;
    }
    
    .table th {
        border-bottom-width: 1px;
        padding: 1rem 1.5rem !important;
    }
    
    .table td {
        padding: 1rem 1.5rem !important;
        vertical-align: middle;
    }
    
    .table tbody tr {
        transition: background-color 0.2s ease;
    }
    
    .table tbody tr:hover {
        background-color: #f8fafc;
    }
    
    .badge-role-admin {
        background-color: rgba(16, 185, 129, 0.1);
        color: #059669;
        font-size: 0.75rem;
        font-weight: 600;
        padding: 0.35rem 0.75rem;
        border-radius: 9999px;
        display: inline-block;
    }
    
    .badge-role-user {
        background-color: rgba(79, 70, 229, 0.1);
        color: #4f46e5;
        font-size: 0.75rem;
        font-weight: 600;
        padding: 0.35rem 0.75rem;
        border-radius: 9999px;
        display: inline-block;
    }

    .btn-action-edit {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background-color: rgba(245, 158, 11, 0.08);
        color: #d97706;
        transition: all 0.2s ease;
    }
    
    .btn-action-edit:hover {
        background-color: #f59e0b;
        color: white;
        transform: translateY(-2px);
    }
    
    .btn-action-delete {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background-color: rgba(239, 68, 68, 0.08);
        color: #dc2626;
        transition: all 0.2s ease;
    }
    
    .btn-action-delete:hover {
        background-color: #ef4444;
        color: white;
        transform: translateY(-2px);
    }
</style>

<div class="container-fluid py-4 px-4">
    <!-- Header Page -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="fw-bold text-dark mb-1"><i class="fas fa-users-cog text-primary me-2"></i>Manajemen User</h3>
            <p class="text-muted mb-0">Daftar semua pengguna yang terdaftar di sistem.</p>
        </div>
        <div class="d-flex align-items-center">
            <a href="form-user.php" class="btn btn-primary shadow-sm rounded-pill px-4"><i class="fas fa-user-plus me-2"></i> Tambah User Baru</a>
        </div>
    </div>

    <!-- Alert / Notification -->
    <?php if (isset($_SESSION['success_message']) && $_SESSION['success_message']) : ?>
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <i class="fas fa-check-circle me-2"></i> <?= $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_message']) && $_SESSION['error_message']) : ?>
        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
            <i class="fas fa-times-circle me-2"></i> <?= $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Table -->
    <div class="table-container">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th width="8%" class="text-center">No</th>
                        <th>Nama Lengkap</th>
                        <th>Username</th>
                        <th width="20%">Jabatan (Role)</th>
                        <th width="15%" class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)) : ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-5">
                                <i class="fas fa-users fa-3x mb-3 text-secondary" style="opacity: 0.4;"></i>
                                <h5 class="fw-semibold">Belum Ada Data User</h5>
                                <p class="text-muted small">Silakan klik tombol "Tambah User Baru" untuk memulai.</p>
                            </td>
                        </tr>
                    <?php else : ?>
                        <?php $nomor = 1; ?>
                        <?php foreach ($users as $user) : ?>
                            <tr>
                                <td class="text-center fw-semibold"><?= $nomor++ ?></td>
                                <td class="fw-semibold text-dark"><?= htmlspecialchars($user['nama_lengkap']) ?></td>
                                <td class="text-muted font-monospace" style="font-size: 0.9rem;"><?= htmlspecialchars($user['username']) ?></td>
                                <td>
                                    <?php if (strtolower($user['role']) == 'admin') : ?>
                                        <span class="badge-role-admin">Admin</span>
                                    <?php else: ?>
                                        <span class="badge-role-user"><?= htmlspecialchars(ucfirst($user['role'])) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="d-inline-flex gap-2">
                                        <?php if (strtolower($user['role']) != 'admin' || (isset($_SESSION['role']) && strtolower($_SESSION['role']) == 'admin')) : ?>
                                            <a href="form-user.php?id=<?= $user['id'] ?>" class="btn-action-edit" title="Edit User">
                                                <i class="fas fa-pen-to-square"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if (strtolower($user['role']) != 'admin') : ?>
                                            <a href="delete-user.php?id=<?= $user['id'] ?>" class="btn-action-delete" title="Hapus User" onclick="confirmSubmit(event, this, 'Hapus User', 'Yakin mau hapus user <?= htmlspecialchars($user['username']) ?>? Tindakan ini tidak bisa dibatalkan!');">
                                                <i class="fas fa-trash-alt"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../../layouts/footer.php';
?>