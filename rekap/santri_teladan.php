<?php
// 1. Panggil 'Otak' aplikasi dulu
require_once __DIR__ . '/../bootstrap/init.php';

// 2. Jalankan 'SATPAM' buat ngejaga halaman
guard('rekap_santri_teladan');

$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if (!$is_ajax) {
    // 3. Kalau lolos, baru panggil Tampilan
    require_once __DIR__ . '/../layouts/header.php'; 
}

// 🔹 Ambil periode aktif
$q_periode = mysqli_query($conn, "SELECT nilai FROM pengaturan WHERE nama = 'periode_aktif' LIMIT 1");
$row_periode = mysqli_fetch_assoc($q_periode);
$periode_aktif = $row_periode['nilai'] ?? null;

if (!$periode_aktif) {
    die("<div class='container' style='padding-top:20px;'><div class='alert alert-danger'>⚠ Periode aktif belum diset. Silakan atur dulu di halaman pengaturan.</div></div>");
}

// 🔹 Ambil data unik untuk filter dropdown
$kamars_query = mysqli_query($conn, "SELECT DISTINCT kamar FROM santri ORDER BY CAST(kamar AS UNSIGNED) ASC");
$kelas_query = mysqli_query($conn, "SELECT DISTINCT kelas FROM santri ORDER BY kelas ASC");

// 🔹 Ambil filter dari URL (jika ada)
$start_date = $_GET['start_date'] ?? $periode_aktif;
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$filter_kamar = $_GET['kamar'] ?? ''; 
$filter_kelas = $_GET['kelas'] ?? ''; 
$hide_violators = isset($_GET['hide_violators']) ? (int)$_GET['hide_violators'] : 1; // Default: Sembunyikan Pelanggar

// 🔹 QUERY UTAMA YANG DIOPTIMISASI
// Jika hide_violators == 1, kita memotong perhitungan pelanggaran dengan langsung mengecualikan santri (NOT IN).
// Ini menghemat pemrosesan rapot & reward subquery hingga 80%.

$sql = "
    SELECT 
        s.id, s.nama, s.kelas, s.kamar,
        " . ($hide_violators ? "0" : "COALESCE(pel.total_pelanggaran, 0)") . " AS total_pelanggaran,
        COALESCE(rwd.total_reward, 0) AS total_reward,
        COALESCE(rpt.avg_rapot, 0) AS avg_rapot
    FROM santri s
";

if (!$hide_violators) {
    $sql .= "
    LEFT JOIN (
        SELECT p.santri_id, SUM(jp.poin) AS total_pelanggaran
        FROM pelanggaran p
        JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id
        WHERE p.tanggal BETWEEN ? AND ?
        GROUP BY p.santri_id
    ) pel ON s.id = pel.santri_id
    ";
}

$sql .= "
    LEFT JOIN (
        SELECT dr.santri_id, SUM(jr.poin_reward) AS total_reward
        FROM daftar_reward dr
        JOIN jenis_reward jr ON dr.jenis_reward_id = jr.id
        WHERE dr.tanggal BETWEEN ? AND ?
        GROUP BY dr.santri_id
    ) rwd ON s.id = rwd.santri_id
    LEFT JOIN (
        SELECT santri_id, 
               ((AVG(puasa_sunnah) + AVG(sholat_duha) + AVG(sholat_malam) + AVG(sedekah) + AVG(sunnah_tidur) + AVG(ibadah_lainnya) + 
                 AVG(lisan) + AVG(sikap) + AVG(kesopanan) + AVG(muamalah) + 
                 AVG(tidur) + AVG(keterlambatan) + AVG(seragam) + AVG(makan) + AVG(arahan) + AVG(bahasa_arab) + 
                 AVG(mandi) + AVG(penampilan) + AVG(piket) + AVG(kerapihan_barang)) / 20) AS avg_rapot
        FROM rapot_kepengasuhan
        WHERE STR_TO_DATE(CONCAT(tahun, '-', FIELD(bulan, 'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'), '-01'), '%Y-%c-%d') 
              BETWEEN STR_TO_DATE(CONCAT(DATE_FORMAT(?, '%Y-%m'), '-01'), '%Y-%m-%d') 
              AND LAST_DAY(?)
        GROUP BY santri_id
    ) rpt ON s.id = rpt.santri_id
    WHERE 1=1
