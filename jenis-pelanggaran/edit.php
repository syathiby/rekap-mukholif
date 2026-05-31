<?php 
// 1. Panggil 'Otak' aplikasi dulu
require_once __DIR__ . '/../bootstrap/init.php';

// 2. Jalankan 'SATPAM' buat ngejaga halaman
guard('jenis_pelanggaran_edit'); 

// 3. Kalau lolos, baru panggil Tampilan
require_once __DIR__ . '/../layouts/header.php'; 
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

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-6">
            <div class="card shadow-sm border-0" style="border-radius: 1rem;">
                <div class="card-header bg-white border-bottom p-4" style="border-radius: 1rem 1rem 0 0;">
                    <div class="d-flex align-items-center">
                        <div class="bg-info bg-opacity-10 text-info rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 48px; height: 48px;">
                            <i class="fas fa-edit fs-5"></i>
                        </div>
                        <div>
                            <h5 class="mb-1 fw-bold text-dark">Edit Jenis Pelanggaran</h5>
                            <p class="mb-0 text-muted small">Ubah detail data pelanggaran.</p>
                        </div>
                    </div>
                </div>
                
                <div class="card-body p-4 p-md-5">
                    <form action="process.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="id" value="<?php echo $data['id']; ?>">
                        
                        <div class="mb-4">
                            <label for="nama_pelanggaran" class="form-label fw-medium text-secondary">Nama Pelanggaran</label>
                            <div class="input-group-modern">
                                <i class="fas fa-file-signature"></i>
                                <input type="text" class="form-control input-modern" id="nama_pelanggaran" 
                                       name="nama_pelanggaran" value="<?php echo htmlspecialchars($data['nama_pelanggaran']); ?>" 
                                       placeholder="Masukkan nama pelanggaran" required>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="bagian" class="form-label fw-medium text-secondary">Bagian</label>
                            <div class="input-group-modern">
                                <i class="fas fa-building"></i>
                                <select class="form-select input-modern" id="bagian" name="bagian" required>
                                    <option value="Kesantrian" <?php echo ($data['bagian'] == 'Kesantrian') ? 'selected' : ''; ?>>Kesantrian</option>
                                    <option value="Bahasa" <?php echo ($data['bagian'] == 'Bahasa') ? 'selected' : ''; ?>>Bahasa</option>
                                    <option value="Diniyyah" <?php echo ($data['bagian'] == 'Diniyyah') ? 'selected' : ''; ?>>Diniyyah</option>
                                    <option value="Pengabdian" <?php echo ($data['bagian'] == 'Pengabdian') ? 'selected' : ''; ?>>Pengabdian</option>
                                    <option value="Tahfidz" <?php echo ($data['bagian'] == 'Tahfidz') ? 'selected' : ''; ?>>Tahfidz</option>
                                </select>
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label for="poin" class="form-label fw-medium text-secondary">Poin</label>
                                <div class="input-group-modern">
                                    <i class="fas fa-star-half-alt"></i>
                                    <input type="number" class="form-control input-modern" id="poin" 
                                           name="poin" value="<?php echo htmlspecialchars($data['poin']); ?>" 
                                           placeholder="Masukkan poin" required min="0">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label for="kategori" class="form-label fw-medium text-secondary">Kategori</label>
                                <div class="input-group-modern">
                                    <i class="fas fa-layer-group"></i>
                                    <select class="form-select input-modern" id="kategori" name="kategori" required>
                                        <option value="Ringan" <?php echo ($data['kategori'] == 'Ringan') ? 'selected' : ''; ?>>Ringan</option>
                                        <option value="Sedang" <?php echo ($data['kategori'] == 'Sedang') ? 'selected' : ''; ?>>Sedang</option>
                                        <option value="Berat" <?php echo ($data['kategori'] == 'Berat') ? 'selected' : ''; ?>>Berat</option>
                                        <option value="Sangat Berat" <?php echo ($data['kategori'] == 'Sangat Berat') ? 'selected' : ''; ?>>Sangat Berat</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <hr class="my-4 border-light">

                        <div class="d-flex justify-content-between gap-2">
                            <a href="index.php" class="btn btn-light fw-medium px-4">
                                <i class="fas fa-arrow-left me-2"></i>Kembali
                            </a>
                            <button type="submit" name="update" class="btn btn-primary fw-medium px-4">
                                <i class="fas fa-save me-2"></i>Simpan Perubahan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>