<?php 
// 1. Panggil 'Otak' aplikasi dulu
require_once __DIR__ . '/../bootstrap/init.php';

// 2. Jalankan 'SATPAM' buat ngejaga halaman
guard(['pelanggaran_bahasa_input', 'pelanggaran_diniyyah_input', 'pelanggaran_tahfidz_input', 'pelanggaran_kesantrian_input', 'pelanggaran_pengabdian_input']);   

// 3. Kalau lolos, baru panggil Tampilan
require_once __DIR__ . '/../layouts/header.php'; 
?>
<style>
    .hover-up {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        border: 1px solid var(--border-color, #e2e8f0) !important;
        border-radius: 1rem;
        background: var(--bg-card, #ffffff);
    }
    .hover-up:hover { 
        transform: translateY(-3px); 
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05) !important;
    }
    
    .icon-container {
        width: 50px;
        height: 50px;
        border-radius: 1rem;
        display: flex;
        align-items: center;
        justify-content: center;
    }
</style>

<div class="container-fluid py-4 px-4">
    <!-- Header Page -->
    <div class="d-flex flex-column align-items-center justify-content-center text-center mb-5 gap-2">
        <h3 class="fw-bolder text-dark mb-0"><i class="fas fa-edit text-primary me-2"></i>Pilih Kategori Pencatatan</h3>
        <p class="text-muted mb-0">Silakan pilih kategori divisi untuk mencatat pelanggaran santri</p>
    </div>

    <div class="row g-4 justify-content-center">
        
        <?php if (has_permission('pelanggaran_pengabdian_input')): ?>
        <div class="col-md-6 col-lg-4">
            <a href="pengabdian/create.php" class="text-decoration-none">
                <div class="card h-100 hover-up p-4 d-flex flex-row align-items-center gap-3 border-start border-4 border-warning">
                    <div class="icon-container text-warning flex-shrink-0" style="background-color: rgba(255, 193, 7, 0.15);">
                        <i class="fas fa-hands-helping fs-4"></i>
                    </div>
                    <div>
                        <h6 class="text-dark fw-bold mb-1">Divisi Pengabdian</h6>
                        <p class="text-muted small mb-0">Catat pelanggaran individu & kebersihan kamar</p>
                    </div>
                </div>
            </a>
        </div>
        <?php endif; ?>

        <?php if (has_permission('pelanggaran_kesantrian_input')): ?>
        <div class="col-md-6 col-lg-4">
            <a href="kesantrian/create.php" class="text-decoration-none">
                <div class="card h-100 hover-up p-4 d-flex flex-row align-items-center gap-3 border-start border-4 border-primary">
                    <div class="icon-container text-primary flex-shrink-0" style="background-color: rgba(79, 70, 229, 0.1);">
                        <i class="fas fa-user-shield fs-4"></i>
                    </div>
                    <div>
                        <h6 class="text-dark fw-bold mb-1">Divisi Kesantrian</h6>
                        <p class="text-muted small mb-0">Catat berbagai jenis pelanggaran umum kesantrian</p>
                    </div>
                </div>
            </a>
        </div>
        <?php endif; ?>

        <?php if (has_permission('pelanggaran_bahasa_input')): ?>
        <div class="col-md-6 col-lg-4">
            <a href="bahasa/create.php" class="text-decoration-none">
                <div class="card h-100 hover-up p-4 d-flex flex-row align-items-center gap-3 border-start border-4 border-info">
                    <div class="icon-container text-info flex-shrink-0" style="background-color: rgba(13, 202, 240, 0.1);">
                        <i class="fas fa-language fs-4"></i>
                    </div>
                    <div>
                        <h6 class="text-dark fw-bold mb-1">Divisi Bahasa</h6>
                        <p class="text-muted small mb-0">Catat pelanggaran muhadatsah & kosa kata</p>
                    </div>
                </div>
            </a>
        </div>
        <?php endif; ?>

        <?php if (has_permission('pelanggaran_diniyyah_input')): ?>
        <div class="col-md-6 col-lg-4">
            <a href="diniyyah/create.php" class="text-decoration-none">
                <div class="card h-100 hover-up p-4 d-flex flex-row align-items-center gap-3 border-start border-4 border-success">
                    <div class="icon-container text-success flex-shrink-0" style="background-color: rgba(25, 135, 84, 0.1);">
                        <i class="fas fa-book-quran fs-4"></i>
                    </div>
                    <div>
                        <h6 class="text-dark fw-bold mb-1">Divisi Diniyyah</h6>
                        <p class="text-muted small mb-0">Catat pelanggaran kegiatan belajar mengajar</p>
                    </div>
                </div>
            </a>
        </div>
        <?php endif; ?>

        <?php if (has_permission('pelanggaran_tahfidz_input')): ?>
        <div class="col-md-6 col-lg-4">
            <a href="tahfidz/create.php" class="text-decoration-none">
                <div class="card h-100 hover-up p-4 d-flex flex-row align-items-center gap-3 border-start border-4 border-danger">
                    <div class="icon-container text-danger flex-shrink-0" style="background-color: rgba(220, 53, 69, 0.1);">
                        <i class="fas fa-book-reader fs-4"></i>
                    </div>
                    <div>
                        <h6 class="text-dark fw-bold mb-1">Divisi Tahfidz</h6>
                        <p class="text-muted small mb-0">Catat berbagai pelanggaran halaqoh tahfidz</p>
                    </div>
                </div>
            </a>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php include __DIR__ . '/../layouts/footer.php'; ?>