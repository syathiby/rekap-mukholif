<?php
/**
 * AsuhTrack Executive Excel Styling Engine v2.0
 * ─────────────────────────────────────────────────────────────────────────────
 * Standar pelaporan formal untuk Kepala Bagian Pondok Pesantren.
 * Dirancang agar setiap lembar laporan mencerminkan kredibilitas institusional.
 *
 * Fitur Utama:
 *  - Kop surat formal 6 baris (nama lembaga, alamat, garis dekoratif)
 *  - Blok metadata dokumen (nomor laporan, periode, pencetak)
 *  - Panel ringkasan statistik otomatis di atas tabel data
 *  - Tiga tema warna resmi (Umum, Kedisiplinan, Prestasi)
 *  - Zebra striping + border presisi + freeze pane
 *  - Footer halaman dengan keterangan kerahasiaan
 */

use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

class ExcelTemplate {

    // ═══════════════════════════════════════════════════════════════
    // KONSTANTA TEMA WARNA
    // ═══════════════════════════════════════════════════════════════

    const THEME_GENERAL   = 'GENERAL';   // Deep Navy — Profil & Data Induk
    const THEME_VIOLATION = 'VIOLATION'; // Crimson   — Kedisiplinan & Pelanggaran
    const THEME_REWARD    = 'REWARD';    // Emerald   — Prestasi & Penghargaan

    // ═══════════════════════════════════════════════════════════════
    // KONSTANTA LAYOUT — mudah diubah tanpa menyentuh logika inti
    // ═══════════════════════════════════════════════════════════════

    const LETTERHEAD_ROWS = 7;  // Baris kop surat (termasuk garis pemisah)
    const META_ROWS       = 4;  // Baris blok metadata dokumen
    const SUMMARY_ROWS    = 3;  // Baris panel ringkasan statistik
    const SPACER_ROWS     = 1;  // Baris kosong sebelum tabel data

    // Total baris header = LETTERHEAD + META + SUMMARY + SPACER
    const TOTAL_HEADER_ROWS = 15; // 7 + 4 + 3 + 1

    /**
     * Mendapatkan palet warna berdasarkan tipe laporan.
     */
    private static function getThemePalette(string $theme): array {
        $palettes = [
            self::THEME_VIOLATION => [
                'primary'      => '7F1D1D', // Deep Crimson — header utama
                'secondary'    => '991B1B', // Crimson — header tabel
                'accent'       => 'DC2626', // Red — aksen dan borders tebal
                'header_font'  => 'FFFFFF',
                'zebra_fill'   => 'FEF2F2', // Soft pink tinge
                'meta_fill'    => 'FFF5F5', // Latar blok metadata
                'summary_fill' => 'FEE2E2', // Latar panel ringkasan
                'border_color' => 'FECACA',
            ],
            self::THEME_REWARD => [
                'primary'      => '052E16', // Deep Forest — header utama
                'secondary'    => '064E3B', // Deep Emerald — header tabel
                'accent'       => '059669', // Emerald — aksen
                'header_font'  => 'FFFFFF',
                'zebra_fill'   => 'F0FDF4',
                'meta_fill'    => 'F0FFF4',
                'summary_fill' => 'DCFCE7',
                'border_color' => 'BBF7D0',
            ],
            self::THEME_GENERAL => [
                'primary'      => '0F172A', // Midnight Navy — header utama
                'secondary'    => '1E293B', // Dark Slate — header tabel
                'accent'       => '334155', // Slate — aksen
                'header_font'  => 'FFFFFF',
                'zebra_fill'   => 'F8FAFC',
                'meta_fill'    => 'F1F5F9',
                'summary_fill' => 'E2E8F0',
                'border_color' => 'CBD5E1',
            ],
        ];

        return $palettes[$theme] ?? $palettes[self::THEME_GENERAL];
    }

    // ═══════════════════════════════════════════════════════════════
    // ENTRY POINT UTAMA
    // ═══════════════════════════════════════════════════════════════

