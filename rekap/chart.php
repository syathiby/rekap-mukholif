<?php
require_once __DIR__ . '/../header.php';
guard('rekap_view_statistik');

// Ambil periode aktif dari pengaturan
$q = mysqli_query($conn, "SELECT nilai FROM pengaturan WHERE nama = 'periode_aktif' LIMIT 1");
$row = mysqli_fetch_assoc($q);
$default_periode_aktif = $row['nilai'] ?? date('Y-m-d', strtotime('-1 year'));

// Menangkap nilai filter tanggal dari URL
$tgl_mulai = $_GET['tgl_mulai'] ?? $default_periode_aktif;
$tgl_selesai = $_GET['tgl_selesai'] ?? date('Y-m-d');

// 1. Total Pelanggaran
$stmt_total1 = $conn->prepare("SELECT COUNT(*) as total FROM pelanggaran WHERE tanggal >= ? AND tanggal <= ?");
$stmt_total1->bind_param("ss", $tgl_mulai, $tgl_selesai);
$stmt_total1->execute();
$total1 = $stmt_total1->get_result()->fetch_assoc()['total'];
$stmt_total2 = $conn->prepare("SELECT COUNT(*) as total FROM pelanggaran_kebersihan WHERE tanggal >= ? AND tanggal <= ?");
$stmt_total2->bind_param("ss", $tgl_mulai, $tgl_selesai);
$stmt_total2->execute();
$total2 = $stmt_total2->get_result()->fetch_assoc()['total'];
$total_pelanggaran = $total1 + $total2;

// 2. Top 10 Santri
$stmt_santri = $conn->prepare("SELECT nama, poin_aktif FROM santri WHERE poin_aktif > 0 ORDER BY poin_aktif DESC LIMIT 10");
$stmt_santri->execute();
$q_santri = $stmt_santri->get_result();

// 3. Data Jenis Pelanggaran
$stmt_jenis1 = $conn->prepare("SELECT j.nama_pelanggaran, COUNT(*) as total FROM pelanggaran p JOIN jenis_pelanggaran j ON p.jenis_pelanggaran_id = j.id WHERE p.tanggal >= ? AND p.tanggal <= ? GROUP BY j.id");
$stmt_jenis1->bind_param("ss", $tgl_mulai, $tgl_selesai);
$stmt_jenis1->execute();
$data_jenis_pelanggaran = $stmt_jenis1->get_result()->fetch_all(MYSQLI_ASSOC);
if ($total2 > 0) { $data_jenis_pelanggaran[] = ['nama_pelanggaran' => 'KEBERSIHAN KAMAR', 'total' => $total2]; }
usort($data_jenis_pelanggaran, fn($a, $b) => $b['total'] <=> $a['total']);
$total_semua_jenis = array_sum(array_column($data_jenis_pelanggaran, 'total'));

// 4. Data Per Kelas
$stmt_kelas = $conn->prepare("SELECT s.kelas, COUNT(*) AS total FROM pelanggaran p JOIN santri s ON s.id = p.santri_id WHERE p.tanggal >= ? AND p.tanggal <= ? GROUP BY s.kelas ORDER BY total DESC");
$stmt_kelas->bind_param("ss", $tgl_mulai, $tgl_selesai);
$stmt_kelas->execute();
$data_per_kelas = $stmt_kelas->get_result()->fetch_all(MYSQLI_ASSOC);
$json_per_kelas = json_encode(['labels' => array_column($data_per_kelas, 'kelas'), 'data' => array_column($data_per_kelas, 'total')]);

// 5. Data Per Kamar
$stmt_kamar = $conn->prepare("SELECT kamar, COUNT(*) AS total FROM pelanggaran_kebersihan WHERE tanggal >= ? AND tanggal <= ? GROUP BY kamar ORDER BY total DESC");
$stmt_kamar->bind_param("ss", $tgl_mulai, $tgl_selesai);
$stmt_kamar->execute();
$data_per_kamar = $stmt_kamar->get_result()->fetch_all(MYSQLI_ASSOC);
$labels_kamar = array_map(fn($item) => "Kamar " . $item['kamar'], $data_per_kamar);
$json_per_kamar = json_encode(['labels' => $labels_kamar, 'data' => array_column($data_per_kamar, 'total')]);

