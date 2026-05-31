<?php 
// 1. Panggil 'Otak' aplikasi dulu
require_once __DIR__ . '/../../bootstrap/init.php';

// 2. Jalankan 'SATPAM' buat ngejaga halaman
guard('rekap_view_bahasa'); 

// Deteksi AJAX
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if (!$is_ajax) {
    // 3. Kalau lolos dan bukan AJAX, baru panggil Tampilan
    require_once __DIR__ . '/../../layouts/header.php';
}

$bagian = 'Bahasa';

// =======================================================
// BAGIAN 1: PERSIAPAN DATA & FILTER
// =======================================================

$q_periode = mysqli_query($conn, "SELECT nilai FROM pengaturan WHERE nama = 'periode_aktif' LIMIT 1");
$periode_aktif = mysqli_fetch_assoc($q_periode)['nilai'] ?? null;

if (!$periode_aktif) {
    die("<div class='container my-4'><div class='alert alert-warning'><strong>Peringatan:</strong> Periode aktif belum diatur. Silakan atur di Pengaturan.</div></div>");
}

// Data Filter: Kamar & Kelas
$kamars_result = mysqli_query($conn, "SELECT DISTINCT kamar FROM santri WHERE kamar IS NOT NULL AND kamar != '' ORDER BY CAST(kamar AS UNSIGNED) ASC");
$kelas_list = mysqli_query($conn, "SELECT DISTINCT CAST(kelas AS UNSIGNED) AS kelas FROM santri WHERE kelas IS NOT NULL AND kelas != '' ORDER BY kelas ASC");

// Data Filter Level Bahasa
$levels_result = mysqli_query($conn, "SELECT id, nama_pelanggaran FROM jenis_pelanggaran WHERE bagian = 'Bahasa' ORDER BY poin ASC");

// Ambil filter dari URL
$filter_kamar = $_GET['kamar'] ?? '';
$filter_kelas = $_GET['kelas'] ?? '';
$filter_level = $_GET['level'] ?? '';
$start_date = $_GET['start_date'] ?? $periode_aktif;
$end_date = $_GET['end_date'] ?? date('Y-m-d');


// =======================================================
// BAGIAN 2: QUERY UTAMA & PROSES DATA (SNAPSHOT LOGIC - OPTIMIZED)
// =======================================================

$params = [$start_date, $end_date, $start_date, $end_date];
$types = "ssss";
$filter_sql = "";

if (!empty($filter_kamar)) {
    $filter_sql .= " AND s.kamar = ?";
    $params[] = $filter_kamar;
    $types .= "s";
}
if (!empty($filter_kelas)) {
    $filter_sql .= " AND s.kelas = ?";
    $params[] = $filter_kelas;
    $types .= "s";
}

// Query teroptimasi untuk menarik data santri yang memiliki riwayat/aktifitas bahasa
$sql_main = "
    SELECT 
        s.id AS santri_id,
        s.nama,
        s.kelas,
        s.kamar,
        p.tanggal AS active_tanggal,
        jp_active.nama_pelanggaran AS active_level,
        jp_active.poin AS active_poin,
        jp_active.id AS active_level_id,
        log.tanggal_melanggar AS log_tanggal,
        jp_log.nama_pelanggaran AS log_level,
        jp_log.poin AS log_poin
    FROM santri s
    LEFT JOIN pelanggaran p ON s.id = p.santri_id 
        AND p.jenis_pelanggaran_id IN (SELECT id FROM jenis_pelanggaran WHERE bagian = 'Bahasa')
    LEFT JOIN jenis_pelanggaran jp_active ON p.jenis_pelanggaran_id = jp_active.id
    LEFT JOIN (
        SELECT l1.santri_id, l1.jenis_pelanggaran_id, l1.tanggal_melanggar
        FROM log_bahasa l1
        INNER JOIN (
            SELECT santri_id, MAX(tanggal_melanggar) as max_tgl
            FROM log_bahasa
            GROUP BY santri_id
        ) l2 ON l1.santri_id = l2.santri_id AND l1.tanggal_melanggar = l2.max_tgl
    ) log ON s.id = log.santri_id
    LEFT JOIN jenis_pelanggaran jp_log ON log.jenis_pelanggaran_id = jp_log.id
    WHERE (
        s.id IN (
            SELECT santri_id FROM pelanggaran 
            WHERE jenis_pelanggaran_id IN (SELECT id FROM jenis_pelanggaran WHERE bagian = 'Bahasa')
              AND DATE(tanggal) BETWEEN ? AND ?
        )
        OR s.id IN (
            SELECT santri_id FROM log_bahasa 
            WHERE DATE(tanggal_melanggar) BETWEEN ? AND ?
        )
    )
    {$filter_sql}
    ORDER BY s.id ASC
