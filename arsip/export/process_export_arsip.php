<?php
// Pastikan PhpSpreadsheet terinstall lewat Composer
require_once __DIR__ . '/../../vendor/autoload.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../bootstrap/init.php';
guard('arsip_export');

// Validasi CSRF Token
csrf_validate();
session_write_close();

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

// Pastikan request adalah POST dan arsip_id ada
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['arsip_id'])) {
    header("Location: ../index.php");
    exit();
}

$arsip_id = (int)$_POST['arsip_id'];

// 1. Dapatkan informasi arsip untuk penamaan dan header dokumen
$stmt = $conn->prepare("SELECT judul, keterangan, tanggal_mulai, tanggal_selesai, dibuat_pada FROM arsip WHERE id = ?");
$stmt->bind_param('i', $arsip_id);
$stmt->execute();
$result = $stmt->get_result();
$arsip = $result->fetch_assoc();
$stmt->close();

if (!$arsip) {
    die("Arsip tidak ditemukan.");
}

$periode_str = date('d M Y', strtotime($arsip['tanggal_mulai'])) . ' - ' . date('d M Y', strtotime($arsip['tanggal_selesai']));
$dibuat_str  = date('d M Y, H:i', strtotime($arsip['dibuat_pada']));

// Inisialisasi Spreadsheet
$spreadsheet = new Spreadsheet();
$spreadsheet->getDefaultStyle()->getFont()->setName('Segoe UI')->setSize(10);

/**
 * Helper untuk membangun Header Kop Laporan & Styling Tabel Eksekutif
 */
function buildExecutiveSheet(
    Worksheet &$sheet,
    string $sheetTitle,
    string $reportTitle,
    array $headers,
    array $alignments,
    string $themeColor,
    string $zebraColor,
    array $metaInfo
) {
    $sheet->setTitle($sheetTitle);
    $sheet->setShowGridLines(true);

    $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));

    // ── 1. KOP LAPORAN EKSEKUTIF ──────────────────────────────
    // Baris 1: Judul Utama
    $sheet->mergeCells("A1:{$lastCol}1");
    $sheet->setCellValue("A1", "LAPORAN ARSIP KEPENGASUHAN SANTRI");
    $sheet->getStyle("A1")->getFont()->setBold(true)->setSize(14)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('0F172A'));
    $sheet->getStyle("A1")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->getRowDimension(1)->setRowHeight(24);

    // Baris 2: Nama Sub Judul & Periode
    $sheet->mergeCells("A2:{$lastCol}2");
    $subTitle = $reportTitle . "  |  Arsip: " . $metaInfo['judul'] . "  (" . $metaInfo['periode'] . ")";
    $sheet->setCellValue("A2", $subTitle);
    $sheet->getStyle("A2")->getFont()->setBold(true)->setSize(10)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('475569'));
    $sheet->getStyle("A2")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->getRowDimension(2)->setRowHeight(18);

    // Baris 3: Tanggal Arsip & Metadata Tambahan
    $sheet->mergeCells("A3:{$lastCol}3");
    $noteText = "Diarsipkan pada: " . $metaInfo['dibuat'] . (!empty($metaInfo['keterangan']) ? "  •  Keterangan: " . $metaInfo['keterangan'] : "");
    $sheet->setCellValue("A3", $noteText);
    $sheet->getStyle("A3")->getFont()->setSize(9)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('64748B'));
    $sheet->getStyle("A3")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->getRowDimension(3)->setRowHeight(18);

    // Baris 4: Spacer Kosong
    $sheet->getRowDimension(4)->setRowHeight(8);

    // ── 2. HEADER TABEL (BARIS 5) ─────────────────────────────
    $tableHeaderRow = 5;
    $sheet->getRowDimension($tableHeaderRow)->setRowHeight(28);

    foreach ($headers as $idx => $headerText) {
        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($idx + 1);
        $cellCoord = "{$colLetter}{$tableHeaderRow}";
        $sheet->setCellValue($cellCoord, $headerText);
    }

    $headerStyle = [
        'font' => [
            'bold' => true,
            'color' => ['rgb' => 'FFFFFF'],
            'size' => 10,
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
            'wrapText' => true
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => $themeColor]
        ],
        'borders' => [
            'bottom' => [
                'borderStyle' => Border::BORDER_MEDIUM,
                'color' => ['rgb' => '0F172A']
            ]
        ]
    ];
    $sheet->getStyle("A{$tableHeaderRow}:{$lastCol}{$tableHeaderRow}")->applyFromArray($headerStyle);

    // Freeze Pane agar header tetap terlihat saat scroll
    $sheet->freezePane("A6");

    // Auto Filter pada header tabel
    $sheet->setAutoFilter("A{$tableHeaderRow}:{$lastCol}{$tableHeaderRow}");
}

