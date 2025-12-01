<?php 
// 1. Panggil 'Otak' aplikasi
require_once __DIR__ . '/../../init.php';

// 2. Jalankan 'SATPAM'
guard('rekap_view_kesantrian');   

// 3. Panggil Header
require_once __DIR__ . '/../../header.php';

$bagian = 'Kesantrian'; 

// =======================================================
// 🔵 BAGIAN 1: PERSIAPAN DATA & FILTER
// =======================================================

$q_periode = mysqli_query($conn, "SELECT nilai FROM pengaturan WHERE nama = 'periode_aktif' LIMIT 1");
$periode_aktif = mysqli_fetch_assoc($q_periode)['nilai'] ?? date('Y-m-01');

$kamars_result = mysqli_query($conn, "SELECT DISTINCT kamar FROM santri WHERE kamar IS NOT NULL AND kamar != '' ORDER BY CAST(kamar AS UNSIGNED) ASC");
$kelas_list = mysqli_query($conn, "SELECT DISTINCT CAST(kelas AS UNSIGNED) AS kelas FROM santri WHERE kelas IS NOT NULL AND kelas != '' ORDER BY kelas ASC");

$filter_kamar = $_GET['kamar'] ?? '';
$filter_kelas = $_GET['kelas'] ?? '';
$start_date = $_GET['start_date'] ?? $periode_aktif;
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// =======================================================
// 🔵 BAGIAN 2: LOGIC QUERY DATABASE
// =======================================================

$base_params = [$bagian, $start_date, $end_date];
$base_types = "sss";

$extra_sql = "";
if (!empty($filter_kamar)) {
    $extra_sql .= " AND s.kamar = ?";
    $base_params[] = $filter_kamar;
    $base_types .= "s";
}
if (!empty($filter_kelas)) {
    $extra_sql .= " AND s.kelas = ?";
    $base_params[] = $filter_kelas;
    $base_types .= "s";
}

// --- QUERY 0: STATISTIK RINGKAS (Header) ---
$sql_stats = "
    SELECT 
        COUNT(p.id) as total_kasus,
        COALESCE(SUM(jp.poin), 0) as total_poin,
        COUNT(DISTINCT p.santri_id) as total_santri
    FROM pelanggaran p
    JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id
    LEFT JOIN santri s ON p.santri_id = s.id
    WHERE jp.bagian = ? 
      AND DATE(p.tanggal) BETWEEN ? AND ?
      {$extra_sql}
";
$stmt_stats = mysqli_prepare($conn, $sql_stats);
mysqli_stmt_bind_param($stmt_stats, $base_types, ...$base_params);
mysqli_stmt_execute($stmt_stats);
$stats = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_stats));

// --- QUERY 1: CHART SEBARAN KAMAR (Diurutkan dari Terbanyak) ---
$sql_kamar = "
    SELECT s.kamar, COUNT(p.id) as total
    FROM pelanggaran p
    JOIN santri s ON p.santri_id = s.id
    JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id
    WHERE jp.bagian = ? AND DATE(p.tanggal) BETWEEN ? AND ?
    {$extra_sql}
    GROUP BY s.kamar 
    ORDER BY total DESC
";
// Note: ORDER BY total DESC bikin urutannya dari kasus terbanyak
$stmt_kamar = mysqli_prepare($conn, $sql_kamar);
mysqli_stmt_bind_param($stmt_kamar, $base_types, ...$base_params);
mysqli_stmt_execute($stmt_kamar);
$data_kamar = mysqli_fetch_all(mysqli_stmt_get_result($stmt_kamar), MYSQLI_ASSOC);

$json_kamar_chart = json_encode([
    'labels' => array_map(function($i) { return "Kamar " . $i; }, array_column($data_kamar, 'kamar')),
    'data' => array_column($data_kamar, 'total')
]);

// --- QUERY 2: CHART SEBARAN KELAS (Diurutkan dari Terbanyak) ---
$sql_kelas = "
    SELECT s.kelas, COUNT(p.id) as total
    FROM pelanggaran p
    JOIN santri s ON p.santri_id = s.id
    JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id
    WHERE jp.bagian = ? AND DATE(p.tanggal) BETWEEN ? AND ?
    {$extra_sql}
    GROUP BY s.kelas 
    ORDER BY total DESC
