<?php 
require_once __DIR__ . '/../../header.php';
// Sesuaikan guard dengan izin yang benar
guard('rekap_view_tahfidz'); 
?>

<?php

$bagian = 'Tahfidz';

// =======================================================
// BAGIAN 1: PERSIAPAN DATA & FILTER (DENGAN LOGIKA PERIODE AKTIF)
// =======================================================

$q_periode = mysqli_query($conn, "SELECT nilai FROM pengaturan WHERE nama = 'periode_aktif' LIMIT 1");
$periode_aktif = mysqli_fetch_assoc($q_periode)['nilai'] ?? null;

if (!$periode_aktif) {
    die("<div class='container my-4'><div class='alert alert-warning'><strong>Peringatan:</strong> Periode aktif belum diatur. Silakan atur terlebih dahulu di halaman Pengaturan agar data yang tampil akurat.</div></div>");
}

$kamars_result = mysqli_query($conn, "SELECT DISTINCT kamar FROM santri WHERE kamar IS NOT NULL AND kamar != '' ORDER BY CAST(kamar AS UNSIGNED) ASC");
$filter_kamar = $_GET['kamar'] ?? '';
$start_date = $_GET['start_date'] ?? $periode_aktif; 
$end_date = $_GET['end_date'] ?? date('Y-m-d');


// =======================================================
// BAGIAN 2: QUERY UTAMA (MODEL PERINGKAT)
// =======================================================

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
";

$params = [$bagian, $start_date, $end_date];
$types = "sss";

if (!empty($filter_kamar)) {
    $sql_peringkat .= " AND s.kamar = ?";
    $params[] = $filter_kamar;
    $types .= "s";
}

$sql_peringkat .= " 
    GROUP BY s.id, s.nama, s.kelas, s.kamar
    HAVING total_pelanggaran > 0
    ORDER BY total_poin DESC, total_pelanggaran DESC, s.nama ASC
";

$stmt_peringkat = mysqli_prepare($conn, $sql_peringkat);
mysqli_stmt_bind_param($stmt_peringkat, $types, ...$params);
mysqli_stmt_execute($stmt_peringkat);
$result_peringkat = mysqli_stmt_get_result($stmt_peringkat);
$peringkat_list = mysqli_fetch_all($result_peringkat, MYSQLI_ASSOC);

// =======================================================
// BAGIAN 3: PERSIAPAN DATA UNTUK GRAFIK
// =======================================================

$top_5_santri = array_slice($peringkat_list, 0, 5);
$json_top_santri = json_encode([
    'labels' => array_column($top_5_santri, 'nama'),
    'data' => array_column($top_5_santri, 'total_poin')
]);

$sql_kelas_chart = "
    SELECT s.kelas, COUNT(p.id) as total
    FROM pelanggaran p
    JOIN santri s ON p.santri_id = s.id
    JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id
    WHERE jp.bagian = ? AND DATE(p.tanggal) BETWEEN ? AND ?
";
if (!empty($filter_kamar)) {
    $sql_kelas_chart .= " AND s.kamar = ?";
}
$sql_kelas_chart .= " GROUP BY s.kelas ORDER BY total DESC";

$stmt_kelas_chart = mysqli_prepare($conn, $sql_kelas_chart);
mysqli_stmt_bind_param($stmt_kelas_chart, $types, ...$params);
mysqli_stmt_execute($stmt_kelas_chart);
$result_kelas_chart = mysqli_stmt_get_result($stmt_kelas_chart);
$data_kelas_chart = mysqli_fetch_all($result_kelas_chart, MYSQLI_ASSOC);

$json_kelas_chart = json_encode([
    'labels' => array_column($data_kelas_chart, 'kelas'),
    'data' => array_column($data_kelas_chart, 'total')
]);
?>

