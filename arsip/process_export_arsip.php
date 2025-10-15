<?php
// Pastikan lu udah install PhpSpreadsheet lewat Composer
// Jalankan: composer require phpoffice/phpspreadsheet
require '../vendor/autoload.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../init.php';
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
    header("Location: index.php"); // Arahkan ke halaman daftar arsip
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
// --- 1. MEMBUAT SHEET: REKAP SEMUA SANTRI (REVISI) ---
// =================================================================================
$sheetRekapSantri = $spreadsheet->getActiveSheet();
$sheetRekapSantri->setTitle('Rekap Per Santri');

$headersSantri = ['No', 'Nama Santri', 'Kelas', 'Kamar', 'Jumlah Pelanggaran', 'Total Poin'];
$sheetRekapSantri->fromArray($headersSantri, NULL, 'A1');

$sqlSantri = "
    SELECT
        s.santri_nama,
        s.santri_kelas,
        s.santri_kamar,
        COALESCE(COUNT(p.id), 0) AS jumlah_pelanggaran,
        COALESCE(SUM(p.poin), 0) AS total_poin
    FROM
        arsip_data_santri s
    LEFT JOIN
        arsip_data_pelanggaran p ON s.santri_id = p.santri_id AND s.arsip_id = p.arsip_id
    WHERE
        s.arsip_id = ?
    GROUP BY
        s.id, s.santri_nama, s.santri_kelas, s.santri_kamar
    ORDER BY
        CAST(s.santri_kelas AS UNSIGNED) ASC,
        CAST(s.santri_kamar AS UNSIGNED) ASC,
        s.santri_nama ASC
";
$stmt = $conn->prepare($sqlSantri);
$stmt->bind_param('i', $arsip_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $rowNum = 2; $no = 1;
    while ($row = $result->fetch_assoc()) {
        $rowData = [$no, $row['santri_nama'], $row['santri_kelas'], $row['santri_kamar'], $row['jumlah_pelanggaran'], $row['total_poin']];
        $sheetRekapSantri->fromArray($rowData, NULL, 'A' . $rowNum);
        $rowNum++; $no++;
    }
}
$stmt->close();
applySheetStyles($sheetRekapSantri);

// =================================================================================
// --- 2. MEMBUAT SHEET: REKAP PER KAMAR (GABUNGAN) ---
// =================================================================================
$sheetRekapKamar = $spreadsheet->createSheet();
$sheetRekapKamar->setTitle('Rekap Per Kamar (Gabungan)');

$headersKamar = ['No', 'Kamar', 'Total Pelanggaran (Gabungan)'];
$sheetRekapKamar->fromArray($headersKamar, NULL, 'A1');

$sqlKamar = "
    SELECT
        kamar,
        SUM(jumlah_pelanggaran) as total_pelanggaran
    FROM (
        SELECT santri_kamar as kamar, COUNT(id) as jumlah_pelanggaran
        FROM arsip_data_pelanggaran
        WHERE arsip_id = ? AND santri_kamar IS NOT NULL AND santri_kamar != ''
        GROUP BY santri_kamar
        
        UNION ALL
        
        SELECT kamar, COUNT(id) as jumlah_pelanggaran
        FROM arsip_data_pelanggaran_kebersihan
        WHERE arsip_id = ?
        GROUP BY kamar
    ) as combined_data
    GROUP BY kamar
    ORDER BY CAST(kamar AS UNSIGNED) ASC
";
$stmt = $conn->prepare($sqlKamar);
$stmt->bind_param('ii', $arsip_id, $arsip_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $rowNum = 2; $no = 1;
    while ($row = $result->fetch_assoc()) {
        $rowData = [$no, $row['kamar'], $row['total_pelanggaran']];
        $sheetRekapKamar->fromArray($rowData, NULL, 'A' . $rowNum);
        $rowNum++; $no++;
    }
}
$stmt->close();
applySheetStyles($sheetRekapKamar);

// =================================================================================
// --- 3. MEMBUAT SHEET: REKAP KEBERSIHAN PER KAMAR (REVISI) ---
// =================================================================================
$sheetKebersihan = $spreadsheet->createSheet();
$sheetKebersihan->setTitle('Rekap Kebersihan Per Kamar');

$headersKebersihan = ['No', 'Kamar', 'Jumlah Pelanggaran'];
$sheetKebersihan->fromArray($headersKebersihan, NULL, 'A1');

$sqlKebersihan = "
    SELECT
        kamar,
        COUNT(id) as jumlah_pelanggaran
    FROM
        arsip_data_pelanggaran_kebersihan
    WHERE
        arsip_id = ?
    GROUP BY
        kamar
    ORDER BY
        jumlah_pelanggaran DESC
";
$stmt = $conn->prepare($sqlKebersihan);
$stmt->bind_param('i', $arsip_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $rowNum = 2; $no = 1;
    while ($row = $result->fetch_assoc()) {
        $rowData = [$no, $row['kamar'], $row['jumlah_pelanggaran']];
        $sheetKebersihan->fromArray($rowData, NULL, 'A' . $rowNum);
        $rowNum++; $no++;
    }
}
$stmt->close();
applySheetStyles($sheetKebersihan);


// =================================================================================
// --- 4. MEMBUAT SHEET: DETAIL PELANGGARAN UMUM (TETAP ADA) ---
// =================================================================================
$sheetPelanggaran = $spreadsheet->createSheet();
$sheetPelanggaran->setTitle('Detail Pelanggaran Umum');

$headersPelanggaran = ['No', 'Nama Santri', 'Kelas', 'Kamar', 'Jenis Pelanggaran', 'Bagian', 'Poin', 'Tanggal'];
$sheetPelanggaran->fromArray($headersPelanggaran, NULL, 'A1');

$sqlPelanggaran = "SELECT santri_nama, santri_kelas, santri_kamar, jenis_pelanggaran_nama, bagian, poin, tanggal FROM arsip_data_pelanggaran WHERE arsip_id = ? ORDER BY tanggal ASC";
$stmt = $conn->prepare($sqlPelanggaran);
$stmt->bind_param('i', $arsip_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $rowNum = 2; $no = 1;
    while ($row = $result->fetch_assoc()) {
        $rowData = [$no, $row['santri_nama'], $row['santri_kelas'], $row['santri_kamar'], $row['jenis_pelanggaran_nama'], $row['bagian'], $row['poin'], $row['tanggal']];
        $sheetPelanggaran->fromArray($rowData, NULL, 'A' . $rowNum);
        $rowNum++; $no++;
    }
}
$stmt->close();
applySheetStyles($sheetPelanggaran);


// =================================================================================
// --- PROSES DOWNLOAD FILE ---
// =================================================================================
$spreadsheet->setActiveSheetIndex(0); // Buka file Excel di sheet 'Rekap Per Santri'

$safe_title = preg_replace('/[^a-zA-Z0-9_-]/', '-', strtolower($arsip['judul']));
$namaFile = 'Laporan_Lengkap_Arsip_' . $safe_title . '_' . date('d-m-Y') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $namaFile . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');

$conn->close();
exit();
?>