    /**
     * Menerapkan seluruh template eksekutif ke worksheet.
     *
     * @param Worksheet $sheet          Referensi worksheet aktif
     * @param string    $titleName      Judul resmi dokumen (contoh: "Laporan Pelanggaran Santri")
     * @param string    $themeType      Konstanta tema warna (THEME_GENERAL / THEME_VIOLATION / THEME_REWARD)
     * @param array     $options        Opsi tambahan:
     *                                   - 'institution'  => Nama lembaga (string)
     *                                   - 'address'      => Alamat lembaga (string)
     *                                   - 'period'       => Periode laporan, contoh "Semester Ganjil 2024/2025"
     *                                   - 'doc_number'   => Nomor dokumen resmi
     *                                   - 'printed_by'   => Nama pencetak dokumen
     *                                   - 'summary_data' => Array [['label' => '...', 'value' => '...'], ...]
     */
    public static function applyExecutiveStyle(
        Worksheet &$sheet,
        string $titleName,
        string $themeType = self::THEME_GENERAL,
        array $options = []
    ): void {
        // Validasi: sheet tidak boleh kosong
        if ($sheet->getHighestRow() <= 1) {
            return;
        }

        $palette = self::getThemePalette($themeType);

        // Rekam kolom tertinggi SEBELUM insert baris baru
        $highestColumn      = $sheet->getHighestColumn();
        $originalHighestRow = $sheet->getHighestRow();

        // Sisipkan baris header di atas data asli
        $sheet->insertNewRowBefore(1, self::TOTAL_HEADER_ROWS);

        // Render setiap blok secara berurutan
        self::renderLetterhead($sheet, $palette, $highestColumn, $titleName, $options);
        self::renderDocumentMetadata($sheet, $palette, $highestColumn, $options);
        self::renderSummaryPanel($sheet, $palette, $highestColumn, $options);

        // Hitung ulang posisi baris setelah injeksi
        $tableHeaderRow = self::TOTAL_HEADER_ROWS + 1;
        $dataStartRow   = $tableHeaderRow + 1;
        $dataEndRow     = $tableHeaderRow + $originalHighestRow - 1;

        // Render tabel data utama
        self::styleTableHeader($sheet, $palette, $highestColumn, $tableHeaderRow);
        self::styleDataRows($sheet, $palette, $highestColumn, $dataStartRow, $dataEndRow);
        self::applyTableNavigation($sheet, $highestColumn, $tableHeaderRow, $dataStartRow);

        // Render footer dan pengaturan cetak
        self::applyPrintSettings($sheet, $titleName);
        self::renderFooterNote($sheet, $palette, $highestColumn, $dataEndRow);

        // Auto-fit semua kolom
        self::autoFitColumns($sheet);
    }

    // ═══════════════════════════════════════════════════════════════
    // BLOK 1 — KOP SURAT FORMAL
    // Baris 1–7: Nama lembaga, sub-judul, alamat, garis dekoratif
    // ═══════════════════════════════════════════════════════════════

