<?php
// 1. Panggil 'Otak' aplikasi dulu
require_once __DIR__ . '/../../bootstrap/init.php';

// 2. Jalankan 'SATPAM' buat ngejaga halaman
guard('arsip_view');

require_once __DIR__ . '/../../layouts/header.php';

// Ambil arsip_id dari URL
$arsip_id = (int)($_GET['id'] ?? 0);
if ($arsip_id <= 0) {
    die("<div class='container mt-5'><div class='alert alert-danger'>Arsip tidak valid.</div></div>");
}

$stmt_arsip = $conn->prepare("SELECT * FROM arsip WHERE id = ?");
$stmt_arsip->bind_param('i', $arsip_id);
$stmt_arsip->execute();
$arsip = $stmt_arsip->get_result()->fetch_assoc();
if (!$arsip) {
    die("<div class='container mt-5'><div class='alert alert-danger'>Arsip tidak ditemukan.</div></div>");
}

// 1. Total Pelanggaran
$stmt_total1 = $conn->prepare("SELECT COUNT(*) as total FROM arsip_data_pelanggaran WHERE arsip_id = ? AND tipe = 'Umum'");
$stmt_total1->bind_param("i", $arsip_id);
$stmt_total1->execute();
$total1 = $stmt_total1->get_result()->fetch_assoc()['total'];

$stmt_total2 = $conn->prepare("SELECT COUNT(*) as total FROM arsip_data_pelanggaran_kebersihan WHERE arsip_id = ?");
$stmt_total2->bind_param("i", $arsip_id);
$stmt_total2->execute();
$total2 = $stmt_total2->get_result()->fetch_assoc()['total'];
$total_pelanggaran = $total1 + $total2;

