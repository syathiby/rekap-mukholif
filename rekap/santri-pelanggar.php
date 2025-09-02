<?php
require_once __DIR__ . '/../header.php';
guard('rekap_view_santri');

// 🔹 Ambil periode aktif
$q = mysqli_query($conn, "SELECT nilai FROM pengaturan WHERE nama = 'periode_aktif' LIMIT 1");
$row = mysqli_fetch_assoc($q);
$periode_aktif = $row['nilai'] ?? null;

if (!$periode_aktif) {
    die("⚠️ Periode aktif belum diset. Silakan atur dulu di halaman pengaturan.");
}

// 🔹 Ambil semua kamar unik
$kamars = mysqli_query($conn, "SELECT DISTINCT kamar FROM santri WHERE kamar IS NOT NULL AND kamar != '' ORDER BY CAST(REGEXP_REPLACE(kamar, '[^0-9]', '') AS UNSIGNED) ASC, REGEXP_REPLACE(kamar, '[0-9]', '') ASC");

// 🔹 Ambil filter dari URL
$filter_kamar = $_GET['kamar'] ?? null;
$start_date = $_GET['start_date'] ?? $periode_aktif;
$end_date   = $_GET['end_date'] ?? date("Y-m-d");

// === 1. QUERY UNTUK TABEL DETAIL ===
$sql_table = "
    SELECT s.id, s.nama, s.kelas, s.kamar,
           COALESCE(p.total_pelanggaran, 0) AS total_pelanggaran,
           COALESCE(p.telat_sholat, 0) AS telat_sholat,
           COALESCE(p.telat_kbm, 0) AS telat_kbm
    FROM santri s
    LEFT JOIN (
        SELECT santri_id,
               COUNT(*) AS total_pelanggaran,
               SUM(CASE WHEN jenis_pelanggaran_id = 1 THEN 1 ELSE 0 END) AS telat_sholat,
               SUM(CASE WHEN jenis_pelanggaran_id = 2 THEN 1 ELSE 0 END) AS telat_kbm
        FROM pelanggaran
        WHERE DATE(tanggal) BETWEEN ? AND ?
          AND jenis_pelanggaran_id IN (1, 2)
        GROUP BY santri_id
    ) p ON s.id = p.santri_id
";
$params = [$start_date, $end_date];
$types = 'ss';
if ($filter_kamar) {
    $sql_table .= " WHERE s.kamar = ?";
    $params[] = $filter_kamar;
    $types .= 's';
}
$sql_table .= " HAVING total_pelanggaran > 0 ORDER BY total_pelanggaran DESC, s.nama ASC";

$stmt_table = $conn->prepare($sql_table);
if ($stmt_table === false) die("❌ Query tabel error: " . $conn->error);
$stmt_table->bind_param($types, ...$params);
$stmt_table->execute();
$query_table = $stmt_table->get_result();


// === 2. QUERY BARU UNTUK DATA CHART ===
$sql_chart = "
    SELECT
        CASE
            WHEN p.jenis_pelanggaran_id = 2 THEN 'Telat KBM'
            WHEN TIME(p.tanggal) BETWEEN '03:30:00' AND '05:30:00' THEN 'Telat Sholat Subuh'
            WHEN TIME(p.tanggal) BETWEEN '11:30:00' AND '13:00:00' THEN 'Telat Sholat Dzuhur'
            WHEN TIME(p.tanggal) BETWEEN '14:45:00' AND '16:00:00' THEN 'Telat Sholat Ashar'
            WHEN TIME(p.tanggal) BETWEEN '17:30:00' AND '18:45:00' THEN 'Telat Sholat Maghrib'
            WHEN TIME(p.tanggal) BETWEEN '18:50:00' AND '20:30:00' THEN 'Telat Sholat Isya'
            ELSE 'Lainnya'
        END AS kategori,
        COUNT(*) AS jumlah
    FROM pelanggaran p
";
// Join ke tabel santri jika ada filter kamar
if ($filter_kamar) {
    $sql_chart .= " JOIN santri s ON p.santri_id = s.id";
}
$sql_chart .= " WHERE p.jenis_pelanggaran_id IN (1, 2) AND DATE(p.tanggal) BETWEEN ? AND ?";
if ($filter_kamar) {
    $sql_chart .= " AND s.kamar = ?";
}
$sql_chart .= " GROUP BY kategori HAVING jumlah > 0 ORDER BY kategori";

$stmt_chart = $conn->prepare($sql_chart);
if ($stmt_chart === false) die("❌ Query chart error: " . $conn->error);
$stmt_chart->bind_param($types, ...$params); // Menggunakan parameter dan tipe yang sama persis
$stmt_chart->execute();
$query_chart = $stmt_chart->get_result();

// Siapkan data untuk di-passing ke JavaScript
$chart_labels = [];
$chart_data = [];
$chart_colors = [];
$color_map = [
    'Telat Sholat Subuh'  => '#f59e0b', // Amber
    'Telat Sholat Dzuhur' => '#0ea5e9', // Sky
    'Telat Sholat Ashar'  => '#f97316', // Orange
    'Telat Sholat Maghrib'=> '#8b5cf6', // Violet
    'Telat Sholat Isya'   => '#6366f1', // Indigo
    'Telat KBM'           => '#10b981', // Emerald
    'Lainnya'             => '#6b7280', // Gray
];

