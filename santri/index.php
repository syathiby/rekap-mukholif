<?php 
require_once __DIR__ . '/../header.php';
guard('santri_view'); 

// Ambil setiap parameter filter secara terpisah
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

// === PENGECEKAN IZIN DI AWAL BIAR RAPI ===
$can_create = has_permission('santri_create');
$can_edit = has_permission('santri_edit');
$can_delete = has_permission('santri_delete');

// Hitung colspan dinamis untuk tabel
$colspan = 3; // Kolom dasar: Nama, Kelas, Kamar
if ($can_delete) $colspan++; // Tambah 1 jika bisa hapus (untuk checkbox)
if ($can_edit || $can_delete) $colspan++; // Tambah 1 jika bisa edit atau hapus (untuk kolom Aksi)
?>

<style>
    /* CSS tidak ada perubahan, tetap sama */
    :root {
        --primary-color: #3498db;
        --secondary-color: #2c3e50;
        --accent-color: #e74c3c;
    }
    .page-title-card {
        background: linear-gradient(135deg, var(--secondary-color), var(--primary-color));
        color: white;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }
    .filter-card {
        background-color: #fff;
        border-radius: 8px;
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
    .btn-info-circle {
        width: 30px;
        height: 30px;
        padding: 0;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        line-height: 1;
        font-size: 0.9rem;
        background-color: rgba(255, 255, 255, 0.2);
        color: white;
        border: 1px solid rgba(255, 255, 255, 0.5);
        transition: background-color 0.2s;
    }
    .btn-info-circle:hover {
        background-color: rgba(255, 255, 255, 0.3);
        color: white;
    }
    #infoModal .modal-body {
        max-height: 60vh;
        overflow-y: auto;
    }
    @media (max-width: 768px) {
        .page-title-card h2 { font-size: 1.25rem; }
        .toolbar {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
        }
        .filter-buttons {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 0.5rem;
        }
    }
</style>

