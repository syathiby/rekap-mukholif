<?php 
// 1. Panggil 'Otak' aplikasi dulu
require_once __DIR__ . '/../../init.php';

// 2. Jalankan 'SATPAM' buat ngejaga halaman
guard('periode_aktif_manage'); 

// 3. Kalau lolos, baru panggil Tampilan
require_once __DIR__ . '/../../header.php';
?>

<?php

// Ambil periode aktif dari DB
$q = mysqli_query($conn, "SELECT nilai FROM pengaturan WHERE nama = 'periode_aktif' LIMIT 1");
$data = mysqli_fetch_assoc($q);
$periode_aktif = $data ? $data['nilai'] : '';
?>

<?php if (isset($_SESSION['notif'])): ?>
    <div class="alert alert-<?= $_SESSION['notif']['type']; ?> alert-dismissible fade show" role="alert">
        <?= $_SESSION['notif']['msg']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['notif']); ?>
<?php endif; ?>

<div class="container my-4">
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0">📅 Atur Periode Aktif</h4>
        </div>
        <div class="card-body">
            <p class="text-muted">
                Periode aktif menentukan data mana yang ditampilkan di halaman utama.  
                Data sebelum tanggal ini <b>disembunyikan</b> (tapi tetap aman di database).
            </p>

            <form action="process.php" method="post" class="mt-3">
                <div class="mb-3">
                    <label for="periode_aktif" class="form-label fw-bold">Tanggal Periode Aktif:</label>
                    <input type="date" id="periode_aktif" name="periode_aktif" 
                           class="form-control" value="<?= $periode_aktif; ?>" required>
                </div>

                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save me-1"></i> Simpan Periode
                </button>
            </form>
        </div>
    </div>
</div>

<?php include '../../footer.php'; ?>