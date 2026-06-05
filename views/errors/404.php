<?php require_once VIEW_PATH . '/layouts/header.php'; ?>
<div class="d-flex align-items-center justify-content-center" style="min-height: 60vh;">
    <div class="text-center">
        <h1 class="display-1 fw-bold text-warning">404</h1>
        <h3 class="fw-semibold mb-3">Halaman Tidak Ditemukan</h3>
        <p class="text-muted mb-4">Halaman yang Anda cari mungkin telah dihapus atau URL salah.</p>
        <a href="<?= $_ENV['APP_URL'] ?? '' ?>/" class="btn btn-primary px-4"><i class="fas fa-home me-2"></i>Kembali ke Beranda</a>
    </div>
</div>
<?php require_once VIEW_PATH . '/layouts/footer.php'; ?>
