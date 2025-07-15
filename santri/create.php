<?php ob_start(); ?>
<?php
include '../db.php';
require_once __DIR__ . '/../header.php'; 

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Validate and sanitize input
        $nama = mysqli_real_escape_string($conn, htmlspecialchars($_POST['nama']));
        $kelas = mysqli_real_escape_string($conn, htmlspecialchars($_POST['kelas']));
        $kamar = mysqli_real_escape_string($conn, htmlspecialchars($_POST['kamar']));
        
        // Validate required fields
        if (empty($nama) || empty($kelas) || empty($kamar)) {
            throw new Exception("Semua field harus diisi!");
        }
        
        // Insert data using prepared statement
        $stmt = mysqli_prepare($conn, "INSERT INTO santri (nama, kelas, kamar) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "sss", $nama, $kelas, $kamar);
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['operation_result'] = [
                'success' => true,
                'message' => "Santri $nama berhasil ditambahkan!",
                'timestamp' => date('Y-m-d H:i:s')
            ];
        } else {
            throw new Exception("Gagal menambahkan santri: " . mysqli_error($conn));
        }
        
        mysqli_stmt_close($stmt);
        header("Location: index.php");
        exit;
        
    } catch (Exception $e) {
        $_SESSION['operation_result'] = [
            'success' => false,
            'message' => $e->getMessage(),
            'timestamp' => date('Y-m-d H:i:s')
        ];
        header("Location: index.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
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
        .form-body {
            padding: 0 2rem 2rem;
        }
        .form-label {
            font-weight: 500;
            color: var(--secondary-color);
        }
        .form-control {
            border-radius: 5px;
            padding: 10px 15px;
            border: 1px solid #ddd;
            transition: all 0.3s;
        }
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(52, 152, 219, 0.25);
        }
        .btn-submit {
            background-color: var(--success-color);
            border-color: var(--success-color);
            padding: 10px 25px;
            font-weight: 500;
            transition: all 0.3s;
        }
        .btn-submit:hover {
            background-color: #219653;
            transform: translateY(-2px);
        }
        .btn-cancel {
            padding: 10px 25px;
            transition: all 0.3s;
        }
        .btn-cancel:hover {
            transform: translateY(-2px);
        }
        .input-icon {
            position: relative;
        }
        .input-icon i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary-color);
        }
        .input-icon input {
            padding-left: 40px;
        }
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
                    <div class="mb-4 input-icon">
                        <label for="nama" class="form-label">Nama Lengkap</label>
                        <i class="fas fa-user"></i>
                        <input type="text" id="nama" name="nama" class="form-control" 
                               placeholder="Masukkan nama lengkap santri" required
                               minlength="3" maxlength="100">
                        <div class="form-text">Contoh: Ahmad Budiman</div>
                    </div>
                    
                    <div class="mb-4 input-icon">
                        <label for="kelas" class="form-label">Kelas</label>
                        <i class="fas fa-graduation-cap"></i>
                        <input type="text" id="kelas" name="kelas" class="form-control" 
                               placeholder="Masukkan kelas santri" required
                               minlength="2" maxlength="10">
                        <div class="form-text">Contoh: 7A, 8B, 9C</div>
                    </div>
                    
                    <div class="mb-4 input-icon">
                        <label for="kamar" class="form-label">Nomor Kamar</label>
                        <i class="fas fa-door-open"></i>
                        <input type="text" id="kamar" name="kamar" class="form-control" 
                               placeholder="Masukkan nomor kamar" required
                               minlength="1" maxlength="10">
                        <div class="form-text">Contoh: A1, B2, C3</div>
                    </div>
                    
                    <div class="d-flex justify-content-between pt-3">
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
    <script>
        // Form validation
        document.getElementById('santriForm').addEventListener('submit', function(e) {
            // Additional client-side validation can be added here
            const nama = document.getElementById('nama').value.trim();
            const kelas = document.getElementById('kelas').value.trim();
            const kamar = document.getElementById('kamar').value.trim();
            
            if (nama.length < 3 || kelas.length < 1 || kamar.length < 1) {
                e.preventDefault();
                alert('Harap isi semua field dengan benar!');
                return false;
            }
            return true;
        });
    </script>
</body>
</html>

<?php require_once __DIR__ . '/../footer.php'; ?>
<?php ob_end_flush(); ?>