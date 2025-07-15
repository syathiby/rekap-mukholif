<?php
ob_start();
session_start();
include '../db.php';

// Secure the ID parameter
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch santri data using prepared statement
$stmt = mysqli_prepare($conn, "SELECT * FROM santri WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$row) {
    $_SESSION['error_message'] = "Santri tidak ditemukan!";
    header("Location: index.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Validate and sanitize input
        $nama = mysqli_real_escape_string($conn, htmlspecialchars(trim($_POST['nama'])));
        $kelas = mysqli_real_escape_string($conn, htmlspecialchars(trim($_POST['kelas'])));
        $kamar = mysqli_real_escape_string($conn, htmlspecialchars(trim($_POST['kamar'])));

        // Validate required fields
        if (empty($nama) || empty($kelas) || empty($kamar)) {
            throw new Exception("Semua field harus diisi!");
        }

        // Update data using prepared statement
        $stmt = mysqli_prepare($conn, "UPDATE santri SET nama=?, kelas=?, kamar=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, "sssi", $nama, $kelas, $kamar, $id);
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success_message'] = "Data santri berhasil diperbarui!";
            header("Location: index.php");
            exit;
        } else {
            throw new Exception("Gagal memperbarui data: " . mysqli_error($conn));
        }
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
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
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .edit-container {
            max-width: 600px;
            margin: 2rem auto;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .edit-header {
            background: linear-gradient(135deg, var(--secondary-color), var(--warning-color));
            color: white;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            position: relative;
        }
        .edit-header:after {
            content: "";
            position: absolute;
            bottom: -10px;
            left: 0;
            right: 0;
            height: 10px;
            background: linear-gradient(135deg, var(--warning-color), transparent);
        }
        .edit-body {
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
            border-color: var(--warning-color);
            box-shadow: 0 0 0 0.25rem rgba(243, 156, 18, 0.25);
        }
        .btn-update {
            background-color: var(--warning-color);
            border-color: var(--warning-color);
            padding: 10px 25px;
            font-weight: 500;
            transition: all 0.3s;
        }
        .btn-update:hover {
            background-color: #e67e22;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .input-icon {
            position: relative;
        }
        .input-icon i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--warning-color);
        }
        .input-icon input {
            padding-left: 40px;
        }
        .current-photo {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 5px;
            border: 2px solid #eee;
        }
        @media (max-width: 576px) {
            .edit-container {
                margin: 1rem;
            }
            .edit-body {
                padding: 0 1rem 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="edit-container">
        <div class="edit-header text-center">
            <h2><i class="fas fa-user-edit me-2"></i>Edit Data Santri</h2>
            <p class="mb-0">ID Santri: <?= $id ?></p>
        </div>
        
        <div class="edit-body">
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?= $_SESSION['error_message'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            <form method="post" id="editForm">
                <div class="mb-4 input-icon">
                    <label for="nama" class="form-label">Nama Lengkap</label>
                    <i class="fas fa-user"></i>
                    <input type="text" id="nama" name="nama" class="form-control" 
                           value="<?= htmlspecialchars($row['nama']) ?>" 
                           placeholder="Masukkan nama lengkap santri" required
                           minlength="3" maxlength="100">
                </div>
                
                <div class="mb-4 input-icon">
                    <label for="kelas" class="form-label">Kelas</label>
                    <i class="fas fa-graduation-cap"></i>
                    <input type="text" id="kelas" name="kelas" class="form-control" 
                           value="<?= htmlspecialchars($row['kelas']) ?>" 
                           placeholder="Masukkan kelas santri" required
                           minlength="2" maxlength="10">
                </div>
                
                <div class="mb-4 input-icon">
                    <label for="kamar" class="form-label">Nomor Kamar</label>
                    <i class="fas fa-door-open"></i>
                    <input type="text" id="kamar" name="kamar" class="form-control" 
                           value="<?= htmlspecialchars($row['kamar']) ?>" 
                           placeholder="Masukkan nomor kamar" required
                           minlength="1" maxlength="10">
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
    <script>
        // Form validation
        document.getElementById('editForm').addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Memproses...';
            submitBtn.disabled = true;
            
            // Additional validation can be added here
            return true;
        });

        // Auto-focus first field
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('nama').focus();
        });
    </script>
</body>
</html>

<?php require_once __DIR__ . '/../footer.php'; ?>