/**
 * Helper untuk styling baris-baris data
 */
function applyDataRowStyles(
    Worksheet &$sheet,
    int $startRow,
    int $endRow,
    array $alignments,
    string $zebraColor
) {
    if ($endRow < $startRow) {
        // Jika tidak ada data, tulis pesan kosong
        $sheet->setCellValue("A{$startRow}", "Tidak ada data pada arsip ini.");
        $sheet->getStyle("A{$startRow}")->getFont()->setItalic(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('64748B'));
        $sheet->getRowDimension($startRow)->setRowHeight(24);
        return;
    }

    $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($alignments));

    // Style baris demi baris
    for ($row = $startRow; $row <= $endRow; $row++) {
        $sheet->getRowDimension($row)->setRowHeight(21);
        $isEven = ($row % 2 === 0);

        if ($isEven && !empty($zebraColor)) {
            $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFill()
                  ->setFillType(Fill::FILL_SOLID)
                  ->getStartColor()->setRGB($zebraColor);
        }

        // Alignments per cell
        foreach ($alignments as $colIdx => $align) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx + 1);
            $hAlign = Alignment::HORIZONTAL_LEFT;
            if ($align === 'C') $hAlign = Alignment::HORIZONTAL_CENTER;
            elseif ($align === 'R') $hAlign = Alignment::HORIZONTAL_RIGHT;

            $sheet->getStyle("{$colLetter}{$row}")->getAlignment()
                  ->setHorizontal($hAlign)
                  ->setVertical(Alignment::VERTICAL_CENTER);
        }
    }

    // Border tipis pada seluruh tabel data
    $borderStyle = [
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => 'E2E8F0'],
            ],
            'outline' => [
                'borderStyle' => Border::BORDER_MEDIUM,
                'color' => ['rgb' => 'CBD5E1'],
            ]
        ],
    ];
    $sheet->getStyle("A5:{$lastCol}{$endRow}")->applyFromArray($borderStyle);

    // Auto-fit Column Dimensions
    foreach (range(1, count($alignments)) as $colIdx) {
        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx);
        $sheet->getColumnDimension($colLetter)->setAutoSize(true);
    }
}

$metaInfo = [
    'judul'      => $arsip['judul'],
    'keterangan' => $arsip['keterangan'] ?? '',
    'periode'    => $periode_str,
    'dibuat'     => $dibuat_str
];


// =================================================================================
// --- 1. SHEET: REKAP PER SANTRI ---
// =================================================================================
$sheet1 = $spreadsheet->getActiveSheet();
$headers1 = ['No', 'NIS', 'Nama Santri', 'Kelas', 'Kamar', 'Jml Pelanggaran', 'Poin Pelanggaran', 'Poin Reward', 'Poin Bersih'];
$aligns1  = ['C', 'C', 'L', 'C', 'C', 'C', 'C', 'C', 'C'];
buildExecutiveSheet($sheet1, 'Rekap Per Santri', 'Rekapitulasi Pelanggaran & Reward Per Santri', $headers1, $aligns1, '1E293B', 'F8FAFC', $metaInfo);

