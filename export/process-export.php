<?php
// Pastikan lu udah install PhpSpreadsheet lewat Composer
// Jalankan: composer require phpoffice/phpspreadsheet
require '../vendor/autoload.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
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

    if ($highestRow < 1) return; // Jangan styling sheet kosong (kondisi diubah dari == 0)

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

    // ==========================================================
    // --- TAMBAHIN INI BUAT AKTIFIN FITUR FILTER OTOMATIS ---
    $sheet->setAutoFilter('A1:' . $highestColumn . '1');
    // ==========================================================
}


// Cek apakah tombol export ditekan
if (isset($_POST['export'])) {

    // Ambil data dari form
    $tanggal_mulai = $_POST['tanggal_mulai'] ?? date('Y-m-01');
    $tanggal_selesai = $_POST['tanggal_selesai'] ?? date('Y-m-t');
    $kamar = $_POST['kamar'] ?? 'semua';

    // Buat objek Spreadsheet baru
    $spreadsheet = new Spreadsheet();

    // =================================================================================
    // --- 1. MEMBUAT SHEET: LAPORAN DETAIL ---
    // =================================================================================
    $sheetDetail = $spreadsheet->getActiveSheet();
    $sheetDetail->setTitle('Detail Pelanggaran');
    
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

    $stmt = $conn->prepare($sqlDetail);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $rowNum = 2; $no = 1;
        while ($row = $result->fetch_assoc()) {
            array_unshift($row, $no);
            $sheetDetail->fromArray($row, NULL, 'A' . $rowNum);
            $rowNum++; $no++;
        }
    }
    $stmt->close();
    applySheetStyles($sheetDetail);


    // =================================================================================
    // --- 2. MEMBUAT SHEET: REKAP PER SANTRI ---
    // =================================================================================
    $sheetRekapSantri = $spreadsheet->createSheet();
    $sheetRekapSantri->setTitle('Rekap Per Santri');

    $headersSantri = ['No', 'Nama Santri', 'Kelas', 'Kamar', 'Jumlah Pelanggaran', 'Total Poin'];
    $sheetRekapSantri->fromArray($headersSantri, NULL, 'A1');

    $sqlSantri = "SELECT s.nama, s.kelas, s.kamar, COUNT(p.id) as jumlah_pelanggaran, SUM(jp.poin) as total_poin
                  FROM pelanggaran p
                  JOIN santri s ON p.santri_id = s.id
                  JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id
                  WHERE DATE(p.tanggal) BETWEEN ? AND ? AND jp.bagian <> 'Pengabdian'";
    // Params dan types sama dengan query sebelumnya, jadi bisa dipakai lagi
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
            array_unshift($row, $no);
            $sheetRekapSantri->fromArray($row, NULL, 'A' . $rowNum);
            $rowNum++; $no++;
        }
    }
    $stmt->close();
    applySheetStyles($sheetRekapSantri);


    // =================================================================================
    // --- 3. MEMBUAT SHEET: REKAP PER KAMAR ---
    // =================================================================================
    $sheetRekapKamar = $spreadsheet->createSheet();
    $sheetRekapKamar->setTitle('Rekap Per Kamar');

    $headersKamar = ['No', 'Kamar', 'Jumlah Pelanggaran'];
    $sheetRekapKamar->fromArray($headersKamar, NULL, 'A1');

    $sqlKamar = "SELECT s.kamar, COUNT(p.id) as jumlah_pelanggaran
                 FROM pelanggaran p
                 JOIN santri s ON p.santri_id = s.id
                 JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id
                 WHERE DATE(p.tanggal) BETWEEN ? AND ? AND jp.bagian <> 'Pengabdian'";
    // Params dan types sama dengan query sebelumnya, jadi bisa dipakai lagi
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
            // Urutkan data sesuai header ['No', 'Kamar', 'Jumlah Pelanggaran']
            $rowData = [$no, $row['kamar'], $row['jumlah_pelanggaran']];
            $sheetRekapKamar->fromArray($rowData, NULL, 'A' . $rowNum);
            $rowNum++; $no++;
        }
    }
    $stmt->close();
    applySheetStyles($sheetRekapKamar);


    // =================================================================================
    // --- PROSES DOWNLOAD FILE ---
    // =================================================================================
    
    // Set sheet pertama yang aktif saat file dibuka
    $spreadsheet->setActiveSheetIndex(0);

    $kamarLabel = ($kamar === 'semua') ? 'Semua_Kamar' : 'Kamar_' . str_replace(' ', '_', $kamar);
    $namaFile = 'Laporan_Lengkap_Pelanggaran_' . $kamarLabel . '_' . date('d-m-Y') . '.xlsx';
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $namaFile . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    
    $conn->close();
    exit();

} else {
    // Jika diakses langsung, redirect ke halaman form
    header('Location: export.php');
    exit();
}
?>