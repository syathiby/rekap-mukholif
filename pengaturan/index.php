<?php
// 1. Panggil 'Otak' aplikasi dulu
require_once __DIR__ . '/../bootstrap/init.php';

// 2. Jalankan 'SATPAM' buat ngejaga halaman
guard(['user_manage', 'reset_poin_manage', 'periode_aktif_manage', 'izin_manage', 'history_manage', 'backup_restore_manage']); 

// 3. Kalau lolos, baru panggil Tampilan
require_once __DIR__ . '/../layouts/header.php'; 
?>

<style>
    :root {
        --card-border: #e2e8f0;
        --card-bg: #ffffff;
        --text-dark: #0f172a;
        --text-muted: #64748b;
        --transition-speed: 0.25s;
    }

    .settings-grid {
        margin-top: 1.5rem;
    }

    .settings-card {
        background-color: var(--card-bg);
        border: 1px solid var(--card-border);
        border-radius: 1.25rem;
        padding: 1.75rem;
        transition: all var(--transition-speed) cubic-bezier(0.4, 0, 0.2, 1);
        height: 100%;
        display: flex;
        flex-direction: column;
        position: relative;
        overflow: hidden;
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
    }
    
    .settings-card::before {
        content: '';
        position: absolute;
        top: 0; left: 0; width: 100%; height: 5px;
        background: transparent;
        transition: all var(--transition-speed) ease;
    }

    .settings-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 12px 20px -8px rgba(0, 0, 0, 0.08), 0 4px 12px -2px rgba(0, 0, 0, 0.03);
        border-color: #cbd5e1;
    }

    /* Modern Minimalist Card Accent Bars */
    .card-izin::before { background: linear-gradient(90deg, #f97316, #fdba74); }
    .card-izin:hover { border-color: #f97316; }
    .card-izin .icon-box { background: rgba(249, 115, 22, 0.08); color: #ea580c; }
    
    .card-user::before { background: linear-gradient(90deg, #10b981, #6ee7b7); }
    .card-user:hover { border-color: #10b981; }
    .card-user .icon-box { background: rgba(16, 185, 129, 0.08); color: #059669; }
    
    .card-periode::before { background: linear-gradient(90deg, #8b5cf6, #c084fc); }
    .card-periode:hover { border-color: #8b5cf6; }
    .card-periode .icon-box { background: rgba(139, 92, 246, 0.08); color: #7c3aed; }
    
    .card-history::before { background: linear-gradient(90deg, #14b8a6, #5eead4); }
    .card-history:hover { border-color: #14b8a6; }
    .card-history .icon-box { background: rgba(20, 184, 166, 0.08); color: #0d9488; }
    
    .card-reset::before { background: linear-gradient(90deg, #ef4444, #fca5a5); }
    .card-reset:hover { border-color: #ef4444; }
    .card-reset .icon-box { background: rgba(239, 68, 68, 0.08); color: #dc2626; }
    
    .card-backup::before { background: linear-gradient(90deg, #3b82f6, #93c5fd); }
    .card-backup:hover { border-color: #3b82f6; }
    .card-backup .icon-box { background: rgba(59, 130, 246, 0.08); color: #2563eb; }

    .icon-box {
        width: 52px;
        height: 52px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
        transition: all var(--transition-speed) ease;
        margin-bottom: 1.25rem;
    }

    .settings-card:hover .icon-box {
        transform: scale(1.05);
    }

    .card-title {
        font-size: 1.15rem;
        font-weight: 600;
        color: var(--text-dark);
        margin-bottom: 0.5rem;
    }

    .card-desc {
        color: var(--text-muted);
        font-size: 0.875rem;
        line-height: 1.5;
        margin-bottom: 1.5rem;
    }

    .settings-link {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        text-decoration: none;
        font-weight: 600;
        font-size: 0.9rem;
        color: #475569;
        margin-top: auto;
        transition: all 0.2s ease;
    }
    
    .settings-link i {
        transition: transform 0.2s ease;
    }
    
    .settings-link:hover {
        color: var(--text-dark);
    }

    .settings-link:hover i {
        transform: translateX(4px);
    }
</style>

<div class="container-fluid py-4 px-4">
    <!-- Header Page -->
    <div class="d-flex align-items-center mb-4">
        <div class="d-flex align-items-center justify-content-center rounded-circle me-3 shadow-sm flex-shrink-0" style="width: 56px; height: 56px; background: linear-gradient(135deg, #4f46e5, #3b82f6); color: white;">
            <i class="fas fa-cogs fa-lg"></i>
        </div>
        <div>
            <h3 class="fw-bold mb-1 text-dark" style="letter-spacing: -0.5px;">Pengaturan Sistem</h3>
            <p class="text-muted mb-0">Kelola semua konfigurasi inti aplikasi dan manajemen pengguna dari satu tempat</p>
        </div>
    </div>
    
    <div class="row g-4 settings-grid">

        <!-- KARTU: Pengaturan Izin (Tiket) -->
        <?php if (has_permission('izin_manage')): ?>
        <div class="col-xl-4 col-md-6">
            <div class="settings-card card-izin">
                <div class="icon-box"><i class="fas fa-ticket-alt"></i></div>
                <h5 class="card-title">Manajemen Hak Akses</h5>
                <p class="card-desc">Atur "tiket" atau izin akses untuk setiap user secara detail dan fleksibel sesuai jabatannya.</p>
                <a href="izin/index.php" class="settings-link">
                    Kelola Izin <i class="fas fa-arrow-right text-xs"></i>
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- KARTU: Manajemen User -->
        <?php if (has_permission('user_manage')): ?>
        <div class="col-xl-4 col-md-6">
            <div class="settings-card card-user">
                <div class="icon-box"><i class="fas fa-user-plus"></i></div>
                <h5 class="card-title">Manajemen User</h5>
                <p class="card-desc">Buat, lihat, ubah password, dan kelola akun login untuk penanggung jawab setiap bagian.</p>
                <a href="users/index.php" class="settings-link">
                    Kelola User <i class="fas fa-arrow-right text-xs"></i>
                </a>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- KARTU: Periode Aktif -->
        <?php if (has_permission('periode_aktif_manage')): ?>
        <div class="col-xl-4 col-md-6">
            <div class="settings-card card-periode">
                <div class="icon-box"><i class="fas fa-calendar-alt"></i></div>
                <h5 class="card-title">Periode Aktif</h5>
                <p class="card-desc">Atur rentang waktu rekap data pelanggaran yang sedang berjalan secara aktif di sistem.</p>
                <a href="periode-aktif/index.php" class="settings-link">
                    Kelola Periode <i class="fas fa-arrow-right text-xs"></i>
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- KARTU: Riwayat Pelanggaran -->
        <?php if (has_permission('history_manage')): ?>
        <div class="col-xl-4 col-md-6">
            <div class="settings-card card-history">
                <div class="icon-box"><i class="fas fa-history"></i></div>
                <h5 class="card-title">Riwayat Pelanggaran</h5>
                <p class="card-desc">Lihat log dan kelola riwayat pelanggaran santri. Batalkan pelanggaran jika terjadi kesalahan input.</p>
                <a href="history/index.php" class="settings-link">
                    Lihat Riwayat <i class="fas fa-arrow-right text-xs"></i>
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- KARTU: Reset Poin Santri -->
        <?php if (has_permission('reset_poin_manage')): ?>
        <div class="col-xl-4 col-md-6">
            <div class="settings-card card-reset">
                <div class="icon-box"><i class="fas fa-sync-alt"></i></div>
                <h5 class="card-title">Reset Poin Santri</h5>
                <p class="card-desc">Kembalikan poin pelanggaran santri ke nol, baik secara individu maupun keseluruhan.</p>
                <a href="reset-poin/index.php" class="settings-link">
                    Reset Poin <i class="fas fa-arrow-right text-xs"></i>
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- KARTU: Backup & Restore -->
        <?php if (has_permission('backup_restore_manage')): ?>
        <div class="col-xl-4 col-md-6">
            <div class="settings-card card-backup">
                <div class="icon-box"><i class="fas fa-database"></i></div>
                <h5 class="card-title">Backup & Restore</h5>
                <p class="card-desc">Cadangkan seluruh data sistem ke dalam file SQL, atau pulihkan data dari file cadangan yang ada.</p>
                <a href="backup-restore/index.php" class="settings-link">
                    Kelola Database <i class="fas fa-arrow-right text-xs"></i>
                </a>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>