while($row = $query_chart->fetch_assoc()){
    $chart_labels[] = $row['kategori'];
    $chart_data[] = $row['jumlah'];
    $chart_colors[] = $color_map[$row['kategori']] ?? '#9ca3af';
}
?>

<!-- Panggil library Chart.js dari CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    :root {
        --danger: #B91C1C; --danger-light: #FEE2E2; --danger-dark: #991B1B;
        --danger-text: #7F1D1D; --success: #15803D; --success-light: #DCFCE7;
        --text-primary: #1F2937; --text-secondary: #6B7280;
        --light-bg: #F9FAFB; --card-bg: #FFFFFF; --border-color: #E5E7EB;
        --gold: #FFD700; --silver: #C0C0C0; --bronze: #CD7F32;
    }
    .page-title {
        color: var(--danger); margin-bottom: 1.5rem; font-size: 2rem;
        font-weight: 700; display: flex; align-items: center; gap: 0.75rem;
    }
    .card {
        background-color: var(--card-bg); border-radius: 12px; padding: 1.5rem;
        margin-bottom: 1.5rem; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05);
        border: 1px solid var(--border-color);
    }
    .card-title {
        font-size: 1.125rem; font-weight: 600; margin-bottom: 1rem;
        color: var(--text-primary); display: flex; align-items: center; gap: 0.5rem;
    }
    .filter-form { display: flex; flex-wrap: wrap; gap: 1.5rem; align-items: flex-end; margin-bottom: 1.5rem; }
    .form-group label { display: block; margin-bottom: 0.5rem; font-size: 0.875rem; color: var(--text-secondary); font-weight: 500; }
    .filter-form input[type="date"] {
        padding: 0.5rem 0.75rem; border: 1px solid var(--border-color); border-radius: 6px;
    }
    .btn {
        background-color: var(--danger); color: white; border: none; padding: 0.5rem 1.25rem;
        border-radius: 6px; cursor: pointer; font-weight: 600; transition: background-color 0.3s;
    }
    .btn:hover { background-color: var(--danger-dark); }
    .kamar-nav { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 1rem; }
    .kamar-nav a {
        padding: 0.375rem 1rem; background: var(--light-bg); border: 1px solid var(--border-color);
        border-radius: 20px; text-decoration: none; color: var(--text-secondary);
        font-size: 0.875rem; font-weight: 500; transition: all 0.2s;
    }
    .kamar-nav .active { background: var(--danger); color: white; border-color: var(--danger); }
    
    table { width: 100%; border-collapse: collapse; } 
    table th, table td { padding: 0.75rem 1rem; text-align: left; vertical-align: middle; }
    table th { background-color: var(--light-bg); color: var(--danger-text); font-weight: 600; }
    
    /* ✅ REVISI KUNCI 1: Wrapper tabelnya dibikin bisa scroll horizontal */
    .table-responsive {
        display: block;
        width: 100%;
        overflow-x: auto;
    }
    
    .rank-1 { border-left: 4px solid var(--gold); }
    .rank-2 { border-left: 4px solid var(--silver); }
    .rank-3 { border-left: 4px solid var(--bronze); }

    .badge-danger { background-color: var(--danger-light); color: var(--danger-text); padding: 0.25rem 0.6rem; border-radius: 20px; font-weight: 600; }
    .btn-detail {
        display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem;
        background-color: var(--success-light); color: var(--success); padding: 0.375rem 0.875rem;
        text-decoration: none; border-radius: 6px; font-weight: 600;
    }
    .no-data { text-align: center; padding: 4rem; color: var(--text-secondary); }

    /* ✅ REVISI KUNCI 2: CSS Mobile dibikin lebih simpel */
    @media (max-width: 768px) {
        /* Biar tulisan di dalem tabel gak kepotong aneh */
        table th, table td {
            white-space: nowrap;
            padding: 0.6rem 0.8rem; /* Padding dikecilin dikit */
        }
        .page-title {
            font-size: 1.5rem;
        }
    }
</style>

