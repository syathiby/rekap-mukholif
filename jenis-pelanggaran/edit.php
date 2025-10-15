<?php 
// 1. Panggil 'Otak' aplikasi dulu
require_once __DIR__ . '/../init.php';

// 2. Jalankan 'SATPAM' buat ngejaga halaman
guard('jenis_pelanggaran_edit'); 

// 3. Kalau lolos, baru panggil Tampilan
require_once __DIR__ . '/../header.php'; 
?>

<?php

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$id = $_GET['id'];

// Menggunakan Prepared Statement untuk mencegah SQL Injection
$query = "SELECT * FROM jenis_pelanggaran WHERE id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$data = mysqli_fetch_assoc($result);

if (!$data) {
    // Jika data tidak ditemukan, redirect ke halaman utama
    $_SESSION['message'] = ['type' => 'danger', 'text' => 'Data tidak ditemukan.'];
    header("Location: index.php");
    exit;
}
?>

<style>
    /* Styling ini bisa dipindah ke file CSS utama jika perlu */
    .card-edit { 
        max-width: 600px; 
        margin: 2rem auto; 
    }
</style>

<div class="container my-4">
    <div class="card card-edit shadow-sm">
        <div class="card-header bg-primary text-white">
            <div class="d-flex justify-content-between align-items-center">
                <h4 class="mb-0"><i class="fas fa-edit me-2"></i>Edit Jenis Pelanggaran</h4>
                <a href="index.php" class="btn btn-light btn-sm"><i class="fas fa-arrow-left me-1"></i> Kembali</a>
            </div>
        </div>
        
        <div class="card-body p-4">
            <form action="process.php" method="POST">
                <input type="hidden" name="id" value="<?php echo $data['id']; ?>">
                
                <div class="mb-3">
                    <label for="nama_pelanggaran" class="form-label">Nama Pelanggaran</label>
                    <input type="text" class="form-control" id="nama_pelanggaran" 
                           name="nama_pelanggaran" value="<?php echo htmlspecialchars($data['nama_pelanggaran']); ?>" 
                           placeholder="Masukkan nama pelanggaran" required>
                </div>

                <!-- ======================================================= -->
                <!-- === INI DIA FIELD BARUNYA === -->
                <!-- ======================================================= -->
                <div class="mb-3">
                    <label for="bagian" class="form-label">Bagian</label>
                    <select class="form-select" id="bagian" name="bagian" required>
                        <option value="Kesantrian" <?php echo ($data['bagian'] == 'Kesantrian') ? 'selected' : ''; ?>>Kesantrian</option>
                        <option value="Bahasa" <?php echo ($data['bagian'] == 'Bahasa') ? 'selected' : ''; ?>>Bahasa</option>
                        <option value="Diniyyah" <?php echo ($data['bagian'] == 'Diniyyah') ? 'selected' : ''; ?>>Diniyyah</option>
                        <option value="Pengabdian" <?php echo ($data['bagian'] == 'Pengabdian') ? 'selected' : ''; ?>>Pengabdian</option>
                        <option value="Tahfidz" <?php echo ($data['bagian'] == 'Tahfidz') ? 'selected' : ''; ?>>Tahfidz</option>
                    </select>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="poin" class="form-label">Poin</label>
                        <input type="number" class="form-control" id="poin" 
                               name="poin" value="<?php echo htmlspecialchars($data['poin']); ?>" 
                               placeholder="Masukkan poin" required min="0">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="kategori" class="form-label">Kategori</label>
                        <select class="form-select" id="kategori" name="kategori" required>
                            <option value="Ringan" <?php echo ($data['kategori'] == 'Ringan') ? 'selected' : ''; ?>>Ringan</option>
                            <option value="Sedang" <?php echo ($data['kategori'] == 'Sedang') ? 'selected' : ''; ?>>Sedang</option>
                            <option value="Berat" <?php echo ($data['kategori'] == 'Berat') ? 'selected' : ''; ?>>Berat</option>
                            <option value="Sangat Berat" <?php echo ($data['kategori'] == 'Sangat Berat') ? 'selected' : ''; ?>>Sangat Berat</option>
                        </select>
                    </div>
                </div>
                
                <hr class="my-3">

                <div class="d-grid">
                    <button type="submit" name="update" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../footer.php'; ?>