<?php
// Pastikan session sudah berjalan
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ambil URL sekarang buat nandain menu aktif
$current_uri = htmlspecialchars($_SERVER['REQUEST_URI']);

// Pastikan BASE_URL sudah didefinisikan (dari config.php yang dipanggil di header.php)
if (!defined('BASE_URL')) {
    define('BASE_URL', ''); 
}

// Jurus Jitu Deteksi Menu Aktif:
// Ambil path relatif setelah folder project
$project_path = parse_url(BASE_URL, PHP_URL_PATH);
$req_path = str_replace($project_path, '', parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$req_path = '/' . ltrim($req_path, '/');
?>

<ul class="nav flex-column">

    <li class="nav-item">
        <a class="nav-link apply-color-hover-active color-blue <?= ($req_path === '/' || strpos($req_path, '/index.php') !== false) ? 'active' : '' ?>" href="<?= BASE_URL ?>/index.php">
            <i class="fas fa-home me-2"></i>Dashboard
        </a>
    </li>

    <?php if (has_permission('santri_view')): ?>
    <li class="nav-item">
        <a class="nav-link apply-color-hover-active color-green <?= strpos($req_path, '/santri') === 0 ? 'active' : '' ?>" href="<?= BASE_URL ?>/santri">
            <i class="fas fa-users me-2"></i>Data Santri
        </a>
    </li>
    <?php endif; ?>

    <?php if (has_permission(['jenis_pelanggaran_view', 'jenis_pelanggaran_create', 'jenis_pelanggaran_edit', 'jenis_pelanggaran_delete'])): ?>
    <li class="nav-item">
        <a class="nav-link apply-color-hover-active color-orange <?= strpos($req_path, '/jenis-pelanggaran') === 0 ? 'active' : '' ?>" href="<?= BASE_URL ?>/jenis-pelanggaran">
            <i class="fas fa-exclamation-triangle me-2"></i>Jenis Pelanggaran
        </a>
    </li>
    <?php endif; ?>
    
    <?php if (has_permission(['pelanggaran_bahasa_input', 'pelanggaran_diniyyah_input', 'pelanggaran_kesantrian_input', 'pelanggaran_pengabdian_input', 'pelanggaran_tahfidz_input'])): ?>
    <li class="nav-item">
        <a class="nav-link apply-color-hover-active color-indigo <?= strpos($req_path, '/pelanggaran') === 0 ? 'active' : '' ?>" href="<?= BASE_URL ?>/pelanggaran">
            <i class="fas fa-clipboard-list me-2"></i>Catatan Pelanggaran
        </a>
    </li>
    <?php endif; ?>

    <?php if (has_permission(['jenis_reward_view', 'reward_input', 'reward_history'])): ?>
    <li class="nav-item">
        <a class="nav-link apply-color-hover-active color-orange <?= strpos($req_path, '/reward') === 0 ? 'active' : '' ?>" href="<?= BASE_URL ?>/reward">
            <i class="fas fa-trophy me-2"></i>Reward & Prestasi
        </a>
    </li>
    <?php endif; ?>

    <?php if (has_permission('eksekusi_manage')): ?>
    <li class="nav-item">
        <a class="nav-link apply-color-hover-active color-teal <?= strpos($req_path, '/eksekusi') === 0 ? 'active' : '' ?>" href="<?= BASE_URL ?>/eksekusi">
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
        <a class="nav-link apply-color-hover-active color-red <?= strpos($req_path, '/rekap') === 0 ? 'active' : '' ?>" href="<?= BASE_URL ?>/rekap">
            <i class="fas fa-chart-bar me-2"></i>Rekap Pelanggaran
        </a>
    </li>
    <?php endif; ?>

    
    <?php if (has_permission(['rapot_view', 'rapot_create', 'rapot_cetak', 'rapot_delete'])): ?>
    <li class="nav-item">
        <a class="nav-link apply-color-hover-active color-pink <?= strpos($req_path, '/rapot') === 0 ? 'active' : '' ?>" href="<?= BASE_URL ?>/rapot">
            <i class="fas fa-file-invoice me-2"></i>Rapot Kepengasuhan
        </a>
    </li>
    <?php endif; ?>
    <?php if (has_permission('export_laporan')): ?>
    <li class="nav-item">
        <a class="nav-link apply-color-hover-active color-cyan <?= strpos($req_path, '/export') === 0 ? 'active' : '' ?>" href="<?= BASE_URL ?>/export">
            <i class="fas fa-file-excel me-2"></i>Export Data
        </a>
    </li>
    <?php endif; ?>
    
    <?php if (has_permission(['user_manage', 'periode_aktif_manage', 'reset_poin_manage', 'izin_manage', 'history_manage'])): ?>
    <li class="nav-item">
        <a class="nav-link apply-color-hover-active color-gray <?= strpos($req_path, '/pengaturan') === 0 ? 'active' : '' ?>" href="<?= BASE_URL ?>/pengaturan">
            <i class="fas fa-cog me-2"></i>Pengaturan
        </a>
    </li>
    <?php endif; ?>
    
</ul>