<?php 
// 1. Panggil 'Otak' aplikasi dulu
require_once __DIR__ . '/../bootstrap/init.php';

// 2. Jalankan 'SATPAM' buat ngejaga halaman
guard('arsip_view'); 

// 3. Kalau lolos, baru panggil Tampilan
require_once __DIR__ . '/../layouts/header.php';

$arsip_id = (int)($_GET['id'] ?? 0);
if ($arsip_id < 1) die('Arsip tidak ditemukan');

// ... (Kode PHP dari sini sampai sebelum bagian #3 tetap sama) ...
// Ambil meta arsip
$stmt_meta = $conn->prepare("SELECT * FROM arsip WHERE id = ?");
$stmt_meta->bind_param('i', $arsip_id);
$stmt_meta->execute();
$meta = $stmt_meta->get_result()->fetch_assoc();
if (!$meta) die('Arsip tidak ditemukan');

// 1. Total Pelanggaran (Umum & Kebersihan)
$stmt_total_umum = $conn->prepare("SELECT COUNT(*) as total FROM arsip_data_pelanggaran WHERE arsip_id = ?");
$stmt_total_umum->bind_param("i", $arsip_id);
$stmt_total_umum->execute();
$total_umum = $stmt_total_umum->get_result()->fetch_assoc()['total'];

$stmt_total_kebersihan = $conn->prepare("SELECT COUNT(*) as total FROM arsip_data_pelanggaran_kebersihan WHERE arsip_id = ?");
$stmt_total_kebersihan->bind_param("i", $arsip_id);
$stmt_total_kebersihan->execute();
$total_kebersihan = $stmt_total_kebersihan->get_result()->fetch_assoc()['total'] ?? 0;
$total_pelanggaran = $total_umum + $total_kebersihan;

