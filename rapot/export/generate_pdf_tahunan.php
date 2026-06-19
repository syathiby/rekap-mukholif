<?php
// rapot/export/generate_pdf_tahunan.php
// Generate PDF Rapor TAHUNAN — 2 Halaman (Sama dengan format Bulanan)

require_once __DIR__ . '/../../bootstrap/init.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../config/helper.php';
require_once __DIR__ . '/../api/generate_catatan.php';

guard('rapot_cetak');

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    http_response_code(400);
    die('Error: ID Rapor Tahunan tidak ditemukan.');
}

try {
    // Ambil data rapor tahunan
    $stmt = $conn->prepare("
        SELECT
            rt.*,
            s.id   AS santri_id,
            s.nama AS nama_santri,
            s.kamar AS kamar_santri,
            s.kelas AS kelas_santri,
            u.nama_lengkap AS nama_musyrif
        FROM rapot_tahunan rt
        LEFT JOIN santri s ON rt.santri_id = s.id
        LEFT JOIN users  u ON rt.approved_by = u.id
        WHERE rt.id = ?
    ");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $rapot = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$rapot) {
        http_response_code(404);
        die('Error: Data rapor tahunan tidak ditemukan.');
    }

    if ($rapot['status'] !== 'APPROVED') {
        http_response_code(403);
        die('Error: Rapor tahunan harus di-approve terlebih dahulu sebelum bisa di-download.');
    }

    // Ambil rekap pelanggaran periode ini
    [$tahun_awal] = explode('/', $rapot['periode']);
    $tahun_akhir  = (int)$tahun_awal + 1;

    $stmt_pel = $conn->prepare("
        SELECT jp.nama_pelanggaran, COUNT(*) AS jumlah, SUM(jp.poin) as total_poin
        FROM pelanggaran p
        JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id
        WHERE p.santri_id = ?
          AND (YEAR(p.tanggal) = ? OR YEAR(p.tanggal) = ?)
        GROUP BY jp.id, jp.nama_pelanggaran
        ORDER BY jumlah DESC
    ");
    $stmt_pel->bind_param('iii', $rapot['santri_id'], $tahun_awal, $tahun_akhir);
    $stmt_pel->execute();
    $pelanggaran_rekap = $stmt_pel->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_pel->close();

    // Ambil rekap reward periode ini
    $stmt_rwd = $conn->prepare("
        SELECT jr.nama_reward, COUNT(*) AS jumlah, SUM(jr.poin_reward) as total_poin
        FROM daftar_reward dr
        JOIN jenis_reward jr ON dr.jenis_reward_id = jr.id
        WHERE dr.santri_id = ?
          AND (YEAR(dr.tanggal) = ? OR YEAR(dr.tanggal) = ?)
        GROUP BY jr.id, jr.nama_reward
        ORDER BY jumlah DESC
    ");
    $stmt_rwd->bind_param('iii', $rapot['santri_id'], $tahun_awal, $tahun_akhir);
    $stmt_rwd->execute();
    $reward_rekap = $stmt_rwd->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_rwd->close();

} catch (Exception $e) {
    die('Error database: ' . $e->getMessage());
}

// ============================================================
// Siapkan variabel untuk template
// ============================================================
$santri = [
    'nama'  => $rapot['nama_santri']  ?? 'Santri Dihapus',
    'kamar' => $rapot['kamar_santri'] ?? $rapot['kamar'] ?? 'N/A',
    'kelas' => $rapot['kelas_santri'] ?? 'N/A',
];
$periode         = $rapot['periode'];
$narasi_global   = $rapot['narasi_ai']       ?? ''; // Diisi oleh generate_catatan.php saat create
$nama_musyrif    = $rapot['nama_musyrif']    ?? 'Musyrif';

// Hitung total poin pelanggaran dari rekap
$total_pelanggaran = 0;
foreach ($pelanggaran_rekap as $p) {
    $total_pelanggaran += (int)$p['total_poin'];
}

// Hitung total poin reward dari rekap
$total_reward = 0;
foreach ($reward_rekap as $r) {
    $total_reward += (int)$r['total_poin'];
}

$nilai_aspek = json_decode($rapot['nilai_snapshot'] ?? '[]', true) ?? [];
$total_nilai = 0;
foreach ($nilai_aspek as $aspek) {
    foreach ($aspek['sub_mutu'] ?? [] as $sub) {
        $total_nilai += (float)($sub['nilai_final'] ?? 0);
    }
}

$logo_path = __DIR__ . '/../../assets/img/Kop Syathiby.jpg';
if (!file_exists($logo_path)) $logo_path = ''; 

// Render template HTML
ob_start();
include __DIR__ . '/../config/template_rapot_tahunan.php';
$html = ob_get_clean();

// ============================================================
// Generate PDF via mPDF
// ============================================================
try {
    $mpdf = new \Mpdf\Mpdf([
        'mode'          => 'utf-8',
        'format'        => 'A4',
        'margin_left'   => 10,
        'margin_right'  => 10,
        'margin_top'    => 7,
        'margin_bottom' => 4,
    ]);

    $mpdf->WriteHTML($html);

    $nama_clean = preg_replace('/[^a-zA-Z0-9 ]/', '', $santri['nama']);
    $nama_file  = "Rapor Tahunan {$nama_clean} - {$periode}.pdf";

    $mpdf->Output($nama_file, \Mpdf\Output\Destination::DOWNLOAD);

} catch (\Mpdf\MpdfException $e) {
    die('Error mPDF: ' . $e->getMessage());
}
exit;
