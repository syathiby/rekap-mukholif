<?php 
include '../db.php';

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$id = $_GET['id'];
$query = "SELECT * FROM jenis_pelanggaran WHERE id = $id";
$result = mysqli_query($conn, $query);
$data = mysqli_fetch_assoc($result);

if (!$data) {
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Jenis Pelanggaran</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .card-edit {
            max-width: 600px;
            margin: 2rem auto;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .form-label {
            font-weight: 500;
        }
        .btn-submit {
            width: 120px;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="card card-edit">
            <div class="card-header bg-primary text-white">
                <div class="d-flex justify-content-between align-items-center">
                    <h4 class="mb-0"><i class="fas fa-edit me-2"></i>Edit Jenis Pelanggaran</h4>
                    <a href="index.php" class="btn btn-light btn-sm">
                        <i class="fas fa-arrow-left me-1"></i> Kembali
                    </a>
                </div>
            </div>
            
            <div class="card-body">
                <form action="process.php" method="POST">
                    <input type="hidden" name="id" value="<?= $data['id']; ?>">
                    
                    <div class="mb-4">
                        <label for="nama_pelanggaran" class="form-label">Nama Pelanggaran</label>
                        <input type="text" class="form-control form-control-lg" id="nama_pelanggaran" 
                               name="nama_pelanggaran" value="<?= htmlspecialchars($data['nama_pelanggaran']); ?>" 
                               placeholder="Masukkan nama pelanggaran" required>
                        <div class="form-text">Isikan jenis pelanggaran yang akan diperbarui</div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="submit" name="update" class="btn btn-primary btn-submit">
                            <i class="fas fa-save me-1"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>