";

// Prepare Parameter Secarea Dinamis
$start_dt_time = $start_date . ' 00:00:00';
$end_dt_time   = $end_date . ' 23:59:59';
$params = [];
$types = "";

if (!$hide_violators) {
    $params[] = $start_dt_time; $params[] = $end_dt_time;
    $types .= "ss";
}

// Param untuk Reward
$params[] = $start_dt_time; $params[] = $end_dt_time;
$types .= "ss";

// Param untuk Rapot
$params[] = $start_date; $params[] = $end_date;
$types .= "ss";

// Filter tambahan
if (!empty($filter_kamar)) {
    $sql .= " AND s.kamar = ?";
    $params[] = $filter_kamar;
    $types .= "s";
}

if (!empty($filter_kelas)) {
    $sql .= " AND s.kelas = ?";
    $params[] = $filter_kelas;
    $types .= "s";
}

// 🔸 Optimisasi: Jika hide_violators = 1, keluarkan langsung santri pelanggar dari seleksi awal
if ($hide_violators) {
    $sql .= " AND s.id NOT IN (SELECT santri_id FROM pelanggaran WHERE tanggal BETWEEN ? AND ?)";
    $params[] = $start_dt_time; $params[] = $end_dt_time;
    $types .= "ss";
}

// Prioritas Urutan (Ranking)
$sql .= " ORDER BY total_pelanggaran ASC, total_reward DESC, avg_rapot DESC, CAST(s.kamar AS UNSIGNED) ASC, s.nama ASC";

// Eksekusi Statement
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($is_ajax) {
    ob_start();
} else {
?>

<style>
/* --- Font Keren dari Google --- */
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap');

/* --- Reset & Body Styling --- */
body {
    background-color: #f8f9fa;
    font-family: 'Poppins', sans-serif;
    color: #333;
}

/* --- Container Utama --- */
.container {
    max-width: 1240px;
    margin: 20px auto;
    padding: 0 15px;
    animation: fadeIn 0.4s ease-out;
}

/* --- Header Halaman --- */
.page-header {
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 2px solid #e9ecef;
}

.page-header h2 {
    color: #1e293b;
    font-weight: 800;
    font-size: 28px;
    display: flex;
    align-items: center;
    gap: 12px;
}
.page-header h2::before {
    content: '🏆'; 
    font-size: 28px;
}

.page-header .subtitle {
    color: #64748b;
    font-size: 15px;
    margin-top: -3px;
}

/* === BAGIAN FILTER BARU DENGAN GRID CSS === */
.filter-form {
    background-color: #ffffff;
    border-radius: 14px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.04);
    padding: 20px 24px;
    margin: 0 0 30px 0;
    border: 1px solid #f1f5f9;
}

.filter-controls {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 18px;
    align-items: flex-end;
}

.filter-group {
    display: flex;
    flex-direction: column;
}

.filter-group label {
    font-size: 12px; 
    color: #64748b; 
    font-weight: 700; 
    margin-bottom: 6px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.filter-input {
    padding: 10px 14px;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    font-family: 'Poppins', sans-serif;
    font-size: 14px;
    background-color: #f8fafc;
    width: 100%;
    transition: all 0.2s ease;
    color: #334155;
    cursor: pointer;
}

.filter-input:focus {
    outline: none;
    border-color: #4f46e5;
    background-color: #fff;
    box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
}

.filter-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 15px;
    height: 42px; /* Setara dengan tinggi input */
}

