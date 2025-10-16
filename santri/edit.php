<?php
// BAGIAN 1: LOGIKA RUANG MESIN (SEBELUM ADA TAMPILAN APAPUN)
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../init.php';
guard('santri_edit');

// ---- SEMUA LOGIKA PEMROSESAN FORM PINDAH KE SINI ----
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Ambil ID dari URL, karena form submit ke halaman ini lagi
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if ($id === 0) {
            throw new Exception("ID Santri tidak valid saat memproses form!");
        }

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
        
        // Normalisasi nomor kelas dan kamar menjadi angka murni (integer)
        $kelas_bersih = intval($kelas_mentah);
        $kamar_bersih = intval($kamar_mentah);

        // Update data using prepared statement
        $stmt = mysqli_prepare($conn, "UPDATE santri SET nama=?, kelas=?, kamar=? WHERE id=?");
        
        // Tipe data sudah benar: s (string), i (integer), i (integer), i (integer)
        mysqli_stmt_bind_param($stmt, "siii", $nama, $kelas_bersih, $kamar_bersih, $id);
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['operation_result'] = [
                'success' => true, 
                'message' => "Data santri $nama berhasil diperbarui!"
            ];
            header("Location: index.php");
            exit;
        } else {
            throw new Exception("Gagal memperbarui data: " . mysqli_error($conn));
        }
        
    } catch (Exception $e) {
        $_SESSION['operation_result'] = ['success' => false, 'message' => $e->getMessage()];
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
require_once __DIR__ . '/../header.php';

// Secure the ID parameter
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id === 0) {
    $_SESSION['operation_result'] = ['success' => false, 'message' => "ID Santri tidak valid!"];
    header("Location: index.php");
    exit;
}

// Fetch santri data using prepared statement
$stmt = mysqli_prepare($conn, "SELECT nama, kelas, kamar FROM santri WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$row) {
    $_SESSION['operation_result'] = ['success' => false, 'message' => "Santri dengan ID $id tidak ditemukan!"];
    header("Location: index.php");
    exit;
}

// HAPUS BLOK IF($_SERVER["REQUEST_METHOD"] == "POST") YANG LAMA DARI SINI
// KARENA SUDAH DIPINDAH KE ATAS
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Data Santri | Pesantren</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2c3e50;
            --warning-color: #f39c12;
        }
        body { background-color: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .edit-container { max-width: 600px; margin: 2rem auto; background-color: white; border-radius: 10px; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1); overflow: hidden; }
        .edit-header { background: linear-gradient(135deg, var(--secondary-color), var(--warning-color)); color: white; padding: 1.5rem; margin-bottom: 1.5rem; }
        .edit-body { padding: 0 2rem 2rem; }
        .form-label { font-weight: 500; color: var(--secondary-color); }
        .form-control { border-radius: 5px; padding: 10px 15px; border: 1px solid #ddd; transition: all 0.3s; }
        .form-control:focus { border-color: var(--warning-color); box-shadow: 0 0 0 0.25rem rgba(243, 156, 18, 0.25); }
        .btn-update { background-color: var(--warning-color); border-color: var(--warning-color); color: white; padding: 10px 25px; font-weight: 500; transition: all 0.3s; }
        .btn-update:hover { background-color: #e67e22; transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); }
        .input-icon { position: relative; }
        .input-icon i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--warning-color); }
        .input-icon input { padding-left: 40px; }
        /* Sembunyikan panah di input number */
        input[type=number]::-webkit-inner-spin-button, 
        input[type=number]::-webkit-outer-spin-button { 
            -webkit-appearance: none; margin: 0; 
        }
        input[type=number] { -moz-appearance: textfield; }
    </style>
</head>
<body>
    <div class="edit-container">
        <div class="edit-header text-center">
            <h2><i class="fas fa-user-edit me-2"></i>Edit Data Santri</h2>
            <p class="mb-0">ID Santri: <?= $id ?></p>
        </div>
        
        <div class="edit-body">
            <?php if (isset($_SESSION['operation_result']) && !$_SESSION['operation_result']['success']): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?= htmlspecialchars($_SESSION['operation_result']['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['operation_result']); ?>
            <?php endif; ?>

            <form method="post" id="editForm">
                <div class="mb-4 input-icon">
                    <label for="nama" class="form-label">Nama Lengkap</label>
                    <i class="fas fa-user"></i>
                    <input type="text" id="nama" name="nama" class="form-control" 
                           value="<?= htmlspecialchars($row['nama']) ?>" required>
                </div>
                
                <div class="mb-4 input-icon">
                    <label for="kelas" class="form-label">Kelas</label>
                    <i class="fas fa-graduation-cap"></i>
                    <!-- PERUBAHAN DI SINI -->
                    <input type="number" id="kelas" name="kelas" class="form-control" 
                           value="<?= htmlspecialchars($row['kelas']) ?>" required min="1">
                    <div class="form-text">Cukup masukkan angkanya saja (misal: 7, 8, 9).</div>
                </div>
                
                <div class="mb-4 input-icon">
                    <label for="kamar" class="form-label">Nomor Kamar</label>
                    <i class="fas fa-door-open"></i>
                    <!-- DAN DI SINI -->
                    <input type="number" id="kamar" name="kamar" class="form-control" 
                           value="<?= htmlspecialchars($row['kamar']) ?>" required min="1">
                    <div class="form-text">Boleh diisi 1 digit (6) atau 2 digit (06).</div>
                </div>
                
                <div class="d-flex justify-content-between pt-3">
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Kembali
                    </a>
                    <button type="submit" class="btn btn-update">
                        <i class="fas fa-save me-2"></i>Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php ob_end_flush(); ?>