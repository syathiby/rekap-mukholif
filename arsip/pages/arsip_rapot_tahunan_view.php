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
        SELECT nama_reward, COUNT(*) AS jumlah, SUM(poin_reward) as total_poin
        FROM arsip_data_reward
        WHERE arsip_id = ? AND santri_id = ?
        GROUP BY nama_reward
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
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title>View Rapot Arsip - ' . htmlspecialchars($santri['nama']) . '</title>
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

ob_start();
include '../../rapot/config/template_rapot_tahunan.php';
$html = ob_get_clean();

// Ganti <pagebreak /> dengan penutup div lama dan pembuka div baru
// agar terlihat seperti 2 kertas yang terpisah di browser
$html = str_replace('<pagebreak />', '</div><div class="page-wrapper" style="margin-top: 20px;">', $html);

echo $html;

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