.btn-submit {
    padding: 0 24px;
    height: 100%;
    border-radius: 10px;
    background-color: #4f46e5;
    color: white;
    font-weight: 600;
    border: none;
    transition: background-color 0.2s, transform 0.1s;
    font-size: 14px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-submit:hover {
    background-color: #4338ca;
}

.btn-submit:active {
    transform: scale(0.97);
}

.reset-link {
    font-size: 13px;
    color: #94a3b8;
    text-decoration: none;
    font-weight: 500;
    transition: color 0.2s;
}

.reset-link:hover {
    color: #ef4444;
}

@media(max-width: 991px) {
    .filter-actions {
        grid-column: 1 / -1;
        justify-content: space-between;
        margin-top: 10px;
    }
}

/* --- Grid Kartu Santri --- */
.card-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 22px;
}

.santri-card {
    background: #ffffff;
    border-radius: 16px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04);
    padding: 24px 22px 20px;
    position: relative;
    overflow: hidden;
    transition: transform 0.25s ease, box-shadow 0.25s ease;
    border: 1px solid #f1f5f9;
    opacity: 0;
    transform: translateY(15px);
    animation: cardFadeIn 0.4s ease-out forwards;
}

.santri-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 25px rgba(0, 0, 0, 0.08);
}

.santri-card.has-violation {
    border-top: 4px solid #ef4444;
    padding-top: 20px;
}

/* --- REDESAIN LENCANA PERINGKAT (RANK BADGE) --- */
.card-rank {
    position: absolute;
    top: 18px;
    right: 18px;
    height: 40px;
    min-width: 40px;
    padding: 0 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 16px;
    border-radius: 50px;
    letter-spacing: -0.5px;
}