<div class="container-fluid mt-4 mb-5">
    <h1 class="page-title"><i class="fas fa-exclamation-triangle"></i>Laporan Pelanggaran Santri</h1>
    
    <div class="card">
        <h2 class="card-title"><i class="fas fa-filter"></i>Filter Data</h2>
        <form method="get" class="filter-form">
            <div class="form-group">
                <label for="start_date">Dari Tanggal</label>
                <input type="date" name="start_date" id="start_date" value="<?= htmlspecialchars($start_date) ?>">
            </div>
            <div class="form-group">
                <label for="end_date">Sampai Tanggal</label>
                <input type="date" name="end_date" id="end_date" value="<?= htmlspecialchars($end_date) ?>">
            </div>
            <?php if ($filter_kamar): ?>
                <input type="hidden" name="kamar" value="<?= htmlspecialchars($filter_kamar) ?>">
            <?php endif; ?>
            <button type="submit" class="btn"><i class="fas fa-search"></i> Terapkan</button>
        </form>
        <hr style="margin: 1.5rem 0;">
        <div class="kamar-nav">
            <a href="?start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" class="<?= !$filter_kamar ? 'active' : '' ?>">Semua Kamar</a>
            <?php 
            mysqli_data_seek($kamars, 0);
            while ($k = mysqli_fetch_assoc($kamars)): ?>
                <a href="?kamar=<?= urlencode($k['kamar']) ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>"
                   class="<?= ($filter_kamar == $k['kamar']) ? 'active' : '' ?>">
                    <?= htmlspecialchars($k['kamar']) ?>
                </a>
            <?php endwhile; ?>
        </div>
    </div>
    
    <div class="row">
        <!-- Kolom untuk Tabel -->
        <div class="col-lg-8">
            <div class="card">
                <!-- ✅ REVISI KUNCI 3: Pastikan class table-responsive ngebungkus tabelnya -->
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Peringkat</th>
                                <th>Nama Santri</th>
                                <th>Total</th>
                                <th>Detail Pelanggaran</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $no = 1;
                            if (mysqli_num_rows($query_table) === 0) {
                                echo '<tr><td colspan="5" class="no-data"><i class="fas fa-check-circle"></i><p><b>Hebat!</b><br>Tidak ada pelanggaran pada periode ini.</p></td></tr>';
                            } else {
                                while ($row = mysqli_fetch_assoc($query_table)) {
                                    $rank_class = match($no) { 1 => "rank-1", 2 => "rank-2", 3 => "rank-3", default => "" };
                                    $peringkat_display = $no;
                                    if ($no == 1) $peringkat_display = '<i class="fas fa-trophy" style="color: var(--gold);"></i> 1';
                                    elseif ($no == 2) $peringkat_display = '<i class="fas fa-trophy" style="color: var(--silver);"></i> 2';
                                    elseif ($no == 3) $peringkat_display = '<i class="fas fa-trophy" style="color: var(--bronze);"></i> 3';

                                    // ✅ REVISI KUNCI 4: HTML di dalem TR disederhanain, hapus data-label
                                    echo "<tr class='{$rank_class}'>
                                            <td>{$peringkat_display}</td>
                                            <td>
                                                <div>
                                                    <span class='fw-bold'>{$row['nama']}</span><br>
                                                    <span class='small text-muted'>Kelas: {$row['kelas']} | Kamar: {$row['kamar']}</span>
                                                </div>
                                            </td>
                                            <td class='text-center'>
                                                <span class='badge badge-danger'>{$row['total_pelanggaran']}</span>
                                            </td>
                                            <td class='small'>
                                                <div class='d-flex flex-column'>
                                                    <span class='d-flex align-items-center gap-1' style='color:#C2410C;'>
                                                        <i class='fas fa-pray fa-fw'></i> Telat Sholat: <b>{$row['telat_sholat']}</b>
                                                    </span>
                                                    <span class='d-flex align-items-center gap-1' style='color:#1D4ED8;'>
                                                        <i class='fas fa-book fa-fw'></i> Telat KBM: <b>{$row['telat_kbm']}</b>
                                                    </span>
                                                </div>
                                            </td>
                                            <td>
                                                <a href='detail-pelanggaran.php?id={$row['id']}&start_date=" . htmlspecialchars($start_date) . "&end_date=" . htmlspecialchars($end_date) . "' class='btn-detail'>
                                                    <i class='fas fa-info-circle'></i> Detail
                                                </a>
                                            </td>
                                        </tr>";
                                    $no++;
                                }
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Kolom untuk Chart -->
        <div class="col-lg-4">
            <div class="card">
                <h2 class="card-title"><i class="fas fa-chart-pie"></i>Ringkasan Grafik</h2>
                <?php if (!empty($chart_data)): ?>
                    <div style="position: relative; height: 350px; width: 100%;">
                        <canvas id="pelanggaranChart"></canvas>
                    </div>
                <?php else: ?>
                    <div class="no-data" style="padding: 2rem 1rem;">
                        <p>Tidak ada data untuk ditampilkan di grafik.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const chartLabels = <?= json_encode($chart_labels) ?>;
    const chartData = <?= json_encode($chart_data) ?>;
    const chartColors = <?= json_encode($chart_colors) ?>;

    if (chartData.length > 0) {
        const ctx = document.getElementById('pelanggaranChart').getContext('2d');
        const pelanggaranChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'Jumlah Pelanggaran',
                    data: chartData,
                    backgroundColor: chartColors,
                    borderColor: '#ffffff',
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
                        labels: { padding: 15, boxWidth: 12, font: { family: 'Poppins', } }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) { label += ': '; }
                                if (context.parsed !== null) { label += context.parsed + ' kali'; }
                                return label;
                            }
                        }
                    }
                },
                cutout: '65%'
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../footer.php'; ?>