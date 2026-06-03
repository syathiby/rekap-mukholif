<?php 
// 1. Panggil 'Otak' aplikasi dulu
require_once __DIR__ . '/../../bootstrap/init.php';

// 2. Jalankan 'SATPAM' buat ngejaga halaman
guard('izin_manage');

// Generate CSRF token sebelum form ditampilkan
$csrf_token = csrf_generate();

// 3. Kalau lolos, baru panggil Tampilan
require_once __DIR__ . '/../../layouts/header.php'; 
?>

<?php

// Ambil role yang mau di-edit dari URL (jika ada)
$selectedRole = isset($_GET['role']) ? strtolower($_GET['role']) : null;

// Jika user nekat mengakses role admin (atau pengelola bagi non-admin) lewat URL, lempar ke halaman terlarang.
if ($selectedRole === 'admin') {
    http_response_code(403);
    require __DIR__ . '/../../bootstrap/access_denied.php';
    exit;
} elseif ($selectedRole === 'pengelola') {
    if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'admin') {
        http_response_code(403);
        require __DIR__ . '/../../bootstrap/access_denied.php';
        exit;
    }
}

// Daftar role yang bisa diatur izin defaultnya
$availableRoles = [];
$is_admin = (isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'admin');
$role_condition = $is_admin ? "id != 'admin'" : "id NOT IN ('admin', 'pengelola')";
$resRoles = $conn->query("SELECT id, role_name FROM roles WHERE $role_condition ORDER BY created_at ASC");
while($r = $resRoles->fetch_assoc()) {
    $availableRoles[$r['id']] = $r['role_name'];
}

$permissions = [];
$rolePermissions = [];
$selectedRoleName = '';

if ($selectedRole && array_key_exists($selectedRole, $availableRoles)) {
    $selectedRoleName = $availableRoles[$selectedRole];

    // Ambil semua jenis tiket dari "Buku Menu", dikelompokkan
    $permResult = $conn->query("SELECT id, nama_izin, deskripsi, grup FROM permissions ORDER BY grup, nama_izin ASC");
    while($row = $permResult->fetch_assoc()) {
        $permissions[$row['grup']][] = $row;
    }

    // Ambil semua tiket yang sudah dimiliki role terpilih
    $stmt = $conn->prepare("SELECT permission_id FROM role_permissions WHERE role = ?");
    $stmt->bind_param("s", $selectedRole);
    $stmt->execute();
    $rolePermResult = $stmt->get_result();
    while($row = $rolePermResult->fetch_assoc()) {
        $rolePermissions[] = $row['permission_id'];
    }
    $stmt->close();
}
?>

<style>
    .form-check-input:checked {
        background-color: #8b5cf6; /* Warna ungu beda dari user biasa */
        border-color: #8b5cf6;
    }
