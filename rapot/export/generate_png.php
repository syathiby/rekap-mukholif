<?php
// File: rekap-mukholif/rapot/generate_png.php

require_once __DIR__ . '/../../bootstrap/init.php'; 
require_once __DIR__ . '/../config/helper.php';

guard('rapot_cetak');
session_write_close();

$mode = $_GET['mode'] ?? 'page';

if (empty($_GET['id'])) {
    http_response_code(400);
    die('Error: ID Rapot tidak ditemukan.');
}
$rapot_id = (int)$_GET['id'];

try {
    $sql = "
        SELECT 
            r.*, 
            s.id AS santri_id, s.nis, s.nama AS nama_santri, s.kamar AS kamar_santri, s.kelas AS kelas_santri,
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
        http_response_code(404);
        die('Error: Data rapot tidak ditemukan.');
    }

    $kamar_filter_musyrif = checkMusyrifKamarAccess();
    if ($kamar_filter_musyrif !== null && (int)$rapot['kamar_santri'] !== $kamar_filter_musyrif) {
        http_response_code(403);
        die('Error: Anda tidak memiliki akses untuk mencetak rapot santri ini (Beda Kamar).');
    }

    // Hitung rentang tanggal dari bulan & tahun rapot
    // Konsisten dengan query di process.php (tidak pakai FIND_IN_SET)
    $bulan_list_indo_png = [
        'Januari' => 1, 'Februari' => 2, 'Maret'    => 3, 'April'    => 4,
        'Mei'     => 5, 'Juni'     => 6, 'Juli'     => 7, 'Agustus'  => 8,
        'September' => 9, 'Oktober' => 10, 'November' => 11, 'Desember' => 12
    ];
    $bulan_num_png  = $bulan_list_indo_png[$rapot['bulan']] ?? 1;
    $start_date_png = sprintf('%04d-%02d-01', $rapot['tahun'], $bulan_num_png);
    $end_date_png   = date('Y-m-t', strtotime($start_date_png));

    // Ambil rincian pelanggaran
    $pelanggaran_list = [];
    $sql_pelanggaran = "
        SELECT jp.nama_pelanggaran, SUM(jp.poin) as poin, COUNT(*) as jumlah
        FROM pelanggaran p
        JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id
        WHERE p.santri_id = ?
          AND p.tanggal >= ? AND p.tanggal <= ?
          AND jp.poin > 0
        GROUP BY jp.nama_pelanggaran
        ORDER BY MAX(p.tanggal) DESC
    ";
    $stmt_pelanggaran = $conn->prepare($sql_pelanggaran);
    $stmt_pelanggaran->bind_param("iss", $rapot['santri_id'], $start_date_png, $end_date_png);
    $stmt_pelanggaran->execute();
    $pelanggaran_list = $stmt_pelanggaran->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_pelanggaran->close();

    // Ambil rincian REWARD
    $reward_list = [];
    $sql_reward = "
        SELECT jr.nama_reward, SUM(jr.poin_reward) AS poin, COUNT(*) as jumlah
        FROM daftar_reward rwd
        JOIN jenis_reward jr ON rwd.jenis_reward_id = jr.id
        WHERE rwd.santri_id = ?
          AND rwd.tanggal >= ? AND rwd.tanggal <= ?
          AND jr.poin_reward > 0
        GROUP BY jr.nama_reward
        ORDER BY MAX(rwd.tanggal) DESC
    ";
    $stmt_reward = $conn->prepare($sql_reward);
    $stmt_reward->bind_param("iss", $rapot['santri_id'], $start_date_png, $end_date_png);
    $stmt_reward->execute();
    $reward_list = $stmt_reward->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_reward->close();

} catch (Exception $e) {
    http_response_code(500);
    die('Error querying database: ' . $e->getMessage());
}

$santri = [
    'nis' => $rapot['nis'] ?? '-',
    'nama' => $rapot['nama_santri'] ?? 'Santri Dihapus',
    'kamar' => $rapot['kamar_santri'] ?? 'N/A',
    'kelas' => $rapot['kelas_santri'] ?? 'N/A'
];
$musyrif = [
    'nama_lengkap' => $rapot['nama_musyrif'] ?? 'User Dihapus'
];

$logo_path = $base_url . '/assets/img/Kop Syathiby.jpg';
$logo_file_path = __DIR__ . '/../assets/img/Kop Syathiby.jpg';
if (!file_exists($logo_file_path)) $logo_path = ''; 

$nama_santri_clean = preg_replace("/[^a-zA-Z0-9 ]/", "", $santri['nama']);
$nama_file = "Rapot {$nama_santri_clean} - {$rapot['bulan']} {$rapot['tahun']}.png";

ob_start(); 
include __DIR__ . '/../config/template_rapot_bulanan.php'; 
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
        html.in-iframe body { background-color: transparent !important; padding: 0 !important; }
        html.in-iframe .loader-card { box-shadow: none !important; margin: auto !important; height: 100vh; display: flex; flex-direction: column; justify-content: center; }
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
    <script>
        if (window.self !== window.top) {
            document.documentElement.classList.add('in-iframe');
        }
    </script>
</head>
<body>
    <div class="loader-card" id="loader-card">
        <div class="icon-wrapper" id="loader-icon"><i class="fas fa-spinner fa-spin"></i></div>
        <div class="loading-text" id="loading-text">Sedang menyiapkan PNG...</div>
        <div class="sub-text" id="sub-text">Mohon tunggu sebentar, file Anda sedang diproses.</div>
    </div>

    <!-- Konten Render Tersembunyi -->
    <div class="hidden-content-wrapper">
        <div class="page-wrapper" id="capture-area">
            <?php echo $html; ?>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            var loaderCard = document.getElementById('loader-card');
            var loaderIcon = document.getElementById('loader-icon');
            var loadingMessage = document.getElementById('loading-text');
            var subMessage = document.getElementById('sub-text');
            var targetElement = document.getElementById('capture-area');

            // Optimasi opsi untuk merender HTML menjadi Canvas seukuran Kertas A4
            var options = {
                scale: 2, 
                useCORS: true, 
                logging: false,
                width: 793, 
                height: 1122, 
                windowWidth: 793,
                windowHeight: 1122
            };

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
                subMessage.innerText = "Jendela ini akan tertutup otomatis...";
                
                if (window.self !== window.top) {
                    setTimeout(function() { window.parent.postMessage('downloadComplete', '*'); }, 2000);
                } else {
                    setTimeout(function() { window.close(); }, 2000);
                }
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