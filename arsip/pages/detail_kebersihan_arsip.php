<?php
require_once __DIR__ . '/../../bootstrap/init.php';
guard('arsip_view');
require_once __DIR__ . '/../../layouts/header.php';

$arsip_id = (int)($_GET['arsip_id'] ?? 0);
$kamar    = trim($_GET['kamar'] ?? '');

if ($arsip_id <= 0 || $kamar === '') {
    die("<div class='container my-4 alert alert-danger'>Parameter tidak valid.</div>");
}

$stmt_arsip = $conn->prepare("SELECT * FROM arsip WHERE id = ?");
$stmt_arsip->bind_param('i', $arsip_id);
$stmt_arsip->execute();
$arsip = $stmt_arsip->get_result()->fetch_assoc();
$stmt_arsip->close();
if (!$arsip) die("<div class='container my-4 alert alert-danger'>Arsip tidak ditemukan.</div>");

// Semua data pelanggaran kamar ini di arsip ini
$stmt_data = $conn->prepare("
    SELECT * FROM arsip_data_pelanggaran_kebersihan
    WHERE arsip_id = ? AND kamar = ?
    ORDER BY tanggal DESC
");
$stmt_data->bind_param('is', $arsip_id, $kamar);
$stmt_data->execute();
$pelanggaran_list = $stmt_data->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_data->close();

$total_pelanggaran = count($pelanggaran_list);

// Tren per minggu untuk chart
$stmt_tren = $conn->prepare("
    SELECT
        DATE_ADD(DATE(tanggal), INTERVAL -WEEKDAY(DATE(tanggal)) DAY) as awal_pekan,
        DATE_ADD(DATE(tanggal), INTERVAL 6-WEEKDAY(DATE(tanggal)) DAY) as akhir_pekan,
        COUNT(id) as jumlah
    FROM arsip_data_pelanggaran_kebersihan
    WHERE arsip_id = ? AND kamar = ?
    GROUP BY awal_pekan, akhir_pekan
    ORDER BY awal_pekan ASC
");
$stmt_tren->bind_param('is', $arsip_id, $kamar);
$stmt_tren->execute();
$res_tren = $stmt_tren->get_result();
$labels_tren = []; $data_tren = [];
while ($row = $res_tren->fetch_assoc()) {
    $awal = date('d', strtotime($row['awal_pekan']));
    $akhir = date('d M', strtotime($row['akhir_pekan']));
    if (date('m', strtotime($row['awal_pekan'])) !== date('m', strtotime($row['akhir_pekan']))) {
        $awal = date('d M', strtotime($row['awal_pekan']));
    }
    $labels_tren[] = $awal . ' - ' . $akhir;
    $data_tren[] = (int)$row['jumlah'];
}
$stmt_tren->close();
?>
<style>
    :root { --primary:#4361ee; --secondary:#3f37c9; --bg:#f8fafc; --card:#ffffff; --border:#e2e8f0; --text:#0f172a; --muted:#64748b; }
    .dkb-page { background:var(--bg); padding:1.5rem; animation:fadeIn .4s ease; }

    .page-header { background:var(--card); border:1px solid var(--border); border-left:4px solid var(--primary); border-radius:1.25rem; padding:1.25rem 1.75rem; margin-bottom:1.25rem; display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; gap:1rem; box-shadow:0 1px 3px rgba(0,0,0,.04); }
    .page-header h1 { font-size:1.25rem; font-weight:700; color:var(--text); margin:0; display:flex; align-items:center; gap:.6rem; }
    .page-header h1 i { color:var(--primary); }
    .page-header p { font-size:.85rem; color:var(--muted); margin:.2rem 0 0; }

    .stat-row { display:flex; flex-wrap:wrap; gap:.75rem; margin-bottom:1.25rem; }
    .stat-mini { background:var(--card); border:1px solid var(--border); border-radius:.875rem; padding:.875rem 1.25rem; flex:1; min-width:150px; box-shadow:0 1px 3px rgba(0,0,0,.04); }
    .stat-mini-label { font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:var(--muted); margin-bottom:.3rem; }
    .stat-mini-val { font-size:1.75rem; font-weight:800; line-height:1; }

    .pro-chart-card { background:var(--card); border-radius:1.25rem; box-shadow:0 2px 16px rgba(0,0,0,.06); padding:1.5rem 1.75rem 1.75rem; margin-bottom:1.25rem; border:1px solid var(--border); }
    .chart-title { font-size:1rem; font-weight:700; color:#1e293b; margin-bottom:2px; display:flex; align-items:center; gap:8px; }
    .chart-subtitle { font-size:.8rem; color:#94a3b8; margin-bottom:1.25rem; padding-left:28px; }
    .chart-icon-wrap { width:28px; height:28px; border-radius:8px; display:inline-flex; align-items:center; justify-content:center; font-size:.8rem; flex-shrink:0; }
    .chart-scroll-outer { overflow-x:auto; -webkit-overflow-scrolling:touch; scrollbar-width:thin; scrollbar-color:#cbd5e1 #f8fafc; border-radius:10px; }
    .chart-scroll-inner { position:relative; height:280px; }
    .chart-scroll-inner.tall { height:340px; }
    .empty-chart { display:flex; flex-direction:column; align-items:center; justify-content:center; color:#94a3b8; padding:3rem 1rem; gap:.75rem; height:200px; }
    .empty-chart i { font-size:2rem; opacity:.3; }

    .table-card { background:var(--card); border:1px solid var(--border); border-radius:1.25rem; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,.04); }
    .table-card-header { padding:1rem 1.5rem; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:.5rem; }
    .table-card-header h2 { font-size:1rem; font-weight:700; color:var(--text); margin:0; }

    table { width:100%; border-collapse:collapse; }
    thead th { background:#f8fafc; font-weight:700; font-size:.75rem; text-transform:uppercase; letter-spacing:.5px; color:var(--muted); padding:1rem 1.5rem; border-bottom:2px solid var(--border); white-space:nowrap; }
    tbody td { padding:.9rem 1.5rem; border-bottom:1px solid #f1f5f9; vertical-align:top; font-size:.875rem; }
    tbody tr:last-child td { border-bottom:none; }
    tbody tr:hover { background:#f8fafc; }

    .timeline-item { position:relative; padding-left:22px; }
    .timeline-item::before { content:''; position:absolute; left:6px; top:6px; bottom:-20px; width:2px; background:#e0e7ff; }
    tr:last-child .timeline-item::before { display:none; }
    .timeline-dot { position:absolute; left:0; top:6px; width:12px; height:12px; border-radius:50%; background:var(--primary); border:2px solid white; box-shadow:0 0 0 2px #e0e7ff; }

    .catatan-text { white-space:pre-wrap; font-size:.875rem; color:var(--text); }
    .catatan-kosong { color:var(--muted); font-style:italic; }

    .empty-state { text-align:center; padding:3rem 1rem; color:var(--muted); }
    .empty-state i { font-size:2.5rem; opacity:.25; display:block; margin-bottom:.75rem; }

    .btn-back { display:inline-flex; align-items:center; gap:.5rem; padding:.5rem 1.1rem; background:#f1f5f9; border:1px solid var(--border); border-radius:.75rem; font-weight:600; font-size:.875rem; color:var(--text); text-decoration:none; transition:background .15s; flex-shrink:0; }
    .btn-back:hover { background:#e2e8f0; color:var(--text); text-decoration:none; }

    @keyframes fadeIn { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:none} }
    @media(max-width:576px){ .dkb-page{padding:1rem;} .page-header{padding:1rem;} thead th, tbody td{padding:.75rem 1rem;} }
</style>

<div class="dkb-page">
    <!-- Header -->
    <div class="page-header">
        <div>
            <h1><i class="fas fa-door-open"></i> Detail Kamar <?= htmlspecialchars($kamar) ?></h1>
            <p>Arsip: <strong><?= htmlspecialchars($arsip['judul']) ?></strong>
                &nbsp;·&nbsp; <?= date('d M Y', strtotime($arsip['tanggal_mulai'])) ?> — <?= date('d M Y', strtotime($arsip['tanggal_selesai'])) ?>
            </p>
        </div>
        <a href="arsip_kebersihan.php?id=<?= $arsip_id ?>" class="btn-back"><i class="fas fa-arrow-left"></i> Kembali</a>
    </div>

    <!-- Stats -->
    <div class="stat-row">
        <div class="stat-mini">
            <div class="stat-mini-label" style="color:#b91c1c;">Total Pelanggaran</div>
            <div class="stat-mini-val" style="color:<?= $total_pelanggaran > 0 ? '#dc2626' : '#15803d' ?>;">
                <?= $total_pelanggaran ?>
            </div>
        </div>
        <?php if (!empty($pelanggaran_list)): ?>
        <div class="stat-mini">
            <div class="stat-mini-label">Pertama Dicatat</div>
            <div class="stat-mini-val" style="color:var(--primary);font-size:1.2rem;">
                <?= date('d M Y', strtotime(end($pelanggaran_list)['tanggal'])) ?>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-label">Terakhir Dicatat</div>
            <div class="stat-mini-val" style="color:var(--primary);font-size:1.2rem;">
                <?= date('d M Y', strtotime($pelanggaran_list[0]['tanggal'])) ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Chart Tren -->
    <?php if (!empty($labels_tren)): ?>
    <div class="pro-chart-card">
        <div class="chart-title">
            <span class="chart-icon-wrap" style="background:#e0e7ff"><i class="fas fa-chart-line" style="color:#4f46e5"></i></span>
            Tren Pelanggaran Kebersihan per Pekan
        </div>
        <div class="chart-subtitle">Pergerakan jumlah pelanggaran kebersihan setiap pekan selama periode arsip</div>
        <div class="chart-scroll-outer">
            <div class="chart-scroll-inner tall" style="min-width:<?= max(count($labels_tren) * 80, 480) ?>px">
                <canvas id="trendChart"></canvas>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="pro-chart-card">
        <div class="chart-title">
            <span class="chart-icon-wrap" style="background:#e0e7ff"><i class="fas fa-chart-line" style="color:#4f46e5"></i></span>
            Tren Pelanggaran Kebersihan per Pekan
        </div>
        <div class="empty-chart"><i class="fas fa-chart-line"></i><p>Tidak ada data tren untuk ditampilkan.</p></div>
    </div>
    <?php endif; ?>

    <!-- Riwayat Table -->
    <div class="table-card">
        <div class="table-card-header">
            <h2><i class="fas fa-history me-2 text-primary"></i>Riwayat Pelanggaran</h2>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th style="width:55px">No</th>
                        <th style="min-width:170px">Tanggal & Waktu</th>
                        <th>Catatan Pelanggaran</th>
                        <th style="min-width:150px">Dicatat Oleh</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pelanggaran_list)): ?>
                        <tr>
                            <td colspan="4">
                                <div class="empty-state">
                                    <i class="fas fa-check-circle text-success"></i>
                                    <p class="fw-bold mb-1">Tidak Ada Rincian Pelanggaran</p>
                                    <p class="small text-muted mb-0">Kamar ini tidak memiliki catatan pelanggaran kebersihan di arsip ini.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($pelanggaran_list as $idx => $item): ?>
                        <tr>
                            <td class="text-muted fw-bold"><?= $idx + 1 ?></td>
                            <td>
                                <div class="timeline-item">
                                    <div class="timeline-dot"></div>
                                    <div class="fw-semibold"><?= date('d M Y', strtotime($item['tanggal'])) ?></div>
                                    <small class="text-muted"><?= date('H:i', strtotime($item['tanggal'])) ?> WIB</small>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($item['catatan'])): ?>
                                    <span class="catatan-text"><?= nl2br(htmlspecialchars($item['catatan'])) ?></span>
                                <?php else: ?>
                                    <span class="catatan-kosong">Tidak ada catatan spesifik.</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted"><?= htmlspecialchars($item['dicatat_oleh_nama'] ?? 'N/A') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if (!empty($labels_tren)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var ctx = document.getElementById('trendChart');
    if (!ctx) return;
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($labels_tren) ?>,
            datasets: [{
                label: 'Pelanggaran',
                data: <?= json_encode($data_tren) ?>,
                borderColor: '#4361ee',
                backgroundColor: 'rgba(67,97,238,.1)',
                borderWidth: 2.5,
                pointBackgroundColor: '#4361ee',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 7,
                fill: true,
                tension: 0.3
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(15,23,42,.92)', titleColor: '#f1f5f9', bodyColor: '#cbd5e1',
                    padding: 12, cornerRadius: 10,
                    callbacks: { label: function(c){ return c.parsed.y + ' pelanggaran'; } }
                }
            },
            scales: {
                y: { beginAtZero: true, ticks: { stepSize: 1, precision: 0 }, grid: { color: 'rgba(148,163,184,.12)' }, border: { display: false } },
                x: { grid: { display: false }, border: { display: false } }
            }
        }
    });
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