";

$stmt_main = mysqli_prepare($conn, $sql_main);
mysqli_stmt_bind_param($stmt_main, $types, ...$params);
mysqli_stmt_execute($stmt_main);
$result_main = mysqli_stmt_get_result($stmt_main);

$peringkat_list = [];
while ($row = mysqli_fetch_assoc($result_main)) {
    $sid = $row['santri_id'];
    
    // Evaluasi Level & Poin Saat Ini
    if ($row['active_level_id'] !== null) {
        $level_sekarang = $row['active_level'];
        $poin_sekarang = (int)$row['active_poin'];
        $level_id = $row['active_level_id'];
        $tanggal_terakhir = $row['active_tanggal'];
        
        $poin_sebelumnya = ($row['log_poin'] !== null) ? (int)$row['log_poin'] : null;
    } else {
        $level_sekarang = 'Level 0 (Bersih)';
        $poin_sekarang = 0;
        $level_id = 0; // Penanda Level 0
        $tanggal_terakhir = $row['log_tanggal'] ?? '-';
        
        $poin_sebelumnya = ($row['log_poin'] !== null) ? (int)$row['log_poin'] : null;
    }

    // Filter Level dari URL
    if ($filter_level !== '') {
        if ($filter_level == '0') {
            if ($row['active_level_id'] !== null) continue;
        } else {
            if ($row['active_level_id'] != $filter_level) continue;
        }
    }

    // Hitung Perubahan Tren Poin
    $trend_html = '';
    if ($poin_sebelumnya !== null) {
        $diff = $poin_sekarang - $poin_sebelumnya;
        if ($diff > 0) {
            $trend_html = '<small class="text-danger ms-2" title="Naik ' . $diff . ' poin"><i class="fas fa-arrow-up"></i></small>';
        } elseif ($diff < 0) {
            $trend_html = '<small class="text-success ms-2" title="Turun ' . abs($diff) . ' poin"><i class="fas fa-arrow-down"></i></small>';
        }
    }

    $peringkat_list[] = [
        'id' => $sid,
        'nama' => $row['nama'],
        'kelas' => $row['kelas'],
        'kamar' => $row['kamar'],
        'total_poin' => $poin_sekarang,
        'level_sekarang' => $level_sekarang,
        'level_id' => $level_id,
        'tanggal_terakhir' => $tanggal_terakhir,
        'trend_html' => $trend_html
    ];
}
mysqli_stmt_close($stmt_main);

// Urutkan Array berdasarkan poin tertinggi
usort($peringkat_list, function($a, $b) {
    if ($b['total_poin'] == $a['total_poin']) {
        return strcmp($a['nama'], $b['nama']); 
    }
    return $b['total_poin'] - $a['total_poin'];
});

// Data untuk Grafik
$top_5_santri = array_slice($peringkat_list, 0, 5);
$json_top_santri = json_encode([
    'labels' => array_column($top_5_santri, 'nama'),
    'data' => array_column($top_5_santri, 'total_poin')
]);

$kelas_stats = [];
foreach ($peringkat_list as $p) {
    $kls = $p['kelas'];
    if (!isset($kelas_stats[$kls])) $kelas_stats[$kls] = 0;
    $kelas_stats[$kls]++;
}
arsort($kelas_stats);
$json_kelas_chart = json_encode([
    'labels' => array_keys($kelas_stats),
    'data' => array_values($kelas_stats)
]);

// Render body tabel
ob_start();
?>
<?php if (empty($peringkat_list)): ?>
    <tr><td colspan="5" class="text-center p-5 text-muted"><i class="fas fa-check-circle fa-3x mb-3"></i><br>Alhamdulillah, tidak ada pelanggaran bahasa sesuai filter.</td></tr>
