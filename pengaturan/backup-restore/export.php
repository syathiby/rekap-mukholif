<?php
require_once __DIR__ . '/../../bootstrap/init.php';

// Proteksi Halaman
guard('backup_restore_manage');

// Set time limit and memory limit for large databases
set_time_limit(0);
ini_set('memory_limit', '512M');

// Cek format yang diminta
$format = $_POST['format'] ?? 'gz';
if (!in_array($format, ['gz', 'sql'])) {
    $format = 'gz';
}

$tables = [];
$result = $conn->query("SHOW TABLES");
if (!$result) {
    set_flash_message('Gagal mengambil daftar tabel: ' . $conn->error, 'danger');
    header("Location: index.php");
    exit;
}
while ($row = $result->fetch_row()) {
    $tables[] = $row[0];
}

$is_gz = ($format === 'gz');
$filename = 'backup_asuhtrack_' . date('Y_m_d_His') . '.sql' . ($is_gz ? '.gz' : '');

// Catat log backup database
write_activity_log('BACKUP', 'backup-restore', "Mengekspor cadangan database: '$filename'", [
    'filename' => $filename,
    'format' => $format
]);

// Persiapan File Handler
if ($is_gz) {
    // Untuk GZIP, kita gunakan temporary file di server agar memory safe
    $tmp_file = tempnam(sys_get_temp_dir(), 'backup_');
    $handle = gzopen($tmp_file, 'w9');
    $write_func = 'gzwrite';
} else {
    // Untuk SQL mentah, kita bisa langsung stream ke output buffer
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Matikan output buffering agar langsung dikirim ke browser
    while (ob_get_level()) {
        ob_end_clean();
    }
    $handle = fopen('php://output', 'w');
    $write_func = 'fwrite';
}

$header_sql = "-- AsuhTrack Database Backup\n-- Date: " . date('Y-m-d H:i:s') . "\n\nSET FOREIGN_KEY_CHECKS=0;\n\n";
$write_func($handle, $header_sql);

foreach ($tables as $table) {
    // Get create table syntax
    $result = $conn->query("SHOW CREATE TABLE `$table`");
    $row = $result->fetch_row();
    $chunk = "-- --------------------------------------------------------\n\n";
    $chunk .= "--\n-- Table structure for table `$table`\n--\n\n";
    $chunk .= "DROP TABLE IF EXISTS `$table`;\n";
    $chunk .= $row[1] . ";\n\n";
    $write_func($handle, $chunk);
    
    // Gunakan metode Chunking (LIMIT & OFFSET) daripada MYSQLI_USE_RESULT.
    // Metode ini mencegah MySQL menutup koneksi (timeout) jika proses kompresi gzwrite lambat.
    // Selain itu tetap hemat RAM karena kita hanya memuat 1000 baris per query.
    $offset = 0;
    $limit = 1000;
    $first_row = true;
    
    while (true) {
        // Gunakan ORDER BY 1 agar urutan chunking LIMIT dan OFFSET stabil
        $result = $conn->query("SELECT * FROM `$table` ORDER BY 1 LIMIT $limit OFFSET $offset");
        
        if (!$result || $result->num_rows == 0) {
            break; // Tidak ada data lagi atau error
        }
        
        $num_fields = $result->field_count;
        
        while ($row_data = $result->fetch_row()) {
            if ($first_row) {
                $write_func($handle, "--\n-- Dumping data for table `$table`\n--\n\n");
                $first_row = false;
            }
            $chunk = "INSERT INTO `$table` VALUES(";
            for ($j = 0; $j < $num_fields; $j++) {
                if (isset($row_data[$j])) {
                    $val = $conn->real_escape_string($row_data[$j]);
                    $val = str_replace(["\n", "\r"], ['\n', '\r'], $val);
                    $chunk .= "'" . $val . "'";
                } else {
                    $chunk .= "NULL";
                }
                
                if ($j < ($num_fields - 1)) {
                    $chunk .= ", ";
                }
            }
            $chunk .= ");\n";
            $write_func($handle, $chunk);
        }
        
        $result->free(); // Bebaskan memori setiap selesai 1 chunk
        $offset += $limit;
    }
        
    if (!$first_row) {
        $write_func($handle, "\n");
    }
}

$write_func($handle, "SET FOREIGN_KEY_CHECKS=1;\n");

// Finalisasi
if ($is_gz) {
    gzclose($handle);
    
    // Kirim header dan file ke browser
    header('Content-Type: application/x-gzip');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($tmp_file));
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    readfile($tmp_file);
    unlink($tmp_file); // Hapus file temporary
} else {
    fclose($handle);
}

exit;
