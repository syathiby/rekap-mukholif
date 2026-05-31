<?php
// Pastikan tidak ada output sebelum header
if (!headers_sent()) {
    header("HTTP/1.1 403 Forbidden");
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
}

// Ambil BASE_URL jika belum ada
if (!defined('BASE_URL')) {
    $project_path = '/rekap-mukholif'; // Sesuaikan jika ada perubahan folder
    define('BASE_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . $project_path);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sesi Kedaluwarsa - AsuhTrack</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8fafc;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
        }
        .csrf-card {
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            padding: 3rem 2rem;
            max-width: 500px;
            width: 100%;
            text-align: center;
            border-top: 5px solid #f59e0b;
            position: relative;
            overflow: hidden;
        }
        .icon-container {
            width: 90px;
            height: 90px;
            background-color: #fef3c7;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem auto;
            color: #d97706;
            font-size: 2.5rem;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.4); }
            70% { box-shadow: 0 0 0 15px rgba(245, 158, 11, 0); }
            100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0); }
        }
        h2 {
            color: #1e293b;
            font-weight: 700;
            margin-bottom: 1rem;
            font-size: 1.5rem;
        }
        p {
            color: #64748b;
            line-height: 1.6;
            margin-bottom: 2rem;
        }
        .btn-reload {
            background-color: #f59e0b;
            color: white;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px -1px rgba(245, 158, 11, 0.4);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }
        .btn-reload:hover {
            background-color: #d97706;
            transform: translateY(-2px);
            box-shadow: 0 6px 8px -1px rgba(245, 158, 11, 0.5);
            color: white;
        }
        .btn-reload i {
            transition: transform 0.5s ease;
        }
        .btn-reload:hover i {
            transform: rotate(180deg);
        }
        .btn-back {
            color: #64748b;
            text-decoration: none;
            font-size: 0.9rem;
            margin-top: 1.5rem;
            display: inline-block;
            transition: color 0.3s;
        }
        .btn-back:hover {
            color: #334155;
            text-decoration: underline;
        }

        /* Responsif untuk layar kecil (Mobile) */
        @media (max-width: 576px) {
            .csrf-card {
                padding: 2rem 1.2rem;
                margin: 0 10px;
            }
            .icon-container {
                width: 70px;
                height: 70px;
                font-size: 2rem;
                margin-bottom: 1rem;
            }
            h2 {
                font-size: 1.3rem;
            }
            p {
                font-size: 0.9rem;
                margin-bottom: 1.5rem;
            }
            .btn-reload {
                padding: 0.6rem 1.5rem;
                font-size: 0.95rem;
            }
        }
    </style>
</head>
<body>
    <div class="csrf-card">
        <div class="icon-container">
            <i class="fas fa-hourglass-end"></i>
        </div>
        <h2>Sesi Formulir Kedaluwarsa</h2>
        <p>
            Mohon maaf, keamanan formulir Anda telah habis karena halaman dibiarkan terbuka terlalu lama tanpa aktivitas. 
            <br><br>
            Untuk perlindungan, <strong>tidak ada data yang diproses</strong>. Silakan kembali untuk mendapatkan token baru.
        </p>
        
        <a href="javascript:void(0)" onclick="safeReload();" class="btn-reload">
            <i class="fas fa-sync-alt"></i> Kembali ke Formulir
        </a>
        
        <br>
        <a href="<?= BASE_URL ?>/index.php" class="btn-back">Atau kembali ke Dashboard Utama</a>
    </div>

    <script>
        function safeReload() {
            var btn = document.querySelector('.btn-reload');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memuat Ulang...';
            btn.style.pointerEvents = 'none';
            btn.style.opacity = '0.8';

            var ref = document.referrer;
            // Pastikan referrer valid dan satu asal (same-origin)
            if (ref && ref.indexOf(window.location.hostname) > -1) {
                // Arahkan browser secara utuh ke halaman form sebelumnya untuk mereset sesi
                window.location.replace(ref); 
            } else {
                // Fallback aman
                window.history.back();
                setTimeout(function(){
                    window.location.reload();
                }, 300);
            }
        }
    </script>
</body>
</html>
