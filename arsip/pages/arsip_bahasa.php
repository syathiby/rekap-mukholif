<?php
// 1. Panggil Inisialisasi Aplikasi
require_once __DIR__ . '/../../bootstrap/init.php';

// 2. Proteksi Halaman
guard('arsip_view');

// Deteksi Request AJAX
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if (!$is_ajax) {
    require_once __DIR__ . '/../../layouts/header.php';
}

$arsip_id = (int)($_GET['id'] ?? 0);
if ($arsip_id <= 0) {
    die("<div class='container mt-5'><div class='alert alert-danger'>ID Arsip tidak valid.</div></div>");
}

// Ambil Meta Arsip
$stmt_arsip = $conn->prepare("SELECT * FROM arsip WHERE id = ?");
$stmt_arsip->bind_param('i', $arsip_id);
$stmt_arsip->execute();
$arsip = $stmt_arsip->get_result()->fetch_assoc();
if (!$arsip) {
    die("<div class='container mt-5'><div class='alert alert-danger'>Arsip tidak ditemukan.</div></div>");
}

// Data Filter: Kamar & Kelas dari Arsip
$kamars_result = $conn->query("SELECT DISTINCT santri_kamar, CAST(santri_kamar AS UNSIGNED) AS k_num FROM arsip_data_log_bahasa WHERE arsip_id = $arsip_id AND santri_kamar IS NOT NULL AND santri_kamar != '' ORDER BY k_num ASC");
$kelas_list = $conn->query("SELECT DISTINCT CAST(santri_kelas AS UNSIGNED) AS kelas FROM arsip_data_log_bahasa WHERE arsip_id = $arsip_id AND santri_kelas IS NOT NULL AND santri_kelas != '' ORDER BY kelas ASC");

// Data Filter Level
$levels_result = $conn->query("SELECT DISTINCT jenis_pelanggaran_id, nama_pelanggaran, poin_lama FROM arsip_data_log_bahasa WHERE arsip_id = $arsip_id ORDER BY poin_lama ASC");

// Ambil filter dari URL
$filter_kamar = isset($_GET['kamar']) ? trim((string)$_GET['kamar']) : '';
$filter_kelas = isset($_GET['kelas']) ? trim((string)$_GET['kelas']) : '';
$filter_level = isset($_GET['level']) ? trim((string)$_GET['level']) : '';

// Query Data Log Bahasa pada Arsip Ini
$filter_sql = "";
$params = [$arsip_id];
$types = "i";

if (!empty($filter_kamar)) {
    $filter_sql .= " AND santri_kamar = ?";
    $params[] = $filter_kamar;
    $types .= "s";
}
if (!empty($filter_kelas)) {
    $filter_sql .= " AND santri_kelas = ?";
    $params[] = $filter_kelas;
    $types .= "s";
}
if (!empty($filter_level)) {
    $filter_sql .= " AND jenis_pelanggaran_id = ?";
    $params[] = (int)$filter_level;
    $types .= "i";
}

$sql_data = "
    SELECT 
        santri_id,
        santri_nama,
        santri_kelas,
        santri_kamar,
        jenis_pelanggaran_id,
        nama_pelanggaran,
        poin_lama,
        tanggal_melanggar,
        diganti_pada,
        diganti_oleh_nama
    FROM arsip_data_log_bahasa
    WHERE arsip_id = ? {$filter_sql}
    ORDER BY tanggal_melanggar ASC
";

$stmt_data = $conn->prepare($sql_data);
$stmt_data->bind_param($types, ...$params);
$stmt_data->execute();
$res_data = $stmt_data->get_result();

$santri_summary = [];
$santri_logs = [];

while ($row = $res_data->fetch_assoc()) {
    $sid = (int)$row['santri_id'];
    $santri_logs[$sid][] = $row;
    
    // Status log terakhir menjadi level puncak/terakhir di arsip ini
    $santri_summary[$sid] = [
        'id' => $sid,
        'nama' => $row['santri_nama'],
        'kelas' => $row['santri_kelas'],
        'kamar' => $row['santri_kamar'],
        'total_poin' => (int)$row['poin_lama'],
        'level_terakhir' => $row['nama_pelanggaran'],
        'level_id' => (int)$row['jenis_pelanggaran_id'],
        'tanggal_terakhir' => $row['tanggal_melanggar'],
        'total_insiden' => count($santri_logs[$sid])
    ];
}
$stmt_data->close();

$peringkat_list = array_values($santri_summary);

