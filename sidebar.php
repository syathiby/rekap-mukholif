<?php
// Pastikan session sudah berjalan
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ambil URL sekarang buat nandain menu aktif
$current_uri = htmlspecialchars($_SERVER['REQUEST_URI']);
?>

<!-- ✅ SATU DAFTAR MENU UNTUK SEMUANYA ✅ -->
<ul class="nav flex-column">

    <!-- Menu Dashboard: Selalu tampil -->
    <li class="nav-item">
        <a class="nav-link apply-color-hover-active color-blue <?= strpos($current_uri, 'index.php') !== false ? 'active' : '' ?>" href="../index.php">
            <i class="fas fa-home me-2"></i>Dashboard
        </a>
    </li>

    <!-- Menu Data Santri -->
    <?php if (has_permission('santri_view')): ?>
    <li class="nav-item">
        <a class="nav-link apply-color-hover-active color-green <?= strpos($current_uri, 'santri') !== false ? 'active' : '' ?>" href="../santri">
            <i class="fas fa-users me-2"></i>Data Santri
        </a>
    </li>
    <?php endif; ?>

    <!-- Menu Jenis Pelanggaran -->
    <?php if (has_permission('jenis_pelanggaran_manage')): ?>
    <li class="nav-item">
        <a class="nav-link apply-color-hover-active color-orange <?= strpos($current_uri, 'jenis-pelanggaran') !== false ? 'active' : '' ?>" href="../jenis-pelanggaran">
            <i class="fas fa-exclamation-triangle me-2"></i>Jenis Pelanggaran
        </a>
    </li>
    <?php endif; ?>
    
    <!-- Menu Catatan Pelanggaran -->
    <?php if (has_permission(['pelanggaran_bahasa_input', 'pelanggaran_diniyyah_input', 'pelanggaran_kesantrian_input', 'pelanggaran_pengabdian_input'])): ?>
    <li class="nav-item">
        <a class="nav-link apply-color-hover-active color-indigo <?= strpos($current_uri, 'pelanggaran') !== false ? 'active' : '' ?>" href="../pelanggaran">
            <i class="fas fa-clipboard-list me-2"></i>Catatan Pelanggaran
        </a>
    </li>
    <?php endif; ?>

    <!-- Menu Eksekusi Kebersihan -->
    <?php if (has_permission('eksekusi_manage')): ?>
    <li class="nav-item">
        <a class="nav-link apply-color-hover-active color-teal <?= strpos($current_uri, 'eksekusi') !== false ? 'active' : '' ?>" href="../eksekusi">
            <i class="fas fa-broom me-2"></i>Eksekusi Kebersihan
        </a>
    </li>
    <?php endif; ?>

    <!-- Menu Arsip -->
    <?php if (has_permission('arsip_manage')): ?>
    <li class="nav-item">
        <a class="nav-link apply-color-hover-active color-purple <?= strpos($current_uri, 'arsip') !== false ? 'active' : '' ?>" href="../arsip">
            <i class="fas fa-archive me-2"></i>Arsip
        </a>
    </li>
    <?php endif; ?>

    <!-- Menu Rekap Pelanggaran -->
    <?php if (has_permission(['rekap_view_umum', 'rekap_view_per_kamar', 'rekap_view_santri', 'rekap_view_statistik'])): ?>
    <li class="nav-item">
        <a class="nav-link apply-color-hover-active color-red <?= strpos($current_uri, 'rekap') !== false ? 'active' : '' ?>" href="../rekap">
            <i class="fas fa-chart-bar me-2"></i>Rekap Pelanggaran
        </a>
    </li>
    <?php endif; ?>
    
    <!-- Menu Pengaturan -->
    <?php if (has_permission('pengaturan_view')): ?>
    <li class="nav-item">
        <a class="nav-link apply-color-hover-active color-gray <?= strpos($current_uri, 'pengaturan') !== false ? 'active' : '' ?>" href="../pengaturan">
            <i class="fas fa-cog me-2"></i>Pengaturan
        </a>
    </li>
    <?php endif; ?>
    
</ul>