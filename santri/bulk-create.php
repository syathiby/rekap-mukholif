<?php
// BAGIAN 1: LOGIKA RUANG MESIN (SEBELUM ADA TAMPILAN APAPUN)
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../init.php';
guard('santri_create'); 

// Logika proses form HANYA jika ada request POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $santri_list = explode("\n", $_POST['list_santri']);
    $success_count = 0;
    $error_count = 0;
    $errors = [];
    
    foreach ($santri_list as $index => $line) {
        $line_number = $index + 1;
        
        if (empty(trim($line))) continue;

        $data = array_map('trim', explode(',', $line));
            
        if (count($data) === 3) {
            list($nama, $kelas, $kamar) = $data;
            
            if (empty($nama) || empty($kelas) || empty($kamar)) {
                $errors[] = "Baris $line_number: Data tidak lengkap (Nama, Kelas, Kamar semua harus diisi)";
                $error_count++;
                continue;
            }
            
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
    
    $_SESSION['bulk_upload_result'] = [
        'success' => $success_count,
        'error' => $error_count,
        'errors' => $errors
    ];
    
    header("Location: index.php");
    exit;
}

// BAGIAN 2: PERSIAPAN TAMPILAN WAHANA
// Setelah semua logika redirect selesai, baru kita panggil header
require_once __DIR__ . '/../header.php';
?>

<style>
    :root {
        --primary-color: #3498db;
        --secondary-color: #2c3e50;
    }
    .container-bulk-create {
        max-width: 800px;
    }
    /* ✅ FIX: Nama class diganti biar ga bentrok */
    .page-title-card {
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
    textarea {
        min-height: 350px; /* ✅ FIX: Textarea diperbesar */
        font-family: monospace;
    }

    /* ✅ FIX: Style khusus mobile */
    @media (max-width: 576px) {
        .page-title-card {
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        .page-title-card h2 {
            font-size: 1.25rem;
        }
        .page-title-card p {
            font-size: 0.875rem;
        }
        .form-container {
            padding: 1.5rem;
        }
        /* ✅ FIX: Tombol dikecilin pas di mobile */
        .form-actions .btn {
            padding: .375rem .75rem; /* Balikin ke ukuran normal */
            font-size: 1rem;
        }
    }
</style>

<div class="container container-bulk-create mt-4 mb-5">
    <!-- ✅ FIX: Nama class diganti -->
    <div class="page-title-card text-center">
        <h2><i class="fas fa-users-cog me-2"></i> Bulk Input Santri</h2>
        <p class="mb-0">Tambahkan banyak santri sekaligus menggunakan format CSV sederhana</p>
    </div>

    <div class="form-container">
        <form method="post" id="bulkForm">
            <div class="mb-4">
                <h5><i class="fas fa-info-circle text-primary me-2"></i>Petunjuk Format Data</h5>
                <div class="example-box">
                    <p>Gunakan format berikut untuk setiap santri (pisahkan dengan koma):</p>
                    <p class="mb-1"><strong>Nama,Kelas,Kamar</strong></p>
                    <p class="text-muted small mb-2">Contoh:</p>
                    <pre class="mb-0">Raffa,10,04
Fauzan,11,02
Luqman,12,03</pre>
                </div>
            </div>

            <div class="mb-4">
                <label for="list_santri" class="form-label fw-bold">
                    <i class="fas fa-paste me-2"></i>Data Santri
                </label>
                <textarea name="list_santri" id="list_santri" class="form-control mb-2" 
                    placeholder="Masukkan data santri disini, satu santri per baris..."></textarea>
                <div class="form-text">
                    <i class="fas fa-lightbulb me-1"></i> Anda bisa copy-paste dari Excel atau text editor lain.
                </div>
            </div>

            <div class="d-flex justify-content-between form-actions">
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

<script>
    document.getElementById('bulkForm').addEventListener('submit', function(e) {
        const textarea = document.getElementById('list_santri');
        if (textarea.value.trim() === '') {
            e.preventDefault();
            // Menggunakan modal dari file utama jika ada, atau alert biasa sebagai fallback
            if (typeof bootstrap !== 'undefined' && document.getElementById('confirmModal')) {
                const confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));
                document.getElementById('confirmModalLabel').textContent = 'Peringatan';
                document.getElementById('confirmModalBody').textContent = 'Silakan masukkan data santri terlebih dahulu!';
                document.getElementById('confirmModalButton').style.display = 'none';
                confirmModal.show();
            } else {
                alert('Silakan masukkan data santri terlebih dahulu!');
            }
            textarea.focus();
        }
    });
</script>

<?php require_once __DIR__ . '/../footer.php'; ?>