<?php
// BAGIAN 1: LOGIKA RUANG MESIN (SEBELUM ADA TAMPILAN APAPUN)
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../bootstrap/init.php';
guard('santri_edit');

// Generate CSRF token sebelum form ditampilkan
$csrf_token = csrf_generate();

// ---- SEMUA LOGIKA PEMROSESAN FORM PINDAH KE SINI ----
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validasi CSRF token terlebih dahulu
    csrf_validate();
    try {
        // Ambil ID dari URL, karena form submit ke halaman ini lagi
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if ($id === 0) {
            throw new Exception("ID Santri tidak valid saat memproses form!");
        }

        // Ambil data mentah dari form
        $nis_input = $_POST['nis'] ?? '';
        $nama_input = $_POST['nama'] ?? '';
        $kelas_input = $_POST['kelas'] ?? '';
        $kamar_input = $_POST['kamar'] ?? '';

        // Bersihkan dan validasi
        $nis = trim($nis_input);
        if ($nis === '') { $nis = null; } // allow null
        $nama = trim($nama_input);
        $kelas_mentah = trim($kelas_input);
        $kamar_mentah = trim($kamar_input);

        if (empty($nama) || empty($kelas_mentah) || empty($kamar_mentah)) {
            throw new Exception("Semua field harus diisi!");
        }
        
        // Normalisasi nomor kelas dan kamar menjadi angka murni (integer)
        $kelas_bersih = intval($kelas_mentah);
        $kamar_bersih = intval($kamar_mentah);

        // Cek apakah NIS sudah dipakai oleh santri lain
        if ($nis !== null) {
            $stmt_check = mysqli_prepare($conn, "SELECT id FROM santri WHERE nis = ? AND id != ?");
            mysqli_stmt_bind_param($stmt_check, "si", $nis, $id);
            mysqli_stmt_execute($stmt_check);
            $res_check = mysqli_stmt_get_result($stmt_check);
            if (mysqli_num_rows($res_check) > 0) {
                mysqli_stmt_close($stmt_check);
                throw new Exception("Gagal! NIS '$nis' sudah terdaftar atas nama santri lain.");
            }
            mysqli_stmt_close($stmt_check);
        }

        // Ambil data lama dulu buat log
        $stmt_old = mysqli_prepare($conn, "SELECT nis, nama, kelas, kamar FROM santri WHERE id = ?");
        mysqli_stmt_bind_param($stmt_old, "i", $id);
        mysqli_stmt_execute($stmt_old);
        $res_old = mysqli_stmt_get_result($stmt_old);
        $old_data = mysqli_fetch_assoc($res_old);
        mysqli_stmt_close($stmt_old);

        // Update data using prepared statement
        $stmt = mysqli_prepare($conn, "UPDATE santri SET nis=?, nama=?, kelas=?, kamar=? WHERE id=?");
        
        // Tipe data sudah benar: s (string), s (string), i (integer), i (integer), i (integer)
        mysqli_stmt_bind_param($stmt, "ssiii", $nis, $nama, $kelas_bersih, $kamar_bersih, $id);
        
        if (mysqli_stmt_execute($stmt)) {
            // Catat log edit
            if ($old_data) {
                write_activity_log('UPDATE', 'santri', "Mengubah data santri: '" . htmlspecialchars($old_data['nama']) . "'", [
                    'id' => $id,
                    'old' => $old_data,
                    'new' => [
                        'nis' => $nis,
                        'nama' => $nama,
                        'kelas' => $kelas_bersih,
                        'kamar' => $kamar_bersih
                    ]
                ]);
            }
            $_SESSION['success_message'] = "Data santri $nama berhasil diperbarui!";
            header("Location: index.php");
            exit;
        } else {
            throw new Exception("Gagal memperbarui data: " . mysqli_error($conn));
        }
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
        // Refresh halaman edit untuk menampilkan error
        // Pastikan $id sudah terdefinisi di sini
        $id_for_redirect = isset($_GET['id']) ? intval($_GET['id']) : 0;
        header("Location: edit.php?id=" . $id_for_redirect);
        exit;
    }
}

// BAGIAN 2: PERSIAPAN TAMPILAN WAHANA (UNTUK MENAMPILKAN DATA AWAL)
// Logika di bawah ini hanya berjalan saat methodnya GET (pertama kali buka halaman)
// atau setelah error di atas (untuk menampilkan ulang form)
require_once __DIR__ . '/../layouts/header.php';

