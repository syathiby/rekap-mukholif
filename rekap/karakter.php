<?php
// 1. Panggil 'Otak' aplikasi dulu
require_once __DIR__ . '/../bootstrap/init.php';

// 2. Jalankan 'SATPAM' buat ngejaga halaman
guard('rekap_detail_santri');

$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// 3. Kalau lolos, baru panggil Tampilan
if (!$is_ajax) {
    require_once __DIR__ . '/../layouts/header.php';
}

// --- LOGIKA PHP ---

// Ambil periode aktif
$periode_aktif = PERIODE_AKTIF;

// Ambil filter dari URL
$filter_kamar = $_GET['kamar'] ?? null;
$filter_kelas = $_GET['kelas'] ?? null;
$start_date = $_GET['start_date'] ?? $periode_aktif;
$end_date = $_GET['end_date'] ?? date("Y-m-d");
$sort_order = $_GET['sort_order'] ?? 'terbaik';

// Ambil daftar Kamar (statis)
$kamars_result = mysqli_query($conn, "SELECT DISTINCT kamar FROM santri WHERE kamar IS NOT NULL AND kamar != '' ORDER BY CAST(REGEXP_REPLACE(kamar, '[^0-9]', '') AS UNSIGNED) ASC, REGEXP_REPLACE(kamar, '[0-9]', '') ASC");

// Ambil daftar Kelas (statis)
$kelas_result = mysqli_query($conn, "SELECT DISTINCT CAST(kelas AS UNSIGNED) AS kelas FROM santri WHERE kelas IS NOT NULL AND kelas != '' ORDER BY kelas ASC");


// --- QUERY UTAMA (GABUNGAN PELANGGARAN & REWARD) ---
$sql = "
SELECT
    s.id,
    s.nama,
    s.kelas,
    s.kamar,
    s.poin_aktif,
    (COALESCE(sub_p.total_poin_pelanggaran, 0) - COALESCE(sub_r.total_poin_reward, 0)) AS poin_bersih_periode,
    COALESCE(sub_p.total_pelanggaran_periode, 0) AS total_pelanggaran_periode,
    COALESCE(sub_p.total_poin_pelanggaran, 0) AS total_poin_pelanggaran,
    COALESCE(sub_r.total_reward_periode, 0) AS total_reward_periode,
    COALESCE(sub_r.total_poin_reward, 0) AS total_poin_reward
FROM
    santri s
LEFT JOIN (
    SELECT
        p.santri_id,
        COUNT(p.id) AS total_pelanggaran_periode,
        SUM(jp.poin) AS total_poin_pelanggaran
    FROM
        pelanggaran p
    JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id
    WHERE DATE(p.tanggal) BETWEEN ? AND ?
    GROUP BY p.santri_id
) AS sub_p ON s.id = sub_p.santri_id
LEFT JOIN (
    SELECT
        dr.santri_id,
        COUNT(dr.id) AS total_reward_periode,
        SUM(jr.poin_reward) AS total_poin_reward
    FROM
        daftar_reward dr
    JOIN jenis_reward jr ON dr.jenis_reward_id = jr.id
    WHERE DATE(dr.tanggal) BETWEEN ? AND ?
    GROUP BY dr.santri_id
) AS sub_r ON s.id = sub_r.santri_id
WHERE
    1 = 1
";

$params = [$start_date, $end_date, $start_date, $end_date];
$types = "ssss";

// Terapkan filter kamar & kelas di query utama
if ($filter_kamar) {
    $sql .= " AND s.kamar = ?";
    $params[] = $filter_kamar;
    $types .= "s";
}
if ($filter_kelas) {
    $sql .= " AND s.kelas = ?";
    $params[] = $filter_kelas;
    $types .= "s";
}

// Filter untuk memastikan hanya santri dengan aktivitas (pelanggaran/reward) di periode ini yang muncul
$sql .= " AND (sub_p.total_poin_pelanggaran > 0 OR sub_r.total_poin_reward > 0)";

