<?php
// 1. Panggil Otak
require_once __DIR__ . '/../init.php';
// 2. Satpam (Cek login umum aja)
// 2. Satpam (Cek apakah user punya hak akses ke modul reward)
guard(['jenis_reward_view', 'reward_input', 'reward_history']); 

require_once __DIR__ . '/../header.php';
?>

<div class="content-wrapper" style="padding: 20px;">
    <div class="page-title-card p-4 mb-4" style="background: linear-gradient(135deg, #11998e, #38ef7d); border-radius: 12px; color: white; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
        <h2 class="mb-1">🎁 Pusat Apresiasi Santri</h2>
        <p class="mb-0 opacity-75">Kelola reward, input prestasi, dan lihat riwayat kebaikan santri.</p>
    </div>

    <div class="row g-4">
        <?php if(has_permission('reward_input')): ?>
        <div class="col-md-4">
            <a href="input/create.php" class="text-decoration-none">
                <div class="card h-100 border-0 shadow-sm hover-up" style="transition: transform 0.3s;">
                    <div class="card-body text-center p-4">
                        <div class="icon-circle mb-3 mx-auto" style="width: 60px; height: 60px; background: #e3f2fd; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-hand-holding-heart fa-2x text-primary"></i>
                        </div>
                        <h5 class="text-dark fw-bold">Input Reward</h5>
                        <p class="text-muted small">Catat prestasi atau kebaikan santri untuk mengurangi poin pelanggaran.</p>
                    </div>
                </div>
            </a>
        </div>
        <?php endif; ?>

        <?php if(has_permission('reward_history')): ?>
        <div class="col-md-4">
            <a href="history/index.php" class="text-decoration-none">
                <div class="card h-100 border-0 shadow-sm hover-up" style="transition: transform 0.3s;">
                    <div class="card-body text-center p-4">
                        <div class="icon-circle mb-3 mx-auto" style="width: 60px; height: 60px; background: #fff3cd; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-history fa-2x text-warning"></i>
                        </div>
                        <h5 class="text-dark fw-bold">Riwayat Reward</h5>
                        <p class="text-muted small">Lihat log siapa saja yang mendapatkan reward dan detailnya.</p>
                    </div>
                </div>
            </a>
        </div>
        <?php endif; ?>

        <?php if(has_permission('jenis_reward_view')): ?>
        <div class="col-md-4">
            <a href="jenis/index.php" class="text-decoration-none">
                <div class="card h-100 border-0 shadow-sm hover-up" style="transition: transform 0.3s;">
                    <div class="card-body text-center p-4">
                        <div class="icon-circle mb-3 mx-auto" style="width: 60px; height: 60px; background: #d1e7dd; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-tags fa-2x text-success"></i>
                        </div>
                        <h5 class="text-dark fw-bold">Jenis Reward</h5>
                        <p class="text-muted small">Kelola master data jenis reward dan bobot poin pengurangnya.</p>
                    </div>
                </div>
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
    .hover-up:hover { transform: translateY(-5px); }
</style>

<?php require_once __DIR__ . '/../footer.php'; ?>