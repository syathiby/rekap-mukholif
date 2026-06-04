<?php
// 1. Panggil 'Otak' aplikasi dulu
require_once __DIR__ . '/../bootstrap/init.php';

// 2. Jalankan 'SATPAM' buat ngejaga halaman
guard('rekap_keterlambatan');

// 3. Kalau lolos, baru panggil Tampilan
require_once __DIR__ . '/../layouts/header.php';

// 🔹 Ambil periode aktif
$periode_aktif = PERIODE_AKTIF;

if (!$periode_aktif) {
    die("⚠️ Periode aktif belum diset. Silakan atur dulu di halaman pengaturan.");
}

// 🔹 Ambil data unik untuk filter
$kamars = mysqli_query($conn, "SELECT DISTINCT kamar FROM santri WHERE kamar IS NOT NULL AND kamar != '' ORDER BY CAST(REGEXP_REPLACE(kamar, '[^0-9]', '') AS UNSIGNED) ASC, REGEXP_REPLACE(kamar, '[0-9]', '') ASC");
$kelas_list = mysqli_query($conn, "SELECT DISTINCT CAST(kelas AS UNSIGNED) AS kelas FROM santri WHERE kelas IS NOT NULL AND kelas != '' ORDER BY kelas ASC");

// 🔹 Ambil filter dari URL
$filter_kamar = $_GET['kamar'] ?? null;
$filter_kelas = $_GET['kelas'] ?? null;
$start_date = $_GET['start_date'] ?? $periode_aktif;
$end_date   = $_GET['end_date'] ?? date("Y-m-d");


// ✅ PERBAIKAN 1: Logika parameter untuk query tabel dan chart dipisah biar tidak bentrok

// === 1. PERSIAPAN & EKSEKUSI QUERY TABEL ===
$sql_table = "
    SELECT s.id, s.nama, s.kelas, s.kamar,
           COALESCE(p.total_pelanggaran, 0) AS total_pelanggaran,
           COALESCE(p.telat_sholat, 0) AS telat_sholat,
           COALESCE(p.telat_kbm, 0) AS telat_kbm
    FROM santri s
    LEFT JOIN (
        SELECT santri_id,
               COUNT(*) AS total_pelanggaran,
               SUM(CASE WHEN jenis_pelanggaran_id = 1 THEN 1 ELSE 0 END) AS telat_sholat,
               SUM(CASE WHEN jenis_pelanggaran_id = 2 THEN 1 ELSE 0 END) AS telat_kbm
        FROM pelanggaran
        WHERE DATE(tanggal) BETWEEN ? AND ?
          AND jenis_pelanggaran_id IN (1, 2)
        GROUP BY santri_id
    ) p ON s.id = p.santri_id
    WHERE 1=1
";
// Parameter khusus untuk query tabel
$table_params = [$start_date, $end_date];
$table_types = 'ss';
if ($filter_kamar) {
    $sql_table .= " AND s.kamar = ?";
    $table_params[] = $filter_kamar;
    $table_types .= 's';
}
if ($filter_kelas) {
    $sql_table .= " AND s.kelas = ?";
    $table_params[] = $filter_kelas;
    $table_types .= 's';
}
$sql_table .= " HAVING total_pelanggaran > 0 ORDER BY total_pelanggaran DESC, s.nama ASC";

$stmt_table = $conn->prepare($sql_table);
if ($stmt_table === false) die("❌ Query tabel error: " . $conn->error);
$stmt_table->bind_param($table_types, ...$table_params);
$stmt_table->execute();
$query_table = $stmt_table->get_result();


// === 2. PERSIAPAN & EKSEKUSI QUERY CHART ===
$sql_chart = "
    SELECT
        CASE
            WHEN p.jenis_pelanggaran_id = 2 THEN 'Telat KBM'
            WHEN TIME(p.tanggal) BETWEEN '03:30:00' AND '05:30:00' THEN 'Telat Sholat Subuh'
            WHEN TIME(p.tanggal) BETWEEN '11:30:00' AND '13:00:00' THEN 'Telat Sholat Dzuhur'
            WHEN TIME(p.tanggal) BETWEEN '14:45:00' AND '16:00:00' THEN 'Telat Sholat Ashar'
            WHEN TIME(p.tanggal) BETWEEN '17:30:00' AND '18:45:00' THEN 'Telat Sholat Maghrib'
            WHEN TIME(p.tanggal) BETWEEN '18:50:00' AND '20:30:00' THEN 'Telat Sholat Isya'
            ELSE 'Lainnya'
        END AS kategori,
        COUNT(*) AS jumlah
    FROM pelanggaran p
