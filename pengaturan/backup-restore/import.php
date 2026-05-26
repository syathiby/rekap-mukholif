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
    
    // Gunakan compress.zlib:// agar PHP otomatis mengekstrak file GZIP secara transparan.
    // Hebatnya, jika file-nya ternyata .sql biasa (tidak dikompres), ini tetap bekerja normal!
    $sql_content = file_get_contents('compress.zlib://' . $tmp_name);
    
    if (empty(trim($sql_content))) {
        set_flash_message('File kosong atau tidak valid.', 'danger');
        header('Location: index.php');
        exit;
    }

    // Set resource limits untuk file berukuran besar
    set_time_limit(0);
    ini_set('memory_limit', '512M');
    
    // Nonaktifkan Foreign Key Check sementara
    $conn->query("SET FOREIGN_KEY_CHECKS=0");
    
    $success = true;
    $error_msg = '';

    // Gunakan multi_query agar parsing SQL ditangani langsung oleh MySQL C API
    if ($conn->multi_query($sql_content)) {
        do {
            if ($result = $conn->store_result()) {
                $result->free();
            }
            if ($conn->errno) {
                $success = false;
                $error_msg = $conn->error;
                break; // Hentikan eksekusi jika ada error fatal
            }
        } while ($conn->more_results() && $conn->next_result());
    } else {
        $success = false;
        $error_msg = $conn->error;
    }
    
    // Aktifkan kembali Foreign Key Check
    $conn->query("SET FOREIGN_KEY_CHECKS=1");
    
    if ($success) {
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
