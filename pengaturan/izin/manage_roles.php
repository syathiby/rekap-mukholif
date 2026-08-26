<?php 
// 1. Panggil 'Otak' aplikasi dulu
require_once __DIR__ . '/../../bootstrap/init.php';

// 2. Jalankan 'SATPAM' buat ngejaga halaman
guard('izin_manage');

// Generate CSRF token sebelum form ditampilkan
$csrf_token = csrf_generate();

// Ambil semua role dari database
$rolesResult = $conn->query("SELECT * FROM roles ORDER BY created_at ASC");
$rolesList = [];
$totalRoles = 0;
$defaultRolesCount = 0;
$customRolesCount = 0;
$protectedRoles = ['admin', 'musyrif', 'pelihat', 'pengelola'];

if ($rolesResult) {
    while ($row = $rolesResult->fetch_assoc()) {
        $rolesList[] = $row;
        $totalRoles++;
        if (in_array($row['id'], $protectedRoles)) {
            $defaultRolesCount++;
        } else {
            $customRolesCount++;
        }
    }
}

// 3. Panggil Header Tampilan
require_once __DIR__ . '/../../layouts/header.php'; 
?>

<style>
    /* Scoped Style untuk Manajemen Role - Clean Modern Minimalist */
    :root {
        --mr-primary: #2563eb;
        --mr-primary-hover: #1d4ed8;
        --mr-border: #e2e8f0;
    }

    .nav-segmented-control {
        display: inline-flex;
        background: #f1f5f9;
        padding: 4px;
        border-radius: 14px;
        border: 1px solid #e2e8f0;
        gap: 3px;
        max-width: 100%;
        flex-wrap: nowrap;
        overflow-x: auto;
        scrollbar-width: none;
    }
    .nav-segmented-control::-webkit-scrollbar {
        display: none;
    }
    .nav-segment-link {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 0.45rem 0.95rem;
        border-radius: 10px;
        font-size: 0.82rem;
        font-weight: 600;
        color: #64748b;
        text-decoration: none;
        white-space: nowrap;
        transition: all 0.18s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .nav-segment-link:hover {
        color: #0f172a;
        background: rgba(255, 255, 255, 0.7);
    }
    .nav-segment-link.active {
        background: #ffffff;
        color: var(--mr-primary);
        box-shadow: 0 2px 6px rgba(15, 23, 42, 0.08), 0 0 0 1px rgba(226, 232, 240, 0.8);
    }

    .btn-action-round {
        width: 32px;
        height: 32px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        transition: all 0.18s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .btn-action-round:hover {
        transform: translateY(-2px);
    }

    .role-system-badge {
        font-family: 'JetBrains Mono', 'Fira Code', monospace;
        font-size: 0.75rem;
        background: #f8fafc;
        color: #475569;
        padding: 3px 8px;
        border-radius: 6px;
        border: 1px solid #e2e8f0;
    }

    .role-link-setting {
        color: var(--mr-primary);
        font-size: 0.78rem;
        font-weight: 600;
        text-decoration: none;
        transition: color 0.15s ease;
    }
    .role-link-setting:hover {
        color: var(--mr-primary-hover);
        text-decoration: underline;
    }

    @media (max-width: 991px) {
        .nav-segmented-control {
            width: 100%;
            overflow-x: auto;
            flex-wrap: nowrap;
            -webkit-overflow-scrolling: touch;
        }
        .nav-segment-link {
            flex-shrink: 0;
            padding: 0.45rem 0.75rem;
            font-size: 0.78rem;
        }
    }
</style>

<div class="dashboard-wrapper container-fluid px-0 px-md-2 mt-2 mb-5">
    
    <!-- Header Page -->
    <div class="d-flex flex-column flex-xl-row justify-content-between align-items-start align-items-xl-center mb-4 px-1 gap-3">
        <div class="d-flex align-items-center">
            <div class="d-flex align-items-center justify-content-center rounded-3 me-3 shadow-sm flex-shrink-0" style="width: 48px; height: 48px; background: linear-gradient(135deg, #2563eb, #3b82f6); color: white;">
                <i class="fas fa-tags fa-lg"></i>
            </div>
            <div>
                <h3 class="fw-bold mb-0 text-dark" style="letter-spacing: -0.3px; font-size: 1.35rem;">Manajemen Role Dinamis</h3>
                <p class="text-muted mb-0" style="font-size: 0.85rem;">Kelola master data jabatan (role) yang dapat dipilih saat membuat akun pengguna.</p>
            </div>
        </div>
        
        <!-- Segmented Navigation Control -->
        <nav class="nav-segmented-control" aria-label="Navigasi Pengaturan Izin">
            <a href="index.php" class="nav-segment-link">
                <i class="fas fa-user-shield"></i>
                <span>Izin User</span>
            </a>
            <a href="role.php" class="nav-segment-link">
                <i class="fas fa-layer-group"></i>
                <span>Default Role</span>
            </a>
            <a href="manage_roles.php" class="nav-segment-link active">
                <i class="fas fa-tags"></i>
                <span>Kelola Role</span>
            </a>
            <a href="bulk.php" class="nav-segment-link">
                <i class="fas fa-users-cog"></i>
                <span>Edit Massal</span>
            </a>
            <a href="daftar_izin.php" class="nav-segment-link">
                <i class="fas fa-list-check"></i>
                <span>Daftar Izin</span>
            </a>
        </nav>
    </div>

    <!-- Table Master Role with Integrated Header Toolbar -->
    <div class="card shadow-sm border-0 rounded-4 mb-4 overflow-hidden" style="background: #ffffff; border: 1.5px solid #edf2f7 !important;">
        <div class="card-header bg-white border-bottom p-3.5 p-md-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-stretch align-items-md-center gap-3">
                <div>
                    <h5 class="fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                        <i class="fas fa-layer-group text-primary fa-sm"></i>
                        <span>Daftar Jabatan (Role)</span>
                    </h5>
                    <div class="d-flex align-items-center gap-2 flex-wrap text-muted small" style="font-size: 0.8rem;">
                        <span><strong class="text-dark"><?= $totalRoles ?></strong> total role</span>
                        <span>•</span>
                        <span class="text-primary"><strong class="text-primary"><?= $defaultRolesCount ?></strong> sistem default</span>
                        <span>•</span>
                        <span class="text-success"><strong class="text-success"><?= $customRolesCount ?></strong> kustom</span>
                    </div>
                </div>

                <div>
                    <button type="button" class="btn btn-primary rounded-3 px-3.5 py-2 fw-semibold shadow-sm d-inline-flex align-items-center justify-content-center gap-2 w-100 w-md-auto" style="background: var(--mr-primary); border: none; font-size: 0.85rem;" data-bs-toggle="modal" data-bs-target="#addRoleModal">
                        <i class="fas fa-plus fa-sm"></i>
                        <span>Tambah Role Baru</span>
                    </button>
                </div>
            </div>
        </div>

        <div class="card-body p-0">
            <!-- Desktop Table View (>= 768px) -->
            <div class="table-responsive d-none d-md-block">
                <table class="table table-hover align-middle mb-0">
                    <thead style="background: #f8fafc; border-bottom: 1.5px solid #e2e8f0;">
                        <tr>
                            <th class="px-4 py-3.5 text-secondary" style="width: 70px; font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">No</th>
                            <th class="py-3.5 text-secondary" style="font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Nama Role (Jabatan)</th>
                            <th class="py-3.5 text-secondary" style="font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">ID Sistem</th>
                            <th class="py-3.5 text-secondary" style="font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Tipe Role</th>
                            <th class="py-3.5 px-4 text-center text-secondary" style="width: 140px; font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = 1;
                        $is_admin = (isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'admin');

                        foreach ($rolesList as $row): 
                            $isProtected = in_array($row['id'], $protectedRoles);
                            $showEdit = true;
                            if (strtolower($row['id']) === 'pengelola' && !$is_admin) {
                                $showEdit = false;
                            }
                        ?>
                            <tr>
                                <td class="px-4 text-muted fw-semibold" style="font-size: 0.85rem;"><?= $no++ ?></td>
                                <td>
                                    <div>
                                        <div class="fw-bold text-dark" style="font-size: 0.92rem;"><?= htmlspecialchars($row['role_name']) ?></div>
                                        <?php if (strtolower($row['id']) !== 'admin'): ?>
                                            <a href="role.php?role=<?= urlencode($row['id']) ?>" class="role-link-setting">
                                                <i class="fas fa-sliders-h me-1"></i>Atur Izin Default &rarr;
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted" style="font-size: 0.76rem;">
                                                <i class="fas fa-infinity me-1 text-primary"></i>Akses Penuh (Bypass)
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="role-system-badge">
                                        <i class="fas fa-code fa-xs opacity-50 me-1"></i><?= htmlspecialchars($row['id']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($isProtected): ?>
                                        <span class="badge" style="background:#f1f5f9; color:#475569; border:1px solid #e2e8f0; font-size:0.73rem; font-weight:600; padding: 4px 8px; border-radius: 6px;">
                                            <i class="fas fa-shield-alt me-1 text-muted"></i> Sistem Default
                                        </span>
                                    <?php else: ?>
                                        <span class="badge" style="background:#f0fdf4; color:#166534; border:1px solid #bbf7d0; font-size:0.73rem; font-weight:600; padding: 4px 8px; border-radius: 6px;">
                                            <i class="fas fa-user-tag me-1 text-success"></i> Role Kustom
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 text-center">
                                    <div class="d-inline-flex gap-1.5 align-items-center justify-content-center">
                                        <?php if ($showEdit): ?>
                                            <button class="btn btn-sm btn-outline-primary btn-action-round" onclick="editRole('<?= addslashes($row['id']) ?>', '<?= addslashes($row['role_name']) ?>')" title="Edit Nama Role">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        <?php endif; ?>
                                        <?php if (!$isProtected): ?>
                                            <form action="process_manage_roles.php" method="POST" class="m-0 p-0 d-inline">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= htmlspecialchars($row['id']) ?>">
                                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                <button type="button" class="btn btn-sm btn-outline-danger btn-action-round" onclick="confirmSubmit(event, this, 'Hapus Role?', 'Pengguna yang memiliki role ini mungkin akan kehilangan hak akses standarnya. Apakah Anda yakin ingin melanjutkan?')" title="Hapus Role">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($rolesList)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-5">
                                    <i class="fas fa-tags fa-3x mb-3 text-muted opacity-25"></i>
                                    <div>Belum ada data role di sistem.</div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mobile Card List View (< 768px, No horizontal clipping / No cut-off) -->
            <div class="d-block d-md-none">
                <?php 
                $is_admin = (isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'admin');
                foreach ($rolesList as $row): 
                    $isProtected = in_array($row['id'], $protectedRoles);
                    $showEdit = true;
                    if (strtolower($row['id']) === 'pengelola' && !$is_admin) {
                        $showEdit = false;
                    }
                ?>
                    <div class="p-3 border-bottom" style="border-color: #f1f5f9 !important;">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                            <div style="flex: 1; min-width: 0;">
                                <div class="fw-bold text-dark mb-1 text-truncate" style="font-size: 0.95rem;">
                                    <?= htmlspecialchars($row['role_name']) ?>
                                </div>
                                <div class="d-flex align-items-center gap-1.5 flex-wrap">
                                    <span class="role-system-badge">
                                        <i class="fas fa-code fa-xs opacity-50 me-1"></i><?= htmlspecialchars($row['id']) ?>
                                    </span>
                                    <?php if ($isProtected): ?>
                                        <span class="badge" style="background:#f1f5f9; color:#475569; border:1px solid #e2e8f0; font-size:0.7rem; font-weight:600; padding: 3px 6px; border-radius: 6px;">
                                            <i class="fas fa-shield-alt me-1 text-muted"></i> Sistem Default
                                        </span>
                                    <?php else: ?>
                                        <span class="badge" style="background:#f0fdf4; color:#166534; border:1px solid #bbf7d0; font-size:0.7rem; font-weight:600; padding: 3px 6px; border-radius: 6px;">
                                            <i class="fas fa-user-tag me-1 text-success"></i> Role Kustom
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="d-flex gap-1.5 align-items-center flex-shrink-0">
                                <?php if ($showEdit): ?>
                                    <button class="btn btn-sm btn-outline-primary btn-action-round" onclick="editRole('<?= addslashes($row['id']) ?>', '<?= addslashes($row['role_name']) ?>')" title="Edit Nama Role">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                <?php endif; ?>
                                <?php if (!$isProtected): ?>
                                    <form action="process_manage_roles.php" method="POST" class="m-0 p-0 d-inline">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= htmlspecialchars($row['id']) ?>">
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                        <button type="button" class="btn btn-sm btn-outline-danger btn-action-round" onclick="confirmSubmit(event, this, 'Hapus Role?', 'Pengguna yang memiliki role ini mungkin akan kehilangan hak akses standarnya. Apakah Anda yakin ingin melanjutkan?')" title="Hapus Role">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Direct link to configure default permissions for this role -->
                        <?php if (strtolower($row['id']) !== 'admin'): ?>
                            <div class="mt-2.5 pt-2 border-top d-flex justify-content-between align-items-center" style="border-color: #f8fafc !important;">
                                <a href="role.php?role=<?= urlencode($row['id']) ?>" class="role-link-setting d-inline-flex align-items-center">
                                    <i class="fas fa-sliders-h me-1.5"></i>Atur Izin Default Role Ini &rarr;
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="mt-2.5 pt-2 border-top d-flex justify-content-between align-items-center" style="border-color: #f8fafc !important;">
                                <span class="text-muted small">
                                    <i class="fas fa-infinity me-1.5 text-primary"></i>Akses Penuh Seluruh Sistem (Bypass)
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($rolesList)): ?>
                    <div class="text-center text-muted py-5 px-3">
                        <i class="fas fa-tags fa-3x mb-3 text-muted opacity-25"></i>
                        <div>Belum ada data role di sistem.</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal Tambah Role -->
<div class="modal fade" id="addRoleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-bottom-0 pt-4 pb-0 px-4">
                <div class="d-flex align-items-center gap-2.5">
                    <div class="d-flex align-items-center justify-content-center rounded-circle flex-shrink-0" style="width: 36px; height: 36px; background: rgba(37, 99, 235, 0.1); color: var(--mr-primary);">
                        <i class="fas fa-plus"></i>
                    </div>
                    <h5 class="modal-title fw-bold text-dark">Tambah Role Baru</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="process_manage_roles.php" method="POST">
                <div class="modal-body px-4 py-3">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark small mb-1.5">Nama Jabatan / Role <span class="text-danger">*</span></label>
                        <input type="text" name="role_name" class="form-control rounded-3" required placeholder="Contoh: Kepala Asrama" style="font-size: 0.88rem; padding: 0.6rem 0.85rem; border: 1.5px solid #e2e8f0;">
                        <div class="form-text mt-2 small text-muted"><i class="fas fa-info-circle me-1 text-primary"></i>Sistem akan membuat ID teknis otomatis (contoh: <code>kepala_asrama</code>).</div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pb-4 px-4 pt-1 gap-2">
                    <button type="button" class="btn btn-light rounded-pill px-4 py-2 fw-semibold text-secondary" style="font-size: 0.85rem;" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 py-2 fw-semibold shadow-sm" style="background: var(--mr-primary); border: none; font-size: 0.85rem;">Simpan Role</button>
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
                <div class="d-flex align-items-center gap-2.5">
                    <div class="d-flex align-items-center justify-content-center rounded-circle flex-shrink-0" style="width: 36px; height: 36px; background: rgba(37, 99, 235, 0.1); color: var(--mr-primary);">
                        <i class="fas fa-edit"></i>
                    </div>
                    <h5 class="modal-title fw-bold text-dark">Edit Nama Role</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="process_manage_roles.php" method="POST">
                <div class="modal-body px-4 py-3">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary small mb-1">ID Sistem (Terkunci)</label>
                        <input type="text" name="id" id="edit_role_id" class="form-control rounded-3 bg-light text-muted font-monospace" readonly style="cursor: not-allowed; font-size: 0.88rem; padding: 0.6rem 0.85rem; border: 1.5px solid #e2e8f0;">
                        <div class="form-text mt-1.5 text-danger small"><i class="fas fa-lock me-1"></i>ID tidak dapat diubah untuk menjaga integritas relasi database.</div>
                    </div>

                    <div class="mb-3 mt-3">
                        <label class="form-label fw-bold text-dark small mb-1.5">Nama Jabatan / Role <span class="text-danger">*</span></label>
                        <input type="text" name="role_name" id="edit_role_name" class="form-control rounded-3" required style="font-size: 0.88rem; padding: 0.6rem 0.85rem; border: 1.5px solid #e2e8f0;">
                    </div>
                </div>
                <div class="modal-footer border-top-0 pb-4 px-4 pt-1 gap-2">
                    <button type="button" class="btn btn-light rounded-pill px-4 py-2 fw-semibold text-secondary" style="font-size: 0.85rem;" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 py-2 fw-semibold shadow-sm" style="background: var(--mr-primary); border: none; font-size: 0.85rem;">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editRole(id, name) {
    var idInput = document.getElementById('edit_role_id');
    var nameInput = document.getElementById('edit_role_name');
    if (idInput) idInput.value = id;
    if (nameInput) nameInput.value = name;
    
    var editModal = new bootstrap.Modal(document.getElementById('editRoleModal'));
    editModal.show();
}
</script>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
