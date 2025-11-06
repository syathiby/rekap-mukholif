<?php 
// 1. Panggil 'Otak' aplikasi dulu
require_once __DIR__ . '/../init.php';

// 2. Jalankan 'SATPAM' buat ngejaga halaman
guard('jenis_pelanggaran_view'); 

// 3. Kalau lolos, baru panggil Tampilan
require_once __DIR__ . '/../header.php';

// === PENGECEKAN IZIN DI AWAL ===
$can_create = has_permission('jenis_pelanggaran_create');
$can_edit = has_permission('jenis_pelanggaran_edit');
$can_delete = has_permission('jenis_pelanggaran_delete');

// --- AMBIL DATA UNTUK FILTER ---
$bagian_list_query = "SELECT DISTINCT bagian FROM jenis_pelanggaran ORDER BY bagian ASC";
$bagian_list_result = mysqli_query($conn, $bagian_list_query);

// --- LOGIKA FILTER ---
$filter_bagian = $_GET['bagian'] ?? '';
$filter_kategori = $_GET['kategori'] ?? '';
$filter_search = $_GET['search'] ?? ''; 

$query = "SELECT * FROM jenis_pelanggaran";
$where_clauses = [];
$params = [];
$types = '';

if (!empty($filter_search)) {
    $where_clauses[] = "nama_pelanggaran LIKE ?";
    $params[] = "%" . $filter_search . "%";
    $types .= 's';
}
if (!empty($filter_bagian)) {
    $where_clauses[] = "bagian = ?";
    $params[] = $filter_bagian;
    $types .= 's';
}
if (!empty($filter_kategori)) {
    $where_clauses[] = "kategori = ?";
    $params[] = $filter_kategori;
    $types .= 's';
}
if (!empty($where_clauses)) {
    $query .= " WHERE " . implode(" AND ", $where_clauses);
}
$query .= " ORDER BY CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(nama_pelanggaran, ' ', -1), ')', 1) AS UNSIGNED) ASC";

// --- EKSEKUSI QUERY ---
$stmt = mysqli_prepare($conn, $query);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Hitung colspan dinamis untuk tabel
$colspan = 5; // Kolom dasar: No, Nama, Bagian, Poin, Kategori
// === REVISI 1 ===
// Checkbox muncul jika bisa edit ATAU bisa delete
if ($can_edit || $can_delete) $colspan++; // Tambah 1 untuk checkbox
if ($can_edit || $can_delete) $colspan++; // Tambah 1 untuk Aksi
?>

