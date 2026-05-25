<?php 
// 1. Panggil 'Otak' aplikasi dulu
require_once __DIR__ . '/../../bootstrap/init.php';

// 2. Jalankan 'SATPAM' buat ngejaga halaman
guard('izin_manage');    

// 3. Kalau lolos, baru panggil Tampilan
require_once __DIR__ . '/../../layouts/header.php'; 
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
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    <style>
        .form-check-input:checked {
            background-color: #f97316;
            border-color: #f97316;
        }
    </style>
</head>
<body>
    <div class="dashboard-wrapper container-fluid px-0 px-md-2 mt-2 mb-5">
        
        <!-- Header Page yang Dilepas dari Card Utama -->
        <div class="d-flex align-items-center mb-4 px-1">
            <div class="d-flex align-items-center justify-content-center rounded-circle me-3 shadow-sm flex-shrink-0" style="width: 56px; height: 56px; background: linear-gradient(135deg, #f97316, #fb923c); color: white;">
                <i class="fas fa-ticket-alt fa-xl"></i>
            </div>
            <div>
                <h3 class="fw-bold mb-1 text-dark" style="letter-spacing: -0.5px; font-size: 1.5rem;">Loket Pengaturan Izin</h3>
                <p class="text-muted mb-0" style="font-size: 0.95rem;">Atur "tiket" atau hak akses setiap pengguna secara fleksibel dan mendetail.</p>
            </div>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success d-flex align-items-center shadow-sm rounded-3 mb-4">
                <i class="fas fa-check-circle fa-lg me-3"></i>
                <div><?= $_SESSION['success_message'] ?></div>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger d-flex align-items-center shadow-sm rounded-3 mb-4">
                <i class="fas fa-exclamation-triangle fa-lg me-3"></i>
                <div><?= $_SESSION['error_message'] ?></div>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <!-- Form Pilih User -->
        <div class="card shadow-sm border-0 rounded-4 mb-4" style="background: linear-gradient(180deg, #ffffff, #f8fafc);">
            <div class="card-body p-4">
                <form method="GET">
                    <label class="form-label fw-bold text-dark mb-3"><i class="fas fa-user-shield me-2 text-warning"></i>Pilih Akun Pengguna</label>
                    <div class="shadow-sm rounded-3">
                        <select class="form-select form-select-lg" name="user_id" id="user_id" onchange="this.form.submit()" style="cursor:pointer;">
                            <option value="">-- Pilih username pengguna --</option>
                            <?php mysqli_data_seek($usersResult, 0); while($user = $usersResult->fetch_assoc()): ?>
                                <option value="<?= $user['id'] ?>" <?= ($selectedUserId == $user['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($user['nama_lengkap']) ?> (@<?= htmlspecialchars($user['username']) ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($selectedUserId && !empty($permissions)): ?>
            <form action="process.php" method="POST">
                <input type="hidden" name="user_id" value="<?= $selectedUserId ?>">
                
                <div class="d-flex align-items-center mb-4 mt-5 px-2">
                    <div class="d-flex align-items-center justify-content-center rounded-circle me-3 shadow-sm flex-shrink-0" style="width: 44px; height: 44px; background: rgba(249, 115, 22, 0.1); color: #f97316;">
                        <i class="fas fa-user-lock"></i>
                    </div>
                    <h5 class="fw-bold mb-0" style="line-height: 1.4;">Izin Akses untuk: <span class="text-dark border-bottom border-warning border-2 pb-1 d-inline-block"><?= htmlspecialchars($selectedUserName) ?></span></h5>
                </div>

                <div class="row g-4 px-1">
                    <?php foreach ($permissions as $grup => $items): ?>
                        <div class="col-xl-4 col-lg-6">
                            <div class="card shadow-sm border-0 rounded-4 h-100" style="position: relative; overflow: hidden;">
                                <!-- Garis Gradasi di atas kartu -->
                                <div style="position:absolute; top:0; left:0; width:100%; height:4px; background: linear-gradient(90deg, #f97316, #fb923c);"></div>
                                <div class="card-header bg-white border-bottom-0 pt-4 pb-2">
                                    <h5 class="fw-bold text-dark mb-0"><i class="fas fa-folder-open text-warning me-2 opacity-75"></i><?= htmlspecialchars($grup) ?></h5>
                                </div>
                                <div class="card-body pt-2">
                                    <div class="d-flex flex-column gap-2">
                                        <?php foreach ($items as $perm): ?>
                                            <div class="d-flex align-items-start p-2 rounded-3" style="transition: all 0.2s;" onmouseover="this.style.backgroundColor='#f8fafc'" onmouseout="this.style.backgroundColor='transparent'">
                                                <div class="form-check form-switch me-3 mt-1">
                                                    <input class="form-check-input shadow-sm" type="checkbox" role="switch" name="permissions[]" value="<?= $perm['id'] ?>" id="perm_<?= $perm['id'] ?>" <?= in_array($perm['id'], $userPermissions) ? 'checked' : '' ?> style="width: 2.5rem; height: 1.25rem; cursor: pointer;">
                                                </div>
                                                <label class="form-check-label mb-0" for="perm_<?= $perm['id'] ?>" style="cursor: pointer; width: 100%;">
                                                    <div class="fw-bold text-dark" style="font-size: 0.95rem; line-height: 1.2;"><?= htmlspecialchars($perm['deskripsi']) ?></div>
                                                    <div class="text-muted small mt-1" style="font-size: 0.75rem; font-family: monospace; background:#f1f5f9; padding: 2px 6px; border-radius: 4px; display: inline-block;">Code: <?= htmlspecialchars($perm['nama_izin']) ?></div>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Sticky Floating Save Button -->
                <div class="position-sticky bottom-0 start-0 w-100 py-3 mt-5" style="background: linear-gradient(0deg, rgba(255,255,255,0.95) 40%, rgba(255,255,255,0) 100%); z-index: 100; backdrop-filter: blur(2px);">
                    <div class="d-flex justify-content-center px-1">
                        <button type="submit" class="btn btn-primary btn-lg rounded-pill px-5 shadow-sm fw-bold" style="background: linear-gradient(90deg, #f97316, #ea580c); border: none; transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
                            <i class="fas fa-save me-2"></i>Simpan Perubahan Izin
                        </button>
                    </div>
                </div>
            </form>
        <?php elseif ($selectedUserId): ?>
            <div class="card shadow-sm border-0 rounded-4 p-5 text-center my-5">
                <i class="fas fa-box-open fa-4x text-muted mb-3 opacity-25"></i>
                <h4 class="fw-bold text-dark">Tiket Kosong</h4>
                <p class="text-muted">Sistem belum memiliki referensi tiket izin yang bisa diatur.</p>
            </div>
        <?php else: ?>
            <div class="card shadow-sm border-0 rounded-4 p-5 text-center my-5" style="background: linear-gradient(180deg, #ffffff, #f8fafc);">
                <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-4 bg-white shadow-sm mx-auto" style="width: 90px; height: 90px;">
                    <i class="fas fa-hand-pointer fa-3x text-warning opacity-75"></i>
                </div>
                <h4 class="fw-bold text-dark mb-2">Pilih Pengguna Dahulu</h4>
                <p class="text-muted" style="max-width: 500px; margin: 0 auto;">Pilih seorang pengguna dari kotak pencarian di atas untuk mulai mengatur "tiket" perizinan akses mereka ke berbagai menu di sistem ini.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#user_id').select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: "-- Ketik nama atau username pengguna --"
            });
        });
    </script>
</body>
</html>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>