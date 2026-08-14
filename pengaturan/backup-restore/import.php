<?php
require_once __DIR__ . '/../../bootstrap/init.php';

// Proteksi Halaman
guard('backup_restore_manage');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['sql_file'])) {
    
    // Validasi error upload
    if ($_FILES['sql_file']['error'] !== UPLOAD_ERR_OK) {
        set_flash_message('Gagal mengupload file (Kode: ' . $_FILES['sql_file']['error'] . ').', 'danger');
        header('Location: index.php');
        exit;
    }
    
    // Validasi ekstensi
    $file_info = pathinfo($_FILES['sql_file']['name']);
    $ext = strtolower($file_info['extension']);
    if (!in_array($ext, ['sql', 'gz'])) {
        set_flash_message('File harus berupa ekstensi .sql atau .sql.gz', 'danger');
        header('Location: index.php');
        exit;
    }
    
    $tmp_name = $_FILES['sql_file']['tmp_name'];
    
    // Set resource limits untuk file berukuran besar
    set_time_limit(0);
    ini_set('memory_limit', '1024M'); // Naikkan limit untuk berjaga-jaga
    
    // Buka file menggunakan gzopen (mendukung file gzip maupun uncompressed secara otomatis)
    $handle = gzopen($tmp_name, 'r');
    if (!$handle) {
        set_flash_message('Gagal membaca file upload.', 'danger');
        header('Location: index.php');
        exit;
    }

    // Nonaktifkan Foreign Key Check sementara
    $conn->query("SET FOREIGN_KEY_CHECKS=0");
    // Hindari masalah encoding
    $conn->query("SET NAMES 'utf8mb4'");
    
    $success = true;
    $error_msg = '';
    $query = '';

    // Eksekusi per query dengan membaca baris demi baris secara streaming (hemat RAM & anti max_allowed_packet)
    while (!gzeof($handle)) {
        $line = gzgets($handle);
        $trimmed = trim($line);
        
        // Lewati baris kosong atau komentar (hanya jika query sedang kosong)
        if (empty($query) && (empty($trimmed) || strpos($trimmed, '--') === 0 || strpos($trimmed, '/*') === 0)) {
            continue;
        }
        
        $query .= $line;
        
        // Jika baris diakhiri dengan titik koma, artinya satu statement lengkap, maka eksekusi
        if (substr(rtrim($query), -1) === ';') {
            if (!$conn->query($query)) {
                $success = false;
                $error_msg = $conn->error;
                break; // Hentikan eksekusi jika ada error fatal
            }
            $query = ''; // Reset untuk query berikutnya
        }
    }
    
    gzclose($handle);
    
    // Aktifkan kembali Foreign Key Check
    $conn->query("SET FOREIGN_KEY_CHECKS=1");
    
    if ($success) {
        // Catat log restore
        write_activity_log('RESTORE', 'backup-restore', "Melakukan restore/pemulihan database dari file cadangan: '" . htmlspecialchars($_FILES['sql_file']['name']) . "'", [
            'filename' => $_FILES['sql_file']['name'],
            'filesize' => $_FILES['sql_file']['size']
        ]);
        set_flash_message('Restore database berhasil dilakukan! Seluruh data telah diperbarui.', 'success');
    } else {
        set_flash_message('Terjadi error saat restore: ' . $error_msg, 'danger');
    }

    header('Location: index.php');
    exit;
} else {
    // Akses langsung tanpa POST
    header('Location: index.php');
    exit;
}
