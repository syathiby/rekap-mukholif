<?php 
// 1. Panggil 'Otak' aplikasi dulu
require_once __DIR__ . '/../../bootstrap/init.php';

// 2. Jalankan 'SATPAM' buat ngejaga halaman
guard('periode_aktif_manage'); 

// 3. Kalau lolos, baru panggil Tampilan
require_once __DIR__ . '/../../layouts/header.php';
?>

<?php
// Ambil periode aktif dari konstanta global
$periode_aktif = PERIODE_AKTIF;
?>

<style>
    :root {
        --primary: #4F46E5;
        --primary-hover: #4338ca;
        --border-color: #e5e7eb;
        --text-dark: #1f2937;
    }

    .periode-info-box {
        background-color: rgba(79, 70, 229, 0.04);
        border-left: 4px solid var(--primary);
        border-radius: 0.5rem;
        padding: 1rem;
        font-size: 0.9rem;
        line-height: 1.5;
        color: #334155;
    }
    
    .input-date-premium {
        padding: 0.85rem 1rem;
        border-radius: 0.75rem;
        border: 1px solid var(--border-color);
        background-color: #f8fafc;
        font-size: 0.95rem;
        font-weight: 500;
        transition: all 0.25s ease;
        color: var(--text-dark);
        width: 100%;
    }
    
    .input-date-premium:focus {
        background-color: #ffffff;
        border-color: var(--primary);
        box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
        outline: none;
    }
    
    .btn-submit-premium {
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-hover) 100%);
        color: white;
        font-weight: 600;
        padding: 0.8rem 1.75rem;
        border-radius: 0.75rem;
        box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2);
        transition: all 0.25s ease;
        border: none;
    }
    
    .btn-submit-premium:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(79, 70, 229, 0.3);
        color: white;
    }
</style>

<div class="container-fluid py-4 px-4">
    <!-- Header Page -->
    <div class="d-flex align-items-center mb-4">
        <a href="../index.php" class="btn btn-light rounded-circle me-3 shadow-sm d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; color: #475569; border: 1px solid #e2e8f0; background: #fff;">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <h3 class="fw-bold text-dark mb-1">Pengaturan Periode Aktif</h3>
            <p class="text-muted mb-0">Tentukan rentang data aktif yang akan disajikan pada dashboard utama</p>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6 col-md-8 mx-auto">
            <!-- Alert Notification -->
            <?php if (isset($_SESSION['notif'])): ?>
                <div class="alert alert-<?= $_SESSION['notif']['type']; ?> alert-dismissible fade show mb-4 shadow-sm" role="alert">
                    <?php if ($_SESSION['notif']['type'] == 'success'): ?>
                        <i class="fas fa-check-circle me-2"></i>
                    <?php else: ?>
                        <i class="fas fa-exclamation-circle me-2"></i>
                    <?php endif; ?>
                    <?= $_SESSION['notif']['msg']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['notif']); ?>
            <?php endif; ?>

            <!-- Premium Card Form -->
            <div class="card-premium">
                <div class="card-header-premium">
                    <h5 class="card-title mb-0"><i class="fas fa-calendar-alt text-primary me-2"></i>Atur Tanggal Periode</h5>
                </div>
                <div class="p-4 bg-white">
                    <div class="periode-info-box mb-4">
                        <i class="fas fa-info-circle text-primary me-1"></i>
                        Periode aktif menentukan data mana yang ditampilkan di halaman utama.  
                        Data sebelum tanggal ini akan <strong>disembunyikan</strong> secara otomatis demi performa dan fokus data, namun tetap aman tersimpan di dalam database.
                    </div>

                    <form action="process.php" method="post">
                        <div class="mb-4">
                            <label for="periode_aktif" class="form-label fw-semibold text-dark mb-2">Tanggal Periode Aktif:</label>
                            <input type="date" id="periode_aktif" name="periode_aktif" 
                                   class="input-date-premium" value="<?= $periode_aktif; ?>" required>
                        </div>

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn-submit-premium">
                                <i class="fas fa-save me-2"></i> Simpan Periode
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../layouts/footer.php'; ?>