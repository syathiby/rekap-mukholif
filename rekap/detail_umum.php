<?php
// 1. Panggil 'Otak' aplikasi
require_once __DIR__ . '/../bootstrap/init.php';

// 2. Jalankan 'SATPAM'
guard('rekap_view_umum');

// 3. Ambil Parameter
$santri_id = (int)($_GET['id'] ?? 0);
if ($santri_id <= 0) {
    $_SESSION['flash_error'] = "Data santri tidak valid.";
    header("Location: umum.php");
    exit;
}

$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-1 year'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// 4. Ambil Info Santri
$stmt_santri = $conn->prepare("SELECT * FROM santri WHERE id = ?");
$stmt_santri->bind_param("i", $santri_id);
$stmt_santri->execute();
$santri = $stmt_santri->get_result()->fetch_assoc();
if (!$santri) {
    die("Data santri tidak ditemukan.");
}

// 5. Query Data Pelanggaran (Untuk Chart)
$sql_p_kategori = "SELECT jp.kategori, COUNT(p.id) as jumlah, SUM(jp.poin) as total_poin FROM pelanggaran p JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id WHERE p.santri_id = ? AND DATE(p.tanggal) BETWEEN ? AND ? GROUP BY jp.kategori";
$stmt_pk = $conn->prepare($sql_p_kategori);
$stmt_pk->bind_param("iss", $santri_id, $start_date, $end_date);
$stmt_pk->execute();
$res_pk = $stmt_pk->get_result();
$pelanggaran_kategori = [];
$total_poin_pelanggaran = 0;
while ($row = $res_pk->fetch_assoc()) {
    $pelanggaran_kategori[] = $row;
    $total_poin_pelanggaran += $row['total_poin'];
}

// 6. Query Data Reward (Untuk Chart)
$sql_r_kategori = "SELECT 'Reward' as kategori, COUNT(dr.id) as jumlah, SUM(jr.poin_reward) as total_poin FROM daftar_reward dr JOIN jenis_reward jr ON dr.jenis_reward_id = jr.id WHERE dr.santri_id = ? AND DATE(dr.tanggal) BETWEEN ? AND ?";
$stmt_rk = $conn->prepare($sql_r_kategori);
$stmt_rk->bind_param("iss", $santri_id, $start_date, $end_date);
$stmt_rk->execute();
$res_rk = $stmt_rk->get_result();
$reward_kategori = [];
$total_poin_reward = 0;
if($row = $res_rk->fetch_assoc()){
    if($row['jumlah'] > 0){
        $reward_kategori[] = $row;
        $total_poin_reward = $row['total_poin'];
    }
}

// 7. Tren Bulanan (Gabungan Pelanggaran & Reward)
$sql_tren_p = "SELECT DATE_FORMAT(p.tanggal, '%Y-%m') as bulan, SUM(jp.poin) as poin FROM pelanggaran p JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id WHERE p.santri_id = ? AND DATE(p.tanggal) BETWEEN ? AND ? GROUP BY bulan ORDER BY bulan ASC";
$stmt_tp = $conn->prepare($sql_tren_p);
$stmt_tp->bind_param("iss", $santri_id, $start_date, $end_date);
$stmt_tp->execute();
$res_tp = $stmt_tp->get_result();
$tren_p = [];
while($row = $res_tp->fetch_assoc()) { $tren_p[$row['bulan']] = $row['poin']; }

$sql_tren_r = "SELECT DATE_FORMAT(dr.tanggal, '%Y-%m') as bulan, SUM(jr.poin_reward) as poin FROM daftar_reward dr JOIN jenis_reward jr ON dr.jenis_reward_id = jr.id WHERE dr.santri_id = ? AND DATE(dr.tanggal) BETWEEN ? AND ? GROUP BY bulan ORDER BY bulan ASC";
$stmt_tr = $conn->prepare($sql_tren_r);
$stmt_tr->bind_param("iss", $santri_id, $start_date, $end_date);
$stmt_tr->execute();
$res_tr = $stmt_tr->get_result();
$tren_r = [];
while($row = $res_tr->fetch_assoc()) { $tren_r[$row['bulan']] = $row['poin']; }

// Menggabungkan label bulan
$all_months = array_unique(array_merge(array_keys($tren_p), array_keys($tren_r)));
sort($all_months);