$sqlSantri = "
    SELECT
        s.santri_id,
        s.santri_nama,
        s.santri_nis,
        s.santri_kelas,
        s.santri_kamar,
        COALESCE(p.jml_pelanggaran, 0) AS jumlah_pelanggaran,
        COALESCE(p.total_poin_pelanggaran, 0) AS total_poin_pelanggaran,
        COALESCE(r.total_poin_reward, 0) AS total_poin_reward
    FROM arsip_data_santri s
    LEFT JOIN (
        SELECT santri_id, COUNT(id) AS jml_pelanggaran, SUM(poin) AS total_poin_pelanggaran
        FROM arsip_data_pelanggaran
        WHERE arsip_id = ?
        GROUP BY santri_id
    ) p ON s.santri_id = p.santri_id
    LEFT JOIN (
        SELECT santri_id, SUM(poin_reward) AS total_poin_reward
        FROM arsip_data_reward
        WHERE arsip_id = ?
        GROUP BY santri_id
    ) r ON s.santri_id = r.santri_id
    WHERE s.arsip_id = ?
    ORDER BY
        CAST(s.santri_kelas AS UNSIGNED) ASC,
        CAST(s.santri_kamar AS UNSIGNED) ASC,
        s.santri_nama ASC
";
$stmt = $conn->prepare($sqlSantri);
$stmt->bind_param('iii', $arsip_id, $arsip_id, $arsip_id);
$stmt->execute();
$result1 = $stmt->get_result();

$rowNum = 6;
$no = 1;
if ($result1 && $result1->num_rows > 0) {
    while ($row = $result1->fetch_assoc()) {
        $poinPelanggaran = (int)$row['total_poin_pelanggaran'];
        $poinReward      = (int)$row['total_poin_reward'];
        $poinBersih      = $poinPelanggaran - $poinReward;

        $sheet1->setCellValue("A{$rowNum}", $no);
        $sheet1->setCellValueExplicit("B{$rowNum}", (string)($row['santri_nis'] ?: '-'), DataType::TYPE_STRING);
        $sheet1->setCellValue("C{$rowNum}", $row['santri_nama']);
        $sheet1->setCellValue("D{$rowNum}", $row['santri_kelas']);
        $sheet1->setCellValue("E{$rowNum}", $row['santri_kamar']);
        $sheet1->setCellValue("F{$rowNum}", (int)$row['jumlah_pelanggaran']);
        $sheet1->setCellValue("G{$rowNum}", $poinPelanggaran);
        $sheet1->setCellValue("H{$rowNum}", $poinReward);
        $sheet1->setCellValue("I{$rowNum}", $poinBersih);

        // Highlight jika ada poin
        if ($poinPelanggaran > 0) {
            $sheet1->getStyle("G{$rowNum}")->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('DC2626'));
        }
        if ($poinReward > 0) {
            $sheet1->getStyle("H{$rowNum}")->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('16A34A'));
        }
        $sheet1->getStyle("I{$rowNum}")->getFont()->setBold(true);

        $rowNum++;
        $no++;
    }
}
$stmt->close();
applyDataRowStyles($sheet1, 6, $rowNum - 1, $aligns1, 'F8FAFC');


// =================================================================================
// --- 2. SHEET: REKAP PER KAMAR ---
// =================================================================================
$sheet2 = $spreadsheet->createSheet();
$headers2 = ['No', 'Kamar', 'Pelanggaran Santri', 'Pelanggaran Kebersihan', 'Total Pelanggaran Gabungan'];
$aligns2  = ['C', 'C', 'C', 'C', 'C'];
buildExecutiveSheet($sheet2, 'Rekap Per Kamar', 'Rekapitulasi Pelanggaran Gabungan Per Kamar', $headers2, $aligns2, '4338CA', 'EEF2FF', $metaInfo);