</style>
    <div class="dashboard-wrapper container-fluid px-0 px-md-2 mt-2 mb-5">
        
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 px-1 gap-3">
            <div class="d-flex align-items-center">
                <div class="d-flex align-items-center justify-content-center rounded-circle me-3 shadow-sm flex-shrink-0" style="width: 56px; height: 56px; background: linear-gradient(135deg, #8b5cf6, #a855f7); color: white;">
                    <i class="fas fa-layer-group fa-xl"></i>
                </div>
                <div>
                    <h3 class="fw-bold mb-1 text-dark" style="letter-spacing: -0.5px; font-size: 1.5rem;">Pengaturan Izin Default Role</h3>
                    <p class="text-muted mb-0" style="font-size: 0.95rem;">Atur izin otomatis yang akan diberikan saat membuat akun baru berdasarkan rolenya.</p>
                </div>
            </div>
            <div class="d-flex gap-2">
                <a href="index.php" class="btn btn-outline-secondary shadow-sm rounded-pill px-4" style="font-weight: 600;">
                    <i class="fas fa-arrow-left me-2"></i>Kembali ke Izin per User
                </a>
                <a href="manage_roles.php" class="btn btn-primary shadow-sm rounded-pill px-4" style="background: linear-gradient(135deg, #8b5cf6, #7c3aed); border: none; font-weight: 600;">
                    <i class="fas fa-tags me-2"></i>Kelola Role
                </a>
            </div>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    showToast('<?= addslashes($_SESSION['success_message']) ?>', 'success');
                });
            </script>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['error_message'])): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    showAlert('<?= addslashes($_SESSION['error_message']) ?>', 'error');
                });
            </script>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <!-- Form Pilih Role -->
        <div class="card shadow-sm border-0 rounded-4 mb-4" style="background: linear-gradient(180deg, #ffffff, #f8fafc);">
            <div class="card-body p-4">
                <form method="GET">
                    <label class="form-label fw-bold text-dark mb-3"><i class="fas fa-users-cog me-2" style="color:#8b5cf6"></i>Pilih Role (Jabatan)</label>
                    <div class="shadow-sm rounded-3">
                        <select class="form-select form-select-lg" name="role" onchange="this.form.submit()" style="cursor:pointer;">
                            <option value="">-- Pilih role/jabatan --</option>
                            <?php foreach($availableRoles as $roleKey => $roleLabel): ?>
                                <option value="<?= $roleKey ?>" <?= ($selectedRole == $roleKey) ? 'selected' : '' ?>>
                                    Role: <?= htmlspecialchars($roleLabel) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($selectedRole && !empty($permissions)): ?>
            <form action="process_role.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="role" value="<?= htmlspecialchars($selectedRole) ?>">
                
                <div class="d-flex align-items-center mb-4 mt-5 px-2">
                    <div class="d-flex align-items-center justify-content-center rounded-circle me-3 shadow-sm flex-shrink-0" style="width: 44px; height: 44px; background: rgba(139, 92, 246, 0.1); color: #8b5cf6;">
                        <i class="fas fa-user-lock"></i>
                    </div>
                    <h5 class="fw-bold mb-0" style="line-height: 1.4;">Izin Default untuk Role: <span class="text-dark border-bottom border-2 pb-1 d-inline-block" style="border-color:#8b5cf6 !important;"><?= htmlspecialchars($selectedRoleName) ?></span></h5>
                </div>

                <div class="alert alert-info shadow-sm rounded-3 mb-4 d-flex align-items-center">
                    <i class="fas fa-info-circle fa-lg me-3 text-info"></i>
                    <div>
                        Perubahan di halaman ini <strong>tidak akan mengubah izin user yang sudah ada</strong>. Izin ini hanya menjadi <strong>cetakan default</strong> ketika Anda membuat akun baru dengan role ini, atau saat Anda secara spesifik mengubah role user yang ada menjadi role ini.
                    </div>
                </div>

                <div class="row g-4 px-1">
                    <?php foreach ($permissions as $grup => $items): ?>
                        <div class="col-xl-4 col-lg-6">
                            <div class="card shadow-sm border-0 rounded-4 h-100" style="position: relative; overflow: hidden;">
                                <div style="position:absolute; top:0; left:0; width:100%; height:4px; background: linear-gradient(90deg, #8b5cf6, #c084fc);"></div>
                                <div class="card-header bg-white border-bottom-0 pt-4 pb-2">
                                    <h5 class="fw-bold text-dark mb-0"><i class="fas fa-folder-open me-2 opacity-75" style="color:#8b5cf6"></i><?= htmlspecialchars($grup) ?></h5>
                                </div>
                                <div class="card-body pt-2">
                                    <div class="d-flex flex-column gap-2">
                                        <?php foreach ($items as $perm): ?>
                                            <div class="d-flex align-items-start p-2 rounded-3" style="transition: all 0.2s;" onmouseover="this.style.backgroundColor='#f8fafc'" onmouseout="this.style.backgroundColor='transparent'">
                                                <div class="form-check form-switch me-3 mt-1">
                                                    <input class="form-check-input shadow-sm" type="checkbox" role="switch" name="permissions[]" value="<?= $perm['id'] ?>" id="perm_<?= $perm['id'] ?>" <?= in_array($perm['id'], $rolePermissions) ? 'checked' : '' ?> style="width: 2.5rem; height: 1.25rem; cursor: pointer;">
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
                <div class="position-sticky bottom-0 py-3 mt-5" style="background: linear-gradient(0deg, rgba(255,255,255,0.95) 40%, rgba(255,255,255,0) 100%); z-index: 100; backdrop-filter: blur(2px);">
                    <div class="d-flex justify-content-center">
                        <button type="submit" class="btn btn-primary btn-lg rounded-pill px-5 shadow-sm fw-bold" style="background: linear-gradient(90deg, #8b5cf6, #7c3aed); border: none; transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
                            <i class="fas fa-save me-2"></i>Simpan Perubahan Izin Role
                        </button>
                    </div>
                </div>
            </form>
        <?php else: ?>
            <?php if (!$selectedRole): ?>
                <div class="card shadow-sm border-0 rounded-4 p-5 text-center my-5" style="background: linear-gradient(180deg, #ffffff, #f8fafc);">
                    <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-4 bg-white shadow-sm mx-auto" style="width: 90px; height: 90px;">
                        <i class="fas fa-hand-pointer fa-3x opacity-75" style="color:#8b5cf6"></i>
                    </div>
                    <h4 class="fw-bold text-dark mb-2">Pilih Role Dahulu</h4>
                    <p class="text-muted" style="max-width: 500px; margin: 0 auto;">Pilih sebuah role dari dropdown di atas untuk mulai mengatur kumpulan izin standarnya.</p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
