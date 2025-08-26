<?php 
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../header.php';
checkRole(['admin']);

?>

<style>
@media (max-width: 576px) {
    .card-header h3 {
        font-size: 1.1rem;
    }
    .card-header .btn {
        font-size: 0.8rem;
        padding: 0.3rem 0.5rem;
    }
    .table th, .table td {
        font-size: 0.85rem;
        padding: 0.4rem;
    }
    .thead-dark th {
        font-size: 0.85rem;
        padding: 0.4rem 0.3rem;
    }
    .card-body {
        padding: 0.75rem 0.5rem;
    }
    .card-header {
        flex-direction: column;
        align-items: flex-start !important;
        gap: 0.5rem;
    }
    .card-header > div {
        width: 100%;
        display: flex;
        justify-content: flex-end;
        gap: 0.5rem;
    }
    .table td .btn {
        padding: 0.25rem 0.4rem;
        font-size: 0.75rem;
    }
    .table td .d-flex {
        gap: 0.3rem;
        justify-content: center;
        flex-wrap: wrap;
    }
}

.table td .btn + .btn {
    margin-left: 0.4rem;
}

.table-responsive .table {
    min-width: 480px;
}
</style>

<div class="container-fluid mt-4 px-2 px-sm-4">
    <div class="card shadow">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h3 class="mb-0"><i class="fas fa-exclamation-triangle mr-2"></i>Data Jenis Pelanggaran</h3>
            <div>
                <a href="create.php" class="btn btn-light btn-sm">
                    <i class="fas fa-plus-circle mr-1"></i> Tambah Baru
                </a>
                <a href="../" class="btn btn-light btn-sm">
                    <i class="fas fa-arrow-left mr-1"></i> Kembali
                </a>
            </div>
        </div>
        
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover table-bordered">
                    <thead class="thead-dark">
                        <tr>
                            <th width="5%" class="text-center">No</th>
                            <th>Nama Pelanggaran</th>
                            <th width="20%" class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $query = "SELECT * FROM jenis_pelanggaran ORDER BY id DESC";
                        $result = mysqli_query($conn, $query);
                        
                        if (!$result) {
                            die("Query error: " . mysqli_error($conn));
                        }
                        
                        if (mysqli_num_rows($result) == 0) {
                            echo '<tr><td colspan="3" class="text-center text-muted">Tidak ada data ditemukan</td></tr>';
                        }
                        
                        $no = 1;
                        
                        while ($row = mysqli_fetch_assoc($result)) {
                        ?>
                        <tr>
                            <td class="text-center"><?= $no++; ?></td>
                            <td><?= htmlspecialchars($row['nama_pelanggaran']); ?></td>
                           <td class="text-center">
                            <div class="d-flex justify-content-center gap-1 flex-wrap">
                                <a href="edit.php?id=<?= $row['id']; ?>" class="btn btn-warning btn-xs p-1 px-2" title="Edit">
                                    <i class="fas fa-edit fa-sm"></i>
                                </a>
                                <a href="delete.php?id=<?= $row['id']; ?>" class="btn btn-danger btn-xs p-1 px-2" title="Hapus" onclick="return confirm('Yakin ingin menghapus data ini?')">
                                    <i class="fas fa-trash-alt fa-sm"></i>
                                </a>
                            </div>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="card-footer text-muted">
            Total Data: <strong><?= mysqli_num_rows($result); ?></strong>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../footer.php'; ?>