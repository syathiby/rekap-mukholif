<?php
// Pastikan lu udah install PhpSpreadsheet lewat Composer
// Jalankan: composer require phpoffice/phpspreadsheet
require '../vendor/autoload.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../init.php';
guard('export_laporan');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

// --- FUNGSI BANTUAN BIAR NGGAK NULIS STYLE BERULANG-ULANG ---
function applySheetStyles(Worksheet &$sheet) {
    $highestColumn = $sheet->getHighestColumn();
    $highestRow = $sheet->getHighestRow();

    if ($highestRow < 1) return; // Jangan styling sheet kosong

    // Style untuk header
    $headerStyle = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']] // Biru Indigo
    ];
    // REVISI: Terapkan style header & row height
    $sheet->getStyle('A1:' . $highestColumn . '1')->applyFromArray($headerStyle);
    $sheet->getRowDimension(1)->setRowHeight(25);

    // Auto size kolom biar rapi
    foreach (range('A', $highestColumn) as $columnID) {
        $sheet->getColumnDimension($columnID)->setAutoSize(true);
    }

    // Border untuk semua sel yang ada isinya
    $borderStyle = [
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => 'E5E7EB'], // Gray-200
            ],
        ],
    ];
    $sheet->getStyle('A1:' . $highestColumn . $highestRow)->applyFromArray($borderStyle);
    
    // Set alignment default
    $sheet->getStyle('A2:' . $highestColumn . $highestRow)->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
    $sheet->getStyle('A2:' . $highestColumn . $highestRow)->getAlignment()->setWrapText(true);


    // Aktifkan fitur filter otomatis di header
    $sheet->setAutoFilter('A1:' . $highestColumn . '1');
}

// =================================================================================
// --- LOGIKA UTAMA: CEK TOMBOL MANA YANG DIKLIK ---
// =================================================================================

// Buat objek Spreadsheet baru
$spreadsheet = new Spreadsheet();


