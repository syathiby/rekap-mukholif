<?php 
// 1. Panggil 'Otak' aplikasi dulu
require_once __DIR__ . '/../../init.php';

// 2. Jalankan 'SATPAM' buat ngejaga halaman
guard('rekap_view_bahasa'); 

// 3. Kalau lolos, baru panggil Tampilan
require_once __DIR__ . '/../../header.php';
?>

<?php
$bagian = 'Bahasa';

// =======================================================
// BAGIAN 1: TANGKAP "SURAT TUGAS" DARI URL
// =======================================================
if (!isset($_GET['santri_id'])) {
    die("<div class='container my-4'><div class='alert alert-danger'>Informasi tidak lengkap. ID Santri dibutuhkan.</div></div>");
}

$santri_id = (int)$_GET['santri_id'];
// Default tanggal kalau gak ada di URL (Sebulan terakhir biar grafik enak diliat)
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date   = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// =======================================================
// BAGIAN 2: AMBIL DATA SANTRI
// =======================================================
$stmt_santri = mysqli_prepare($conn, "SELECT nama, kelas, kamar FROM santri WHERE id = ?");
mysqli_stmt_bind_param($stmt_santri, "i", $santri_id);
mysqli_stmt_execute($stmt_santri);
$santri = mysqli_stmt_get_result($stmt_santri)->fetch_assoc();

if (!$santri) {
    die("<div class='container my-4'><div class='alert alert-danger'>Data santri tidak ditemukan.</div></div>");
}

// =======================================================
// BAGIAN 3: AMBIL DATA PELANGGARAN (AKTIF & HISTORY)
// =======================================================

// A. Ambil Data AKTIF (Dari tabel pelanggaran)
// Kita filter pake tanggal juga biar sesuai filter user
$sql_aktif = "
    SELECT 
        p.tanggal,
        jp.nama_pelanggaran,
        jp.poin,
        jp.kategori,
        'Aktif' as status_data
    FROM pelanggaran p
    JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id
    WHERE p.santri_id = ?
      AND jp.bagian = ?
      AND DATE(p.tanggal) BETWEEN ? AND ?
";

// B. Ambil Data RIWAYAT (Dari tabel log_bahasa)
$sql_history = "
    SELECT 
        l.tanggal_melanggar as tanggal,
        jp.nama_pelanggaran,
        l.poin_lama as poin,
        jp.kategori,
        'Riwayat' as status_data
    FROM log_bahasa l
    JOIN jenis_pelanggaran jp ON l.jenis_pelanggaran_id = jp.id
    WHERE l.santri_id = ?
      AND DATE(l.tanggal_melanggar) BETWEEN ? AND ?
";

// Eksekusi Query Aktif
$stmt_aktif = mysqli_prepare($conn, $sql_aktif);
mysqli_stmt_bind_param($stmt_aktif, "isss", $santri_id, $bagian, $start_date, $end_date);
mysqli_stmt_execute($stmt_aktif);
$res_aktif = mysqli_stmt_get_result($stmt_aktif);
$data_aktif = mysqli_fetch_all($res_aktif, MYSQLI_ASSOC);

// Eksekusi Query History
$stmt_hist = mysqli_prepare($conn, $sql_history);
mysqli_stmt_bind_param($stmt_hist, "iss", $santri_id, $start_date, $end_date);
mysqli_stmt_execute($stmt_hist);
$res_hist = mysqli_stmt_get_result($stmt_hist);
$data_history = mysqli_fetch_all($res_hist, MYSQLI_ASSOC);

// C. GABUNGKAN DATA (Merge)
$all_data = array_merge($data_aktif, $data_history);

// D. URUTKAN DATA (Sort by Date ASCENDING untuk Grafik)
usort($all_data, function($a, $b) {
    return strtotime($a['tanggal']) - strtotime($b['tanggal']);
});

// Hitung total buat statistik
$total_pelanggaran = count($all_data);
// Total poin itu agak tricky kalau ada history, biasanya yg dihitung poin AKTIF aja.
// Tapi kalau mau total history, pake array_sum kolom poin.
// Disini gw tampilin Total Poin yg AKTIF aja biar relevan sama sanksi.
$poin_aktif_list = array_filter($all_data, fn($item) => $item['status_data'] === 'Aktif');
$total_poin_aktif = array_sum(array_column($poin_aktif_list, 'poin'));


