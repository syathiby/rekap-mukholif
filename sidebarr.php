<?php
// --- Sidebar Menu ---
// Sanitasi URL biar aman
$current_file = htmlspecialchars(basename($_SERVER['PHP_SELF']));
$current_uri  = htmlspecialchars($_SERVER['REQUEST_URI']);
?>

<ul class="nav flex-column mb-auto">
    <li class="nav-item">
        <a class="nav-link <?= $current_file == 'index.php' ? 'active' : '' ?>" href="/rekap-mukholif/index.php">
            <i class="fas fa-home me-2"></i>Dashboard
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= strpos($current_uri, 'santri') !== false ? 'active' : '' ?>" href="/rekap-mukholif/santri/">
            <i class="fas fa-users me-2"></i>Data Santri
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= strpos($current_uri, 'jenis-pelanggaran') !== false ? 'active' : '' ?>" href="/rekap-mukholif/jenis-pelanggaran/">
            <i class="fas fa-exclamation-triangle me-2"></i>Jenis Pelanggaran
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= strpos($current_uri, 'pelanggaran') !== false ? 'active' : '' ?>" href="/rekap-mukholif/pelanggaran/">
            <i class="fas fa-clipboard-list me-2"></i>Catatan Pelanggaran
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= strpos($current_uri, 'rekap') !== false ? 'active' : '' ?>" href="/rekap-mukholif/rekap/">
            <i class="fas fa-chart-bar me-2"></i>Rekap Pelanggaran
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= strpos($current_uri, 'eksekusi') !== false ? 'active' : '' ?>" href="/rekap-mukholif/eksekusi/">
            <i class="fas fa-broom me-2"></i>Eksekusi Kebersihan
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= strpos($current_uri, 'arsip') !== false ? 'active' : '' ?>" href="/rekap-mukholif/arsip/">
            <i class="fas fa-archive me-2"></i>Arsip
        </a>
    </li>
</ul>

<!-- Menu bawah -->
<ul class="nav flex-column mt-auto mb-3">
    <li class="nav-item">
        <a class="nav-link <?= strpos($current_uri, 'pengaturan') !== false ? 'active' : '' ?>" href="/rekap-mukholif/pengaturan/">
            <i class="fas fa-cog me-2"></i>Pengaturan
        </a>
    </li>
</ul>