";
if ($filter_kamar || $filter_kelas) {
    $sql_chart .= " JOIN santri s ON p.santri_id = s.id";
}
$sql_chart .= " WHERE p.jenis_pelanggaran_id IN (1, 2) AND DATE(p.tanggal) BETWEEN ? AND ?";

// Parameter khusus untuk query chart
$chart_params = [$start_date, $end_date];
$chart_types = 'ss';
if ($filter_kamar) {
    $sql_chart .= " AND s.kamar = ?";
    $chart_params[] = $filter_kamar;
    $chart_types .= 's';
}
if ($filter_kelas) {
    $sql_chart .= " AND s.kelas = ?";
    $chart_params[] = $filter_kelas;
    $chart_types .= 's';
}
$sql_chart .= " GROUP BY kategori HAVING jumlah > 0 ORDER BY kategori";

$stmt_chart = $conn->prepare($sql_chart);
if ($stmt_chart === false) die("❌ Query chart error: " . $conn->error);
$stmt_chart->bind_param($chart_types, ...$chart_params);
$stmt_chart->execute();
$query_chart = $stmt_chart->get_result();


// Siapkan data untuk di-passing ke JavaScript (tidak ada perubahan di sini)
$chart_labels = [];
$chart_data = [];
$chart_colors = [];
$color_map = [
    'Telat Sholat Subuh'  => '#f59e0b', 'Telat Sholat Dzuhur' => '#0ea5e9',
    'Telat Sholat Ashar'  => '#f97316', 'Telat Sholat Maghrib'=> '#8b5cf6',
    'Telat Sholat Isya'   => '#6366f1', 'Telat KBM'           => '#10b981',
    'Lainnya'             => '#6b7280',
];

while($row = $query_chart->fetch_assoc()){
    $chart_labels[] = $row['kategori'];
    $chart_data[] = $row['jumlah'];
    $chart_colors[] = $color_map[$row['kategori']] ?? '#9ca3af';
}
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    :root {
        --primary: #4f46e5; --primary-light: #e0e7ff; --primary-dark: #4338ca;
        --secondary: #64748b; --light-bg: #f8fafc; --card-bg: #ffffff;
        --border-color: #e2e8f0; --text-dark: #1e293b; --text-light: #64748b;
        --gold: #f59e0b; --silver: #9ca3af; --bronze: #a16207;
        --danger: #ef4444; --danger-light: #fee2e2; --danger-dark: #b91c1c;
    }
    body { background-color: var(--light-bg); font-family: 'Poppins', sans-serif; }
    .card { background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem; box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.05); }
    .page-title { color: var(--text-dark); font-weight: 700; }
    .table th { background-color: var(--light-bg); color: var(--text-light); text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em; border-bottom: 1px solid var(--border-color); }
    .table tbody td { vertical-align: middle; padding-top: 1rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border-color); }
    .rank-icon { font-size: 1.5rem; }
    .rank-1 .rank-icon { color: var(--gold); }
    .rank-2 .rank-icon { color: var(--silver); }
    .rank-3 .rank-icon { color: var(--bronze); }
    .btn-detail { background-color: var(--primary-light); color: var(--primary-dark); font-weight: 600; text-decoration: none; transition: all 0.2s; padding: 0.375rem 1rem; border-radius: 9999px; }
    .btn-detail:hover { background-color: var(--primary-dark); color: white; }
    .badge-danger { background-color: var(--danger-light); color: var(--danger-dark); padding: 0.25rem 0.75rem; border-radius: 9999px; font-weight: 600; font-size: 0.875rem;}
</style>

