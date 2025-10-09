<?php
// =================================================================
// PROTOKOL BARU UNTUK PINTU LOBI UTAMA (DASHBOARD)
// =================================================================
require_once __DIR__ . '/header.php';
guard();

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
$recent_violations = mysqli_query($conn, "
    (
        SELECT p.id, s.nama, s.kamar, jp.nama_pelanggaran, p.tanggal, u.nama_lengkap AS pencatat
        FROM pelanggaran p
        JOIN santri s ON p.santri_id = s.id
        JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id
        LEFT JOIN users u ON p.dicatat_oleh = u.id
        WHERE p.tanggal BETWEEN '$start_date_sql' AND '$end_date_sql'
    )
    UNION ALL
    (
        SELECT pk.id, 'Penghuni Kamar' AS nama, pk.kamar, 'Kebersihan Kamar' AS nama_pelanggaran, pk.tanggal, u.nama_lengkap AS pencatat
        FROM pelanggaran_kebersihan pk
        LEFT JOIN users u ON pk.dicatat_oleh = u.id
        WHERE pk.tanggal BETWEEN '$start_date_sql' AND '$end_date_sql'
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
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Aplikasi Pendataan Mukholif</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4F46E5; --secondary: #7C3AED; --accent: #10B981;
            --light: #F9FAFB; --dark: #1F2937; --dark-light: #6B7280;
            --success: #2ecc71; --warning: #f39c12; --danger: #e74c3c;
            --info: #3498db;
            --gradient-main: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Poppins', sans-serif; background-color: var(--light);
            color: var(--dark); line-height: 1.6; min-height: 100vh;
        }
        .container { max-width: 1400px; margin: 0 auto; padding: 2rem; }
        .dashboard-header {
            margin-bottom: 2rem; text-align: center; position: relative;
            overflow: hidden; padding: 3rem 2rem; border-radius: 24px;
            background: var(--gradient-main); color: white;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
        }
        .dashboard-header::before, .dashboard-header::after {
            content: ''; position: absolute; border-radius: 50%;
            background: rgba(255,255,255,0.05); filter: blur(50px);
        }
        .dashboard-header::before { top: -100px; left: -100px; width: 300px; height: 300px; }
        .dashboard-header::after { bottom: -120px; right: -80px; width: 350px; height: 350px; }
        .header-shield-bg { position: absolute; top: 20px; right: 20px; font-size: 8rem; color: rgba(255, 255, 255, 0.15); z-index: 0; }
        .dashboard-header h1 { font-size: 2.8rem; margin-bottom: 0.75rem; font-weight: 700; position: relative; z-index: 1; text-shadow: 0 2px 5px rgba(0,0,0,0.2); }
        .dashboard-header .subtitle { color: rgba(255,255,255,0.9); font-size: 1.15rem; position: relative; z-index: 1; max-width: 600px; margin: 0 auto; font-weight: 400; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; margin: 2rem 0; }
        .stat-card {
            background: white; border-radius: 16px; padding: 2rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease-in-out; position: relative; overflow: hidden;
            border: 1px solid #e5e7eb; text-decoration: none; color: inherit; display: block;
        }
        .stat-card:hover { transform: translateY(-8px) scale(1.02); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1); }
        .stat-icon {
            font-size: 2.5rem; margin-bottom: 1.25rem; transition: transform 0.3s ease;
            display: inline-flex; align-items: center; justify-content: center;
            width: 64px; height: 64px; border-radius: 50%;
        }
        .stat-card.santri .stat-icon { color: var(--success); background: rgba(46, 204, 113, 0.1); }
        .stat-card.pelanggaran .stat-icon { color: var(--warning); background: rgba(243, 156, 18, 0.1); }
        .stat-card.violations .stat-icon { color: var(--danger); background: rgba(231, 76, 60, 0.1); }
        .stat-card.clean .stat-icon { color: var(--info); background: rgba(52, 152, 219, 0.1); }
        .stat-card h3 { font-size: 1.25rem; margin-bottom: 0.5rem; color: var(--dark); font-weight: 600; }
        .stat-value { font-size: 2.8rem; font-weight: 700; color: var(--dark); margin-bottom: 0.25rem; letter-spacing: -1px; line-height: 1; }
        .stat-description { color: var(--dark-light); font-size: 0.95rem; }
        .stat-card .additional-info {
            font-size: 0.85rem; margin-top: 0.75rem; color: var(--dark-light);
            font-style: italic; padding-top: 0.75rem; border-top: 1px dashed #e2e8f0;
        }
        .content-section { margin-top: 3rem; }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; border-bottom: 1px solid #e5e7eb; padding-bottom: 1rem; }
        .section-title { font-size: 1.7rem; color: var(--dark); font-weight: 700; display: flex; align-items: center; gap: 0.75rem; }
        .section-title i { color: var(--primary); }
        .student-list { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; }
        .student-list-card {
            background: white; border-radius: 16px; padding: 1.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease; border: 1px solid #e5e7eb;
        }
        .student-list-card:hover { transform: translateY(-5px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.08), 0 4px 6px -4px rgba(0, 0, 0, 0.08); }
        .list-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem; padding-bottom: 0.75rem; border-bottom: 1px solid #f1f5f9; }
        .list-title { font-size: 1.2rem; font-weight: 600; color: var(--dark); display: flex; align-items: center; gap: 0.5rem; }
        .list-title i { font-size: 1.1rem; }
        .student-item { display: flex; align-items: center; padding: 0.75rem 0; border-bottom: 1px solid #f1f5f9; transition: all 0.3s ease; }
        .student-item:hover { background-color: #f9fafb; transform: translateX(5px); }
        .student-item:last-child { border-bottom: none; }
        .student-avatar {
            width: 40px; height: 40px; border-radius: 50%;
            background-color: rgba(79, 70, 229, 0.1); display: flex; align-items: center; justify-content: center;
            color: var(--primary); font-weight: 600; margin-right: 1rem; flex-shrink: 0;
        }
        .student-avatar.top-violators { background-color: rgba(231, 76, 60, 0.1); color: var(--danger); }
        .student-avatar.best-students { background-color: rgba(46, 204, 113, 0.1); color: var(--success); }
        .student-info { flex-grow: 1; }
        .student-name { font-weight: 500; margin-bottom: 0.15rem; }
        .student-details { display: flex; font-size: 0.8rem; color: var(--dark-light); }
        .student-details span { margin-right: 0.75rem; display: flex; align-items: center; }
        .student-details i { margin-right: 0.25rem; font-size: 0.7rem; }
        .violation-count { font-weight: 600; min-width: 30px; text-align: center; }
        .violation-count.zero { color: var(--success); }
        .view-all-link { display: inline-flex; align-items: center; color: var(--primary); font-weight: 500; font-size: 0.9rem; text-decoration: none; transition: all 0.3s ease; }
        .view-all-link:hover { color: var(--secondary); }
        .view-all-link i { margin-left: 0.3rem; transition: transform 0.3s ease; }
        .view-all-link:hover i { transform: translateX(3px); }
        .recent-violations {
            background: white; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
            overflow: hidden; transition: all 0.3s ease; margin-top: 1.5rem; border: 1px solid #e5e7eb;
        }
        .recent-violations:hover { box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.08), 0 4px 6px -4px rgba(0, 0, 0, 0.08); }
        .violation-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .violation-table thead { background-color: #2563eb; color: #F9FAFB; }
        .violation-table tbody tr:nth-child(even) { background-color: #f9fafb; }
        .violation-table th { padding: 1rem 1.5rem; text-align: left; font-weight: 600; font-size: 0.9rem; letter-spacing: 0.5px; text-transform: uppercase; }
        .violation-table td { padding: 1rem 1.5rem; border-bottom: 1px solid #e5e7eb; transition: all 0.2s ease; white-space: normal; overflow-wrap: break-word; vertical-align: middle; }
        .violation-table tr:last-child td { border-bottom: none; }
        .violation-table tbody tr:hover td { background-color: #eef2ff; }
        .violation-time { white-space: nowrap; }
        .time-ago { display: block; color: var(--dark-light); font-size: 0.8rem; margin-top: 0.25rem; }
        .empty-state { text-align: center; padding: 2.5rem; color: var(--dark-light); }
        .empty-state i { font-size: 2.5rem; margin-bottom: 1rem; color: #d1d5db; }
        .filter-form {
            margin-bottom: 2rem; display: flex; gap: 1rem; flex-wrap: wrap;
            background: white; padding: 1.5rem; border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
            border: 1px solid #e5e7eb;
        }
        .filter-form div { flex: 1; min-width: 200px; }
        .filter-form label { display: block; margin-bottom: 0.5rem; font-weight: 500; color: var(--dark); }
        .filter-form input { width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 8px; font-family: 'Poppins', sans-serif; transition: all 0.3s ease; }
        .filter-form input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.2); }
        .filter-form button {
            align-self: flex-end; padding: 0.75rem 1.5rem; background: var(--gradient-main); color: white; border: none;
            border-radius: 8px; cursor: pointer; font-family: 'Poppins', sans-serif; font-weight: 600;
            transition: all 0.3s ease; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
        }
        .filter-form button:hover { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(79, 70, 229, 0.2), 0 4px 6px -4px rgba(79, 70, 229, 0.2); }
        @media (max-width: 768px) {
            .container { padding: 1rem; }
            .dashboard-header { padding: 2rem 1.5rem; }
            .dashboard-header h1 { font-size: 2rem; }
            .stats-grid, .student-list { grid-template-columns: 1fr; }
            .violation-table { display: block; overflow-x: auto; }
            .section-title { font-size: 1.4rem; }
            .list-title { font-size: 1.05rem; }
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="dashboard-header">
            <div class="header-shield-bg"><i class="fas fa-shield-alt"></i></div>
            <h1><i class="fas fa-chart-pie"></i> Aplikasi Pendataan Mukholif</h1>
            <p class="subtitle">Pantau dan kelola data pelanggaran santri dengan mudah</p>
        </header>
        
        <div class="content-section">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-history"></i> Pelanggaran Terkini</h2>
                <a href="rekap/umum.php" class="view-all-link">Lihat semua <i class="fas fa-chevron-right"></i></a>
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
            <a href="santri/index.php" class="stat-card santri">
                <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
                <h3>Total Santri</h3>
                <div class="stat-value"><?= number_format($stats['santri'] ?? 0) ?></div>
                <p class="stat-description">Santri terdaftar</p>
            </a>
            <a href="jenis-pelanggaran/index.php" class="stat-card pelanggaran">
                <div class="stat-icon"><i class="fas fa-clipboard-check"></i></div>
                <h3>Jenis Pelanggaran</h3>
                <div class="stat-value"><?= number_format($stats['jenis_pelanggaran'] ?? 0) ?></div>
                <p class="stat-description">Kategori pelanggaran</p>
            </a>
            <a href="rekap/chart.php" class="stat-card violations">
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
            <a href="rekap/santri-teladan.php" class="stat-card clean">
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
                        <a href="rekap/santri-pelanggar.php" class="view-all-link">Lihat semua <i class="fas fa-chevron-right"></i></a>
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
                        <a href="rekap/santri-teladan.php" class="view-all-link">Lihat semua <i class="fas fa-chevron-right"></i></a>
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