$sqlKamar = "
    SELECT
        kamar,
        SUM(CASE WHEN tipe = 'santri' THEN jml ELSE 0 END) AS pel_santri,
        SUM(CASE WHEN tipe = 'kebersihan' THEN jml ELSE 0 END) AS pel_kebersihan,
        SUM(jml) AS total_gabungan
    FROM (
        SELECT santri_kamar AS kamar, COUNT(id) AS jml, 'santri' AS tipe
        FROM arsip_data_pelanggaran
        WHERE arsip_id = ? AND santri_kamar IS NOT NULL AND santri_kamar != ''
        GROUP BY santri_kamar

        UNION ALL

        SELECT kamar, COUNT(id) AS jml, 'kebersihan' AS tipe
        FROM arsip_data_pelanggaran_kebersihan
        WHERE arsip_id = ? AND kamar IS NOT NULL AND kamar != ''
        GROUP BY kamar
    ) AS comb
    GROUP BY kamar
    ORDER BY CAST(kamar AS UNSIGNED) ASC
";
$stmt = $conn->prepare($sqlKamar);
$stmt->bind_param('ii', $arsip_id, $arsip_id);
$stmt->execute();
$result2 = $stmt->get_result();

$rowNum = 6;
$no = 1;
if ($result2 && $result2->num_rows > 0) {
    while ($row = $result2->fetch_assoc()) {
        $sheet2->setCellValue("A{$rowNum}", $no);
        $sheet2->setCellValue("B{$rowNum}", "Kamar " . $row['kamar']);
        $sheet2->setCellValue("C{$rowNum}", (int)$row['pel_santri']);
        $sheet2->setCellValue("D{$rowNum}", (int)$row['pel_kebersihan']);
        $sheet2->setCellValue("E{$rowNum}", (int)$row['total_gabungan']);

        $sheet2->getStyle("E{$rowNum}")->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('4338CA'));

        $rowNum++;
        $no++;
    }
}
$stmt->close();
applyDataRowStyles($sheet2, 6, $rowNum - 1, $aligns2, 'EEF2FF');


// =================================================================================
// --- 3. SHEET: KEBERSIHAN KAMAR ---
// =================================================================================
$sheet3 = $spreadsheet->createSheet();
$headers3 = ['No', 'Kamar', 'Jumlah Pelanggaran Kebersihan'];
$aligns3  = ['C', 'C', 'C'];
buildExecutiveSheet($sheet3, 'Kebersihan Kamar', 'Rekapitulasi Pelanggaran Kebersihan Kamar', $headers3, $aligns3, 'D97706', 'FFFBEB', $metaInfo);

$sqlKebersihan = "
    SELECT kamar, COUNT(id) AS jml
    FROM arsip_data_pelanggaran_kebersihan
    WHERE arsip_id = ?
    GROUP BY kamar
    ORDER BY jml DESC, CAST(kamar AS UNSIGNED) ASC
";
$stmt = $conn->prepare($sqlKebersihan);
$stmt->bind_param('i', $arsip_id);
$stmt->execute();
$result3 = $stmt->get_result();

$rowNum = 6;
$no = 1;
if ($result3 && $result3->num_rows > 0) {
    while ($row = $result3->fetch_assoc()) {
        $sheet3->setCellValue("A{$rowNum}", $no);
        $sheet3->setCellValue("B{$rowNum}", "Kamar " . $row['kamar']);
        $sheet3->setCellValue("C{$rowNum}", (int)$row['jml']);

        $sheet3->getStyle("C{$rowNum}")->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('D97706'));

        $rowNum++;
        $no++;
    }
}
$stmt->close();
applyDataRowStyles($sheet3, 6, $rowNum - 1, $aligns3, 'FFFBEB');


// =================================================================================
// --- 4. SHEET: DETAIL PELANGGARAN UMUM ---
// =================================================================================
$sheet4 = $spreadsheet->createSheet();
$headers4 = ['No', 'NIS', 'Nama Santri', 'Kelas', 'Kamar', 'Jenis Pelanggaran', 'Bagian', 'Poin', 'Tanggal'];
$aligns4  = ['C', 'C', 'L', 'C', 'C', 'L', 'C', 'C', 'C'];
buildExecutiveSheet($sheet4, 'Pelanggaran Umum', 'Detail Seluruh Transaksi Pelanggaran Umum', $headers4, $aligns4, 'B91C1C', 'FEF2F2', $metaInfo);