<div class="container py-4">
    <h1 class="page-title mb-4"><i class="fas fa-clock me-3"></i>Rekap Pelanggaran Keterlambatan Santri</h1>
    
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title fw-bold mb-3"><i class="fas fa-filter me-2"></i>Filter Data</h5>
            <form method="get" id="filterForm">
                <div class="row g-3">
                    <div class="col-lg-3 col-md-6">
                        <label for="start_date" class="form-label">Dari Tanggal</label>
                        <input type="date" class="form-control" name="start_date" id="start_date" value="<?= htmlspecialchars($start_date) ?>">
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <label for="end_date" class="form-label">Sampai Tanggal</label>
                        <input type="date" class="form-control" name="end_date" id="end_date" value="<?= htmlspecialchars($end_date) ?>">
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <label for="kamar" class="form-label">Kamar</label>
                        <select name="kamar" id="kamar" class="form-select">
                            <option value="">Semua Kamar</option>
                            <?php 
                            mysqli_data_seek($kamars, 0);
                            while ($k = mysqli_fetch_assoc($kamars)): ?>
                                <option value="<?= htmlspecialchars($k['kamar']) ?>" <?= ($filter_kamar == $k['kamar']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($k['kamar']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <label for="kelas" class="form-label">Kelas</label>
                        <select name="kelas" id="kelas" class="form-select">
                            <option value="">Semua Kelas</option>
                            <?php 
                            mysqli_data_seek($kelas_list, 0);
                            while ($k = mysqli_fetch_assoc($kelas_list)): ?>
                                <option value="<?= htmlspecialchars($k['kelas']) ?>" <?= ($filter_kelas == $k['kelas']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($k['kelas']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-body table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th class="text-center">Peringkat</th>
                                <th>Santri</th>
                                <th class="text-center">Total</th>
                                <th>Detail Pelanggaran</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $no = 1;
                            if (mysqli_num_rows($query_table) === 0) {
                                echo '<tr><td colspan="5" class="text-center p-5 text-muted"><i class="fas fa-check-circle fa-3x mb-3"></i><br><b>Hebat!</b><br>Tidak ada pelanggaran pada periode ini.</td></tr>';
                            } else {
                                while ($row = mysqli_fetch_assoc($query_table)) {
                                    $detail_link = "detail_keterlambatan.php?id={$row['id']}"
                                                 . "&start_date=" . htmlspecialchars($start_date)
                                                 . "&end_date=" . htmlspecialchars($end_date)
                                                 . "&kamar=" . urlencode($filter_kamar ?? '')
                                                 . "&kelas=" . urlencode($filter_kelas ?? '');
                                    ?>
                                    <tr class="rank-<?= $no ?>">
                                        <td class="text-center">
                                            <?php if ($no <= 3) : ?>
                                                <i class="fas fa-trophy rank-icon"></i>
                                            <?php else : ?>
                                                <span class="fw-bold fs-5"><?= $no ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="fw-bold"><?= htmlspecialchars($row['nama']) ?></div>
                                            <small class="text-muted">Kls: <?= htmlspecialchars($row['kelas']) ?> | Kmr: <?= htmlspecialchars($row['kamar']) ?></small>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge-danger"><?= $row['total_pelanggaran'] ?></span>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-column gap-1 small">
                                                <span class="d-flex align-items-center gap-2" style="color:var(--danger-dark);">
                                                    <i class="fas fa-pray fa-fw"></i> Telat Sholat: <b><?= $row['telat_sholat'] ?></b>
                                                </span>
                                                <span class="d-flex align-items-center gap-2" style="color:var(--primary-dark);">
                                                    <i class="fas fa-book fa-fw"></i> Telat KBM: <b><?= $row['telat_kbm'] ?></b>
                                                </span>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <a href="<?= $detail_link ?>" class="btn btn-sm btn-detail d-inline-flex align-items-center justify-content-center">
                                                <i class="fas fa-info-circle me-1"></i> Detail
                                            </a>
                                        </td>
                                    </tr>
                                    <?php
                                    $no++;
                                 }
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title fw-bold mb-4"><i class="fas fa-chart-pie me-2"></i>Ringkasan Grafik</h5>
                    <?php if (!empty($chart_data)): ?>
                        <div style="position: relative; height: 350px; width: 100%;">
                            <canvas id="pelanggaranChart"></canvas>
                        </div>
                    <?php else: ?>
                        <div class="text-center p-5 text-muted d-flex flex-column align-items-center justify-content-center h-100">
                            <i class="fas fa-chart-bar fa-3x mb-3" style="opacity: 0.2;"></i>
                            <p>Tidak ada data untuk ditampilkan di grafik.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Script untuk Chart.js (tidak berubah)
document.addEventListener('DOMContentLoaded', function() {
    const chartLabels = <?= json_encode($chart_labels) ?>;
    const chartData = <?= json_encode($chart_data) ?>;
    const chartColors = <?= json_encode($chart_colors) ?>;

    if (chartData.length > 0) {
        const ctx = document.getElementById('pelanggaranChart').getContext('2d');
        const pelanggaranChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'Jumlah Pelanggaran',
                    data: chartData,
                    backgroundColor: chartColors,
                    borderColor: '#ffffff',
                    borderWidth: 2,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { padding: 15, boxWidth: 12, font: { family: 'Poppins', } }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) { label += ': '; }
                                if (context.parsed !== null) { label += context.parsed + ' kali'; }
                                return label;
                            }
                        }
                    }
                },
                cutout: '65%'
            }
        });
    }
});

// Script Javascript untuk auto-submit filter (tidak berubah)
document.addEventListener('DOMContentLoaded', function() {
    const filterForm = document.getElementById('filterForm');
    const filterInputs = filterForm.querySelectorAll('select, input[type="date"]');

    filterInputs.forEach(function(input) {
        input.addEventListener('change', function() {
            filterForm.submit();
        });
    });
});
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>