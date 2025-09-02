<?php 
require_once __DIR__ . '/../header.php';
guard(); 
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Menu Rekap Pelanggaran</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4361ee; --secondary: #3f37c9; --accent: #4895ef;
            --light: #f8f9fa; --dark: #212529; --success: #198754;
            --danger: #dc3545; --warning: #ffc107;
            --info: #0dcaf0; 
            --purple: #6f42c1;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8f0 100%);
            min-height: 100vh;
        }
        
        .container {
            padding: 40px;
            max-width: 700px;
            animation: fadeIn 0.8s ease-out;
        }
        
        .header-box {
            background: white; padding: 25px; border-radius: 15px;
            box-shadow: 0 10px 20px rgba(0,0,0,0.08); margin-bottom: 30px;
            border-left: 5px solid var(--primary);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .header-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.12);
        }
        
        h2 {
            font-weight: 600; color: var(--primary); margin: 0;
            font-size: 28px;
        }
        
        .btn-menu {
            padding: 18px 25px; border-radius: 12px; font-weight: 500;
            font-size: 18px; text-align: left; border: none;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transition: all 0.3s ease; position: relative;
            overflow: hidden; display: flex; align-items: center; gap: 15px;
        }
        
        .btn-menu:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.15);
        } 
        
        .btn-menu i {
            font-size: 24px; width: 40px; height: 40px;
            display: flex; align-items: center; justify-content: center;
            background: rgba(255,255,255,0.2); border-radius: 50%;
        }
        
        .d-grid { gap: 20px; }
        
        .btn-primary { background-color: var(--primary); }
        .btn-warning { background-color: var(--warning); color: var(--dark); }
        .btn-danger { background-color: var(--danger); }
        .btn-success { background-color: var(--success); }
        .btn-info { background-color: var(--info); color: white; }
        .btn-purple { background-color: var(--purple); }

        /* ✅ FIX: Paksa warna background tetap sama saat di-hover */
        .btn-primary:hover { background-color: var(--primary); }
        .btn-warning:hover { background-color: var(--warning); color: var(--dark); }
        .btn-danger:hover { background-color: var(--danger); }
        .btn-success:hover { background-color: var(--success); }
        .btn-info:hover { background-color: var(--info); color: white; }
        .btn-purple:hover { background-color: var(--purple); }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes buttonEntrance {
            from { opacity: 0; transform: scale(0.9); }
            to { opacity: 1; transform: scale(1); }
        }
        
        .btn-menu {
            animation: buttonEntrance 0.5s ease-out;
            animation-fill-mode: both;
        }
        
        .btn-menu:nth-child(1) { animation-delay: 0.1s; }
        .btn-menu:nth-child(2) { animation-delay: 0.2s; }
        .btn-menu:nth-child(3) { animation-delay: 0.3s; }
        .btn-menu:nth-child(4) { animation-delay: 0.4s; }
        .btn-menu:nth-child(5) { animation-delay: 0.5s; }
        .btn-menu:nth-child(6) { animation-delay: 0.6s; }
        
    </style>
</head>
<body>
<div class="container text-center">
    <div class="header-box">
        <h2><i class="fas fa-chart-pie"></i> Menu Rekap Pelanggaran</h2>
    </div>

    <div class="d-grid">
        <a href="umum.php" class="btn btn-purple btn-menu text-white">
            <i class="fas fa-list-alt"></i>
            <span>Rekap Pelanggaran Umum</span>
        </a>
        <a href="santri-pelanggar.php" class="btn btn-primary btn-menu text-white">
            <i class="fas fa-user-graduate"></i>
            <span>Rekap Keterlambatan Santri</span>
        </a>
        <a href="per-kamar.php" class="btn btn-warning btn-menu text-dark">
            <i class="fas fa-home"></i>
            <span>Rekap Mukholif Kamar</span>
        </a>
        <a href="../pelanggaran/bahasa/rekap.php" class="btn btn-danger btn-menu text-white">
            <i class="fas fa-language"></i>
            <span>Rekap Pelanggaran Bahasa</span>
        </a>
        <a href="../pelanggaran/diniyyah/rekap.php" class="btn btn-success btn-menu text-white">
            <i class="fas fa-book-quran"></i>
            <span>Rekap Pelanggaran Diniyyah</span>
        </a>
        <a href="chart.php" class="btn btn-info btn-menu text-white">
            <i class="fas fa-chart-line"></i>
            <span>Grafik Statistik</span>
        </a>
    </div>
</div>

<?php require_once __DIR__ . '/../footer.php'; ?>