// --- OPSI 1: Laporan Pelanggaran Lengkap (4-in-1) ---
if (isset($_POST['export'])) {

    // Ambil data dari form
    $tanggal_mulai = $_POST['tanggal_mulai'] ?? date('Y-m-01');
    $tanggal_selesai = $_POST['tanggal_selesai'] ?? date('Y-m-t');
    $kamar = $_POST['kamar'] ?? 'semua';
    
    // --- 1. MEMBUAT SHEET: LAPORAN DETAIL (PELANGGARAN UMUM) ---
    $sheetDetail = $spreadsheet->getActiveSheet();
    $sheetDetail->setTitle('Detail Pelanggaran Umum');
    
    $headersDetail = ['No', 'Nama Santri', 'Kelas', 'Kamar', 'Nama Pelanggaran', 'Poin', 'Bagian', 'Tanggal'];
    $sheetDetail->fromArray($headersDetail, NULL, 'A1');

    $sqlDetail = "SELECT s.nama, s.kelas, s.kamar, jp.nama_pelanggaran, jp.poin, jp.bagian, p.tanggal
                    FROM pelanggaran p
                    JOIN santri s ON p.santri_id = s.id
                    JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id
                    WHERE DATE(p.tanggal) BETWEEN ? AND ? AND jp.bagian <> 'Pengabdian'";
    
    $params = [$tanggal_mulai, $tanggal_selesai];
    $types = "ss";

    if ($kamar !== 'semua') {
        $sqlDetail .= " AND s.kamar = ?";
        $params[] = $kamar;
        $types .= "s";
    }
    $sqlDetail .= " ORDER BY p.tanggal ASC";

    $stmt = $conn->prepare($sqlDetail);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $rowNum = 2; $no = 1;
        while ($row = $result->fetch_assoc()) {
            $rowData = [$no, $row['nama'], $row['kelas'], $row['kamar'], $row['nama_pelanggaran'], $row['poin'], $row['bagian'], $row['tanggal']];
            $sheetDetail->fromArray($rowData, NULL, 'A' . $rowNum);
            $rowNum++; $no++;
        }
    }
    $stmt->close();
    applySheetStyles($sheetDetail);


    // --- 2. MEMBUAT SHEET: REKAP PER SANTRI (PELANGGARAN UMUM) ---
    $sheetRekapSantri = $spreadsheet->createSheet();
    $sheetRekapSantri->setTitle('Rekap Santri (Umum)');

    $headersSantri = ['No', 'Nama Santri', 'Kelas', 'Kamar', 'Jumlah Pelanggaran', 'Total Poin'];
    $sheetRekapSantri->fromArray($headersSantri, NULL, 'A1');

    $sqlSantri = "SELECT s.nama, s.kelas, s.kamar, COUNT(p.id) as jumlah_pelanggaran, SUM(jp.poin) as total_poin
                    FROM pelanggaran p
                    JOIN santri s ON p.santri_id = s.id
                    JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id
                    WHERE DATE(p.tanggal) BETWEEN ? AND ? AND jp.bagian <> 'Pengabdian'";
    if ($kamar !== 'semua') {
        $sqlSantri .= " AND s.kamar = ?";
    }
    $sqlSantri .= " GROUP BY s.id, s.nama, s.kelas, s.kamar ORDER BY total_poin DESC";

    $stmt = $conn->prepare($sqlSantri);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $rowNum = 2; $no = 1;
        while ($row = $result->fetch_assoc()) {
            $rowData = [$no, $row['nama'], $row['kelas'], $row['kamar'], $row['jumlah_pelanggaran'], $row['total_poin']];
            $sheetRekapSantri->fromArray($rowData, NULL, 'A' . $rowNum);
            $rowNum++; $no++;
        }
    }
    $stmt->close();
    applySheetStyles($sheetRekapSantri);


    // --- 3. MEMBUAT SHEET: REKAP PER KAMAR (PELANGGARAN UMUM) ---
    $sheetRekapKamar = $spreadsheet->createSheet();
    $sheetRekapKamar->setTitle('Rekap Kamar (Umum)');

    $headersKamar = ['No', 'Kamar', 'Jumlah Pelanggaran'];
    $sheetRekapKamar->fromArray($headersKamar, NULL, 'A1');

    $sqlKamar = "SELECT s.kamar, COUNT(p.id) as jumlah_pelanggaran
                   FROM pelanggaran p
                   JOIN santri s ON p.santri_id = s.id
                   JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id
                   WHERE DATE(p.tanggal) BETWEEN ? AND ? AND jp.bagian <> 'Pengabdian'";
    if ($kamar !== 'semua') {
        $sqlKamar .= " AND s.kamar = ?";
    }
    $sqlKamar .= " GROUP BY s.kamar ORDER BY jumlah_pelanggaran DESC";

    $stmt = $conn->prepare($sqlKamar);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $rowNum = 2; $no = 1;
        while ($row = $result->fetch_assoc()) {
            $rowData = [$no, $row['kamar'], $row['jumlah_pelanggaran']];
            $sheetRekapKamar->fromArray($rowData, NULL, 'A' . $rowNum);
            $rowNum++; $no++;
        }
    }
    $stmt->close();
    applySheetStyles($sheetRekapKamar);


    // --- 4. REVISI: REKAP PELANGGARAN KEBERSIHAN PER KAMAR ---
    $sheetKebersihan = $spreadsheet->createSheet();
    $sheetKebersihan->setTitle('Rekap Kebersihan Kamar');

    $headersKebersihan = ['No', 'Kamar', 'Jumlah Pelanggaran'];
    $sheetKebersihan->fromArray($headersKebersihan, NULL, 'A1');

    $sqlKebersihan = "SELECT kamar, COUNT(id) as jumlah_pelanggaran
                      FROM pelanggaran_kebersihan
                      WHERE DATE(tanggal) BETWEEN ? AND ?";
    
    $paramsKebersihan = [$tanggal_mulai, $tanggal_selesai];
    $typesKebersihan = "ss";

    if ($kamar !== 'semua') {
        $sqlKebersihan .= " AND kamar = ?";
        $paramsKebersihan[] = $kamar;
        $typesKebersihan .= "s";
    }
    $sqlKebersihan .= " GROUP BY kamar ORDER BY jumlah_pelanggaran DESC";

    $stmt = $conn->prepare($sqlKebersihan);
    $stmt->bind_param($typesKebersihan, ...$paramsKebersihan);
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
    
    // Set sheet pertama yang aktif saat file dibuka
    $spreadsheet->setActiveSheetIndex(0);
    
    $kamarLabel = ($kamar === 'semua') ? 'Semua_Kamar' : 'Kamar_' . str_replace(' ', '_', $kamar);
    $namaFile = 'Laporan_Lengkap_Pelanggaran_' . $kamarLabel . '_' . date('d-m-Y') . '.xlsx';


