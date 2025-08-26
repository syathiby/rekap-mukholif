<?php
include '../db.php';
include '../header.php';
checkRole(['admin']);

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan Sistem</title>
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3f37c9;
            --success: #4cc9f0;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
            --card-shadow: 0 4px 12px rgba(0,0,0,0.08);
            --transition: all 0.3s ease;
        }
        
        .settings-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 15px;
        }
        
        .settings-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }
        
        .settings-header h2 {
            color: var(--dark);
            font-size: 2.2rem;
            margin-bottom: 0.5rem;
            font-weight: 700;
        }
        
        .settings-header p {
            color: var(--gray);
            font-size: 1.1rem;
        }
        
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        
        .settings-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            transition: var(--transition);
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .settings-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        }
        
        .card-header {
            padding: 1.5rem 1.5rem 0.5rem;
            display: flex;
            align-items: center;
        }
        
        .card-icon {
            font-size: 1.8rem;
            margin-right: 12px;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(67, 97, 238, 0.1);
            color: var(--primary);
        }
        
        .card-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--dark);
            margin: 0;
        }
        
        .card-body {
            padding: 0 1.5rem 1rem;
            flex-grow: 1;
        }
        
        .card-description {
            color: var(--gray);
            line-height: 1.5;
            margin-bottom: 1.5rem;
        }
        
        .card-footer {
            padding: 0 1.5rem 1.5rem;
        }
        
        .settings-link {
            display: inline-block;
            padding: 10px 20px;
            background: var(--primary);
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 500;
            transition: var(--transition);
        }
        
        .settings-link:hover {
            background: var(--secondary);
            transform: translateY(-2px);
        }
        
        @media (max-width: 768px) {
            .settings-grid {
                grid-template-columns: 1fr;
            }
            
            .settings-header h2 {
                font-size: 1.8rem;
            }
        }
    </style>
</head>
<body>
    <div class="settings-container">
        <div class="settings-header">
            <h2>⚙️ Pengaturan Sistem</h2>
            <p>Kelola preferensi dan konfigurasi sistem sesuai kebutuhan Anda</p>
        </div>
        
        <div class="settings-grid">
            <div class="settings-card">
                <div class="card-header">
                    <div class="card-icon">📅</div>
                    <h3 class="card-title">Periode Aktif</h3>
                </div>
                <div class="card-body">
                    <p class="card-description">Atur periode aktif untuk sistem akademik, termasuk tanggal mulai dan akhir semester.</p>
                </div>
                <div class="card-footer">
                    <a href="periode-aktif/index.php" class="settings-link">Kelola Periode</a>
                </div>
            </div>
            
            <div class="settings-card">
                <div class="card-header">
                    <div class="card-icon">🔑</div>
                    <h3 class="card-title">Keamanan Akun</h3>
                </div>
                <div class="card-body">
                    <p class="card-description">Ubah kata sandi akun Anda untuk menjaga keamanan dan privasi data sistem.</p>
                </div>
                <div class="card-footer">
                    <a href="ganti-password/index.php" class="settings-link">Ganti Password</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

<?php include '../footer.php'; ?>