/* Medali Spesial */
.rank-1 { 
    background: linear-gradient(135deg, #fbbf24, #d97706); 
    color: white; 
    box-shadow: 0 4px 12px rgba(217, 119, 6, 0.3); 
    border: 2px solid #fff;
}
.rank-2 { 
    background: linear-gradient(135deg, #cbd5e1, #64748b); 
    color: white; 
    box-shadow: 0 4px 12px rgba(100, 116, 139, 0.3); 
    border: 2px solid #fff;
}
.rank-3 { 
    background: linear-gradient(135deg, #fca5a5, #b91c1c); 
    color: white; 
    box-shadow: 0 4px 12px rgba(185, 28, 28, 0.3); 
    border: 2px solid #fff;
}
.rank-other { 
    background: #f1f5f9; 
    color: #64748b; 
    border: 2px solid #e2e8f0; 
}

/* Jika ada pelanggaran, override warna rank jadi merah waspada */
.santri-card.has-violation .card-rank {
    background: #fef2f2;
    color: #ef4444;
    border: 2px solid #fecaca;
    box-shadow: none;
}

.card-content h3 {
    margin: 0 0 14px 0;
    font-size: 18px;
    font-weight: 700;
    color: #0f172a;
    padding-right: 48px;
    line-height: 1.3;
}

.card-content h3 a {
    color: inherit;
    text-decoration: none;
    transition: color 0.2s;
}
.card-content h3 a:hover {
    color: #4f46e5;
}

/* Lokasi Badge */
.location-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background-color: #f8fafc;
    color: #475569;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 20px;
    border: 1px solid #f1f5f9;
}

/* Metric Stats Container */
.stats-container {
    display: flex;
    justify-content: space-between;
    background-color: #f8fafc;
    border-radius: 12px;
    padding: 12px;
    border: 1px solid #f1f5f9;
}

.stat-item {
    text-align: center;
    flex: 1;
}

.stat-item:not(:last-child) {
    border-right: 1px solid #e2e8f0;
}

.stat-value {
    font-size: 18px;
    font-weight: 800;
    display: block;
    line-height: 1.2;
    letter-spacing: -0.5px;
}

.stat-label {
    font-size: 10px;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    margin-top: 4px;
    font-weight: 600;
}

/* Colors for specific metrics */
.val-pelanggaran.danger { color: #ef4444; }
.val-pelanggaran.success { color: #10b981; }
.val-reward { color: #10b981; }
.val-rapot { color: #4f46e5; }

/* --- Pesan Kosong --- */
.no-data {
    grid-column: 1 / -1;
    text-align: center;
    padding: 60px 20px;
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04);
    border: 1px dashed #cbd5e1;
}
.no-data .icon {
    font-size: 54px;
    margin-bottom: 15px;
    opacity: 0.8;
}
.no-data p {
    font-size: 17px;
    color: #475569;
    font-weight: 500;
}

/* --- Animasi --- */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-8px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes cardFadeIn {
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>

<div class="container">
    <div class="page-header">
        <h2>Peringkat Santri Teladan</h2>
        <p class="subtitle">Berdasarkan total poin pelanggaran terendah, poin reward tertinggi, dan nilai rapot rata-rata terbaik.</p>
        <div style="margin-top: 15px; padding: 12px 18px; border-radius: 10px; font-size: 13.5px; display: flex; align-items: flex-start; gap: 12px; background-color: #eff6ff; color: #1e3a8a; border: 1px solid #bfdbfe;">
            <i class="fas fa-info-circle" style="font-size: 18px; margin-top: 2px;"></i>
            <div>
                <strong>Informasi Perhitungan:</strong> Angka yang ditampilkan pada kartu adalah <strong>Total Poin Pelanggaran</strong> dan <strong>Total Poin Reward</strong>, bukan jumlah/frekuensi kejadiannya.
            </div>
        </div>
    </div>

    <!-- Form Filter -->
    <form id="filterForm" action="" method="GET" class="filter-form">
        <div class="filter-controls">
            
            <div class="filter-group">
                <label>Mulai</label>
                <input type="date" name="start_date" class="filter-input" value="<?= htmlspecialchars($start_date) ?>" title="Tanggal Mulai">
            </div>
            
            <div class="filter-group">
                <label>Akhir</label>
                <input type="date" name="end_date" class="filter-input" value="<?= htmlspecialchars($end_date) ?>" title="Tanggal Akhir">
            </div>

            <div class="filter-group">
                <label>Filter Santri</label>
                <select name="hide_violators" class="filter-input">
                    <option value="1" <?= ($hide_violators === 1) ? 'selected' : '' ?>>Tanpa Pelanggaran</option>
                    <option value="0" <?= ($hide_violators === 0) ? 'selected' : '' ?>>Semua Santri</option>
                </select>
            </div>

            <div class="filter-group">
                <label>Kamar</label>
                <select name="kamar" class="filter-input">
                    <option value="">Semua Kamar</option>
                    <?php while ($k = mysqli_fetch_assoc($kamars_query)): ?>
                        <option value="<?= htmlspecialchars($k['kamar']) ?>" <?= ($filter_kamar == $k['kamar']) ? 'selected' : '' ?>>
                            Kamar <?= htmlspecialchars($k['kamar']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="filter-group">
                <label>Kelas</label>
                <select name="kelas" class="filter-input">
                    <option value="">Semua Kelas</option>
                    <?php while ($kl = mysqli_fetch_assoc($kelas_query)): ?>
                        <option value="<?= htmlspecialchars($kl['kelas']) ?>" <?= ($filter_kelas == $kl['kelas']) ? 'selected' : '' ?>>
                            Kelas <?= htmlspecialchars($kl['kelas']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="filter-actions">
                <a href="?" class="reset-link" id="resetBtn"><i class="fas fa-undo me-1"></i> Reset</a>
                <button type="submit" class="btn-submit"><i class="fas fa-search"></i> Terapkan</button>
            </div>

        </div>
    </form>

    <!-- Overlay Loader (hidden by default) -->
    <div id="loadingOverlay" style="display:none; text-align:center; padding: 40px;">
        <i class="fas fa-circle-notch fa-spin fa-3x" style="color:#4f46e5;"></i>
        <p class="mt-3 text-muted">Memuat data...</p>
    </div>

    <div id="gridContainer">
<?php
} // End if (!$is_ajax)
?>
        <div class="card-grid">
        <?php
        if (mysqli_num_rows($result) === 0) {
            echo "
            <div class='no-data'>
                <div class='icon'>🎉</div>
                <p>Belum ada data untuk ditampilkan.</p>
            </div>";
        } else {
            $no = 1;
            while ($row = mysqli_fetch_assoc($result)) {
                $animation_delay = min($no * 0.05, 1.5); // Cap delay at 1.5s
                
                $pelanggaran = (int)$row['total_pelanggaran'];
                $reward = (int)$row['total_reward'];
                $rapot = round((float)$row['avg_rapot'], 1);

                $card_class = ($pelanggaran > 0) ? 'santri-card has-violation' : 'santri-card';
                $pel_class = ($pelanggaran > 0) ? 'val-pelanggaran danger' : 'val-pelanggaran success';
                
                // Menentukan warna rank
                if ($pelanggaran > 0) {
                    $rank_class = 'rank-violation'; // Akan di-override CSS jadi merah
                } else {
                    if ($no === 1) $rank_class = 'rank-1';
                    elseif ($no === 2) $rank_class = 'rank-2';
                    elseif ($no === 3) $rank_class = 'rank-3';
                    else $rank_class = 'rank-other';
                }
                
                // Format angka rapot
                $str_rapot = ($rapot > 0) ? number_format($rapot, 1, '.', '') : '-';
                $str_reward = ($reward > 0) ? '+' . $reward : '0';
        ?>
                <div class="<?= $card_class ?>" style="animation-delay: <?= $animation_delay ?>s;">
                    <div class="card-rank <?= $rank_class ?>"><?= $no ?></div>
                    
                    <div class="card-content">
                        <h3><a href="detail_umum.php?id=<?= $row['id'] ?>&start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>"><?= htmlspecialchars($row['nama']) ?></a></h3>
                        
                        <div class="location-badge">
                            <i class="fas fa-map-marker-alt" style="opacity: 0.6;"></i>
                            Kelas <?= htmlspecialchars($row['kelas']) ?> <span style="opacity: 0.4; margin: 0 2px;">•</span> Kmr <?= htmlspecialchars($row['kamar']) ?>
                        </div>

                        <div class="stats-container">
                            <div class="stat-item">
                                <span class="stat-value <?= $pel_class ?>"><?= $pelanggaran ?></span>
                                <span class="stat-label">Poin</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-value val-reward"><?= $str_reward ?></span>
                                <span class="stat-label">Poin Reward</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-value val-rapot">
                                    <i class="fas fa-star" style="font-size: 11px; margin-right: 3px; transform: translateY(-1px);"></i><?= $str_rapot ?>
                                </span>
                                <span class="stat-label">Rapot</span>
                            </div>
                        </div>
                    </div>
                </div>
        <?php
                $no++;
            }
        }
        mysqli_stmt_close($stmt);
        ?>
        </div>
<?php 
if ($is_ajax) {
    $html = ob_get_clean();
    echo $html;
    exit;
} else {
?>
    </div>
</div>



<script>
document.addEventListener('DOMContentLoaded', function() {
    const filterForm = document.getElementById('filterForm');
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
            
            // Re-trigger animations
            const cards = gridContainer.querySelectorAll('.santri-card');
            cards.forEach(card => {
                card.style.animation = 'none';
                card.offsetHeight; /* trigger reflow */
                card.style.animation = null; 
            });
            
            // Update URL without reloading
            window.history.pushState({}, '', url);
        })
        .catch(error => {
            console.error('Error fetching data:', error);
            loadingOverlay.innerHTML = '<div class="alert alert-danger">Gagal memuat data. Silakan refresh halaman.</div>';
        });
    }

    // Tangani event submit form
    filterForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(filterForm);
        const params = new URLSearchParams(formData);
        const url = '?' + params.toString();
        fetchGrid(url);
    });

    // Event saat dropdown atau input tanggal diganti langsung otomatis load data
    const selects = filterForm.querySelectorAll('select, input[type="date"]');
    selects.forEach(select => {
        select.addEventListener('change', function() {
            filterForm.dispatchEvent(new Event('submit'));
        });
    });
});
</script>

<?php 
require_once __DIR__ . '/../layouts/footer.php'; 
} // End else (!$is_ajax) footer inclusion
?>