<?php else: ?>
    <?php foreach ($peringkat_list as $index => $row): 
        $no = $index + 1; 
        $level_singkat = str_ireplace(['(Bahasa)', '(bahasa)'], '', $row['level_sekarang']);
        $level_singkat = trim($level_singkat); 
    ?>
    <tr class="rank-<?= $no ?>">
        <td class="text-center">
            <?php if ($no <= 3): ?>
                <i class="fas fa-trophy rank-icon"></i>
            <?php else: ?>
                <span class="fw-bold fs-5"><?= $no ?></span>
            <?php endif; ?>
        </td>
        <td>
            <div class="fw-bold"><?= htmlspecialchars($row['nama']) ?></div>
            <small class="text-muted">Kls: <?= htmlspecialchars($row['kelas']) ?> | Kmr: <?= htmlspecialchars($row['kamar']) ?></small>
        </td>
        <td class="text-center">
            <div class="d-flex align-items-center justify-content-center">
                <span class="total-poin">
                    <?= $row['total_poin'] ?>
                </span>
                <?= $row['trend_html'] ?>
            </div>
        </td>
        <td class="text-center">
            <?php 
                $is_clean = ($row['level_id'] == 0);
                $badge_class = $is_clean ? 'level-badge-clean' : 'level-badge';
            ?>
            <span class="<?= $badge_class ?>">
                <?= htmlspecialchars($level_singkat) ?>
            </span>
        </td>
        <td class="text-center">
            <?php 
            $detail_link = "detail-bahasa.php?santri_id={$row['id']}&start_date=$start_date&end_date=$end_date&kamar=" . urlencode($filter_kamar) . "&kelas=" . urlencode($filter_kelas);
            ?>
            <a href="<?= $detail_link ?>" class="btn btn-sm btn-detail rounded-pill px-3">
                <i class="fas fa-info-circle me-1"></i> Detail
            </a>
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
        'top_santri' => [
            'labels' => array_column($top_5_santri, 'nama'),
            'data' => array_column($top_5_santri, 'total_poin')
        ],
        'kelas_chart' => [
            'labels' => array_keys($kelas_stats),
            'data' => array_values($kelas_stats)
        ]
    ]);
    exit;
}
?>

<style>
    :root {
        --primary: #4f46e5; --primary-light: #e0e7ff; --primary-dark: #4338ca;
        --secondary: #64748b; --light-bg: #f8fafc; --card-bg: #ffffff;
        --border-color: #e2e8f0; --text-dark: #1e293b; --text-light: #64748b;
        --gold: #f59e0b; --silver: #9ca3af; --bronze: #a16207;
    }
    .card { background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem; box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.05); }
    .page-title { color: var(--text-dark); font-weight: 700; }
    .form-control, .form-select { width: 100%; padding: 0.5rem 0.75rem; border: 1px solid var(--border-color); border-radius: 0.375rem; }
    .table th { background-color: var(--light-bg); color: var(--text-light); text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em; }
    .table td { vertical-align: middle; }
    .rank-icon { font-size: 1.5rem; }
    .rank-1 .rank-icon { color: var(--gold); }
    .rank-2 .rank-icon { color: var(--silver); }
    .rank-3 .rank-icon { color: var(--bronze); }
    .total-poin { font-size: 1.25rem; font-weight: 700; color: var(--primary); }
    .btn-detail { background-color: var(--primary-light); color: var(--primary); font-weight: 600; text-decoration: none; transition: all 0.2s; }
    .btn-detail:hover { background-color: var(--primary); color: white; }
    
    .level-badge {
        background-color: #ffe4e6; 
        color: #e11d48; 
        border: 1px solid #fecdd3; 
        font-weight: 600;
        padding: 6px 16px;
        border-radius: 50px;
        font-size: 0.85rem;
        display: inline-block;
        white-space: nowrap;
    }
    .level-badge-clean {
        background-color: #f0fdf4; 
        color: #16a34a; 
        border: 1px solid #dcfce7; 
        font-weight: 600;
        padding: 6px 16px;
        border-radius: 50px;
        font-size: 0.85rem;
        display: inline-block;
        white-space: nowrap;
    }
