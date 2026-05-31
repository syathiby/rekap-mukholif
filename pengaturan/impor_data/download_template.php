<?php
/**
 * pengaturan/impor_data/download_template.php
 * Generator Template Excel untuk fitur Impor Data.
 *
 * Parameter GET: ?tipe=santri | jenis_pelanggaran | jenis_reward
 *
 * Menghasilkan file .xlsx dengan header kolom + contoh data
 * agar pengguna tahu format yang benar sebelum mengunggah.
 */
require_once __DIR__ . '/../../bootstrap/init.php';
guard('impor_data');

require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

// ── Validasi parameter ──────────────────────────────────────────────────────
$allowed_types = ['santri', 'jenis_pelanggaran', 'jenis_reward'];
$tipe = $_GET['tipe'] ?? '';

if (!in_array($tipe, $allowed_types, true)) {
    http_response_code(400);
    exit('Tipe template tidak valid.');
}

// ── Definisi template per tipe ──────────────────────────────────────────────
$templates = [
    'santri' => [
        'filename'    => 'Template_Impor_Santri.xlsx',
        'sheet_title' => 'Data Santri',
        'headers'     => ['ID', 'Nama Santri', 'Kelas', 'Kamar'],
        'col_widths'  => [10, 42, 12, 12],
        'examples'    => [
            [1,  'Ahmad Fauzan Maulana',    '1', '3'],
            [2,  'Muhammad Rizky Pratama',  '2', '5'],
            ['', 'Siti Aisyah Rahmawati',   '3', '7'],
            ['', 'Yusuf Ibrahim Al-Farisi', '4', '8'],
        ],
        'notes' => [
            'Kolom ID dapat dikosongkan untuk data baru (ID akan dibuat otomatis).',
            'Kolom Nama Santri WAJIB diisi di setiap baris.',
        ],
    ],

    'jenis_pelanggaran' => [
        'filename'    => 'Template_Impor_Jenis_Pelanggaran.xlsx',
        'sheet_title' => 'Jenis Pelanggaran',
        'headers'     => ['ID', 'Nama Pelanggaran', 'Bagian', 'Poin', 'Kategori'],
        'col_widths'  => [8, 45, 16, 10, 16],
        'examples'    => [
            [1,  'Tidak menggunakan seragam lengkap',   'Kesantrian', 10, 'Ringan'],
            [2,  'Meninggalkan kelas tanpa izin',        'Diniyyah',   20, 'Sedang'],
            ['', 'Tidak hadir shalat berjamaah',         'Kesantrian', 15, 'Ringan'],
            ['', 'Membawa alat elektronik terlarang',    'Kesantrian', 50, 'Berat'],
            ['', 'Tidak menyetorkan hafalan',            'Tahfidz',    25, 'Sedang'],
            ['', 'Melakukan kekerasan fisik',            'Kesantrian', 100,'Sangat Berat'],
        ],
        'notes' => [
            'Kolom ID dapat dikosongkan untuk data baru (ID akan dibuat otomatis).',
            'Bagian harus salah satu dari: Bahasa, Diniyyah, Kesantrian, Pengabdian, Tahfidz.',
            'Kategori harus salah satu dari: Ringan, Sedang, Berat, Sangat Berat.',
            'Poin harus berupa angka positif.',
        ],
    ],

    'jenis_reward' => [
        'filename'    => 'Template_Impor_Jenis_Reward.xlsx',
        'sheet_title' => 'Jenis Reward',
        'headers'     => ['ID', 'Nama Reward', 'Poin Reward', 'Deskripsi'],
        'col_widths'  => [8, 45, 14, 50],
        'examples'    => [
            [1,  'Hafalan Juz 30',           100, 'Berhasil menghafal seluruh Juz 30'],
            [2,  'Juara Lomba Bahasa Arab',   75,  'Meraih juara dalam kompetisi bahasa Arab'],
            ['', 'Santri Teladan Bulanan',    50,  ''],
            ['', 'Ketua Organisasi Aktif',    30,  ''],
        ],
        'notes' => [
            'Kolom ID dapat dikosongkan untuk data baru (ID akan dibuat otomatis).',
            'Kolom Nama Reward WAJIB diisi.',
            'Poin Reward harus berupa angka positif.',
            'Kolom Deskripsi bersifat opsional (boleh dikosongkan).',
        ],
    ],
];

$tpl = $templates[$tipe];

// ── Warna tema berdasarkan tipe ─────────────────────────────────────────────
$theme = match ($tipe) {
    'jenis_pelanggaran' => ['header_bg' => '991B1B', 'accent' => 'DC2626', 'example_bg' => 'FFF5F5'],
    'jenis_reward'      => ['header_bg' => '064E3B', 'accent' => '059669', 'example_bg' => 'F0FFF4'],
    default             => ['header_bg' => '1E293B', 'accent' => '334155', 'example_bg' => 'F8FAFC'],
};