// Urutkan berdasarkan poin tertinggi, lalu nama
usort($peringkat_list, function($a, $b) {
    if ($b['total_poin'] == $a['total_poin']) {
        return strcmp($a['nama'], $b['nama']);
    }
    return $b['total_poin'] - $a['total_poin'];
});

// Data untuk Grafik
$top_5_santri = array_slice($peringkat_list, 0, 5);
$json_top_santri = json_encode([
    'labels' => array_column($top_5_santri, 'nama'),
    'data' => array_column($top_5_santri, 'total_poin')
]);

$kelas_stats = [];
foreach ($peringkat_list as $p) {
    $kls = $p['kelas'];
    if (!isset($kelas_stats[$kls])) $kelas_stats[$kls] = 0;
    $kelas_stats[$kls]++;
}
arsort($kelas_stats);
$json_kelas_chart = json_encode([
    'labels' => array_keys($kelas_stats),
    'data' => array_values($kelas_stats)
]);

// Render HTML Desktop Table Body
ob_start();
?>
<?php if (empty($peringkat_list)): ?>
    <tr><td colspan="6" class="text-center p-5 text-muted"><i class="fas fa-check-circle fa-3x mb-3 text-success"></i><br>Tidak ada riwayat pelanggaran bahasa sesuai filter pada arsip ini.</td></tr>
<?php else: ?>
    <?php foreach ($peringkat_list as $index => $row): 
        $no = $index + 1;
        $level_singkat = str_ireplace(['(Bahasa)', '(bahasa)'], '', $row['level_terakhir']);
        $level_singkat = trim($level_singkat);
        $logs_json = htmlspecialchars(json_encode($santri_logs[$row['id']] ?? []), ENT_QUOTES, 'UTF-8');
    ?>
    <tr class="rank-<?= $no ?>">
        <td class="text-center">
            <?php if ($no === 1): ?>
                <i class="fas fa-trophy rank-icon" style="color: #f59e0b;"></i>
            <?php elseif ($no === 2): ?>
                <i class="fas fa-trophy rank-icon" style="color: #9ca3af;"></i>
            <?php elseif ($no === 3): ?>
                <i class="fas fa-trophy rank-icon" style="color: #a16207;"></i>
            <?php else: ?>
                <span class="fw-bold fs-6 text-secondary"><?= $no ?></span>
            <?php endif; ?>
        </td>
        <td>
            <div class="fw-bold text-dark"><?= htmlspecialchars($row['nama']) ?></div>
            <small class="text-muted">Kls: <?= htmlspecialchars($row['kelas']) ?> | Kmr: <?= htmlspecialchars($row['kamar']) ?></small>
        </td>
        <td class="text-center">
            <span class="fw-bold fs-5 text-danger"><?= $row['total_poin'] ?></span>
        </td>
        <td class="text-center">
            <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-3 py-1.5 font-monospace">
                <?= htmlspecialchars($level_singkat) ?>
            </span>
        </td>
        <td class="text-center">
            <span class="badge bg-light text-secondary border rounded-pill px-2.5 py-1">
                <?= $row['total_insiden'] ?> catatan
            </span>
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3" 
                    data-nama="<?= htmlspecialchars($row['nama'], ENT_QUOTES, 'UTF-8') ?>"
                    data-logs="<?= $logs_json ?>"
                    onclick="openLogFromBtn(this)">
                <i class="fas fa-history me-1"></i> Riwayat
            </button>
        </td>
    </tr>
    <?php endforeach; ?>
<?php endif; ?>
<?php
$table_tbody_html = ob_get_clean();

// Render HTML Mobile Cards
ob_start();
?>
<?php if (empty($peringkat_list)): ?>
    <div class="text-center p-5 text-muted bg-white rounded-4 border">
        <i class="fas fa-check-circle fa-3x mb-3 text-success"></i>
        <p class="mb-0 fw-medium">Tidak ada riwayat pelanggaran bahasa sesuai filter pada arsip ini.</p>
    </div>
