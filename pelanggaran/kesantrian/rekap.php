<?php 
// 1. Panggil 'Otak' aplikasi
require_once __DIR__ . '/../../bootstrap/init.php';

// 2. Jalankan 'SATPAM'
guard('rekap_view_kesantrian');   

// Deteksi AJAX
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if (!$is_ajax) {
    // 3. Panggil Header
    require_once __DIR__ . '/../../layouts/header.php';
}

$bagian = 'Kesantrian'; 

// =======================================================
// 🔵 BAGIAN 1: PERSIAPAN DATA & FILTER
// =======================================================

$periode_aktif = PERIODE_AKTIF;

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
mysqli_stmt_close($stmt_stats);

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
$stmt_kamar = mysqli_prepare($conn, $sql_kamar);
mysqli_stmt_bind_param($stmt_kamar, $base_types, ...$base_params);
mysqli_stmt_execute($stmt_kamar);
$data_kamar = mysqli_fetch_all(mysqli_stmt_get_result($stmt_kamar), MYSQLI_ASSOC);
mysqli_stmt_close($stmt_kamar);

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
mysqli_stmt_close($stmt_kelas);

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
mysqli_stmt_close($stmt);

// Render body tabel
ob_start();
?>
<?php if (empty($peringkat_list)): ?>
    <tr><td colspan="5" class="text-center py-5 text-muted">Belum ada data pelanggaran.</td></tr>
<?php else: ?>
    <?php foreach ($peringkat_list as $index => $row): $no = $index + 1; ?>
    <tr>
        <td class="text-center py-3">
            <div class="rank-badge mx-auto <?= ($no <= 3) ? 'rank-'.$no : 'rank-other' ?>">
                <?= ($no <= 3) ? '<i class="fas fa-crown"></i>' : $no ?>
            </div>
        </td>
        <td class="py-3">
            <div class="fw-bold text-dark mb-1"><?= htmlspecialchars($row['nama']) ?></div>
            <div class="d-flex flex-wrap gap-2">
                <span class="badge bg-light text-secondary border" title="NIS">NIS <?= htmlspecialchars($row['nis'] ?? '-') ?></span>
                <span class="badge bg-light text-secondary border">Kls <?= htmlspecialchars($row['kelas']) ?></span>
                <span class="badge bg-light text-secondary border">Kmr <?= htmlspecialchars($row['kamar']) ?></span>
            </div>
        </td>
        <td class="text-center py-3"><span class="poin-badge"><?= $row['total_poin'] ?></span></td>
        <td class="text-center text-muted fw-bold py-3"><?= $row['total_pelanggaran'] ?></td>
        <td class="text-end pe-4 py-3">
            <a href="detail-kesantrian.php?santri_id=<?= $row['id'] ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" class="btn btn-sm btn-outline-primary rounded-pill px-4 fw-bold">Detail <i class="fas fa-arrow-right ms-1"></i></a>
        </td>
    </tr>
    <?php endforeach; ?>
<?php endif; ?>
<?php
$table_tbody_html = ob_get_clean();

