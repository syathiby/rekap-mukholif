<?php 
// 1. Panggil 'Otak' aplikasi
require_once __DIR__ . '/../bootstrap/init.php';

// 2. SATPAM: Cek izin buat liat statistik
guard('rekap_view_statistik'); 

// 3. Ambil data filter dari URL
$rentang = $_GET['rentang'] ?? '7_hari';
$tanggal_mulai_input = $_GET['mulai'] ?? '';
$tanggal_selesai_input = $_GET['selesai'] ?? '';
// Ambil filter feed
$filter_bagian = $_GET['bagian'] ?? '';
$filter_kategori = $_GET['kategori'] ?? '';

// --- Ambil data untuk dropdown filter bagian ---
$bagian_list_query = "SELECT DISTINCT bagian FROM jenis_pelanggaran ORDER BY bagian ASC";
$bagian_list_result = mysqli_query($conn, $bagian_list_query);

// 4. Siapin variabel tanggal buat query
$params_date = [];
$types_date = '';

// Tentukan tanggal mulai dan selesai berdasarkan filter 'rentang'
if ($rentang == 'custom' && !empty($tanggal_mulai_input) && !empty($tanggal_selesai_input)) {
    $tanggal_mulai = $tanggal_mulai_input . ' 00:00:00';
    $tanggal_selesai = $tanggal_selesai_input . ' 23:59:59';
    $judul_rentang = "Periode " . date('d/m/Y', strtotime($tanggal_mulai)) . " - " . date('d/m/Y', strtotime($tanggal_selesai));
} else {
    $days_ago = 6; // default 7 hari
    $judul_rentang = "7 Hari Terakhir";
    if ($rentang == '3_hari') {
        $days_ago = 2;
        $judul_rentang = "3 Hari Terakhir";
    } elseif ($rentang == '30_hari') {
        $days_ago = 29;
        $judul_rentang = "30 Hari Terakhir";
    }
    $tanggal_mulai = date('Y-m-d 00:00:00', strtotime("-{$days_ago} days"));
    $tanggal_selesai = date('Y-m-d 23:59:59');
}

// Kondisi WHERE untuk tanggal (dipakai di semua query)
$date_condition_umum = "p.tanggal BETWEEN ? AND ?";
$date_condition_kebersihan = "tanggal BETWEEN ? AND ?";
$params_date = [$tanggal_mulai, $tanggal_selesai];
$types_date = "ss";

// 5. --- EKSEKUSI QUERY UNTUK CHARTS (HANYA PAKAI FILTER TANGGAL) ---

// Query 1: Tren Harian (Line Chart)
$query_tren_harian = "
    SELECT DATE(p.tanggal) AS hari, COUNT(p.id) AS jumlah 
    FROM pelanggaran p 
    WHERE $date_condition_umum 
    GROUP BY DATE(p.tanggal) 
    ORDER BY hari ASC
";
$stmt_tren = mysqli_prepare($conn, $query_tren_harian);
mysqli_stmt_bind_param($stmt_tren, $types_date, ...$params_date);
mysqli_stmt_execute($stmt_tren);
$result_tren = mysqli_stmt_get_result($stmt_tren);

$tren_harian_data = [];
while ($row = mysqli_fetch_assoc($result_tren)) { $tren_harian_data[] = $row; }
mysqli_stmt_close($stmt_tren);
$tren_labels = [];
$tren_values = [];
$period = new DatePeriod( new DateTime(date('Y-m-d', strtotime($tanggal_mulai))), new DateInterval('P1D'), (new DateTime(date('Y-m-d', strtotime($tanggal_selesai))))->modify('+1 day') );
$data_lookup = array_column($tren_harian_data, 'jumlah', 'hari');
foreach ($period as $date) {
    $hari = $date->format('Y-m-d');
    $tren_labels[] = $date->format('d/m');
    $tren_values[] = $data_lookup[$hari] ?? 0;
}


// Query 2: Top 5 Pelanggaran (Doughnut Chart)
$query_top_5 = "
    SELECT jp.nama_pelanggaran, COUNT(p.id) AS jumlah 
    FROM pelanggaran p 
    JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id 
    WHERE $date_condition_umum 
    GROUP BY jp.id 
    ORDER BY jumlah DESC 
    LIMIT 5
