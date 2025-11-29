<?php 
// 1. Panggil 'Otak' aplikasi dulu
require_once __DIR__ . '/../../init.php';

// 2. Jalankan 'SATPAM' buat ngejaga halaman
guard('rekap_view_bahasa'); 

// 3. Kalau lolos, baru panggil Tampilan
require_once __DIR__ . '/../../header.php';

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
// BAGIAN 2: QUERY UTAMA (SNAPSHOT LOGIC)
// =======================================================

// Kita ambil SEMUA santri dulu sesuai filter kamar/kelas
// Nanti logic "Level Terakhir" kita hitung pakai Subquery UNION biar akurat sesuai tanggal

$params = []; 
$types = ""; 

$sql_base = "SELECT id, nama, kelas, kamar FROM santri WHERE 1=1";

if (!empty($filter_kamar)) {
    $sql_base .= " AND kamar = ?";
    $params[] = $filter_kamar;
    $types .= "s";
}
if (!empty($filter_kelas)) {
    $sql_base .= " AND kelas = ?";
    $params[] = $filter_kelas;
    $types .= "s";
}

$stmt_base = mysqli_prepare($conn, $sql_base);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt_base, $types, ...$params);
}
mysqli_stmt_execute($stmt_base);
$result_santri = mysqli_stmt_get_result($stmt_base);

$peringkat_list = [];

// =======================================================
// BAGIAN 3: PROSES DATA (Mencari Status Terakhir per Santri)
// =======================================================

// Siapkan Query buat nyari "Last Status" di rentang tanggal
// Kita gabung tabel PELANGGARAN (Aktif) + LOG_BAHASA (Riwayat)
// Ambil yang tanggalnya paling akhir di dalam filter
$sql_snapshot = "
    SELECT 
        u.tanggal, 
        jp.nama_pelanggaran, 
        jp.poin, 
        jp.id as level_id
    FROM (
        SELECT jenis_pelanggaran_id, tanggal, santri_id FROM pelanggaran 
        UNION ALL
        SELECT jenis_pelanggaran_id, tanggal_melanggar as tanggal, santri_id FROM log_bahasa
    ) AS u
    JOIN jenis_pelanggaran jp ON u.jenis_pelanggaran_id = jp.id
    WHERE u.santri_id = ? 
      AND jp.bagian = ?
      AND DATE(u.tanggal) BETWEEN ? AND ?
    ORDER BY u.tanggal DESC 
    LIMIT 1
";
$stmt_snapshot = mysqli_prepare($conn, $sql_snapshot);

while ($santri = mysqli_fetch_assoc($result_santri)) {
    // Cek status terakhir si santri ini di rentang tanggal yg dipilih
    mysqli_stmt_bind_param($stmt_snapshot, "isss", $santri['id'], $bagian, $start_date, $end_date);
    mysqli_stmt_execute($stmt_snapshot);
    $res_snapshot = mysqli_stmt_get_result($stmt_snapshot);
    $snapshot = mysqli_fetch_assoc($res_snapshot);

    // Kalau ada data pelanggaran di rentang tgl tsb
    if ($snapshot) {
        // Cek Filter Level (Kalau user milih level tertentu)
        if (!empty($filter_level) && $snapshot['level_id'] != $filter_level) {
            continue; // Skip kalau levelnya gak cocok sama filter
        }

        // Masukin ke list buat ditampilin
        $peringkat_list[] = [
            'id' => $santri['id'],
            'nama' => $santri['nama'],
            'kelas' => $santri['kelas'],
            'kamar' => $santri['kamar'],
            'total_poin' => $snapshot['poin'], // Poin sesuai saat itu
            'level_sekarang' => $snapshot['nama_pelanggaran'], // Level sesuai saat itu
            'tanggal_terakhir' => $snapshot['tanggal']
        ];
    }
}

// Urutkan Array (Poin Tertinggi diatas)
usort($peringkat_list, function($a, $b) {
    if ($b['total_poin'] == $a['total_poin']) {
        return strcmp($a['nama'], $b['nama']); // Kalau poin sama, urut abjad
    }
    return $b['total_poin'] - $a['total_poin'];
});

// Data untuk Grafik (Diambil dari array yang udah diproses)
$top_5_santri = array_slice($peringkat_list, 0, 5);
$json_top_santri = json_encode([
    'labels' => array_column($top_5_santri, 'nama'),
    'data' => array_column($top_5_santri, 'total_poin')
]);

// Hitung sebaran kelas manual dari array hasil
$kelas_stats = [];
foreach ($peringkat_list as $p) {
    $kls = $p['kelas'];
    if (!isset($kelas_stats[$kls])) $kelas_stats[$kls] = 0;
    $kelas_stats[$kls]++;
}
arsort($kelas_stats); // Urutkan kelas terbanyak
$json_kelas_chart = json_encode([
    'labels' => array_keys($kelas_stats),
    'data' => array_values($kelas_stats)
]);

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekap Pelanggaran Bahasa</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4f46e5; --primary-light: #e0e7ff; --primary-dark: #4338ca;
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
        
        /* ✅ REVISI WARNA: Soft Red (Merah Kalem) */
        .level-badge {
            background-color: #fee2e2; /* Background merah muda lembut */
            color: #991b1b; /* Teks merah tua */
            border: 1px solid #fecaca; /* Border tipis */
            font-weight: 600;
            padding: 6px 16px;
            border-radius: 50px;
            font-size: 0.85rem;
            display: inline-block;
            white-space: nowrap;
        }
    </style>
</head>
<body>
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
                        <?php mysqli_data_seek($levels_result, 0); while ($l = mysqli_fetch_assoc($levels_result)): ?>
                        <option value="<?= htmlspecialchars($l['id']) ?>" <?= ($filter_level == $l['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($l['nama_pelanggaran']) ?>
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
                <tbody>
                    <?php if (empty($peringkat_list)): ?>
                        <tr><td colspan="5" class="text-center p-5 text-muted"><i class="fas fa-check-circle fa-3x mb-3"></i><br>Alhamdulillah, tidak ada pelanggaran bahasa sesuai filter.</td></tr>
                    <?php else: ?>
                        <?php foreach ($peringkat_list as $index => $row): 
                            $no = $index + 1; 
                            // ✅ REVISI TEKS: Hapus kata (Bahasa) biar singkat
                            $level_singkat = str_ireplace(['(Bahasa)', '(bahasa)'], '', $row['level_sekarang']);
                            $level_singkat = trim($level_singkat); // Hapus spasi sisa
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
                                <span class="total-poin">
                                    <?= $row['total_poin'] ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <!-- ✅ REVISI TAMPILAN: Soft Red & Teks Singkat -->
                                <span class="level-badge">
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
        new Chart(ctxTopSantri, { type: 'pie', data: { labels: dataTopSantri.labels, datasets: [{ label: 'Total Poin', data: dataTopSantri.data, backgroundColor: ['#4f46e5', '#f59e0b', '#dc3545', '#198754', '#6c757d'], hoverOffset: 4 }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'top' } } } });
    }

    const dataKelas = <?= $json_kelas_chart ?>;
    const ctxKelas = document.getElementById('chartKelas').getContext('2d');
    if (dataKelas.labels.length > 0) {
        new Chart(ctxKelas, { type: 'doughnut', data: { labels: dataKelas.labels, datasets: [{ data: dataKelas.data, backgroundColor: ['#0d6efd', '#6f42c1', '#d63384', '#fd7e14', '#198754', '#0dcaf0'], hoverOffset: 4 }] }, options: { responsive: true, maintainAspectRatio: false } });
    }
});

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