// --- OPSI 2: Export Master Data Santri (Urutan V11.0) ---
} elseif (isset($_POST['export_santri'])) {

    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Data Santri');
    
    $headers = ['ID Santri', 'Nama Lengkap', 'Kelas', 'Kamar', 'Poin Aktif'];
    $sheet->fromArray($headers, NULL, 'A1');
    
    // Urutan dari V11.0
    $sql = "SELECT id, nama, kelas, kamar, poin_aktif 
            FROM santri 
            ORDER BY CAST(kamar AS UNSIGNED) ASC, kelas ASC, nama ASC"; // <-- Pakai CAST biar urutan kamar bener
            
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $rowNum = 2;
        while ($row = $result->fetch_assoc()) {
            $rowData = [$row['id'], $row['nama'], $row['kelas'], $row['kamar'], $row['poin_aktif']];
            $sheet->fromArray($rowData, NULL, 'A' . $rowNum);
            $rowNum++;
        }
    }
    
    applySheetStyles($sheet);
    $namaFile = 'Master_Data_Santri_' . date('d-m-Y') . '.xlsx';


// --- OPSI 3: Export Master Data Jenis Pelanggaran (REVISI V12.0) ---
} elseif (isset($_POST['export_jenis_pelanggaran'])) {

    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Data Jenis Pelanggaran');
    
    $headers = ['ID', 'Nama Pelanggaran', 'Poin', 'Kategori', 'Bagian'];
    $sheet->fromArray($headers, NULL, 'A1');
    
    // REVISI V12.0: Ubah ORDER BY sesuai permintaan (pake 2x FIELD())
    // 1. Urutkan berdasarkan Bagian (sesuai list, TAHFIDZ ditambahin)
    // 2. Urutkan berdasarkan Kategori (sesuai logika, bukan abjad)
    // 3. Urutkan berdasarkan Poin (kecil ke besar)
    // 4. Urutkan berdasarkan Nama (A-Z)
    $sql = "SELECT id, nama_pelanggaran, poin, kategori, bagian 
            FROM jenis_pelanggaran 
            ORDER BY 
                FIELD(bagian, 'Kesantrian', 'Bahasa', 'Diniyyah', 'TAHFIDZ', 'Pengabdian'),
                FIELD(kategori, 'Ringan', 'Sedang', 'Berat', 'Sangat Berat'),
                poin ASC,
                nama_pelanggaran ASC"; // <-- REVISI DI SINI
                
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $rowNum = 2;
        while ($row = $result->fetch_assoc()) {
            $rowData = [$row['id'], $row['nama_pelanggaran'], $row['poin'], $row['kategori'], $row['bagian']];
            $sheet->fromArray($rowData, NULL, 'A' . $rowNum);
            $rowNum++;
        }
    }
    
    applySheetStyles($sheet);
    $namaFile = 'Master_Data_Jenis_Pelanggaran_' . date('d-m-Y') . '.xlsx';


// --- FALLBACK: Jika tidak ada tombol yang ditekan ---
} else {
    // Jika diakses langsung, redirect ke halaman form
    header('Location: index.php');
    exit();
}


// =================================================================================
// --- PROSES DOWNLOAD FILE (Berlaku untuk semua OPSI) ---
// =================================================================================

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $namaFile . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');

$conn->close();
exit();
?>