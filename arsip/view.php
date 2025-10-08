<?php
require_once __DIR__ . '/../header.php';
// Ganti guardnya, karena ini halaman view, bukan manage
guard('arsip_view');

$arsip_id = (int)($_GET['id'] ?? 0);
if ($arsip_id < 1) die('Arsip tidak ditemukan');

// Ambil meta arsip
$stmt_meta = $conn->prepare("SELECT * FROM arsip WHERE id = ?");
$stmt_meta->bind_param('i', $arsip_id);
$stmt_meta->execute();
$meta = $stmt_meta->get_result()->fetch_assoc();
if (!$meta) die('Arsip tidak ditemukan');

// ===============================================
// === SEMUA QUERY MENGAMBIL DATA DARI ARSIP ===
// ===============================================

// 1. Total Pelanggaran (Umum & Kebersihan)
$stmt_total_umum = $conn->prepare("SELECT COUNT(*) as total FROM arsip_data_pelanggaran WHERE arsip_id = ? AND tipe = 'Umum'");
$stmt_total_umum->bind_param("i", $arsip_id);
$stmt_total_umum->execute();
$total_umum = $stmt_total_umum->get_result()->fetch_assoc()['total'];

$stmt_total_kebersihan = $conn->prepare("SELECT COUNT(*) as total FROM arsip_data_pelanggaran WHERE arsip_id = ? AND tipe = 'Kebersihan'");
$stmt_total_kebersihan->bind_param("i", $arsip_id);
$stmt_total_kebersihan->execute();
$total_kebersihan = $stmt_total_kebersihan->get_result()->fetch_assoc()['total'];
$total_pelanggaran = $total_umum + $total_kebersihan;

// 2. Top 10 Santri berdasarkan POIN YANG DIARSIPKAN
$stmt_santri = $conn->prepare("SELECT santri_nama, total_poin_saat_arsip FROM arsip_data_santri WHERE arsip_id = ? AND total_poin_saat_arsip > 0 ORDER BY total_poin_saat_arsip DESC LIMIT 10");
$stmt_santri->bind_param("i", $arsip_id);
$stmt_santri->execute();
$q_santri = $stmt_santri->get_result();

// 3. Komposisi Jenis Pelanggaran dari arsip
$stmt_jenis = $conn->prepare("SELECT jenis_pelanggaran_nama, COUNT(*) as total FROM arsip_data_pelanggaran WHERE arsip_id = ? GROUP BY jenis_pelanggaran_nama ORDER BY total DESC");
$stmt_jenis->bind_param("i", $arsip_id);
$stmt_jenis->execute();
$data_jenis_pelanggaran = $stmt_jenis->get_result()->fetch_all(MYSQLI_ASSOC);
$total_semua_jenis = array_sum(array_column($data_jenis_pelanggaran, 'total'));

// 4. Sebaran per Bagian dari arsip
$stmt_bagian = $conn->prepare("SELECT bagian, COUNT(*) AS total FROM arsip_data_pelanggaran WHERE arsip_id = ? GROUP BY bagian ORDER BY total DESC");
$stmt_bagian->bind_param("i", $arsip_id);
$stmt_bagian->execute();
$data_per_bagian = $stmt_bagian->get_result()->fetch_all(MYSQLI_ASSOC);
$json_per_bagian = json_encode(['labels' => array_column($data_per_bagian, 'bagian'), 'data' => array_column($data_per_bagian, 'total')]);

// 5. Sebaran per Kelas dari arsip (hanya tipe 'Umum')
$stmt_kelas = $conn->prepare("SELECT santri_kelas, COUNT(*) AS total FROM arsip_data_pelanggaran WHERE arsip_id = ? AND tipe = 'Umum' AND santri_kelas IS NOT NULL AND santri_kelas != 'N/A' GROUP BY santri_kelas ORDER BY total DESC");
$stmt_kelas->bind_param("i", $arsip_id);
$stmt_kelas->execute();
$data_per_kelas = $stmt_kelas->get_result()->fetch_all(MYSQLI_ASSOC);
$json_per_kelas = json_encode(['labels' => array_column($data_per_kelas, 'santri_kelas'), 'data' => array_column($data_per_kelas, 'total')]);

// 6. Tren Harian SELAMA PERIODE ARSIP
$stmt_tren = $conn->prepare("SELECT DATE(tanggal) as tanggal_harian, COUNT(*) as total FROM arsip_data_pelanggaran WHERE arsip_id = ? GROUP BY DATE(tanggal) ORDER BY tanggal_harian ASC");
$stmt_tren->bind_param("i", $arsip_id);
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
    .dashboard-header { background-color: var(--card-bg); color: var(--text-dark); padding: 25px; border-radius: 16px; margin-bottom: 25px; border: 1px solid var(--border-color); } 
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

    /* === PERBAIKAN CSS GRAFIK (v3) === */
    .chart-container { 
        position: relative; 
        width: 100%;
        flex-grow: 1;
        /* DIUBAH: Kasih tinggi minimal biar chart donat & legendanya rapi */
        min-height: 380px; 
        display: flex;
        align-items: center;
        justify-content: center;
    }

    /* BARU: Kelas khusus buat card chart garis biar lebih tinggi & lega */
    .chart-container.chart-container-tall {
        min-height: 400px;
    }

    /* (HANYA UNTUK CHART GARIS) Wrapper untuk scroll horizontal */
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
</style>

