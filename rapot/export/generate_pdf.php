<?php
// File: rekap-mukholif/rapot/generate_pdf.php

require_once __DIR__ . '/../../bootstrap/init.php'; 
require_once __DIR__ . '/../../vendor/autoload.php'; 
require_once __DIR__ . '/../config/helper.php';

guard('rapot_cetak');

$output_mode = $_GET['output'] ?? 'download';

if (empty($_GET['id'])) {
    http_response_code(400);
    die('Error: ID Rapot tidak ditemukan.');
}
$rapot_id = (int)$_GET['id'];

try {
    $sql = "
        SELECT 
            r.*, 
            s.id AS santri_id, s.nis, s.nama AS nama_santri, s.kamar AS kamar_santri, s.kelas AS kelas_santri,
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
        http_response_code(404);
        die('Error: Data rapot tidak ditemukan.');
    }

    $kamar_filter_musyrif = checkMusyrifKamarAccess();
    if ($kamar_filter_musyrif !== null && (int)$rapot['kamar_santri'] !== $kamar_filter_musyrif) {
        http_response_code(403);
        die('Error: Anda tidak memiliki akses untuk mencetak rapot santri ini (Beda Kamar).');
    }

    // Hitung rentang tanggal dari bulan & tahun rapot
    // Konsisten dengan query di process.php (tidak pakai FIND_IN_SET)
    $bulan_list_indo_pdf = [
        'Januari' => 1, 'Februari' => 2, 'Maret'    => 3, 'April'    => 4,
        'Mei'     => 5, 'Juni'     => 6, 'Juli'     => 7, 'Agustus'  => 8,
        'September' => 9, 'Oktober' => 10, 'November' => 11, 'Desember' => 12
    ];
    $bulan_num_pdf  = $bulan_list_indo_pdf[$rapot['bulan']] ?? 1;
    $start_date_pdf = sprintf('%04d-%02d-01', $rapot['tahun'], $bulan_num_pdf);
    $end_date_pdf   = date('Y-m-t', strtotime($start_date_pdf));

    // Ambil rincian pelanggaran
    $pelanggaran_list = [];
    $sql_pelanggaran = "
        SELECT jp.nama_pelanggaran, SUM(jp.poin) as poin, COUNT(*) as jumlah
        FROM pelanggaran p
        JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id
        WHERE p.santri_id = ?
          AND p.tanggal >= ? AND p.tanggal <= ?
          AND jp.poin > 0
        GROUP BY jp.nama_pelanggaran
        ORDER BY MAX(p.tanggal) DESC
    ";
    $stmt_pelanggaran = $conn->prepare($sql_pelanggaran);
    $stmt_pelanggaran->bind_param("iss", $rapot['santri_id'], $start_date_pdf, $end_date_pdf);
    $stmt_pelanggaran->execute();
    $pelanggaran_list = $stmt_pelanggaran->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_pelanggaran->close();

    // Ambil rincian REWARD
    $reward_list = [];
    $sql_reward = "
        SELECT jr.nama_reward, SUM(jr.poin_reward) AS poin, COUNT(*) as jumlah
        FROM daftar_reward rwd
        JOIN jenis_reward jr ON rwd.jenis_reward_id = jr.id
        WHERE rwd.santri_id = ?
          AND rwd.tanggal >= ? AND rwd.tanggal <= ?
          AND jr.poin_reward > 0
        GROUP BY jr.nama_reward
        ORDER BY MAX(rwd.tanggal) DESC
    ";
    $stmt_reward = $conn->prepare($sql_reward);
    $stmt_reward->bind_param("iss", $rapot['santri_id'], $start_date_pdf, $end_date_pdf);
    $stmt_reward->execute();
    $reward_list = $stmt_reward->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_reward->close();

} catch (Exception $e) {
    http_response_code(500);
    die('Error querying database: ' . $e->getMessage());
}

$santri = [
    'nis' => $rapot['nis'] ?? '-',
    'nama' => $rapot['nama_santri'] ?? 'Santri Dihapus',
    'kamar' => $rapot['kamar_santri'] ?? 'N/A',
    'kelas' => $rapot['kelas_santri'] ?? 'N/A'
];
$musyrif = [
    'nama_lengkap' => $rapot['nama_musyrif'] ?? 'User Dihapus'
];

$logo_path = __DIR__ . '/../assets/img/Kop Syathiby.jpg';
if (!file_exists($logo_path)) $logo_path = ''; 

ob_start(); 
include __DIR__ . '/../config/template_rapot_bulanan.php'; 
$html = ob_get_contents();
ob_end_clean(); 

try {
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'margin_left' => 10,
        'margin_right' => 10,
        'margin_top' => 7,
        'margin_bottom' => 4,
    ]);

    $mpdf->WriteHTML($html);

    $nama_santri_clean = preg_replace("/[^a-zA-Z0-9 ]/", "", $santri['nama']);
    $nama_file = "Rapot {$nama_santri_clean} - {$rapot['bulan']} {$rapot['tahun']}.pdf";
    
    if ($output_mode === 'string') {
        $pdf_content = $mpdf->Output($nama_file, \Mpdf\Output\Destination::STRING_RETURN);
        header('Content-Type: application/pdf');
        echo $pdf_content;
    } else {
        $mpdf->Output($nama_file, \Mpdf\Output\Destination::DOWNLOAD); 
    }
    
} catch (\Mpdf\MpdfException $e) {
    echo 'Error mPDF: ' . $e->getMessage();
}

exit;
?>