<?php
// BAGIAN 1: LOGIKA RUANG MESIN (SEMUA PROSES DI SINI)
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../bootstrap/init.php';
guard('santri_create'); 

// [FIX] Logika POST yang sudah dibersihkan dan disatukan
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Ambil data mentah dari form
        $nama_input = $_POST['nama'] ?? '';
        $kelas_input = $_POST['kelas'] ?? '';
        $kamar_input = $_POST['kamar'] ?? '';
        
        // Bersihkan dan validasi
        $nama = trim($nama_input);
        $kelas_mentah = trim($kelas_input);
        $kamar_mentah = trim($kamar_input);

        if (empty($nama) || empty($kelas_mentah) || empty($kamar_mentah)) {
            throw new Exception("Semua field harus diisi!");
        }

        $kelas_bersih = intval($kelas_mentah);
        $kamar_bersih = intval($kamar_mentah);

        // Insert data menggunakan prepared statement
        $stmt = mysqli_prepare($conn, "INSERT INTO santri (nama, kelas, kamar) VALUES (?, ?, ?)");
        
        mysqli_stmt_bind_param($stmt, "sii", $nama, $kelas_bersih, $kamar_bersih);
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success_message'] = "Santri $nama berhasil ditambahkan!";
        } else {
            throw new Exception("Gagal menambahkan santri: " . mysqli_error($conn));
        }
        
        mysqli_stmt_close($stmt);
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
    
    // Redirect setelah semua logika selesai
    header("Location: index.php");
    exit;
}

// BAGIAN 2: PERSIAPAN TAMPILAN WAHANA
require_once __DIR__ . '/../layouts/header.php';
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-6">
            <div class="card shadow-sm border-0" style="border-radius: 1rem;">
                <div class="card-header bg-white border-bottom p-4" style="border-radius: 1rem 1rem 0 0;">
                    <div class="d-flex align-items-center">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 48px; height: 48px;">
                            <i class="fas fa-user-plus fs-5"></i>
                        </div>
                        <div>
                            <h5 class="mb-1 fw-bold text-dark">Tambah Santri Baru</h5>
                            <p class="mb-0 text-muted small">Lengkapi informasi di bawah untuk pendaftaran santri.</p>
                        </div>
                    </div>
                </div>
                
                <div class="card-body p-4 p-md-5">
                    <form method="post" id="santriForm">
                        <div class="mb-4">
                            <label for="nama" class="form-label fw-medium text-secondary">Nama Lengkap</label>
                            <div class="input-group-modern">
                                <i class="fas fa-user"></i>
                                <input type="text" id="nama" name="nama" class="form-control input-modern" 
                                       placeholder="Masukkan nama lengkap santri" required
                                       minlength="3" maxlength="100">
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="kelas" class="form-label fw-medium text-secondary">Kelas</label>
                            <div class="input-group-modern">
                                <i class="fas fa-graduation-cap"></i>
                                <input type="number" id="kelas" name="kelas" class="form-control input-modern" 
                                       placeholder="Contoh: 7 atau 8" required min="1">
                            </div>
                            <div class="form-text small text-muted mt-2">Cukup masukkan angkanya saja (misal: 7, 8, 9).</div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="kamar" class="form-label fw-medium text-secondary">Nomor Kamar</label>
                            <div class="input-group-modern">
                                <i class="fas fa-door-open"></i>
                                <input type="number" id="kamar" name="kamar" class="form-control input-modern" 
                                       placeholder="Contoh: 6 atau 12" required min="1">
                            </div>
                            <div class="form-text small text-muted mt-2">Boleh diisi 1 digit (6) atau 2 digit (06).</div>
                        </div>
                        
                        <hr class="my-4 border-light">

                        <div class="d-flex justify-content-end gap-2">
                            <a href="index.php" class="btn btn-light fw-medium px-4">
                                <i class="fas fa-times me-2"></i>Batal
                            </a>
                            <button type="submit" class="btn btn-success fw-medium px-4">
                                <i class="fas fa-save me-2"></i>Simpan Data
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>