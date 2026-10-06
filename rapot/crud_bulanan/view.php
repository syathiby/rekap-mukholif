<?php
// File: rekap-mukholif/rapot/view.php

require_once __DIR__ . '/../../bootstrap/init.php'; 
require_once __DIR__ . '/../config/helper.php'; 

guard('rapot_view');

if (empty($_GET['id'])) {
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
        die('Error: Data rapot tidak ditemukan.');
    }
    
    // Hitung rentang tanggal dari bulan & tahun yang tersimpan di rapot
    // Konsisten dengan query di process.php (tidak pakai FIND_IN_SET)
    $bulan_list_indo = [
        'Januari' => 1, 'Februari' => 2, 'Maret'    => 3, 'April'    => 4,
        'Mei'     => 5, 'Juni'     => 6, 'Juli'     => 7, 'Agustus'  => 8,
        'September' => 9, 'Oktober' => 10, 'November' => 11, 'Desember' => 12
    ];
    $bulan_num_view  = $bulan_list_indo[$rapot['bulan']] ?? 1;
    $start_date_view = sprintf('%04d-%02d-01', $rapot['tahun'], $bulan_num_view);
    $end_date_view   = date('Y-m-t', strtotime($start_date_view));

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
    $stmt_pelanggaran->bind_param("iss", $rapot['santri_id'], $start_date_view, $end_date_view);
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
    $stmt_reward->bind_param("iss", $rapot['santri_id'], $start_date_view, $end_date_view);
    $stmt_reward->execute();
    $reward_list = $stmt_reward->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_reward->close();

} catch (Exception $e) {
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
$logo_file_path = __DIR__ . '/../../assets/img/Kop Syathiby.jpg';
if (!file_exists($logo_file_path)) $logo_path = ''; 

echo '<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title>View Rapot - ' . htmlspecialchars($santri['nama']) . '</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        html, body {
            touch-action: pan-x pan-y pinch-zoom !important;
            -webkit-text-size-adjust: 100%;
            text-size-adjust: 100%;
            background-color: #525659;
            margin: 0;
            padding: 0;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        .rapot-zoom-outer {
            width: 100%;
            min-height: 100vh;
            padding: 20px 0 80px 0;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            align-items: center;
            touch-action: pan-x pan-y pinch-zoom !important;
        }
        .rapot-zoom-container {
            transform-origin: top center;
            transition: transform 0.12s ease-out;
            touch-action: pan-x pan-y pinch-zoom !important;
        }
        .page-wrapper {
            width: 210mm;
            min-height: 297mm;
            background-color: white;
            box-shadow: 0 0 15px rgba(0,0,0,0.4);
            margin: 0 auto 20px auto;
            padding: 7mm 10mm 4mm 10mm;
            box-sizing: border-box;
            touch-action: pan-x pan-y pinch-zoom !important;
        }
        .rapot-zoom-toolbar {
            position: fixed;
            bottom: 16px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 99999;
            display: flex;
            align-items: center;
            gap: 6px;
            background: rgba(24, 24, 27, 0.92);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 6px 14px;
            border-radius: 9999px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
            user-select: none;
            -webkit-user-select: none;
        }
        .rapot-zoom-btn {
            background: rgba(255, 255, 255, 0.15);
            color: #f4f4f5;
            border: none;
            border-radius: 9999px;
            padding: 6px 12px;
            font-size: 13px;
            font-weight: 600;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.15s ease;
            touch-action: manipulation;
        }
        .rapot-zoom-btn:hover {
            background: rgba(255, 255, 255, 0.25);
            color: #ffffff;
        }
        .rapot-zoom-btn:active {
            transform: scale(0.94);
        }
        .rapot-zoom-val {
            color: #a1a1aa;
            font-size: 12px;
            font-weight: 600;
            min-width: 46px;
            text-align: center;
            font-family: monospace;
        }
        @media print {
            .rapot-zoom-toolbar { display: none !important; }
            .rapot-zoom-outer { padding: 0 !important; }
            .rapot-zoom-container { transform: none !important; }
            body, .page-wrapper {
                background-color: white !important;
                box-shadow: none !important;
                margin: 0 !important;
                padding: 0 !important;
                width: 210mm !important;
            }
        }
    </style>
</head>
<body class="allow-zoom">
    <!-- Floating Zoom Controls -->
    <div class="rapot-zoom-toolbar d-print-none">
        <button type="button" class="rapot-zoom-btn" onclick="zoomOut()" title="Perkecil"><i class="fas fa-minus"></i></button>
        <span class="rapot-zoom-val" id="zoomValue">100%</span>
        <button type="button" class="rapot-zoom-btn" onclick="zoomIn()" title="Perbesar"><i class="fas fa-plus"></i></button>
        <button type="button" class="rapot-zoom-btn" onclick="zoomFit()" title="Sesuaikan Layar"><i class="fas fa-expand"></i> Fit</button>
        <button type="button" class="rapot-zoom-btn" onclick="window.print()" title="Cetak Rapot"><i class="fas fa-print"></i> Cetak</button>
    </div>

    <div class="rapot-zoom-outer">
        <div id="rapotZoomContainer" class="rapot-zoom-container allow-zoom">
            <div class="page-wrapper">';

include '../config/template_rapot_bulanan.php';

echo '
            </div>
        </div>
    </div>

    <script>
    (function() {
        var currentZoom = 1;
        var container = document.getElementById("rapotZoomContainer");
        var zoomVal = document.getElementById("zoomValue");
        var PAGE_WIDTH_PX = 794; // ~210mm

        function setZoom(val, smooth) {
            currentZoom = Math.max(0.35, Math.min(val, 2.5));
            if (container) {
                container.style.transition = smooth ? "transform 0.15s ease-out" : "none";
                container.style.transform = "scale(" + currentZoom + ")";
            }
            if (zoomVal) {
                zoomVal.textContent = Math.round(currentZoom * 100) + "%";
            }
        }

        window.zoomIn = function() { setZoom(currentZoom + 0.15, true); };
        window.zoomOut = function() { setZoom(currentZoom - 0.15, true); };
        window.zoomFit = function() {
            var availableWidth = window.innerWidth - 30;
            var fitScale = Math.min(1, availableWidth / PAGE_WIDTH_PX);
            setZoom(fitScale, true);
        };
        window.zoomReset = function() { setZoom(1, true); };

        // Auto-fit pada mobile saat buka pertama kali
        window.addEventListener("DOMContentLoaded", function() {
            if (window.innerWidth < 768) {
                window.zoomFit();
            } else {
                setZoom(1, false);
            }
        });

        window.addEventListener("resize", function() {
            if (currentZoom < 1 && window.innerWidth < 768) {
                window.zoomFit();
            }
        });

        // Touch gesture pinch inside iframe
        var initialDist = 0;
        var initialZoom = 1;
        document.addEventListener("touchstart", function(e) {
            if (e.touches.length === 2) {
                var dx = e.touches[0].clientX - e.touches[1].clientX;
                var dy = e.touches[0].clientY - e.touches[1].clientY;
                initialDist = Math.hypot(dx, dy);
                initialZoom = currentZoom;
            }
        }, { passive: true });

        document.addEventListener("touchmove", function(e) {
            if (e.touches.length === 2 && initialDist > 0) {
                var dx = e.touches[0].clientX - e.touches[1].clientX;
                var dy = e.touches[0].clientY - e.touches[1].clientY;
                var dist = Math.hypot(dx, dy);
                var factor = dist / initialDist;
                setZoom(initialZoom * factor, false);
            }
        }, { passive: true });

        document.addEventListener("touchend", function(e) {
            if (e.touches.length < 2) {
                initialDist = 0;
            }
        }, { passive: true });
    })();
    </script>
</body>
</html>';
?>