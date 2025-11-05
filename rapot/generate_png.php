<?php
// File: rekap-mukholif/rapot/generate_png.php

// 1. Panggil semua file penting
require_once __DIR__ . '/../init.php'; 
require_once __DIR__ . '/helper.php'; 

// 2. Cek Izin "SATPAM"
guard('rapot_cetak');

// ==========================================================
//           PERUBAHAN DI SINI: Mode Output
// ==========================================================
$mode = $_GET['mode'] ?? 'page'; // 'page' (default) or 'html'
// ==========================================================


// 3. Ambil ID Rapot dari URL
if (empty($_GET['id'])) {
    die('Error: ID Rapot tidak ditemukan.');
}
$rapot_id = (int)$_GET['id'];

// 4. Ambil Data Rapot (Query 1)
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

    // Ambil rincian pelanggaran (Query 2)
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

} catch (Exception $e) {
    die('Error querying database: ' . $e->getMessage());
}

// 5. Siapin data buat dikirim ke template
$santri = [
    'nama' => $rapot['nama_santri'] ?? 'Santri Dihapus',
    'kamar' => $rapot['kamar_santri'] ?? 'N/A',
    'kelas' => $rapot['kelas_santri'] ?? 'N/A'
];
$musyrif = [
    'nama_lengkap' => $rapot['nama_musyrif'] ?? 'User Dihapus'
];

// 6. Setting Path Logo (PENTING: Pake $base_url)
$logo_path = $base_url . '/assets/Kop Syathiby.jpg';
$logo_file_path = __DIR__ . '/../assets/Kop Syathiby.jpg';
if (!file_exists($logo_file_path)) $logo_path = ''; 


// 7. Siapin Nama File
$nama_santri_clean = preg_replace("/[^a-zA-Z0-9 ]/", "", $santri['nama']);
$nama_file = "Rapot {$nama_santri_clean} - {$rapot['bulan']} {$rapot['tahun']}.png";

// 8. Tangkap HTML rapotnya
ob_start(); 
include 'template_rapot.php'; 
$html = ob_get_contents();
ob_end_clean(); 

// ==========================================================
//           PERUBAHAN DI SINI (LOGIKA OUTPUT)
// ==========================================================
if ($mode === 'html') {
    // Mode 'html': Cuma echo HTML mentahnya buat ditangkep bulk processor
    echo $html;
    exit;
}
// ==========================================================

// Mode 'page' (default): Tampilkan halaman loading html2canvas

?>
<!DOCTYPE html>
<html>
<head>
    <title>Memproses Rapot PNG...</title>
    <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f0f0f0; 
               display: flex; flex-direction: column; justify-content: center; 
               align-items: center; min-height: 100vh; }
        .page-wrapper {
            width: 210mm; min-height: 297mm; background-color: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.5); margin: 20px auto;
            padding: 7mm 10mm 4mm 10mm; /* Margin bawah sempit */
            box-sizing: border-box;
        }
        .loading-text { font-size: 1.2rem; font-weight: bold; color: #333; margin-top: 20px; }
    </style>
</head>
<body>

    <div class="loading-text" id="loading-message">
        <i class="fas fa-spinner fa-spin"></i> 
        Sedang menyiapkan PNG... Mohon tunggu...
    </div>

    <div style="opacity: 0; height: 0; overflow: hidden;">
        <div class="page-wrapper" id="target-rapot">
            <?php echo $html; ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var targetElement = document.getElementById('target-rapot');
            var loadingMessage = document.getElementById('loading-message');
            var options = {
                useCORS: true, width: targetElement.scrollWidth, 
                height: targetElement.scrollHeight, windowWidth: 1000 
            };
            html2canvas(targetElement, options).then(function(canvas) {
                var dataURL = canvas.toDataURL('image/png', 0.9);
                var link = document.createElement('a');
                link.href = dataURL;
                link.download = '<?php echo addslashes($nama_file); ?>';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                loadingMessage.innerText = "Download berhasil! Tab ini akan ditutup...";
                setTimeout(function() { window.close(); }, 2000);
            }).catch(function(error) {
                loadingMessage.style.color = 'red';
                loadingMessage.innerText = 'Error: ' + error;
                console.error('Oops, ada error:', error);
            });
        });
    </script>
</body>
</html>