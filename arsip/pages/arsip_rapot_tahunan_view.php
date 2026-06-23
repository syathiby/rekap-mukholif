<?php
// arsip/pages/arsip_rapot_tahunan_view.php
// Menampilkan Rapor Tahunan dari Arsip di Browser (sebagai ganti PDF untuk preview)

require_once __DIR__ . '/../../bootstrap/init.php'; 
require_once __DIR__ . '/../../rapot/config/helper.php'; 

guard('arsip_view');

$arsip_id = (int)($_GET['arsip_id'] ?? 0);
$id = (int)($_GET['id'] ?? 0);

if (!$id || !$arsip_id) {
    die('Error: ID Rapot atau ID Arsip tidak ditemukan.');
}

try {
    $stmt = $conn->prepare("
        SELECT rt.*, rt.santri_nama AS nama_santri, rt.kamar, rt.santri_kelas as kelas_santri, rt.approved_by_nama AS nama_musyrif
        FROM arsip_data_rapot_tahunan rt
        WHERE rt.id = ? AND rt.arsip_id = ?
    ");
    $stmt->bind_param('ii', $id, $arsip_id);
    $stmt->execute();
    $rapot = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$rapot) {
        die('Error: Data rapor tahunan di arsip tidak ditemukan.');
    }

    $stmt_pel = $conn->prepare("
        SELECT jenis_pelanggaran_nama as nama_pelanggaran, COUNT(*) AS jumlah, SUM(poin) as total_poin
        FROM arsip_data_pelanggaran
        WHERE arsip_id = ? AND santri_id = ? AND tipe = 'Umum'
        GROUP BY jenis_pelanggaran_nama
        ORDER BY jumlah DESC
    ");
    $stmt_pel->bind_param('ii', $arsip_id, $rapot['santri_id']);
    $stmt_pel->execute();
    $pelanggaran_rekap = $stmt_pel->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_pel->close();

    $stmt_rwd = $conn->prepare("
        SELECT jenis_reward_nama as nama_reward, COUNT(*) AS jumlah, SUM(poin_reward) as total_poin
        FROM arsip_data_reward
        WHERE arsip_id = ? AND santri_id = ?
        GROUP BY jenis_reward_nama
        ORDER BY jumlah DESC
    ");
    $stmt_rwd->bind_param('ii', $arsip_id, $rapot['santri_id']);
    $stmt_rwd->execute();
    $reward_rekap = $stmt_rwd->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_rwd->close();

} catch (Exception $e) {
    die('Error database: ' . $e->getMessage());
}

$santri = [
    'nama'  => $rapot['nama_santri']  ?? 'Santri Dihapus',
    'kamar' => $rapot['kamar'] ?? 'N/A',
    'kelas' => $rapot['kelas_santri'] ?? 'N/A',
];
$periode         = $rapot['periode'];
$narasi_global   = $rapot['narasi_ai']       ?? ''; 
$nama_musyrif    = $rapot['nama_musyrif']    ?? 'Musyrif';

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
    <title>View Rapot Arsip - ' . htmlspecialchars($santri['nama']) . '</title>
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
include '../../rapot/config/template_rapot_tahunan.php';
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
