<?php
// Pastikan Anda sudah menginstal PhpSpreadsheet melalui Composer
// Jalankan: composer require phpoffice/phpspreadsheet
require '../vendor/autoload.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../bootstrap/init.php';
guard('export_laporan');

// 1. Validasi CSRF Token untuk Keamanan
if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403);
    require __DIR__ . '/../bootstrap/csrf_expired.php';
    exit;
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

require_once __DIR__ . '/template-export.php';

// =================================================================================
// KONSTANTA IDENTITAS LEMBAGA
// Sentralisasi: ubah di sini, berlaku di semua laporan.
// =================================================================================

const LEMBAGA_NAMA    = "Ma'had Tahfizh Al-Qur'an Al-Imam Asy-Syathiby Putra";
const LEMBAGA_ALAMAT  = "Jl. Pahlawan, RT.01/RW.05, Cileungsi, Kabupaten Bogor, Jawa Barat";
const LEMBAGA_PERIODE = "Tahun Ajaran 2024/2025"; // <-- Ubah sesuai periode aktif

// Nama pencetak — diambil dari session yang di-set saat login.
// Key 'nama_lengkap' sesuai dengan: $_SESSION['nama_lengkap'] = $user['nama_lengkap'] di login.php
$printed_by = $_SESSION['nama_lengkap'] ?? $_SESSION['username'] ?? 'Sistem';

// =================================================================================
// LOGIKA UTAMA
// =================================================================================

$spreadsheet = new Spreadsheet();


