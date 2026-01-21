<?php
// =================================================================
// PROTOKOL BARU UNTUK PINTU LOBI UTAMA (DASHBOARD)
// =================================================================
// 1. Panggil 'Otak' aplikasi dulu
require_once __DIR__ . '/init.php';

// 2. Jalankan 'SATPAM' buat ngejaga halaman
guard();

// 3. Kalau lolos, baru panggil Tampilan
require_once __DIR__ . '/header.php';

// =================================================================
// PENGUMPULAN DATA UNTUK DASHBOARD (VERSI FINAL & BENAR)
// =================================================================

$start_date = $_GET['start_date'] ?? null;
$end_date = $_GET['end_date'] ?? null;

$q = mysqli_query($conn, "SELECT nilai FROM pengaturan WHERE nama = 'periode_aktif' LIMIT 1");
$row = mysqli_fetch_assoc($q);
$periode_aktif = $row['nilai'] ?? '2000-01-01';

$start_date_sql = $periode_aktif;
$end_date_sql = date('Y-m-d H:i:s');

if (!empty($start_date)) {
    $start_date_sql = mysqli_real_escape_string($conn, $start_date . ' 00:00:00');
}
if (!empty($end_date)) {
    $end_date_sql = mysqli_real_escape_string($conn, $end_date . ' 23:59:59');
}

