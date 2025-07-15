<?php
ob_start(); // HARUS PALING ATAS
session_start();
include '../db.php';
require_once __DIR__ . '/../header.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $santri_list = explode("\n", $_POST['list_santri']);
    $success_count = 0;
    $error_count = 0;
    $errors = [];
    
   foreach ($santri_list as $index => $line) {
    $line_number = $index + 1;
    
    // Skip empty lines **sebelum** explode
    if (empty(trim($line))) continue;

    $data = array_map('trim', explode(',', $line));
        
        if (count($data) === 3) {
            list($nama, $kelas, $kamar) = $data;
            
            // Validate data
            if (empty($nama) || empty($kelas) || empty($kamar)) {
                $errors[] = "Baris $line_number: Data tidak lengkap (Nama, Kelas, Kamar semua harus diisi)";
                $error_count++;
                continue;
            }
            
            // Insert to database
            $query = "INSERT INTO santri (nama, kelas, kamar) VALUES (?, ?, ?)";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "sss", $nama, $kelas, $kamar);
            
            if (mysqli_stmt_execute($stmt)) {
                $success_count++;
            } else {
                $errors[] = "Baris $line_number: Gagal menyimpan - " . mysqli_error($conn);
                $error_count++;
            }
            
            mysqli_stmt_close($stmt);
        } else {
            $errors[] = "Baris $line_number: Format tidak valid (harus Nama,Kelas,Kamar)";
            $error_count++;
        }
    }
    
    // Set session for feedback
    $_SESSION['bulk_upload_result'] = [
        'success' => $success_count,
        'error' => $error_count,
        'errors' => $errors
    ];
    
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Input Santri - Pesantren</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2c3e50;
        }
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .container {
            max-width: 800px;
        }
        .header {
            background: linear-gradient(135deg, var(--secondary-color), var(--primary-color));
            color: white;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        .form-container {
            background-color: white;
            border-radius: 8px;
            padding: 2rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        .example-box {
            background-color: #f8f9fa;
            border-left: 4px solid var(--primary-color);
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: 0 4px 4px 0;
        }
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        .btn-secondary {
            background-color: #6c757d;
            border-color: #6c757d;
        }
        textarea {
            min-height: 300px;
            font-family: monospace;
        }
        .step-indicator {
            display: flex;
            margin-bottom: 2rem;
        }
        .step {
            flex: 1;
            text-align: center;
            padding: 0.5rem;
            position: relative;
        }
        .step-number {
            display: inline-block;
            width: 30px;
            height: 30px;
            line-height: 30px;
            border-radius: 50%;
            background-color: #e9ecef;
            margin-bottom: 0.5rem;
            font-weight: bold;
        }
        .step.active .step-number {
            background-color: var(--primary-color);
            color: white;
        }
        .step::after {
            content: '';
            position: absolute;
            top: 15px;
            left: 50%;
            right: -50%;
            height: 2px;
            background-color: #e9ecef;
            z-index: -1;
        }
        .step:last-child::after {
            display: none;
        }
        .step.active::after {
            background-color: var(--primary-color);
        }
    </style>
</head>
<body>
<div class="container mt-4 mb-5">
    <div class="header text-center">
        <h2><i class="fas fa-users-cog me-2"></i> Bulk Input Santri</h2>
        <p class="mb-0">Tambahkan banyak santri sekaligus menggunakan format CSV sederhana</p>
    </div>

    <div class="step-indicator">
        <div class="step active">
            <div class="step-number">1</div>
            <div>Format Data</div>
        </div>
        <div class="step">
            <div class="step-number">2</div>
            <div>Upload Data</div>
        </div>
        <div class="step">
            <div class="step-number">3</div>
            <div>Konfirmasi</div>
        </div>
    </div>

    <div class="form-container">
        <form method="post" id="bulkForm">
            <div class="mb-4">
                <h5><i class="fas fa-info-circle text-primary me-2"></i>Petunjuk Format Data</h5>
                <div class="example-box">
                    <p>Gunakan format berikut untuk setiap santri:</p>
                    <p class="mb-1"><strong>Nama,Kelas,Kamar</strong></p>
                    <p class="text-muted small mb-2">Contoh:</p>
                    <pre class="mb-0">Ahmad Al-Faruq,7A,A1
Budi Santoso,8B,B2
Siti Aminah,9C,C3</pre>
                </div>
            </div>

            <div class="mb-4">
                <label for="list_santri" class="form-label fw-bold">
                    <i class="fas fa-paste me-2"></i>Data Santri
                </label>
                <textarea name="list_santri" id="list_santri" rows="10" class="form-control mb-2" 
                    placeholder="Masukkan data santri disini, satu santri per baris..."></textarea>
                <div class="form-text">
                    <i class="fas fa-lightbulb me-1"></i> Anda bisa copy-paste dari Excel atau text editor lain
                </div>
            </div>

            <div class="d-flex justify-content-between">
                <a href="index.php" class="btn btn-secondary btn-lg">
                    <i class="fas fa-arrow-left me-2"></i>Kembali
                </a>
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-cloud-upload-alt me-2"></i>Upload Data
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Form validation
    document.getElementById('bulkForm').addEventListener('submit', function(e) {
        const textarea = document.getElementById('list_santri');
        if (textarea.value.trim() === '') {
            e.preventDefault();
            alert('Silakan masukkan data santri terlebih dahulu!');
            textarea.focus();
        }
    });

    // Example data button (for demo purposes)
    document.addEventListener('DOMContentLoaded', function() {
        // You can add a button to insert example data for testing
        const exampleData = `Ahmad Al-Faruq,7A,A1
Budi Santoso,8B,B2
Siti Aminah,9C,C3
Muhammad Hasyim,10A,D4
Fatimah Az-Zahra,8A,B1`;

        // Uncomment to add example button
        /*
        const exampleBtn = document.createElement('button');
        exampleBtn.className = 'btn btn-outline-primary btn-sm mb-3';
        exampleBtn.innerHTML = '<i class="fas fa-magic me-1"></i> Contoh Data';
        exampleBtn.onclick = function() {
            document.getElementById('list_santri').value = exampleData;
        };
        document.querySelector('.example-box').appendChild(exampleBtn);
        */
    });
</script>
</body>
</html>

<?php require_once __DIR__ . '/../footer.php'; ?>