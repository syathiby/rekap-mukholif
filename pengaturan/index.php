<?php
// 1. Panggil 'Otak' aplikasi dulu
require_once __DIR__ . '/../bootstrap/init.php';

// 2. Jalankan 'SATPAM' buat ngejaga halaman
guard(['user_manage', 'reset_poin_manage', 'periode_aktif_manage', 'izin_manage', 'history_manage']); 

// 3. Kalau lolos, baru panggil Tampilan
require_once __DIR__ . '/../layouts/header.php'; 
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
        .settings-card {
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 1rem;
            padding: 1.5rem;
            transition: all 0.3s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: hidden;
        }
        
        .settings-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 100%; height: 4px;
            background: transparent;
            transition: all 0.3s ease;
        }

        .settings-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
        }

        /* Varian Warna Kartu - Warna Permanen (Tidak hanya saat hover) */
        /* Izin (Oranye) */
        .card-izin::before { background: linear-gradient(90deg, #f97316, #fb923c); }
        .card-izin .icon-box { background: rgba(249, 115, 22, 0.1); color: #f97316; }
        
        /* User (Hijau) */
        .card-user::before { background: linear-gradient(90deg, #10b981, #34d399); }
        .card-user .icon-box { background: rgba(16, 185, 129, 0.1); color: #10b981; }
        
        /* Periode (Ungu) */
        .card-periode::before { background: linear-gradient(90deg, #8b5cf6, #a78bfa); }
        .card-periode .icon-box { background: rgba(139, 92, 246, 0.1); color: #8b5cf6; }
        
        /* Reset (Merah) */
        .card-reset::before { background: linear-gradient(90deg, #ef4444, #f87171); }
        .card-reset .icon-box { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
        
        /* Riwayat (Teal/Toska) */
        .card-history::before { background: linear-gradient(90deg, #14b8a6, #2dd4bf); }
        .card-history .icon-box { background: rgba(20, 184, 166, 0.1); color: #14b8a6; }

        .icon-box {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            transition: all 0.3s ease;
            margin-bottom: 1rem;
        }

        .settings-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            font-weight: 600;
            color: #475569;
            margin-top: auto;
            transition: all 0.2s ease;
        }
        
        .settings-link:hover {
            color: #1e293b;
            gap: 0.75rem;
        }
    </style>
</head>
<body>
    <div class="dashboard-wrapper container-fluid px-0 px-md-2 mt-2 mb-5">
        
        <!-- Header Page yang Dilepas dari Card Utama -->
        <div class="d-flex align-items-center mb-4 px-1">
            <div class="d-flex align-items-center justify-content-center rounded-circle me-3 shadow-sm flex-shrink-0" style="width: 56px; height: 56px; background: linear-gradient(135deg, #4f46e5, #3b82f6); color: white;">
                <i class="fas fa-cogs fa-xl"></i>
            </div>
            <div>
                <h3 class="fw-bold mb-1 text-dark" style="letter-spacing: -0.5px;">Pengaturan Sistem</h3>
                <p class="text-muted mb-0">Kelola semua konfigurasi inti aplikasi dan manajemen pengguna dari satu tempat</p>
            </div>
        </div>
        
        <div class="row g-4 px-1">

            <!-- KARTU: Pengaturan Izin (Tiket) -->
            <?php if (has_permission('izin_manage')): ?>
            <div class="col-xl-4 col-md-6">
                <div class="settings-card card-izin shadow-sm">
                    <div class="icon-box"><i class="fas fa-ticket-alt"></i></div>
                    <h5 class="fw-bold text-dark mb-2">Manajemen Hak Akses</h5>
                    <p class="text-muted small mb-4">Atur "tiket" atau izin akses untuk setiap user secara detail dan fleksibel sesuai jabatannya.</p>
                    <a href="izin/index.php" class="settings-link">
                        Kelola Izin <i class="fas fa-arrow-right small"></i>
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <!-- KARTU: Manajemen User -->
            <?php if (has_permission('user_manage')): ?>
            <div class="col-xl-4 col-md-6">
                <div class="settings-card card-user shadow-sm">
                    <div class="icon-box"><i class="fas fa-user-plus"></i></div>
                    <h5 class="fw-bold text-dark mb-2">Manajemen User</h5>
                    <p class="text-muted small mb-4">Buat, lihat, ubah password, dan kelola akun login untuk penanggung jawab setiap bagian.</p>
                    <a href="users/index.php" class="settings-link">
                        Kelola User <i class="fas fa-arrow-right small"></i>
                    </a>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- KARTU: Periode Aktif -->
            <?php if (has_permission('periode_aktif_manage')): ?>
            <div class="col-xl-4 col-md-6">
                <div class="settings-card card-periode shadow-sm">
                    <div class="icon-box"><i class="fas fa-calendar-alt"></i></div>
                    <h5 class="fw-bold text-dark mb-2">Periode Aktif</h5>
                    <p class="text-muted small mb-4">Atur rentang waktu rekap data pelanggaran yang sedang berjalan secara aktif di sistem.</p>
                    <a href="periode-aktif/index.php" class="settings-link">
                        Kelola Periode <i class="fas fa-arrow-right small"></i>
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <!-- KARTU: Riwayat Pelanggaran -->
            <?php if (has_permission('history_manage')): ?>
            <div class="col-xl-4 col-md-6">
                <div class="settings-card card-history shadow-sm">
                    <div class="icon-box"><i class="fas fa-history"></i></div>
                    <h5 class="fw-bold text-dark mb-2">Riwayat Pelanggaran</h5>
                    <p class="text-muted small mb-4">Lihat log dan kelola riwayat pelanggaran santri. Batalkan pelanggaran jika terjadi kesalahan input.</p>
                    <a href="history/index.php" class="settings-link">
                        Lihat Riwayat <i class="fas fa-arrow-right small"></i>
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <!-- KARTU: Reset Poin Santri -->
            <?php if (has_permission('reset_poin_manage')): ?>
            <div class="col-xl-4 col-md-6">
                <div class="settings-card card-reset shadow-sm">
                    <div class="icon-box"><i class="fas fa-sync-alt"></i></div>
                    <h5 class="fw-bold text-dark mb-2">Reset Poin Santri</h5>
                    <p class="text-muted small mb-4">Kembalikan poin pelanggaran santri ke nol, baik secara individu maupun keseluruhan.</p>
                    <a href="reset-poin/index.php" class="settings-link">
                        Reset Poin <i class="fas fa-arrow-right small"></i>
                    </a>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>
</body>
</html>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>