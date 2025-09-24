<?php 
require_once __DIR__ . '/../header.php';
guard('jenis_pelanggaran_view');

// --- AMBIL DATA UNTUK FILTER ---
$bagian_list_query = "SELECT DISTINCT bagian FROM jenis_pelanggaran ORDER BY bagian ASC";
$bagian_list_result = mysqli_query($conn, $bagian_list_query);

// --- LOGIKA FILTER (SUDAH BENAR, TIDAK DIUBAH) ---
$filter_bagian = $_GET['bagian'] ?? '';
$filter_kategori = $_GET['kategori'] ?? '';
$query = "SELECT * FROM jenis_pelanggaran";
$where_clauses = [];
$params = [];
$types = '';
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

// --- EKSEKUSI QUERY (SUDAH BENAR, TIDAK DIUBAH) ---
$stmt = mysqli_prepare($conn, $query);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
?>

<style>
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

    /* ✅ PERBAIKAN: CSS untuk tampilan mobile (Responsive) */
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
        .filter-card .col-md, .filter-card .col-md-auto {
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
        <div class="btn-group">
            <a href="create.php" class="btn btn-success"><i class="fas fa-plus-circle me-1"></i> Tambah Baru</a>
        </div>
    </div>

    <?php if (isset($_SESSION['success_message'])) : ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i> <?= $_SESSION['success_message']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    
    <div class="filter-card">
        <form method="GET" action="">
            <div class="row g-3 align-items-center">
                <div class="col-md">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-layer-group"></i></span>
                        <select name="bagian" class="form-select" onchange="this.form.submit()">
                            <option value="">-- Filter Bagian --</option>
                            <?php mysqli_data_seek($bagian_list_result, 0); ?>
                            <?php while ($bagian_row = mysqli_fetch_assoc($bagian_list_result)) : ?>
                                <option value="<?= htmlspecialchars($bagian_row['bagian']); ?>" <?= ($filter_bagian == $bagian_row['bagian']) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($bagian_row['bagian']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-tags"></i></span>
                        <select name="kategori" class="form-select" onchange="this.form.submit()">
                            <option value="">-- Filter Kategori --</option>
                            <option value="Ringan" <?= ($filter_kategori == 'Ringan') ? 'selected' : '' ?>>Ringan</option>
                            <option value="Sedang" <?= ($filter_kategori == 'Sedang') ? 'selected' : '' ?>>Sedang</option>
                            <option value="Berat" <?= ($filter_kategori == 'Berat') ? 'selected' : '' ?>>Berat</option>
                            <option value="Sangat Berat" <?= ($filter_kategori == 'Sangat Berat') ? 'selected' : '' ?>>Sangat Berat</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-auto">
                    <a href="index.php" class="btn btn-outline-secondary w-100"><i class="fas fa-sync-alt me-1"></i> Reset</a>
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
                            <th width="3%" class="text-center"><input type="checkbox" id="selectAll"></th>
                            <th width="5%" class="text-center">No</th>
                            <th>Nama Pelanggaran</th>
                            <th width="20%">Bagian</th>
                            <th width="10%" class="text-center">Poin</th>
                            <th width="15%" class="text-center">Kategori</th>
                            <th width="15%" class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (mysqli_num_rows($result) == 0) {
                            echo '<tr><td colspan="7" class="text-center py-4"><h5>Data tidak ditemukan.</h5><p class="text-muted">Coba reset filter di atas.</p></td></tr>';
                        }
                        
                        $no = 1;
                        while ($row = mysqli_fetch_assoc($result)) {
                            $badge_class = match ($row['kategori']) {
                                'Ringan' => 'bg-info',
                                'Sedang' => 'bg-warning',
                                'Berat' => 'bg-danger',
                                'Sangat Berat' => 'bg-dark',
                                default => 'bg-secondary',
                            };
                        ?>
                        <tr>
                            <td class="text-center align-middle">
                                <input type="checkbox" name="ids[]" value="<?= $row['id']; ?>" class="row-checkbox">
                            </td>
                            <td class="text-center align-middle"><?= $no++; ?></td>
                            <td class="align-middle">
                                <div class="fw-bold"><?= htmlspecialchars($row['nama_pelanggaran']); ?></div>
                                <div class="small text-muted">ID: <?= $row['id']; ?></div>
                            </td>
                            <td class="align-middle"><?= htmlspecialchars($row['bagian']); ?></td>
                            <td class="text-center align-middle fw-bold"><?= htmlspecialchars($row['poin']); ?></td>
                            <td class="text-center align-middle">
                                <span class="badge <?= $badge_class; ?>"><?= htmlspecialchars($row['kategori']); ?></span>
                            </td>
                            <td class="text-center align-middle">
                                <div class="btn-group" role="group">
                                    <a href="edit.php?id=<?= $row['id']; ?>" class="btn btn-sm btn-warning" title="Edit"><i class="fas fa-edit"></i></a>
                                    <a href="#" onclick="showConfirmDelete('delete.php?id=<?= $row['id']; ?>')" class="btn btn-sm btn-danger" title="Hapus"><i class="fas fa-trash"></i></a>
                                </div>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            
            <div class="card-footer d-flex justify-content-between align-items-center">
                <button type="submit" class="btn btn-danger" id="bulkDeleteBtn" disabled>
                    <i class="fas fa-trash-alt me-1"></i> Hapus Terpilih
                </button>
                <div class="text-muted">
                    Total Data: <strong><?= mysqli_num_rows($result); ?></strong>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Modal dan JS di bawah sini TIDAK ADA PERUBAHAN, sudah benar -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Konfirmasi Tindakan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">Apakah Anda yakin ingin menghapus data ini?</div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <a href="#" id="confirmModalButton" class="btn btn-danger">Yakin, Hapus</a>
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
    // JS Untuk modal hapus satu per satu
    const confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));
    const confirmBtn = document.getElementById('confirmModalButton');

    window.showConfirmDelete = function(deleteUrl) {
        confirmBtn.href = deleteUrl;
        confirmModal.show();
    }

    // JS untuk Bulk Delete
    const selectAllCheckbox = document.getElementById('selectAll');
    const rowCheckboxes = document.querySelectorAll('.row-checkbox');
    const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
    const bulkDeleteForm = document.getElementById('bulkDeleteForm');

    function toggleDeleteButton() {
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
            if (!this.checked) {
                selectAllCheckbox.checked = false;
            }
            const totalCheckboxes = rowCheckboxes.length;
            const checkedCount = document.querySelectorAll('.row-checkbox:checked').length;
            if (totalCheckboxes === checkedCount) {
                selectAllCheckbox.checked = true;
            }
            toggleDeleteButton();
        });
    });

    if (bulkDeleteForm) {
        bulkDeleteForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const checkedCount = document.querySelectorAll('.row-checkbox:checked').length;
            if (checkedCount > 0) {
                if (confirm(`Apakah Anda yakin ingin menghapus ${checkedCount} data terpilih?`)) {
                    this.submit();
                }
            }
        });
    }
});
</script>