<?php
// File: rekap-mukholif/rapot/generate_png.php

require_once __DIR__ . '/../init.php'; 
require_once __DIR__ . '/helper.php'; 

guard('rapot_cetak');

$mode = $_GET['mode'] ?? 'page';

if (empty($_GET['id'])) {
    die('Error: ID Rapot tidak ditemukan.');
}
$rapot_id = (int)$_GET['id'];

try {
    $sql = "
        SELECT 
            r.*, 
            s.id AS santri_id, s.nama AS nama_santri, s.kamar AS kamar_santri, s.kelas AS kelas_santri,
            u.nama_lengkap AS nama_musyrif
        FROM rapot_kepengasuhan r
        LEFT JOIN santri s ON r.santri_id = s.id
        LEFT JOIN users u ON r.musyrif_id = u.id
        WHERE r.id = ?
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $rapot_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $rapot = $result->fetch_assoc();
    $stmt->close();

    if (!$rapot) {
        die('Error: Data rapot tidak ditemukan.');
    }

    // Ambil rincian pelanggaran
    $pelanggaran_list = [];
    $sql_pelanggaran = "
        SELECT jp.nama_pelanggaran, jp.poin
        FROM pelanggaran p
        JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id
        WHERE p.santri_id = ? 
          AND MONTH(p.tanggal) = FIND_IN_SET(?, 'Januari,Februari,Maret,April,Mei,Juni,Juli,Agustus,September,Oktober,November,Desember')
          AND YEAR(p.tanggal) = ?
          AND jp.poin > 0
        ORDER BY p.tanggal DESC
    ";
    $stmt_pelanggaran = $conn->prepare($sql_pelanggaran);
    $stmt_pelanggaran->bind_param("isi", $rapot['santri_id'], $rapot['bulan'], $rapot['tahun']);
    $stmt_pelanggaran->execute();
    $pelanggaran_list = $stmt_pelanggaran->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_pelanggaran->close();

    // === TAMBAHAN: Ambil rincian REWARD ===
    $reward_list = [];
    $sql_reward = "
        SELECT jr.nama_reward, jr.poin_reward AS poin
        FROM daftar_reward rwd
        JOIN jenis_reward jr ON rwd.jenis_reward_id = jr.id
        WHERE rwd.santri_id = ? 
          AND MONTH(rwd.tanggal) = FIND_IN_SET(?, 'Januari,Februari,Maret,April,Mei,Juni,Juli,Agustus,September,Oktober,November,Desember')
          AND YEAR(rwd.tanggal) = ?
          AND jr.poin_reward > 0  -- DIPERBAIKI: gunakan poin_reward, bukan poin
        ORDER BY rwd.tanggal DESC
    ";
    $stmt_reward = $conn->prepare($sql_reward);
    $stmt_reward->bind_param("isi", $rapot['santri_id'], $rapot['bulan'], $rapot['tahun']);
    $stmt_reward->execute();
    $reward_list = $stmt_reward->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_reward->close();

} catch (Exception $e) {
    die('Error querying database: ' . $e->getMessage());
}

$santri = [
    'nama' => $rapot['nama_santri'] ?? 'Santri Dihapus',
    'kamar' => $rapot['kamar_santri'] ?? 'N/A',
    'kelas' => $rapot['kelas_santri'] ?? 'N/A'
];
$musyrif = [
    'nama_lengkap' => $rapot['nama_musyrif'] ?? 'User Dihapus'
];

$logo_path = $base_url . '/assets/Kop Syathiby.jpg';
$logo_file_path = __DIR__ . '/../assets/Kop Syathiby.jpg';
if (!file_exists($logo_file_path)) $logo_path = ''; 

$nama_santri_clean = preg_replace("/[^a-zA-Z0-9 ]/", "", $santri['nama']);
$nama_file = "Rapot {$nama_santri_clean} - {$rapot['bulan']} {$rapot['tahun']}.png";

ob_start(); 
include 'template_rapot.php'; 
$html = ob_get_contents();
ob_end_clean(); 

if ($mode === 'html') {
    echo $html;
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Memproses Rapot PNG...</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css    ">
    <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js    "></script>
    <style>
        body, html {
            margin: 0; padding: 0; width: 100%; min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            box-sizing: border-box;
        }
        body { background-color: #f4f7f6; display: flex; flex-direction: column; justify-content: center; align-items: center; padding: 20px; }
        .loader-card {
            background-color: #ffffff; border-radius: 12px; box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
            padding: 32px 40px; text-align: center; width: 100%; max-width: 400px; box-sizing: border-box;
        }
        .loader-card .icon-wrapper { font-size: 48px; color: #007bff; margin-bottom: 24px; }
        .loader-card .loading-text { font-size: 1.1rem; font-weight: 600; color: #333; margin-bottom: 8px; }
        .loader-card .sub-text { font-size: 0.9rem; color: #777; }
        .loader-card.success .icon-wrapper { color: #28a745; }
        .loader-card.error .icon-wrapper { color: #dc3545; }
        .hidden-content-wrapper { position: absolute; left: -9999px; top: -9999px; opacity: 0; }
        .page-wrapper { width: 210mm; min-height: 297mm; background-color: white; box-shadow: none; margin: 0; padding: 7mm 10mm 4mm 10mm; box-sizing: border-box; }
        @media (max-width: 480px) {
            body { padding: 15px; }
            .loader-card { padding: 24px 20px; }
            .loader-card .icon-wrapper { font-size: 40px; margin-bottom: 20px; }
            .loader-card .loading-text { font-size: 1rem; }
            .loader-card .sub-text { font-size: 0.85rem; }
        }
    </style>
</head>
<body>
    <div class="loader-card" id="loader-card">
        <div class="icon-wrapper" id="loader-icon"><i class="fas fa-spinner fa-spin"></i></div>
        <div class="loading-text" id="loading-message">Sedang menyiapkan PNG...</div>
        <div class="sub-text" id="sub-message">Mohon tunggu sebentar, jangan tutup tab ini.</div>
    </div>
    <div class="hidden-content-wrapper">
        <div class="page-wrapper" id="target-rapot"><?php echo $html; ?></div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var targetElement = document.getElementById('target-rapot');
            var loaderCard = document.getElementById('loader-card');
            var loadingMessage = document.getElementById('loading-message');
            var subMessage = document.getElementById('sub-message');
            var loaderIcon = document.getElementById('loader-icon');
            
            var options = { useCORS: true, scale: 1.5 };

            html2canvas(targetElement, options).then(function(canvas) {
                var dataURL = canvas.toDataURL('image/png', 0.9);
                var link = document.createElement('a');
                link.href = dataURL;
                link.download = '<?php echo addslashes($nama_file); ?>';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);

                loaderCard.classList.add('success');
                loaderIcon.innerHTML = '<i class="fas fa-check-circle"></i>';
                loadingMessage.innerText = "Download Berhasil!";
                subMessage.innerText = "Anda akan diarahkan kembali...";
                setTimeout(function() { window.location.href = 'index.php'; }, 2000);
            }).catch(function(error) {
                loaderCard.classList.add('error');
                loaderIcon.innerHTML = '<i class="fas fa-times-circle"></i>';
                loadingMessage.style.color = '#dc3545';
                loadingMessage.innerText = 'Oops, Gagal Membuat PNG';
                subMessage.innerText = 'Error: ' + error.message; 
                console.error('Oops, ada error:', error);
            });
        });
    </script>
</body>
</html>