// Urutkan berdasarkan filter pilihan pengguna
if ($sort_order === 'terburuk') {
    // Terburuk: Poin bersih tertinggi (paling banyak pelanggaran dikurangi reward)
    $sql .= "
    ORDER BY
        poin_bersih_periode DESC,
        COALESCE(sub_p.total_pelanggaran_periode, 0) DESC,
        s.nama ASC
    ";
} else {
    // Terbaik: Poin bersih terkecil (bisa minus karena reward)
    $sql .= "
    ORDER BY
        poin_bersih_periode ASC,
        COALESCE(sub_p.total_pelanggaran_periode, 0) ASC,
        s.nama ASC
    ";
}
// Batasi 200 data untuk menjaga performa
$sql .= " LIMIT 200";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die("Error preparing statement: " . $conn->error);
}
$stmt->bind_param($types, ...$params);
$stmt->execute();
$query = $stmt->get_result();
?>

<style>
    :root {
        --primary: #4f46e5; --primary-light: #e0e7ff; --primary-dark: #4338ca;
        --secondary: #64748b; --light-bg: #f8fafc; --card-bg: #ffffff;
        --border-color: #e2e8f0; --text-dark: #1e293b; --text-light: #64748b;
        --gold: #f59e0b; --silver: #9ca3af; --bronze: #a16207;
        --danger: #ef4444; --success: #10b981;
    }
    body { background-color: var(--light-bg); font-family: 'Poppins', sans-serif; }
    .card { background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem; box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.05); }
    .page-title { color: var(--text-dark); font-weight: 700; }
    .table th { background-color: var(--light-bg); color: var(--text-light); text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em; }
    .table tbody td { vertical-align: middle; padding-top: 1rem; padding-bottom: 1rem; }
    .rank-icon { font-size: 1.5rem; }
    .rank-1 .rank-icon { color: var(--gold); }
    .rank-2 .rank-icon { color: var(--silver); }
    .rank-3 .rank-icon { color: var(--bronze); }
    .poin-value-danger { font-size: 1.1rem; font-weight: 700; color: var(--danger); }
    .poin-value-success { font-size: 1.1rem; font-weight: 700; color: var(--success); }
    .btn-detail { background-color: var(--primary-light); color: var(--primary-dark); font-weight: 600; text-decoration: none; transition: all 0.2s; }
    .btn-detail:hover { background-color: var(--primary-dark); color: white; }
    .periode-stats { font-size: 0.8rem; font-weight: 500; color: var(--text-light); background-color: #f1f5f9; padding: 0.2rem 0.6rem; border-radius: 9999px; white-space: nowrap; display: inline-block; margin-bottom: 4px; }
    .poin-aktif-info { display: block; font-size: 0.75rem; color: #ef4444; font-weight: 500; margin-top: 2px; }
</style>

<?php if (!$is_ajax): ?>
<div class="container py-4">
    <h1 class="page-title mb-2"><i class="fas fa-list-alt me-3"></i>Analisis Karakter Santri</h1>

    <div style="margin-bottom: 25px; padding: 12px 18px; border-radius: 10px; font-size: 13.5px; display: flex; align-items: flex-start; gap: 12px; background-color: #f8fafc; color: #334155; border: 1px solid #e2e8f0;">
        <i class="fas fa-info-circle" style="font-size: 18px; margin-top: 2px; color: #64748b;"></i>
        <div>
            <strong>Info Halaman:</strong> Halaman ini adalah <strong>Neraca Kedisiplinan</strong> murni (Reward vs Pelanggaran). Berbeda dengan Santri Teladan, halaman ini <strong>TIDAK mempertimbangkan Nilai Rapot</strong>. Peringkat disusun dari Poin Bersih (Reward dikurangi Pelanggaran) berdasarkan filter Urutan Peringkat pilihan Anda.
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title fw-bold mb-3"><i class="fas fa-filter me-2"></i>Filter Data</h5>
            <form method="get" id="filterForm">
                <div class="row g-3">
                    <div class="col-lg-3 col-md-6">
                        <label for="start_date" class="form-label">Dari Tanggal</label>
                        <input type="date" class="form-control" name="start_date" id="start_date" value="<?= htmlspecialchars($start_date) ?>">
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <label for="end_date" class="form-label">Sampai Tanggal</label>
                        <input type="date" class="form-control" name="end_date" id="end_date" value="<?= htmlspecialchars($end_date) ?>">
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <label for="kelas" class="form-label">Kelas</label>
                        <select name="kelas" id="kelas" class="form-select">
                            <option value="">Semua Kelas</option>
                            <?php mysqli_data_seek($kelas_result, 0); while ($k = mysqli_fetch_assoc($kelas_result)) : ?>
                                <option value="<?= htmlspecialchars($k['kelas']) ?>" <?= ($filter_kelas == $k['kelas']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($k['kelas']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="col-lg-3 col-md-6">
                        <label for="kamar" class="form-label">Kamar</label>
                        <select name="kamar" id="kamar" class="form-select">
                            <option value="">Semua Kamar</option>
                            <?php mysqli_data_seek($kamars_result, 0); while ($k = mysqli_fetch_assoc($kamars_result)) : ?>
                                <option value="<?= htmlspecialchars($k['kamar']) ?>" <?= ($filter_kamar == $k['kamar']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($k['kamar']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="col-lg-3 col-md-6">
                        <label for="sort_order" class="form-label">Urutan Peringkat</label>
                        <select name="sort_order" id="sort_order" class="form-select bg-light fw-bold text-primary border-primary">
                            <option value="terbaik" <?= ($sort_order == 'terbaik') ? 'selected' : '' ?>>🏆 Terbaik (Bersih dari Kasus)</option>
                            <option value="terburuk" <?= ($sort_order == 'terburuk') ? 'selected' : '' ?>>🚨 Terburuk (Banyak Pelanggaran)</option>
                        </select>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Overlay Loader (hidden by default) -->
    <div id="loadingOverlay" style="display:none; text-align:center; padding: 40px;">
        <i class="fas fa-circle-notch fa-spin fa-3x" style="color:#4f46e5;"></i>
        <p class="mt-3 text-muted">Memuat data...</p>
    </div>

    <div id="gridContainer">
<?php endif; // End if (!$is_ajax) for top parts ?>

<?php if ($is_ajax) ob_start(); ?>
    <div class="row g-3">
        <?php if (mysqli_num_rows($query) === 0) : ?>
            <div class="col-12">
                <div class="card border-0 shadow-sm rounded-4 p-5 text-center text-muted">
                    <i class="fas fa-check-circle fa-3x mb-3"></i>
                    <p class="mb-0">Tidak ada data ditemukan dengan filter ini.</p>
                </div>
            </div>
        <?php else : ?>
            <?php $no = 1; while ($row = mysqli_fetch_assoc($query)) : ?>
                <div class="col-12">
                    <div class="card border-0 shadow-sm rounded-4" style="transition: transform 0.2s;">
                        <div class="card-body p-3 d-flex flex-column flex-md-row align-items-center justify-content-between gap-3">
                            <div class="d-flex align-items-center gap-3 w-100">
                                <div class="rounded-circle bg-light d-flex align-items-center justify-content-center text-secondary fw-bold" style="width: 45px; height: 45px; flex-shrink: 0;">
                                    <?= $no ?>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-1 fw-bold text-dark text-truncate" style="max-width: 200px;"><?= htmlspecialchars($row['nama']) ?></h6>
                                    <div class="text-muted small">
                                        Kls: <?= htmlspecialchars($row['kelas']) ?> &bull; Kmr: <?= htmlspecialchars($row['kamar']) ?>
                                    </div>
                                    <?php 
                                    $poin_bersih_val = (int)$row['poin_bersih_periode'];
                                    $display_poin = $poin_bersih_val < 0 ? 0 : $poin_bersih_val;
                                    $pb_class = $display_poin > 0 ? 'text-danger' : 'text-success';
                                    ?>
                                    <div class="mt-1 <?= $pb_class ?>" style="font-size: 0.75rem; font-weight: 600;">Poin Bersih: <?= $display_poin ?></div>
                                </div>
                            </div>
                            
                            <div class="d-flex align-items-center justify-content-around w-100 bg-light rounded-4 p-2 gap-2" style="max-width: 450px;">
                                <div class="text-center px-2 flex-fill">
                                    <div class="text-muted fw-semibold" style="font-size: 0.7rem; letter-spacing: 0.5px;">PELANGGARAN</div>
                                    <div class="poin-value-danger lh-1 mt-1"><?= $row['total_poin_pelanggaran'] ?> <span class="fs-6 fw-normal">Poin</span></div>
                                    <div class="text-muted mt-1" style="font-size: 0.7rem;"><?= $row['total_pelanggaran_periode'] ?> Kali</div>
                                </div>
                                
                                <div class="text-center border-start border-end px-2 flex-fill border-2 border-white">
                                    <div class="text-muted fw-semibold" style="font-size: 0.7rem; letter-spacing: 0.5px;">REWARD</div>
                                    <div class="poin-value-success lh-1 mt-1">+<?= $row['total_poin_reward'] ?> <span class="fs-6 fw-normal">Poin</span></div>
                                    <div class="text-muted mt-1" style="font-size: 0.7rem;"><?= $row['total_reward_periode'] ?> Kali</div>
                                </div>
                                
                                <div class="text-center px-2 flex-fill">
                                    <?php
                                    $detail_link = "detail_karakter.php?id={$row['id']}&start_date=$start_date&end_date=$end_date"
                                        . "&kamar=" . urlencode($filter_kamar ?? '')
                                        . "&kelas=" . urlencode($filter_kelas ?? '');
                                    ?>
                                    <a href="<?= $detail_link ?>" class="btn btn-sm btn-detail rounded-pill px-3 py-2 w-100 fw-bold shadow-sm d-flex align-items-center justify-content-center">
                                        <i class="fas fa-chart-pie me-1"></i> Detail
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php $no++; endwhile; ?>
        <?php endif; ?>
    </div>
<?php 
if ($is_ajax) {
    echo ob_get_clean();
    exit;
} 
?>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const filterForm = document.getElementById('filterForm');
        const filterInputs = filterForm.querySelectorAll('select, input[type="date"]');
        const gridContainer = document.getElementById('gridContainer');
        const loadingOverlay = document.getElementById('loadingOverlay');

        function fetchGrid(url) {
            gridContainer.style.display = 'none';
            loadingOverlay.style.display = 'block';

            fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(response => response.text())
            .then(html => {
                gridContainer.innerHTML = html;
                loadingOverlay.style.display = 'none';
                gridContainer.style.display = 'block';
                window.history.pushState({}, '', url);
            })
            .catch(error => {
                console.error('Error fetching data:', error);
                loadingOverlay.innerHTML = '<div class="alert alert-danger">Gagal memuat data.</div>';
            });
        }

        filterInputs.forEach(function(input) {
            input.addEventListener('change', function() {
                const formData = new FormData(filterForm);
                const params = new URLSearchParams(formData);
                fetchGrid('?' + params.toString());
            });
        });

        filterForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(filterForm);
            const params = new URLSearchParams(formData);
            fetchGrid('?' + params.toString());
        });
    });
</script>

<?php 
if (!$is_ajax) {
    require_once __DIR__ . '/../layouts/footer.php'; 
}
?>