";
$stmt_top_5 = mysqli_prepare($conn, $query_top_5);
mysqli_stmt_bind_param($stmt_top_5, $types_date, ...$params_date);
mysqli_stmt_execute($stmt_top_5);
$result_top_5 = mysqli_stmt_get_result($stmt_top_5);

$top_pelanggaran_labels = [];
$top_pelanggaran_values = [];
while ($row = mysqli_fetch_assoc($result_top_5)) {
    $top_pelanggaran_labels[] = $row['nama_pelanggaran'];
    $top_pelanggaran_values[] = $row['jumlah'];
}
mysqli_stmt_close($stmt_top_5);


// Query 3: Komposisi Bagian (Pisah Kebersihan)
$bagian_data = [];

// Query 3a: Pelanggaran Umum (Kesantrian, Bahasa, dll)
$query_bagian_umum = "
    SELECT jp.bagian, COUNT(p.id) AS jumlah 
    FROM pelanggaran p 
    JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id 
    WHERE $date_condition_umum 
    GROUP BY jp.bagian
";
$stmt_bagian_umum = mysqli_prepare($conn, $query_bagian_umum);
mysqli_stmt_bind_param($stmt_bagian_umum, $types_date, ...$params_date);
mysqli_stmt_execute($stmt_bagian_umum);
$result_bagian_umum = mysqli_stmt_get_result($stmt_bagian_umum);

while ($row = mysqli_fetch_assoc($result_bagian_umum)) {
    $bagian_data[$row['bagian']] = $row['jumlah'];
}
mysqli_stmt_close($stmt_bagian_umum);

// Query 3b: Pelanggaran Kebersihan
$query_bagian_kebersihan = "
    SELECT COUNT(id) AS jumlah 
    FROM pelanggaran_kebersihan 
    WHERE $date_condition_kebersihan
";
$stmt_bagian_kebersihan = mysqli_prepare($conn, $query_bagian_kebersihan);
mysqli_stmt_bind_param($stmt_bagian_kebersihan, $types_date, ...$params_date);
mysqli_stmt_execute($stmt_bagian_kebersihan);
$result_kebersihan = mysqli_stmt_get_result($stmt_bagian_kebersihan);
$row_kebersihan = mysqli_fetch_assoc($result_kebersihan);
if ($row_kebersihan && $row_kebersihan['jumlah'] > 0) {
    $bagian_data['Kebersihan'] = $row_kebersihan['jumlah'];
}
mysqli_stmt_close($stmt_bagian_kebersihan);

arsort($bagian_data);
$bagian_labels = array_keys($bagian_data);
$bagian_values = array_values($bagian_data);


// --- Query 4 (Feed) DENGAN FILTER TAMBAHAN ---

// Buat kondisi filter Bagian & Kategori
$jp_conditions = [];
$params_jp = [];
$types_jp = '';

if (!empty($filter_bagian)) {
    $jp_conditions[] = "jp.bagian = ?";
    $params_jp[] = $filter_bagian;
    $types_jp .= 's';
}
if (!empty($filter_kategori)) {
    $jp_conditions[] = "jp.kategori = ?";
    $params_jp[] = $filter_kategori;
    $types_jp .= 's';
}

$jp_where_string = "";
if (count($jp_conditions) > 0) {
    $jp_where_string = " AND " . implode(" AND ", $jp_conditions);
}

// Gabungkan parameter tanggal + filter jp
$params_all = array_merge($params_date, $params_jp);
$types_all = $types_date . $types_jp;

// Query 4: Feed Pelanggaran Terkini
$query_feed = "
    SELECT p.tanggal, s.nama AS nama_santri, jp.nama_pelanggaran, jp.bagian, jp.poin, jp.kategori
    FROM pelanggaran p 
    JOIN santri s ON p.santri_id = s.id 
    JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id 
    WHERE $date_condition_umum $jp_where_string
    ORDER BY 
        FIELD(jp.bagian, 'Kesantrian', 'Diniyyah', 'Bahasa', 'Tahfidz', 'Pengabdian'), 
        p.tanggal DESC