<?php else: ?>
    <?php foreach ($peringkat_list as $index => $row): 
        $no = $index + 1;
        $level_singkat = str_ireplace(['(Bahasa)', '(bahasa)'], '', $row['level_terakhir']);
        $level_singkat = trim($level_singkat);
        $logs_json = htmlspecialchars(json_encode($santri_logs[$row['id']] ?? []), ENT_QUOTES, 'UTF-8');
    ?>
    <div class="card border-0 shadow-sm rounded-4 mb-3 p-3 bg-white">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <div class="d-flex align-items-center gap-2">
                <span class="badge-rank-mobile rank-badge-<?= min($no, 4) ?>">
                    #<?= $no ?>
                </span>
                <div>
                    <h6 class="fw-bold text-dark mb-0"><?= htmlspecialchars($row['nama']) ?></h6>
                    <small class="text-muted">Kls <?= htmlspecialchars($row['kelas']) ?> &bull; Kmr <?= htmlspecialchars($row['kamar']) ?></small>
                </div>
            </div>
            <div class="text-end">
                <span class="fw-bold fs-5 text-danger"><?= $row['total_poin'] ?></span>
                <small class="d-block text-muted" style="font-size: 0.65rem; margin-top: -3px;">POIN</small>
            </div>
        </div>
        <div class="d-flex align-items-center justify-content-between pt-2 border-top gap-2">
            <div class="d-flex align-items-center gap-2 flex-wrap" style="gap: 8px;">
                <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2.5 py-1 font-monospace" style="font-size: 0.72rem;">
                    <?= htmlspecialchars($level_singkat) ?>
                </span>
                <span class="badge bg-light text-secondary border rounded-pill px-2.5 py-1" style="font-size: 0.72rem;">
                    <?= $row['total_insiden'] ?> insiden
                </span>
            </div>
            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1" style="font-size: 0.78rem;"
                    data-nama="<?= htmlspecialchars($row['nama'], ENT_QUOTES, 'UTF-8') ?>"
                    data-logs="<?= $logs_json ?>"
                    onclick="openLogFromBtn(this)">
                <i class="fas fa-history me-1"></i> Detail
            </button>
        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>
<?php
$mobile_cards_html = ob_get_clean();

// AJAX Response
if ($is_ajax) {
    header('Content-Type: application/json');
    echo json_encode([
        'tbody' => $table_tbody_html,
        'mobile_cards' => $mobile_cards_html,
        'top_santri' => [
            'labels' => array_column($top_5_santri, 'nama'),
            'data' => array_column($top_5_santri, 'total_poin')
        ],
        'kelas_chart' => [
            'labels' => array_keys($kelas_stats),
            'data' => array_values($kelas_stats)
        ]
    ]);
    exit;
}
?>