$labels = [];
$data_p = [];
$data_r = [];
foreach($all_months as $m) {
    $labels[] = date('M Y', strtotime($m . '-01'));
    $data_p[] = $tren_p[$m] ?? 0;
    $data_r[] = $tren_r[$m] ?? 0;
}

// Kesimpulan Karakter
$rasio = '';
$karakter_class = '';
$karakter_icon = '';
if ($total_poin_reward == 0 && $total_poin_pelanggaran == 0) {
    $rasio = "Santri belum memiliki catatan pelanggaran maupun reward pada periode ini.";
    $karakter_class = "text-secondary";
    $karakter_icon = "fa-minus-circle";
} elseif ($total_poin_reward >= $total_poin_pelanggaran * 2) {
    $rasio = "Sangat Baik. Santri menunjukkan prestasi yang jauh melampaui catatan pelanggarannya.";
    $karakter_class = "text-success";
    $karakter_icon = "fa-star";
} elseif ($total_poin_reward > $total_poin_pelanggaran) {
    $rasio = "Baik. Santri lebih banyak mendapatkan apresiasi/reward dibanding melakukan pelanggaran.";
    $karakter_class = "text-primary";
    $karakter_icon = "fa-thumbs-up";
} elseif ($total_poin_pelanggaran >= $total_poin_reward * 2 && $total_poin_reward > 0) {
    $rasio = "Perlu Perhatian Khusus. Pelanggaran santri jauh mendominasi dibandingkan prestasinya.";
    $karakter_class = "text-danger";
    $karakter_icon = "fa-exclamation-triangle";
} elseif ($total_poin_pelanggaran > $total_poin_reward) {
    $rasio = "Kurang Disiplin. Catatan pelanggaran masih lebih tinggi daripada prestasi/reward.";
    $karakter_class = "text-warning";
    $karakter_icon = "fa-exclamation-circle";
} else {
    // Reward == Pelanggaran (dan tidak 0)
    $rasio = "Seimbang. Santri memiliki jumlah poin pelanggaran dan reward yang seimbang.";
    $karakter_class = "text-info";
    $karakter_icon = "fa-balance-scale";
}

// Logika Peringatan (SP)
$sp_status = "";
$sp_class = "";
if ($santri['poin_aktif'] >= 300) {
    $sp_status = "PERINGATAN 3";
    $sp_class = "bg-danger text-white";
} elseif ($santri['poin_aktif'] >= 200) {
    $sp_status = "PERINGATAN 2";
    $sp_class = "bg-warning text-dark";
} elseif ($santri['poin_aktif'] >= 100) {
    $sp_status = "PERINGATAN 1";
    $sp_class = "bg-info text-white";
}

require_once __DIR__ . '/../layouts/header.php';
?>

