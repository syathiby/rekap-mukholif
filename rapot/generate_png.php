<?php
// File: rekap-mukholif/rapot/generate_png.php

// 1. Panggil semua file penting
require_once __DIR__ . '/../init.php'; 
require_once __DIR__ . '/helper.php'; 

// 2. Cek Izin "SATPAM"
guard('rapot_cetak');

// ==========================================================
//           Mode Output
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
//           LOGIKA OUTPUT
// ==========================================================
if ($mode === 'html') {
    // Mode 'html': Cuma echo HTML mentahnya buat ditangkep bulk processor
    echo $html;
    exit;
}
// ==========================================================

// Mode 'page' (default): Tampilkan halaman loading html2canvas
// ==========================================================
//           MULAI PERBAIKAN TAMPILAN DI SINI
// ==========================================================
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Memproses Rapot PNG...</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
    
    <style>
        /* CSS Reset & Font */
        body, html {
            margin: 0;
            padding: 0;
            width: 100%;
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, 
                "Helvetica Neue", Arial, sans-serif;
            box-sizing: border-box;
        }

        /* Latar belakang & Layout */
        body { 
            background-color: #f4f7f6;
            display: flex; 
            flex-direction: column; 
            justify-content: center; 
            align-items: center; 
            padding: 20px; /* Kasih jarak dari pinggir layar HP */
        }

        /* Box Loading (Responsif) */
        .loader-card {
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
            padding: 32px 40px; /* Padding default buat desktop */
            text-align: center;
            width: 100%;
            max-width: 400px; 
            transition: all 0.3s ease;
            
            /* ================================== */
            /* PERBAIKAN RESPONSIVE      */
            /* ================================== */
            box-sizing: border-box; /* PENTING: Biar padding gak ngerusak width 100% */
        }

        /* Ikon Spinner */
        .loader-card .icon-wrapper {
            font-size: 48px;
            color: #007bff;
            margin-bottom: 24px;
        }

        /* Teks Loading */
        .loader-card .loading-text { 
            font-size: 1.1rem; 
            font-weight: 600; 
            color: #333; 
            margin-bottom: 8px;
        }
        
        /* Teks Sub-info */
        .loader-card .sub-text {
            font-size: 0.9rem;
            color: #777;
        }

        /* State Selesai (Ikon Ceklis) */
        .loader-card.success .icon-wrapper {
            color: #28a745;
        }
        
        /* State Error (Ikon X) */
        .loader-card.error .icon-wrapper {
            color: #dc3545;
        }

        /* Ini buat nyembunyiin Rapot aslinya */
        .hidden-content-wrapper {
            position: absolute;
            left: -9999px;
            top: -9999px;
            opacity: 0;
        }
        
        /* Style A4 asli */
        .page-wrapper {
            width: 210mm; 
            min-height: 297mm; 
            background-color: white;
            box-shadow: none; 
            margin: 0; 
            padding: 7mm 10mm 4mm 10mm; 
            box-sizing: border-box;
        }

        /* ================================== */
        /* PERBAIKAN RESPONSIVE (HP)    */
        /* ================================== */
        @media (max-width: 480px) {
            body {
                padding: 15px; /* Kurangi padding body di HP */
            }
            
            .loader-card {
                padding: 24px 20px; /* Kurangi padding card di HP */
            }

            .loader-card .icon-wrapper {
                font-size: 40px; /* Kecilin ikon */
                margin-bottom: 20px;
            }

            .loader-card .loading-text {
                font-size: 1rem; /* Kecilin font */
            }

            .loader-card .sub-text {
                font-size: 0.85rem;
            }
        }
    </style>
</head>
<body>

    <div class="loader-card" id="loader-card">
        <div class="icon-wrapper" id="loader-icon">
            <i class="fas fa-spinner fa-spin"></i>
        </div>
        <div class="loading-text" id="loading-message">
            Sedang menyiapkan PNG...
        </div>
        <div class="sub-text" id="sub-message">
            Mohon tunggu sebentar, jangan tutup tab ini.
        </div>
    </div>

    <div class="hidden-content-wrapper">
        <div class="page-wrapper" id="target-rapot">
            <?php echo $html; ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Ambil elemen-elemen
            var targetElement = document.getElementById('target-rapot');
            var loaderCard = document.getElementById('loader-card');
            var loadingMessage = document.getElementById('loading-message');
            var subMessage = document.getElementById('sub-message');
            var loaderIcon = document.getElementById('loader-icon');
            
            // Opsi html2canvas
            var options = {
                useCORS: true, 
                scale: 1.5 
            };

            // Mulai proses screenshot
            html2canvas(targetElement, options).then(function(canvas) {
                // 1. Ubah jadi gambar
                var dataURL = canvas.toDataURL('image/png', 0.9);
                
                // 2. Bikin link download palsu
                var link = document.createElement('a');
                link.href = dataURL;
                link.download = '<?php echo addslashes($nama_file); ?>';
                
                // 3. Klik link-nya 
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);

                // 4. Kasih feedback SUKSES
                loaderCard.classList.add('success');
                loaderIcon.innerHTML = '<i class="fas fa-check-circle"></i>';
                loadingMessage.innerText = "Download Berhasil!";
                subMessage.innerText = "Anda akan diarahkan kembali...";

                // 5. Balik ke Index
                setTimeout(function() { 
                    window.location.href = 'index.php'; 
                }, 2000); 

            }).catch(function(error) {
                // 6. Kasih feedback ERROR
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