<!-- ======================================================= -->
<!-- BAGIAN 4: TAMPILAN HTML DENGAN TEMA BARU -->
<!-- ======================================================= -->
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekap Pelanggaran Tahfidz</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* === PERUBAHAN TEMA WARNA === */
        :root {
            --primary: #dc3545; /* Merah (danger) */
            --primary-light: #f8d7da; /* Merah muda */
            --primary-dark: #b02a37; /* Merah tua */
            --secondary: #6c757d; 
            --light-bg: #f8fafc; 
            --card-bg: #ffffff;
            --border-color: #e2e8f0; 
            --text-dark: #1e293b; 
            --text-light: #64748b;
            --gold: #f59e0b; 
            --silver: #9ca3af; 
            --bronze: #a16207;
        }
        body { background-color: var(--light-bg); font-family: 'Poppins', sans-serif; }
        .card { background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem; box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.05); }
        .page-title { color: var(--text-dark); font-weight: 700; }
        .kamar-nav a { padding: 0.5rem 1rem; border-radius: 9999px; text-decoration: none; color: var(--secondary); font-weight: 500; transition: all 0.2s; }
        .kamar-nav a:hover { background-color: var(--primary-light); color: var(--primary); }
        .kamar-nav .active { background-color: var(--primary); color: white; }
        .table th { background-color: var(--light-bg); color: var(--text-light); text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em; }
        .table td { vertical-align: middle; }
        .rank-icon { font-size: 1.5rem; }
        .rank-1 .rank-icon { color: var(--gold); }
        .rank-2 .rank-icon { color: var(--silver); }
        .rank-3 .rank-icon { color: var(--bronze); }
        .total-poin { font-size: 1.25rem; font-weight: 700; color: var(--primary); }
        .btn-detail { background-color: var(--primary-light); color: var(--primary); font-weight: 600; text-decoration: none; transition: all 0.2s; }
        .btn-detail:hover { background-color: var(--primary); color: white; }
        .btn-primary { background-color: var(--primary); border-color: var(--primary); }
        .btn-primary:hover { background-color: var(--primary-dark); border-color: var(--primary-dark); }
    </style>
</head>
<body>
<div class="container py-4">
    <!-- === PERUBAHAN JUDUL & IKON === -->
    <h1 class="page-title mb-4"><i class="fas fa-book-reader me-3"></i>Rekap Pelanggaran Tahfidz</h1>

    <!-- Filter Card -->
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title fw-bold mb-3"><i class="fas fa-filter me-2"></i>Filter Data</h5>
            <form method="get" class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label for="start_date" class="form-label">Dari Tanggal</label>
                    <input type="date" class="form-control" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
                </div>
                <div class="col-md-5">
                    <label for="end_date" class="form-label">Sampai Tanggal</label>
                    <input type="date" class="form-control" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search"></i> Terapkan</button>
                </div>
                <?php if ($filter_kamar): ?>
                    <input type="hidden" name="kamar" value="<?= htmlspecialchars($filter_kamar) ?>">
                <?php endif; ?>
            </form>
            <hr class="my-4">
            <div class="kamar-nav d-flex flex-wrap gap-2">
                <a href="?start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" class="<?= !$filter_kamar ? 'active' : '' ?>">Semua Kamar</a>
                <?php mysqli_data_seek($kamars_result, 0); // Reset pointer query kamar ?>
                <?php while ($k = mysqli_fetch_assoc($kamars_result)): ?>
                    <a href="?kamar=<?= urlencode($k['kamar']) ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" class="<?= ($filter_kamar == $k['kamar']) ? 'active' : '' ?>">
                        <?= htmlspecialchars($k['kamar']) ?>
                    </a>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
    
    <!-- Grafik Card -->
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

    <!-- Peringkat Card -->
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
                        <tr><td colspan="5" class="text-center p-5 text-muted"><i class="fas fa-check-circle fa-3x mb-3"></i><br>Alhamdulillah, tidak ada pelanggaran Tahfidz ditemukan.</td></tr>
                    <?php else: ?>
                        <?php foreach ($peringkat_list as $index => $row): $no = $index + 1; ?>
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
                                <span class="total-poin"><?= $row['total_poin'] ?></span>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-secondary rounded-pill"><?= $row['total_pelanggaran'] ?></span>
                            </td>
                            <td class="text-center">
                                <a href="detail-tahfidz.php?santri_id=<?= $row['id'] ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" class="btn btn-sm btn-detail rounded-pill px-3">
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
document.addEventListener('DOMContentLoaded', function () {
    const dataTopSantri = <?= $json_top_santri ?>;
    const ctxTopSantri = document.getElementById('chartTopSantri').getContext('2d');
    if (dataTopSantri.labels.length > 0) {
        new Chart(ctxTopSantri, {
            type: 'pie', // Diubah lagi jadi chart bulet (pie)
            data: {
                labels: dataTopSantri.labels,
                datasets: [{
                    label: 'Total Poin',
                    data: dataTopSantri.data,
                    backgroundColor: ['#dc3545', '#fd7e14', '#ffc107', '#198754', '#0dcaf0'],
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top', // Tampilkan legenda di atas
                    }
                }
            }
        });
    }

    const dataKelas = <?= $json_kelas_chart ?>;
    const ctxKelas = document.getElementById('chartKelas').getContext('2d');
    if (dataKelas.labels.length > 0) {
        new Chart(ctxKelas, {
            type: 'doughnut',
            data: {
                labels: dataKelas.labels,
                datasets: [{
                    data: dataKelas.data,
                    backgroundColor: ['#6f42c1', '#d63384', '#198754', '#0dcaf0', '#fd7e14', '#0d6efd'],
                    hoverOffset: 4
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../../footer.php'; ?>
</body>
</html>