<div class="container-fluid mt-4 mb-5">
    <div class="dashboard-header">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h2 class="dashboard-title"><i class="fa-solid fa-archive"></i> Statistik Arsip: <?= htmlspecialchars($meta['judul']) ?></h2>
                <p style="margin: 0;">Ringkasan data untuk periode: <strong><?= date('d M Y', strtotime($meta['tanggal_mulai'])) ?></strong> sampai <strong><?= date('d M Y', strtotime($meta['tanggal_selesai'])) ?></strong></p>
            </div>
            <a href="index.php" class="btn btn-light flex-shrink-0"> &larr; Kembali</a>
        </div>
        <div class="btn-group">
            <a href="arsip_pelanggaran.php?id=<?= $arsip_id ?>" class="btn-detail"><i class="fas fa-list"></i> Lihat Detail Pelanggaran</a>
            <a href="arsip_santri.php?id=<?= $arsip_id ?>" class="btn-detail secondary"><i class="fas fa-users"></i> Lihat Rekap Poin Santri</a>
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

    <div class="card dashboard-card mb-4">
        <h3 class="card-title"><i class="fa-solid fa-chart-line"></i> Tren Pelanggaran Harian (Periode Arsip)</h3>
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
                                <td class="text-center"><span class="badge"><?= $row['total_poin_saat_arsip'] ?></span></td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr><td colspan="2" class="text-center text-muted">Tidak ada santri dengan poin.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-xl-6 mt-4">
            <div class="card dashboard-card h-100">
                <h3 class="card-title"><i class="fa-solid fa-tags"></i> Sebaran per Bagian</h3>
                <div class="chart-container">
                    <canvas id="chartPerBagian"></canvas>
                </div>
            </div>
        </div>
        <div class="col-xl-6 mt-4">
            <div class="card dashboard-card h-100">
                <h3 class="card-title"><i class="fa-solid fa-chalkboard-user"></i> Sebaran per Kelas</h3>
                <div class="chart-container">
                    <canvas id="chartPerKelas"></canvas>
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
    const dataTren = <?= $json_tren_harian ?>;
    
    // Opsi dasar untuk doughnut charts
    const doughnutChartOptions = { 
        responsive: true, 
        maintainAspectRatio: false, // Penting untuk custom container height
        plugins: { 
            legend: { 
                position: 'bottom', 
                labels: { padding: 15, font: { family: "'Poppins', sans-serif" }, usePointStyle: true, pointStyle: 'circle' } 
            }, 
            tooltip: { 
                backgroundColor: 'rgba(17, 24, 39, 0.9)', 
                titleFont: { family: "'Poppins', sans-serif" }, 
                bodyFont: { family: "'Poppins', sans-serif" }, 
                padding: 12, 
                cornerRadius: 8 
            } 
        }, 
        cutout: '60%' 
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
        document.getElementById('chartPerBagian').parentElement.innerHTML = '<p class="text-center text-muted">Tidak ada data untuk ditampilkan.</p>';
    }

    if(dataKelas.labels.length > 0) {
        new Chart(document.getElementById('chartPerKelas'), { type: 'doughnut', data: { labels: dataKelas.labels, datasets: [{ data: dataKelas.data, backgroundColor: generateDistinctColors(dataKelas.labels.length) }] }, options: doughnutChartOptions });
    } else {
        document.getElementById('chartPerKelas').parentElement.innerHTML = '<p class="text-center text-muted">Tidak ada data untuk ditampilkan.</p>';
    }
    
    if(dataTren.labels.length > 0) {
        new Chart(document.getElementById('chartTrenPelanggaran'), {
            type: 'line',
            data: { 
                labels: dataTren.labels, 
                datasets: [{ 
                    label: 'Jumlah Pelanggaran', 
                    data: dataTren.data, 
                    backgroundColor: 'rgba(79, 70, 229, 0.1)', 
                    borderColor: 'rgba(79, 70, 229, 1)', 
                    borderWidth: 2, 
                    pointBackgroundColor: 'rgba(79, 70, 229, 1)', 
                    pointRadius: 4, 
                    tension: 0.3, 
                    fill: true 
                }] 
            },
            options: { 
                responsive: true,
                maintainAspectRatio: false, // Penting untuk custom container height
                scales: { 
                    y: { beginAtZero: true, ticks: { precision: 0 } } 
                }, 
                plugins: { 
                    legend: { display: false } 
                } 
            }
        });
    } else {
        document.getElementById('chartTrenPelanggaran').parentElement.parentElement.innerHTML = '<p class="text-center text-muted">Tidak ada data tren untuk ditampilkan.</p>';
    }
});
</script>

<?php require_once __DIR__ . '/../footer.php'; ?>