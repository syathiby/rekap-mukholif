<?php 
require_once __DIR__ . '/../header.php';
guard('santri_view'); 

// ✅ FIX: Ambil setiap parameter filter secara terpisah
$nama_search = $_GET['nama'] ?? '';
$kelas_search = $_GET['kelas'] ?? '';
$kamar_search = $_GET['kamar'] ?? '';

// Persiapan untuk query yang dinamis dan aman
$where_clauses = [];
$params = [];
$types = '';

if (!empty($nama_search)) {
    $where_clauses[] = "nama LIKE ?";
    $params[] = "%" . $nama_search . "%";
    $types .= 's';
}
if (!empty($kelas_search)) {
    $where_clauses[] = "kelas LIKE ?";
    $params[] = "%" . $kelas_search . "%";
    $types .= 's';
}
if (!empty($kamar_search)) {
    $where_clauses[] = "kamar LIKE ?";
    $params[] = "%" . $kamar_search . "%";
    $types .= 's';
}

$where_sql = '';
if (!empty($where_clauses)) {
    $where_sql = " WHERE " . implode(" AND ", $where_clauses);
}
?>

<style>
    /* CSS khusus halaman ini */
    :root {
        --primary-color: #3498db;
        --secondary-color: #2c3e50;
        --accent-color: #e74c3c;
    }
    .page-title-card {
        background: linear-gradient(135deg, var(--secondary-color), var(--primary-color));
        color: white;
        padding: 1.5rem;
        border-radius: 8px;
        margin-bottom: 1.5rem;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }
    .filter-card {
        background-color: #fff;
        padding: 1.5rem;
        border-radius: 8px;
        margin-bottom: 1.5rem;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    }
    .table-container {
        background-color: white;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        overflow: hidden;
    }
    .table thead {
        background-color: var(--secondary-color);
        color: white;
    }
    .action-cell {
        width: 120px;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .page-title-card h2 { font-size: 1.25rem; }
        .toolbar { 
            flex-direction: column;
            align-items: stretch;
            gap: 1rem;
        }
        .toolbar .btn-group {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .toolbar .btn-group .btn {
            flex-grow: 1;
        }
    }
</style>

<div class="container mt-4 mb-5">
    <div class="page-title-card">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="mb-0"><i class="fas fa-users me-2"></i>Data Santri</h2>
            <div>
                <span class="badge bg-light text-dark fs-6">
                    <i class="fas fa-database me-1"></i>
                    <?php 
                    // Query untuk menghitung total santri dengan filter
                    $count_query = "SELECT COUNT(*) as total FROM santri" . $where_sql;
                    $stmt_count = mysqli_prepare($conn, $count_query);
                    if (!empty($params)) {
                        mysqli_stmt_bind_param($stmt_count, $types, ...$params);
                    }
                    mysqli_stmt_execute($stmt_count);
                    $count_result = mysqli_stmt_get_result($stmt_count);
                    $total = mysqli_fetch_assoc($count_result)['total'];
                    echo $total . " Santri";
                    mysqli_stmt_close($stmt_count);
                    ?>
                </span>
            </div>
        </div>
    </div>
    
    <!-- ✅ Filter Section Baru -->
    <div class="filter-card">
        <form class="row g-3 align-items-center" method="GET" action="">
            <div class="col-md-4">
                <input class="form-control" type="search" name="nama" placeholder="Cari Nama Santri..." value="<?= htmlspecialchars($nama_search) ?>">
            </div>
            <div class="col-md-3">
                <input class="form-control" type="search" name="kelas" placeholder="Filter Kelas..." value="<?= htmlspecialchars($kelas_search) ?>">
            </div>
            <div class="col-md-3">
                <input class="form-control" type="search" name="kamar" placeholder="Filter Kamar..." value="<?= htmlspecialchars($kamar_search) ?>">
            </div>
            <div class="col-md-2 d-flex">
                <button class="btn btn-primary w-100 me-2" type="submit"><i class="fas fa-filter me-1"></i> Cari</button>
                <a href="?" class="btn btn-outline-secondary" title="Reset Filter"><i class="fas fa-times"></i></a>
            </div>
        </form>
    </div>

    <!-- Toolbar Atas Tabel -->
    <div class="d-flex justify-content-start mb-3 toolbar">
        <div class="btn-group">
            <a href="create.php" class="btn btn-success"><i class="fas fa-user-plus me-1"></i> Tambah Santri</a>
            <a href="bulk-create.php" class="btn btn-info"><i class="fas fa-file-import me-1"></i> Bulk Input</a>
            <a href="bulk-edit.php" class="btn btn-warning"><i class="fas fa-pen-to-square me-1"></i> Bulk Edit</a>
            <button type="button" class="btn btn-danger" onclick="confirmBulkDelete()"><i class="fas fa-user-minus me-1"></i> Hapus Terpilih</button>
        </div>
    </div>

    <!-- Tabel Data Santri -->
    <div class="table-container">
        <form id="form-bulk-delete" method="post" action="bulk-delete.php">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th class="text-center" width="50px"><input type="checkbox" id="select-all"></th>
                            <th>Nama Santri</th>
                            <th>Kelas</th>
                            <th>Kamar</th>
                            <th class="action-cell">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $query = "SELECT * FROM santri" . $where_sql . " ORDER BY nama ASC";
                        $stmt = mysqli_prepare($conn, $query);
                        if (!empty($params)) {
                            mysqli_stmt_bind_param($stmt, $types, ...$params);
                        }
                        mysqli_stmt_execute($stmt);
                        $result = mysqli_stmt_get_result($stmt);
                        
                        if (mysqli_num_rows($result) > 0) {
                            while ($row = mysqli_fetch_assoc($result)) {
                        ?>
                        <tr>
                            <td class="text-center"><input type="checkbox" name="ids[]" value="<?= $row['id'] ?>"></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar me-3">
                                        <div class="rounded-circle text-white d-flex align-items-center justify-content-center" 
                                             style="width: 38px; height: 38px; background-color: #<?= substr(md5($row['id']), 0, 6) ?>; font-weight: 500;">
                                            <?= strtoupper(substr($row['nama'], 0, 1)) ?>
                                        </div>
                                    </div>
                                    <div>
                                        <strong><?= htmlspecialchars($row['nama']) ?></strong>
                                        <div class="text-muted small">ID: <?= $row['id'] ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><span class="badge bg-info text-dark"><?= htmlspecialchars($row['kelas']) ?></span></td>
                            <td>
                                <span class="badge bg-light text-dark">
                                    <i class="fas fa-door-open me-1"></i>
                                    <?= htmlspecialchars($row['kamar']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="d-flex">
                                    <a href="edit.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-warning me-2" title="Edit"><i class="fas fa-edit"></i></a>
                                    <a href="#" onclick="showConfirmDelete('delete.php?id=<?= $row['id'] ?>')" class="btn btn-sm btn-danger" title="Hapus"><i class="fas fa-trash"></i></a>
                                </div>
                            </td>
                        </tr>
                        <?php 
                            }
                        } else {
                            echo '<tr><td colspan="5" class="text-center py-4"><h5>Tidak ada data santri ditemukan.</h5><p class="text-muted">Coba ubah atau reset filter pencarian Anda.</p></td></tr>';
                        }
                        mysqli_stmt_close($stmt);
                        ?>
                    </tbody>
                </table>
            </div>
        </form>
    </div>
</div>

<!-- Modal Universal untuk Konfirmasi -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="confirmModalLabel">Konfirmasi</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="confirmModalBody"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-danger" id="confirmModalButton">Yakin</button>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const confirmModalEl = document.getElementById('confirmModal');
    const confirmModal = new bootstrap.Modal(confirmModalEl);
    const confirmModalBody = document.getElementById('confirmModalBody');
    let confirmModalButton = document.getElementById('confirmModalButton');

    // Fungsi "Pilih Semua"
    document.getElementById("select-all").onclick = function() {
        document.querySelectorAll('input[name="ids[]"]').forEach(checkbox => {
            checkbox.checked = this.checked;
        });
    }

    // Modal konfirmasi hapus SATU santri
    window.showConfirmDelete = function(deleteUrl) {
        confirmModalBody.textContent = 'Apakah Anda yakin ingin menghapus santri ini?';
        
        const newBtn = confirmModalButton.cloneNode(true);
        confirmModalButton.parentNode.replaceChild(newBtn, confirmModalButton);
        confirmModalButton = newBtn;

        confirmModalButton.style.display = 'inline-block';
        confirmModalButton.onclick = () => window.location.href = deleteUrl;
        
        confirmModal.show();
    }

    // Modal konfirmasi hapus BANYAK santri
    window.confirmBulkDelete = function() {
        const checkedBoxes = document.querySelectorAll('input[name="ids[]"]:checked');
        
        if (checkedBoxes.length === 0) {
            confirmModalBody.textContent = 'Silakan pilih santri yang akan dihapus terlebih dahulu.';
            confirmModalButton.style.display = 'none';
        } else {
            confirmModalBody.textContent = `Anda yakin ingin menghapus ${checkedBoxes.length} santri terpilih?`;
            confirmModalButton.style.display = 'inline-block';
            
            const newBtn = confirmModalButton.cloneNode(true);
            confirmModalButton.parentNode.replaceChild(newBtn, confirmModalButton);
            confirmModalButton = newBtn;
            
            confirmModalButton.onclick = () => document.getElementById('form-bulk-delete').submit();
        }
        confirmModal.show();
    }
});
</script>