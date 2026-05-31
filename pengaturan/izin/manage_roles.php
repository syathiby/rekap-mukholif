<?php 
require_once __DIR__ . '/../../bootstrap/init.php';
guard('izin_manage');    
require_once __DIR__ . '/../../layouts/header.php'; 

// Ambil semua role dari database
$rolesResult = $conn->query("SELECT * FROM roles ORDER BY created_at ASC");
?>

<div class="dashboard-wrapper container-fluid px-0 px-md-2 mt-2 mb-5">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 px-1 gap-3">
        <div class="d-flex align-items-center">
            <div class="d-flex align-items-center justify-content-center rounded-circle me-3 shadow-sm flex-shrink-0" style="width: 56px; height: 56px; background: linear-gradient(135deg, #8b5cf6, #a855f7); color: white;">
                <i class="fas fa-tags fa-xl"></i>
            </div>
            <div>
                <h3 class="fw-bold mb-1 text-dark" style="letter-spacing: -0.5px; font-size: 1.5rem;">Manajemen Role Dinamis</h3>
                <p class="text-muted mb-0" style="font-size: 0.95rem;">Kelola daftar jabatan (role) yang dapat dipilih saat membuat akun baru.</p>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="role.php" class="btn btn-outline-secondary shadow-sm rounded-pill px-4" style="font-weight: 600;">
                <i class="fas fa-arrow-left me-2"></i>Kembali ke Izin Default
            </a>
            <button type="button" class="btn btn-primary shadow-sm rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addRoleModal" style="background: linear-gradient(135deg, #8b5cf6, #7c3aed); border: none; font-weight: 600;">
                <i class="fas fa-plus me-2"></i>Tambah Role
            </button>
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

    <div class="card shadow-sm border-0 rounded-4 mb-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="px-4 py-3" style="width: 80px;">No</th>
                            <th class="py-3">Nama Role (Jabatan)</th>
                            <th class="py-3">ID Sistem</th>
                            <th class="py-3 px-4 text-center" style="width: 150px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = 1;
                        // Default roles tidak boleh dihapus/diedit ID-nya sembarangan
                        $protectedRoles = ['admin', 'pelihat', 'staff', 'pengelola'];
                        while ($row = $rolesResult->fetch_assoc()): 
                            $isProtected = in_array($row['id'], $protectedRoles);
                        ?>
                            <tr>
                                <td class="px-4 text-muted"><?= $no++ ?></td>
                                <td class="fw-bold text-dark">
                                    <?= htmlspecialchars($row['role_name']) ?>
                                    <?php if($isProtected): ?>
                                        <span class="badge bg-secondary ms-2" style="font-size:0.7rem;">Sistem Default</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-light text-dark border font-monospace"><?= htmlspecialchars($row['id']) ?></span></td>
                                <td class="px-4 text-center">
                                    <button class="btn btn-sm btn-outline-primary rounded-circle shadow-sm" onclick="editRole('<?= addslashes($row['id']) ?>', '<?= addslashes($row['role_name']) ?>')" title="Edit Nama Role">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    
                                    <?php if(!$isProtected): ?>
                                        <form action="process_manage_roles.php" method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= htmlspecialchars($row['id']) ?>">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <button type="button" class="btn btn-sm btn-outline-danger rounded-circle shadow-sm ms-1" onclick="confirmSubmit(event, this, 'Hapus Role?', 'User yang memiliki role ini mungkin akan kehilangan hak aksesnya. Lanjutkan?')" title="Hapus Role">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        <?php if($rolesResult->num_rows == 0): ?>
                            <tr><td colspan="4" class="text-center text-muted py-4">Belum ada data role.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Tambah Role -->
<div class="modal fade" id="addRoleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header border-bottom-0 pt-4 pb-0 px-4">
                <h5 class="modal-title fw-bold text-dark"><i class="fas fa-plus-circle me-2" style="color: #8b5cf6;"></i>Tambah Role Baru</h5>
                <button type="button" class="btn-close shadow-sm" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="process_manage_roles.php" method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-secondary small">Nama Jabatan / Role</label>
                        <input type="text" name="role_name" class="form-control form-control-lg bg-light" required placeholder="Contoh: Kepala Asrama">
                        <div class="form-text mt-2"><i class="fas fa-info-circle me-1"></i>Sistem akan membuat ID otomatis secara <strong>backend</strong> (contoh: <code>kepala_asrama</code>).</div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pb-4 px-4">
                    <button type="button" class="btn btn-light rounded-pill px-4 fw-bold" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" style="background: linear-gradient(135deg, #8b5cf6, #7c3aed); border: none;">Simpan Role</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit Role -->
<div class="modal fade" id="editRoleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header border-bottom-0 pt-4 pb-0 px-4">
                <h5 class="modal-title fw-bold text-dark"><i class="fas fa-edit me-2" style="color: #8b5cf6;"></i>Edit Role</h5>
                <button type="button" class="btn-close shadow-sm" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="process_manage_roles.php" method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold text-secondary small">ID Sistem (Terkunci)</label>
                        <input type="text" name="id" id="edit_role_id" class="form-control form-control-lg bg-light" readonly style="cursor: not-allowed;">
                        <div class="form-text mt-1 text-danger small"><i class="fas fa-lock me-1"></i>ID tidak dapat diubah untuk menjaga relasi database.</div>
                    </div>

                    <div class="mb-3 mt-4">
                        <label class="form-label fw-bold text-secondary small">Nama Jabatan / Role</label>
                        <input type="text" name="role_name" id="edit_role_name" class="form-control form-control-lg" required>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pb-4 px-4">
                    <button type="button" class="btn btn-light rounded-pill px-4 fw-bold" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" style="background: linear-gradient(135deg, #8b5cf6, #7c3aed); border: none;">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editRole(id, name) {
    document.getElementById('edit_role_id').value = id;
    document.getElementById('edit_role_name').value = name;
    var editModal = new bootstrap.Modal(document.getElementById('editRoleModal'));
    editModal.show();
}
</script>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