// 1. Hitung dulu semua angka yang dibutuhkan
$pelanggaran_umum = (int) (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM pelanggaran WHERE tanggal BETWEEN '$start_date_sql' AND '$end_date_sql'"))['total'] ?? 0);
$pelanggaran_kebersihan = (int) (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM pelanggaran_kebersihan WHERE tanggal BETWEEN '$start_date_sql' AND '$end_date_sql'"))['total'] ?? 0);
$total_santri = (int) (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM santri"))['total'] ?? 0);
$total_jenis_pelanggaran = (int) (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM jenis_pelanggaran"))['total'] ?? 0);

// 2. Baru kumpulkan semua hasil ke dalam satu array `$stats`
$stats = [
    'santri' => $total_santri,
    'jenis_pelanggaran' => $total_jenis_pelanggaran,
    'pelanggaran_umum' => $pelanggaran_umum,
    'pelanggaran_kebersihan' => $pelanggaran_kebersihan,
    'total_pelanggaran' => $pelanggaran_umum + $pelanggaran_kebersihan,
];

$stats['santri_tanpa_pelanggaran'] = (int) (mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(s.id) as total 
    FROM santri s
    WHERE NOT EXISTS (
        SELECT 1 FROM pelanggaran p 
        WHERE p.santri_id = s.id AND p.tanggal BETWEEN '$start_date_sql' AND '$end_date_sql'
    )
"))['total'] ?? 0);

// =============================================================
// QUERY UNTUK TABEL DAN LIST LAINNYA
// =============================================================
// Query ini diubah agar tidak terpengaruh filter tanggal
$recent_violations = mysqli_query($conn, "
    (
        SELECT p.id, s.nama, s.kamar, jp.nama_pelanggaran, p.tanggal, u.nama_lengkap AS pencatat
        FROM pelanggaran p
        JOIN santri s ON p.santri_id = s.id
        JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id
        LEFT JOIN users u ON p.dicatat_oleh = u.id
    )
    UNION ALL
    (
        SELECT pk.id, 'Penghuni Kamar' AS nama, pk.kamar, 'Kebersihan Kamar' AS nama_pelanggaran, pk.tanggal, u.nama_lengkap AS pencatat
        FROM pelanggaran_kebersihan pk
        LEFT JOIN users u ON pk.dicatat_oleh = u.id
    )
    ORDER BY tanggal DESC
    LIMIT 5
");

$frequent_violation_query = mysqli_query($conn, "
    SELECT nama_pelanggaran, SUM(total) AS total_gabungan FROM (
        SELECT jp.nama_pelanggaran, COUNT(*) as total 
        FROM pelanggaran p
        JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id
        WHERE p.tanggal BETWEEN '$start_date_sql' AND '$end_date_sql'
        GROUP BY jp.nama_pelanggaran
        UNION ALL
        SELECT 'Kebersihan Kamar' AS nama_pelanggaran, COUNT(*) AS total
        FROM pelanggaran_kebersihan pk
        WHERE pk.tanggal BETWEEN '$start_date_sql' AND '$end_date_sql'
        HAVING COUNT(*) > 0
    ) AS gabung
    GROUP BY nama_pelanggaran
    ORDER BY total_gabungan DESC
    LIMIT 1
");
$frequent_violation = mysqli_fetch_assoc($frequent_violation_query);

$top_violators = mysqli_query($conn, "
    SELECT s.nama, s.kamar, COUNT(*) as total 
    FROM pelanggaran p
    JOIN santri s ON p.santri_id = s.id
    WHERE p.tanggal BETWEEN '$start_date_sql' AND '$end_date_sql'
    GROUP BY s.id
    ORDER BY total DESC
    LIMIT 5
");

$best_students = mysqli_query($conn, "
    SELECT s.nama, s.kelas, s.kamar 
    FROM santri s
    WHERE NOT EXISTS (
        SELECT 1 FROM pelanggaran p 
        WHERE p.santri_id = s.id AND p.tanggal BETWEEN '$start_date_sql' AND '$end_date_sql'
    )
    ORDER BY s.nama ASC
    LIMIT 5
");

// =============================================================
// PERSIAPAN UNTUK LOGIKA TAMPILAN BERDASARKAN IZIN (REVISI 2)
// =============================================================
// Ini asumsi ada fungsi has_permission() di init.php ya

// Izin Sesuai Arahan Baru:
$can_view_pel_terkini = has_permission('rekap_view_statistik'); // Tombol "Lihat semua" Pelanggaran Terkini
$can_view_rekap_santri = has_permission('rekap_view_santri');   // Tombol "Lihat semua" Top Pelanggar & Santri Teladan

// Card 1: Total Santri
$can_view_santri = has_permission('santri_view');
$santri_href = $can_view_santri ? 'href="santri/index.php"' : 'href="#"';
$santri_style = !$can_view_santri ? 'style="cursor: not-allowed; opacity: 0.7;"' : '';
$santri_onclick = !$can_view_santri ? 'onclick="event.preventDefault(); return false;"' : '';

// Card 2: Jenis Pelanggaran
$can_view_jp = has_permission('jenis_pelanggaran_view');
$jp_href = $can_view_jp ? 'href="jenis-pelanggaran/index.php"' : 'href="#"';
$jp_style = !$can_view_jp ? 'style="cursor: not-allowed; opacity: 0.7;"' : '';
$jp_onclick = !$can_view_jp ? 'onclick="event.preventDefault(); return false;"' : '';

// Card 3: Total Pelanggaran (Chart)
$can_view_chart = has_permission('rekap_view_statistik');
$chart_href = $can_view_chart ? 'href="rekap/chart.php"' : 'href="#"';
$chart_style = !$can_view_chart ? 'style="cursor: not-allowed; opacity: 0.7;"' : '';
$chart_onclick = !$can_view_chart ? 'onclick="event.preventDefault(); return false;"' : '';

// Card 4: Santri Teladan (Izin: rekap_view_santri)
$teladan_href = $can_view_rekap_santri ? 'href="rekap/santri-teladan.php"' : 'href="#"';
$teladan_style = !$can_view_rekap_santri ? 'style="cursor: not-allowed; opacity: 0.7;"' : '';
$teladan_onclick = !$can_view_rekap_santri ? 'onclick="event.preventDefault(); return false;"' : '';

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Aplikasi Pendataan Mukholif</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

</head>
<body>
    <div class="container">
        <header class="dashboard-header">
            <div class="header-shield-bg"><i class="fas fa-shield-alt"></i></div>
            <h1><i class="fas fa-chart-pie"></i> Aplikasi Kepengasuhan Santri</h1>
            <p class="subtitle">Pantau dan kelola data pelanggaran santri dengan mudah</p>
        </header>
        
        <div class="content-section">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-history"></i> Pelanggaran Terkini</h2>
                <?php if ($can_view_pel_terkini): // Izin: rekap_view_statistik ?>
                    <a href="rekap/tren-pelanggaran.php" class="view-all-link">Lihat semua <i class="fas fa-chevron-right"></i></a>
                <?php endif; ?>
            </div>
            <div class="recent-violations">
                <table class="violation-table">
                    <thead>
                        <tr>
                            <th style="width: 5%;">No</th>
                            <th style="width: 20%;">Nama Santri</th>
                            <th style="width: 10%;">Kamar</th>
                            <th style="width: 35%;">Pelanggaran</th>
                            <th style="width: 15%;">Waktu</th>
                            <th style="width: 15%;">Pencatat</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(mysqli_num_rows($recent_violations) > 0): ?>
                            <?php $no = 1; while($violation = mysqli_fetch_assoc($recent_violations)): $time_ago = time_elapsed_string($violation['tanggal']); ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td><?= htmlspecialchars($violation['nama']) ?></td>
                                    <td><?= htmlspecialchars($violation['kamar']) ?></td>
                                    <td><?= htmlspecialchars($violation['nama_pelanggaran']) ?></td>
                                    <td class="violation-time">
                                        <?= date('d M Y H:i', strtotime($violation['tanggal'])) ?>
                                        <span class="time-ago"><?= $time_ago ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($violation['pencatat'] ?? 'N/A') ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="empty-state"><i class="fas fa-check-circle"></i><p>Alhamdulillah, tidak ada pelanggaran baru-baru ini.</p></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="stats-grid">
            <!-- Card 1: Total Santri (Izin: santri_view) -->
            <a <?= $santri_href ?> class="stat-card santri" <?= $santri_style ?> <?= $santri_onclick ?>>
                <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
                <h3>Total Santri</h3>
                <div class="stat-value"><?= number_format($stats['santri'] ?? 0) ?></div>
                <p class="stat-description">Santri terdaftar</p>
            </a>
            
            <!-- Card 2: Jenis Pelanggaran (Izin: jenis_pelanggaran_view) -->
            <a <?= $jp_href ?> class="stat-card pelanggaran" <?= $jp_style ?> <?= $jp_onclick ?>>
                <div class="stat-icon"><i class="fas fa-clipboard-check"></i></div>
                <h3>Jenis Pelanggaran</h3>
                <div class="stat-value"><?= number_format($stats['jenis_pelanggaran'] ?? 0) ?></div>
                <p class="stat-description">Kategori pelanggaran</p>
            </a>
            
            <!-- Card 3: Total Pelanggaran (Izin: rekap_view_statistik) -->
            <a <?= $chart_href ?> class="stat-card violations" <?= $chart_style ?> <?= $chart_onclick ?>>
                <div class="stat-icon"><i class="fas fa-exclamation-circle"></i></div>
                <h3>Total Pelanggaran</h3>
                <div class="stat-value"><?= number_format($stats['total_pelanggaran'] ?? 0) ?></div>
                <p class="stat-description">Pelanggaran tercatat</p>
                <?php if(!empty($frequent_violation)): ?>
                    <div class="additional-info">
                        <i class="fas fa-fire" style="color: var(--warning);"></i> Paling sering: <?= htmlspecialchars($frequent_violation['nama_pelanggaran']) ?>
                    </div>
                <?php endif; ?>
            </a>
            
            <!-- Card 4: Santri Teladan (Izin: rekap_view_santri) -->
            <a <?= $teladan_href ?> class="stat-card clean" <?= $teladan_style ?> <?= $teladan_onclick ?>>
                <div class="stat-icon"><i class="fas fa-award"></i></div>
                <h3>Santri Teladan</h3>
                <div class="stat-value"><?= number_format($stats['santri_tanpa_pelanggaran'] ?? 0) ?></div>
                <p class="stat-description">Tanpa pelanggaran</p>
            </a>
        </div>
        
        <form method="GET" class="filter-form">
            <div>
                <label for="start_date"><i class="fas fa-calendar-day"></i> Dari Tanggal:</label>
                <input type="date" name="start_date" id="start_date" value="<?= htmlspecialchars($_GET['start_date'] ?? '') ?>">
            </div>
            <div>
                <label for="end_date"><i class="fas fa-calendar-week"></i> Sampai Tanggal:</label>
                <input type="date" name="end_date" id="end_date" value="<?= htmlspecialchars($_GET['end_date'] ?? '') ?>">
            </div>
            <div style="align-self: flex-end;">
                <button type="submit"><i class="fas fa-filter"></i> Filter</button>
            </div>
        </form>
        
        <div class="content-section">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-star"></i> Sorotan Santri</h2>
            </div>
            <div class="student-list">
                <div class="student-list-card">
                    <div class="list-header">
                        <h3 class="list-title"><i class="fas fa-exclamation-triangle" style="color: var(--danger)"></i> Top Pelanggar Hirosah</h3>
                        <?php if ($can_view_rekap_santri): // Izin: rekap_view_santri ?>
                            <a href="rekap/santri-pelanggar.php" class="view-all-link">Lihat semua <i class="fas fa-chevron-right"></i></a>
                        <?php endif; ?>
                    </div>
                    <?php if(mysqli_num_rows($top_violators) > 0): ?>
                        <?php while($violator = mysqli_fetch_assoc($top_violators)): ?>
                            <div class="student-item">
                                <div class="student-avatar top-violators"><?= htmlspecialchars(substr($violator['nama'], 0, 1)) ?></div>
                                <div class="student-info">
                                    <div class="student-name"><?= htmlspecialchars($violator['nama']) ?></div>
                                    <div class="student-details"><span><i class="fas fa-home"></i> Kamar <?= htmlspecialchars($violator['kamar']) ?></span></div>
                                </div>
                                <div class="violation-count"><?= $violator['total'] ?></div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state"><i class="fas fa-info-circle"></i><p>Tidak ada data pelanggar</p></div>
                    <?php endif; ?>
                </div>
                <div class="student-list-card">
                    <div class="list-header">
                        <h3 class="list-title"><i class="fas fa-medal" style="color: var(--success)"></i> Daftar Santri Teladan</h3>
                        <?php if ($can_view_rekap_santri): // Izin: rekap_view_santri ?>
                            <a href="rekap/santri-teladan.php" class="view-all-link">Lihat semua <i class="fas fa-chevron-right"></i></a>
                        <?php endif; ?>
                    </div>
                    <?php if(mysqli_num_rows($best_students) > 0): ?>
                        <?php while($student = mysqli_fetch_assoc($best_students)): ?>
                            <div class="student-item">
                                <div class="student-avatar best-students"><?= htmlspecialchars(substr($student['nama'], 0, 1)) ?></div>
                                <div class="student-info">
                                    <div class="student-name"><?= htmlspecialchars($student['nama']) ?></div>
                                    <div class="student-details">
                                        <span><i class="fas fa-home"></i> Kamar <?= htmlspecialchars($student['kamar']) ?></span>
                                        <span><i class="fas fa-graduation-cap"></i> <?= htmlspecialchars($student['kelas']) ?></span>
                                    </div>
                                </div>
                                <div class="violation-count zero"><i class="fas fa-check"></i></div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state"><i class="fas fa-info-circle"></i><p>Belum ada santri tanpa pelanggaran</p></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>
    
    <?php
    function time_elapsed_string($datetime, $full = false) {
        $now = new DateTime;
        $ago = new DateTime($datetime);
        $diff = $now->diff($ago);
        $diff->w = floor($diff->d / 7);
        $diff->d -= $diff->w * 7;
        $string = [ 'y' => 'tahun', 'm' => 'bulan', 'w' => 'minggu', 'd' => 'hari', 'h' => 'jam', 'i' => 'menit', 's' => 'detik' ];
        foreach ($string as $k => &$v) {
            if ($diff->$k) $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? '' : ''); else unset($string[$k]);
        }
        if (!$full) $string = array_slice($string, 0, 1);
        return $string ? implode(', ', $string) . ' yang lalu' : 'baru saja';
    }
    ?>
</body>
</html>