";
$stmt_feed = mysqli_prepare($conn, $query_feed);
mysqli_stmt_bind_param($stmt_feed, $types_all, ...$params_all);
mysqli_stmt_execute($stmt_feed);
$result_feed = mysqli_stmt_get_result($stmt_feed);

$feed_terkini_data = [];
while ($row = mysqli_fetch_assoc($result_feed)) {
    $feed_terkini_data[] = $row;
}
mysqli_stmt_close($stmt_feed);


// 6. Panggil Tampilan Header
require_once __DIR__ . '/../layouts/header.php';
?>

<div class="container-fluid py-4 px-4">

    <!-- Header Page -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h3 class="fw-bolder text-dark mb-1"><i class="fas fa-chart-line text-primary me-2"></i>Tren Pelanggaran Terkini</h3>
            <p class="text-muted mb-0"><?= $judul_rentang ?></p>
        </div>
        <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-circle d-none d-md-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
            <i class="fas fa-chart-pie fs-4"></i>
        </div>
    </div>

    <form method="GET" action="" id="filterForm">

        <!-- Filter Card -->
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-4">
                <div class="row g-4 align-items-end">
                    
                    <div class="col-md-5 col-lg-4">
                        <label for="rentang" class="form-label fw-bold text-secondary small mb-2">RENTANG WAKTU (GLOBAL)</label>
                        <select name="rentang" id="rentang" class="form-select">
                            <option value="3_hari" <?= ($rentang == '3_hari') ? 'selected' : '' ?>>3 Hari Terakhir</option>
                            <option value="7_hari" <?= ($rentang == '7_hari') ? 'selected' : '' ?>>7 Hari Terakhir</option>
                            <option value="30_hari" <?= ($rentang == '30_hari') ? 'selected' : '' ?>>30 Hari Terakhir</option>
                            <option value="custom" <?= ($rentang == 'custom') ? 'selected' : '' ?>>Pilih Tanggal (Custom)</option>
                        </select>
                    </div>
                    
                    <div class="col-md-7 col-lg-5" id="customDateWrapper" style="display: <?= ($rentang == 'custom') ? 'block' : 'none' ?>;">
                        <div class="row g-2">
                            <div class="col-6">
                        <label for="mulai" class="form-label fw-bold text-secondary small mb-2">MULAI TGL</label>
                                <input type="date" name="mulai" id="mulai" class="form-control" style="border-radius: 0.5rem;" value="<?= htmlspecialchars($tanggal_mulai_input) ?>">
                            </div>
                            <div class="col-6">
                                <label for="selesai" class="form-label fw-bold text-secondary small mb-2">SAMPAI TGL</label>
                                <input type="date" name="selesai" id="selesai" class="form-control" style="border-radius: 0.5rem;" value="<?= htmlspecialchars($tanggal_selesai_input) ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-12 col-lg-3 d-grid">
                        <button type="submit" class="btn btn-primary fw-bold" style="border-radius: 0.5rem; padding: 0.6rem;"><i class="fas fa-filter me-2"></i> TERAPKAN FILTER</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tren Harian Chart -->
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-header bg-white border-0 pt-4 pb-2 px-4">
                <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-calendar-day me-2 text-primary"></i>Tren Pelanggaran Harian</h6>
            </div>
            <div class="card-body px-4 pb-4 pt-2">
                <div style="position: relative; height: 320px;">
                    <canvas id="trenHarianChart"></canvas>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-header bg-white border-0 pt-4 pb-2 px-4">
                        <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-star me-2 text-warning"></i>Top 5 Pelanggaran</h6>
                    </div>
                    <div class="card-body p-4 d-flex align-items-center">
                        <div style="position: relative; height: 350px; width: 100%;">
                            <canvas id="topPelanggaranChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-header bg-white border-0 pt-4 pb-2 px-4">
                        <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-puzzle-piece me-2 text-info"></i>Komposisi Per Bagian</h6>
                    </div>
                    <div class="card-body p-4 d-flex align-items-center">
                        <div style="position: relative; height: 350px; width: 100%;">
                            <canvas id="bagianChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 mb-5">
            <div class="card-header bg-white border-0 pt-4 pb-3 px-4">
                <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-history me-2 text-secondary"></i>Feed Pelanggaran Terkini</h6>
            </div>
            
            <div class="card-body px-4 py-3 bg-light border-bottom border-top">
                <h6 class="mb-3 fw-bold text-secondary small">FILTER SPESIFIK FEED</h6>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="bagian" class="form-label fw-bold text-muted" style="font-size: 0.8rem;">BAGIAN</label>
                        <select name="bagian" id="bagian" class="form-select form-select-sm shadow-sm" style="border-radius: 0.5rem; padding: 0.5rem;">
                            <option value="">-- Semua Bagian --</option>
                            <?php mysqli_data_seek($bagian_list_result, 0); ?>
                            <?php while ($bagian_row = mysqli_fetch_assoc($bagian_list_result)) : ?>
                                <option value="<?= htmlspecialchars($bagian_row['bagian']); ?>" <?= ($filter_bagian == $bagian_row['bagian']) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars(format_typing($bagian_row['bagian'])); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="kategori" class="form-label fw-bold text-muted" style="font-size: 0.8rem;">KATEGORI</label>
                        <select name="kategori" id="kategori" class="form-select form-select-sm shadow-sm" style="border-radius: 0.5rem; padding: 0.5rem;">
                            <option value="">-- Semua Kategori --</option>
                            <option value="Ringan" <?= ($filter_kategori == 'Ringan') ? 'selected' : '' ?>>Ringan</option>
                            <option value="Sedang" <?= ($filter_kategori == 'Sedang') ? 'selected' : '' ?>>Sedang</option>
                            <option value="Berat" <?= ($filter_kategori == 'Berat') ? 'selected' : '' ?>>Berat</option>
                            <option value="Sangat Berat" <?= ($filter_kategori == 'Sangat Berat') ? 'selected' : '' ?>>Sangat Berat</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Waktu</th>
                            <th scope="col">Nama Santri</th>
                            <th scope="col">Pelanggaran</th>
                            <th scope="col">Bagian</th>
                            <th scope="col" class="text-center">Kategori</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($feed_terkini_data) == 0): ?>
                            <tr>
                                <td colspan="5" class="text-center py-5">
                                    <i class="fas fa-search fa-3x text-muted mb-2"></i>
                                    <h5 class="mb-0">Data Tidak Ditemukan</h5>
                                    <p class="text-muted">Coba ubah atau reset filter yang Anda gunakan.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($feed_terkini_data as $row): ?>
                                <?php
                                    $badge_class = match ($row['kategori']) {
                                        'Ringan' => 'bg-info',
                                        'Sedang' => 'bg-warning',
                                        'Berat' => 'bg-danger',
                                        'Sangat Berat' => 'bg-dark',
                                        default => 'bg-secondary',
                                    };
                                ?>
                                <tr>
                                    <td class="align-middle">
                                        <span class="fw-bold"><?= date('H:i', strtotime($row['tanggal'])) ?></span>
                                        <small class="d-block text-muted"><?= date('d M Y', strtotime($row['tanggal'])) ?></small>
                                    </td>
                                    <td class="align-middle"><?= htmlspecialchars($row['nama_santri']) ?></td>
                                    <td class="align-middle"><?= htmlspecialchars($row['nama_pelanggaran']) ?></td>
                                    <td class="align-middle"><?= htmlspecialchars($row['bagian']) ?></td>
                                    <td class="text-center align-middle">
                                        <span class="badge <?= $badge_class ?>"><?= htmlspecialchars($row['kategori']) ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </form> </div>
