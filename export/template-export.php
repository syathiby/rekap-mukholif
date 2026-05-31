<?php
/**
 * AsuhTrack Executive Excel Styling Engine v4.0 (Full Layout Fix)
 * ─────────────────────────────────────────────────────────────────────────────
 * Perbaikan v4.0:
 *  - TOTAL_HEADER_ROWS = 13 (hapus 2 spacer row penyebab "header ngambang")
 *  - wrapText aktif di baris nama lembaga, judul, keterangan, & summary banner
 *  - MIN_HEADER_COLS = 8 (header minimal 8 kolom agar teks tidak terpotong)
 *  - Extra column (di luar data) diberi lebar 18 agar metadata lapang
 *  - Kolom A (No) dikunci lebar 6 — tidak ikut auto-fit
 *  - Metadata disusun 2 kolom dinamis 50/50 (merge presisi, tidak overlap)
 *  - Summary banner satu baris merged penuh dengan wrapText
 *  - autoFitColumns hanya membaca baris tabel data (bukan header/footer)
 */

use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

class ExcelTemplate {

    // ═══════════════════════════════════════════════════════════════
    // KONSTANTA TEMA WARNA
    // ═══════════════════════════════════════════════════════════════

    const THEME_GENERAL   = 'GENERAL';
    const THEME_VIOLATION = 'VIOLATION';
    const THEME_REWARD    = 'REWARD';

    // ═══════════════════════════════════════════════════════════════
    // KONSTANTA LAYOUT
    // Layout baris:
    //   1  — Banner sistem (accent)
    //   2  — Nama lembaga (besar)
    //   3  — Alamat lembaga
    //   4  — Garis dekoratif tebal
    //   5  — Judul laporan
    //   6  — Garis pemisah tipis
    //   7  — Spacer kop surat
    //   8  — Nomor Dokumen | Dicetak Oleh
    //   9  — Periode Laporan | Waktu Cetak
    //   10 — Keterangan (full width)
    //   11 — Spacer metadata (tipis)
    //   12 — ▌ RINGKASAN LAPORAN (judul panel)
    //   13 — Summary banner (satu baris teks statistik)
    //  [14] — Header tabel data (tableHeaderRow = TOTAL_HEADER_ROWS + 1)
    //  [15+] — Baris data
    // ═══════════════════════════════════════════════════════════════

    const TOTAL_HEADER_ROWS = 13;  // 7 kop + 4 metadata + 2 summary (tanpa spacer)
    const MIN_HEADER_COLS   = 8;   // Lebar minimum header agar teks tidak terpotong

    // ─────────────────────────────────────────────────────────────────
    // PALET WARNA
    // ─────────────────────────────────────────────────────────────────

    private static function getThemePalette(string $theme): array {
        $palettes = [
            self::THEME_VIOLATION => [
                'primary'      => '7F1D1D',
                'secondary'    => '991B1B',
                'accent'       => 'DC2626',
                'header_font'  => 'FFFFFF',
                'zebra_fill'   => 'FEF2F2',
                'meta_fill'    => 'FFF5F5',
                'summary_fill' => 'FEE2E2',
                'border_color' => 'FECACA',
            ],
            self::THEME_REWARD => [
                'primary'      => '052E16',
                'secondary'    => '064E3B',
                'accent'       => '059669',
                'header_font'  => 'FFFFFF',
                'zebra_fill'   => 'F0FDF4',
                'meta_fill'    => 'F0FFF4',
                'summary_fill' => 'DCFCE7',
                'border_color' => 'BBF7D0',
            ],
            self::THEME_GENERAL => [
                'primary'      => '0F172A',
                'secondary'    => '1E293B',
                'accent'       => '334155',
                'header_font'  => 'FFFFFF',
                'zebra_fill'   => 'F8FAFC',
                'meta_fill'    => 'F1F5F9',
                'summary_fill' => 'E2E8F0',
                'border_color' => 'CBD5E1',
            ],
        ];
        return $palettes[$theme] ?? $palettes[self::THEME_GENERAL];
    }

    // ─────────────────────────────────────────────────────────────────
    // HELPER: Tulis cell aman (anti Formula/CSV Injection)
    // ─────────────────────────────────────────────────────────────────