// 6. DATA SEBARAN PER BAGIAN 
// =================================================================
// INI BAGIAN YANG HILANG & DITAMBAHKAN KEMBALI
// =================================================================
$stmt_bagian = $conn->prepare("
    SELECT jp.bagian, COUNT(p.id) AS total 
    FROM pelanggaran p 
    JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id 
    WHERE p.tanggal >= ? AND p.tanggal <= ? 
    GROUP BY jp.bagian 
    ORDER BY total DESC
");
// =================================================================

// Baris ke-7 (sekarang) yang error tadi, sekarang jadi valid
$stmt_bagian->bind_param("ss", $tgl_mulai, $tgl_selesai);
$stmt_bagian->execute();
$data_per_bagian = $stmt_bagian->get_result()->fetch_all(MYSQLI_ASSOC);
$json_per_bagian = json_encode(['labels' => array_column($data_per_bagian, 'bagian'), 'data' => array_column($data_per_bagian, 'total')]);
?>

<style>
    /* ... (SEMUA CSS LU DARI ATAS SAMPAI BAWAH TETAP SAMA, TIDAK ADA PERUBAHAN) ... */
    :root { --primary: #4f46e5; --primary-light: #e0e7ff; --secondary: #10b981; --accent: #ef4444; --text-dark: #111827; --text-light: #6b7280; --bg-light: #f9fafb; --border-color: #e5e7eb; --card-bg: #ffffff; --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05); --hover-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.07), 0 4px 6px -4px rgba(0, 0, 0, 0.07); }
    body { background-color: var(--bg-light); color: var(--text-dark); font-family: 'Poppins', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; }
    .dashboard-header { background-color: var(--card-bg); color: var(--text-dark); padding: 25px; border-radius: 16px; margin-bottom: 25px; border: 1px solid var(--border-color); }
    h2.dashboard-title { font-size: 26px; font-weight: 600; margin: 0 0 5px 0; display: flex; align-items: center; gap: 10px; }
    .dashboard-header p { color: var(--text-light); font-size: 15px; }
    .card.dashboard-card { background: var(--card-bg); border-radius: 16px; border: 1px solid var(--border-color); box-shadow: none; padding: 25px; display: flex; flex-direction: column; transition: border-color 0.3s ease, box-shadow 0.3s ease; }
    .card.dashboard-card:hover { border-color: var(--primary); box-shadow: var(--hover-shadow); }
    h3.card-title { font-size: 18px; margin: 0 0 20px 0; color: var(--text-dark); font-weight: 600; padding-bottom: 0; border-bottom: none; display: flex; align-items: center; gap: 8px; }
    .stat-card { text-align: center; }
    .stat-card .icon { font-size: 24px; color: var(--primary); background-color: var(--primary-light); width: 50px; height: 50px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 15px; }
    .stat-card .number { font-size: 32px; font-weight: 700; color: var(--text-dark); margin-bottom: 5px; }
    .stat-card h4 { font-size: 15px; color: var(--text-light); margin: 0; font-weight: 500; }
    .table-container { overflow-x: auto; flex-grow: 1; }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 14px 10px; text-align: left; border-bottom: 1px solid var(--border-color); white-space: nowrap; color: var(--text-dark); }
    th { background-color: transparent; font-weight: 600; color: var(--text-light); font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; }
    tr:last-child td { border-bottom: none; }
    .badge { padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; color: var(--accent); background-color: #fee2e2; }
    .date-filter-form { display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; }
    .date-input-group label { font-size: 13px; color: var(--text-light); font-weight: 500; margin-bottom: 5px; display: block; }
    .form-control, .btn { padding: 10px 15px; border-radius: 10px; font-size: 14px; transition: all 0.3s ease; }
    .form-control { border: 1px solid var(--border-color); }
    .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-light); outline: none; }
    .btn-primary { background-color: var(--primary); color: white; border: none; cursor: pointer; font-weight: 600; }
    .btn-primary:hover { background-color: #4338ca; }
    .chart-filters { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
    .chart-filters button { background-color: var(--card-bg); color: var(--text-light); border: 1px solid var(--border-color); padding: 8px 16px; border-radius: 20px; font-weight: 500; cursor: pointer; transition: all 0.3s ease; }
    .chart-filters button.active { background-color: var(--primary-light); color: var(--primary); border-color: var(--primary); font-weight: 600; }
    .progress-bar-container { background-color: #e5e7eb; border-radius: 10px; height: 8px; width: 100px; overflow: hidden; }
    .progress-bar { background-color: var(--secondary); height: 100%; border-radius: 10px; }
    
    /* CSS PERBAIKAN GRAFIK DARI SEBELUMNYA */
    .chart-container {
        position: relative;
        min-height: 380px; 
        width: 100%;
        flex-grow: 1;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    /* Kelas khusus buat card chart garis biar lebih tinggi & lega */
    .chart-container.chart-container-tall {
        min-height: 450px; /* Nilainya kita naikin biar lebih lega */
    }
    
    .chart-scroll-container {
        position: relative;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        padding-bottom: 10px;
        width: 100%;
    }
    .chart-scroll-container canvas {
        min-width: 500px;
    }

    @media (max-width: 768px) {
        .dashboard-card { padding: 20px; }
        h2.dashboard-title { font-size: 22px; }
        .stat-card .number { font-size: 28px; }
        .date-filter-form { flex-direction: column; align-items: stretch; gap: 15px; }
    }
</style>

<div class="container-fluid mt-4 mb-5">
    <div class="dashboard-header">
        <h2 class="dashboard-title"><i class="fa-solid fa-chart-pie"></i> Dashboard Pelanggaran</h2>
        <p style="margin: 0;">Ringkasan data untuk periode: <strong><?= date('d M Y', strtotime($tgl_mulai)) ?></strong> sampai <strong><?= date('d M Y', strtotime($tgl_selesai)) ?></strong></p>
    </div>

    <div class="card dashboard-card mb-4">
        <h3 class="card-title"><i class="fa-solid fa-filter"></i> Filter Data Berdasarkan Tanggal</h3>
        <form action="" method="GET" class="date-filter-form">
            <div class="date-input-group">
                <label for="tgl_mulai">Dari Tanggal</label>
                <input type="date" id="tgl_mulai" name="tgl_mulai" value="<?= htmlspecialchars($tgl_mulai) ?>" class="form-control">
            </div>
            <div class="date-input-group">
                <label for="tgl_selesai">Sampai Tanggal</label>
                <input type="date" id="tgl_selesai" name="tgl_selesai" value="<?= htmlspecialchars($tgl_selesai) ?>" class="form-control">
            </div>
            <div class="date-input-group">
                <button type="submit" class="btn btn-primary">Terapkan Filter</button>
            </div>
        </form>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-4 col-md-6">
            <div class="card dashboard-card stat-card">
                <div class="icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
                <div class="number"><?= $total_pelanggaran ?></div>
                <h4>Total Pelanggaran</h4>
            </div>
        </div>
        <div class="col-lg-4 col-md-6">
            <div class="card dashboard-card stat-card">
                <div class="icon"><i class="fa-solid fa-book"></i></div>
                <div class="number"><?= $total1 ?></div>
                <h4>Pelanggaran Umum</h4>
            </div>
        </div>
        <div class="col-lg-4 col-md-12">
            <div class="card dashboard-card stat-card">
                <div class="icon"><i class="fa-solid fa-broom"></i></div>
                <div class="number"><?= $total2 ?></div>
                <h4>Pelanggaran Kebersihan</h4>
            </div>
        </div>
    </div>

    <div class="card dashboard-card mb-4">
        <h3 class="card-title"><i class="fa-solid fa-chart-line"></i> Tren Pelanggaran Harian</h3>
        <div class="chart-filters">
            <button id="filter-7d" class="active">7 Hari</button> <button id="filter-1m">1 Bulan</button>
            <button id="filter-3m">3 Bulan</button> <button id="filter-1y">1 Tahun</button>
        </div>
        <div class="chart-container chart-container-tall">
            <div class="chart-scroll-container">
                <canvas id="chartTrenPelanggaran"></canvas>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-6">
            <div class="card dashboard-card h-100">
                <h3 class="card-title"><i class="fa-solid fa-list-check"></i> Komposisi Jenis Pelanggaran</h3>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Jenis Pelanggaran</th>
                                <th class="text-center">Jumlah</th>
                                <th class="text-center">%</th>
                                <th>Distribusi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($data_jenis_pelanggaran)): foreach ($data_jenis_pelanggaran as $row): ?>
                                <?php $persentase = ($total_semua_jenis > 0) ? ($row['total'] / $total_semua_jenis) * 100 : 0; ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['nama_pelanggaran']) ?></td>
                                    <td class="text-center"><?= $row['total'] ?></td>
                                    <td class="text-center"><?= round($persentase) ?>%</td>
                                    <td><div class="progress-bar-container"><div class="progress-bar" style="width: <?= $persentase ?>%;"></div></div></td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="4" class="text-center text-muted">Belum ada data.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-xl-6">
             <div class="card dashboard-card h-100">
                <h3 class="card-title"><i class="fa-solid fa-users"></i> Top 10 Santri Pelanggar</h3>
                <div class="table-container">
                    <table>
                        <thead><tr><th>Nama Santri</th><th class="text-center">Poin</th></tr></thead>
                        <tbody>
                            <?php if (mysqli_num_rows($q_santri) > 0): while($row = mysqli_fetch_assoc($q_santri)): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['nama']) ?></td>
                                <td class="text-center"><span class="badge"><?= $row['poin_aktif'] ?></span></td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr><td colspan="2" class="text-center text-muted">Belum ada data.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-xl-4 mt-4">
            <div class="card dashboard-card h-100">
                <h3 class="card-title"><i class="fa-solid fa-tags"></i> Sebaran per Bagian</h3>
                <div class="chart-container"><canvas id="chartPerBagian"></canvas></div>
            </div>
        </div>

        <div class="col-xl-4 mt-4">
            <div class="card dashboard-card h-100">
                <h3 class="card-title"><i class="fa-solid fa-chalkboard-user"></i> Sebaran per Kelas</h3>
                <div class="chart-container"><canvas id="chartPerKelas"></canvas></div>
            </div>
        </div>
        
        <div class="col-xl-4 mt-4">
            <div class="card dashboard-card h-100">
                <h3 class="card-title"><i class="fa-solid fa-door-open"></i> Sebaran per Kamar (Kebersihan)</h3>
                <div class="chart-container"><canvas id="chartPerKamar"></canvas></div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    /* ... (SEMUA JS LU DARI ATAS SAMPAI BAWAH TETAP SAMA, TIDAK ADA PERUBAHAN) ... */
    document.addEventListener('DOMContentLoaded', function () {
    const dataKelas = <?= $json_per_kelas ?>;
    const dataKamar = <?= $json_per_kamar ?>;
    const dataBagian = <?= $json_per_bagian ?>;
    
    const doughnutChartOptions = {
        responsive: true, maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom', labels: { padding: 15, font: { family: "'Poppins', sans-serif" }, usePointStyle: true, pointStyle: 'circle' } },
            tooltip: { backgroundColor: 'rgba(17, 24, 39, 0.9)', titleFont: { family: "'Poppins', sans-serif" }, bodyFont: { family: "'Poppins', sans-serif" }, padding: 12, cornerRadius: 8 }
        },
        cutout: '60%'
    };

    function generateDistinctColors(count) {
        const colors = [];
        const saturation = 70;
        const lightness = 55;
        if (count === 0) return colors;
        for (let i = 0; i < count; i++) {
            const hue = Math.floor((i * (360 / count)));
            colors.push(`hsl(${hue}, ${saturation}%, ${lightness}%)`);
        }
        return colors;
    }

    if(dataBagian.labels.length > 0) {
        new Chart(document.getElementById('chartPerBagian'), { type: 'doughnut', data: { labels: dataBagian.labels, datasets: [{ data: dataBagian.data, backgroundColor: generateDistinctColors(dataBagian.labels.length) }] }, options: doughnutChartOptions });
    } else { document.getElementById('chartPerBagian').parentElement.innerHTML = '<p class="text-center text-muted m-auto">Tidak ada data.</p>'; }

    if(dataKelas.labels.length > 0) {
        new Chart(document.getElementById('chartPerKelas'), { type: 'doughnut', data: { labels: dataKelas.labels, datasets: [{ data: dataKelas.data, backgroundColor: generateDistinctColors(dataKelas.labels.length) }] }, options: doughnutChartOptions });
    } else { document.getElementById('chartPerKelas').parentElement.innerHTML = '<p class="text-center text-muted m-auto">Tidak ada data.</p>'; }

    if(dataKamar.labels.length > 0) {
        new Chart(document.getElementById('chartPerKamar'), { type: 'doughnut', data: { labels: dataKamar.labels, datasets: [{ data: dataKamar.data, backgroundColor: generateDistinctColors(dataKamar.labels.length) }] }, options: doughnutChartOptions });
    } else { document.getElementById('chartPerKamar').parentElement.innerHTML = '<p class="text-center text-muted m-auto">Tidak ada data.</p>'; }

    const ctxTren = document.getElementById('chartTrenPelanggaran').getContext('2d');
    const lineChart = new Chart(ctxTren, {
        type: 'line',
        data: { labels: [], datasets: [{ label: 'Jumlah Pelanggaran', data: [], backgroundColor: 'rgba(79, 70, 229, 0.1)', borderColor: 'rgba(79, 70, 229, 1)', borderWidth: 2, pointBackgroundColor: 'rgba(79, 70, 229, 1)', pointRadius: 4, tension: 0.3, fill: true }] },
        options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }, plugins: { legend: { display: false } } }
    });
    const filterButtons = document.querySelectorAll('.chart-filters button');
    const rangeToDays = { '7d': 7, '1m': 30, '3m': 90, '1y': 365 };
    async function fetchAndUpdateLineChart(rangeKey) {
        const days = rangeToDays[rangeKey];
        if (!days) return;
        try {
            const response = await fetch(`get_harian_data.php?days=${days}`);
            if (!response.ok) throw new Error('Network error');
            const chartData = await response.json();
            lineChart.data.labels = chartData.labels;
            lineChart.data.datasets[0].data = chartData.data;
            lineChart.update();
        } catch (error) { console.error('Gagal fetch data:', error); }
    }
    filterButtons.forEach(button => {
        button.addEventListener('click', () => {
            filterButtons.forEach(btn => btn.classList.remove('active'));
            button.classList.add('active');
            const rangeKey = button.id.split('-')[1];
            fetchAndUpdateLineChart(rangeKey);
        });
    });
    fetchAndUpdateLineChart('7d');
});
</script>

<?php require_once __DIR__ . '/../footer.php'; ?>