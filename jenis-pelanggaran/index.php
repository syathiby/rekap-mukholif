<?php 
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../header.php'; 
?>

<div class="container-fluid mt-4">
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
                                <a href="edit.php?id=<?= $row['id']; ?>" class="btn btn-warning btn-sm" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="delete.php?id=<?= $row['id']; ?>" class="btn btn-danger btn-sm" title="Hapus" onclick="return confirm('Yakin ingin menghapus data ini?')">
                                    <i class="fas fa-trash-alt"></i>
                                </a>
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