<style>
    .info-card { background: white; border-radius: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); padding: 1.5rem; height: 100%; border-top: 4px solid var(--primary); }
    .info-card.danger { border-top-color: #ef4444; }
    .info-card.success { border-top-color: #10b981; }
    .info-card.warning { border-top-color: #f59e0b; }
    
    .chart-container { background: white; border-radius: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); padding: 1.5rem; margin-bottom: 1.5rem; }
    .big-number { font-size: 2.5rem; font-weight: 700; line-height: 1; margin-top: 0.5rem; }
    
    .character-summary { background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-radius: 15px; padding: 2rem; text-align: center; margin-bottom: 2rem; box-shadow: inset 0 2px 4px rgba(0,0,0,0.02); }
    .character-icon { font-size: 3rem; margin-bottom: 1rem; }
</style>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="page-title m-0"><i class="fas fa-chart-pie me-2"></i>Analisis Karakter Santri</h1>
        <a href="umum.php?start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" class="btn btn-outline-secondary rounded-pill">
            <i class="fas fa-arrow-left me-1"></i> Kembali
        </a>
    </div>

    <div class="character-summary">
        <i class="fas <?= $karakter_icon ?> character-icon <?= $karakter_class ?>"></i>
        <h3 class="fw-bold mb-2"><?= htmlspecialchars($santri['nama']) ?></h3>
        <p class="text-muted mb-3">Kelas: <?= htmlspecialchars($santri['kelas']) ?> | Kamar: <?= htmlspecialchars($santri['kamar']) ?></p>
        
        <?php if($sp_status): ?>
            <div class="mb-3">
                <span class="badge rounded-pill <?= $sp_class ?> px-4 py-2 shadow-sm" style="font-size: 0.9rem; letter-spacing: 1px;">
                    <i class="fas fa-exclamation-triangle me-2"></i><?= $sp_status ?>
                </span>
            </div>
        <?php endif; ?>

        <p class="lead mb-0 <?= $karakter_class ?> fw-semibold"><?= $rasio ?></p>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="info-card">
                <div class="text-muted text-uppercase fw-bold" style="letter-spacing: 1px; font-size: 0.8rem;">Poin Aktif</div>
                <div class="big-number text-dark"><?= htmlspecialchars($santri['poin_aktif']) ?></div>
                <div class="text-muted small mt-2">Sisa poin saat ini</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="info-card danger">
                <div class="text-muted text-uppercase fw-bold" style="letter-spacing: 1px; font-size: 0.8rem;">Total Pelanggaran</div>
                <div class="big-number text-danger"><?= $total_poin_pelanggaran ?></div>
                <div class="text-muted small mt-2">Poin dalam periode difilter</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="info-card success">
                <div class="text-muted text-uppercase fw-bold" style="letter-spacing: 1px; font-size: 0.8rem;">Total Reward</div>
                <div class="big-number text-success">+<?= $total_poin_reward ?></div>
                <div class="text-muted small mt-2">Poin dalam periode difilter</div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Chart Tren Bulanan -->
        <div class="col-lg-8">
            <div class="chart-container">
                <h5 class="fw-bold mb-4"><i class="fas fa-chart-line me-2 text-primary"></i>Tren Pelanggaran vs Reward</h5>
                <?php if(empty($labels)): ?>
                    <div class="text-center text-muted p-5">Belum ada data untuk ditampilkan.</div>
                <?php else: ?>
                    <canvas id="trendChart" height="100"></canvas>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Chart Komposisi Kategori -->
        <div class="col-lg-4">
            <div class="chart-container">
                <h5 class="fw-bold mb-4"><i class="fas fa-chart-pie me-2 text-primary"></i>Komposisi Kategori</h5>
                <?php if(empty($pelanggaran_kategori) && empty($reward_kategori)): ?>
                    <div class="text-center text-muted p-5">Belum ada data.</div>
                <?php else: ?>
                    <canvas id="compositionChart" height="200"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    <?php if(!empty($labels)): ?>
    // Line Chart: Trend Bulanan
    const trendCtx = document.getElementById('trendChart').getContext('2d');
    new Chart(trendCtx, {
        type: 'line',
        data: {
            labels: <?= json_encode($labels) ?>,
            datasets: [
                {
                    label: 'Poin Pelanggaran',
                    data: <?= json_encode($data_p) ?>,
                    borderColor: '#ef4444',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                },
                {
                    label: 'Poin Reward',
                    data: <?= json_encode($data_r) ?>,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }
            ]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'top' }
            },
            scales: {
                y: { beginAtZero: true }
            }
        }
    });
    <?php endif; ?>

    <?php if(!empty($pelanggaran_kategori) || !empty($reward_kategori)): ?>
    // Doughnut Chart: Komposisi Pelanggaran Kategori
    const compCtx = document.getElementById('compositionChart').getContext('2d');
    
    <?php
        $comp_labels = [];
        $comp_data = [];
        $comp_colors = [];
        
        // Define colors based on category
        $colorMap = [
            'Sangat Berat' => '#b91c1c', // dark red
            'Berat' => '#ef4444', // red
            'Sedang' => '#f59e0b', // amber
            'Ringan' => '#fcd34d', // yellow
            'Reward' => '#10b981'  // emerald green
        ];
        
        foreach($pelanggaran_kategori as $pk) {
            $comp_labels[] = "Pelanggaran " . $pk['kategori'];
            $comp_data[] = $pk['total_poin'];
            $comp_colors[] = $colorMap[$pk['kategori']] ?? '#6b7280';
        }
        foreach($reward_kategori as $rk) {
            $comp_labels[] = "Reward";
            $comp_data[] = $rk['total_poin'];
            $comp_colors[] = $colorMap['Reward'];
        }
    ?>
    
    new Chart(compCtx, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($comp_labels) ?>,
            datasets: [{
                data: <?= json_encode($comp_data) ?>,
                backgroundColor: <?= json_encode($comp_colors) ?>,
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });
    <?php endif; ?>
});
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
