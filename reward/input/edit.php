<?php
require_once '../../init.php';
guard('reward_input');
require_once '../../header.php';

$id = (int) $_GET['id'];

// Ambil data transaksi ini
$q_data = mysqli_query($conn, "SELECT * FROM daftar_reward WHERE id = $id");
$data   = mysqli_fetch_assoc($q_data);

if (!$data) {
    echo "<script>alert('Data tidak ditemukan'); window.location='../history/index.php';</script>";
    exit;
}

$santri_list = mysqli_query($conn, "SELECT id, nama, kelas, poin_aktif FROM santri ORDER BY nama ASC");
$reward_list = mysqli_query($conn, "SELECT * FROM jenis_reward ORDER BY nama_reward ASC");
?>

<div class="content-wrapper" style="padding: 20px;">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-header bg-warning text-white py-3">
                    <h5 class="mb-0 fw-bold"><i class="fas fa-edit me-2"></i>Edit Data Reward</h5>
                </div>
                <div class="card-body p-4">
                    
                    <div class="alert alert-info small mb-4">
                        <i class="fas fa-exclamation-circle"></i> <b>Perhatian:</b> Mengubah data ini akan otomatis menyesuaikan poin santri (mengembalikan poin lama dan mengurangi dengan poin baru).
                    </div>

                    <form action="process.php" method="POST">
                        <input type="hidden" name="id" value="<?= $data['id'] ?>">

                        <div class="mb-4">
                            <label class="form-label fw-bold">Nama Santri</label>
                            <select name="santri_id" class="form-control select2" required style="width: 100%;">
                                <?php while($s = mysqli_fetch_assoc($santri_list)): ?>
                                    <option value="<?= $s['id'] ?>" <?= ($s['id'] == $data['santri_id']) ? 'selected' : '' ?>>
                                        <?= $s['nama'] ?> (Kelas <?= $s['kelas'] ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold">Jenis Reward</label>
                            <select name="jenis_reward_id" class="form-control" required>
                                <?php while($r = mysqli_fetch_assoc($reward_list)): ?>
                                    <option value="<?= $r['id'] ?>" <?= ($r['id'] == $data['jenis_reward_id']) ? 'selected' : '' ?>>
                                        <?= $r['nama_reward'] ?> (<?= $r['poin_reward'] ?> Poin)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold">Tanggal</label>
                            <input type="datetime-local" name="tanggal" class="form-control" value="<?= date('Y-m-d\TH:i', strtotime($data['tanggal'])) ?>" required>
                        </div>

                        <div class="d-flex justify-content-between pt-3 border-top">
                            <a href="../history/index.php" class="btn btn-light text-muted">Batal</a>
                            <button type="submit" name="edit_reward" class="btn btn-warning text-white px-4 fw-bold">
                                Update Data
                            </button>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once '../../footer.php'; ?>