// Kirim data JSON jika request berupa AJAX
if ($is_ajax) {
    header('Content-Type: application/json');
    echo json_encode([
        'tbody' => $table_tbody_html,
        'stats' => [
            'total_kasus' => number_format($stats['total_kasus']),
            'total_poin' => number_format($stats['total_poin']),
            'total_santri' => number_format($stats['total_santri'])
        ],
        'kamar_chart' => [
            'labels' => array_map(function($i) { return "Kamar " . $i; }, array_column($data_kamar, 'kamar')),
            'data' => array_column($data_kamar, 'total')
        ],
        'kelas_chart' => [
            'labels' => array_map(function($i) { return "Kelas " . $i; }, array_column($data_kelas, 'kelas')),
            'data' => array_column($data_kelas, 'total')
        ]
    ]);
    exit;
}
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    .rank-badge { width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; border-radius: 50%; font-weight: bold; }
    .rank-1 { background: #fff7ed; color: #ea580c; border: 2px solid #fdba74; } 
    .rank-2 { background: #f1f5f9; color: #475569; border: 2px solid #cbd5e1; } 
    .rank-3 { background: #fefce8; color: #ca8a04; border: 2px solid #fde047; }
    .rank-other { background: #f8f9fa; color: #6c757d; border: 1px solid #dee2e6; }
    .poin-badge { background: #eff6ff; color: #3b82f6; padding: 6px 14px; border-radius: 20px; font-weight: 800; }
</style>

<div class="container my-4">
    
    <div class="d-flex align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1"><i class="fas fa-user-tie me-2 text-primary"></i>Rekap Kesantrian</h4>
            <p class="mb-0 text-secondary small">
                Monitoring Kedisiplinan | Periode: <span class="fw-bold text-dark"><?= date('d M Y', strtotime($start_date)) ?> - <?= date('d M Y', strtotime($end_date)) ?></span>
            </p>
        </div>
    </div>

    <!-- Global Stats -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="pro-card p-4 d-flex align-items-center h-100">
                <div class="icon-circle bg-danger bg-opacity-10 text-danger me-3" style="width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; border-radius: 12px; font-size: 1.5rem;">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1 text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Total Pelanggaran</h6>
                    <h3 class="mb-0 fw-bold text-dark" id="statsTotalKasus"><?= number_format($stats['total_kasus']) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="pro-card p-4 d-flex align-items-center h-100">
                <div class="icon-circle bg-warning bg-opacity-10 text-warning me-3" style="width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; border-radius: 12px; font-size: 1.5rem;">
                    <i class="fas fa-star-half-alt"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1 text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Total Poin</h6>
                    <h3 class="mb-0 fw-bold text-dark" id="statsTotalPoin"><?= number_format($stats['total_poin']) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="pro-card p-4 d-flex align-items-center h-100">
                <div class="icon-circle bg-primary bg-opacity-10 text-primary me-3" style="width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; border-radius: 12px; font-size: 1.5rem;">
                    <i class="fas fa-users"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1 text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Santri Terlibat</h6>
                    <h3 class="mb-0 fw-bold text-dark" id="statsTotalSantri"><?= number_format($stats['total_santri']) ?></h3>
                </div>
            </div>
        </div>
    </div>

    <div class="pro-card p-4 mb-4">
        <form method="get" id="filterForm">
            <div class="row g-3 align-items-end">
                <div class="col-6 col-md-3">
                    <label class="form-label fw-bold text-secondary text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Mulai Tanggal</label>
                    <input type="date" class="form-control bg-light" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label fw-bold text-secondary text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Sampai Tanggal</label>
                    <input type="date" class="form-control bg-light" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label fw-bold text-secondary text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Filter Kamar</label>
                    <select name="kamar" class="form-select bg-light">
                        <option value="">Semua Kamar</option>
                        <?php mysqli_data_seek($kamars_result, 0); while ($k = mysqli_fetch_assoc($kamars_result)): ?>
                            <option value="<?= htmlspecialchars($k['kamar']) ?>" <?= ($filter_kamar == $k['kamar']) ? 'selected' : '' ?>>Kamar <?= htmlspecialchars($k['kamar']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label fw-bold text-secondary text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Filter Kelas</label>
                    <select name="kelas" class="form-select bg-light">
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
            <div class="pro-card p-4 h-100">
                <h6 class="fw-bold text-dark mb-4 text-uppercase border-bottom pb-3" style="letter-spacing: 0.5px;"><i class="fas fa-chart-pie me-2 text-primary"></i>Sebaran Per Kamar</h6>
                <div style="height: 250px; position: relative;">
                    <canvas id="chartKamar"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-5">
            <div class="pro-card p-4 h-100">
                <h6 class="fw-bold text-dark mb-4 text-uppercase border-bottom pb-3" style="letter-spacing: 0.5px;"><i class="fas fa-chart-doughnut me-2 text-primary"></i>Sebaran Kelas</h6>
                <div style="height: 250px; position: relative;">
                    <canvas id="chartKelas"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="mb-5 pro-card p-0 overflow-hidden">
        <div class="p-4 border-bottom bg-light">
            <h6 class="fw-bold text-dark mb-0 text-uppercase" style="letter-spacing: 0.5px;"><i class="fas fa-trophy text-warning me-2"></i> Peringkat Pelanggaran</h6>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 ranking-table">
                <thead class="text-secondary bg-white" style="font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;">
                    <tr>
                        <th class="text-center py-3" width="80">Rank</th>
                        <th class="py-3">Identitas Santri</th>
                        <th class="text-center py-3">Total Poin</th>
                        <th class="text-center py-3">Jml Kasus</th>
                        <th class="text-end pe-4 py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody id="rekapTableBody">
                    <?= $table_tbody_html ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
let chartKamar = null;
let chartKelas = null;

const dataKamar = <?= $json_kamar_chart ?>;
const dataKelas = <?= $json_kelas_chart ?>;
const colorfulColors = ['#3b82f6', '#ef4444', '#f59e0b', '#10b981', '#8b5cf6', '#06b6d4', '#ec4899', '#6366f1'];

const commonOptions = {
    responsive: true, 
    maintainAspectRatio: false, 
    plugins: { 
        legend: { 
            position: 'bottom', 
            labels: { 
                usePointStyle: true, 
                boxWidth: 8,
                padding: 15,
                font: { size: 11 }
            } 
        } 
    }
};

document.addEventListener('DOMContentLoaded', () => {
    // Chart 1: Sebaran Kamar (Pie)
    if (document.getElementById('chartKamar')) {
        chartKamar = new Chart(document.getElementById('chartKamar'), {
            type: 'pie',
            data: {
                labels: dataKamar.labels || [],
                datasets: [{
                    data: dataKamar.data || [],
                    backgroundColor: colorfulColors,
                    borderWidth: 2, borderColor: '#ffffff'
                }]
            },
            options: commonOptions
        });
    }

    // Chart 2: Sebaran Kelas (Doughnut)
    if (document.getElementById('chartKelas')) {
        chartKelas = new Chart(document.getElementById('chartKelas'), {
            type: 'doughnut',
            data: {
                labels: dataKelas.labels || [],
                datasets: [{
                    data: dataKelas.data || [],
                    backgroundColor: colorfulColors,
                    borderWidth: 2, borderColor: '#ffffff'
                }]
            },
            options: commonOptions
        });
    }
});

document.addEventListener('DOMContentLoaded', () => {
    const filterForm = document.getElementById('filterForm');
    const tbody = document.getElementById('rekapTableBody');
    const statsTotalKasus = document.getElementById('statsTotalKasus');
    const statsTotalPoin = document.getElementById('statsTotalPoin');
    const statsTotalSantri = document.getElementById('statsTotalSantri');

    function updateData() {
        const formData = new FormData(filterForm);
        const params = new URLSearchParams(formData);
        const url = '?' + params.toString();

        tbody.style.opacity = '0.5';

        fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.json();
        })
        .then(data => {
            tbody.innerHTML = data.tbody;
            tbody.style.opacity = '1';

            if (statsTotalKasus) statsTotalKasus.innerText = data.stats.total_kasus;
            if (statsTotalPoin) statsTotalPoin.innerText = data.stats.total_poin;
            if (statsTotalSantri) statsTotalSantri.innerText = data.stats.total_santri;

            if (chartKamar && data.kamar_chart) {
                chartKamar.data.labels = data.kamar_chart.labels;
                chartKamar.data.datasets[0].data = data.kamar_chart.data;
                chartKamar.update();
            }

            if (chartKelas && data.kelas_chart) {
                chartKelas.data.labels = data.kelas_chart.labels;
                chartKelas.data.datasets[0].data = data.kelas_chart.data;
                chartKelas.update();
            }

            window.history.pushState(null, '', url);
        })
        .catch(error => {
            console.error('Error fetching data:', error);
            tbody.style.opacity = '1';
        });
    }

    document.querySelectorAll('#filterForm select, #filterForm input').forEach(input => {
        input.addEventListener('change', updateData);
    });
});
</script>

<?php 
if (!$is_ajax) {
    require_once __DIR__ . '/../../layouts/footer.php'; 
}
?>