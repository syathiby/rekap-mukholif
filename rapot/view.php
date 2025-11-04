<?php
// File: rekap-mukholif/rapot/view.php

// 1. Panggil file penting
require_once __DIR__ . '/../init.php'; 
require_once __DIR__ . '/helper.php'; 

// 2. Cek Izin "SATPAM"
guard('rapot_view_detail');

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
    
    // ==========================================================
    //           PERBAIKAN SQL-NYA DI SINI
    //  Kita tambahin 'AND jp.poin > 0'
    // ==========================================================
    $pelanggaran_list = [];
    $sql_pelanggaran = "
        SELECT jp.nama_pelanggaran, jp.poin
        FROM pelanggaran p
        JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id
        WHERE p.santri_id = ? 
          AND MONTH(p.tanggal) = FIND_IN_SET(?, 'Januari,Februari,Maret,April,Mei,Juni,Juli,Agustus,September,Oktober,November,Desember')
          AND YEAR(p.tanggal) = ?
          AND jp.poin > 0  -- <-- INI TAMBAHANNYA
        ORDER BY p.tanggal DESC
    ";
    
    $stmt_pelanggaran = $conn->prepare($sql_pelanggaran);
    $stmt_pelanggaran->bind_param("isi", $rapot['santri_id'], $rapot['bulan'], $rapot['tahun']);
    $stmt_pelanggaran->execute();
    $pelanggaran_list = $stmt_pelanggaran->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_pelanggaran->close();
    // ==========================================================

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

// 6. Setting Path Logo (Pake $base_url)
$logo_path = $base_url . '/assets/Kop Syathiby.jpg';
$logo_file_path = $_SERVER['DOCUMENT_ROOT'] . '/rekap-mukholif/assets/Kop Syathiby.jpg';
if (!file_exists($logo_file_path)) $logo_path = ''; 

/*
 * 7. TAMPILKAN SEBAGAI HTML
 */
echo '<!DOCTYPE html>
<html>
<head>
    <title>View Rapot - ' . htmlspecialchars($santri['nama']) . '</title>
    <style>
        body {
            background-color: #525659;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            margin: 0;
            padding: 20px 0;
        }
        .page-wrapper {
            width: 210mm;
            min-height: 297mm;
            background-color: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.5);
            margin: 20px auto;
            padding: 7mm 10mm 7mm 10mm;
            box-sizing: border-box;
        }
        @media print {
            body, .page-wrapper {
                background-color: white;
                box-shadow: none;
                margin: 0;
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="page-wrapper">';

// 8. Panggil template rapotnya
include 'template_rapot.php';

echo '
    </div>
</body>
</html>';
?>