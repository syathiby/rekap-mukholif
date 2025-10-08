<?php
require_once __DIR__ . '/../../header.php';
guard('rekap_view_diniyyah');

$bagian = 'Diniyyah';

// =======================================================
// BAGIAN 1: PERSIAPAN DATA & FILTER
// =======================================================

$q_periode = mysqli_query($conn, "SELECT nilai FROM pengaturan WHERE nama = 'periode_aktif' LIMIT 1");
$periode_aktif = mysqli_fetch_assoc($q_periode)['nilai'] ?? null;

if (!$periode_aktif) {
    die("<div class='container my-4'><div class='alert alert-warning'><strong>Peringatan:</strong> Periode aktif belum diatur. Silakan atur di Pengaturan.</div></div>");
}

// ✅ PERUBAHAN: Ambil data untuk semua filter dropdown
$kamars_result = mysqli_query($conn, "SELECT DISTINCT kamar FROM santri WHERE kamar IS NOT NULL AND kamar != '' ORDER BY CAST(kamar AS UNSIGNED) ASC");
$kelas_list = mysqli_query($conn, "SELECT DISTINCT CAST(kelas AS UNSIGNED) AS kelas FROM santri WHERE kelas IS NOT NULL AND kelas != '' ORDER BY kelas ASC");

// ✅ PERUBAHAN: Ambil semua filter dari URL
$filter_kamar = $_GET['kamar'] ?? '';
$filter_kelas = $_GET['kelas'] ?? ''; // Tambahan filter kelas
$start_date = $_GET['start_date'] ?? $periode_aktif;
$end_date = $_GET['end_date'] ?? date('Y-m-d');


// =======================================================
// BAGIAN 2: QUERY UTAMA (PERINGKAT & GRAFIK)
// =======================================================

// ✅ PERBAIKAN: Siapkan parameter sekali untuk semua query
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

// Query Peringkat
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
    GROUP BY s.id, s.nama, s.kelas, s.kamar
    HAVING total_pelanggaran > 0
    ORDER BY total_poin DESC, total_pelanggaran DESC, s.nama ASC
";
$stmt_peringkat = mysqli_prepare($conn, $sql_peringkat);
mysqli_stmt_bind_param($stmt_peringkat, $base_types, ...$base_params);
mysqli_stmt_execute($stmt_peringkat);
$result_peringkat = mysqli_stmt_get_result($stmt_peringkat);
$peringkat_list = mysqli_fetch_all($result_peringkat, MYSQLI_ASSOC);

// Data untuk Grafik 1: Top 5 Santri
$top_5_santri = array_slice($peringkat_list, 0, 5);
$json_top_santri = json_encode([
    'labels' => array_column($top_5_santri, 'nama'),
    'data' => array_column($top_5_santri, 'total_poin')
]);

// Data untuk Grafik 2: Sebaran per Kelas
$sql_kelas_chart = "
    SELECT s.kelas, COUNT(p.id) as total
    FROM pelanggaran p
    JOIN santri s ON p.santri_id = s.id
    JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id
    WHERE jp.bagian = ? AND DATE(p.tanggal) BETWEEN ? AND ?
    {$extra_sql}
    GROUP BY s.kelas ORDER BY total DESC
";
$stmt_kelas_chart = mysqli_prepare($conn, $sql_kelas_chart);
mysqli_stmt_bind_param($stmt_kelas_chart, $base_types, ...$base_params);
mysqli_stmt_execute($stmt_kelas_chart);
$result_kelas_chart = mysqli_stmt_get_result($stmt_kelas_chart);
$data_kelas_chart = mysqli_fetch_all($result_kelas_chart, MYSQLI_ASSOC);

$json_kelas_chart = json_encode([
    'labels' => array_column($data_kelas_chart, 'kelas'),
    'data' => array_column($data_kelas_chart, 'total')
]);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Peringkat Pelanggaran Diniyyah</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #198754; --primary-light: #d1e7dd; --primary-dark: #157347;
            --secondary: #64748b; --light-bg: #f8fafc; --card-bg: #ffffff;
            --border-color: #e2e8f0; --text-dark: #1e293b; --text-light: #64748b;
            --gold: #f59e0b; --silver: #9ca3af; --bronze: #a16207;
        }
        body { background-color: var(--light-bg); font-family: 'Poppins', sans-serif; }
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
    </style>
