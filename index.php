<?php
// =================================================================
// PROTOKOL BARU UNTUK PINTU LOBI UTAMA (DASHBOARD)
// =================================================================
// 1. Panggil 'Otak' aplikasi dulu
require_once __DIR__ . '/bootstrap/init.php';

// 2. Jalankan 'SATPAM' buat ngejaga halaman
guard();

// 3. Kalau lolos, baru panggil Tampilan
require_once __DIR__ . '/layouts/header.php';

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
    LIMIT 7
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
    SELECT s.nama, s.kamar, s.poin_aktif, SUM(jp.poin) as total_poin 
    FROM pelanggaran p
    JOIN santri s ON p.santri_id = s.id
    JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id
    WHERE p.tanggal BETWEEN '$start_date_sql' AND '$end_date_sql'
    GROUP BY s.id
    ORDER BY total_poin DESC, s.nama ASC
    LIMIT 5
");


$best_students = mysqli_query($conn, "
    SELECT s.id, s.nama, s.kelas, s.kamar,
           COALESCE(rwd.total_reward, 0) AS total_reward,
           COALESCE(rpt.avg_rapot, 0) AS avg_rapot
    FROM santri s
    LEFT JOIN (
        SELECT dr.santri_id, SUM(jr.poin_reward) AS total_reward
        FROM daftar_reward dr
        JOIN jenis_reward jr ON dr.jenis_reward_id = jr.id
        WHERE dr.tanggal BETWEEN '$start_date_sql' AND '$end_date_sql'
        GROUP BY dr.santri_id
    ) rwd ON s.id = rwd.santri_id
    LEFT JOIN (
        SELECT santri_id, 
               ((AVG(puasa_sunnah) + AVG(sholat_duha) + AVG(sholat_malam) + AVG(sedekah) + AVG(sunnah_tidur) + AVG(ibadah_lainnya) + 
                 AVG(lisan) + AVG(sikap) + AVG(kesopanan) + AVG(muamalah) + 
                 AVG(tidur) + AVG(keterlambatan) + AVG(seragam) + AVG(makan) + AVG(arahan) + AVG(bahasa_arab) + 
                 AVG(mandi) + AVG(penampilan) + AVG(piket) + AVG(kerapihan_barang)) / 20) AS avg_rapot
        FROM rapot_kepengasuhan
        WHERE STR_TO_DATE(CONCAT(tahun, '-', FIELD(bulan, 'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'), '-01'), '%Y-%c-%d') 
              BETWEEN STR_TO_DATE(CONCAT(DATE_FORMAT('$start_date_sql', '%Y-%m'), '-01'), '%Y-%m-%d') 
              AND LAST_DAY('$end_date_sql')
        GROUP BY santri_id
    ) rpt ON s.id = rpt.santri_id
    WHERE s.id NOT IN (
        SELECT p.santri_id FROM pelanggaran p WHERE p.tanggal BETWEEN '$start_date_sql' AND '$end_date_sql'
    )
    ORDER BY total_reward DESC, avg_rapot DESC, CAST(s.kamar AS UNSIGNED) ASC, s.nama ASC
    LIMIT 5
");


// =============================================================
// PERSIAPAN UNTUK LOGIKA TAMPILAN BERDASARKAN IZIN (REVISI 2)
// =============================================================
// Ini asumsi ada fungsi has_permission() di init.php ya

// Izin Sesuai Arahan Baru:
$can_view_pel_terkini = has_permission('rekap_view_statistik'); // Tombol "Lihat semua" Pelanggaran Terkini
$can_view_santri_teladan = has_permission('rekap_santri_teladan');   // Tombol "Lihat semua" Santri Teladan
$can_view_top_pelanggar = has_permission('rekap_pelanggaran_umum');  // Tombol "Lihat semua" Top Pelanggar

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

// Card 4: Santri Teladan (Izin: rekap_santri_teladan)
$teladan_href = $can_view_santri_teladan ? 'href="rekap/santri_teladan.php"' : 'href="#"';
$teladan_style = !$can_view_santri_teladan ? 'style="cursor: not-allowed; opacity: 0.7;"' : '';
$teladan_onclick = !$can_view_santri_teladan ? 'onclick="event.preventDefault(); return false;"' : '';

// --- CSS KHUSUS UNTUK RANKING ---
?>
<style>
.rank-badge-sm {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 14px;
    border-radius: 50%;
    margin-left: auto;
}
.rank-badge-sm.rank-1 { background: linear-gradient(135deg, #fbbf24, #d97706); color: white; box-shadow: 0 3px 8px rgba(217, 119, 6, 0.3); }
.rank-badge-sm.rank-2 { background: linear-gradient(135deg, #cbd5e1, #64748b); color: white; box-shadow: 0 3px 8px rgba(100, 116, 139, 0.3); }
.rank-badge-sm.rank-3 { background: linear-gradient(135deg, #fca5a5, #b91c1c); color: white; box-shadow: 0 3px 8px rgba(185, 28, 28, 0.3); }
.rank-badge-sm.rank-other { background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0; }

.mini-stats {
    display: flex;
    gap: 12px;
    margin-top: 4px;
}
.mini-stat {
    font-size: 11px;
    font-weight: 600;
    color: #64748b;
    display: flex;
    align-items: center;
    gap: 4px;
}
.mini-stat span {
    font-weight: 800;
}
</style>

    <!-- Banner utama dihapus, form filter dirapikan -->
    <div class="dashboard-wrapper">
        
        <div class="row g-4 mb-4">
            <!-- Card 1: Total Santri -->
            <div class="col-xl-3 col-md-6">
                <div class="card h-100 shadow-sm border-0 rounded-4 overflow-hidden position-relative" style="background: linear-gradient(145deg, #ffffff, #f8fafc);">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h6 class="text-muted fw-semibold mb-1">Total Santri</h6>
                                <h3 class="fw-bold mb-0 text-dark" style="font-size: 2.2rem; letter-spacing:-0.5px;"><?= number_format($stats['santri'] ?? 0) ?></h3>
                            </div>
                            <div class="d-flex align-items-center justify-content-center rounded-3" style="width: 48px; height: 48px; background: rgba(16, 185, 129, 0.1); color: #10b981;">
                                <i class="fas fa-user-graduate fa-lg"></i>
                            </div>
                        </div>
                        <div class="text-muted small mt-2 d-flex justify-content-between align-items-center">
                            <span>Santri aktif terdaftar</span>
                            <?php if ($can_view_santri): ?>
                                <a href="santri/index.php" class="text-success text-decoration-none fw-semibold stretched-link" style="font-size:0.8rem;">Lihat <i class="fas fa-arrow-right ms-1"></i></a>
                            <?php else: ?>
                                <i class="fas fa-lock opacity-50"></i>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Card 2: Jenis Pelanggaran -->
            <div class="col-xl-3 col-md-6">
                <div class="card h-100 shadow-sm border-0 rounded-4 overflow-hidden position-relative" style="background: linear-gradient(145deg, #ffffff, #fffbeb);">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h6 class="text-muted fw-semibold mb-1">Kategori Aturan</h6>
                                <h3 class="fw-bold mb-0 text-dark" style="font-size: 2.2rem; letter-spacing:-0.5px;"><?= number_format($stats['jenis_pelanggaran'] ?? 0) ?></h3>
                            </div>
                            <div class="d-flex align-items-center justify-content-center rounded-3" style="width: 48px; height: 48px; background: rgba(245, 158, 11, 0.1); color: #f59e0b;">
                                <i class="fas fa-clipboard-list fa-lg"></i>
                            </div>
                        </div>
                        <div class="text-muted small mt-2 d-flex justify-content-between align-items-center">
                            <span>Macam-macam aturan</span>
                            <?php if ($can_view_jp): ?>
                                <a href="jenis-pelanggaran/index.php" class="text-warning text-decoration-none fw-semibold stretched-link" style="font-size:0.8rem;">Kelola <i class="fas fa-arrow-right ms-1"></i></a>
                            <?php else: ?>
                                <i class="fas fa-lock opacity-50"></i>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Card 3: Total Pelanggaran -->
            <div class="col-xl-3 col-md-6">
                <div class="card h-100 shadow-sm border-0 rounded-4 overflow-hidden position-relative" style="background: linear-gradient(145deg, #ffffff, #fef2f2);">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h6 class="text-muted fw-semibold mb-1">Pelanggaran Tercatat</h6>
                                <h3 class="fw-bold mb-0 text-dark" style="font-size: 2.2rem; letter-spacing:-0.5px;"><?= number_format($stats['total_pelanggaran'] ?? 0) ?></h3>
                            </div>
                            <div class="d-flex align-items-center justify-content-center rounded-3" style="width: 48px; height: 48px; background: rgba(239, 68, 68, 0.1); color: #ef4444;">
                                <i class="fas fa-exclamation-circle fa-lg"></i>
                            </div>
                        </div>
                        
                        <div class="text-muted small mt-2 d-flex justify-content-between align-items-end">
                            <div>
                                Total kasus tercatat
                                <?php if(!empty($frequent_violation)): ?>
                                    <br><span class="text-danger fw-medium d-inline-block mt-1" style="font-size: 0.75rem;"><i class="fas fa-fire me-1"></i>Sering: <?= htmlspecialchars($frequent_violation['nama_pelanggaran']) ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if ($can_view_chart): ?>
                                <a href="rekap/chart.php" class="text-danger text-decoration-none fw-semibold stretched-link" style="font-size:0.8rem;">Statistik <i class="fas fa-arrow-right ms-1"></i></a>
                            <?php else: ?>
                                <i class="fas fa-lock opacity-50 mb-1"></i>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Card 4: Santri Teladan -->
            <div class="col-xl-3 col-md-6">
                <div class="card h-100 shadow-sm border-0 rounded-4 overflow-hidden position-relative" style="background: linear-gradient(145deg, #ffffff, #f0fdf4);">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h6 class="text-muted fw-semibold mb-1">Santri Prestasi</h6>
                                <h3 class="fw-bold mb-0 text-dark" style="font-size: 2.2rem; letter-spacing:-0.5px;"><?= number_format($stats['santri_tanpa_pelanggaran'] ?? 0) ?></h3>
                            </div>
                            <div class="d-flex align-items-center justify-content-center rounded-3" style="width: 48px; height: 48px; background: rgba(59, 130, 246, 0.1); color: #3b82f6;">
                                <i class="fas fa-award fa-lg"></i>
                            </div>
                        </div>
                        <div class="text-muted small mt-2 d-flex justify-content-between align-items-center">
                            <span>Tanpa catatan kasus</span>
                            <?php if ($can_view_santri_teladan): ?>
                                <a href="rekap/santri_teladan.php" class="text-primary text-decoration-none fw-semibold stretched-link" style="font-size:0.8rem;">Daftar <i class="fas fa-arrow-right ms-1"></i></a>
                            <?php else: ?>
                                <i class="fas fa-lock opacity-50"></i>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-xl-8 col-lg-7">
                <!-- Pelanggaran Terkini -->
                <div class="card-premium h-100">
                    <div class="card-header-premium">
                        <h2 class="card-title"><i class="fas fa-history text-primary"></i> Pelanggaran Terkini</h2>
                        <?php if ($can_view_pel_terkini): ?>
                            <a href="rekap/tren_pelanggaran.php" class="btn btn-sm btn-light border">Lihat semua <i class="fas fa-chevron-right ms-1"></i></a>
                        <?php endif; ?>
                    </div>
                    
                    <div class="p-0">
                        <div class="table-responsive d-none d-md-block">
                            <table class="table-premium w-100 mb-0">
                                <thead>
                                    <tr>
                                        <th style="width: 5%;">No</th>
                                        <th style="width: 25%;">Nama Santri</th>
                                        <th style="width: 30%;">Pelanggaran</th>
                                        <th style="width: 20%;">Waktu</th>
                                        <th style="width: 20%;">Pencatat</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(mysqli_num_rows($recent_violations) > 0): ?>
                                        <?php $no = 1; while($violation = mysqli_fetch_assoc($recent_violations)): $time_ago = time_elapsed_string($violation['tanggal']); ?>
                                            <tr>
                                                <td><?= $no++ ?></td>
                                                <td>
                                                    <div class="fw-medium text-dark"><?= htmlspecialchars($violation['nama']) ?></div>
                                                    <div class="text-muted small"><i class="fas fa-home"></i> Km. <?= htmlspecialchars($violation['kamar']) ?></div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-light text-dark border text-wrap text-start">
                                                        <?= htmlspecialchars($violation['nama_pelanggaran']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="text-dark small"><?= date('d M Y H:i', strtotime($violation['tanggal'])) ?></div>
                                                    <div class="text-muted text-xs"><?= $time_ago ?></div>
                                                </td>
                                                <td><span class="text-muted small"><i class="fas fa-user-edit"></i> <?= htmlspecialchars($violation['pencatat'] ?? 'N/A') ?></span></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr><td colspan="5" class="text-center py-5 text-muted"><i class="fas fa-check-circle fs-2 text-success mb-2 d-block"></i> Alhamdulillah, tidak ada pelanggaran baru-baru ini.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Mobile Cards -->
                        <div class="d-md-none p-3">
                            <?php if(mysqli_num_rows($recent_violations) > 0): ?>
                                <?php mysqli_data_seek($recent_violations, 0); // Reset pointer ?>
                                <div class="mobile-violations-list">
                                    <?php while($violation = mysqli_fetch_assoc($recent_violations)): 
                                        $time_ago = time_elapsed_string($violation['tanggal']);
                                        $initial = htmlspecialchars(substr($violation['nama'] ?? 'S', 0, 1));
                                        
                                        $avatar_bg = '#f1f5f9';
                                        $avatar_color = '#475569';
                                        $pel_nama = strtolower($violation['nama_pelanggaran'] ?? '');
                                        if (strpos($pel_nama, 'bahasa') !== false) { $avatar_bg = '#eef2ff'; $avatar_color = '#4f46e5'; }
                                        elseif (strpos($pel_nama, 'diniyyah') !== false) { $avatar_bg = '#ecfdf5'; $avatar_color = '#10b981'; }
                                        elseif (strpos($pel_nama, 'tahfidz') !== false) { $avatar_bg = '#fff1f2'; $avatar_color = '#f43f5e'; }
                                        elseif (strpos($pel_nama, 'kebersihan') !== false) { $avatar_bg = '#f0fdfa'; $avatar_color = '#0d9488'; }
                                    ?>
                                        <div class="mobile-violation-card mb-3 p-3 border rounded shadow-sm bg-white">
                                            <div class="mobile-violation-avatar rounded-circle d-flex align-items-center justify-content-center fw-bold" style="background-color: <?= $avatar_bg ?>; color: <?= $avatar_color ?>;">
                                                <?= $initial ?>
                                            </div>
                                            <div class="mobile-violation-content flex-grow-1">
                                                <div class="d-flex justify-content-between align-items-start mb-1">
                                                    <div class="fw-bold text-dark text-break pe-2"><?= htmlspecialchars($violation['nama'] ?? 'Penghuni Kamar') ?></div>
                                                    <div class="text-muted text-xs text-end flex-shrink-0"><?= $time_ago ?></div>
                                                </div>
                                                <div class="mobile-violation-title fw-medium text-danger mb-2">
                                                    <?= htmlspecialchars($violation['nama_pelanggaran'] ?? '') ?>
                                                </div>
                                                <div class="d-flex flex-wrap gap-3 text-muted small">
                                                    <span><i class="fas fa-home"></i> Km. <?= htmlspecialchars($violation['kamar'] ?? '') ?></span>
                                                    <span><i class="fas fa-user-edit"></i> <?= htmlspecialchars($violation['pencatat'] ?? 'N/A') ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4 text-muted border rounded bg-light">
                                    <i class="fas fa-check-circle text-success fs-1 mb-2"></i>
                                    <p class="mb-0">Alhamdulillah, tidak ada pelanggaran baru-baru ini.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4 col-lg-5">
                <!-- Sorotan Santri -->
                <div class="card-premium h-100">
                    <div class="card-header-premium">
                        <h2 class="card-title"><i class="fas fa-star text-warning"></i> Sorotan Santri</h2>
                    </div>
                    <div class="card-body p-3">
                        <ul class="nav nav-pills custom-student-tabs w-100 mb-3" id="studentTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active w-100" id="violators-tab" data-bs-toggle="tab" data-bs-target="#violators-panel" type="button" role="tab">
                                    <i class="fas fa-exclamation-triangle"></i> Top Pelanggar
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link w-100" id="teladan-tab" data-bs-toggle="tab" data-bs-target="#teladan-panel" type="button" role="tab">
                                    <i class="fas fa-medal"></i> Santri Teladan
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content" id="studentTabsContent">
                            <div class="tab-pane fade show active" id="violators-panel" role="tabpanel">
                                <?php if ($can_view_top_pelanggar): ?>
                                    <div class="text-end mb-3"><a href="rekap/pelanggaran_umum.php" class="text-primary text-decoration-none small fw-medium">Lihat semua <i class="fas fa-arrow-right"></i></a></div>
                                <?php endif; ?>
                                
                                <div class="student-list d-flex flex-column gap-3">
                                    <?php if(mysqli_num_rows($top_violators) > 0): ?>
                                        <?php while($violator = mysqli_fetch_assoc($top_violators)): ?>
                                            <div class="student-item d-flex align-items-center p-3 border rounded bg-white shadow-sm">
                                                <div class="student-avatar rounded-circle text-white d-flex align-items-center justify-content-center me-3 fw-bold" style="background: linear-gradient(135deg, #ef4444, #b91c1c); box-shadow: 0 3px 8px rgba(239,68,68,0.2);">
                                                    <?= htmlspecialchars(substr($violator['nama'], 0, 1)) ?>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="fw-bold text-dark text-truncate" style="max-width: 180px;"><?= htmlspecialchars($violator['nama']) ?></div>
                                                    <div class="text-muted" style="font-size: 11px;">
                                                        <span class="me-2"><i class="fas fa-home opacity-75"></i> Km. <?= htmlspecialchars($violator['kamar']) ?></span>
                                                    </div>
                                                    <div class="mt-1">
                                                        <span class="badge bg-light text-secondary border px-2 py-1" style="font-size: 10px;">
                                                            Poin Aktif: <strong class="text-dark"><?= max(0, (int)$violator['poin_aktif']) ?></strong>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="badge text-white rounded-pill px-3 py-2 fw-bold" style="background-color: #ef4444; font-size: 14px;">
                                                    <?= (int)$violator['total_poin'] ?>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    <?php else: ?>

                                        <div class="text-center text-muted py-4"><i class="fas fa-info-circle fs-2 mb-2"></i><p>Tidak ada data pelanggar</p></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="tab-pane fade" id="teladan-panel" role="tabpanel">
                                <?php if ($can_view_santri_teladan): ?>
                                    <div class="text-end mb-3"><a href="rekap/santri_teladan.php" class="text-primary text-decoration-none small fw-medium">Lihat semua <i class="fas fa-arrow-right"></i></a></div>
                                <?php endif; ?>
                                
                                <div class="student-list d-flex flex-column gap-3">
                                    <?php if(mysqli_num_rows($best_students) > 0): ?>
                                        <?php $no = 1; while($student = mysqli_fetch_assoc($best_students)): 
                                            // Menentukan warna rank
                                            if ($no === 1) $rank_class = 'rank-1';
                                            elseif ($no === 2) $rank_class = 'rank-2';
                                            elseif ($no === 3) $rank_class = 'rank-3';
                                            else $rank_class = 'rank-other';
                                            
                                            $rapot = round((float)$student['avg_rapot'], 1);
                                            $str_rapot = ($rapot > 0) ? number_format($rapot, 1, '.', '') : '-';
                                        ?>
                                            <div class="student-item d-flex align-items-center p-3 border rounded bg-white shadow-sm position-relative">
                                                <div class="student-avatar rounded-circle text-white d-flex align-items-center justify-content-center me-3 fw-bold" style="background: linear-gradient(135deg, #10b981, #059669); box-shadow: 0 3px 8px rgba(16,185,129,0.2);">
                                                    <?= htmlspecialchars(substr($student['nama'], 0, 1)) ?>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="fw-bold text-dark text-truncate" style="max-width: 180px;"><?= htmlspecialchars($student['nama']) ?></div>
                                                    <div class="text-muted" style="font-size: 11px;">
                                                        <span class="me-2"><i class="fas fa-home opacity-75"></i> Km. <?= htmlspecialchars($student['kamar']) ?></span>
                                                        <span><i class="fas fa-graduation-cap opacity-75"></i> Kls <?= htmlspecialchars($student['kelas']) ?></span>
                                                    </div>
                                                    <div class="mini-stats">
                                                        <div class="mini-stat text-success"><i class="fas fa-plus-circle"></i> <span style="font-size: 12px;"> <?= (int)$student['total_reward'] ?></span></div>
                                                        <div class="mini-stat text-primary"><i class="fas fa-star"></i> <span style="font-size: 12px;"> <?= $str_rapot ?></span></div>
                                                    </div>
                                                </div>
                                                <div class="rank-badge-sm <?= $rank_class ?>"><?= $no ?></div>
                                            </div>
                                        <?php $no++; endwhile; ?>
                                    <?php else: ?>

                                        <div class="text-center text-muted py-4"><i class="fas fa-info-circle fs-2 mb-2"></i><p>Belum ada santri tanpa pelanggaran</p></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/layouts/footer.php'; ?>
    
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
