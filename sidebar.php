<?php
// Pastikan session sudah berjalan
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ambil URL sekarang buat nandain menu aktif
$current_uri = htmlspecialchars($_SERVER['REQUEST_URI']);

// Pastikan BASE_URL sudah didefinisikan (dari config.php yang dipanggil di header.php)
if (!defined('BASE_URL')) {
    // Fallback sederhana jika config.php lupa di-include, meskipun seharusnya tidak terjadi.
    define('BASE_URL', ''); 
}
?>

<ul class="nav flex-column">

    <li class="nav-item">
        <a class="nav-link apply-color-hover-active color-blue <?= strpos($current_uri, 'index.php') !== false || $current_uri === BASE_URL . '/' ? 'active' : '' ?>" href="<?= BASE_URL ?>/index.php">
            <i class="fas fa-home me-2"></i>Dashboard
        </a>
    </li>

    <?php if (has_permission('santri_view')): ?>
    <li class="nav-item">
        <a class="nav-link apply-color-hover-active color-green <?= strpos($current_uri, '/santri') !== false ? 'active' : '' ?>" href="<?= BASE_URL ?>/santri">
            <i class="fas fa-users me-2"></i>Data Santri
        </a>
    </li>
    <?php endif; ?>

    <?php if (has_permission(['jenis_pelanggaran_view', 'jenis_pelanggaran_create', 'jenis_pelanggaran_edit', 'jenis_pelanggaran_delete'])): ?>
    <li class="nav-item">
        <a class="nav-link apply-color-hover-active color-orange <?= strpos($current_uri, '/jenis-pelanggaran') !== false ? 'active' : '' ?>" href="<?= BASE_URL ?>/jenis-pelanggaran">
            <i class="fas fa-exclamation-triangle me-2"></i>Jenis Pelanggaran
        </a>
    </li>
    <?php endif; ?>
    
    <?php if (has_permission(['pelanggaran_bahasa_input', 'pelanggaran_diniyyah_input', 'pelanggaran_kesantrian_input', 'pelanggaran_pengabdian_input', 'pelanggaran_tahfidz_input'])): ?>
    <li class="nav-item">
        <a class="nav-link apply-color-hover-active color-indigo <?= strpos($current_uri, '/pelanggaran') !== false ? 'active' : '' ?>" href="<?= BASE_URL ?>/pelanggaran">
            <i class="fas fa-clipboard-list me-2"></i>Catatan Pelanggaran
        </a>
    </li>
    <?php endif; ?>

    <?php if (has_permission('eksekusi_manage')): ?>
    <li class="nav-item">
        <a class="nav-link apply-color-hover-active color-teal <?= strpos($current_uri, '/eksekusi') !== false ? 'active' : '' ?>" href="<?= BASE_URL ?>/eksekusi">
            <i class="fas fa-broom me-2"></i>Eksekusi Kebersihan
        </a>
    </li>
    <?php endif; ?>

    <?php if (has_permission(['arsip_view', 'arsip_create', 'arsip_delete'])): ?>
    <li class="nav-item">
        <a class="nav-link apply-color-hover-active color-purple <?= strpos($current_uri, '/arsip') !== false ? 'active' : '' ?>" href="<?= BASE_URL ?>/arsip">
            <i class="fas fa-archive me-2"></i>Arsip
        </a>
    </li>
    <?php endif; ?>

    <?php if (has_permission(['rekap_view_umum', 'rekap_view_per_kamar', 'rekap_view_santri', 'rekap_view_statistik', 'rekap_view_tahfidz'])): ?>
    <li class="nav-item">
        <a class="nav-link apply-color-hover-active color-red <?= strpos($current_uri, '/rekap') !== false ? 'active' : '' ?>" href="<?= BASE_URL ?>/rekap">
            <i class="fas fa-chart-bar me-2"></i>Rekap Pelanggaran
        </a>
    </li>
    <?php endif; ?>

    
    <?php if (has_permission(['rapot_view', 'rapot_create', 'rapot_cetak', 'rapot_delete'])): ?>
    <li class="nav-item">
        <a class="nav-link apply-color-hover-active color-pink <?= strpos($current_uri, '/rapot') !== false ? 'active' : '' ?>" href="<?= BASE_URL ?>/rapot">
            <i class="fas fa-file-invoice me-2"></i>Rapot Kepengasuhan
        </a>
    </li>
    <?php endif; ?>
    <?php if (has_permission('export_laporan')): ?>
    <li class="nav-item">
        <a class="nav-link apply-color-hover-active color-cyan <?= strpos($current_uri, '/export') !== false ? 'active' : '' ?>" href="<?= BASE_URL ?>/export">
            <i class="fas fa-file-excel me-2"></i>Export Laporan
        </a>
    </li>
    <?php endif; ?>
    
    <?php if (has_permission(['user_manage', 'periode_aktif_manage', 'reset_poin_manage', 'izin_manage', 'history_manage'])): ?>
    <li class="nav-item">
        <a class="nav-link apply-color-hover-active color-gray <?= strpos($current_uri, '/pengaturan') !== false ? 'active' : '' ?>" href="<?= BASE_URL ?>/pengaturan">
            <i class="fas fa-cog me-2"></i>Pengaturan
        </a>
    </li>
    <?php endif; ?>
    
</ul>