// Secure the ID parameter
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id === 0) {
    $_SESSION['error_message'] = "ID Santri tidak valid!";
    header("Location: index.php");
    exit;
}

// Fetch santri data using prepared statement
$stmt = mysqli_prepare($conn, "SELECT nis, nama, kelas, kamar FROM santri WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$row) {
    $_SESSION['error_message'] = "Santri dengan ID $id tidak ditemukan!";
    header("Location: index.php");
    exit;
}

// HAPUS BLOK IF($_SERVER["REQUEST_METHOD"] == "POST") YANG LAMA DARI SINI
// KARENA SUDAH DIPINDAH KE ATAS
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-6">
            <div class="card shadow-sm border-0" style="border-radius: 1rem;">
                <div class="card-header bg-white border-bottom p-4" style="border-radius: 1rem 1rem 0 0;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <div class="bg-warning bg-opacity-10 text-warning rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 48px; height: 48px;">
                                <i class="fas fa-user-edit fs-5"></i>
                            </div>
                            <div>
                                <h5 class="mb-1 fw-bold text-dark">Edit Data Santri</h5>
                                <p class="mb-0 text-muted small">Perbarui informasi santri ini.</p>
                            </div>
                        </div>
                        <span class="badge bg-light text-secondary border">ID: <?= $id ?></span>
                    </div>
                </div>
                
                <div class="card-body p-4 p-md-5">
                    <?php if (isset($_SESSION['error_message'])): ?>
                        <div class="alert alert-danger d-flex align-items-center rounded-3 mb-4" role="alert">
                            <i class="fas fa-exclamation-triangle fs-4 me-3"></i>
                            <div>
                                <?= htmlspecialchars($_SESSION['error_message']) ?>
                            </div>
                        </div>
                        <?php unset($_SESSION['error_message']); ?>
                    <?php endif; ?>

                    <form method="post" id="editForm">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        
                        <div class="mb-4">
                            <label for="nis" class="form-label fw-medium text-secondary">Nomor Induk Santri (NIS)</label>
                            <div class="input-group-modern">
                                <i class="fas fa-id-card"></i>
                                <input type="text" id="nis" name="nis" class="form-control input-modern" 
                                       value="<?= htmlspecialchars($row['nis'] ?? '') ?>" placeholder="Kosongkan jika belum ada">
                            </div>
                        </div>
                        <div class="mb-4">
                            <label for="nama" class="form-label fw-medium text-secondary">Nama Lengkap</label>
                            <div class="input-group-modern">
                                <i class="fas fa-user"></i>
                                <input type="text" id="nama" name="nama" class="form-control input-modern" 
                                       value="<?= htmlspecialchars($row['nama']) ?>" required>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="kelas" class="form-label fw-medium text-secondary">Kelas</label>
                            <div class="input-group-modern">
                                <i class="fas fa-graduation-cap"></i>
                                <input type="number" id="kelas" name="kelas" class="form-control input-modern" 
                                       value="<?= htmlspecialchars($row['kelas']) ?>" required min="1">
                            </div>
                            <div class="form-text small text-muted mt-2">Cukup masukkan angkanya saja (misal: 7, 8, 9).</div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="kamar" class="form-label fw-medium text-secondary">Nomor Kamar</label>
                            <div class="input-group-modern">
                                <i class="fas fa-door-open"></i>
                                <input type="number" id="kamar" name="kamar" class="form-control input-modern" 
                                       value="<?= htmlspecialchars($row['kamar']) ?>" required min="1">
                            </div>
                            <div class="form-text small text-muted mt-2">Boleh diisi 1 digit (6) atau 2 digit (06).</div>
                        </div>
                        
                        <hr class="my-4 border-light">

                        <div class="d-flex justify-content-between gap-2">
                            <a href="index.php" class="btn btn-light fw-medium px-4">
                                <i class="fas fa-arrow-left me-2"></i>Kembali
                            </a>
                            <button type="submit" class="btn btn-warning text-dark fw-medium px-4">
                                <i class="fas fa-save me-2"></i>Simpan Perubahan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
require_once __DIR__ . '/../layouts/footer.php'; 
ob_end_flush(); 
?>