<div class="container mt-4 mb-5">
    <div class="page-title-card p-3 p-md-4 mb-4">
        <div class="d-flex flex-column flex-md-row justify-content-md-between align-items-md-center">
            <div class="d-flex align-items-center mb-2 mb-md-0">
                <h2 class="mb-0 me-2"><i class="fas fa-users me-2"></i>Data Santri</h2>
                <button type="button" class="btn btn-info-circle" data-bs-toggle="modal" data-bs-target="#infoModal" title="Informasi Penting">
                    <i class="fas fa-info"></i>
                </button>
            </div>
            <div>
                <span class="badge bg-light text-dark fs-6">
                    <i class="fas fa-database me-1"></i>
                    <?php 
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
    
    <div class="filter-card p-3 p-md-4 mb-4">
        <form class="row g-3" method="GET" action="">
            <div class="col-12 col-md-4">
                <input class="form-control" type="search" name="nama" placeholder="Cari Nama Santri..." value="<?= htmlspecialchars($nama_search) ?>">
            </div>
            <div class="col-12 col-md-3">
                <input class="form-control" type="search" name="kelas" placeholder="Filter Kelas..." value="<?= htmlspecialchars($kelas_search) ?>">
            </div>
            <div class="col-12 col-md-3">
                <input class="form-control" type="search" name="kamar" placeholder="Filter Kamar..." value="<?= htmlspecialchars($kamar_search) ?>">
            </div>
            <div class="col-12 col-md-2 filter-buttons">
                <button class="btn btn-primary" type="submit"><i class="fas fa-filter me-1"></i> Cari</button>
                <a href="?" class="btn btn-outline-secondary" title="Reset Filter"><i class="fas fa-times"></i></a>
            </div>
        </form>
    </div>

    <div class="mb-3 toolbar">
        <?php if ($can_create): ?>
            <a href="create.php" class="btn btn-success"><i class="fas fa-user-plus me-1"></i> Tambah Santri</a>
            <a href="bulk-create.php" class="btn btn-info"><i class="fas fa-file-import me-1"></i> Bulk Input</a>
        <?php endif; ?>
        <?php if ($can_edit): ?>
            <a href="bulk-edit.php" class="btn btn-warning"><i class="fas fa-pen-to-square me-1"></i> Bulk Edit</a>
        <?php endif; ?>
        <?php if ($can_delete): ?>
            <button type="button" class="btn btn-danger" onclick="confirmBulkDelete()"><i class="fas fa-user-minus me-1"></i> Hapus Terpilih</button>
        <?php endif; ?>
    </div>

    <div class="table-container">
        <form id="form-bulk-delete" method="post" action="bulk-delete.php">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <?php if ($can_delete): ?>
                                <th class="text-center" width="50px"><input type="checkbox" id="select-all"></th>
                            <?php endif; ?>
                            <th>Nama Santri</th>
                            <th>Kelas</th>
                            <th>Kamar</th>
                            <?php if ($can_edit || $can_delete): ?>
                                <th class="action-cell">Aksi</th>
                            <?php endif; ?>
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
                            <?php if ($can_delete): ?>
                                <td class="text-center"><input type="checkbox" name="ids[]" value="<?= $row['id'] ?>"></td>
                            <?php endif; ?>
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
                            <?php if ($can_edit || $can_delete): ?>
                                <td>
                                    <div class="d-flex">
                                        <?php if ($can_edit): ?>
                                            <a href="edit.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-warning me-2" title="Edit"><i class="fas fa-edit"></i></a>
                                        <?php endif; ?>
                                        <?php if ($can_delete): ?>
                                            <a href="#" onclick="showConfirmDelete('delete.php?id=<?= $row['id'] ?>')" class="btn btn-sm btn-danger" title="Hapus"><i class="fas fa-trash"></i></a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            <?php endif; ?>
                        </tr>
                        <?php 
                            }
                        } else {
                            echo '<tr><td colspan="' . $colspan . '" class="text-center py-4"><h5>Tidak ada data santri ditemukan.</h5><p class="text-muted">Coba ubah atau reset filter pencarian Anda.</p></td></tr>';
                        }
                        mysqli_stmt_close($stmt);
                        ?>
                    </tbody>
                </table>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="infoModal" tabindex="-1" aria-labelledby="infoModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="infoModalLabel"><i class="fas fa-exclamation-triangle text-warning me-2"></i>Informasi Penting</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="fw-bold">Sebelum mengubah (update) atau menghapus data santri, harap perhatikan hal-hal berikut:</p>
        <ul>
            <li><strong>Pastikan Data Telah Diarsipkan:</strong> Proses pengarsipan adalah snapshot data pada periode tertentu. Jika Anda menghapus santri sebelum periode saat ini diarsip, maka data pelanggaran santri tersebut tidak akan terekam dalam arsip periode ini.</li>
            <li><strong>Data yang Dihapus Bersifat Permanen:</strong> Data santri yang dihapus tidak dapat dikembalikan lagi. Riwayat pelanggarannya di periode aktif juga akan ikut terhapus.</li>
            <li><strong>Update Data:</strong> Mengubah data seperti nama, kelas, atau kamar akan langsung berlaku dan memengaruhi laporan-laporan yang sedang berjalan.</li>
        </ul>
        <p>Tindakan yang bijak adalah menjalankan proses "BUAT ARSIP BARU" di menu Manajemen Arsip secara berkala (misalnya setiap akhir semester) untuk mengamankan semua data sebelum melakukan perubahan besar.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Saya Mengerti</button>
      </div>
    </div>
  </div>
</div>

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

    // Cek jika elemen #select-all ada sebelum menambahkan event listener
    const selectAllCheckbox = document.getElementById("select-all");
    if (selectAllCheckbox) {
        selectAllCheckbox.onclick = function() {
            document.querySelectorAll('input[name="ids[]"]').forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        }
    }

    window.showConfirmDelete = function(deleteUrl) {
        confirmModalBody.textContent = 'Apakah Anda yakin ingin menghapus santri ini?';
        const newBtn = confirmModalButton.cloneNode(true);
        confirmModalButton.parentNode.replaceChild(newBtn, confirmModalButton);
        confirmModalButton = newBtn;
        confirmModalButton.style.display = 'inline-block';
        confirmModalButton.onclick = () => window.location.href = deleteUrl;
        confirmModal.show();
    }

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

    const infoModalEl = document.getElementById('infoModal');
    if (infoModalEl) {
        infoModalEl.addEventListener('hide.bs.modal', function () {
            if (document.activeElement && infoModalEl.contains(document.activeElement)) {
                document.activeElement.blur();
            }
        });
    }
});
</script>