</style>
<div class="container py-4">
    <h1 class="page-title mb-4"><i class="fas fa-crown me-3"></i>Rekap Pelanggaran Bahasa</h1>

    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title fw-bold mb-3"><i class="fas fa-filter me-2"></i>Filter Data</h5>
            <form method="get" id="filterForm" class="row g-3 align-items-end">
                <div class="col-lg-2 col-md-6">
                    <label for="start_date" class="form-label">Dari Tanggal</label>
                    <input type="date" class="form-control" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
                </div>
                <div class="col-lg-2 col-md-6">
                    <label for="end_date" class="form-label">Sampai Tanggal</label>
                    <input type="date" class="form-control" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
                </div>
                <div class="col-lg-2 col-md-6">
                    <label for="kamar" class="form-label">Kamar</label>
                    <select name="kamar" id="kamar" class="form-select">
                        <option value="">Semua Kamar</option>
                        <?php mysqli_data_seek($kamars_result, 0); while ($k = mysqli_fetch_assoc($kamars_result)): ?>
                        <option value="<?= htmlspecialchars($k['kamar']) ?>" <?= ($filter_kamar == $k['kamar']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($k['kamar']) ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-6">
                    <label for="kelas" class="form-label">Kelas</label>
                    <select name="kelas" id="kelas" class="form-select">
                        <option value="">Semua Kelas</option>
                        <?php mysqli_data_seek($kelas_list, 0); while ($k = mysqli_fetch_assoc($kelas_list)): ?>
                        <option value="<?= htmlspecialchars($k['kelas']) ?>" <?= ($filter_kelas == $k['kelas']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($k['kelas']) ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-lg-4 col-md-12">
                    <label for="level" class="form-label">Level Bahasa</label>
                    <select name="level" id="level" class="form-select">
                        <option value="">Semua Level</option>
                        <option value="0" <?= ($filter_level === '0') ? 'selected' : '' ?>>Level 0 (Bersih)</option>
                        <?php mysqli_data_seek($levels_result, 0); while ($l = mysqli_fetch_assoc($levels_result)): ?>
                        <option value="<?= htmlspecialchars($l['id']) ?>" <?= ($filter_level == $l['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars(trim(str_ireplace(['(Bahasa)', '(bahasa)'], '', $l['nama_pelanggaran']))) ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </form>
        </div>
    </div>
    
    <div class="row g-4 mb-4">
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title fw-bold mb-3">Top 5 Santri (berdasarkan Poin)</h5>
                    <div style="height: 300px;"><canvas id="chartTopSantri"></canvas></div>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title fw-bold mb-3">Sebaran Pelanggaran per Kelas</h5>
                    <div style="height: 300px;"><canvas id="chartKelas"></canvas></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th class="text-center">Peringkat</th>
                        <th>Santri</th>
                        <th class="text-center">Total Poin</th>
                        <th class="text-center">Level Saat Ini</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody id="rekapTableBody">
                    <?= $table_tbody_html ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let chartTop = null;
let chartKelas = null;

document.addEventListener('DOMContentLoaded', function () {
    const dataTopSantri = <?= $json_top_santri ?>;
    const ctxTopSantri = document.getElementById('chartTopSantri').getContext('2d');
    chartTop = new Chart(ctxTopSantri, { 
        type: 'pie', 
        data: { 
            labels: dataTopSantri.labels || [], 
            datasets: [{ 
                label: 'Total Poin', 
                data: dataTopSantri.data || [], 
                backgroundColor: ['#4f46e5', '#f59e0b', '#dc3545', '#198754', '#6c757d'], 
                hoverOffset: 4 
            }] 
        }, 
        options: { 
            responsive: true, 
            maintainAspectRatio: false, 
            plugins: { 
                legend: { position: 'top' } 
            } 
        } 
    });

    const dataKelas = <?= $json_kelas_chart ?>;
    const ctxKelas = document.getElementById('chartKelas').getContext('2d');
    chartKelas = new Chart(ctxKelas, { 
        type: 'doughnut', 
        data: { 
            labels: dataKelas.labels || [], 
            datasets: [{ 
                data: dataKelas.data || [], 
                backgroundColor: ['#0d6efd', '#6f42c1', '#d63384', '#fd7e14', '#198754', '#0dcaf0'], 
                hoverOffset: 4 
            }] 
        }, 
        options: { 
            responsive: true, 
            maintainAspectRatio: false 
        } 
    });
});

document.addEventListener('DOMContentLoaded', function() {
    const filterForm = document.getElementById('filterForm');
    const tbody = document.getElementById('rekapTableBody');

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

            if (chartTop) {
                chartTop.data.labels = data.top_santri.labels;
                chartTop.data.datasets[0].data = data.top_santri.data;
                chartTop.update();
            }

            if (chartKelas) {
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

    const filterInputs = filterForm.querySelectorAll('select, input[type="date"]');
    filterInputs.forEach(input => {
        input.addEventListener('change', updateData);
    });
});
</script>

<?php 
if (!$is_ajax) {
    require_once __DIR__ . '/../../layouts/footer.php'; 
}
?>
</body>
</html>