    private static function setSafeCellValue(Worksheet &$sheet, string $cell, $value): void {
        $str = (string)$value;
        if (strlen($str) > 0 && in_array($str[0], ['=', '+', '-', '@'])) {
            $sheet->setCellValueExplicit(
                $cell, $str, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
            );
        } else {
            $sheet->setCellValue($cell, $value);
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // HELPER: Tulis RichText metadata (label bold + value regular)
    // ─────────────────────────────────────────────────────────────────

    private static function setRichText(
        Worksheet &$sheet,
        string $cell,
        string $label,
        string $value,
        string $labelRgb,
        string $valueRgb
    ): void {
        $rt = new \PhpOffice\PhpSpreadsheet\RichText\RichText();

        $rl = $rt->createTextRun('  ' . $label . ': ');
        $rl->getFont()->setBold(true)->setSize(9)
           ->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color($labelRgb));

        $rv = $rt->createTextRun($value);
        $rv->getFont()->setBold(false)->setSize(9)
           ->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color($valueRgb));

        $sheet->setCellValue($cell, $rt);
    }

    // ─────────────────────────────────────────────────────────────────
    // HELPER: Hitung kolom header (selalu >= MIN_HEADER_COLS)
    // ─────────────────────────────────────────────────────────────────

    private static function getHeaderLastCol(string $dataLastCol): string {
        $idx = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($dataLastCol);
        return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(
            max($idx, self::MIN_HEADER_COLS)
        );
    }

    // ═══════════════════════════════════════════════════════════════
    // ENTRY POINT UTAMA
    // ═══════════════════════════════════════════════════════════════

    public static function applyExecutiveStyle(
        Worksheet &$sheet,
        string $titleName,
        string $themeType = self::THEME_GENERAL,
        array $options = []
    ): void {
        if ($sheet->getHighestRow() <= 1) {
            return;
        }

        // Font premium global
        $sheet->getParent()->getDefaultStyle()->getFont()->setName('Segoe UI')->setSize(10);

        $palette = self::getThemePalette($themeType);

        // Rekam kolom & baris data SEBELUM insert
        $dataLastCol        = $sheet->getHighestColumn();
        $originalHighestRow = $sheet->getHighestRow();

        // Kolom header diperluas ke minimum MIN_HEADER_COLS
        $headerLastCol = self::getHeaderLastCol($dataLastCol);

        // Insert baris header (13 baris)
        $sheet->insertNewRowBefore(1, self::TOTAL_HEADER_ROWS);

        // Render blok header
        self::renderLetterhead($sheet, $palette, $headerLastCol, $titleName, $options);
        self::renderDocumentMetadata($sheet, $palette, $headerLastCol, $options);
        self::renderSummaryPanel($sheet, $palette, $headerLastCol, $options);

        // Posisi tabel data (langsung setelah baris 13, tidak ada spacer tambahan)
        $tableHeaderRow = self::TOTAL_HEADER_ROWS + 1; // = 14
        $dataStartRow   = $tableHeaderRow + 1;          // = 15
        $dataEndRow     = $tableHeaderRow + $originalHighestRow - 1;

        // Render tabel data — pakai dataLastCol (bukan headerLastCol)
        self::styleTableHeader($sheet, $palette, $dataLastCol, $tableHeaderRow);
        self::styleDataRows($sheet, $palette, $dataLastCol, $dataStartRow, $dataEndRow);
        self::applyTableNavigation($sheet, $dataLastCol, $tableHeaderRow, $dataStartRow);

        // Print settings, footer, lalu auto-fit
        self::applyPrintSettings($sheet, $titleName, $headerLastCol);
        self::renderFooterNote($sheet, $palette, $headerLastCol, $dataEndRow);
        self::autoFitColumns($sheet, $tableHeaderRow, $dataEndRow, $dataLastCol, $headerLastCol);
    }

    // ═══════════════════════════════════════════════════════════════
    // BLOK 1 — KOP SURAT FORMAL (Baris 1–7)
    // ═══════════════════════════════════════════════════════════════

