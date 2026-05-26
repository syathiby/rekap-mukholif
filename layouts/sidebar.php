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

// Cek izin input pelanggaran untuk link sidebar dinamis
$violation_url = BASE_URL . '/pelanggaran';
if (function_exists('has_permission')) {
    $permissions_list = [
        'pelanggaran_bahasa_input' => '/pelanggaran/bahasa/create.php',
        'pelanggaran_diniyyah_input' => '/pelanggaran/diniyyah/create.php',
        'pelanggaran_kesantrian_input' => '/pelanggaran/kesantrian/create.php',
        'pelanggaran_pengabdian_input' => '/pelanggaran/pengabdian/create.php',
        'pelanggaran_tahfidz_input' => '/pelanggaran/tahfidz/create.php',
    ];
    
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        $allowed_paths = [];
        foreach ($permissions_list as $perm => $path) {
            if (has_permission($perm)) {
                $allowed_paths[] = BASE_URL . $path;
            }
        }
        if (count($allowed_paths) === 1) {
            $violation_url = $allowed_paths[0];
        }
    }
}
?>

<!-- Nav Menu (SyathibyFood Style) -->
<div class="sb-nav">

    <div class="sb-section-label">MENU UTAMA</div>
    
    <a href="<?= BASE_URL ?>/index.php" class="sb-link <?= ($req_path === '/' || $req_path === '/index.php' || $req_path === '') ? 'active' : '' ?>">
        <i class="fas fa-home"></i>
        <span>Dashboard</span>
    </a>

    <?php if (has_permission(['pelanggaran_bahasa_input', 'pelanggaran_diniyyah_input', 'pelanggaran_kesantrian_input', 'pelanggaran_pengabdian_input', 'pelanggaran_tahfidz_input'])): ?>
    <a href="<?= $violation_url ?>" class="sb-link <?= strpos($req_path, '/pelanggaran') === 0 ? 'active' : '' ?>">
        <i class="fas fa-clipboard-list" style="color:#f87171;"></i>
        <span>Catatan Pelanggaran</span>
    </a>
    <?php endif; ?>

    <?php if (has_permission(['jenis_reward_view', 'reward_input', 'reward_history'])): ?>
    <a href="<?= BASE_URL ?>/reward" class="sb-link <?= strpos($req_path, '/reward') === 0 ? 'active' : '' ?>">
        <i class="fas fa-trophy" style="color:#34d399;"></i>
        <span>Reward & Prestasi</span>
    </a>
    <?php endif; ?>

    <?php if (has_permission('eksekusi_manage')): ?>
    <a href="<?= BASE_URL ?>/eksekusi" class="sb-link <?= strpos($req_path, '/eksekusi') === 0 ? 'active' : '' ?>">
        <i class="fas fa-broom" style="color:#a78bfa;"></i>
        <span>Eksekusi Kebersihan</span>
    </a>
    <?php endif; ?>

    <?php if (has_permission(['rekap_pelanggaran_umum', 'rekap_detail_santri', 'rekap_kebersihan', 'rekap_keterlambatan', 'rekap_view_statistik', 'rekap_view_tahfidz'])): ?>
    <a href="<?= BASE_URL ?>/rekap" class="sb-link <?= strpos($req_path, '/rekap') === 0 ? 'active' : '' ?>">
        <i class="fas fa-chart-bar" style="color:#38bdf8;"></i>
        <span>Rekap Pelanggaran</span>
    </a>
    <?php endif; ?>
    
    <?php if (has_permission(['rapot_view', 'rapot_create', 'rapot_cetak', 'rapot_delete'])): ?>
    <a href="<?= BASE_URL ?>/rapot" class="sb-link <?= strpos($req_path, '/rapot') === 0 ? 'active' : '' ?>">
        <i class="fas fa-file-invoice" style="color:#f472b6;"></i>
        <span>Rapot Kepengasuhan</span>
    </a>
    <?php endif; ?>

    <?php if (has_permission(['santri_view', 'jenis_pelanggaran_view', 'jenis_pelanggaran_create', 'jenis_pelanggaran_edit', 'jenis_pelanggaran_delete'])): ?>
    <div class="sb-section-label">MANAJEMEN</div>
    <?php endif; ?>
    
    <?php if (has_permission('santri_view')): ?>
    <a href="<?= BASE_URL ?>/santri" class="sb-link <?= strpos($req_path, '/santri') === 0 ? 'active' : '' ?>">
        <i class="fas fa-users" style="color:#60a5fa;"></i>
        <span>Data Santri</span>
    </a>
    <?php endif; ?>

    <?php if (has_permission(['jenis_pelanggaran_view', 'jenis_pelanggaran_create', 'jenis_pelanggaran_edit', 'jenis_pelanggaran_delete'])): ?>
    <a href="<?= BASE_URL ?>/jenis-pelanggaran" class="sb-link <?= strpos($req_path, '/jenis-pelanggaran') === 0 ? 'active' : '' ?>">
        <i class="fas fa-exclamation-triangle" style="color:#fbbf24;"></i>
        <span>Jenis Pelanggaran</span>
    </a>
    <?php endif; ?>
    
    <?php if (has_permission(['arsip_view', 'arsip_create', 'arsip_delete', 'export_laporan', 'user_manage', 'periode_aktif_manage', 'reset_poin_manage', 'izin_manage', 'history_manage'])): ?>
    <div class="sb-section-label">SISTEM</div>
    <?php endif; ?>

    <?php if (has_permission(['arsip_view', 'arsip_create', 'arsip_delete'])): ?>
    <a href="<?= BASE_URL ?>/arsip" class="sb-link <?= strpos($req_path, '/arsip') === 0 ? 'active' : '' ?>">
        <i class="fas fa-archive" style="color:#94a3b8;"></i>
        <span>Arsip</span>
    </a>
    <?php endif; ?>

    <?php if (has_permission('export_laporan')): ?>
    <a href="<?= BASE_URL ?>/export" class="sb-link <?= strpos($req_path, '/export') === 0 ? 'active' : '' ?>">
        <i class="fas fa-file-excel" style="color:#10b981;"></i>
        <span>Export Data</span>
    </a>
    <?php endif; ?>
    
    <?php if (has_permission(['user_manage', 'periode_aktif_manage', 'reset_poin_manage', 'izin_manage', 'history_manage'])): ?>
    <a href="<?= BASE_URL ?>/pengaturan" class="sb-link <?= strpos($req_path, '/pengaturan') === 0 ? 'active' : '' ?>">
        <i class="fas fa-cog"></i>
        <span>Pengaturan</span>
    </a>
    <?php endif; ?>
    
</div>