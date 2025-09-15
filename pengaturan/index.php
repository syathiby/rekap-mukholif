<?php 
require_once __DIR__ . '/../header.php';
guard(); 
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan Sistem</title>
    <!-- Font Awesome tetap kita pakai untuk ikon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /*
        =================================================================
        ✅ CSS UPDATE: PALET WARNA BARU UNTUK SETIAP KARTU ✅
        =================================================================
        */
        :root {
            /* Palet Warna Utama */
            --text-dark: #1e293b;
            --text-light: #64748b;
            --bg-light: #f8fafc;
            --border-color: #e2e8f0;
            --card-bg: #ffffff;
            
            /* ✅ Palet Warna Baru untuk Kartu Pengaturan */
            --color-izin: #fd7e14;     /* Oranye */
            --color-user: #198754;     /* Hijau */
            --color-periode: #6f42c1;   /* Ungu */
            --color-keamanan: #dc3545;  /* Merah */
            --color-reset: #0d6efd;     /* ✅ Biru untuk Reset Poin */
        }
        
        /* Kontainer utama halaman pengaturan */
        .settings-container {
            max-width: 960px;
            margin: 0 auto;
            padding: 1rem;
        }
        
        /* Header halaman (Judul & Deskripsi) */
        .page-header {
            text-align: left;
            margin-bottom: 2.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .page-header h1 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .page-header p {
            font-size: 1rem;
            color: var(--text-light);
            margin-top: 0.5rem;
            max-width: 600px;
        }
        
        /* Grid untuk kartu-kartu pengaturan */
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
        }
        
        /* Desain kartu yang baru */
        .settings-card {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 0.75rem;
            padding: 1.75rem;
            transition: all 0.2s ease-in-out;
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        
        .card-content {
            flex-grow: 1;
        }
        
        .card-icon {
            font-size: 2rem;
            margin-bottom: 1.25rem;
        }
        
        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-dark);
            margin: 0 0 0.5rem 0;
        }
        
        .card-description {
            color: var(--text-light);
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }
        
        .card-action {
            margin-top: auto;
        }
        
        .settings-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            font-weight: 600;
            transition: gap 0.2s ease-in-out;
        }
        
        .settings-link:hover {
            gap: 0.75rem;
        }

        /* ✅ ATURAN WARNA UNTUK SETIAP KARTU */
        /* Kartu Izin (Oranye) */
        .settings-card.color-izin .card-icon,
        .settings-card.color-izin .settings-link {
            color: var(--color-izin);
        }
        .settings-card.color-izin:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 20px rgba(30, 41, 59, 0.05);
            border-color: var(--color-izin);
        }

        /* Kartu User (Hijau) */
        .settings-card.color-user .card-icon,
        .settings-card.color-user .settings-link {
            color: var(--color-user);
        }
        .settings-card.color-user:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 20px rgba(30, 41, 59, 0.05);
            border-color: var(--color-user);
        }

        /* Kartu Periode (Ungu) */
        .settings-card.color-periode .card-icon,
        .settings-card.color-periode .settings-link {
            color: var(--color-periode);
        }
        .settings-card.color-periode:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 20px rgba(30, 41, 59, 0.05);
            border-color: var(--color-periode);
        }

        /* Kartu Keamanan (Merah) */
        .settings-card.color-keamanan .card-icon,
        .settings-card.color-keamanan .settings-link {
            color: var(--color-keamanan);
        }
        .settings-card.color-keamanan:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 20px rgba(30, 41, 59, 0.05);
            border-color: var(--color-keamanan);
        }
        
        /* ✅ KARTU BARU: Kartu Reset Poin (Biru) */
        .settings-card.color-reset .card-icon,
        .settings-card.color-reset .settings-link {
            color: var(--color-reset);
        }
        .settings-card.color-reset:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 20px rgba(30, 41, 59, 0.05);
            border-color: var(--color-reset);
        }
        
        @media (max-width: 768px) {
            .page-header h1 {
                font-size: 1.8rem;
            }
        }
    </style>
</head>
<body>
    <div class="settings-container">
        <div class="page-header">
            <h1><i class="fas fa-cogs"></i>Pengaturan Sistem</h1>
            <p>Kelola semua konfigurasi inti aplikasi dan manajemen pengguna dari satu tempat terpusat.</p>
        </div>
        
        <div class="settings-grid">

            <!-- KARTU: Pengaturan Izin (Tiket) - Warna Oranye -->
            <div class="settings-card color-izin">
                <div class="card-content">
                    <div class="card-icon"><i class="fas fa-ticket-alt"></i></div>
                    <h3 class="card-title">Manajemen Hak Akses</h3>
                    <p class="card-description">Atur "tiket" atau izin akses untuk setiap user secara detail dan fleksibel.</p>
                </div>
                <div class="card-action">
                    <a href="izin/index.php" class="settings-link">
                        Kelola Izin <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>

            <!-- KARTU: Manajemen User - Warna Hijau -->
            <div class="settings-card color-user">
                <div class="card-content">
                    <div class="card-icon"><i class="fas fa-user-plus"></i></div>
                    <h3 class="card-title">Manajemen User</h3>
                    <p class="card-description">Buat, lihat, dan kelola akun untuk penanggung jawab setiap bagian.</p>
                </div>
                <div class="card-action">
                    <a href="users/index.php" class="settings-link">
                        Kelola User <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
            
            <!-- KARTU: Periode Aktif - Warna Ungu -->
            <div class="settings-card color-periode">
                <div class="card-content">
                    <div class="card-icon"><i class="fas fa-calendar-alt"></i></div>
                    <h3 class="card-title">Periode Aktif</h3>
                    <p class="card-description">Atur rentang waktu rekap data pelanggaran yang sedang berjalan di sistem.</p>
                </div>
                <div class="card-action">
                    <a href="periode-aktif/index.php" class="settings-link">
                        Kelola Periode <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>

            <!-- ✅ KARTU BARU: Reset Poin Santri - Warna Biru -->
            <!-- Kartu ini hanya muncul jika user punya izin 'reset_poin_manage' -->
            <?php if (has_permission('reset_poin_manage')): ?>
            <div class="settings-card color-reset">
                <div class="card-content">
                    <div class="card-icon"><i class="fas fa-sync-alt"></i></div>
                    <h3 class="card-title">Reset Poin Santri</h3>
                    <p class="card-description">Kembalikan poin pelanggaran santri ke nol. Gunakan dengan sangat hati-hati.</p>
                </div>
                <div class="card-action">
                    <a href="reset-poin/index.php" class="settings-link">
                        Reset Poin <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>
</body>
</html>

<?php include '../footer.php'; ?>