    private static function renderLetterhead(
        Worksheet &$sheet,
        array $palette,
        string $lastCol,   // headerLastCol (min. MIN_HEADER_COLS)
        string $titleName,
        array $options
    ): void {
        $institution = $options['institution'] ?? 'PONDOK PESANTREN';
        $address     = $options['address']     ?? 'Jl. Pesantren No. 1, Indonesia';

        // Merge seluruh lebar header untuk baris 1–6
        foreach ([1, 2, 3, 4, 5, 6] as $row) {
            $sheet->mergeCells('A' . $row . ':' . $lastCol . $row);
        }

        // Baris 1: Banner sistem (warna accent, teks putih kecil)
        self::setSafeCellValue($sheet, 'A1', 'ASUHTRACK DIGITAL MANAGEMENT SYSTEM');
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 8, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER],
            'fill'      => ['fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => $palette['accent']]],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(14);

        // Baris 2: Nama Lembaga (besar, wrapText aktif agar tidak terpotong)
        self::setSafeCellValue($sheet, 'A2', strtoupper($institution));
        $sheet->getStyle('A2')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 16,
                            'color' => ['rgb' => $palette['primary']]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER,
                            'wrapText'   => true],                          // ← FIX
            'fill'      => ['fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'FFFFFF']],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(34);

        // Baris 3: Alamat lembaga (italic, wrapText aktif)
        self::setSafeCellValue($sheet, 'A3', $address);
        $sheet->getStyle('A3')->applyFromArray([
            'font'      => ['size' => 9, 'italic' => true,
                            'color' => ['rgb' => '64748B']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER,
                            'wrapText'   => true],                          // ← FIX
            'fill'      => ['fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'FFFFFF']],
        ]);
        $sheet->getRowDimension(3)->setRowHeight(14);

        // Baris 4: Garis dekoratif tebal (warna primary)
        $sheet->getStyle('A4:' . $lastCol . '4')->applyFromArray([
            'fill'    => ['fillType' => Fill::FILL_SOLID,
                          'startColor' => ['rgb' => $palette['primary']]],
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THICK,
                                       'color'       => ['rgb' => $palette['accent']]]],
        ]);
        $sheet->getRowDimension(4)->setRowHeight(3);

