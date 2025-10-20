<?php 
// 1. Panggil 'Otak' aplikasi dulu
require_once __DIR__ . '/../init.php';

// 2. Jalankan 'SATPAM' buat ngejaga halaman
guard('jenis_pelanggaran_edit'); 

// 3. Ambil data ID dari form index.php
$ids = $_POST['ids'] ?? [];

// 4. Validasi: Kalo nggak ada ID yang dikirim, tendang balik
if (empty($ids)) {
    $_SESSION['message'] = ['type' => 'danger', 'text' => 'Anda belum memilih data untuk diedit.'];
    header("Location: index.php");
    exit();
}

// 5. Kita butuh array ini untuk bikin dropdown
$allowed_kategori = ['Ringan', 'Sedang', 'Berat', 'Sangat Berat'];
$allowed_bagian = ['Kesantrian', 'Bahasa', 'Diniyyah', 'Pengabdian', 'Tahfidz'];

// 6. Siapin & eksekusi query buat ngambil data yang mau diedit
// Bikin placeholder ? sejumlah ID yang dipilih
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$types = str_repeat('i', count($ids)); // Tipe datanya integer semua

$query = "SELECT * FROM jenis_pelanggaran WHERE id IN ($placeholders) ORDER BY nama_pelanggaran ASC";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, $types, ...$ids);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// 7. Panggil Tampilan
require_once __DIR__ . '/../header.php';
?>

<style>
    /* Biar card-nya nggak terlalu lebar di desktop */
    .card-edit-item {
        max-width: 800px; /* Atur sesuai selera */
        margin-left: auto;
        margin-right: auto;
    }
</style>

<div class="container my-4">
    <div class="card col-lg-10 mx-auto shadow-sm mb-4">
        <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="fas fa-edit me-2"></i>Edit Banyak Jenis Pelanggaran (<?= mysqli_num_rows($result) ?> Data)</h4>
            <a href="index.php" class="btn btn-light text-dark btn-sm"><i class="fas fa-arrow-left me-1"></i> Kembali</a>
        </div>
    </div>

    <form action="process.php" method="POST">
        <input type="hidden" name="bulk_update" value="1">

        <?php if(mysqli_num_rows($result) > 0): ?>
            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                <?php $id = $row['id']; ?>
                <div class="card shadow-sm mb-3 card-edit-item">
                    <div class="card-body p-3 p-md-4">
                        <input type="hidden" name="ids[]" value="<?= $id; ?>">
                        
                        <div class="mb-3">
                            <label for="nama_<?= $id ?>" class="form-label fw-bold">Nama Pelanggaran (ID: <?= $id ?>)</label>
                            <input type="text" 
                                   id="nama_<?= $id ?>"
                                   name="nama_pelanggaran[<?= $id; ?>]" 
                                   value="<?= htmlspecialchars($row['nama_pelanggaran']); ?>" 
                                   class="form-control" 
                                   required>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-5">
                                <label for="bagian_<?= $id ?>" class="form-label">Bagian</label>
                                <select id="bagian_<?= $id ?>" name="bagian[<?= $id; ?>]" class="form-select" required>
                                    <?php foreach ($allowed_bagian as $bagian): ?>
                                        <?php 
                                            $selected = (strcasecmp($row['bagian'], $bagian) == 0) ? 'selected' : ''; 
                                        ?>
                                        <option value="<?= $bagian; ?>" <?= $selected ?>>
                                            <?= $bagian; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="poin_<?= $id ?>" class="form-label">Poin</label>
                                <input type="number" 
                                       id="poin_<?= $id ?>"
                                       name="poin[<?= $id; ?>]" 
                                       value="<?= $row['poin']; ?>" 
                                       class="form-control" 
                                       min="0"
                                       required>
                            </div>
                            <div class="col-md-4">
                                <label for="kategori_<?= $id ?>" class="form-label">Kategori</label>
                                <select id="kategori_<?= $id ?>" name="kategori[<?= $id; ?>]" class="form-select" required>
                                    <?php foreach ($allowed_kategori as $kategori): ?>
                                         <?php 
                                            $selected = (strcasecmp($row['kategori'], $kategori) == 0) ? 'selected' : ''; 
                                        ?>
                                        <option value="<?= $kategori; ?>" <?= $selected ?>>
                                            <?= $kategori; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>

            <div class="card shadow-sm mt-4 card-edit-item">
                <div class="card-body">
                    <div class="alert alert-info mb-3">
                        <i class="fas fa-info-circle me-1"></i> 
                        Mengubah poin akan <strong>otomatis menghitung ulang</strong> poin aktif semua santri yang terkait.
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="fas fa-save me-2"></i> Simpan Semua Perubahan
                        </button>
                    </div>
                </div>
            </div>

        <?php else: ?>
             <div class="alert alert-warning text-center">Data tidak ditemukan. Silakan kembali dan pilih data.</div>
        <?php endif; ?>

    </form>
</div>

<?php 
mysqli_stmt_close($stmt);
require_once __DIR__ . '/../footer.php'; 
?>