<?php
require_once __DIR__ . '/../../bootstrap/init.php';

// Cek user yang login
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/login.php");
    exit;
}

// User dengan role pelihat tidak boleh mengubah profilnya
if (isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'pelihat') {
    http_response_code(403);
    require __DIR__ . '/../../bootstrap/access_denied.php';
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// Ambil data user saat ini
$stmt = $conn->prepare("SELECT nama_lengkap, username FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    echo "User tidak ditemukan.";
    exit;
}

$csrf_token = csrf_generate();

// Cek sisa limit ganti profil
$has_user_manage = false;
if (function_exists('has_permission')) {
    $has_user_manage = has_permission('user_manage');
}

$limit_max = 3;
$limit_used = 0;
$limit_remaining = $limit_max;

if (!$has_user_manage) {
    $current_month = date('Y-m');
    $stmt_limit = $conn->prepare("SELECT COUNT(*) as count FROM log_aktifitas WHERE user_id = ? AND fitur = 'profil' AND aksi = 'UPDATE' AND DATE_FORMAT(dibuat_pada, '%Y-%m') = ?");
    $stmt_limit->bind_param("is", $user_id, $current_month);
    $stmt_limit->execute();
    $res_limit = $stmt_limit->get_result();
    $limit_data = $res_limit->fetch_assoc();
    $stmt_limit->close();
    
    $limit_used = (int)$limit_data['count'];
    $limit_remaining = max(0, $limit_max - $limit_used);
}

require_once __DIR__ . '/../../layouts/header.php';
?>

<div class="container-fluid px-0 px-md-2 mt-2 mb-5">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 px-1 gap-3">
        <div class="d-flex align-items-center">
            <div class="d-flex align-items-center justify-content-center rounded-circle me-3 shadow-sm flex-shrink-0" style="width: 56px; height: 56px; background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white;">
                <i class="fas fa-user-edit fa-xl"></i>
            </div>
            <div>
                <h3 class="fw-bold mb-1 text-dark" style="letter-spacing: -0.5px; font-size: 1.5rem;">Pengaturan Profil</h3>
                <p class="text-muted mb-0" style="font-size: 0.95rem;">Ubah informasi login dan kata sandi akun Anda.</p>
            </div>
        </div>
    </div>

    <div class="row justify-content-center px-1">
        <div class="col-lg-8">
            <?php if (!$has_user_manage): ?>
            <div class="alert <?= $limit_remaining > 0 ? 'alert-info' : 'alert-danger' ?> d-flex align-items-center border-0 shadow-sm rounded-4 mb-4" role="alert" style="background: <?= $limit_remaining > 0 ? 'linear-gradient(to right, #eff6ff, #dbeafe)' : 'linear-gradient(to right, #fef2f2, #fee2e2)' ?>;">
                <i class="fas <?= $limit_remaining > 0 ? 'fa-info-circle text-primary' : 'fa-exclamation-triangle text-danger' ?> fa-2x me-3"></i>
                <div>
                    <h6 class="alert-heading fw-bold mb-1 <?= $limit_remaining > 0 ? 'text-primary' : 'text-danger' ?>">Informasi Limit Profil</h6>
                    <p class="mb-0 <?= $limit_remaining > 0 ? 'text-dark' : 'text-danger' ?>" style="font-size: 0.95rem;">
                        Anda memiliki batas maksimal perubahan profil sebanyak <strong><?= $limit_max ?> kali</strong> dalam 1 bulan.<br>
                        Sisa limit Anda bulan ini: <span class="badge <?= $limit_remaining > 0 ? 'bg-success' : 'bg-danger' ?> rounded-pill ms-1 px-2 py-1"><?= $limit_remaining ?> kali</span>
                    </p>
                </div>
            </div>
            <?php endif; ?>

            <div class="card shadow-sm border-0 rounded-4" style="background: linear-gradient(180deg, #ffffff, #f8fafc); overflow: hidden;">
                <div style="height: 4px; background: linear-gradient(90deg, #6366f1, #8b5cf6); width: 100%;"></div>
                <div class="card-body p-4 p-md-5">
                    
                    <form action="process_profil.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold text-dark"><i class="fas fa-id-card me-2 text-muted"></i>Nama Lengkap</label>
                            <input type="text" class="form-control form-control-lg bg-light" value="<?= htmlspecialchars($user['nama_lengkap']) ?>" readonly style="cursor: not-allowed; border-color: #e2e8f0; color: #64748b;">
                            <div class="form-text mt-2"><i class="fas fa-info-circle me-1"></i>Nama lengkap tidak dapat diubah oleh pengguna secara mandiri demi ketertiban data. Hubungi Administrator untuk mengganti nama asli Anda.</div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold text-dark"><i class="fas fa-user me-2 text-primary"></i>Username</label>
                            <div class="input-group input-group-lg shadow-sm rounded-3">
                                <span class="input-group-text bg-white border-end-0 text-muted">@</span>
                                <input type="text" name="username" class="form-control border-start-0 ps-0" value="<?= htmlspecialchars($user['username']) ?>" required autocomplete="off">
                            </div>
                        </div>

                        <hr class="my-4 border-light">

                        <h5 class="fw-bold mb-3 text-dark"><i class="fas fa-lock me-2 text-warning"></i>Keamanan Akun & Konfirmasi</h5>
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold text-dark">Password Lama <span class="text-danger">*</span></label>
                            <input type="password" name="old_password" class="form-control form-control-lg shadow-sm" required placeholder="Masukkan password saat ini untuk konfirmasi" autocomplete="off">
                            <div class="form-text mt-2"><i class="fas fa-shield-alt me-1"></i>Password lama wajib diisi setiap kali menyimpan pembaruan profil maupun mengganti sandi baru.</div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold text-dark">Password Baru</label>
                            <input type="password" name="new_password" class="form-control form-control-lg shadow-sm" placeholder="Biarkan kosong jika tidak ingin mengubah password" autocomplete="off">
                            <div class="form-text mt-2">Isi hanya jika Anda ingin mengganti password login Anda.</div>
                        </div>

                        <div class="mt-5 d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary btn-lg rounded-pill px-5 shadow-sm fw-bold" <?= (!$has_user_manage && $limit_remaining <= 0) ? 'disabled' : '' ?> style="background: linear-gradient(135deg, #6366f1, #4f46e5); border: none; transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
                                <i class="fas fa-save me-2"></i>Simpan Perubahan
                            </button>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
