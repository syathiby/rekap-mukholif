<?php
// File: rekap-mukholif/rapot/crud_tahunan/view.php
// Menampilkan Rapor Tahunan di Browser (sebagai ganti PDF)

require_once __DIR__ . '/../../bootstrap/init.php'; 
require_once __DIR__ . '/../config/helper.php'; 

guard('rapot_view');

$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    die('Error: ID Rapot tidak ditemukan.');
}

try {
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
        die('Error: Data rapor tahunan tidak ditemukan.');
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

$santri = [
    'nama'  => $rapot['nama_santri']  ?? 'Santri Dihapus',
    'kamar' => $rapot['kamar_santri'] ?? $rapot['kamar'] ?? 'N/A',
    'kelas' => $rapot['kelas_santri'] ?? 'N/A',
];
$periode         = $rapot['periode'];
$narasi_global   = $rapot['narasi_ai']       ?? ''; 
$nama_musyrif    = $_SESSION['nama_lengkap'] ?? $rapot['nama_musyrif']    ?? 'Musyrif';

$total_pelanggaran = 0;
foreach ($pelanggaran_rekap as $p) {
    $total_pelanggaran += (int)$p['total_poin'];
}

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

$logo_path = $base_url . '/assets/img/Kop Syathiby.jpg';
$logo_file_path = __DIR__ . '/../../assets/img/Kop Syathiby.jpg';
if (!file_exists($logo_file_path)) $logo_path = ''; 

echo '<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title>View Rapot - ' . htmlspecialchars($santri['nama']) . '</title>
    <style>
        body {
            background-color: #525659;
            margin: 0;
            padding: 20px 0;
        }
        .page-wrapper {
            width: 210mm;
            min-height: 297mm;
            background-color: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.5);
            margin: 0 auto 20px auto;
            padding: 7mm 10mm 4mm 10mm;
            box-sizing: border-box;
        }
        @media print {
            body, .page-wrapper {
                background-color: white;
                box-shadow: none;
                margin: 0;
                padding: 0;
                width: 210mm;
            }
        }
    </style>
</head>
<body>';

echo '
    <div class="page-wrapper">';

ob_start();
include '../config/template_rapot_tahunan.php';
$html = ob_get_clean();

// Ganti <pagebreak /> dengan penutup div lama dan pembuka div baru
// agar terlihat seperti 2 kertas yang terpisah di browser
$html = str_replace('<pagebreak />', '</div><div class="page-wrapper" style="margin-top: 20px;">', $html);

echo $html;

echo '
    </div>
</body>
</html>';
?>
