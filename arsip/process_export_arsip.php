<?php
// Pastikan lu udah install PhpSpreadsheet lewat Composer
// Jalankan: composer require phpoffice/phpspreadsheet
require '../vendor/autoload.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
guard('arsip_export');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

// --- FUNGSI BANTUAN UNTUK STYLING SHEET ---
function applySheetStyles(Worksheet &$sheet) {
    $highestColumn = $sheet->getHighestColumn();
    $highestRow = $sheet->getHighestRow();

    if ($highestRow < 1) return; // Jangan styling sheet kosong

    // Style untuk header
    $headerStyle = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']]
    ];
    $sheet->getStyle('A1:' . $highestColumn . '1')->applyFromArray($headerStyle);

    // Auto size kolom biar rapi
    foreach (range('A', $highestColumn) as $columnID) {
        $sheet->getColumnDimension($columnID)->setAutoSize(true);
    }

    // Border untuk semua sel yang ada isinya
    $borderStyle = [
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => 'E5E7EB'],
            ],
        ],
    ];
    $sheet->getStyle('A1:' . $highestColumn . $highestRow)->applyFromArray($borderStyle);

    // Aktifkan fitur filter otomatis di header
    $sheet->setAutoFilter('A1:' . $highestColumn . '1');
}

// Pastikan request adalah POST dan arsip_id ada
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['arsip_id'])) {
    header("Location: export_arsip.php");
    exit();
}

$arsip_id = (int)$_POST['arsip_id'];

// 1. Dapatkan informasi arsip untuk penamaan file
$stmt = $conn->prepare("SELECT judul FROM arsip WHERE id = ?");
$stmt->bind_param('i', $arsip_id);
$stmt->execute();
$result = $stmt->get_result();
$arsip = $result->fetch_assoc();
$stmt->close();

if (!$arsip) {
    die("Arsip tidak ditemukan.");
}

// Buat objek Spreadsheet baru
$spreadsheet = new Spreadsheet();

// =================================================================================
// --- 1. MEMBUAT SHEET: DATA SANTRI ---
// =================================================================================
$sheetSantri = $spreadsheet->getActiveSheet();
$sheetSantri->setTitle('Data Santri');

$headersSantri = ['No', 'Nama Santri', 'Kelas', 'Kamar', 'Total Poin Saat Arsip'];
$sheetSantri->fromArray($headersSantri, NULL, 'A1');

$sqlSantri = "SELECT santri_nama, santri_kelas, santri_kamar, total_poin_saat_arsip FROM arsip_data_santri WHERE arsip_id = ?";
$stmt = $conn->prepare($sqlSantri);
$stmt->bind_param('i', $arsip_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $rowNum = 2; $no = 1;
    while ($row = $result->fetch_assoc()) {
        $rowData = [$no, $row['santri_nama'], $row['santri_kelas'], $row['santri_kamar'], $row['total_poin_saat_arsip']];
        $sheetSantri->fromArray($rowData, NULL, 'A' . $rowNum);
        $rowNum++; $no++;
    }
}
$stmt->close();
applySheetStyles($sheetSantri);

// =================================================================================
// --- 2. MEMBUAT SHEET: PELANGGARAN UMUM ---
// =================================================================================
$sheetPelanggaran = $spreadsheet->createSheet();
$sheetPelanggaran->setTitle('Pelanggaran Umum');

$headersPelanggaran = ['No', 'Nama Santri', 'Kelas', 'Kamar', 'Jenis Pelanggaran', 'Bagian', 'Poin', 'Tanggal', 'Tipe'];
$sheetPelanggaran->fromArray($headersPelanggaran, NULL, 'A1');

$sqlPelanggaran = "SELECT santri_nama, santri_kelas, santri_kamar, jenis_pelanggaran_nama, bagian, poin, tanggal, tipe FROM arsip_data_pelanggaran WHERE arsip_id = ?";
$stmt = $conn->prepare($sqlPelanggaran);
$stmt->bind_param('i', $arsip_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $rowNum = 2; $no = 1;
    while ($row = $result->fetch_assoc()) {
        $rowData = [$no, $row['santri_nama'], $row['santri_kelas'], $row['santri_kamar'], $row['jenis_pelanggaran_nama'], $row['bagian'], $row['poin'], $row['tanggal'], $row['tipe']];
        $sheetPelanggaran->fromArray($rowData, NULL, 'A' . $rowNum);
        $rowNum++; $no++;
    }
}
$stmt->close();
applySheetStyles($sheetPelanggaran);

// =================================================================================
// --- 3. MEMBUAT SHEET: PELANGGARAN KEBERSIHAN ---
// =================================================================================
$sheetKebersihan = $spreadsheet->createSheet();
$sheetKebersihan->setTitle('Pelanggaran Kebersihan');

$headersKebersihan = ['No', 'Kamar', 'Catatan', 'Tanggal', 'Dicatat Oleh'];
$sheetKebersihan->fromArray($headersKebersihan, NULL, 'A1');

$sqlKebersihan = "SELECT kamar, catatan, tanggal, dicatat_oleh_nama FROM arsip_data_pelanggaran_kebersihan WHERE arsip_id = ?";
$stmt = $conn->prepare($sqlKebersihan);
$stmt->bind_param('i', $arsip_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $rowNum = 2; $no = 1;
    while ($row = $result->fetch_assoc()) {
        $rowData = [$no, $row['kamar'], $row['catatan'], $row['tanggal'], $row['dicatat_oleh_nama']];
        $sheetKebersihan->fromArray($rowData, NULL, 'A' . $rowNum);
        $rowNum++; $no++;
    }
}
$stmt->close();
applySheetStyles($sheetKebersihan);

// =================================================================================
// --- PROSES DOWNLOAD FILE ---
// =================================================================================
$spreadsheet->setActiveSheetIndex(0);

$safe_title = preg_replace('/[^a-zA-Z0-9_-]/', '-', strtolower($arsip['judul']));
$namaFile = 'Arsip_' . $safe_title . '_' . date('d-m-Y') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $namaFile . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');

$conn->close();
exit();
?>