<style>
    /* CSS tidak ada perubahan */
    :root {
        --primary-color: #3498db;
        --secondary-color: #2c3e50;
    }
    .page-title-card {
        background: linear-gradient(135deg, var(--secondary-color), var(--primary-color));
        color: white;
        padding: 1.5rem;
        border-radius: 8px;
        margin-bottom: 1.5rem;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }
    .filter-card, .table-container {
        background-color: #fff;
        padding: 1.5rem;
        border-radius: 8px;
        margin-bottom: 1.5rem;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    }
    .table-container { padding: 0; overflow: hidden; }
    .table thead {
        background-color: var(--secondary-color);
        color: white;
    }
    .badge.bg-info { background-color: #0dcaf0 !important; }
    .badge.bg-warning { background-color: #ffc107 !important; }
    .badge.bg-danger { background-color: #dc3545 !important; }
    .badge.bg-dark { background-color: #212529 !important; }
    
    .card-action-bulk {
        padding: 1rem;
        background-color: #fff;
    }

    @media (max-width: 767px) {
        .page-title-card {
            flex-direction: column;
            align-items: flex-start !important;
            gap: 1rem;
        }
        .page-title-card .btn-group {
            width: 100%;
            display: flex;
            justify-content: flex-end;
        }
        .page-title-card h3 {
            font-size: 1.25rem;
        }
        .filter-card .col-md-4, .filter-card .col-md-3, .filter-card .col-md-2 {
            margin-bottom: 0.75rem;
        }
        .filter-card .row > *:last-child {
            margin-bottom: 0;
        }

        .card-action-bulk .d-flex {
            flex-direction: column;
            gap: 0.75rem;
            align-items: stretch !important;
        }
        .card-action-bulk .btn-group {
            width: 100%;
            display: flex; 
        }
        .card-action-bulk .btn-group .btn {
            flex-grow: 1; /* Biar tombolnya sama rata */
        }
        .card-action-bulk .text-muted {
            text-align: center;
        }
    }
</style>

<div class="container mt-4 mb-5">
    <div class="page-title-card d-flex justify-content-between align-items-center">
        <h3 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Data Jenis Pelanggaran</h3>
        <?php if ($can_create): ?>
            <div class="btn-group">
                <a href="create.php" class="btn btn-success"><i class="fas fa-plus-circle me-1"></i> Tambah Baru</a>
            </div>
        <?php endif; ?>
    </div>

    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?php echo $_SESSION['message']['type']; ?> alert-dismissible fade show" role="alert">
            <?php 
                if ($_SESSION['message']['type'] == 'success') {
                    echo '<i class="fas fa-check-circle me-2"></i>';
                } elseif ($_SESSION['message']['type'] == 'danger') {
                    echo '<i class="fas fa-times-circle me-2"></i>';
                }
                echo $_SESSION['message']['text']; 
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['message']); ?>
    <?php endif; ?>
    
    <div class="filter-card">
        <form method="GET" action="" id="filterForm"> 
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="search" class="form-label">Cari Nama Pelanggaran</label>
                    <input type="text" name="search" id="search" class="form-control" placeholder="Ketik di sini..." value="<?= htmlspecialchars($filter_search); ?>">
                </div>
                <div class="col-md-3">
                    <label for="bagian" class="form-label">Filter Bagian</label>
                    <select name="bagian" id="bagian" class="form-select">
                        <option value="">-- Semua Bagian --</option>
                        <?php mysqli_data_seek($bagian_list_result, 0); ?>
                        <?php while ($bagian_row = mysqli_fetch_assoc($bagian_list_result)) : ?>
                            <option value="<?= htmlspecialchars($bagian_row['bagian']); ?>" <?= ($filter_bagian == $bagian_row['bagian']) ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($bagian_row['bagian']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="kategori" class="form-label">Filter Kategori</label>
                    <select name="kategori" id="kategori" class="form-select">
                        <option value="">-- Semua Kategori --</option>
                        <option value="Ringan" <?= ($filter_kategori == 'Ringan') ? 'selected' : '' ?>>Ringan</option>
                        <option value="Sedang" <?= ($filter_kategori == 'Sedang') ? 'selected' : '' ?>>Sedang</option>
                        <option value="Berat" <?= ($filter_kategori == 'Berat') ? 'selected' : '' ?>>Berat</option>
                        <option value="Sangat Berat" <?= ($filter_kategori == 'Sangat Berat') ? 'selected' : '' ?>>Sangat Berat</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex">
                    <button type="submit" class="btn btn-primary w-100 me-2 d-none"><i class="fas fa-search"></i> Cari</button> 
                    <a href="index.php" id="resetFilterBtn" class="btn btn-outline-secondary w-100" title="Reset Filter"><i class="fas fa-sync-alt"></i> Reset</a>
                </div>
            </div>
        </form>
    </div>

    <form method="POST" action="" id="bulkActionForm">

        <div class="card shadow-sm mb-3 card-action-bulk">
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <div class="btn-group" role="group">
                        <?php if ($can_edit): ?>
                            <button type="button" class="btn btn-warning" id="bulkEditBtn" disabled title="Edit Terpilih">
                                <i class="fas fa-edit me-1"></i> 
                                <span class="d-none d-sm-inline">Edit Terpilih</span>
                            </button>
                        <?php endif; ?>
                        <?php if ($can_delete): ?>
                            <button type="submit" class="btn btn-danger" id="bulkDeleteBtn" disabled title="Hapus Terpilih">
                                <i class="fas fa-trash-alt me-1"></i> 
                                <span class="d-none d-sm-inline">Hapus Terpilih</span>
                            </button>
                        <?php endif; ?>
                    </div>
                    
                    <div id="selected-count-info" class="ms-3 d-none">
                        </div>
                </div>

                <div class="text-muted">
                    Total Data: <strong><?= mysqli_num_rows($result); ?></strong>
                </div>
            </div>
        </div>
        
        <div class="table-container">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead>
                        <tr>
                            <?php if ($can_edit || $can_delete): ?>
                                <th width="3%" class="text-center"><input type="checkbox" id="selectAll"></th>
                            <?php endif; ?>
                            <th width="5%" class="text-center">No</th>
                            <th>Nama Pelanggaran</th>
                            <th width="20%">Bagian</th>
                            <th width="10%" class="text-center">Poin</th>
                            <th width="15%" class="text-center">Kategori</th>
                            <?php if ($can_edit || $can_delete): ?>
                                <th width="15%" class="text-center">Aksi</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (mysqli_num_rows($result) == 0) {
                            echo '<tr><td colspan="' . $colspan . '" class="text-center py-4"><h5>Data tidak ditemukan.</h5><p class="text-muted">Coba reset filter atau ubah kata kunci pencarian.</p></td></tr>';
                        }
                        
                        $no = 1;
                        while ($row = mysqli_fetch_assoc($result)) {
                            $protected_ids = [1, 2, 3];
                            $is_protected = in_array($row['id'], $protected_ids);

                            $badge_class = match ($row['kategori']) {
                                'Ringan' => 'bg-info',
                                'Sedang' => 'bg-warning',
                                'Berat' => 'bg-danger',
                                'Sangat Berat' => 'bg-dark',
                                default => 'bg-secondary',
                            };
                        ?>
                        <tr>
                            <?php if ($can_edit || $can_delete): ?>
                                <td class="text-center align-middle">
                                    <?php if (!$is_protected) : ?>
                                        <input type="checkbox" name="ids[]" value="<?= $row['id']; ?>" class="row-checkbox">
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                            <td class="text-center align-middle"><?= $no++; ?></td>
                            <td class="align-middle">
                                <div class="fw-bold"><?= htmlspecialchars($row['nama_pelanggaran']); ?></div>
                                <div class="small text-muted">ID: <?= $row['id']; ?>
                                    <?php if ($is_protected) : ?>
                                        <span class="badge bg-secondary ms-1">Default</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="align-middle"><?= htmlspecialchars($row['bagian']); ?></td>
                            <td class="text-center align-middle fw-bold"><?= htmlspecialchars($row['poin']); ?></td>
                            <td class="text-center align-middle">
                                <span class="badge <?= $badge_class; ?>"><?= htmlspecialchars($row['kategori']); ?></span>
                            </td>
                            <?php if ($can_edit || $can_delete): ?>
                                <td class="text-center align-middle">
                                    <div class="btn-group" role="group">
                                        <?php if ($can_edit): ?>
                                            <a href="edit.php?id=<?= $row['id']; ?>" class="btn btn-sm btn-warning" title="Edit"><i class="fas fa-edit"></i></a>
                                        <?php endif; ?>
                                        <?php if ($can_delete && !$is_protected) : ?>
                                            <a href="#" onclick="showConfirmDelete('delete.php?id=<?= $row['id']; ?>')" class="btn btn-sm btn-danger" title="Hapus"><i class="fas fa-trash"></i></a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            <?php endif; ?>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </form>
</div>

<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Konfirmasi Penghapusan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="confirmMessage">Apakah Anda benar-benar yakin?</p>
                <p class="fw-bold text-danger">
                    PERINGATAN: Semua riwayat pelanggaran santri yang terkait juga akan terhapus secara permanen. Tindakan ini tidak dapat dibatalkan.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <a href="#" id="confirmDeleteButton" class="btn btn-danger">Yakin, Hapus Permanen</a>
            </div>
        </div>
    </div>
</div>

<?php 
mysqli_stmt_close($stmt);
require_once __DIR__ . '/../footer.php'; 
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const confirmModalElement = document.getElementById('confirmDeleteModal');
    const confirmModal = new bootstrap.Modal(confirmModalElement);
    const confirmMessage = document.getElementById('confirmMessage');
    const confirmBtn = document.getElementById('confirmDeleteButton');

    window.showConfirmDelete = function(deleteUrl) {
        confirmMessage.textContent = 'Apakah Anda benar-benar yakin ingin menghapus data ini?';
        confirmBtn.onclick = function() {
            window.location.href = deleteUrl;
        };
        confirmBtn.removeAttribute('href');
        confirmModal.show();
    }

    const selectAllCheckbox = document.getElementById('selectAll');
    const rowCheckboxes = document.querySelectorAll('.row-checkbox');
    const bulkActionForm = document.getElementById('bulkActionForm');
    const bulkEditBtn = document.getElementById('bulkEditBtn');
    const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
    const selectedCountInfo = document.getElementById('selected-count-info');
    const resetFilterBtn = document.getElementById('resetFilterBtn'); 

    const STORAGE_KEY = 'selectedPelanggaranIds'; 

    function getStoredIds() {
        const storedIdsJson = sessionStorage.getItem(STORAGE_KEY);
        return storedIdsJson ? new Set(JSON.parse(storedIdsJson)) : new Set();
    }
    
    function saveStoredIds(idsSet) {
        sessionStorage.setItem(STORAGE_KEY, JSON.stringify([...idsSet]));
    }

    function loadSelections() {
        const selectedIds = getStoredIds();
        rowCheckboxes.forEach(checkbox => {
            const checkboxId = parseInt(checkbox.value); 
            checkbox.checked = selectedIds.has(checkboxId);
        });
    }

    // ✅ REVISI KECIL: Ganti nama fungsi biar lebih jelas
    function handleSelectionChange() {
        const selectedIds = getStoredIds();
        // Loop SEMUA checkbox yang KELIATAN di halaman ini
        rowCheckboxes.forEach(checkbox => {
            const checkboxId = parseInt(checkbox.value);
            if (checkbox.checked) {
                selectedIds.add(checkboxId); // Tambahin ke Set
            } else {
                selectedIds.delete(checkboxId); // Hapus dari Set
            }
        });
        saveStoredIds(selectedIds); // Simpan Set yang udah diupdate
        toggleActionButtons();
    }
    
    function updateSelectAllState() {
        if (selectAllCheckbox) {
             const totalVisibleCheckboxes = rowCheckboxes.length;
             const checkedVisibleCount = document.querySelectorAll('.row-checkbox:checked').length;
             selectAllCheckbox.checked = totalVisibleCheckboxes > 0 && totalVisibleCheckboxes === checkedVisibleCount;
        }
    }

    function toggleActionButtons() {
        const selectedIds = getStoredIds();
        const checkedCount = selectedIds.size;
        
        if (bulkDeleteBtn) bulkDeleteBtn.disabled = checkedCount === 0;
        if (bulkEditBtn) bulkEditBtn.disabled = checkedCount === 0;

        if (selectedCountInfo) {
            if (checkedCount > 0) {
                selectedCountInfo.innerHTML = `<span class="badge bg-secondary">${checkedCount} data terpilih</span>`;
                selectedCountInfo.classList.remove('d-none');
            } else {
                selectedCountInfo.classList.add('d-none');
            }
        }
        updateSelectAllState(); 
    }

    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            rowCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            handleSelectionChange();
        });
    }

    rowCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', handleSelectionChange);
    });

    if (bulkEditBtn) {
        bulkEditBtn.addEventListener('click', function() {
            const selectedIds = getStoredIds();
            if (selectedIds.size > 0) {
                 bulkActionForm.innerHTML = ''; 
                 selectedIds.forEach(id => {
                    const hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = 'ids[]';
                    hiddenInput.value = id;
                    bulkActionForm.appendChild(hiddenInput);
                 });
                 bulkActionForm.action = 'bulk-edit.php';
                 bulkActionForm.submit();
            } else {
                alert('Pilih minimal satu data untuk diedit.');
            }
        });
    }

    if (bulkActionForm) {
        bulkActionForm.addEventListener('submit', function(e) {
            e.preventDefault(); 
            bulkActionForm.action = 'delete.php'; 

            const selectedIds = getStoredIds();
            if (selectedIds.size > 0) {
                 bulkActionForm.innerHTML = '';
                 selectedIds.forEach(id => {
                    const hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = 'ids[]';
                    hiddenInput.value = id;
                    bulkActionForm.appendChild(hiddenInput);
                 });

                confirmMessage.textContent = `Apakah Anda yakin ingin menghapus ${selectedIds.size} data terpilih?`;
                confirmBtn.onclick = function() {
                    bulkActionForm.submit();
                };
                confirmBtn.removeAttribute('href');
                confirmModal.show();
            }
        });
    }
    
    if (resetFilterBtn) {
        resetFilterBtn.addEventListener('click', function(e) {
            sessionStorage.removeItem(STORAGE_KEY);
        });
    }

    // --- Inisialisasi Saat Halaman Load ---
    loadSelections();
    toggleActionButtons();

    // --- Logika Auto Load Filter (Tetap Sama) ---
    const filterForm = document.getElementById('filterForm');
    const searchInput = document.getElementById('search');
    const bagianSelect = document.getElementById('bagian');
    const kategoriSelect = document.getElementById('kategori');
    let debounceTimer; 

    function submitFilterForm() {
        filterForm.submit();
    }

    function debounceSubmit() {
        clearTimeout(debounceTimer); 
        debounceTimer = setTimeout(submitFilterForm, 500); 
    }

    if (bagianSelect) {
        bagianSelect.addEventListener('change', submitFilterForm);
    }
    if (kategoriSelect) {
        kategoriSelect.addEventListener('change', submitFilterForm);
    }
    if (searchInput) {
        searchInput.addEventListener('input', debounceSubmit);
    }
});
</script>