$sqlPelanggaran = "
    SELECT p.santri_nama, s.santri_nis, p.santri_kelas, p.santri_kamar, p.jenis_pelanggaran_nama, p.bagian, p.poin, p.tanggal
    FROM arsip_data_pelanggaran p
    LEFT JOIN arsip_data_santri s ON p.santri_id = s.santri_id AND p.arsip_id = s.arsip_id
    WHERE p.arsip_id = ?
    ORDER BY p.tanggal ASC
";
$stmt = $conn->prepare($sqlPelanggaran);
$stmt->bind_param('i', $arsip_id);
$stmt->execute();
$result4 = $stmt->get_result();

$rowNum = 6;
$no = 1;
if ($result4 && $result4->num_rows > 0) {
    while ($row = $result4->fetch_assoc()) {
        $sheet4->setCellValue("A{$rowNum}", $no);
        $sheet4->setCellValueExplicit("B{$rowNum}", (string)($row['santri_nis'] ?: '-'), DataType::TYPE_STRING);
        $sheet4->setCellValue("C{$rowNum}", $row['santri_nama']);
        $sheet4->setCellValue("D{$rowNum}", $row['santri_kelas']);
        $sheet4->setCellValue("E{$rowNum}", $row['santri_kamar']);
        $sheet4->setCellValue("F{$rowNum}", $row['jenis_pelanggaran_nama']);
        $sheet4->setCellValue("G{$rowNum}", $row['bagian']);
        $sheet4->setCellValue("H{$rowNum}", (int)$row['poin']);
        $sheet4->setCellValue("I{$rowNum}", date('d-m-Y H:i', strtotime($row['tanggal'])));

        $sheet4->getStyle("H{$rowNum}")->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('B91C1C'));

        $rowNum++;
        $no++;
    }
}
$stmt->close();
applyDataRowStyles($sheet4, 6, $rowNum - 1, $aligns4, 'FEF2F2');


// =================================================================================
// --- 5. SHEET: RIWAYAT BAHASA ---
// =================================================================================
$sheet5 = $spreadsheet->createSheet();
$headers5 = ['No', 'NIS', 'Nama Santri', 'Kelas', 'Kamar', 'Tingkatan Level', 'Poin', 'Tanggal Insiden', 'Dicatat Oleh'];
$aligns5  = ['C', 'C', 'L', 'C', 'C', 'C', 'C', 'C', 'L'];
buildExecutiveSheet($sheet5, 'Riwayat Bahasa', 'Detail Riwayat Perkembangan & Pelanggaran Bahasa', $headers5, $aligns5, '0E7490', 'ECFEFF', $metaInfo);

$sqlBahasa = "
    SELECT lb.santri_nama, s.santri_nis, lb.santri_kelas, lb.santri_kamar, lb.nama_pelanggaran, lb.poin_lama, lb.tanggal_melanggar, lb.diganti_oleh_nama
    FROM arsip_data_log_bahasa lb
    LEFT JOIN arsip_data_santri s ON lb.santri_id = s.santri_id AND lb.arsip_id = s.arsip_id
    WHERE lb.arsip_id = ?
    ORDER BY lb.tanggal_melanggar ASC
";
$stmt = $conn->prepare($sqlBahasa);
$stmt->bind_param('i', $arsip_id);
$stmt->execute();
$result5 = $stmt->get_result();