<style>
    :root {
        --primary: #0891b2;
        --primary-light: #ecfeff;
        --bg: #f8fafc;
        --card-bg: #ffffff;
        --border: #e2e8f0;
        --text-dark: #0f172a;
        --text-muted: #64748b;
    }
    body { background: var(--bg); font-family: 'Poppins', sans-serif; }
    
    .page-container { 
        max-width: 1200px; 
        margin: 0 auto; 
        padding: 1.5rem 1rem; 
    }
    
    /* Header & Back Button Styling */
    .page-header-arsip {
        background: var(--card-bg);
        border: 1px solid var(--border);
        border-radius: 1.25rem;
        padding: 1.25rem 1.5rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.03);
    }
    
    .btn-kembali-arsip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 18px;
        background-color: #f8fafc;
        color: #475569;
        border: 1px solid #e2e8f0;
        text-decoration: none;
        border-radius: 9999px;
        font-weight: 600;
        font-size: 13px;
        transition: all 0.2s ease;
        white-space: nowrap;
    }
    .btn-kembali-arsip:hover {
        background-color: #f1f5f9;
        border-color: #cbd5e1;
        color: #0f172a;
        text-decoration: none;
    }

    .rank-icon { font-size: 1.25rem; }

    /* Mobile Rank Badge */
    .badge-rank-mobile {
        width: 32px;
        height: 32px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.8rem;
    }
    .rank-badge-1 { background: #fef3c7; color: #d97706; border: 1px solid #fde68a; }
    .rank-badge-2 { background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0; }
    .rank-badge-3 { background: #ffedd5; color: #c2410c; border: 1px solid #fed7aa; }
    .rank-badge-4 { background: #f8fafc; color: #94a3b8; border: 1px solid #e2e8f0; }

    @media (max-width: 767.98px) {
        .page-container { padding: 1rem 0.75rem; }
        .page-header-arsip { padding: 1rem 1.15rem; }
        .page-title-text { font-size: 1.25rem; }
        .chart-card-body { padding: 1rem !important; }
        .chart-wrapper { height: 230px !important; }
    }
</style>

<div class="page-container">
    
    <!-- Top Header Card -->
    <div class="page-header-arsip mb-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
            <div class="flex-grow-1">
                <h2 class="fw-bold text-dark mb-1 page-title-text"><i class="fas fa-language me-2" style="color: var(--primary);"></i>Arsip Perkembangan Bahasa</h2>
                <div class="text-muted small mt-1">
                    Arsip: <strong class="text-dark"><?= htmlspecialchars($arsip['judul']) ?></strong> 
                    <span class="ms-1">&bull; Periode: <?= date('d M Y', strtotime($arsip['tanggal_mulai'])) ?> &ndash; <?= date('d M Y', strtotime($arsip['tanggal_selesai'])) ?></span>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-shrink-0">
                <span class="badge bg-cyan-subtle text-cyan border border-cyan-subtle px-3 py-2 rounded-pill font-monospace" style="background:#ecfeff; color:#0e7490; font-size: 0.75rem;">
                    <i class="fas fa-archive me-1"></i> Data Arsip
                </span>
                <a href="../view.php?id=<?= $arsip_id ?>" class="btn-kembali-arsip">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
            </div>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-3 p-md-4">
            <h6 class="fw-bold text-dark mb-3"><i class="fas fa-filter me-2 text-muted"></i>Filter Riwayat Bahasa Arsip</h6>
            <form id="filterForm" method="GET" class="row g-2 g-md-3">
                <input type="hidden" name="id" value="<?= $arsip_id ?>">
                <div class="col-12 col-md-4">
                    <label class="form-label text-muted small fw-bold mb-1">KAMAR</label>
                    <select name="kamar" class="form-select rounded-3">
                        <option value="">Semua Kamar</option>
                        <?php mysqli_data_seek($kamars_result, 0); while($k = $kamars_result->fetch_assoc()): ?>
                            <option value="<?= htmlspecialchars($k['santri_kamar']) ?>" <?= ($filter_kamar === $k['santri_kamar']) ? 'selected' : '' ?>>
                                Kamar <?= htmlspecialchars($k['santri_kamar']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label text-muted small fw-bold mb-1">KELAS</label>
                    <select name="kelas" class="form-select rounded-3">
                        <option value="">Semua Kelas</option>
                        <?php mysqli_data_seek($kelas_list, 0); while($kls = $kelas_list->fetch_assoc()): ?>
                            <option value="<?= htmlspecialchars($kls['kelas']) ?>" <?= ($filter_kelas == $kls['kelas']) ? 'selected' : '' ?>>
                                Kelas <?= htmlspecialchars($kls['kelas']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label text-muted small fw-bold mb-1">LEVEL BAHASA</label>
                    <select name="level" class="form-select rounded-3">
                        <option value="">Semua Level</option>
                        <?php mysqli_data_seek($levels_result, 0); while($lvl = $levels_result->fetch_assoc()): ?>
                            <option value="<?= htmlspecialchars($lvl['jenis_pelanggaran_id']) ?>" <?= ($filter_level == $lvl['jenis_pelanggaran_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars(trim(str_ireplace(['(Bahasa)', '(bahasa)'], '', $lvl['nama_pelanggaran']))) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row g-3 g-md-4 mb-4">
        <div class="col-12 col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-3 p-md-4 chart-card-body">
                    <h6 class="fw-bold text-dark mb-3">Top 5 Santri (Poin Puncak saat Arsip)</h6>
                    <div class="chart-wrapper" style="height: 260px;"><canvas id="chartTopSantri"></canvas></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-3 p-md-4 chart-card-body">
                    <h6 class="fw-bold text-dark mb-3">Sebaran Pelanggaran per Kelas</h6>
                    <div class="chart-wrapper" style="height: 260px;"><canvas id="chartKelas"></canvas></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Desktop Table View (Hidden on Mobile) -->
    <div class="d-none d-md-block">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center" style="width: 80px;">Peringkat</th>
                            <th>Santri</th>
                            <th class="text-center">Total Poin</th>
                            <th class="text-center">Level Terakhir</th>
                            <th class="text-center">Insiden</th>
                            <th class="text-center" style="width: 120px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="rekapTableBody">
                        <?= $table_tbody_html ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Mobile Card View (Visible only on Mobile) -->
    <div class="d-block d-md-none" id="mobileCardsContainer">
        <?= $mobile_cards_html ?>
    </div>

</div>

<!-- Modal Detail Riwayat Log Bahasa Santri -->
<div class="modal fade" id="modalLogBahasa" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header border-bottom-0 pb-0">
                <div>
                    <h5 class="modal-title fw-bold" id="modalSantriNama">Riwayat Bahasa</h5>
                    <p class="text-muted small mb-0">Catatan perkembangan level bahasa pada arsip ini</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3 p-md-4">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Tanggal Insiden</th>
                                <th>Tingkatan Level</th>
                                <th class="text-center">Poin</th>
                                <th>Dicatat Oleh</th>
                            </tr>
                        </thead>
                        <tbody id="modalLogBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let chartTop = null;
let chartKelas = null;

document.addEventListener('DOMContentLoaded', function () {
    const dataTopSantri = <?= $json_top_santri ?>;
    const ctxTopSantri = document.getElementById('chartTopSantri').getContext('2d');
    chartTop = new Chart(ctxTopSantri, { 
        type: 'pie', 
        data: { 
            labels: dataTopSantri.labels || [], 
            datasets: [{ 
                label: 'Total Poin', 
                data: dataTopSantri.data || [], 
                backgroundColor: ['#0891b2', '#f59e0b', '#dc3545', '#10b981', '#64748b'], 
                hoverOffset: 4 
            }] 
        }, 
        options: { 
            responsive: true, 
            maintainAspectRatio: false, 
            plugins: { 
                legend: { 
                    position: 'top',
                    labels: { boxWidth: 12, font: { size: 11 } }
                } 
            } 
        } 
    });

    const dataKelas = <?= $json_kelas_chart ?>;
    const ctxKelas = document.getElementById('chartKelas').getContext('2d');
    chartKelas = new Chart(ctxKelas, { 
        type: 'doughnut', 
        data: { 
            labels: dataKelas.labels || [], 
            datasets: [{ 
                data: dataKelas.data || [], 
                backgroundColor: ['#0d6efd', '#0891b2', '#d63384', '#fd7e14', '#10b981', '#6f42c1'], 
                hoverOffset: 4 
            }] 
        }, 
        options: { 
            responsive: true, 
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { boxWidth: 12, font: { size: 11 } }
                }
            }
        } 
    });

    const filterForm = document.getElementById('filterForm');
    const tbody = document.getElementById('rekapTableBody');
    const mobileCards = document.getElementById('mobileCardsContainer');

    function updateData() {
        const formData = new FormData(filterForm);
        const params = new URLSearchParams(formData);
        const url = '?' + params.toString();

        if (tbody) tbody.style.opacity = '0.5';
        if (mobileCards) mobileCards.style.opacity = '0.5';

        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(response => response.json())
        .then(data => {
            if (tbody) {
                tbody.innerHTML = data.tbody;
                tbody.style.opacity = '1';
            }
            if (mobileCards) {
                mobileCards.innerHTML = data.mobile_cards;
                mobileCards.style.opacity = '1';
            }

            if (chartTop) {
                chartTop.data.labels = data.top_santri.labels;
                chartTop.data.datasets[0].data = data.top_santri.data;
                chartTop.update();
            }
            if (chartKelas) {
                chartKelas.data.labels = data.kelas_chart.labels;
                chartKelas.data.datasets[0].data = data.kelas_chart.data;
                chartKelas.update();
            }
            window.history.pushState(null, '', url);
        })
        .catch(err => {
            console.error('Error fetching data:', err);
            if (tbody) tbody.style.opacity = '1';
            if (mobileCards) mobileCards.style.opacity = '1';
        });
    }

    filterForm.querySelectorAll('select').forEach(sel => sel.addEventListener('change', updateData));
});

function openLogFromBtn(btn) {
    const nama = btn.getAttribute('data-nama') || 'Santri';
    let logs = [];
    try {
        logs = JSON.parse(btn.getAttribute('data-logs') || '[]');
    } catch(e) {
        logs = [];
    }
    showLogModal(nama, logs);
}

function showLogModal(santriNama, logs) {
    document.getElementById('modalSantriNama').textContent = 'Riwayat Bahasa: ' + santriNama;
    const tbody = document.getElementById('modalLogBody');
    tbody.innerHTML = '';

    if (!logs || logs.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3">Tidak ada detail riwayat.</td></tr>';
    } else {
        logs.forEach(item => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><span class="fw-semibold">${item.tanggal_melanggar || '-'}</span></td>
                <td><span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2.5 py-1">${item.nama_pelanggaran || '-'}</span></td>
                <td class="text-center fw-bold text-danger">${item.poin_lama || 0}</td>
                <td><small class="text-muted">${item.diganti_oleh_nama || 'Sistem'}</small></td>
            `;
            tbody.appendChild(tr);
        });
    }

    const modal = new bootstrap.Modal(document.getElementById('modalLogBahasa'));
    modal.show();
}
</script>

<?php 
if (!$is_ajax) {
    require_once __DIR__ . '/../../layouts/footer.php'; 
}
?>