</head>
<body>
<div class="container py-4">
    <h1 class="page-title mb-4"><i class="fas fa-book-quran me-3"></i>Peringkat Pelanggaran Diniyyah</h1>

    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title fw-bold mb-3"><i class="fas fa-filter me-2"></i>Filter Data</h5>
            <form method="get" id="filterForm" class="row g-3 align-items-end">
                <div class="col-lg-3 col-md-6">
                    <label for="start_date" class="form-label">Dari Tanggal</label>
                    <input type="date" class="form-control" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
                </div>
                <div class="col-lg-3 col-md-6">
                    <label for="end_date" class="form-label">Sampai Tanggal</label>
                    <input type="date" class="form-control" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
                </div>
                <div class="col-lg-3 col-md-6">
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
                <div class="col-lg-3 col-md-6">
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
                        <th class="text-center">Jumlah Pelanggaran</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($peringkat_list)): ?>
                        <tr><td colspan="5" class="text-center p-5 text-muted"><i class="fas fa-check-circle fa-3x mb-3"></i><br>Alhamdulillah, tidak ada pelanggaran Diniyyah ditemukan.</td></tr>
                    <?php else: ?>
                        <?php foreach ($peringkat_list as $index => $row): $no = $index + 1; ?>
                        <tr class="rank-<?= $no ?>">
                            <td class="text-center">
                                <?php if ($no <= 3): ?><i class="fas fa-trophy rank-icon"></i><?php else: ?><span class="fw-bold fs-5"><?= $no ?></span><?php endif; ?>
                            </td>
                            <td>
                                <div class="fw-bold"><?= htmlspecialchars($row['nama']) ?></div>
                                <small class="text-muted">Kls: <?= htmlspecialchars($row['kelas']) ?> | Kmr: <?= htmlspecialchars($row['kamar']) ?></small>
                            </td>
                            <td class="text-center">
                                <span class="total-poin"><?= $row['total_poin'] ?></span>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-secondary rounded-pill"><?= $row['total_pelanggaran'] ?></span>
                            </td>
                            <td class="text-center">
                                <?php // ✅ PERUBAHAN: Link detail membawa semua filter
                                $detail_link = "detail-diniyyah.php?santri_id={$row['id']}&start_date=$start_date&end_date=$end_date&kamar=" . urlencode($filter_kamar) . "&kelas=" . urlencode($filter_kelas);
                                ?>
                                <a href="<?= $detail_link ?>" class="btn btn-sm btn-detail rounded-pill px-3">
                                    <i class="fas fa-info-circle me-1"></i> Detail
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Chart rendering script (tidak berubah)
document.addEventListener('DOMContentLoaded', function () {
    const dataTopSantri = <?= $json_top_santri ?>;
    const ctxTopSantri = document.getElementById('chartTopSantri').getContext('2d');
    if (dataTopSantri.labels.length > 0) {
        new Chart(ctxTopSantri, { type: 'pie', data: { labels: dataTopSantri.labels, datasets: [{ label: 'Total Poin', data: dataTopSantri.data, backgroundColor: ['#198754', '#f59e0b', '#dc3545', '#6c757d', '#0dcaf0'], hoverOffset: 4 }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'top' } } } });
    }

    const dataKelas = <?= $json_kelas_chart ?>;
    const ctxKelas = document.getElementById('chartKelas').getContext('2d');
    if (dataKelas.labels.length > 0) {
        new Chart(ctxKelas, { type: 'doughnut', data: { labels: dataKelas.labels, datasets: [{ data: dataKelas.data, backgroundColor: ['#198754', '#6f42c1', '#d63384', '#fd7e14', '#157347', '#0dcaf0'], hoverOffset: 4 }] }, options: { responsive: true, maintainAspectRatio: false } });
    }
});

// ✅ TAMBAHAN: Script untuk auto-submit filter
document.addEventListener('DOMContentLoaded', function() {
    const filterForm = document.getElementById('filterForm');
    const filterInputs = filterForm.querySelectorAll('select, input[type="date"]');
    filterInputs.forEach(input => {
        input.addEventListener('change', () => filterForm.submit());
    });
});
</script>

<?php require_once __DIR__ . '/../../footer.php'; ?>
</body>
</html>