$rowNum = 6;
$no = 1;
if ($result5 && $result5->num_rows > 0) {
    while ($row = $result5->fetch_assoc()) {
        $sheet5->setCellValue("A{$rowNum}", $no);
        $sheet5->setCellValueExplicit("B{$rowNum}", (string)($row['santri_nis'] ?: '-'), DataType::TYPE_STRING);
        $sheet5->setCellValue("C{$rowNum}", $row['santri_nama']);
        $sheet5->setCellValue("D{$rowNum}", $row['santri_kelas']);
        $sheet5->setCellValue("E{$rowNum}", $row['santri_kamar']);
        $sheet5->setCellValue("F{$rowNum}", $row['nama_pelanggaran']);
        $sheet5->setCellValue("G{$rowNum}", (int)$row['poin_lama']);
        $sheet5->setCellValue("H{$rowNum}", date('d-m-Y H:i', strtotime($row['tanggal_melanggar'])));
        $sheet5->setCellValue("I{$rowNum}", $row['diganti_oleh_nama'] ?: 'Sistem');

        $sheet5->getStyle("G{$rowNum}")->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('0E7490'));

        $rowNum++;
        $no++;
    }
}
$stmt->close();
applyDataRowStyles($sheet5, 6, $rowNum - 1, $aligns5, 'ECFEFF');


// =================================================================================
// --- 6. SHEET: DATA REWARD SANTRI ---
// =================================================================================
$sheet6 = $spreadsheet->createSheet();
$headers6 = ['No', 'NIS', 'Nama Santri', 'Kelas', 'Kamar', 'Nama Reward', 'Poin Reward', 'Tanggal', 'Dicatat Oleh'];
$aligns6  = ['C', 'C', 'L', 'C', 'C', 'L', 'C', 'C', 'L'];
buildExecutiveSheet($sheet6, 'Data Reward', 'Detail Seluruh Transaksi Apresiasi & Reward Santri', $headers6, $aligns6, '047857', 'F0FDF4', $metaInfo);

$sqlReward = "
    SELECT r.santri_nama, s.santri_nis, r.santri_kelas, r.santri_kamar, r.nama_reward, r.poin_reward, r.tanggal, r.dicatat_oleh_nama
    FROM arsip_data_reward r
    LEFT JOIN arsip_data_santri s ON r.santri_id = s.santri_id AND r.arsip_id = s.arsip_id
    WHERE r.arsip_id = ?
    ORDER BY r.tanggal ASC
";
$stmt = $conn->prepare($sqlReward);
$stmt->bind_param('i', $arsip_id);
$stmt->execute();
$result6 = $stmt->get_result();

$rowNum = 6;
$no = 1;
if ($result6 && $result6->num_rows > 0) {
    while ($row = $result6->fetch_assoc()) {
        $sheet6->setCellValue("A{$rowNum}", $no);
        $sheet6->setCellValueExplicit("B{$rowNum}", (string)($row['santri_nis'] ?: '-'), DataType::TYPE_STRING);
        $sheet6->setCellValue("C{$rowNum}", $row['santri_nama']);
        $sheet6->setCellValue("D{$rowNum}", $row['santri_kelas']);
        $sheet6->setCellValue("E{$rowNum}", $row['santri_kamar']);
        $sheet6->setCellValue("F{$rowNum}", $row['nama_reward']);
        $sheet6->setCellValue("G{$rowNum}", (int)$row['poin_reward']);
        $sheet6->setCellValue("H{$rowNum}", date('d-m-Y', strtotime($row['tanggal'])));
        $sheet6->setCellValue("I{$rowNum}", $row['dicatat_oleh_nama'] ?: 'Sistem');

        $sheet6->getStyle("G{$rowNum}")->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('047857'));

        $rowNum++;
        $no++;
    }
}
$stmt->close();
applyDataRowStyles($sheet6, 6, $rowNum - 1, $aligns6, 'F0FDF4');


// =================================================================================
// --- OUTPUT FILE DOWNLOAD ---
// =================================================================================
$spreadsheet->setActiveSheetIndex(0); // Fokus ke sheet pertama

$safe_title = preg_replace('/[^a-zA-Z0-9_-]/', '_', strtolower($arsip['judul']));
$namaFile   = 'Laporan_Arsip_' . $safe_title . '_' . date('d_m_Y') . '.xlsx';

if (ob_get_length()) {
    ob_end_clean();
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $namaFile . '"');
header('Cache-Control: max-age=0');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); 
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); 
header('Cache-Control: cache, must-revalidate'); 
header('Pragma: public'); 

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');

$conn->close();
exit();