// ─────────────────────────────────────────────────────────────────────────────────
// OPSI 1: Laporan Pelanggaran Lengkap (4 sheet sekaligus)
// ─────────────────────────────────────────────────────────────────────────────────
if (isset($_POST['export'])) {

    $tanggal_mulai   = $_POST['tanggal_mulai']   ?? '';
    $tanggal_selesai = $_POST['tanggal_selesai'] ?? '';
    $kamar           = $_POST['kamar']           ?? 'semua';

    // Sanitasi input kamar
    $kamar = filter_var($kamar, FILTER_SANITIZE_SPECIAL_CHARS);

    // Fungsi pembantu validasi format tanggal Y-m-d
    if (!function_exists('isValidDate')) {
        function isValidDate($date, $format = 'Y-m-d') {
            $d = DateTime::createFromFormat($format, $date);
            return $d && $d->format($format) === $date;
        }
    }

    // Validasi format tanggal
    if (empty($tanggal_mulai) || empty($tanggal_selesai) || !isValidDate($tanggal_mulai) || !isValidDate($tanggal_selesai)) {
        set_flash_message('Input tanggal tidak valid! Gunakan format tanggal yang benar.', 'danger');
        header('Location: index.php');
        exit();
    }

    // Validasi rentang logis tanggal (Mulai tidak boleh melampaui Selesai)
    if (strtotime($tanggal_mulai) > strtotime($tanggal_selesai)) {
        set_flash_message('Tanggal Mulai tidak boleh melewati Tanggal Selesai!', 'danger');
        header('Location: index.php');
        exit();
    }

    // Format periode yang terbaca manusia untuk kop surat
    $periodeLabel = date('d M Y', strtotime($tanggal_mulai))
                  . ' s.d. '
                  . date('d M Y', strtotime($tanggal_selesai));

    // Opsi dasar lembaga yang dipakai bersama (di-merge per sheet dengan opsi spesifik)
    $baseOptions = [
        'institution' => LEMBAGA_NAMA,
        'address'     => LEMBAGA_ALAMAT,
        'period'      => $periodeLabel,
        'printed_by'  => $printed_by,
    ];

    $params = [$tanggal_mulai, $tanggal_selesai];
    $types  = "ss";
    $kamarClause = "";
    if ($kamar !== 'semua') {
        $kamarClause = " AND s.kamar = ?";
        $params[]    = $kamar;
        $types      .= "s";
    }


    // ── SHEET 1: DETAIL SANTRI ──────────────────────────────────────────────────
    $sheetSantri = $spreadsheet->getActiveSheet();
    $sheetSantri->setTitle('Detail Santri');

    $sheetSantri->fromArray(
        ['No', 'Nama Santri', 'Kelas', 'Kamar', 'Jumlah Pelanggaran', 'Total Poin Pelanggaran', 'Total Poin Reward', 'Poin Aktif'],
        NULL, 'A1'
    );


    $sqlSantri = "SELECT s.id, s.nama, s.kelas, s.kamar, s.poin_aktif,
                         COUNT(p.id)    AS jumlah_pelanggaran,
                         SUM(jp.poin)  AS total_poin_pelanggaran,
                         (SELECT COALESCE(SUM(jr.poin_reward), 0)
                          FROM daftar_reward dr
                          JOIN jenis_reward jr ON dr.jenis_reward_id = jr.id
                          WHERE dr.santri_id = s.id AND DATE(dr.tanggal) BETWEEN ? AND ?) AS total_poin_reward
                  FROM pelanggaran p
                  JOIN santri s          ON p.santri_id           = s.id
                  JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id
                  WHERE DATE(p.tanggal) BETWEEN ? AND ?
                    AND jp.bagian <> 'Pengabdian'"
                . $kamarClause
                . " GROUP BY s.id, s.nama, s.kelas, s.kamar, s.poin_aktif
                  ORDER BY total_poin_pelanggaran DESC";

    $stmtSantri = $conn->prepare($sqlSantri);
    
    $paramsSantri = [$tanggal_mulai, $tanggal_selesai, $tanggal_mulai, $tanggal_selesai];
    $typesSantri  = "ssss";
    if ($kamar !== 'semua') {
        $paramsSantri[] = $kamar;
        $typesSantri   .= "s";
    }
    $stmtSantri->bind_param($typesSantri, ...$paramsSantri);
    $stmtSantri->execute();
    $resultSantri = $stmtSantri->get_result();

    $totalSantriTerlibat = 0;
    $poinTertinggi       = 0;
    $santriTertinggi     = '-';

    if ($resultSantri->num_rows > 0) {
        $rowNum = 2; $no = 1;
        while ($row = $resultSantri->fetch_assoc()) {
            $sheetSantri->fromArray(
                [$no, $row['nama'], $row['kelas'], $row['kamar'],
                 $row['jumlah_pelanggaran'], $row['total_poin_pelanggaran'], $row['total_poin_reward'], $row['poin_aktif']],
                NULL, 'A' . $rowNum
            );
            $totalSantriTerlibat++;
            if ((int) $row['total_poin_pelanggaran'] > $poinTertinggi) {
                $poinTertinggi   = (int) $row['total_poin_pelanggaran'];
                $santriTertinggi = $row['nama'];
            }
            $rowNum++; $no++;
        }
    }
    $stmtSantri->close();

    ExcelTemplate::applyExecutiveStyle(
        $sheetSantri,
        'Rekapitulasi Detail Santri (Pelanggaran & Reward)',
        ExcelTemplate::THEME_VIOLATION,
        array_merge($baseOptions, [
            'doc_number'   => 'ASUH/DISIP/' . date('Y') . '/001',
            'summary_data' => [
                ['label' => 'Santri Terlibat',  'value' => $totalSantriTerlibat . ' santri'],
                ['label' => 'Poin Pelanggaran Tertinggi',   'value' => $poinTertinggi . ' poin'],
                ['label' => 'Santri Tertinggi', 'value' => $santriTertinggi],
                ['label' => 'Kamar Filter',     'value' => ($kamar === 'semua') ? 'Semua Kamar' : 'Kamar ' . $kamar],
            ],
        ])
    );

    // ── SHEET 2: DETAIL PELANGGARAN UMUM ────────────────────────────────────────
    $sheetDetail = $spreadsheet->createSheet();
    $sheetDetail->setTitle('Detail Pelanggaran Umum');

    $sheetDetail->fromArray(
        ['No', 'Nama Santri', 'Kelas', 'Kamar', 'Nama Pelanggaran', 'Poin', 'Bagian', 'Tanggal'],
        NULL, 'A1'
    );


    $sqlDetail = "SELECT s.nama, s.kelas, s.kamar, jp.nama_pelanggaran, jp.poin, jp.bagian, p.tanggal
                  FROM pelanggaran p
                  JOIN santri s          ON p.santri_id           = s.id
                  JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id
                  WHERE DATE(p.tanggal) BETWEEN ? AND ?
                    AND jp.bagian <> 'Pengabdian'"
                . $kamarClause
                . " ORDER BY p.tanggal ASC";

    $stmt   = $conn->prepare($sqlDetail);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $totalDetailRows = 0;
    $totalPoinDetail = 0;
    $bagianSet       = [];

    if ($result->num_rows > 0) {
        $rowNum = 2; $no = 1;
        while ($row = $result->fetch_assoc()) {
            $sheetDetail->fromArray(
                [$no, $row['nama'], $row['kelas'], $row['kamar'],
                 $row['nama_pelanggaran'], $row['poin'], $row['bagian'],
                 date('d-m-Y', strtotime($row['tanggal']))],
                NULL, 'A' . $rowNum
            );
            $totalDetailRows++;
            $totalPoinDetail        += (int) $row['poin'];
            $bagianSet[$row['bagian']] = true;
            $rowNum++; $no++;
        }
    }
    $stmt->close();

    ExcelTemplate::applyExecutiveStyle(
        $sheetDetail,
        'Laporan Detail Pelanggaran Santri',
        ExcelTemplate::THEME_VIOLATION,
        array_merge($baseOptions, [
            'doc_number'   => 'ASUH/DISIP/' . date('Y') . '/002',
            'summary_data' => [
                ['label' => 'Total Kejadian',  'value' => $totalDetailRows . ' kasus'],
                ['label' => 'Total Poin',      'value' => $totalPoinDetail . ' poin'],
                ['label' => 'Bagian Terlibat', 'value' => implode(', ', array_keys($bagianSet))],
                ['label' => 'Kamar Filter',    'value' => ($kamar === 'semua') ? 'Semua Kamar' : 'Kamar ' . $kamar],
            ],
        ])
    );

    // ── SHEET 3: REKAP PELANGGARAN UMUM PER KAMAR ──────────────────────────────
    $sheetRekapKamar = $spreadsheet->createSheet();
    // Excel membatasi nama sheet maksimal 31 karakter.
    // "Rekap Pelanggaran Umum Per Kamar" = 32 karakter, sehingga diubah menjadi:
    $sheetRekapKamar->setTitle('Rekap Pelanggaran Umum Kamar');

    $sheetRekapKamar->fromArray(
        ['No', 'Kamar', 'Jumlah Pelanggaran', 'Catatan'],
        NULL, 'A1'
    );


    $sqlKamar = "SELECT s.kamar, COUNT(p.id) AS jumlah_pelanggaran
                 FROM pelanggaran p
                 JOIN santri s          ON p.santri_id           = s.id
                 JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id
                 WHERE DATE(p.tanggal) BETWEEN ? AND ?
                   AND jp.bagian <> 'Pengabdian'"
               . $kamarClause
               . " GROUP BY s.kamar
                 ORDER BY jumlah_pelanggaran DESC";

    $stmt   = $conn->prepare($sqlKamar);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $totalKamarTerlibat   = 0;
    $kamarTerburuk        = '-';
    $kasusKamarTertinggi  = 0;

    if ($result->num_rows > 0) {
        $rowNum = 2; $no = 1;
        while ($row = $result->fetch_assoc()) {
            $sheetRekapKamar->fromArray(
                [$no, $row['kamar'], $row['jumlah_pelanggaran'], ''],
                NULL, 'A' . $rowNum
            );
            $totalKamarTerlibat++;
            if ($no === 1) { // baris pertama = tertinggi karena sudah ORDER BY DESC
                $kamarTerburuk       = $row['kamar'];
                $kasusKamarTertinggi = $row['jumlah_pelanggaran'];
            }
            $rowNum++; $no++;
        }
    }
    $stmt->close();

    ExcelTemplate::applyExecutiveStyle(
        $sheetRekapKamar,
        'Rekapitulasi Pelanggaran Umum Per Kamar',
        ExcelTemplate::THEME_VIOLATION,
        array_merge($baseOptions, [
            'doc_number'   => 'ASUH/DISIP/' . date('Y') . '/003',
            'summary_data' => [
                ['label' => 'Total Kamar',         'value' => $totalKamarTerlibat . ' kamar'],
                ['label' => 'Kamar Terbanyak',     'value' => 'Kamar ' . $kamarTerburuk],
                ['label' => 'Jumlah Kasus Teratas','value' => $kasusKamarTertinggi . ' kasus'],
            ],
        ])
    );

    // ── SHEET 4: REKAP PELANGGARAN KEBERSIHAN ──────────────────────────────────
    $sheetKebersihan = $spreadsheet->createSheet();
    $sheetKebersihan->setTitle('Rekap Pelanggaran Kebersihan');

    $sheetKebersihan->fromArray(
        ['No', 'Kamar', 'Jumlah Pelanggaran', 'Catatan'],
        NULL, 'A1'
    );


    $sqlKebersihan = "SELECT kamar, COUNT(id) AS jumlah_pelanggaran
                      FROM pelanggaran_kebersihan
                      WHERE DATE(tanggal) BETWEEN ? AND ?";
    
    $paramsKebersihan = [$tanggal_mulai, $tanggal_selesai];
    $typesKebersihan  = "ss";
    
    if ($kamar !== 'semua') {
        $sqlKebersihan .= " AND kamar = ?";
        $paramsKebersihan[] = $kamar;
        $typesKebersihan .= "s";
    }
    
    $sqlKebersihan .= " GROUP BY kamar ORDER BY jumlah_pelanggaran DESC";

    $stmtKebersihan = $conn->prepare($sqlKebersihan);
    $stmtKebersihan->bind_param($typesKebersihan, ...$paramsKebersihan);
    $stmtKebersihan->execute();
    $resultKebersihan = $stmtKebersihan->get_result();

    $totalKamarKebersihan = 0;
    $kamarTerburukKebersihan = '-';
    $kasusTertinggiKebersihan = 0;

    if ($resultKebersihan->num_rows > 0) {
        $rowNum = 2; $no = 1;
        while ($row = $resultKebersihan->fetch_assoc()) {
            $sheetKebersihan->fromArray(
                [$no, $row['kamar'], $row['jumlah_pelanggaran'], ''],
                NULL, 'A' . $rowNum
            );
            $totalKamarKebersihan++;
            if ($no === 1) {
                $kamarTerburukKebersihan = $row['kamar'];
                $kasusTertinggiKebersihan = $row['jumlah_pelanggaran'];
            }
            $rowNum++; $no++;
        }
    }
    $stmtKebersihan->close();

    ExcelTemplate::applyExecutiveStyle(
        $sheetKebersihan,
        'Rekapitulasi Pelanggaran Kebersihan Per Kamar',
        ExcelTemplate::THEME_VIOLATION,
        array_merge($baseOptions, [
            'doc_number'   => 'ASUH/DISIP/' . date('Y') . '/004',
            'summary_data' => [
                ['label' => 'Total Kamar',         'value' => $totalKamarKebersihan . ' kamar'],
                ['label' => 'Kamar Terbanyak',     'value' => 'Kamar ' . $kamarTerburukKebersihan],
                ['label' => 'Jumlah Kasus Teratas','value' => $kasusTertinggiKebersihan . ' kasus'],
            ],
        ])
    );

    $spreadsheet->setActiveSheetIndex(0);

    $kamarLabel = ($kamar === 'semua') ? 'Semua_Kamar' : 'Kamar_' . str_replace(' ', '_', $kamar);
    $namaFile   = 'Laporan_Lengkap_Pelanggaran_' . $kamarLabel . '_' . date('d-m-Y') . '.xlsx';


// ─────────────────────────────────────────────────────────────────────────────────
// OPSI 2: Export Master Data Santri
// ─────────────────────────────────────────────────────────────────────────────────
} elseif (isset($_POST['export_santri'])) {

    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Data Santri');

    $sheet->fromArray(
        ['ID Santri', 'Nama Lengkap', 'Kelas', 'Kamar'],
        NULL, 'A1'
    );

    $sql    = "SELECT id, nama, kelas, kamar
               FROM santri
               ORDER BY CAST(kamar AS UNSIGNED) ASC, kelas ASC, nama ASC";
    $result = $conn->query($sql);

    $totalSantri = 0;

    if ($result && $result->num_rows > 0) {
        $rowNum = 2;
        while ($row = $result->fetch_assoc()) {
            $sheet->fromArray(
                [$row['id'], $row['nama'], $row['kelas'], $row['kamar']],
                NULL, 'A' . $rowNum
            );
            $totalSantri++;
            $rowNum++;
        }
    }

    ExcelTemplate::applyExecutiveStyle(
        $sheet,
        'Data Induk Profil Santri Aktif',
        ExcelTemplate::THEME_GENERAL,
        [
            'institution'  => LEMBAGA_NAMA,
            'address'      => LEMBAGA_ALAMAT,
            'period'       => LEMBAGA_PERIODE,
            'printed_by'   => $printed_by,
            'doc_number'   => 'ASUH/DATA/' . date('Y') . '/001',
            'summary_data' => [
                ['label' => 'Total Santri Aktif',   'value' => $totalSantri . ' santri'],
                ['label' => 'Status Data',          'value' => 'Terverifikasi'],
            ],
        ]
    );

    $namaFile = 'Master_Data_Santri_' . date('d-m-Y') . '.xlsx';


// ─────────────────────────────────────────────────────────────────────────────────
// OPSI 3: Export Master Data Jenis Pelanggaran
// ─────────────────────────────────────────────────────────────────────────────────
} elseif (isset($_POST['export_jenis_pelanggaran'])) {

    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Data Jenis Pelanggaran');

    $sheet->fromArray(
        ['ID', 'Nama Pelanggaran', 'Poin', 'Kategori', 'Bagian'],
        NULL, 'A1'
    );

    $sql    = "SELECT id, nama_pelanggaran, poin, kategori, bagian
               FROM jenis_pelanggaran
               ORDER BY
                   FIELD(bagian, 'Kesantrian', 'Bahasa', 'Diniyyah', 'TAHFIDZ', 'Pengabdian'),
                   FIELD(kategori, 'Ringan', 'Sedang', 'Berat', 'Sangat Berat'),
                   poin ASC,
                   nama_pelanggaran ASC";
    $result = $conn->query($sql);

    $totalJenis    = 0;
    $totalBagian   = [];
    $totalKategori = [];

    if ($result && $result->num_rows > 0) {
        $rowNum = 2;
        while ($row = $result->fetch_assoc()) {
            $sheet->fromArray(
                [$row['id'], $row['nama_pelanggaran'], $row['poin'], $row['kategori'], $row['bagian']],
                NULL, 'A' . $rowNum
            );
            $totalJenis++;
            $totalBagian[$row['bagian']]     = true;
            $totalKategori[$row['kategori']] = true;
            $rowNum++;
        }
    }

    ExcelTemplate::applyExecutiveStyle(
        $sheet,
        'Data Master Klasifikasi Jenis Pelanggaran',
        ExcelTemplate::THEME_GENERAL,
        [
            'institution'  => LEMBAGA_NAMA,
            'address'      => LEMBAGA_ALAMAT,
            'period'       => LEMBAGA_PERIODE,
            'printed_by'   => $printed_by,
            'doc_number'   => 'ASUH/DATA/' . date('Y') . '/002',
            'summary_data' => [
                ['label' => 'Total Jenis',   'value' => $totalJenis . ' jenis'],
                ['label' => 'Bagian',        'value' => count($totalBagian) . ' bagian'],
                ['label' => 'Tingkat',       'value' => implode(', ', array_keys($totalKategori))],
            ],
        ]
    );

    $namaFile = 'Master_Data_Jenis_Pelanggaran_' . date('d-m-Y') . '.xlsx';


// ─────────────────────────────────────────────────────────────────────────────────
// OPSI 4: Export Master Data Jenis Reward
// ─────────────────────────────────────────────────────────────────────────────────
} elseif (isset($_POST['export_jenis_reward'])) {

    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Data Jenis Reward');

    $sheet->fromArray(
        ['ID', 'Nama Reward', 'Poin Reward', 'Deskripsi'],
        NULL, 'A1'
    );

    $sql    = "SELECT id, nama_reward, poin_reward, deskripsi
               FROM jenis_reward
               ORDER BY poin_reward ASC, nama_reward ASC";
    $result = $conn->query($sql);

    $totalJenis = 0;
    $totalPoin  = 0;

    if ($result && $result->num_rows > 0) {
        $rowNum = 2;
        while ($row = $result->fetch_assoc()) {
            $sheet->fromArray(
                [$row['id'], $row['nama_reward'], (int)$row['poin_reward'], !empty($row['deskripsi']) ? $row['deskripsi'] : '-'],
                NULL, 'A' . $rowNum
            );
            $totalJenis++;
            $totalPoin += (int)$row['poin_reward'];
            $rowNum++;
        }
    }

    ExcelTemplate::applyExecutiveStyle(
        $sheet,
        'Data Master Klasifikasi Jenis Reward',
        ExcelTemplate::THEME_REWARD,
        [
            'institution'  => LEMBAGA_NAMA,
            'address'      => LEMBAGA_ALAMAT,
            'period'       => LEMBAGA_PERIODE,
            'printed_by'   => $printed_by,
            'doc_number'   => 'ASUH/DATA/' . date('Y') . '/003',
            'summary_data' => [
                ['label' => 'Total Jenis Reward', 'value' => $totalJenis . ' jenis'],
                ['label' => 'Akumulasi Poin',     'value' => $totalPoin . ' poin'],
            ],
        ]
    );

    $namaFile = 'Master_Data_Jenis_Reward_' . date('d-m-Y') . '.xlsx';


// ─────────────────────────────────────────────────────────────────────────────────
// FALLBACK
// ─────────────────────────────────────────────────────────────────────────────────
} else {
    header('Location: index.php');
    exit();
}


// =================================================================================
// PROSES DOWNLOAD FILE
// =================================================================================

if (ob_get_length()) {
    ob_end_clean();
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $namaFile . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');

$conn->close();
exit();