// 2. Top 10 Santri 
$stmt_santri = $conn->prepare("
    SELECT
        s.santri_nama AS nama,
        SUM(p.poin) AS total_poin_periode
    FROM
        arsip_data_pelanggaran AS p
    JOIN arsip_data_santri AS s
    ON
        p.santri_id = s.santri_id AND p.arsip_id = s.arsip_id
    WHERE
        p.arsip_id = ? AND p.tipe = 'Umum'
    GROUP BY
        s.santri_id,
        s.santri_nama
    ORDER BY
        total_poin_periode DESC
    LIMIT 10
");
$stmt_santri->bind_param("i", $arsip_id);
$stmt_santri->execute();
$q_santri = $stmt_santri->get_result();

// 3. Data Jenis Pelanggaran
$stmt_jenis1 = $conn->prepare("SELECT jenis_pelanggaran_nama AS nama_pelanggaran, COUNT(*) as total FROM arsip_data_pelanggaran WHERE arsip_id = ? AND tipe = 'Umum' GROUP BY jenis_pelanggaran_id, jenis_pelanggaran_nama");
$stmt_jenis1->bind_param("i", $arsip_id);
$stmt_jenis1->execute();
$data_jenis_pelanggaran = $stmt_jenis1->get_result()->fetch_all(MYSQLI_ASSOC);
if ($total2 > 0) {
    $data_jenis_pelanggaran[] = ['nama_pelanggaran' => 'KEBERSIHAN KAMAR', 'total' => $total2];
}
usort($data_jenis_pelanggaran, fn ($a, $b) => $b['total'] <=> $a['total']);
$total_semua_jenis = array_sum(array_column($data_jenis_pelanggaran, 'total'));

// 4. Data Per Kelas
$stmt_kelas = $conn->prepare("SELECT s.santri_kelas AS kelas, COUNT(*) AS total FROM arsip_data_pelanggaran p JOIN arsip_data_santri s ON s.santri_id = p.santri_id AND s.arsip_id = p.arsip_id WHERE p.arsip_id = ? AND p.tipe = 'Umum' GROUP BY s.santri_kelas ORDER BY total DESC");
$stmt_kelas->bind_param("i", $arsip_id);
$stmt_kelas->execute();
$data_per_kelas = $stmt_kelas->get_result()->fetch_all(MYSQLI_ASSOC);
$json_per_kelas = json_encode(['labels' => array_column($data_per_kelas, 'kelas'), 'data' => array_column($data_per_kelas, 'total')]);

// 5. Data Per Kamar
$stmt_kamar = $conn->prepare("SELECT kamar, COUNT(*) AS total FROM arsip_data_pelanggaran_kebersihan WHERE arsip_id = ? GROUP BY kamar ORDER BY total DESC");
$stmt_kamar->bind_param("i", $arsip_id);
$stmt_kamar->execute();
$data_per_kamar = $stmt_kamar->get_result()->fetch_all(MYSQLI_ASSOC);
$labels_kamar = array_map(fn ($item) => "Kamar " . $item['kamar'], $data_per_kamar);
$json_per_kamar = json_encode(['labels' => $labels_kamar, 'data' => array_column($data_per_kamar, 'total')]);

// 6. DATA SEBARAN PER BAGIAN
$stmt_bagian = $conn->prepare("
    SELECT bagian, COUNT(id) AS total 
    FROM arsip_data_pelanggaran 
    WHERE arsip_id = ? AND tipe = 'Umum'
    GROUP BY bagian 
    ORDER BY total DESC
");
$stmt_bagian->bind_param("i", $arsip_id);
$stmt_bagian->execute();
$data_per_bagian = $stmt_bagian->get_result()->fetch_all(MYSQLI_ASSOC);
if ($total2 > 0) {
    $data_per_bagian[] = ['bagian' => 'Kebersihan', 'total' => $total2];
}
usort($data_per_bagian, fn ($a, $b) => $b['total'] <=> $a['total']);
$json_per_bagian = json_encode(['labels' => array_column($data_per_bagian, 'bagian'), 'data' => array_column($data_per_bagian, 'total')]);

// 7. Tren Pelanggaran Harian (Berdasarkan rentang arsip)
$stmt_tren = $conn->prepare("
    SELECT DATE(tanggal) as tgl, COUNT(*) as jumlah 
    FROM (
        SELECT tanggal FROM arsip_data_pelanggaran WHERE arsip_id = ?
        UNION ALL
        SELECT tanggal FROM arsip_data_pelanggaran_kebersihan WHERE arsip_id = ?
    ) as gabungan
    GROUP BY DATE(tanggal) 
    ORDER BY tgl ASC
");
$stmt_tren->bind_param("ii", $arsip_id, $arsip_id);
$stmt_tren->execute();
$res_tren = $stmt_tren->get_result();

$labels_tren = [];
$data_tren = [];

// Isi range tanggal kosong
$start_ts = strtotime($arsip['tanggal_mulai']);
$end_ts = strtotime($arsip['tanggal_selesai']);

$daily_counts = [];
while ($row = $res_tren->fetch_assoc()) {
    $daily_counts[$row['tgl']] = $row['jumlah'];
}

// Bikin array per hari
for ($i = $start_ts; $i <= $end_ts; $i += 86400) {
    $tgl_str = date('Y-m-d', $i);
    $labels_tren[] = date('d M', $i);
    $data_tren[] = $daily_counts[$tgl_str] ?? 0;
}

$json_harian = json_encode(['labels' => $labels_tren, 'data' => $data_tren]);
?>

<style>
    /* ... (CSS TETAP SAMA, TIDAK ADA PERUBAHAN) ... */
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
    /* Pro chart card styles dari detail_karakter.php */
    .pro-chart-card{background:#fff;border-radius:16px;box-shadow:0 2px 16px rgba(0,0,0,.07);padding:1.5rem 1.75rem 1.75rem;margin-bottom:1.5rem;border:1px solid #f1f5f9; height:100%}
    .chart-title{font-size:1rem;font-weight:700;color:#1e293b;margin-bottom:2px;display:flex;align-items:center;gap:8px}
    .chart-subtitle{font-size:.8rem;color:#94a3b8;margin-bottom:1.25rem;padding-left:28px}
    .chart-icon-wrap{width:28px;height:28px;border-radius:8px;display:inline-flex;align-items:center;justify-content:center;font-size:.8rem;flex-shrink:0}
    .chart-scroll-outer{overflow-x:auto;-webkit-overflow-scrolling:touch;scrollbar-width:thin;scrollbar-color:#cbd5e1 #f8fafc;border-radius:10px}
    .chart-scroll-outer::-webkit-scrollbar{height:5px}
    .chart-scroll-outer::-webkit-scrollbar-track{background:#f8fafc;border-radius:10px}
    .chart-scroll-outer::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:10px}
    .chart-scroll-inner{position:relative;height:300px}
    .chart-scroll-inner.tall{height:360px}
    @media (max-width: 768px) {
        .dashboard-card { padding: 20px; }
        h2.dashboard-title { font-size: 22px; }
        .stat-card .number { font-size: 28px; }
        .date-filter-form { flex-direction: column; align-items: stretch; gap: 15px; }
    }
</style>

<div class="container-fluid mt-4 mb-5">
    <div class="dashboard-header">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 w-100">
            <div>
                <h2 class="dashboard-title"><i class="fa-solid fa-chart-pie"></i> Statistik Arsip</h2>
                <p style="margin: 0; color: #64748b;">Ringkasan data untuk arsip: <strong><?= date('d M Y', strtotime($arsip['tanggal_mulai'])) ?></strong> sampai <strong><?= date('d M Y', strtotime($arsip['tanggal_selesai'])) ?></strong></p>
            </div>
            <div>
                <a href="../view.php?id=<?= $arsip_id ?>" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 24px; background-color: #f8fafc; color: #475569; border: 1px solid #e2e8f0; text-decoration: none; border-radius: 9999px; font-weight: 600; font-size: 14px; transition: all 0.2s ease; white-space: nowrap;" onmouseover="this.style.backgroundColor='#f1f5f9'; this.style.borderColor='#cbd5e1';" onmouseout="this.style.backgroundColor='#f8fafc'; this.style.borderColor='#e2e8f0';">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-4 col-md-6">
            <a href="arsip_per_santri.php?id=<?= $arsip_id ?>&tipe=daftar_hitam" style="text-decoration: none; display: block; height: 100%;">
                <div class="card dashboard-card stat-card h-100">
                    <div class="icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
                    <div class="number"><?= $total_pelanggaran ?></div>
                    <h4>Total Pelanggaran</h4>
                </div>
            </a>
        </div>
        <div class="col-lg-4 col-md-6">
            <a href="arsip_per_santri.php?id=<?= $arsip_id ?>&tipe=daftar_hitam" style="text-decoration: none; display: block; height: 100%;">
                <div class="card dashboard-card stat-card h-100">
                    <div class="icon"><i class="fa-solid fa-book"></i></div>
                    <div class="number"><?= $total1 ?></div>
                    <h4>Pelanggaran Umum</h4>
                </div>
            </a>
        </div>
        <div class="col-lg-4 col-md-12">
            <a href="arsip_kebersihan.php?id=<?= $arsip_id ?>" style="text-decoration: none; display: block; height: 100%;">
                <div class="card dashboard-card stat-card h-100">
                    <div class="icon"><i class="fa-solid fa-broom"></i></div>
                    <div class="number"><?= $total2 ?></div>
                    <h4>Pelanggaran Kebersihan</h4>
                </div>
            </a>
        </div>
    </div>

    <div class="pro-chart-card mb-4">
        <div class="chart-title">
            <span class="chart-icon-wrap" style="background:#fef3c7"><i class="fas fa-chart-line" style="color:#d97706"></i></span>
            Tren Pelanggaran Harian
        </div>
        <div class="chart-subtitle">Pergerakan jumlah pelanggaran sepanjang periode arsip</div>
        <div class="chart-scroll-outer">
            <div class="chart-scroll-inner tall" style="min-width:600px">
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
                            <?php if (!empty($data_jenis_pelanggaran)) : ?>
                                <?php foreach ($data_jenis_pelanggaran as $row) : ?>
                                    <?php $persentase = ($total_semua_jenis > 0) ? ($row['total'] / $total_semua_jenis) * 100 : 0; ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['nama_pelanggaran']) ?></td>
                                        <td class="text-center"><?= $row['total'] ?></td>
                                        <td class="text-center"><?= round($persentase) ?>%</td>
                                        <td>
                                            <div class="progress-bar-container">
                                                <div class="progress-bar" style="width: <?= $persentase ?>%;"></div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted">Belum ada data.</td>
                                </tr>
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
                        <thead>
                            <tr>
                                <th>Nama Santri</th>
                                <th class="text-center">Poin</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($q_santri) > 0) : ?>
                                <?php while ($row = mysqli_fetch_assoc($q_santri)) : ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['nama']) ?></td>
                                        <td class="text-center">
                                            <span class="badge"><?= $row['total_poin_periode'] ?></span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else : ?>
                                <tr>
                                    <td colspan="2" class="text-center text-muted">Belum ada data pelanggaran di periode ini.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-xl-4 mt-4">
            <div class="pro-chart-card">
                <div class="chart-title">
                    <span class="chart-icon-wrap" style="background:#f3e8ff"><i class="fas fa-tags" style="color:#7c3aed"></i></span>
                    Sebaran per Bagian
                </div>
                <div class="chart-subtitle">Proporsi pelanggaran per bagian</div>
                <div class="chart-scroll-inner" style="height:280px;max-width:340px;margin:0 auto">
                    <canvas id="chartPerBagian"></canvas>
                </div>
            </div>
        </div>

        <div class="col-xl-4 mt-4">
            <div class="pro-chart-card">
                <div class="chart-title">
                    <span class="chart-icon-wrap" style="background:#e0e7ff"><i class="fas fa-chalkboard-user" style="color:#4f46e5"></i></span>
                    Sebaran per Kelas
                </div>
                <div class="chart-subtitle">Proporsi pelanggaran berdasarkan kelas</div>
                <div class="chart-scroll-inner" style="height:280px;max-width:340px;margin:0 auto">
                    <canvas id="chartPerKelas"></canvas>
                </div>
            </div>
        </div>

        <div class="col-xl-4 mt-4">
            <div class="pro-chart-card">
                <div class="chart-title">
                    <span class="chart-icon-wrap" style="background:#dbeafe"><i class="fas fa-door-open" style="color:#3b82f6"></i></span>
                    Sebaran per Kamar
                </div>
                <div class="chart-subtitle">Pelanggaran khusus kebersihan</div>
                <div class="chart-scroll-inner" style="height:280px;max-width:340px;margin:0 auto">
                    <canvas id="chartPerKamar"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    /* ... (JAVASCRIPT TETAP SAMA, TIDAK ADA PERUBAHAN) ... */
    document.addEventListener('DOMContentLoaded', function() {
        const dataKelas = <?= $json_per_kelas ?>;
        const dataKamar = <?= $json_per_kamar ?>;
        const dataBagian = <?= $json_per_bagian ?>;
        const dataHarian = <?= $json_harian ?>;

        const FONT = "'Poppins','Segoe UI',sans-serif";
        const TOOLTIP = {
            backgroundColor:'rgba(15,23,42,.92)', titleColor:'#f1f5f9', bodyColor:'#cbd5e1',
            padding:12, cornerRadius:10, titleFont:{weight:'700',size:13},
            bodyFont:{size:12}, displayColors:true, boxPadding:4
        };
        const GRID  = { color:'rgba(148,163,184,.12)', drawTicks:false };
        const TICKS = { padding:8, font:{size:11, family:FONT} };

        Chart.defaults.font.family = FONT;
        Chart.defaults.color       = '#64748b';

        function linGrad(ctx, h, r, g, b) {
            const gr = ctx.createLinearGradient(0, 0, 0, h);
            gr.addColorStop(0, `rgba(${r},${g},${b},.22)`);
            gr.addColorStop(1, `rgba(${r},${g},${b},0)`);
            return gr;
        }

        const doughnutChartOptions = {
            responsive:true, maintainAspectRatio:false, cutout:'62%',
            plugins:{
                legend:{ position:'bottom', labels:{ usePointStyle:true, pointStyle:'circle', padding:14, font:{size:11,family:FONT} } },
                tooltip:TOOLTIP
            }
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

        if (dataBagian.labels.length > 0) {
            new Chart(document.getElementById('chartPerBagian'), {
                type: 'doughnut',
                data: {
                    labels: dataBagian.labels,
                    datasets: [{
                        data: dataBagian.data,
                        backgroundColor: generateDistinctColors(dataBagian.labels.length)
                    }]
                },
                options: doughnutChartOptions
            });
        } else {
            document.getElementById('chartPerBagian').parentElement.innerHTML = '<p class="text-center text-muted m-auto">Tidak ada data.</p>';
        }

        if (dataKelas.labels.length > 0) {
            new Chart(document.getElementById('chartPerKelas'), {
                type: 'doughnut',
                data: {
                    labels: dataKelas.labels,
                    datasets: [{
                        data: dataKelas.data,
                        backgroundColor: generateDistinctColors(dataKelas.labels.length)
                    }]
                },
                options: doughnutChartOptions
            });
        } else {
            document.getElementById('chartPerKelas').parentElement.innerHTML = '<p class="text-center text-muted m-auto">Tidak ada data.</p>';
        }

        if (dataKamar.labels.length > 0) {
            new Chart(document.getElementById('chartPerKamar'), {
                type: 'doughnut',
                data: {
                    labels: dataKamar.labels,
                    datasets: [{
                        data: dataKamar.data,
                        backgroundColor: generateDistinctColors(dataKamar.labels.length)
                    }]
                },
                options: doughnutChartOptions
            });
        } else {
            document.getElementById('chartPerKamar').parentElement.innerHTML = '<p class="text-center text-muted m-auto">Tidak ada data.</p>';
        }

        const ctxTren = document.getElementById('chartTrenPelanggaran').getContext('2d');
        const hTren = document.getElementById('chartTrenPelanggaran').parentElement.offsetHeight || 360;

        const lineChart = new Chart(ctxTren, {
            type: 'line',
            data: {
                labels: dataHarian.labels,
                datasets: [{
                    label: 'Jumlah Pelanggaran',
                    data: dataHarian.data,
                    backgroundColor: linGrad(ctxTren, hTren, 79, 70, 229),
                    borderColor: '#4f46e5',
                    borderWidth: 2.5,
                    pointBackgroundColor: '#4f46e5',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 8,
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive:true, maintainAspectRatio:false,
                layout: { padding: { top: 15 } },
                interaction:{ mode:'index', intersect:false },
                plugins: {
                    legend:{ display:false },
                    tooltip:TOOLTIP
                },
                scales: {
                    y:{ beginAtZero:true, grid:GRID, ticks:{...TICKS, callback:v=>v+' kali'}, border:{display:false} },
                    x:{ grid:{display:false}, ticks:TICKS, border:{display:false} }
                }
            }
        });
        
        document.getElementById('chartTrenPelanggaran').parentElement.style.minWidth = Math.max(dataHarian.labels.length * 50, 600) + 'px';
    });
</script>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
