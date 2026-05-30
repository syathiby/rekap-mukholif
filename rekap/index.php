<?php
// 1. Panggil 'Otak' aplikasi dulu
require_once __DIR__ . '/../bootstrap/init.php';

// 2. Jalankan 'SATPAM' buat ngejaga halaman
guard(['rekap_view_statistik', 'rekap_kebersihan', 'rekap_keterlambatan', 'rekap_pelanggaran_umum', 'rekap_detail_santri', 'rekap_santri_teladan']);

// 3. Kalau lolos, baru panggil Tampilan
require_once __DIR__ . '/../layouts/header.php'; 
?><style>
    :root {
        --primary: #4f46e5;
        --primary-hover: #4338ca;
        --bg-body: #f8fafc;
        --bg-card: #ffffff;
        --text-main: #0f172a;
        --text-muted: #64748b;
        --border: #e2e8f0;
    }
    
    .hover-up {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        border: 1px solid var(--border) !important;
        border-radius: 1rem;
        background: var(--bg-card);
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
        <h3 class="fw-bolder text-dark mb-0"><i class="fas fa-chart-pie text-primary me-2"></i>Menu Rekap Pelanggaran</h3>
        <p class="text-muted mb-0">Akses seluruh laporan dan statistik pelanggaran santri</p>
    </div>

    <div class="row g-4">
        <?php if (has_permission('rekap_pelanggaran_umum')): ?>
        <div class="col-md-6 col-lg-4">
            <a href="pelanggaran_umum.php" class="text-decoration-none">
                <div class="card h-100 hover-up p-4 d-flex flex-row align-items-center gap-3 border-start border-4 border-primary">
                    <div class="icon-container text-primary flex-shrink-0" style="background-color: rgba(79, 70, 229, 0.1);">
                        <i class="fas fa-list-alt fs-4"></i>
                    </div>
                    <div>
                        <h6 class="text-dark fw-bold mb-1">Pelanggaran Umum</h6>
                        <p class="text-muted small mb-0">Laporan rekapitulasi pelanggaran secara umum</p>
                    </div>
                </div>
            </a>
        </div>
        <?php endif; ?>

        <?php if (has_permission('rekap_detail_santri')): ?>
        <div class="col-md-6 col-lg-4">
            <a href="umum.php" class="text-decoration-none">
                <div class="card h-100 hover-up p-4 d-flex flex-row align-items-center gap-3 border-start border-4" style="border-left-color: #10b981 !important;">
                    <div class="icon-container flex-shrink-0" style="color: #10b981; background-color: rgba(16, 185, 129, 0.1);">
                        <i class="fas fa-chart-pie fs-4"></i>
                    </div>
                    <div>
                        <h6 class="text-dark fw-bold mb-1">Analisis Karakter Santri</h6>
                        <p class="text-muted small mb-0">Rekapitulasi total pelanggaran & reward santri</p>
                    </div>
                </div>
            </a>
        </div>
        <?php endif; ?>

        <?php if (has_permission('rekap_keterlambatan')): ?>
        <div class="col-md-6 col-lg-4">
            <a href="keterlambatan.php" class="text-decoration-none">
                <div class="card h-100 hover-up p-4 d-flex flex-row align-items-center gap-3 border-start border-4 border-info">
                    <div class="icon-container text-info flex-shrink-0" style="background-color: rgba(13, 202, 240, 0.1);">
                        <i class="fas fa-user-graduate fs-4"></i>
                    </div>
                    <div>
                        <h6 class="text-dark fw-bold mb-1">Keterlambatan Santri</h6>
                        <p class="text-muted small mb-0">Laporan akumulasi poin dan denda keterlambatan</p>
                    </div>
                </div>
            </a>
        </div>
        <?php endif; ?>

        <?php if (has_permission('rekap_santri_teladan')): ?>
        <div class="col-md-6 col-lg-4">
            <a href="santri_teladan.php" class="text-decoration-none">
                <div class="card h-100 hover-up p-4 d-flex flex-row align-items-center gap-3 border-start border-4" style="border-left-color: #3b82f6 !important;">
                    <div class="icon-container flex-shrink-0" style="color: #3b82f6; background-color: rgba(59, 130, 246, 0.1);">
                        <i class="fas fa-award fs-4"></i>
                    </div>
                    <div>
                        <h6 class="text-dark fw-bold mb-1">Santri Teladan</h6>
                        <p class="text-muted small mb-0">Peringkat santri berprestasi tanpa pelanggaran</p>
                    </div>
                </div>
            </a>
        </div>
        <?php endif; ?>

        <?php if (has_permission('rekap_kebersihan')): ?>
        <div class="col-md-6 col-lg-4">
            <a href="kebersihan.php" class="text-decoration-none">
                <div class="card h-100 hover-up p-4 d-flex flex-row align-items-center gap-3 border-start border-4 border-warning">
                    <div class="icon-container text-warning flex-shrink-0" style="background-color: rgba(255, 193, 7, 0.15);">
                        <i class="fas fa-home fs-4"></i>
                    </div>
                    <div>
                        <h6 class="text-dark fw-bold mb-1">Kebersihan Kamar</h6>
                        <p class="text-muted small mb-0">Laporan inspeksi kebersihan asrama dan kamar</p>
                    </div>
                </div>
            </a>
        </div>
        <?php endif; ?>

        <?php if (has_permission('rekap_view_bahasa')): ?>
        <div class="col-md-6 col-lg-4">
            <a href="../pelanggaran/bahasa/rekap.php" class="text-decoration-none">
                <div class="card h-100 hover-up p-4 d-flex flex-row align-items-center gap-3 border-start border-4 border-danger">
                    <div class="icon-container text-danger flex-shrink-0" style="background-color: rgba(220, 53, 69, 0.1);">
                        <i class="fas fa-language fs-4"></i>
                    </div>
                    <div>
                        <h6 class="text-dark fw-bold mb-1">Pelanggaran Bahasa</h6>
                        <p class="text-muted small mb-0">Rekapitulasi data denda kedisiplinan bahasa</p>
                    </div>
                </div>
            </a>
        </div>
        <?php endif; ?>

        <?php if (has_permission('rekap_view_diniyyah')): ?>
        <div class="col-md-6 col-lg-4">
            <a href="../pelanggaran/diniyyah/rekap.php" class="text-decoration-none">
                <div class="card h-100 hover-up p-4 d-flex flex-row align-items-center gap-3 border-start border-4 border-success">
                    <div class="icon-container text-success flex-shrink-0" style="background-color: rgba(25, 135, 84, 0.1);">
                        <i class="fas fa-book-quran fs-4"></i>
                    </div>
                    <div>
                        <h6 class="text-dark fw-bold mb-1">Pelanggaran Diniyyah</h6>
                        <p class="text-muted small mb-0">Laporan pelanggaran program pendidikan Diniyyah</p>
                    </div>
                </div>
            </a>
        </div>
        <?php endif; ?>
        
        <?php if (has_permission('rekap_view_tahfidz')): ?>
        <div class="col-md-6 col-lg-4">
            <a href="../pelanggaran/tahfidz/rekap.php" class="text-decoration-none">
                <div class="card h-100 hover-up p-4 d-flex flex-row align-items-center gap-3 border-start border-4" style="border-left-color: #0891b2 !important;">
                    <div class="icon-container flex-shrink-0" style="color: #0891b2; background-color: rgba(8, 145, 178, 0.1);">
                        <i class="fas fa-book-open fs-4"></i>
                    </div>
                    <div>
                        <h6 class="text-dark fw-bold mb-1">Pelanggaran Tahfidz</h6>
                        <p class="text-muted small mb-0">Laporan pelanggaran halaqoh tahfidz Qur'an</p>
                    </div>
                </div>
            </a>
        </div>
        <?php endif; ?>

        <?php if (has_permission('rekap_view_statistik')): ?>
        <div class="col-md-6 col-lg-4">
            <a href="chart.php" class="text-decoration-none">
                <div class="card h-100 hover-up p-4 d-flex flex-row align-items-center gap-3 border-start border-4" style="border-left-color: #8b5cf6 !important;">
                    <div class="icon-container flex-shrink-0" style="color: #8b5cf6; background-color: rgba(139, 92, 246, 0.1);">
                        <i class="fas fa-chart-line fs-4"></i>
                    </div>
                    <div>
                        <h6 class="text-dark fw-bold mb-1">Grafik Statistik</h6>
                        <p class="text-muted small mb-0">Visualisasi data dan tren analitik pelanggaran</p>
                    </div>
                </div>
            </a>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