// ── Buat Spreadsheet ────────────────────────────────────────────────────────
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle($tpl['sheet_title']);
$spreadsheet->getDefaultStyle()->getFont()->setName('Segoe UI')->setSize(10);

$colCount    = count($tpl['headers']);
$lastColLtr  = Coordinate::stringFromColumnIndex($colCount);

// ── Baris 1: Banner judul ───────────────────────────────────────────────────
$sheet->mergeCells('A1:' . $lastColLtr . '1');
$sheet->setCellValue('A1', 'TEMPLATE IMPOR — ' . strtoupper($tpl['sheet_title']));
$sheet->getStyle('A1')->applyFromArray([
    'font'      => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $theme['header_bg']]],
]);
$sheet->getRowDimension(1)->setRowHeight(26);

// ── Baris 2: Sub-judul ──────────────────────────────────────────────────────
$sheet->mergeCells('A2:' . $lastColLtr . '2');
$sheet->setCellValue('A2', 'Isi file ini lalu unggah di menu Sinkronisasi Data. Baris berwarna = contoh data (hapus sebelum diunggah).');
$sheet->getStyle('A2')->applyFromArray([
    'font'      => ['italic' => true, 'size' => 9, 'color' => ['rgb' => '64748B']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F1F5F9']],
]);
$sheet->getRowDimension(2)->setRowHeight(16);

// ── Baris 3: Header kolom ───────────────────────────────────────────────────
$headerRow = 3;
foreach ($tpl['headers'] as $colIdx => $headerText) {
    $colLtr = Coordinate::stringFromColumnIndex($colIdx + 1);
    $sheet->setCellValue($colLtr . $headerRow, $headerText);
    $sheet->getColumnDimension($colLtr)->setWidth($tpl['col_widths'][$colIdx]);
}
$sheet->getStyle('A' . $headerRow . ':' . $lastColLtr . $headerRow)->applyFromArray([
    'font'      => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $theme['accent']]],
    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => $theme['header_bg']]]],
]);
$sheet->getRowDimension($headerRow)->setRowHeight(22);

// ── Baris 4+: Contoh data ───────────────────────────────────────────────────
foreach ($tpl['examples'] as $exIdx => $exRow) {
    $rowNum = $headerRow + 1 + $exIdx;
    foreach ($exRow as $colIdx => $cellValue) {
        $colLtr = Coordinate::stringFromColumnIndex($colIdx + 1);
        $sheet->setCellValue($colLtr . $rowNum, $cellValue !== '' ? $cellValue : '');
    }
    $sheet->getStyle('A' . $rowNum . ':' . $lastColLtr . $rowNum)->applyFromArray([
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $theme['example_bg']]],
        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => 'CBD5E1']]],
        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        'font'      => ['color' => ['rgb' => '64748B']],
    ]);
    $sheet->getRowDimension($rowNum)->setRowHeight(18);
}

// ── Catatan penggunaan ──────────────────────────────────────────────────────
$noteStartRow = $headerRow + 1 + count($tpl['examples']) + 1;
$sheet->mergeCells('A' . $noteStartRow . ':' . $lastColLtr . $noteStartRow);
$sheet->setCellValue('A' . $noteStartRow, '  CATATAN PENTING:');
$sheet->getStyle('A' . $noteStartRow)->applyFromArray([
    'font'      => ['bold' => true, 'size' => 9, 'color' => ['rgb' => $theme['header_bg']]],
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFBEB']],
    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
]);
$sheet->getRowDimension($noteStartRow)->setRowHeight(18);

foreach ($tpl['notes'] as $noteIdx => $noteText) {
    $noteRow = $noteStartRow + 1 + $noteIdx;
    $sheet->mergeCells('A' . $noteRow . ':' . $lastColLtr . $noteRow);
    $sheet->setCellValue('A' . $noteRow, '  * ' . $noteText);
    $sheet->getStyle('A' . $noteRow)->applyFromArray([
        'font'      => ['size' => 9, 'color' => ['rgb' => '374151']],
        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFBEB']],
    ]);
    $sheet->getRowDimension($noteRow)->setRowHeight(16);
}

// ── Freeze header & Auto-filter ─────────────────────────────────────────────
$sheet->freezePane('A' . ($headerRow + 1));
$sheet->setAutoFilter('A' . $headerRow . ':' . $lastColLtr . $headerRow);

// ── Kirim file ke browser ───────────────────────────────────────────────────
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $tpl['filename'] . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