<?php 
// 7. Panggil Tampilan Footer (isinya JS, dll)
require_once __DIR__ . '/../layouts/footer.php'; 
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // Pastikan plugin-nya udah di-load sebelum ini
    if (typeof ChartDataLabels !== 'undefined') {
        Chart.register(ChartDataLabels);
    } else {
        console.error('Chart.js Datalabels plugin not found. Please add the CDN link.');
    }

    // --- LOGIKA FILTER ---
    const rentangSelect = document.getElementById('rentang');
    const customDateWrapper = document.getElementById('customDateWrapper');

    // === INI KODE BARU UNTUK AUTO-SUBMIT FEED FILTER ===
    const filterForm = document.getElementById('filterForm'); // Ambil form utamanya
    const bagianFeedSelect = document.getElementById('bagian'); // Filter bagian di feed
    const kategoriFeedSelect = document.getElementById('kategori'); // Filter kategori di feed

    // Fungsi untuk submit form
    function submitFeedFilter() {
        filterForm.submit();
    }

    // Pasang listener ke select 'bagian'
    if (bagianFeedSelect) {
        bagianFeedSelect.addEventListener('change', submitFeedFilter);
    }
    
    // Pasang listener ke select 'kategori'
    if (kategoriFeedSelect) {
        kategoriFeedSelect.addEventListener('change', submitFeedFilter);
    }
    // === AKHIR KODE BARU ===


    // Ini listener untuk filter rentang (YANG ATAS), ini JANGAN diubah
    // Cuma nampilin/sembunyiin custom date, BUKAN submit form
    if (rentangSelect) {
        rentangSelect.addEventListener('change', function() {
            if (this.value === 'custom') {
                customDateWrapper.style.display = 'block';
            } else {
                customDateWrapper.style.display = 'none';
            }
        });
    }

    // --- Inisialisasi Chart ---
    const ctxLine = document.getElementById('trenHarianChart')?.getContext('2d');
    const ctxDoughnutTop5 = document.getElementById('topPelanggaranChart')?.getContext('2d');
    const ctxDoughnutBagian = document.getElementById('bagianChart')?.getContext('2d');

    // Palet Warna Keren
    const chartColors = [
        '#3498db', '#e74c3c', '#f1c40f', '#2ecc71', '#9b59b6', 
        '#1abc9c', '#e67e22', '#34495e', '#7f8c8d'
    ];
    
    // 1. Chart Tren Harian (Line)
    if (ctxLine) {
        new Chart(ctxLine, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($tren_labels); ?>,
                datasets: [{
                    label: 'Jumlah Pelanggaran',
                    data: <?php echo json_encode($tren_values); ?>,
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    borderColor: '#3498db',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false, 
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1 }
                    }
                },
                plugins: {
                    legend: { display: false },
                    datalabels: { display: false }
                }
            }
        });
    }

    // 2. Chart Top 5 Pelanggaran (Doughnut + Persentase)
    if (ctxDoughnutTop5) {
        new Chart(ctxDoughnutTop5, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($top_pelanggaran_labels); ?>,
                datasets: [{
                    label: 'Jumlah',
                    data: <?php echo json_encode($top_pelanggaran_values); ?>,
                    backgroundColor: chartColors,
                    borderColor: '#fff',
                    borderWidth: 2,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false, 
                plugins: {
                    legend: {
                        position: 'bottom',
                    },
                    datalabels: {
                        formatter: (value, ctx) => {
                            const sum = ctx.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                            const percentage = (value * 100 / sum).toFixed(1) + '%';
                            if (value === 0) return '';
                            return percentage;
                        },
                        color: '#fff',
                        font: {
                            weight: 'bold',
                            size: 14,
                        }
                    }
                }
            }
        });
    }

    // 3. Chart Pelanggaran per Bagian (Doughnut)
    if (ctxDoughnutBagian) {
        new Chart(ctxDoughnutBagian, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($bagian_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($bagian_values); ?>,
                    backgroundColor: chartColors,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false, 
                plugins: {
                    legend: {
                        position: 'bottom',
                    },
                    datalabels: {
                         formatter: (value, ctx) => {
                            const sum = ctx.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                            const percentage = (value * 100 / sum).toFixed(1) + '%';
                            if (value === 0) return '';
                            return percentage;
                        },
                        color: '#fff',
                        font: { weight: 'bold' }
                    }
                }
            }
        });
    }

});
</script>