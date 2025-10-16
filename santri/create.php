<?php
// BAGIAN 1: LOGIKA RUANG MESIN (SEMUA PROSES DI SINI)
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../init.php';
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
require_once __DIR__ . '/../header.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Santri Baru | Pesantren</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2c3e50;
            --success-color: #27ae60;
        }
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .form-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .form-header {
            background: linear-gradient(135deg, var(--secondary-color), var(--primary-color));
            color: white;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .form-body { padding: 0 2rem 2rem; }
        .form-label { font-weight: 500; color: var(--secondary-color); }
        .form-control { border-radius: 5px; padding: 10px 15px; border: 1px solid #ddd; transition: all 0.3s; }
        .form-control:focus { border-color: var(--primary-color); box-shadow: 0 0 0 0.25rem rgba(52, 152, 219, 0.25); }
        
        .btn-submit, .btn-cancel {
            padding: 0.5rem 1.25rem;
            font-weight: 500; 
            transition: all 0.3s;
        }
        .btn-submit { background-color: var(--success-color); border-color: var(--success-color); }
        .btn-submit:hover { background-color: #219653; transform: translateY(-2px); }
        .btn-cancel:hover { transform: translateY(-2px); }

        /* [FIX UTAMA] Ini adalah CSS yang benar setelah HTML diperbaiki.
           .input-icon sekarang HANYA membungkus input dan ikonnya.
        */
        .input-icon { 
            position: relative; 
        }
        .input-icon i {
            position: absolute;
            left: 15px;
            top: 50%; /* Ini sekarang berfungsi sempurna */
            transform: translateY(-50%);
            color: var(--primary-color);
            pointer-events: none; /* Biar ikonnya ga bisa diklik */
        }
        .input-icon input { 
            padding-left: 40px; 
        }

        /* Sembunyikan panah di input number */
        input[type=number]::-webkit-inner-spin-button, 
        input[type=number]::-webkit-outer-spin-button { 
            -webkit-appearance: none; margin: 0; 
        }
        input[type=number] { -moz-appearance: textfield; }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="form-container">
            <div class="form-header text-center">
                <h2><i class="fas fa-user-plus me-2"></i>Tambah Santri Baru</h2>
                <p class="mb-0">Isi form berikut untuk menambahkan santri baru</p>
            </div>
            
            <div class="form-body">
                <form method="post" id="santriForm">
                    <div class="mb-4">
                        <label for="nama" class="form-label">Nama Lengkap</label>
                        <div class="input-icon">
                            <i class="fas fa-user"></i>
                            <input type="text" id="nama" name="nama" class="form-control" 
                                   placeholder="Masukkan nama lengkap santri" required
                                   minlength="3" maxlength="100">
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="kelas" class="form-label">Kelas</label>
                        <div class="input-icon">
                            <i class="fas fa-graduation-cap"></i>
                            <input type="number" id="kelas" name="kelas" class="form-control" 
                                   placeholder="Contoh: 7 atau 8" required min="1">
                        </div>
                        <div class="form-text">Cukup masukkan angkanya saja (misal: 7, 8, 9).</div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="kamar" class="form-label">Nomor Kamar</label>
                        <div class="input-icon">
                            <i class="fas fa-door-open"></i>
                            <input type="number" id="kamar" name="kamar" class="form-control" 
                                   placeholder="Contoh: 6 atau 12" required min="1">
                        </div>
                        <div class="form-text">Boleh diisi 1 digit (6) atau 2 digit (06).</div>
                    </div>
                    
                    <div class="d-flex justify-content-end pt-3" style="gap: 10px;">
                        <a href="index.php" class="btn btn-secondary btn-cancel">
                            <i class="fas fa-times me-2"></i>Batal
                        </a>
                        <button type="submit" class="btn btn-success btn-submit">
                            <i class="fas fa-save me-2"></i>Simpan Data
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php require_once __DIR__ . '/../footer.php'; ?>