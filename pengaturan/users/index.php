<?php
// 1. Panggil 'Otak' aplikasi dulu
require_once __DIR__ . '/../../bootstrap/init.php';

// 2. Jalankan 'SATPAM' buat ngejaga halaman
guard('user_manage'); 

// 3. Kalau lolos, baru panggil Tampilan
require_once __DIR__ . '/../../layouts/header.php';

// --- AMBIL DATA ROLE UNTUK FILTER ---
$roles = [];
$resRoles = $conn->query("SELECT id, role_name FROM roles ORDER BY role_name ASC");
if ($resRoles) {
    while($r = $resRoles->fetch_assoc()) {
        $roles[] = $r;
    }
}

// --- LOGIKA PENCARIAN DAN FILTER ---
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role_filter'] ?? '';

$sql = "SELECT id, nama_lengkap, username, role FROM users WHERE 1=1";
$params = [];
$types = "";

if ($search !== '') {
    $sql .= " AND (nama_lengkap LIKE ? OR username LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= "ss";
}

if ($role_filter !== '') {
    $sql .= " AND role = ?";
    $params[] = $role_filter;
    $types .= "s";
}

$sql .= " ORDER BY nama_lengkap ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$users = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}
$stmt->close();
?>

<style>
    :root {
        --bg-card: #ffffff;
        --border: #e2e8f0;
        --text-main: #0f172a;
        --text-muted: #64748b;
        --primary-soft: #eff6ff;
        --primary-color: #3b82f6;
    }
    
    .filter-card {
        background-color: var(--bg-card);
        border-radius: 1rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
        border: 1px solid var(--border);
        transition: all 0.3s ease;
    }
    
    .table-container {
        background-color: var(--bg-card);
        padding: 0;
        border-radius: 1rem;
        margin-bottom: 2rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
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
    
    .badge-role {
        font-size: 0.75rem;
        font-weight: 600;
        padding: 0.35rem 0.75rem;
        border-radius: 9999px;
        display: inline-block;
    }

    .badge-role-admin {
        background-color: rgba(16, 185, 129, 0.1);
        color: #059669;
    }
    
    .badge-role-user {
        background-color: rgba(79, 70, 229, 0.1);
        color: #4f46e5;
    }

    .btn-action {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        transition: all 0.2s ease;
        border: none;
    }
    
    .btn-action-edit {
        background-color: rgba(245, 158, 11, 0.08);
        color: #d97706;
    }
    
    .btn-action-edit:hover {
        background-color: #f59e0b;
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 4px 6px -1px rgba(245, 158, 11, 0.3);
    }
    
    .btn-action-delete {
        background-color: rgba(239, 68, 68, 0.08);
        color: #dc2626;
    }
    
    .btn-action-delete:hover {
        background-color: #ef4444;
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 4px 6px -1px rgba(239, 68, 68, 0.3);
    }
    
    .search-input:focus {
        box-shadow: none;
        border-color: var(--border);
    }
</style>

<div class="container-fluid py-4 px-4">
    <!-- Header Page -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="fw-bold text-dark mb-1"><i class="fas fa-users-cog text-primary me-2"></i>Manajemen User</h3>
            <p class="text-muted mb-0">Kelola pengguna, perbarui profil, dan kontrol akses sistem.</p>
        </div>
        <div class="d-flex flex-wrap justify-content-start justify-content-md-end align-items-center gap-2">
            <button type="button" class="btn btn-outline-danger shadow-sm rounded-pill px-4 d-none d-inline-flex align-items-center" id="btnBulkDelete" onclick="if(confirm('Yakin ingin menghapus user yang dipilih? Tindakan ini tidak bisa dibatalkan!')) document.getElementById('bulkDeleteForm').submit();" style="white-space: nowrap;">
                <i class="fas fa-trash-alt me-2"></i> Hapus (<span id="selectedCount" class="fw-bold mx-1">0</span>)
            </button>
            <a href="form-user.php" class="btn btn-primary shadow-sm rounded-pill px-4" style="background: linear-gradient(135deg, #3b82f6, #2563eb); border: none; white-space: nowrap;">
                <i class="fas fa-user-plus me-2"></i> Tambah User
            </a>
        </div>
    </div>

    <!-- Filter & Search Card -->
    <div class="filter-card mb-4 p-3 p-md-4">
        <form id="filterForm" method="GET" action="index.php" class="row gx-3 gy-3 align-items-center">
            <div class="col-12 col-md-6">
                <label class="form-label text-muted small fw-bold mb-1"><i class="fas fa-search me-1"></i> Pencarian</label>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" id="searchInput" class="form-control border-start-0 search-input" name="search" placeholder="Cari nama atau username..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label text-muted small fw-bold mb-1"><i class="fas fa-filter me-1"></i> Filter Jabatan</label>
                <div class="d-flex gap-2">
                    <select id="roleFilter" name="role_filter" class="form-select">
                        <option value="">Semua Jabatan</option>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= htmlspecialchars($r['id']) ?>" <?= $role_filter === $r['id'] ? 'selected' : '' ?>><?= htmlspecialchars($r['role_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" id="btnResetFilter" class="btn btn-light border text-secondary fw-medium px-3 d-none" title="Reset Filter">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
            </div>
            <!-- Tombol manual dihilangkan agar UI lebih clean karena sudah auto-load via AJAX -->
        </form>
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
    <div class="table-container" style="transition: opacity 0.2s ease;">
        <form id="bulkDeleteForm" action="bulk-delete-users.php" method="POST">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th width="4%" class="text-center">
                            <input class="form-check-input shadow-sm" type="checkbox" id="selectAll">
                        </th>
                        <th width="5%" class="text-center">No</th>
                        <th>Nama Lengkap</th>
                        <th>Username</th>
                        <th width="20%">Jabatan (Role)</th>
                        <th width="15%" class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)) : ?>
                        <tr>
                            <td colspan="6" class="text-center py-5">
                                <div class="py-4">
                                    <div class="mb-3">
                                        <div class="d-inline-flex align-items-center justify-content-center bg-light rounded-circle" style="width: 80px; height: 80px;">
                                            <i class="fas fa-users-slash fa-2x text-secondary" style="opacity: 0.5;"></i>
                                        </div>
                                    </div>
                                    <h5 class="fw-bold text-dark mb-1">Tidak Ada Data Ditemukan</h5>
                                    <p class="text-muted small mb-0">Coba sesuaikan filter pencarian atau tambah user baru.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else : ?>
                        <?php $nomor = 1; ?>
                        <?php foreach ($users as $user) : ?>
                            <tr>
                                <?php 
                                $is_admin = (isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'admin');
                                $user_role_str = strtolower($user['role']);
                                $is_self = (isset($_SESSION['user_id']) && $user['id'] == $_SESSION['user_id']);
                                
                                $can_edit = false;
                                $can_delete = false;

                                if ($is_admin) {
                                    if ($user_role_str === 'admin') {
                                        if ($is_self) $can_edit = true; 
                                    } else {
                                        $can_edit = true; 
                                        $can_delete = true; 
                                    }
                                } else {
                                    if ($is_self) {
                                        $can_edit = true; 
                                    } elseif ($user_role_str !== 'admin' && $user_role_str !== 'pengelola') {
                                        $can_edit = true; 
                                        $can_delete = true; 
                                    }
                                }

                                if ($is_self) {
                                    $can_delete = false;
                                }
                                ?>
                                <td class="text-center">
                                    <?php if ($can_delete) : ?>
                                        <input class="form-check-input shadow-sm user-checkbox" type="checkbox" name="ids[]" value="<?= $user['id'] ?>">
                                    <?php else : ?>
                                        <input class="form-check-input shadow-sm" type="checkbox" disabled>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center fw-semibold text-muted"><?= $nomor++ ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px; font-weight: bold;">
                                            <?= strtoupper(substr($user['nama_lengkap'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($user['nama_lengkap']) ?></div>
                                            <?php if($is_self): ?>
                                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 mt-1" style="font-size: 0.65rem;">Anda Sendiri</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-muted font-monospace" style="font-size: 0.9rem;"><i class="fas fa-at fa-sm me-1 text-secondary opacity-50"></i><?= htmlspecialchars($user['username']) ?></td>
                                <td>
                                    <?php 
                                        $display_role = ucfirst($user['role']);
                                        foreach($roles as $r) {
                                            if ($r['id'] === $user['role']) {
                                                $display_role = $r['role_name'];
                                                break;
                                            }
                                        }
                                    ?>
                                    <?php if (strtolower($user['role']) == 'admin') : ?>
                                        <span class="badge-role badge-role-admin"><i class="fas fa-shield-alt me-1"></i> <?= htmlspecialchars($display_role) ?></span>
                                    <?php else: ?>
                                        <span class="badge-role badge-role-user"><i class="fas fa-user-tag me-1"></i> <?= htmlspecialchars($display_role) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="d-inline-flex gap-2">
                                        <?php if ($can_edit) : ?>
                                            <a href="form-user.php?id=<?= $user['id'] ?>" class="btn-action btn-action-edit" title="Edit User">
                                                <i class="fas fa-pen"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($can_delete) : ?>
                                            <a href="delete-user.php?id=<?= $user['id'] ?>" class="btn-action btn-action-delete" title="Hapus User" onclick="confirmSubmit(event, this, 'Hapus User', 'Yakin mau hapus user <?= htmlspecialchars($user['username']) ?>? Tindakan ini tidak bisa dibatalkan!');">
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
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let debounceTimer;
    const searchInput = document.getElementById('searchInput');
    const roleFilter = document.getElementById('roleFilter');
    const btnResetFilter = document.getElementById('btnResetFilter');
    const tableContainer = document.querySelector('.table-container');

    const STORAGE_KEY_SEARCH = 'user_manage_search';
    const STORAGE_KEY_ROLE = 'user_manage_role';
    const STORAGE_KEY_SELECTED = 'user_manage_selected';

    // Helper: State memori Centangan (Checkboxes)
    function getStoredSelected() {
        const data = sessionStorage.getItem(STORAGE_KEY_SELECTED);
        return data ? new Set(JSON.parse(data)) : new Set();
    }
    function saveStoredSelected(set) {
        sessionStorage.setItem(STORAGE_KEY_SELECTED, JSON.stringify(Array.from(set)));
    }

    // Helper: State memori Filter
    function saveFilters() {
        if(searchInput) sessionStorage.setItem(STORAGE_KEY_SEARCH, searchInput.value);
        if(roleFilter) sessionStorage.setItem(STORAGE_KEY_ROLE, roleFilter.value);
        toggleResetButton();
    }

    function toggleResetButton() {
        if (!btnResetFilter) return;
        if ((searchInput && searchInput.value) || (roleFilter && roleFilter.value)) {
            btnResetFilter.classList.remove('d-none');
        } else {
            btnResetFilter.classList.add('d-none');
        }
    }

    function fetchFilteredData() {
        if(!tableContainer) return;
        tableContainer.style.opacity = '0.4';
        saveFilters(); // Simpan setiap kali fetch
        
        const url = new URL(window.location.href);
        if(searchInput) url.searchParams.set('search', searchInput.value);
        if(roleFilter) url.searchParams.set('role_filter', roleFilter.value);
        
        window.history.replaceState({}, '', url);

        fetch(url)
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newTableContainer = doc.querySelector('.table-container');
                if(newTableContainer) {
                    tableContainer.innerHTML = newTableContainer.innerHTML;
                }
                tableContainer.style.opacity = '1';
                
                bindCheckboxEvents();
            })
            .catch(error => {
                console.error('Error fetching data:', error);
                tableContainer.style.opacity = '1';
            });
    }

    // Event Listeners Filter
    if (searchInput) {
        searchInput.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(fetchFilteredData, 300);
        });
    }
    if (roleFilter) {
        roleFilter.addEventListener('change', fetchFilteredData);
    }
    if (btnResetFilter) {
        btnResetFilter.addEventListener('click', () => {
            if(searchInput) searchInput.value = '';
            if(roleFilter) roleFilter.value = '';
            fetchFilteredData();
        });
    }

    // Checkbox Logic
    function bindCheckboxEvents() {
        const selectAll = document.getElementById('selectAll');
        const checkboxes = document.querySelectorAll('.user-checkbox');
        const btnBulkDelete = document.getElementById('btnBulkDelete');
        const selectedCount = document.getElementById('selectedCount');

        let selectedSet = getStoredSelected();

        // Terapkan state centangan dari memori ke UI yang baru dirender
        checkboxes.forEach(cb => {
            cb.checked = selectedSet.has(cb.value);
        });

        function updateBulkDeleteButton() {
            if(!btnBulkDelete) return;
            const count = selectedSet.size;
            if(selectedCount) selectedCount.textContent = count;
            if (count > 0) {
                btnBulkDelete.classList.remove('d-none');
            } else {
                btnBulkDelete.classList.add('d-none');
            }
        }

        function handleSelectionChange() {
            checkboxes.forEach(cb => {
                if (cb.checked) {
                    selectedSet.add(cb.value);
                } else {
                    selectedSet.delete(cb.value);
                }
            });
            saveStoredSelected(selectedSet);
            updateBulkDeleteButton();
            
            if (selectAll) {
                const totalCheckboxes = checkboxes.length;
                const checkedCount = document.querySelectorAll('.user-checkbox:checked').length;
                selectAll.checked = (totalCheckboxes > 0 && totalCheckboxes === checkedCount);
            }
        }

        if (selectAll) {
            selectAll.addEventListener('change', function() {
                checkboxes.forEach(cb => { cb.checked = selectAll.checked; });
                handleSelectionChange();
            });
            // Update initial state of selectAll
            const totalCheckboxes = checkboxes.length;
            const checkedCount = document.querySelectorAll('.user-checkbox:checked').length;
            selectAll.checked = (totalCheckboxes > 0 && totalCheckboxes === checkedCount);
        }

        checkboxes.forEach(cb => {
            cb.addEventListener('change', handleSelectionChange);
        });

        updateBulkDeleteButton();
    }

    // INIT PAGE LOAD
    const urlParams = new URLSearchParams(window.location.search);
    // Jika tidak ada filter di URL, tapi ada di memori, auto-load memori
    if (!urlParams.has('search') && !urlParams.has('role_filter')) {
        const storedSearch = sessionStorage.getItem(STORAGE_KEY_SEARCH);
        const storedRole = sessionStorage.getItem(STORAGE_KEY_ROLE);
        
        let needsFetch = false;
        if (storedSearch !== null && searchInput && storedSearch !== searchInput.value) {
            searchInput.value = storedSearch;
            needsFetch = true;
        }
        if (storedRole !== null && roleFilter && storedRole !== roleFilter.value) {
            roleFilter.value = storedRole;
            needsFetch = true;
        }
        
        toggleResetButton();
        if (needsFetch) {
            fetchFilteredData();
        } else {
            bindCheckboxEvents();
        }
    } else {
        // Jika ada di URL, pastikan filter sinkron dengan URL (sudah di PHP) dan simpan ke memori
        saveFilters();
        bindCheckboxEvents();
    }
});
</script>

<?php
require_once __DIR__ . '/../../layouts/footer.php';
?>