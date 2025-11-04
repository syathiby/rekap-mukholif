<?php
// File: rekap-mukholif/rapot/generate_pdf.php

// 1. Panggil semua file penting
require_once __DIR__ . '/../init.php'; 
require_once __DIR__ . '/../vendor/autoload.php'; 
require_once __DIR__ . '/helper.php'; 

// 2. Cek Izin "SATPAM"
guard('rapot_cetak');

// 3. Ambil ID Rapot dari URL
if (empty($_GET['id'])) {
    die('Error: ID Rapot tidak ditemukan.');
}
$rapot_id = (int)$_GET['id'];

// 4. Ambil Data Rapot (Query 1)
try {
    $sql = "
        SELECT 
            r.*, 
            s.id AS santri_id, s.nama AS nama_santri, s.kamar AS kamar_santri, s.kelas AS kelas_santri,
            u.nama_lengkap AS nama_musyrif
        FROM rapot_kepengasuhan r
        LEFT JOIN santri s ON r.santri_id = s.id
        LEFT JOIN users u ON r.musyrif_id = u.id
        WHERE r.id = ?
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $rapot_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $rapot = $result->fetch_assoc();
    $stmt->close();

    if (!$rapot) {
        die('Error: Data rapot tidak ditemukan.');
    }

    // Ambil rincian pelanggaran (Query 2)
    $pelanggaran_list = [];
    $sql_pelanggaran = "
        SELECT jp.nama_pelanggaran, jp.poin
        FROM pelanggaran p
        JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id
        WHERE p.santri_id = ? 
          AND MONTH(p.tanggal) = FIND_IN_SET(?, 'Januari,Februari,Maret,April,Mei,Juni,Juli,Agustus,September,Oktober,November,Desember')
          AND YEAR(p.tanggal) = ?
          AND jp.poin > 0
        ORDER BY p.tanggal DESC
    ";
    
    $stmt_pelanggaran = $conn->prepare($sql_pelanggaran);
    $stmt_pelanggaran->bind_param("isi", $rapot['santri_id'], $rapot['bulan'], $rapot['tahun']);
    $stmt_pelanggaran->execute();
    $pelanggaran_list = $stmt_pelanggaran->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_pelanggaran->close();

} catch (Exception $e) {
    die('Error querying database: ' . $e->getMessage());
}

// 5. Siapin data buat dikirim ke template
$santri = [
    'nama' => $rapot['nama_santri'] ?? 'Santri Dihapus',
    'kamar' => $rapot['kamar_santri'] ?? 'N/A',
    'kelas' => $rapot['kelas_santri'] ?? 'N/A'
];
$musyrif = [
    'nama_lengkap' => $rapot['nama_musyrif'] ?? 'User Dihapus'
];

// 6. Setting Path Logo
$logo_path = __DIR__ . '/../assets/Kop Syathiby.jpg';
if (!file_exists($logo_path)) $logo_path = ''; 

// 7. Proses 'Magic' mPDF
ob_start(); 
include 'template_rapot.php'; 
$html = ob_get_contents();
ob_end_clean(); 

try {
    // 8. Konfigurasi mPDF
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'margin_left' => 10,
        'margin_right' => 10,
        'margin_top' => 7,
        'margin_bottom' => 7,
    ]);

    // 9. Tulis HTML ke PDF
    $mpdf->WriteHTML($html);

    // 10. Tampilkan/Download PDF
    
    // ==========================================================
    //           PERBAIKAN NAMA FILE DI SINI
    //  Kita tambahin $rapot['tahun']
    // ==========================================================
    $nama_file = "Rapot " . $santri['nama'] . " - " . $rapot['bulan'] . " " . $rapot['tahun'] . ".pdf";
    
    $mpdf->Output($nama_file, \Mpdf\Output\Destination::DOWNLOAD); 
    
} catch (\Mpdf\MpdfException $e) {
    echo 'Error mPDF: ' . $e->getMessage();
}

exit;
?>