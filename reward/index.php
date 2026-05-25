<?php
// 1. Panggil Otak
require_once __DIR__ . '/../bootstrap/init.php';
// 2. Satpam (Cek login umum aja)
// 2. Satpam (Cek apakah user punya hak akses ke modul reward)
guard(['jenis_reward_view', 'reward_input', 'reward_history']); 

require_once __DIR__ . '/../layouts/header.php';
?>

<style>
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
    }
    .hover-up:hover { 
        transform: translateY(-3px); 
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05) !important;
    }
    
    .page-header {
        margin-bottom: 2rem;
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
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
        <h3 class="fw-bolder text-dark mb-0"><i class="fas fa-gift text-primary me-2"></i>Pusat Apresiasi Santri</h3>
        <p class="text-muted mb-0">Kelola reward, input prestasi, dan lihat riwayat kebaikan santri</p>
    </div>

    <div class="row g-4 justify-content-center">
        <?php if(has_permission('reward_input')): ?>
        <div class="col-md-6 col-lg-4">
            <a href="input/create.php" class="text-decoration-none">
                <div class="card h-100 hover-up p-4 d-flex flex-row align-items-center gap-3 border-start border-4 border-primary">
                    <div class="icon-container text-primary flex-shrink-0" style="background-color: rgba(79, 70, 229, 0.1);">
                        <i class="fas fa-hand-holding-heart fs-4"></i>
                    </div>
                    <div>
                        <h6 class="text-dark fw-bold mb-1">Input Reward</h6>
                        <p class="text-muted small mb-0">Catat prestasi atau kebaikan santri untuk mengurangi poin</p>
                    </div>
                </div>
            </a>
        </div>
        <?php endif; ?>

        <?php if(has_permission('reward_history')): ?>
        <div class="col-md-6 col-lg-4">
            <a href="history/index.php" class="text-decoration-none">
                <div class="card h-100 hover-up p-4 d-flex flex-row align-items-center gap-3 border-start border-4 border-warning">
                    <div class="icon-container text-warning flex-shrink-0" style="background-color: rgba(255, 193, 7, 0.15);">
                        <i class="fas fa-history fs-4"></i>
                    </div>
                    <div>
                        <h6 class="text-dark fw-bold mb-1">Riwayat Reward</h6>
                        <p class="text-muted small mb-0">Lihat log penerima reward beserta detailnya</p>
                    </div>
                </div>
            </a>
        </div>
        <?php endif; ?>

        <?php if(has_permission('jenis_reward_view')): ?>
        <div class="col-md-6 col-lg-4">
            <a href="jenis/index.php" class="text-decoration-none">
                <div class="card h-100 hover-up p-4 d-flex flex-row align-items-center gap-3 border-start border-4 border-success">
                    <div class="icon-container text-success flex-shrink-0" style="background-color: rgba(25, 135, 84, 0.1);">
                        <i class="fas fa-tags fs-4"></i>
                    </div>
                    <div>
                        <h6 class="text-dark fw-bold mb-1">Jenis Reward</h6>
                        <p class="text-muted small mb-0">Kelola master data reward dan bobot poin pengurang</p>
                    </div>
                </div>
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>