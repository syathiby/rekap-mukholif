<?php
require_once '../../init.php';

guard('jenis_reward_edit'); 

require_once '../../header.php';

$id = (int)$_GET['id'];
$data = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM jenis_reward WHERE id=$id"));

if(!$data) {
    echo "<script>alert('Data tidak ditemukan'); window.location='index.php';</script>";
    exit;
}
?>

<div class="content-wrapper" style="padding: 20px;">
    <div class="card shadow-sm border-0 rounded-3 mx-auto" style="max-width: 700px;">
        <div class="card-header bg-warning text-white py-3">
            <h5 class="mb-0 fw-bold"><i class="fas fa-edit me-2"></i>Edit Data Reward</h5>
        </div>
        <div class="card-body p-4">
            <form action="process.php" method="POST">
                <input type="hidden" name="id" value="<?= $data['id'] ?>">
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Nama Reward</label>
                    <input type="text" name="nama_reward" class="form-control" value="<?= htmlspecialchars($data['nama_reward']) ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Poin Reward (Pengurang)</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="fas fa-star text-warning"></i></span>
                        <input type="number" name="poin_reward" class="form-control" value="<?= $data['poin_reward'] ?>" required>
                        <span class="input-group-text">Poin</span>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold">Deskripsi</label>
                    <textarea name="deskripsi" class="form-control" rows="3"><?= htmlspecialchars($data['deskripsi']) ?></textarea>
                </div>

                <div class="d-flex justify-content-between">
                    <a href="index.php" class="btn btn-secondary">Batal</a>
                    <button type="submit" name="edit_jenis" class="btn btn-warning text-white fw-bold px-4">Update Data</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../../footer.php'; ?>