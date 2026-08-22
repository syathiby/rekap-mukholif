<?php 
require_once __DIR__ . '/../../bootstrap/init.php';
guard('izin_manage');

// Generate CSRF token sebelum form ditampilkan
$csrf_token = csrf_generate();

require_once __DIR__ . '/../../layouts/header.php'; 

// Ambil semua role dari database
$rolesResult = $conn->query("SELECT * FROM roles ORDER BY created_at ASC");
?>

<style>
    /* Scoped Style untuk Manajemen Role */
    .izin-header-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.45rem;
        font-weight: 600;
        font-size: 0.84rem;
        padding: 0.55rem 1rem;
        border-radius: 10px;
        white-space: nowrap;
        text-decoration: none;
        transition: all 0.2s ease;
        line-height: 1.25;
    }
    .izin-btn-back {
        background-color: #f1f5f9;
        color: #475569;
        border: 1px solid #e2e8f0;
    }
    .izin-btn-back:hover {
        background-color: #e2e8f0;
        color: #1e293b;
        transform: translateY(-1px);
    }
    .izin-btn-add {
        background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
        color: #ffffff;
        border: none;
        box-shadow: 0 2px 8px rgba(139, 92, 246, 0.25);
    }
    .izin-btn-add:hover {
        background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%);
        color: #ffffff;
        box-shadow: 0 4px 12px rgba(139, 92, 246, 0.35);
        transform: translateY(-1px);
    }

    .btn-action-round {
        width: 32px;
        height: 32px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        transition: all 0.15s ease;
    }

    @media (max-width: 768px) {
        .manage-roles-actions {
            width: 100%;
            display: flex;
            gap: 8px;
        }
        .manage-roles-actions .izin-header-btn {
            flex: 1;
            padding: 0.5rem 0.5rem;
            font-size: 0.76rem;
            border-radius: 8px;
        }
    }
</style>

