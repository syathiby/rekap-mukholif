<?php
require_once '../../init.php';
guard('jenis_reward_edit'); 

// Cek kalo gak ada data yang dipilih
if (!isset($_POST['ids']) || empty($_POST['ids'])) {
    header("Location: index.php");
    exit;
}

require_once '../../header.php';

$ids = implode(',', array_map('intval', $_POST['ids']));
$query = "SELECT * FROM jenis_reward WHERE id IN ($ids)";
$result = mysqli_query($conn, $query);
?>

<div class="content-wrapper" style="padding: 20px;">
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white py-3">
            <h5 class="mb-0"><i class="fas fa-tasks me-2"></i>Bulk Edit Reward</h5>
            <small>Ubah banyak data sekaligus dalam satu layar.</small>
        </div>
        <div class="card-body">
            <form action="process.php" method="POST">
                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th style="width: 250px;">Nama Reward</th>
                                <th style="width: 150px;">Poin Reward</th>
                                <th>Deskripsi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = mysqli_fetch_assoc($result)): ?>
                            <tr>
                                <input type="hidden" name="ids[]" value="<?= $row['id'] ?>">
                                
                                <td>
                                    <input type="text" name="nama_reward[<?= $row['id'] ?>]" class="form-control" value="<?= htmlspecialchars($row['nama_reward']) ?>" required>
                                </td>
                                <td>
                                    <div class="input-group">
                                        <input type="number" name="poin_reward[<?= $row['id'] ?>]" class="form-control text-center" value="<?= $row['poin_reward'] ?>" required>
                                    </div>
                                </td>
                                <td>
                                    <input type="text" name="deskripsi[<?= $row['id'] ?>]" class="form-control" value="<?= htmlspecialchars($row['deskripsi']) ?>">
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-3 d-flex justify-content-end gap-2">
                    <a href="index.php" class="btn btn-secondary">Batal</a>
                    <button type="submit" name="bulk_update" class="btn btn-primary px-4 fw-bold">Simpan Semua Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../../footer.php'; ?>