        // Baris 5: Judul laporan (besar, wrapText aktif)
        self::setSafeCellValue($sheet, 'A5', strtoupper($titleName));
        $sheet->getStyle('A5')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 14,
                            'color' => ['rgb' => $palette['primary']]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER,
                            'wrapText'   => true],                          // ← FIX
            'fill'      => ['fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'FFFFFF']],
        ]);
        $sheet->getRowDimension(5)->setRowHeight(30);

        // Baris 6: Garis pemisah bawah kop (warna secondary)
        $sheet->getStyle('A6:' . $lastCol . '6')->applyFromArray([
            'fill'    => ['fillType' => Fill::FILL_SOLID,
                          'startColor' => ['rgb' => $palette['secondary']]],
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_MEDIUM,
                                       'color'       => ['rgb' => $palette['accent']]]],
        ]);
        $sheet->getRowDimension(6)->setRowHeight(3);

        // Baris 7: Spacer putih tipis
        $sheet->mergeCells('A7:' . $lastCol . '7');
        $sheet->getStyle('A7')->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID,
                       'startColor' => ['rgb' => 'FFFFFF']],
        ]);
        $sheet->getRowDimension(7)->setRowHeight(5);
    }

    // ═══════════════════════════════════════════════════════════════
    // BLOK 2 — METADATA DOKUMEN (Baris 8–11)
    // Layout:
    //   R8:  [Nomor Dokumen: xxx ........] [Dicetak oleh: xxx .......]
    //   R9:  [Periode Laporan: xxx ......] [Waktu Cetak: xxx .........]
    //   R10: [Keterangan: xxx .....................................................]
    //   R11: [Spacer tipis]
    // ═══════════════════════════════════════════════════════════════

    private static function renderDocumentMetadata(
        Worksheet &$sheet,
        array $palette,
        string $lastCol,
        array $options
    ): void {
        $startRow  = 8;
        $period    = $options['period']     ?? '-';
        $docNumber = $options['doc_number']
                   ?? 'AUTO/' . date('Ymd') . '/' . strtoupper(substr(uniqid(), -4));
        $printedBy = $options['printed_by'] ?? 'Sistem';
        $printTime = date('d F Y, H:i') . ' WIB';

        // Latar belakang & outline seluruh blok metadata (R8–R11)
        $sheet->getStyle('A' . $startRow . ':' . $lastCol . ($startRow + 3))->applyFromArray([
            'fill'    => ['fillType' => Fill::FILL_SOLID,
                          'startColor' => ['rgb' => $palette['meta_fill']]],
            'borders' => [
                'outline' => ['borderStyle' => Border::BORDER_THIN,
                              'color'       => ['rgb' => $palette['border_color']]],
            ],
        ]);

        // Titik bagi kolom 50/50 (kiri dan kanan)
        $lastIdx  = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($lastCol);
        $midIdx   = (int)floor($lastIdx / 2);
        $midCol   = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($midIdx);
        $midNext  = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($midIdx + 1);

        // Garis pemisah vertikal tengah (visual divider kiri-kanan)
        $sheet->getStyle($midNext . $startRow . ':' . $midNext . ($startRow + 1))->applyFromArray([
            'borders' => ['left' => ['borderStyle' => Border::BORDER_HAIR,
                                     'color'       => ['rgb' => $palette['border_color']]]],
        ]);

        // R8: Nomor Dokumen (kiri) | Dicetak oleh (kanan)
        $sheet->mergeCells('A' . $startRow . ':' . $midCol . $startRow);
        self::setRichText($sheet, 'A' . $startRow,
            'Nomor Dokumen', $docNumber, $palette['secondary'], '334155');
        $sheet->getStyle('A' . $startRow)->applyFromArray([
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER,
                            'wrapText' => false],
        ]);

        $sheet->mergeCells($midNext . $startRow . ':' . $lastCol . $startRow);
        self::setRichText($sheet, $midNext . $startRow,
            'Dicetak oleh', $printedBy, $palette['secondary'], '334155');
        $sheet->getStyle($midNext . $startRow)->applyFromArray([
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER,
                            'wrapText' => false],
        ]);
        $sheet->getRowDimension($startRow)->setRowHeight(17);

        // R9: Periode Laporan (kiri) | Waktu Cetak (kanan)
        $r9 = $startRow + 1;
        $sheet->mergeCells('A' . $r9 . ':' . $midCol . $r9);
        self::setRichText($sheet, 'A' . $r9,
            'Periode Laporan', $period, $palette['secondary'], '334155');
        $sheet->getStyle('A' . $r9)->applyFromArray([
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER,
                            'wrapText' => false],
        ]);

        $sheet->mergeCells($midNext . $r9 . ':' . $lastCol . $r9);
        self::setRichText($sheet, $midNext . $r9,
            'Waktu Cetak', $printTime, $palette['secondary'], '334155');
        $sheet->getStyle($midNext . $r9)->applyFromArray([
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER,
                            'wrapText' => false],
        ]);
        $sheet->getRowDimension($r9)->setRowHeight(17);

        // R10: Keterangan full-width (wrapText aktif untuk teks panjang)
        $r10 = $startRow + 2;
        $sheet->mergeCells('A' . $r10 . ':' . $lastCol . $r10);
        self::setRichText($sheet, 'A' . $r10,
            'Keterangan',
            'Dokumen ini diterbitkan secara otomatis oleh sistem AsuhTrack.',
            $palette['secondary'], '64748B');
        $sheet->getStyle('A' . $r10)->applyFromArray([
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER,
                            'wrapText' => true],                            // ← FIX
        ]);
        $sheet->getRowDimension($r10)->setRowHeight(15);

        // R11: Spacer bawah metadata (border bottom memisahkan dari summary)
        $r11 = $startRow + 3;
        $sheet->mergeCells('A' . $r11 . ':' . $lastCol . $r11);
        $sheet->getStyle('A' . $r11 . ':' . $lastCol . $r11)->applyFromArray([
            'fill'    => ['fillType' => Fill::FILL_SOLID,
                          'startColor' => ['rgb' => $palette['meta_fill']]],
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN,
                                       'color'       => ['rgb' => $palette['border_color']]]],
        ]);
        $sheet->getRowDimension($r11)->setRowHeight(4);
    }

    // ═══════════════════════════════════════════════════════════════
    // BLOK 3 — PANEL RINGKASAN STATISTIK (Baris 12–13)
    // R12: ▌ RINGKASAN LAPORAN (header panel, background secondary)
    // R13: [Statistik … │ … │ …] (banner merged, wrapText aktif)
    // Tidak ada spacer setelah panel ini — langsung ke tabel data!
    // ═══════════════════════════════════════════════════════════════

    private static function renderSummaryPanel(
        Worksheet &$sheet,
        array $palette,
        string $lastCol,
        array $options
    ): void {
        $r12 = 12;
        $r13 = 13;
        $summaryData = $options['summary_data'] ?? [];

        if (empty($summaryData)) {
            $summaryData = [
                ['label' => 'Total Data', 'value' => '—'],
                ['label' => 'Kategori',   'value' => '—'],
                ['label' => 'Status',     'value' => 'Terverifikasi'],
            ];
        }

        // R12: Header panel (background secondary, teks putih)
        $sheet->mergeCells('A' . $r12 . ':' . $lastCol . $r12);
        $sheet->getStyle('A' . $r12 . ':' . $lastCol . $r12)->applyFromArray([
            'fill'    => ['fillType' => Fill::FILL_SOLID,
                          'startColor' => ['rgb' => $palette['secondary']]],
            'borders' => [
                'outline' => ['borderStyle' => Border::BORDER_MEDIUM,
                              'color'       => ['rgb' => $palette['primary']]],
            ],
        ]);
        self::setSafeCellValue($sheet, 'A' . $r12, '  ▌  RINGKASAN LAPORAN');
        $sheet->getStyle('A' . $r12)->applyFromArray([
            'font'      => ['bold' => true, 'size' => 9,
                            'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT,
                            'vertical'   => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension($r12)->setRowHeight(18);

        // R13: Banner statistik (merged penuh, wrapText aktif agar tidak terpotong)
        $sheet->mergeCells('A' . $r13 . ':' . $lastCol . $r13);
        $sheet->getStyle('A' . $r13 . ':' . $lastCol . $r13)->applyFromArray([
            'fill'    => ['fillType' => Fill::FILL_SOLID,
                          'startColor' => ['rgb' => $palette['summary_fill']]],
            'borders' => [
                'outline' => ['borderStyle' => Border::BORDER_MEDIUM,
                              'color'       => ['rgb' => $palette['secondary']]],
                'bottom'  => ['borderStyle' => Border::BORDER_MEDIUM,
                              'color'       => ['rgb' => $palette['primary']]],
            ],
        ]);

        $parts = [];
        foreach ($summaryData as $item) {
            $parts[] = $item['label'] . ': ' . $item['value'];
        }
        $bannerText = implode('   │   ', $parts);

        self::setSafeCellValue($sheet, 'A' . $r13, $bannerText);
        $sheet->getStyle('A' . $r13)->applyFromArray([
            'font'      => ['bold' => true, 'size' => 10,
                            'color' => ['rgb' => $palette['primary']]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER,
                            'wrapText'   => true],                          // ← FIX
        ]);
        $sheet->getRowDimension($r13)->setRowHeight(24);
    }

    // ═══════════════════════════════════════════════════════════════
    // BLOK 4 — STYLING HEADER TABEL (Baris 14)
    // ═══════════════════════════════════════════════════════════════

    private static function styleTableHeader(
        Worksheet &$sheet,
        array $palette,
        string $lastCol,
        int $headerRow
    ): void {
        $sheet->getStyle('A' . $headerRow . ':' . $lastCol . $headerRow)->applyFromArray([
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
                'allBorders' => ['borderStyle' => Border::BORDER_MEDIUM,
                                 'color'       => ['rgb' => $palette['primary']]],
            ],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(30);
    }

    // ═══════════════════════════════════════════════════════════════
    // BLOK 5 — STYLING BARIS DATA (Baris 15+)
    // ═══════════════════════════════════════════════════════════════

    private static function styleDataRows(
        Worksheet &$sheet,
        array $palette,
        string $lastCol,
        int $startRow,
        int $endRow
    ): void {
        $fullRange = 'A' . $startRow . ':' . $lastCol . $endRow;

        // Border tabel + alignment vertikal massal (O(1))
        $sheet->getStyle($fullRange)->applyFromArray([
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN,
                                 'color'       => ['rgb' => $palette['border_color']]],
                'outline'    => ['borderStyle' => Border::BORDER_MEDIUM,
                                 'color'       => ['rgb' => $palette['secondary']]],
            ],
        ]);
        $sheet->getStyle($fullRange)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

        // Zebra striping + tinggi baris
        for ($row = $startRow; $row <= $endRow; $row++) {
            $sheet->getRowDimension($row)->setRowHeight(20);
            if ($row % 2 === 0) {
                $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->applyFromArray([
                    'fill' => ['fillType'    => Fill::FILL_SOLID,
                               'startColor' => ['rgb' => $palette['zebra_fill']]],
                ]);
            }
        }

        // Rata tengah massal untuk kolom numerik pendek (O(M) — jauh lebih cepat)
        $colCount = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($lastCol);
        for ($c = 1; $c <= $colCount; $c++) {
            $cl  = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
            $val = $sheet->getCell($cl . $startRow)->getValue();
            if (is_numeric($val) && strlen((string)$val) < 6) {
                $sheet->getStyle($cl . $startRow . ':' . $cl . $endRow)
                      ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // BLOK 6 — NAVIGASI TABEL (Auto Filter & Freeze Pane)
    // ═══════════════════════════════════════════════════════════════

    private static function applyTableNavigation(
        Worksheet &$sheet,
        string $lastCol,
        int $headerRow,
        int $dataStartRow
    ): void {
        $sheet->setAutoFilter('A' . $headerRow . ':' . $lastCol . $headerRow);
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
        $row = $dataEndRow + 2;
        $sheet->mergeCells('A' . $row . ':' . $lastCol . $row);
        self::setSafeCellValue(
            $sheet, 'A' . $row,
            'DOKUMEN INI BERSIFAT INTERNAL DAN RAHASIA — Hanya untuk penggunaan resmi '
            . 'Bagian Pengasuhan Santri. Dilarang menggandakan atau menyebarkan tanpa izin '
            . 'tertulis dari pimpinan lembaga. © ' . date('Y') . ' AsuhTrack Digital Management System.'
        );
        $sheet->getStyle('A' . $row)->applyFromArray([
            'font'      => ['italic' => true, 'size' => 8, 'color' => ['rgb' => '94A3B8']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                            'wrapText'   => true],
            'borders'   => ['top' => ['borderStyle' => Border::BORDER_THIN,
                                      'color'       => ['rgb' => $palette['border_color']]]],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(24);
    }

    // ═══════════════════════════════════════════════════════════════
    // BLOK 8 — PENGATURAN CETAK (Responsive Orientation)
    // ═══════════════════════════════════════════════════════════════

    private static function applyPrintSettings(
        Worksheet &$sheet,
        string $titleName,
        string $lastCol
    ): void {
        $pageSetup = $sheet->getPageSetup();
        $colCount  = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($lastCol);
        $pageSetup->setOrientation(
            $colCount <= 6 ? PageSetup::ORIENTATION_PORTRAIT : PageSetup::ORIENTATION_LANDSCAPE
        );
        $pageSetup->setPaperSize(PageSetup::PAPERSIZE_A4);
        $pageSetup->setFitToPage(true)->setFitToWidth(1)->setFitToHeight(0);

        $hf = $sheet->getHeaderFooter();
        $hf->setOddHeader('&C&"Arial,Bold"&10' . strtoupper($titleName));
        $hf->setOddFooter(
            '&L&"Arial,Italic"&8AsuhTrack — Dokumen Internal Kesantrian'
            . '&C&"Arial"&8Halaman &P dari &N'
            . '&R&"Arial,Italic"&8Dicetak: ' . date('d/m/Y')
        );

        $m = $sheet->getPageMargins();
        $m->setTop(0.75)->setBottom(0.75)->setLeft(0.7)->setRight(0.7)
          ->setHeader(0.3)->setFooter(0.3);
    }

    // ═══════════════════════════════════════════════════════════════
    // UTILITAS — Auto-fit Kolom (Presisi & Bounded)
    //
    // Logika:
    //  - Kolom A (No): kunci lebar = 6 (tidak ikut auto-fit)
    //  - Kolom data biasa: ukur dari tableHeaderRow s.d. dataEndRow
    //  - Kolom extra (header lebih lebar dari data): lebar tetap 18
    // ═══════════════════════════════════════════════════════════════

    private static function autoFitColumns(
        Worksheet &$sheet,
        int $startRow,
        int $endRow,
        string $dataLastCol,
        string $headerLastCol
    ): void {
        $dataIdx   = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($dataLastCol);
        $headerIdx = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($headerLastCol);

        for ($c = 1; $c <= $headerIdx; $c++) {
            $cl = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);

            // Kolom A → kunci di 6 (menampung "No" tanpa melar)
            if ($c === 1) {
                $sheet->getColumnDimension($cl)->setWidth(6);
                continue;
            }

            // Kolom di luar kolom data (hanya ada di header) → lebar tetap 18
            if ($c > $dataIdx) {
                $sheet->getColumnDimension($cl)->setWidth(18);
                continue;
            }

            // Kolom data: ukur konten dari headerRow s.d. dataEndRow
            $max = 0;
            for ($row = $startRow; $row <= $endRow; $row++) {
                $len = mb_strwidth(
                    (string)$sheet->getCell($cl . $row)->getFormattedValue(), 'UTF-8'
                );
                if ($len > $max) {
                    $max = $len;
                }
            }
            // Padding +4, minimal 10
            $sheet->getColumnDimension($cl)->setWidth(max($max + 4, 10));
        }
    }
}