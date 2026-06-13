<div class="container-fluid py-4 mb-5">
    <div class="text-center mb-5 mt-4">
        <h3 class="fw-bold mb-2"><i class="fas fa-edit text-primary me-2"></i> Pilih Kategori Pencatatan</h3>
        <p class="text-muted">Silakan pilih kategori divisi untuk mencatat pelanggaran santri</p>
    </div>

    <?php require_once __DIR__ . '/../../components/alert.php'; ?>

    <div class="row g-4 justify-content-center">
        <?php
        $bidangList = [
            'pengabdian' => ['icon' => 'fa-hands-helping', 'color' => 'warning', 'title' => 'Divisi Pengabdian', 'desc' => 'Catat pelanggaran individu & kebersihan kamar'],
            'kesantrian' => ['icon' => 'fa-user-shield', 'color' => 'primary', 'title' => 'Divisi Kesantrian', 'desc' => 'Catat berbagai jenis pelanggaran umum kesantrian'],
            'bahasa' => ['icon' => 'fa-spell-check', 'color' => 'info', 'title' => 'Divisi Bahasa', 'desc' => 'Catat pelanggaran muhadatsah & kosa kata'],
            'diniyyah' => ['icon' => 'fa-book-quran', 'color' => 'success', 'title' => 'Divisi Diniyyah', 'desc' => 'Catat pelanggaran kegiatan belajar mengajar'],
            'tahfidz' => ['icon' => 'fa-book-open-reader', 'color' => 'danger', 'title' => 'Divisi Tahfidz', 'desc' => 'Catat berbagai pelanggaran halaqoh tahfidz'],
        ];

        foreach ($bidangList as $key => $item):
            // Check permission, we can disable or grey out if they don't have access.
            $hasInput = \App\Helpers\AuthHelper::hasPermission("pelanggaran_{$key}_input");
            $cardClass = $hasInput ? 'card-hover' : 'opacity-50';
            $linkUrl = $hasInput ? BASE_URL . "/pelanggaran/{$key}/create" : '#';
            $cursor = $hasInput ? '' : 'cursor: not-allowed;';
        ?>
            <div class="col-12 col-md-6 col-lg-4">
                <a href="<?= $linkUrl ?>" class="text-decoration-none" hx-boost="<?= $hasInput ? 'true' : 'false' ?>" style="<?= $cursor ?>">
                    <div class="card shadow-sm border-0 h-100 <?= $cardClass ?>" style="border-radius: 1rem;">
                        <div class="card-body d-flex align-items-center p-4">
                             <div class="bg-<?= $item['color'] ?> bg-opacity-10 text-<?= $item['color'] ?> me-4 d-flex align-items-center justify-content-center" style="width: 60px; height: 60px; border-radius: 0.8rem; flex-shrink: 0;">
                                 <i class="fas <?= $item['icon'] ?> fa-2x"></i>
                             </div>
                             <div>
                                 <h5 class="fw-bold text-dark mb-1"><?= $item['title'] ?></h5>
                                 <p class="text-muted mb-0" style="font-size: 0.85rem; line-height: 1.4;"><?= $item['desc'] ?></p>
                             </div>
                        </div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<style>
    .card-hover {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .card-hover:hover {
        transform: translateY(-3px);
        box-shadow: 0 .5rem 1rem rgba(0,0,0,.08)!important;
    }
</style>