";
$stmt_kelas = mysqli_prepare($conn, $sql_kelas);
mysqli_stmt_bind_param($stmt_kelas, $base_types, ...$base_params);
mysqli_stmt_execute($stmt_kelas);
$data_kelas = mysqli_fetch_all(mysqli_stmt_get_result($stmt_kelas), MYSQLI_ASSOC);

$json_kelas_chart = json_encode([
    'labels' => array_map(function($i) { return "Kelas " . $i; }, array_column($data_kelas, 'kelas')),
    'data' => array_column($data_kelas, 'total')
]);

// --- QUERY 3: PERINGKAT SANTRI (Tabel Bawah) ---
$sql_peringkat = "
    SELECT 
        s.id, s.nama, s.kelas, s.kamar,
        COUNT(p.id) AS total_pelanggaran,
        COALESCE(SUM(jp.poin), 0) AS total_poin
    FROM santri s
    LEFT JOIN pelanggaran p ON s.id = p.santri_id
    JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id
    WHERE jp.bagian = ? 
      AND DATE(p.tanggal) BETWEEN ? AND ?
      {$extra_sql}
    GROUP BY s.id
    HAVING total_pelanggaran > 0
    ORDER BY total_poin DESC, total_pelanggaran DESC, s.nama ASC
";
$stmt = mysqli_prepare($conn, $sql_peringkat);
mysqli_stmt_bind_param($stmt, $base_types, ...$base_params);
mysqli_stmt_execute($stmt);
$peringkat_list = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    /* -- SOFT BLUE THEME -- */
    :root {
        --theme-primary: #3b82f6;      
        --theme-soft: #eff6ff;         
        --theme-dark: #1e3a8a;         
        --theme-card-bg: #ffffff;
        --theme-border: #dbeafe;       
    }
    body { background-color: #f8f9fa; font-family: 'Poppins', sans-serif; }

    /* PAGE HEADER */
    .page-header {
        background: linear-gradient(135deg, var(--theme-primary), var(--theme-dark));
        color: white; padding: 2rem; border-radius: 16px; margin-bottom: 2rem;
        box-shadow: 0 10px 20px -5px rgba(37, 99, 235, 0.3);
        position: relative; overflow: hidden;
    }
    /* Stat Cards */
    .stat-card-row {
        display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem;
        margin-top: 1.5rem;
    }
    .stat-item {
        background: rgba(255, 255, 255, 0.15);
        backdrop-filter: blur(5px);
        border: 1px solid rgba(255, 255, 255, 0.2);
        padding: 1rem; border-radius: 12px;
        text-align: center; color: white;
        transition: transform 0.2s;
    }
    .stat-item:hover { transform: translateY(-3px); background: rgba(255, 255, 255, 0.25); }
    .stat-value { font-size: 1.8rem; font-weight: 800; display: block; line-height: 1.2; }
    .stat-label { font-size: 0.85rem; opacity: 0.9; text-transform: uppercase; letter-spacing: 0.5px; }

    /* Filter */
    .filter-card {
        background: white; border-radius: 16px; border: 1px solid var(--theme-border);
        box-shadow: 0 4px 15px rgba(59, 130, 246, 0.05); padding: 1.5rem;
    }
    .filter-label { font-weight: 700; color: var(--theme-dark); font-size: 0.8rem; text-transform: uppercase; margin-bottom: 5px; }
    .form-control, .form-select { border-radius: 10px; border-color: #e5e7eb; }
    .form-control:focus, .form-select:focus { border-color: var(--theme-primary); box-shadow: 0 0 0 4px var(--theme-soft); }

    /* Charts */
    .chart-card { background: white; border-radius: 16px; padding: 1.5rem; height: 100%; border: 1px solid var(--theme-border); box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
    .card-title { font-weight: 700; color: var(--theme-dark); border-bottom: 2px solid var(--theme-soft); padding-bottom: 10px; margin-bottom: 20px; }

    /* Table */
    .ranking-table tbody tr { background: white; transition: transform 0.2s; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
    .ranking-table tbody tr:hover { transform: translateY(-3px); box-shadow: 0 10px 20px -5px rgba(59, 130, 246, 0.1); }
    .ranking-table td { padding: 1rem; vertical-align: middle; border: none; }
    .ranking-table td:first-child { border-top-left-radius: 12px; border-bottom-left-radius: 12px; }
    .ranking-table td:last-child { border-top-right-radius: 12px; border-bottom-right-radius: 12px; }
    
    .rank-badge { width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; border-radius: 50%; font-weight: bold; }
    .rank-1 { background: #fff7ed; color: #ea580c; border: 2px solid #fdba74; } 
    .rank-2 { background: #f1f5f9; color: #475569; border: 2px solid #cbd5e1; } 
    .rank-3 { background: #fefce8; color: #ca8a04; border: 2px solid #fde047; }
    .rank-other { background: var(--theme-soft); color: var(--theme-primary); }
    .poin-badge { background: var(--theme-soft); color: var(--theme-primary); padding: 6px 14px; border-radius: 20px; font-weight: 800; }
</style>

<div class="container my-4">
    
    <div class="page-header">
        <div class="d-flex flex-column align-items-start">
            <h2 class="fw-bold mb-1"><i class="fas fa-user-tie me-2"></i>Rekap Kesantrian</h2>
            <p class="mb-0 opacity-75 small">
                Monitoring Kedisiplinan | Periode: <span class="fw-bold text-warning"><?= date('d M Y', strtotime($start_date)) ?> - <?= date('d M Y', strtotime($end_date)) ?></span>
            </p>
        </div>

        <div class="stat-card-row">
            <div class="stat-item">
                <span class="stat-value"><?= number_format($stats['total_kasus']) ?></span>
                <span class="stat-label">Total Pelanggaran</span>
            </div>
            <div class="stat-item">
                <span class="stat-value"><?= number_format($stats['total_poin']) ?></span>
                <span class="stat-label">Total Poin</span>
            </div>
            <div class="stat-item">
                <span class="stat-value"><?= number_format($stats['total_santri']) ?></span>
                <span class="stat-label">Santri Terlibat</span>
            </div>
        </div>
    </div>

    <div class="filter-card mb-4">
        <form method="get" id="filterForm">
            <div class="row g-3 align-items-end">
                <div class="col-6 col-md-3">
                    <label class="filter-label">Mulai Tanggal</label>
                    <input type="date" class="form-control" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
                </div>
                <div class="col-6 col-md-3">
                    <label class="filter-label">Sampai Tanggal</label>
                    <input type="date" class="form-control" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
                </div>
                <div class="col-6 col-md-3">
                    <label class="filter-label">Filter Kamar</label>
                    <select name="kamar" class="form-select">
                        <option value="">Semua Kamar</option>
                        <?php mysqli_data_seek($kamars_result, 0); while ($k = mysqli_fetch_assoc($kamars_result)): ?>
                            <option value="<?= htmlspecialchars($k['kamar']) ?>" <?= ($filter_kamar == $k['kamar']) ? 'selected' : '' ?>>Kamar <?= htmlspecialchars($k['kamar']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="filter-label">Filter Kelas</label>
                    <select name="kelas" class="form-select">
                        <option value="">Semua Kelas</option>
                        <?php mysqli_data_seek($kelas_list, 0); while ($k = mysqli_fetch_assoc($kelas_list)): ?>
                            <option value="<?= htmlspecialchars($k['kelas']) ?>" <?= ($filter_kelas == $k['kelas']) ? 'selected' : '' ?>>Kelas <?= htmlspecialchars($k['kelas']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>
        </form>
    </div>

    <div class="row g-4 mb-5">
        <div class="col-md-7">
            <div class="chart-card">
                <h5 class="card-title text-primary"><i class="fas fa-chart-pie me-2"></i>Sebaran Per Kamar</h5>
                <div style="height: 250px; position: relative;">
                    <canvas id="chartKamar"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-5">
            <div class="chart-card">
                <h5 class="card-title text-primary"><i class="fas fa-chart-doughnut me-2"></i>Sebaran Kelas</h5>
                <div style="height: 250px; position: relative;">
                    <canvas id="chartKelas"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="mb-5">
        <h5 class="fw-bold text-dark mb-3 ps-2 border-start border-4 border-primary">&nbsp; Peringkat Pelanggaran</h5>
        <div class="table-responsive">
            <table class="table ranking-table">
                <thead>
                    <tr>
                        <th class="text-center" width="80">Rank</th>
                        <th>Identitas Santri</th>
                        <th class="text-center">Total Poin</th>
                        <th class="text-center">Jml Kasus</th>
                        <th class="text-end pe-4">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($peringkat_list)): ?>
                        <tr><td colspan="5" class="text-center py-5 text-muted">Belum ada data pelanggaran.</td></tr>
                    <?php else: ?>
                        <?php foreach ($peringkat_list as $index => $row): $no = $index + 1; ?>
                        <tr>
                            <td class="text-center">
                                <div class="rank-badge mx-auto <?= ($no <= 3) ? 'rank-'.$no : 'rank-other' ?>">
                                    <?= ($no <= 3) ? '<i class="fas fa-crown"></i>' : $no ?>
                                </div>
                            </td>
                            <td>
                                <div class="fw-bold text-dark mb-1"><?= htmlspecialchars($row['nama']) ?></div>
                                <div class="d-flex gap-2">
                                    <span class="badge bg-light text-secondary border">Kls <?= htmlspecialchars($row['kelas']) ?></span>
                                    <span class="badge bg-light text-secondary border">Kmr <?= htmlspecialchars($row['kamar']) ?></span>
                                </div>
                            </td>
                            <td class="text-center"><span class="poin-badge"><?= $row['total_poin'] ?></span></td>
                            <td class="text-center text-muted fw-bold"><?= $row['total_pelanggaran'] ?></td>
                            <td class="text-end pe-3">
                                <a href="detail-kesantrian.php?santri_id=<?= $row['id'] ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" class="btn btn-sm btn-outline-primary rounded-pill px-4 fw-bold">Detail <i class="fas fa-arrow-right ms-1"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Auto Submit Filter
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('#filterForm select, #filterForm input').forEach(input => {
        input.addEventListener('change', () => document.getElementById('filterForm').submit());
    });
});

// Data JSON dari PHP
const dataKamar = <?= $json_kamar_chart ?>;
const dataKelas = <?= $json_kelas_chart ?>;
const colorfulColors = ['#3b82f6', '#ef4444', '#f59e0b', '#10b981', '#8b5cf6', '#06b6d4', '#ec4899', '#6366f1'];

// Common Options buat Chart (Legend Bottom biar gak kepotong)
const commonOptions = {
    responsive: true, 
    maintainAspectRatio: false, 
    plugins: { 
        legend: { 
            position: 'bottom', // INI KUNCINYA (Legend di bawah)
            labels: { 
                usePointStyle: true, 
                boxWidth: 8,
                padding: 15,
                font: { size: 11 }
            } 
        } 
    }
};

// Chart 1: Sebaran Kamar (Pie)
if (dataKamar.labels.length > 0) {
    new Chart(document.getElementById('chartKamar'), {
        type: 'pie',
        data: {
            labels: dataKamar.labels,
            datasets: [{
                data: dataKamar.data,
                backgroundColor: colorfulColors,
                borderWidth: 2, borderColor: '#ffffff'
            }]
        },
        options: commonOptions
    });
}

// Chart 2: Sebaran Kelas (Doughnut)
if (dataKelas.labels.length > 0) {
    new Chart(document.getElementById('chartKelas'), {
        type: 'doughnut',
        data: {
            labels: dataKelas.labels,
            datasets: [{
                data: dataKelas.data,
                backgroundColor: colorfulColors,
                borderWidth: 2, borderColor: '#ffffff'
            }]
        },
        options: commonOptions
    });
}
</script>

<?php require_once __DIR__ . '/../../footer.php'; ?>