<div class="dashboard-wrapper container-fluid px-0 px-md-2 mt-2 mb-5">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 px-1 gap-3">
        <div class="d-flex align-items-center">
            <div class="d-flex align-items-center justify-content-center rounded-3 me-3 shadow-sm flex-shrink-0" style="width: 48px; height: 48px; background: linear-gradient(135deg, #8b5cf6, #a855f7); color: white;">
                <i class="fas fa-tags fa-lg"></i>
            </div>
            <div>
                <h3 class="fw-bold mb-0 text-dark" style="letter-spacing: -0.3px; font-size: 1.35rem;">Manajemen Role Dinamis</h3>
                <p class="text-muted mb-0" style="font-size: 0.85rem;">Kelola daftar jabatan (role) yang dapat dipilih saat membuat akun baru.</p>
            </div>
        </div>
        <div class="manage-roles-actions d-flex gap-2">
            <a href="role.php" class="izin-header-btn izin-btn-back">
                <i class="fas fa-arrow-left"></i>
                <span>Izin Default Role</span>
            </a>
            <button type="button" class="izin-header-btn izin-btn-add" data-bs-toggle="modal" data-bs-target="#addRoleModal">
                <i class="fas fa-plus"></i>
                <span>Tambah Role</span>
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
                showToast('<?= addslashes($_SESSION['error_message']) ?>', 'error');
            });
        </script>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <div class="card shadow-sm border-0 rounded-4 mb-4 overflow-hidden">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="px-4 py-3 text-secondary" style="width: 80px; font-size: 0.82rem; font-weight: 600;">No</th>
                            <th class="py-3 text-secondary" style="font-size: 0.82rem; font-weight: 600;">Nama Role (Jabatan)</th>
                            <th class="py-3 text-secondary" style="font-size: 0.82rem; font-weight: 600;">ID Sistem</th>
                            <th class="py-3 px-4 text-center text-secondary" style="width: 140px; font-size: 0.82rem; font-weight: 600;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = 1;
                        // Default roles tidak boleh dihapus/diedit ID-nya sembarangan
                        $protectedRoles = ['admin', 'pelihat', 'pengelola'];
                        while ($row = $rolesResult->fetch_assoc()): 
                            $isProtected = in_array($row['id'], $protectedRoles);
                        ?>
                            <tr>
                                <td class="px-4 text-muted"><?= $no++ ?></td>
                                <td class="fw-bold text-dark">
                                    <?= htmlspecialchars($row['role_name']) ?>
                                    <?php if($isProtected): ?>
                                        <span class="badge bg-secondary-subtle text-secondary border ms-2" style="font-size:0.68rem; font-weight: 500;">Sistem Default</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-light text-dark border font-monospace px-2 py-1" style="font-size: 0.76rem;"><?= htmlspecialchars($row['id']) ?></span></td>
                                <td class="px-4 text-center">
                                    <div class="d-inline-flex gap-1.5 align-items-center justify-content-center">
                                        <?php 
                                        $is_admin = (isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'admin');
                                        $show_edit = true;
                                        if (strtolower($row['id']) === 'pengelola' && !$is_admin) {
                                            $show_edit = false;
                                        }
                                        ?>
                                        <?php if($show_edit): ?>
                                        <button class="btn btn-sm btn-outline-primary btn-action-round" onclick="editRole('<?= addslashes($row['id']) ?>', '<?= addslashes($row['role_name']) ?>')" title="Edit Nama Role">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php endif; ?>
                                        <?php if(!$isProtected): ?>
                                            <form action="process_manage_roles.php" method="POST" class="m-0 p-0 d-inline">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= htmlspecialchars($row['id']) ?>">
                                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                <button type="button" class="btn btn-sm btn-outline-danger btn-action-round" onclick="confirmSubmit(event, this, 'Hapus Role?', 'User yang memiliki role ini mungkin akan kehilangan hak aksesnya. Lanjutkan?')" title="Hapus Role">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
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
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-bottom-0 pt-4 pb-0 px-4">
                <h5 class="modal-title fw-bold text-dark"><i class="fas fa-plus-circle me-2" style="color: #8b5cf6;"></i>Tambah Role Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="process_manage_roles.php" method="POST">
                <div class="modal-body px-4 py-3">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark small mb-1.5">Nama Jabatan / Role <span class="text-danger">*</span></label>
                        <input type="text" name="role_name" class="form-control rounded-3" required placeholder="Contoh: Kepala Asrama" style="font-size: 0.88rem; padding: 0.6rem 0.85rem;">
                        <div class="form-text mt-1.5 small text-muted"><i class="fas fa-info-circle me-1"></i>Sistem akan membuat ID teknis otomatis (contoh: <code>kepala_asrama</code>).</div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pb-4 px-4 pt-1 gap-2">
                    <button type="button" class="btn btn-light rounded-3 px-3.5 py-2 fw-semibold text-secondary" style="font-size: 0.85rem;" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-3 px-3.5 py-2 fw-semibold shadow-sm" style="background: linear-gradient(135deg, #8b5cf6, #7c3aed); border: none; font-size: 0.85rem;">Simpan Role</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit Role -->
<div class="modal fade" id="editRoleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-bottom-0 pt-4 pb-0 px-4">
                <h5 class="modal-title fw-bold text-dark"><i class="fas fa-edit me-2" style="color: #8b5cf6;"></i>Edit Role</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="process_manage_roles.php" method="POST">
                <div class="modal-body px-4 py-3">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary small mb-1">ID Sistem (Terkunci)</label>
                        <input type="text" name="id" id="edit_role_id" class="form-control rounded-3 bg-light text-muted" readonly style="cursor: not-allowed; font-size: 0.88rem; padding: 0.6rem 0.85rem;">
                        <div class="form-text mt-1 text-danger small"><i class="fas fa-lock me-1"></i>ID tidak dapat diubah untuk menjaga relasi database.</div>
                    </div>

                    <div class="mb-3 mt-3">
                        <label class="form-label fw-semibold text-dark small mb-1.5">Nama Jabatan / Role <span class="text-danger">*</span></label>
                        <input type="text" name="role_name" id="edit_role_name" class="form-control rounded-3" required style="font-size: 0.88rem; padding: 0.6rem 0.85rem;">
                    </div>
                </div>
                <div class="modal-footer border-top-0 pb-4 px-4 pt-1 gap-2">
                    <button type="button" class="btn btn-light rounded-3 px-3.5 py-2 fw-semibold text-secondary" style="font-size: 0.85rem;" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-3 px-3.5 py-2 fw-semibold shadow-sm" style="background: linear-gradient(135deg, #8b5cf6, #7c3aed); border: none; font-size: 0.85rem;">Simpan Perubahan</button>
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
