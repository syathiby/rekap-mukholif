<?php 
require_once __DIR__ . '/../header.php';
guard('jenis_pelanggaran_view');

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
$query .= " ORDER BY nama_pelanggaran ASC";

// --- EKSEKUSI QUERY ---
$stmt = mysqli_prepare($conn, $query);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Hitung colspan dinamis untuk tabel
$colspan = 5; // Kolom dasar: No, Nama, Bagian, Poin, Kategori
if ($can_delete) $colspan++; // Tambah 1 untuk checkbox
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
        .card-footer {
            flex-direction: column;
            gap: 0.75rem;
            align-items: stretch !important;
            padding: 1rem;
        }
        .card-footer .btn {
            width: 100%;
        }
        .card-footer .text-muted {
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

    <?php if (isset($_SESSION['success_message'])) : ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i> <?= $_SESSION['success_message']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])) : ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-times-circle me-2"></i> <?= $_SESSION['error_message']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>
    
    <div class="filter-card">
        <form method="GET" action="">
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
                    <button type="submit" class="btn btn-primary w-100 me-2"><i class="fas fa-search"></i> Cari</button>
                    <a href="index.php" class="btn btn-outline-secondary" title="Reset Filter"><i class="fas fa-sync-alt"></i></a>
                </div>
            </div>
        </form>
    </div>

    <form method="POST" action="delete.php" id="bulkDeleteForm">
        <div class="table-container">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead>
                        <tr>
                            <?php if ($can_delete): ?>
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
                            <?php if ($can_delete): ?>
                                <td class="text-center align-middle">
                                    <?php if (!$is_protected) : ?>
                                        <input type="checkbox" name="ids[]" value="<?= $row['id']; ?>" class="row-checkbox">
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                            <td class="text-center align-middle"><?= $no++; ?></td>
                            <td class="align-middle">
                                <div class="fw-bold"><?= htmlspecialchars($row['nama_pelanggaran']); ?></div>
                                <div class="small text-muted">
                                    ID: <?= $row['id']; ?>
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
            
            <div class="card-footer d-flex justify-content-between align-items-center">
                <?php if ($can_delete): ?>
                    <button type="submit" class="btn btn-danger" id="bulkDeleteBtn" disabled>
                        <i class="fas fa-trash-alt me-1"></i> Hapus Terpilih
                    </button>
                <?php else: ?>
                    <div></div> <?php endif; ?>
                <div class="text-muted">
                    Total Data: <strong><?= mysqli_num_rows($result); ?></strong>
                </div>
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

    // === JS untuk Bulk Delete (dibuat lebih aman) ===
    const selectAllCheckbox = document.getElementById('selectAll');
    const rowCheckboxes = document.querySelectorAll('.row-checkbox');
    const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
    const bulkDeleteForm = document.getElementById('bulkDeleteForm');

    function toggleDeleteButton() {
        if (!bulkDeleteBtn) return; // Jika tombol tidak ada, jangan lakukan apa-apa
        const checkedCount = document.querySelectorAll('.row-checkbox:checked').length;
        bulkDeleteBtn.disabled = checkedCount === 0;
    }

    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            rowCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            toggleDeleteButton();
        });
    }

    rowCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            if (selectAllCheckbox) {
                if (!this.checked) {
                    selectAllCheckbox.checked = false;
                }
                const totalCheckboxes = rowCheckboxes.length;
                const checkedCount = document.querySelectorAll('.row-checkbox:checked').length;
                if (totalCheckboxes > 0 && totalCheckboxes === checkedCount) {
                    selectAllCheckbox.checked = true;
                }
            }
            toggleDeleteButton();
        });
    });

    if (bulkDeleteForm) {
        bulkDeleteForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const checkedCount = document.querySelectorAll('.row-checkbox:checked').length;
            if (checkedCount > 0) {
                confirmMessage.textContent = `Apakah Anda yakin ingin menghapus ${checkedCount} data terpilih?`;
                confirmBtn.onclick = function() {
                    bulkDeleteForm.submit();
                };
                confirmBtn.removeAttribute('href');
                confirmModal.show();
            }
        });
    }
    
    toggleDeleteButton(); // Panggil di awal untuk set state tombol
});
</script>