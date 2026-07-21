<?php
// BAGIAN 1: LOGIKA RUANG MESIN (SEBELUM ADA TAMPILAN APAPUN)
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../bootstrap/init.php';
guard('santri_create'); 

// Generate CSRF token
$csrf_token = csrf_generate();

// Logika proses form HANYA jika ada request POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validasi CSRF
    csrf_validate();
    // ... (Logika PHP kamu udah bener, gw gak ubah) ...
    $santri_list = explode("\n", $_POST['list_santri']);
    $success_count = 0;
    $error_count = 0;
    $errors = [];
    
    $query = "INSERT INTO santri (nis, nama, kelas, kamar) VALUES (?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $query);

    // Siapkan statement untuk ngecek NIS duplikat di database
    $stmt_check = mysqli_prepare($conn, "SELECT id FROM santri WHERE nis = ?");
    $seen_nis = []; // Untuk melacak duplikat di dalam file CSV yang sama

    foreach ($santri_list as $index => $line) {
        $line_number = $index + 1;
        
        if (empty(trim($line))) continue;

        $data = array_map('trim', explode(',', $line));
            
        if (count($data) >= 3 && count($data) <= 4) {
            $nama = $data[0];
            $kelas_raw = $data[1];
            $kamar_raw = $data[2];
            $nis = isset($data[3]) && trim($data[3]) !== '' ? trim($data[3]) : null;
            
            if (empty($nama) || empty($kelas_raw) || empty($kamar_raw)) {
                $errors[] = "Baris $line_number: Data tidak lengkap";
                $error_count++;
                continue;
            }
            
            $kelas = intval($kelas_raw);
            $kamar = intval($kamar_raw);

            // Validasi duplikat NIS
            if ($nis !== null) {
                // 1. Cek duplikat di dalam file CSV yang sama
                if (in_array($nis, $seen_nis)) {
                    $errors[] = "Baris $line_number ($nama): Gagal - NIS '$nis' ganda/kembar di dalam file CSV ini.";
                    $error_count++;
                    continue;
                }
                
                // 2. Cek duplikat di database
                mysqli_stmt_bind_param($stmt_check, "s", $nis);
                mysqli_stmt_execute($stmt_check);
                $res_check = mysqli_stmt_get_result($stmt_check);
                if (mysqli_num_rows($res_check) > 0) {
                    $errors[] = "Baris $line_number ($nama): Gagal - NIS '$nis' sudah terdaftar di database.";
                    $error_count++;
                    continue;
                }
                
                $seen_nis[] = $nis;
            }

            mysqli_stmt_bind_param($stmt, "ssii", $nis, $nama, $kelas, $kamar);
            
            if (mysqli_stmt_execute($stmt)) {
                $success_count++;
            } else {
                $errors[] = "Baris $line_number ($nama): Gagal menyimpan - " . mysqli_error($conn);
                $error_count++;
            }
        } else {
            $errors[] = "Baris $line_number: Format tidak valid";
            $error_count++;
        }
    }
    
    mysqli_stmt_close($stmt);
    if (isset($stmt_check)) { mysqli_stmt_close($stmt_check); }
    
    $_SESSION['bulk_upload_result'] = [
        'success' => $success_count,
        'error'   => $error_count,
        'errors'  => $errors
    ];

    if ($success_count > 0) {
        write_activity_log('CREATE', 'santri', "Bulk import $success_count santri baru sekaligus (error: $error_count)");
    }

    header("Location: index.php");
    exit;
}

// BAGIAN 2: PERSIAPAN TAMPILAN WAHANA
require_once __DIR__ . '/../layouts/header.php';
?>

<style>
    :root {
        --primary-color: #3498db;
        --secondary-color: #2c3e50;
    }
    .container-bulk-create {
        max-width: 800px;
    }
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

    /* --- PERUBAHAN DI SINI --- */
    /* Gw ganti dari 'textarea' jadi 'textarea#list_santri' biar lebih spesifik */
    textarea#list_santri {
        min-height: 550px; 
        font-family: monospace;
        /* Scrollbar akan otomatis muncul jika kontennya melebihi tinggi ini */
    }

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
        .form-actions .btn {
            padding: .375rem .75rem;
            font-size: 1rem;
        }
        /* Menyesuaikan tinggi textarea di HP */
        textarea#list_santri {
            min-height: 400px;
        }
    }
</style>

<div class="container container-bulk-create mt-4 mb-5">
    <div class="page-title-card text-center">
        <h2 class="text-white"><i class="fas fa-users-cog me-2 text-white"></i> Bulk Input Santri</h2>
        <p class="mb-0 text-white">Tambahkan banyak santri sekaligus menggunakan format CSV sederhana</p>
    </div>

    <div class="form-container">
        <form method="post" id="bulkForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <div class="mb-4">
                <h5><i class="fas fa-info-circle text-primary me-2"></i>Petunjuk Format Data</h5>
                <div class="example-box">
                    <p>Gunakan format berikut untuk setiap santri (pisahkan dengan koma):</p>
                    <p class="mb-1"><strong>Nama,Kelas,Kamar,NIS(Opsional)</strong></p>
                    <p class="text-muted small mb-2">Contoh:</p>
                    <pre class="mb-0">Raffa,10,4,123456
Fauzan,11,2,
Luqman,12,3</pre>
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
            showAlert('Silakan masukkan data santri terlebih dahulu!', 'warning');
            textarea.focus();
        }
    });
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>