// 2. Top 10 Santri (diubah untuk menghitung total poin dari arsip_data_pelanggaran)
$stmt_santri = $conn->prepare("
    SELECT 
        santri_nama, 
        SUM(poin) AS total_poin_pelanggaran 
    FROM 
        arsip_data_pelanggaran 
    WHERE 
        arsip_id = ? 
    GROUP BY 
        santri_nama 
    HAVING 
        SUM(poin) > 0
    ORDER BY 
        total_poin_pelanggaran DESC 
    LIMIT 10
");
$stmt_santri->bind_param("i", $arsip_id);
$stmt_santri->execute();
$q_santri = $stmt_santri->get_result();


// ==========================================================
// === BAGIAN INI DI-UPGRADE UNTUK MENGGABUNGKAN DATA ===
// ==========================================================

// 3. Komposisi Jenis Pelanggaran (digabung dengan data kebersihan)
// Step 1: Ambil data jenis pelanggaran dari tabel umum
$stmt_jenis = $conn->prepare("SELECT jenis_pelanggaran_nama, COUNT(*) as total FROM arsip_data_pelanggaran WHERE arsip_id = ? GROUP BY jenis_pelanggaran_nama ORDER BY total DESC");
$stmt_jenis->bind_param("i", $arsip_id);
$stmt_jenis->execute();
$data_jenis_pelanggaran = $stmt_jenis->get_result()->fetch_all(MYSQLI_ASSOC);

// Step 2: "Suntik" data total kebersihan ke dalam array
if ($total_kebersihan > 0) {
    $data_jenis_pelanggaran[] = [
        'jenis_pelanggaran_nama' => 'KEBERSIHAN KAMAR', 
        'total' => $total_kebersihan
    ];
}

// Step 3: Urutkan ulang array berdasarkan 'total' secara descending (dari besar ke kecil)
// Ini penting biar urutannya tetep bener setelah data baru ditambahin
usort($data_jenis_pelanggaran, function($a, $b) {
    return $b['total'] <=> $a['total'];
});

// Step 4: Hitung ulang total keseluruhan untuk persentase yang akurat
$total_semua_jenis = array_sum(array_column($data_jenis_pelanggaran, 'total'));


// ... (Sisa kode PHP setelah ini tetap sama persis) ...
// 4. Sebaran per Bagian (digabung dengan data Kebersihan)
$stmt_bagian = $conn->prepare("SELECT bagian, COUNT(*) AS total FROM arsip_data_pelanggaran WHERE arsip_id = ? GROUP BY bagian ORDER BY total DESC");
$stmt_bagian->bind_param("i", $arsip_id);
$stmt_bagian->execute();
$data_per_bagian = $stmt_bagian->get_result()->fetch_all(MYSQLI_ASSOC);

if ($total_kebersihan > 0) {
    $data_per_bagian[] = ['bagian' => 'Kebersihan', 'total' => $total_kebersihan];
}

$json_per_bagian = json_encode(['labels' => array_column($data_per_bagian, 'bagian'), 'data' => array_column($data_per_bagian, 'total')]);

// 5. Sebaran per Kelas (tidak berubah)
$stmt_kelas = $conn->prepare("SELECT santri_kelas, COUNT(*) AS total FROM arsip_data_pelanggaran WHERE arsip_id = ? AND santri_kelas IS NOT NULL AND santri_kelas != 'N/A' GROUP BY santri_kelas ORDER BY total DESC");
$stmt_kelas->bind_param("i", $arsip_id);
$stmt_kelas->execute();
$data_per_kelas = $stmt_kelas->get_result()->fetch_all(MYSQLI_ASSOC);
$json_per_kelas = json_encode(['labels' => array_column($data_per_kelas, 'santri_kelas'), 'data' => array_column($data_per_kelas, 'total')]);

// 6. Sebaran per Kamar (tidak berubah, sudah benar)
$stmt_kamar_kebersihan = $conn->prepare("SELECT kamar, COUNT(*) AS total FROM arsip_data_pelanggaran_kebersihan WHERE arsip_id = ? AND kamar IS NOT NULL AND kamar != '' GROUP BY kamar ORDER BY total DESC");
$stmt_kamar_kebersihan->bind_param("i", $arsip_id);
$stmt_kamar_kebersihan->execute();
$data_per_kamar_kebersihan = $stmt_kamar_kebersihan->get_result()->fetch_all(MYSQLI_ASSOC);
$labels_kamar = array_map(fn($item) => 'Kamar ' . $item['kamar'], $data_per_kamar_kebersihan);
$json_per_kamar_kebersihan = json_encode(['labels' => $labels_kamar, 'data' => array_column($data_per_kamar_kebersihan, 'total')]);

// 7. Tren Harian
$stmt_tren = $conn->prepare("
    SELECT tanggal_harian, SUM(total) as total
    FROM (
        SELECT DATE(tanggal) as tanggal_harian, COUNT(*) as total 
        FROM arsip_data_pelanggaran 
        WHERE arsip_id = ? 
        GROUP BY DATE(tanggal)
        
        UNION ALL
        
        SELECT DATE(tanggal) as tanggal_harian, COUNT(*) as total 
        FROM arsip_data_pelanggaran_kebersihan 
        WHERE arsip_id = ? 
        GROUP BY DATE(tanggal)
    ) as subquery
    GROUP BY tanggal_harian
    ORDER BY tanggal_harian ASC
");
$stmt_tren->bind_param("ii", $arsip_id, $arsip_id);
$stmt_tren->execute();
$data_tren = $stmt_tren->get_result()->fetch_all(MYSQLI_ASSOC);
$json_tren_harian = json_encode([
    'labels' => array_map(fn($item) => date('d M', strtotime($item['tanggal_harian'])), $data_tren),
    'data' => array_column($data_tren, 'total')
]);
?>

<style>
    :root { --primary: #4f46e5; --primary-light: #e0e7ff; --secondary: #10b981; --accent: #ef4444; --text-dark: #111827; --text-light: #6b7280; --bg-light: #f9fafb; --border-color: #e5e7eb; --card-bg: #ffffff; --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05); --hover-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.07), 0 4px 6px -4px rgba(0, 0, 0, 0.07); } 
    body { background-color: var(--bg-light); color: var(--text-dark); font-family: 'Poppins', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; } 
    .dashboard-header { background-color: var(--card-bg); color: var(--text-dark); padding: 25px; border-radius: 16px; margin-bottom: 25px; border: 1px solid var(--border-color); display: block !important; text-align: left !important; box-shadow: var(--card-shadow) !important; } 
    h2.dashboard-title { font-size: 24px; font-weight: 600; margin: 0 0 5px 0; display: flex; align-items: center; gap: 10px; } 
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
    .btn-group { display: flex; flex-wrap: wrap; gap: 1rem; margin-top: 1.5rem; } 
    .btn-detail { background-color: var(--primary); color: white; padding: 0.75rem 1.5rem; text-decoration: none; border-radius: 10px; font-weight: 600; transition: all 0.2s; display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; text-align: center; } 
    .btn-detail:hover { background-color: #4338ca; box-shadow: var(--hover-shadow); } 
    .btn-detail.secondary { background-color: #10b981; } 
    .btn-detail.secondary:hover { background-color: #059669; }
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

    /* === CSS TAMBAHAN UNTUK TOMBOL RESPONSIVE === */
    @media (max-width: 576px) {
        .btn-group {
            gap: 0.5rem; /* Kurangi jarak antar tombol */
            justify-content: stretch; /* Bikin tombol menuhin lebar card */
        }
        .btn-detail {
            padding: 0.6rem 1rem; /* Kecilin padding tombol */
            font-size: 0.875rem;    /* Kecilin font di dalem tombol */
            flex-grow: 1;         /* Biar kedua tombol lebarnya sama */
        }
    }
    /* =========================================== */

</style>

<div class="container-fluid mt-4 mb-5">
    <div class="dashboard-header">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h2 class="dashboard-title"><i class="fa-solid fa-archive text-primary"></i> Statistik Arsip: <?= htmlspecialchars($meta['judul']) ?></h2>
                <p style="margin: 0; color: var(--text-light);">Ringkasan data untuk periode: <strong class="text-dark"><?= date('d M Y', strtotime($meta['tanggal_mulai'])) ?></strong> sampai <strong class="text-dark"><?= date('d M Y', strtotime($meta['tanggal_selesai'])) ?></strong></p>
            </div>
            <a href="index.php" class="btn btn-light border shadow-sm flex-shrink-0 px-3 py-2 fw-medium"><i class="fas fa-arrow-left me-2"></i> Kembali</a>
        </div>
        <div class="btn-group">
            <a href="views/arsip_pelanggaran.php?id=<?= $arsip_id ?>" class="btn-detail"><i class="fas fa-list"></i> Lihat Detail Pelanggaran</a>
            <a href="views/arsip_santri.php?id=<?= $arsip_id ?>" class="btn-detail secondary"><i class="fas fa-users"></i> Lihat Rekap Poin Santri</a>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-4 col-md-6">
            <div class="card dashboard-card stat-card"><div class="icon"><i class="fa-solid fa-triangle-exclamation"></i></div><div class="number"><?= $total_pelanggaran ?></div><h4>Total Pelanggaran</h4></div>
        </div>
        <div class="col-lg-4 col-md-6">
            <div class="card dashboard-card stat-card"><div class="icon"><i class="fa-solid fa-book"></i></div><div class="number"><?= $total_umum ?></div><h4>Pelanggaran Umum</h4></div>
        </div>
        <div class="col-lg-4 col-md-12">
            <div class="card dashboard-card stat-card"><div class="icon"><i class="fa-solid fa-broom"></i></div><div class="number"><?= $total_kebersihan ?></div><h4>Pelanggaran Kebersihan</h4></div>
        </div>
    </div>

    <div class="pro-chart-card mb-4">
        <div class="chart-title">
            <span class="chart-icon-wrap" style="background:#fef3c7"><i class="fas fa-chart-line" style="color:#d97706"></i></span>
            Tren Pelanggaran Harian (Periode Arsip)
        </div>
        <div class="chart-subtitle">Pergerakan jumlah pelanggaran setiap harinya pada periode arsip</div>
        <div class="chart-scroll-outer">
            <div class="chart-scroll-inner tall" style="min-width:<?= max(count($data_tren)*80, 480) ?>px">
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
                        <thead><tr><th>Jenis Pelanggaran</th><th class="text-center">Jumlah</th><th class="text-center">%</th></tr></thead>
                        <tbody>
                            <?php if (!empty($data_jenis_pelanggaran)): foreach ($data_jenis_pelanggaran as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['jenis_pelanggaran_nama']) ?></td>
                                    <td class="text-center"><?= $row['total'] ?></td>
                                    <td class="text-center"><?= round(($total_semua_jenis > 0) ? ($row['total'] / $total_semua_jenis) * 100 : 0) ?>%</td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="3" class="text-center text-muted">Belum ada data.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-xl-6">
             <div class="card dashboard-card h-100">
                <h3 class="card-title"><i class="fa-solid fa-user-slash"></i> Top 10 Santri (Berdasarkan Poin Arsip)</h3>
                <div class="table-container">
                    <table>
                        <thead><tr><th>Nama Santri</th><th class="text-center">Total Poin</th></tr></thead>
                        <tbody>
                            <?php if ($q_santri->num_rows > 0): while($row = $q_santri->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['santri_nama']) ?></td>
                                <td class="text-center"><span class="badge"><?= $row['total_poin_pelanggaran'] ?></span></td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr><td colspan="2" class="text-center text-muted">Tidak ada santri dengan poin.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mt-4">
        <div class="col-xl-4">
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
        <div class="col-xl-4">
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
        <div class="col-xl-4">
            <div class="pro-chart-card">
                <div class="chart-title">
                    <span class="chart-icon-wrap" style="background:#dbeafe"><i class="fas fa-broom-ball" style="color:#3b82f6"></i></span>
                    Sebaran per Kamar
                </div>
                <div class="chart-subtitle">Pelanggaran khusus kebersihan</div>
                <div class="chart-scroll-inner" style="height:280px;max-width:340px;margin:0 auto">
                    <canvas id="chartPerKamarKebersihan"></canvas>
                </div>
            </div>
        </div>
    </div>

</div> 

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const dataBagian = <?= $json_per_bagian ?>;
    const dataKelas = <?= $json_per_kelas ?>;
    const dataKamarKebersihan = <?= $json_per_kamar_kebersihan ?>;
    const dataTren = <?= $json_tren_harian ?>;
    
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
        const colors = []; const saturation = 70; const lightness = 55; 
        if (count === 0) return colors; 
        for (let i = 0; i < count; i++) { 
            const hue = Math.floor((i * (360 / count))); 
            colors.push(`hsl(${hue}, ${saturation}%, ${lightness}%)`); 
        } 
        return colors; 
    }

    if(dataBagian.labels.length > 0) {
        new Chart(document.getElementById('chartPerBagian'), { type: 'doughnut', data: { labels: dataBagian.labels, datasets: [{ data: dataBagian.data, backgroundColor: generateDistinctColors(dataBagian.labels.length) }] }, options: doughnutChartOptions });
    } else {
        document.getElementById('chartPerBagian').parentElement.innerHTML = '<p class="text-center text-muted m-auto">Tidak ada data.</p>';
    }

    if(dataKelas.labels.length > 0) {
        new Chart(document.getElementById('chartPerKelas'), { type: 'doughnut', data: { labels: dataKelas.labels, datasets: [{ data: dataKelas.data, backgroundColor: generateDistinctColors(dataKelas.labels.length) }] }, options: doughnutChartOptions });
    } else {
        document.getElementById('chartPerKelas').parentElement.innerHTML = '<p class="text-center text-muted m-auto">Tidak ada data.</p>';
    }

    if(dataKamarKebersihan.labels.length > 0) {
        new Chart(document.getElementById('chartPerKamarKebersihan'), { type: 'doughnut', data: { labels: dataKamarKebersihan.labels, datasets: [{ data: dataKamarKebersihan.data, backgroundColor: generateDistinctColors(dataKamarKebersihan.labels.length) }] }, options: doughnutChartOptions });
    } else {
        document.getElementById('chartPerKamarKebersihan').parentElement.innerHTML = '<p class="text-center text-muted m-auto">Tidak ada data.</p>';
    }
    
    if(dataTren.labels.length > 0) {
        const trenCanvas = document.getElementById('chartTrenPelanggaran');
        const ctxTren = trenCanvas.getContext('2d');
        const h = trenCanvas.parentElement.offsetHeight || 360;

        new Chart(trenCanvas, {
            type: 'line',
            data: { 
                labels: dataTren.labels, 
                datasets: [{ 
                    label: 'Jumlah Pelanggaran', 
                    data: dataTren.data, 
                    backgroundColor: linGrad(ctxTren, h, 79, 70, 229), 
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
    } else {
        document.getElementById('chartTrenPelanggaran').parentElement.parentElement.innerHTML = '<p class="text-center text-muted m-auto">Tidak ada data tren.</p>';
    }
});
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>