    private static function renderLetterhead(
        Worksheet &$sheet,
        array $palette,
        string $lastCol,
        string $titleName,
        array $options
    ): void {
        $institution = $options['institution'] ?? 'PONDOK PESANTREN';
        $address     = $options['address']     ?? 'Jl. Pesantren No. 1, Indonesia';

        // Merge seluruh lebar untuk tiap baris kop
        foreach (range(1, 6) as $row) {
            $sheet->mergeCells('A' . $row . ':' . $lastCol . $row);
        }

        // Baris 1: Latar belakang header — identitas sistem
        $sheet->setCellValue('A1', 'ASUHTRACK DIGITAL MANAGEMENT SYSTEM');
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 8, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $palette['accent']]],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(14);

        // Baris 2: Nama Lembaga — huruf besar, kapital, berbobot
        $sheet->setCellValue('A2', strtoupper($institution));
        $sheet->getStyle('A2')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 16, 'color' => ['rgb' => $palette['primary']]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFFF']],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(28);

        // Baris 3: Alamat lembaga
        $sheet->setCellValue('A3', $address);
        $sheet->getStyle('A3')->applyFromArray([
            'font'      => ['size' => 9, 'italic' => true, 'color' => ['rgb' => '64748B']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getRowDimension(3)->setRowHeight(14);

        // Baris 4: Garis dekoratif (simulated dengan border bawah tebal)
        $sheet->getStyle('A4:' . $lastCol . '4')->applyFromArray([
            'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $palette['primary']]],
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THICK, 'color' => ['rgb' => $palette['accent']]]],
        ]);
        $sheet->getRowDimension(4)->setRowHeight(3);

        // Baris 5: Judul Laporan — inti dokumen
        $sheet->setCellValue('A5', strtoupper($titleName));
        $sheet->getStyle('A5')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 13, 'color' => ['rgb' => $palette['primary']]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(5)->setRowHeight(26);

        // Baris 6: Garis pemisah bawah kop (warna sekunder, tipis)
        $sheet->getStyle('A6:' . $lastCol . '6')->applyFromArray([
            'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $palette['secondary']]],
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => $palette['accent']]]],
        ]);
        $sheet->getRowDimension(6)->setRowHeight(2);

        // Baris 7: Spacer putih
        $sheet->getRowDimension(7)->setRowHeight(6);
    }

    // ═══════════════════════════════════════════════════════════════
    // BLOK 2 — METADATA DOKUMEN
    // Baris 8–11: Nomor dokumen, periode, pencetak, timestamp
    // ═══════════════════════════════════════════════════════════════

    private static function renderDocumentMetadata(
        Worksheet &$sheet,
        array $palette,
        string $lastCol,
        array $options
    ): void {
        $startRow  = 8;
        $period    = $options['period']     ?? '-';
        $docNumber = $options['doc_number'] ?? 'AUTO/' . date('Ymd') . '/' . strtoupper(substr(uniqid(), -4));
        $printedBy = $options['printed_by'] ?? 'Sistem';
        $printTime = date('d F Y, H:i') . ' WIB';

        // Latar blok metadata
        $metaRange = 'A' . $startRow . ':' . $lastCol . ($startRow + 3);
        $sheet->getStyle($metaRange)->applyFromArray([
            'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $palette['meta_fill']]],
            'borders' => [
                'outline' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => $palette['border_color']]],
            ],
        ]);

        // Metadata disusun dua kolom: kiri (label) dan kanan (nilai)
        // Kalkulasi kolom tengah secara manual (A = kiri, kolom tengah ≈ setengah lebar sheet)
        $metaItems = [
            ['Nomor Dokumen', $docNumber, 'Dicetak oleh', $printedBy],
            ['Periode Laporan', $period,  'Waktu Cetak',  $printTime],
        ];

        $labelStyle = [
            'font'      => ['bold' => true, 'size' => 9, 'color' => ['rgb' => $palette['secondary']]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ];
        $valueStyle = [
            'font'      => ['size' => 9, 'color' => ['rgb' => '1E293B']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ];

        foreach ($metaItems as $idx => $row) {
            $r = $startRow + $idx;
            $sheet->setCellValue('A' . $r, '  ' . $row[0] . ':');
            $sheet->getStyle('A' . $r)->applyFromArray($labelStyle);
            $sheet->setCellValue('B' . $r, $row[1]);
            $sheet->getStyle('B' . $r)->applyFromArray($valueStyle);

            // Kolom kanan — gunakan kolom ke-4 dan ke-5 jika tersedia
            $sheet->setCellValue('D' . $r, '  ' . $row[2] . ':');
            $sheet->getStyle('D' . $r)->applyFromArray($labelStyle);
            $sheet->setCellValue('E' . $r, $row[3]);
            $sheet->getStyle('E' . $r)->applyFromArray($valueStyle);

            $sheet->getRowDimension($r)->setRowHeight(16);
        }

        // Baris 10–11: Label "DOKUMEN INTERNAL — RAHASIA" di kanan bawah blok
        $sheet->setCellValue('A' . ($startRow + 2), '  Keterangan:');
        $sheet->getStyle('A' . ($startRow + 2))->applyFromArray($labelStyle);
        $sheet->setCellValue('B' . ($startRow + 2), 'Dokumen ini diterbitkan secara otomatis oleh sistem AsuhTrack.');
        $sheet->getStyle('B' . ($startRow + 2))->applyFromArray($valueStyle);
        $sheet->getRowDimension($startRow + 2)->setRowHeight(15);

        $sheet->getRowDimension($startRow + 3)->setRowHeight(5); // spacer
    }

    // ═══════════════════════════════════════════════════════════════
    // BLOK 3 — PANEL RINGKASAN STATISTIK
    // Baris 12–14: Statistik kunci yang relevan per laporan
    // ═══════════════════════════════════════════════════════════════

    private static function renderSummaryPanel(
        Worksheet &$sheet,
        array $palette,
        string $lastCol,
        array $options
    ): void {
        $startRow    = 12;
        $summaryData = $options['summary_data'] ?? [];

        if (empty($summaryData)) {
            // Isi default jika tidak ada data ringkasan yang diberikan
            $summaryData = [
                ['label' => 'Total Data', 'value' => '—'],
                ['label' => 'Kategori',   'value' => '—'],
                ['label' => 'Status',     'value' => 'Terverifikasi'],
            ];
        }

        // Latar panel
        $panelRange = 'A' . $startRow . ':' . $lastCol . ($startRow + 1);
        $sheet->getStyle($panelRange)->applyFromArray([
            'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $palette['summary_fill']]],
            'borders' => [
                'outline'    => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => $palette['secondary']]],
                'allBorders' => ['borderStyle' => Border::BORDER_THIN,   'color' => ['rgb' => $palette['border_color']]],
            ],
        ]);

        // Judul panel — baris pertama
        $sheet->mergeCells('A' . $startRow . ':' . $lastCol . $startRow);
        $sheet->setCellValue('A' . $startRow, '▌ RINGKASAN LAPORAN');
        $sheet->getStyle('A' . $startRow)->applyFromArray([
            'font'      => ['bold' => true, 'size' => 9, 'color' => ['rgb' => $palette['secondary']]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension($startRow)->setRowHeight(16);

        // Statistik — baris kedua, distributed per kolom
        $valueRow = $startRow + 1;
        $col      = 'A';
        foreach ($summaryData as $item) {
            $sheet->setCellValue($col . $valueRow, $item['label'] . ': ' . $item['value']);
            $sheet->getStyle($col . $valueRow)->applyFromArray([
                'font'      => ['bold' => true, 'size' => 10, 'color' => ['rgb' => $palette['primary']]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ]);
            $col++;
        }
        $sheet->getRowDimension($valueRow)->setRowHeight(20);

        // Spacer setelah panel (baris 14)
        $sheet->getRowDimension($startRow + 2)->setRowHeight(4);
    }

    // ═══════════════════════════════════════════════════════════════
    // BLOK 4 — STYLING HEADER TABEL
    // ═══════════════════════════════════════════════════════════════

    private static function styleTableHeader(
        Worksheet &$sheet,
        array $palette,
        string $lastCol,
        int $headerRow
    ): void {
        $headerRange = 'A' . $headerRow . ':' . $lastCol . $headerRow;

        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => [
                'bold'  => true,
                'size'  => 10,
                'color' => ['rgb' => $palette['header_font']],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
                'wrapText'   => true,
            ],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => $palette['secondary']],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_MEDIUM,
                    'color'       => ['rgb' => $palette['primary']],
                ],
            ],
        ]);

        $sheet->getRowDimension($headerRow)->setRowHeight(30);
    }

    // ═══════════════════════════════════════════════════════════════
    // BLOK 5 — STYLING BARIS DATA
    // ═══════════════════════════════════════════════════════════════

    private static function styleDataRows(
        Worksheet &$sheet,
        array $palette,
        string $lastCol,
        int $startRow,
        int $endRow
    ): void {
        // Border keseluruhan tabel data
        $fullRange = 'A' . $startRow . ':' . $lastCol . $endRow;
        $sheet->getStyle($fullRange)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color'       => ['rgb' => $palette['border_color']],
                ],
                'outline' => [
                    'borderStyle' => Border::BORDER_MEDIUM,
                    'color'       => ['rgb' => $palette['secondary']],
                ],
            ],
        ]);

        for ($row = $startRow; $row <= $endRow; $row++) {
            $sheet->getRowDimension($row)->setRowHeight(20);

            // Zebra striping — baris genap diberi latar warna
            if ($row % 2 === 0) {
                $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $palette['zebra_fill']]],
                ]);
            }

            // Alignment vertikal rata tengah untuk semua sel
            $sheet->getStyle('A' . $row . ':' . $lastCol . $row)
                  ->getAlignment()
                  ->setVertical(Alignment::VERTICAL_CENTER);

            // Auto-center untuk nilai numerik pendek (skor, angka, kode)
            foreach ($sheet->getColumnIterator() as $column) {
                $colIdx    = $column->getColumnIndex();
                $cellValue = $sheet->getCell($colIdx . $row)->getValue();

                if (is_numeric($cellValue) && strlen((string) $cellValue) < 6) {
                    $sheet->getStyle($colIdx . $row)
                          ->getAlignment()
                          ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }
            }
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // BLOK 6 — NAVIGASI TABEL (Filter & Freeze Pane)
    // ═══════════════════════════════════════════════════════════════

    private static function applyTableNavigation(
        Worksheet &$sheet,
        string $lastCol,
        int $headerRow,
        int $dataStartRow
    ): void {
        $headerRange = 'A' . $headerRow . ':' . $lastCol . $headerRow;
        $sheet->setAutoFilter($headerRange);

        // Freeze pane: header tabel tidak ikut scroll saat Kabag membuka di Excel
        $sheet->freezePane('A' . $dataStartRow);
    }

    // ═══════════════════════════════════════════════════════════════
    // BLOK 7 — FOOTER CATATAN KERAHASIAAN
    // ═══════════════════════════════════════════════════════════════

    private static function renderFooterNote(
        Worksheet &$sheet,
        array $palette,
        string $lastCol,
        int $dataEndRow
    ): void {
        $footerRow = $dataEndRow + 2;

        $sheet->mergeCells('A' . $footerRow . ':' . $lastCol . $footerRow);
        $sheet->setCellValue(
            'A' . $footerRow,
            'DOKUMEN INI BERSIFAT INTERNAL DAN RAHASIA — Hanya untuk penggunaan resmi Bagian Pengasuhan Santri. '
            . 'Dilarang menggandakan atau menyebarkan tanpa izin tertulis dari pimpinan lembaga. '
            . '© ' . date('Y') . ' AsuhTrack Digital Management System.'
        );
        $sheet->getStyle('A' . $footerRow)->applyFromArray([
            'font' => [
                'italic' => true,
                'size'   => 8,
                'color'  => ['rgb' => '94A3B8'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'wrapText'   => true,
            ],
            'borders' => [
                'top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => $palette['border_color']]],
            ],
        ]);
        $sheet->getRowDimension($footerRow)->setRowHeight(24);
    }

    // ═══════════════════════════════════════════════════════════════
    // BLOK 8 — PENGATURAN CETAK (Print Setup)
    // ═══════════════════════════════════════════════════════════════

    private static function applyPrintSettings(Worksheet &$sheet, string $titleName): void {
        $pageSetup = $sheet->getPageSetup();
        $pageSetup->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $pageSetup->setPaperSize(PageSetup::PAPERSIZE_A4);
        $pageSetup->setFitToPage(true);
        $pageSetup->setFitToWidth(1);
        $pageSetup->setFitToHeight(0);

        // Header/footer halaman cetak
        $headerFooter = $sheet->getHeaderFooter();
        $headerFooter->setOddHeader('&C&"Arial,Bold"&10' . strtoupper($titleName));
        $headerFooter->setOddFooter(
            '&L&"Arial,Italic"&8AsuhTrack — Dokumen Internal Kesantrian'
            . '&C&"Arial"&8Halaman &P dari &N'
            . '&R&"Arial,Italic"&8Dicetak: ' . date('d/m/Y')
        );

        // Margin cetak (dalam inci)
        $sheet->getPageMargins()->setTop(0.75);
        $sheet->getPageMargins()->setBottom(0.75);
        $sheet->getPageMargins()->setLeft(0.7);
        $sheet->getPageMargins()->setRight(0.7);
        $sheet->getPageMargins()->setHeader(0.3);
        $sheet->getPageMargins()->setFooter(0.3);
    }

    // ═══════════════════════════════════════════════════════════════
    // UTILITAS — Auto-fit Kolom
    // ═══════════════════════════════════════════════════════════════

    private static function autoFitColumns(Worksheet &$sheet): void {
        // Menggunakan iterasi native PhpSpreadsheet untuk mendukung kolom melewati 'Z'
        foreach ($sheet->getColumnIterator() as $column) {
            $sheet->getColumnDimension($column->getColumnIndex())->setAutoSize(true);
        }
    }
}