// SIAPIN DATA BUAT CHART.JS (JSON Format)
$chart_labels = [];
$chart_data = [];
$chart_colors = [];

foreach ($all_data as $row) {
    $chart_labels[] = date('d M', strtotime($row['tanggal'])); // Format tgl: 29 Nov
    $chart_data[] = $row['poin'];
    // Warna dot di grafik: Merah kalau aktif, Abu kalau riwayat
    $chart_colors[] = ($row['status_data'] === 'Aktif') ? '#ef4444' : '#94a3b8';
}

// =======================================================
// MODIFIKASI SORTING TABEL
// =======================================================
// Kita copy dulu datanya
$table_data = $all_data;

// Kita sort ulang khusus buat tabel
usort($table_data, function($a, $b) {
    // ATURAN 1: Prioritas Status 'Aktif'
    // Kalau $a Aktif & $b Riwayat, $a menang (naik ke atas)
    if ($a['status_data'] === 'Aktif' && $b['status_data'] !== 'Aktif') {
        return -1; 
    }
    // Kalau $a Riwayat & $b Aktif, $b menang
    if ($a['status_data'] !== 'Aktif' && $b['status_data'] === 'Aktif') {
        return 1; 
    }

    // ATURAN 2: Kalau statusnya sama-sama Aktif atau sama-sama Riwayat
    // Baru kita adu tanggalnya (Yang paling BARU di atas)
    return strtotime($b['tanggal']) - strtotime($a['tanggal']);
});

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Bahasa: <?= htmlspecialchars($santri['nama']) ?></title>
    
    <!-- CSS & Font -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Chart.js CDN (Wajib buat grafik) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        :root {
            --primary: #4f46e5; --primary-light: #e0e7ff; --primary-dark: #4338ca;
            --secondary: #64748b; --light-bg: #f8fafc; --card-bg: #ffffff;
            --border-color: #e2e8f0; --text-dark: #1e293b; --text-light: #64748b;
        }
        body { background-color: var(--light-bg); font-family: 'Poppins', sans-serif; }
        .card { background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem; box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.05); }
        .page-title { color: var(--text-dark); font-weight: 700; }
        .stat-card { background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); }
        .stat-number { font-size: 2.5rem; font-weight: 700; color: white; }
        .stat-label { font-size: 1rem; color: rgba(255, 255, 255, 0.8); }
        
        /* Badge Status */
        .badge-aktif { background-color: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .badge-riwayat { background-color: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0; text-decoration: line-through; }

        @media (max-width: 767.98px) {
            .page-title { font-size: 1.5rem; }
            .profile-card-info { text-align: center; }
            .chart-container { height: 250px; }
        }
    </style>
</head>
<body>
<div class="container py-4">
    
    <!-- HEADER -->
    <div class="d-flex flex-column flex-md-row justify-content-md-between align-items-start align-items-md-center mb-4">
        <a href="javascript:history.back()" class="btn btn-outline-secondary mb-3 mb-md-0"><i class="fas fa-arrow-left me-2"></i>Kembali</a>
        <h1 class="page-title mb-0 w-100 w-md-auto text-center text-md-end">Detail Perkembangan Bahasa</h1>
    </div>

    <!-- PROFILE CARD -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-6 profile-card-info text-md-start">
                    <h3 class="fw-bold mb-1"><?= htmlspecialchars($santri['nama']) ?></h3>
                    <p class="text-muted mb-0">Kelas: <strong><?= htmlspecialchars($santri['kelas']) ?></strong> | Kamar: <strong><?= htmlspecialchars($santri['kamar']) ?></strong></p>
                </div>
                <div class="col-md-6 text-md-end mt-3 mt-md-0">
                    <form action="" method="GET" class="d-flex justify-content-md-end gap-2">
                        <input type="hidden" name="santri_id" value="<?= $santri_id ?>">
                        <input type="date" name="start_date" class="form-control form-control-sm" style="width: auto;" value="<?= $start_date ?>">
                        <span class="align-self-center">-</span>
                        <input type="date" name="end_date" class="form-control form-control-sm" style="width: auto;" value="<?= $end_date ?>">
                        <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-filter"></i></button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- STATS ROW -->
    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="card stat-card text-white p-3">
                <div class="card-body text-center">
                    <div class="stat-number"><?= $total_pelanggaran ?></div>
                    <div class="stat-label">Total Pelanggaran (Aktif + Riwayat)</div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card bg-white p-3 border-danger border-2">
                <div class="card-body text-center">
                    <!-- Ini Poin Aktif, karena poin lama udah ga berlaku -->
                    <div class="stat-number text-danger"><?= $total_poin_aktif ?></div>
                    <div class="stat-label text-dark">Poin Pelanggaran Aktif</div>
                </div>
            </div>
        </div>
    </div>

    <!-- GRAFIK CHART -->
    <div class="card mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
             <h5 class="card-title fw-bold mb-0"><i class="fas fa-chart-line me-2 text-primary"></i>Grafik Level Pelanggaran</h5>
        </div>
        <div class="card-body">
            <div class="chart-container" style="position: relative; height:300px; width:100%">
                <canvas id="bahasaChart"></canvas>
            </div>
        </div>
    </div>

    <!-- TABEL RINCIAN -->
    <div class="card">
        <div class="card-header">
             <h5 class="card-title fw-bold mb-0"><i class="fas fa-history me-2"></i>Riwayat & Status Terkini</h5>
        </div>
        <div class="card-body table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width: 5%;">No</th>
                        <th>Tanggal</th>
                        <th>Status</th>
                        <th>Pelanggaran</th>
                        <th class="text-center">Kategori</th>
                        <th class="text-center">Poin</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($table_data)): ?>
                        <tr><td colspan="6" class="text-center p-5 text-muted"><i class="fas fa-check-circle fa-3x mb-3 text-success"></i><br>Bersih! Tidak ada pelanggaran bahasa.</td></tr>
                    <?php else: ?>
                        <?php foreach ($table_data as $index => $row): ?>
                        <tr class="<?= $row['status_data'] === 'Riwayat' ? 'text-muted' : '' ?>">
                            <td class="fw-bold"><?= $index + 1 ?></td>
                            <td>
                                <?= date('d M Y', strtotime($row['tanggal'])) ?>
                                <small class="d-block text-muted"><?= date('H:i', strtotime($row['tanggal'])) ?></small>
                            </td>
                            <td>
                                <?php if($row['status_data'] === 'Aktif'): ?>
                                    <span class="badge badge-aktif px-3 rounded-pill">AKTIF</span>
                                <?php else: ?>
                                    <span class="badge badge-riwayat px-3 rounded-pill">RIWAYAT</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($row['nama_pelanggaran']) ?></td>
                            <td class="text-center">
                                <?php 
                                    // Logic warna kategori sederhana
                                    $cat_color = 'bg-secondary';
                                    if(strpos($row['nama_pelanggaran'], 'Level') !== false) {
                                        // Kalau nama pelanggaran ada kata "Level", kasih warna beda
                                        $cat_color = 'bg-info text-dark';
                                    }
                                ?>
                                <span class="badge <?= $cat_color ?>"><?= $row['kategori'] ?></span>
                            </td>
                            <td class="text-center fw-bold fs-5 <?= $row['status_data'] === 'Aktif' ? 'text-danger' : 'text-secondary' ?>">
                                <?= $row['poin'] ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../footer.php'; ?>

<!-- SCRIPT CHART.JS -->
<script>
    const ctx = document.getElementById('bahasaChart').getContext('2d');
    const myChart = new Chart(ctx, {
        type: 'line',
        data: {
            // Data diambil dari PHP pake json_encode
            labels: <?= json_encode($chart_labels) ?>,
            datasets: [{
                label: 'Poin Pelanggaran',
                data: <?= json_encode($chart_data) ?>,
                backgroundColor: 'rgba(79, 70, 229, 0.2)', // Warna area bawah grafik
                borderColor: '#4f46e5', // Warna garis utama
                pointBackgroundColor: <?= json_encode($chart_colors) ?>, // Warna titik beda2 (merah/abu)
                pointBorderColor: '#ffffff',
                pointRadius: 6,
                pointHoverRadius: 8,
                borderWidth: 2,
                tension: 0.3, // Biar garisnya agak melengkung dikit (smooth)
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false // Gak usah pake legend, menuhin tempat
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return ' Poin: ' + context.parsed.y;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: { display: true, text: 'Poin Pelanggaran' },
                    grid: { borderDash: [2, 4] }
                },
                x: {
                    grid: { display: false }
                }
            }
        }
    });
</script>

</body>
</html>