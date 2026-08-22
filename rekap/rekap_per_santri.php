<?php
// 1. Panggil 'Otak' aplikasi dulu
require_once __DIR__ . '/../bootstrap/init.php';

// 2. Jalankan 'SATPAM'
guard('rekap_per_santri');

$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if (!$is_ajax) {
    require_once __DIR__ . '/../layouts/header.php';
}

// --- LOGIKA PHP ---
$periode_aktif = PERIODE_AKTIF;

// Ambil tanggal transaksi paling awal di database
$q_earliest = mysqli_query($conn, "SELECT MIN(tanggal) as min_tgl FROM pelanggaran");
$row_earliest = mysqli_fetch_assoc($q_earliest);
$earliest_date = (!empty($row_earliest['min_tgl'])) ? date('Y-m-d', strtotime($row_earliest['min_tgl'])) : '2025-01-01';

// Backward compat: URL lama (pelanggaran_umum, karakter, santri_teladan) → tipe baru
$tipe_raw = $_GET['tipe'] ?? 'daftar_hitam';
$compat_map = ['pelanggaran_umum' => 'daftar_hitam', 'karakter' => 'peringkat', 'santri_teladan' => 'peringkat'];
if (isset($compat_map[$tipe_raw])) $tipe_raw = $compat_map[$tipe_raw];
$tipe = in_array($tipe_raw, ['daftar_hitam', 'peringkat']) ? $tipe_raw : 'daftar_hitam';

$filter_kamar = $_GET['kamar']      ?? null;
$filter_kelas = $_GET['kelas']      ?? null;

// Validasi format tanggal dari input GET
$raw_start = $_GET['start_date'] ?? $periode_aktif;
$raw_end   = $_GET['end_date']   ?? date("Y-m-d");
$start_date = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw_start) && strtotime($raw_start)) ? $raw_start : $periode_aktif;
$end_date   = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw_end)   && strtotime($raw_end))   ? $raw_end   : date("Y-m-d");
// Pastikan start <= end
if ($start_date > $end_date) { $start_date = $end_date; }

// Dropdown kamar & kelas (dipakai semua tipe)
$kamars_result = mysqli_query($conn, "SELECT DISTINCT kamar FROM santri WHERE kamar IS NOT NULL AND kamar != '' ORDER BY CAST(REGEXP_REPLACE(kamar, '[^0-9]', '') AS UNSIGNED) ASC, REGEXP_REPLACE(kamar, '[0-9]', '') ASC");
$kelas_result  = mysqli_query($conn, "SELECT DISTINCT kelas FROM santri WHERE kelas IS NOT NULL AND kelas != '' ORDER BY CAST(REGEXP_REPLACE(kelas, '[^0-9]', '') AS UNSIGNED) ASC, REGEXP_REPLACE(kelas, '[0-9]', '') ASC");

// Dropdown filter khusus daftar hitam (ambil seluruh master opsi valid di sistem)
$bagian_result   = mysqli_query($conn, "SELECT DISTINCT bagian FROM jenis_pelanggaran WHERE bagian IS NOT NULL AND bagian != '' ORDER BY bagian ASC");
$kategori_result = mysqli_query($conn, "SELECT DISTINCT kategori FROM jenis_pelanggaran WHERE kategori IS NOT NULL AND kategori != '' ORDER BY FIELD(kategori, 'Sangat Berat', 'Berat', 'Sedang', 'Ringan')");
$jp_result       = mysqli_query($conn, "SELECT DISTINCT id, nama_pelanggaran FROM jenis_pelanggaran ORDER BY nama_pelanggaran ASC");

// ================================================================
// TIPE 1: DAFTAR HITAM (ex-pelanggaran_umum)
// ================================================================
if ($tipe === 'daftar_hitam') {

    $filter_bagian   = $_GET['bagian']           ?? null;
    $filter_kategori = $_GET['kategori']          ?? null;
    $filter_jp       = $_GET['jenis_pelanggaran'] ?? null;

    $sql = "SELECT s.id, s.nis, s.nama, s.kelas, s.kamar, s.poin_aktif,
                   COALESCE(sub.total_pelanggaran_periode, 0) AS total_pelanggaran_periode,
                   COALESCE(sub.total_poin_periode, 0) AS total_poin_periode,
                   sub.detail_kasus
            FROM santri s
            JOIN (
                  SELECT p.santri_id, 
                         SUM(p.cnt) AS total_pelanggaran_periode, 
                         SUM(p.tot_poin) AS total_poin_periode,
                         GROUP_CONCAT(CONCAT(jp.nama_pelanggaran, ':', p.cnt) SEPARATOR '|') AS detail_kasus
                  FROM (
                      SELECT p_inner.santri_id, p_inner.jenis_pelanggaran_id, COUNT(p_inner.id) as cnt, SUM(jp_inner.poin) as tot_poin
                      FROM pelanggaran p_inner 
                      JOIN jenis_pelanggaran jp_inner ON p_inner.jenis_pelanggaran_id = jp_inner.id
                      WHERE DATE(p_inner.tanggal) BETWEEN ? AND ? ";
    
    $params = [$start_date, $end_date]; $types = "ss";
    if ($filter_jp)       { $sql .= " AND p_inner.jenis_pelanggaran_id = ?"; $params[] = $filter_jp;       $types .= "i"; }
    if ($filter_bagian)   { $sql .= " AND jp_inner.bagian = ?";              $params[] = $filter_bagian;   $types .= "s"; }
    if ($filter_kategori) { $sql .= " AND jp_inner.kategori = ?";            $params[] = $filter_kategori; $types .= "s"; }
    
    $sql .= "         GROUP BY p_inner.santri_id, p_inner.jenis_pelanggaran_id
                  ) p
                  JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id
                  GROUP BY p.santri_id
            ) AS sub ON s.id = sub.santri_id WHERE 1=1";
    if ($filter_kamar) { $sql .= " AND s.kamar = ?"; $params[] = $filter_kamar; $types .= "s"; }
    if ($filter_kelas)  { $sql .= " AND s.kelas = ?"; $params[] = $filter_kelas;  $types .= "s"; }
    $sql .= " AND sub.total_pelanggaran_periode > 0 ORDER BY total_poin_periode DESC, total_pelanggaran_periode DESC, s.nama ASC";

    $stmt = $conn->prepare($sql); $stmt->bind_param($types, ...$params); $stmt->execute();
    $query = $stmt->get_result();
}

// ================================================================
// TIPE 2: PERINGKAT SANTRI (merger: karakter + santri_teladan)
// ================================================================
if ($tipe === 'peringkat') {

    $formula        = $_GET['formula']        ?? 'semua_aspek'; // 'semua_aspek' | 'neraca'
    $hide_violators = 1; // Selalu 'Tanpa Pelanggaran' untuk semua_aspek
    $sort_order     = $_GET['sort_order']     ?? 'terbaik';

    if ($formula === 'neraca') {
        // ── Formula Neraca: reward - pelanggaran (tanpa rapot) ──────────────
        $sql = "SELECT s.id, s.nis, s.nama, s.kelas, s.kamar,
                       (COALESCE(sub_p.total_poin_pelanggaran, 0) - COALESCE(sub_r.total_poin_reward, 0)) AS skor,
                       COALESCE(sub_p.total_pelanggaran_periode, 0) AS total_pelanggaran,
                       COALESCE(sub_p.total_poin_pelanggaran, 0)    AS poin_pelanggaran,
                       COALESCE(sub_r.total_reward_periode, 0)      AS total_reward,
                       COALESCE(sub_r.total_poin_reward, 0)         AS poin_reward,
                       0 AS avg_rapot
                FROM santri s
                LEFT JOIN (SELECT p.santri_id, COUNT(p.id) AS total_pelanggaran_periode, SUM(jp.poin) AS total_poin_pelanggaran
                           FROM pelanggaran p JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id
                           WHERE DATE(p.tanggal) BETWEEN ? AND ? GROUP BY p.santri_id) AS sub_p ON s.id = sub_p.santri_id
                LEFT JOIN (SELECT dr.santri_id, COUNT(dr.id) AS total_reward_periode, SUM(jr.poin_reward) AS total_poin_reward
                           FROM daftar_reward dr JOIN jenis_reward jr ON dr.jenis_reward_id = jr.id
                           WHERE DATE(dr.tanggal) BETWEEN ? AND ? GROUP BY dr.santri_id) AS sub_r ON s.id = sub_r.santri_id
                WHERE 1=1";

        $params = [$start_date, $end_date, $start_date, $end_date]; $types = "ssss";
        if ($filter_kamar) { $sql .= " AND s.kamar = ?"; $params[] = $filter_kamar; $types .= "s"; }
        if ($filter_kelas)  { $sql .= " AND s.kelas = ?"; $params[] = $filter_kelas;  $types .= "s"; }
        $sql .= " AND (sub_p.total_poin_pelanggaran > 0 OR sub_r.total_poin_reward > 0)";
        $sql .= ($sort_order === 'terburuk')
            ? " ORDER BY skor DESC, total_pelanggaran DESC, s.nama ASC LIMIT 200"
            : " ORDER BY skor ASC, total_pelanggaran ASC, s.nama ASC LIMIT 200";

        $stmt_p = $conn->prepare($sql); $stmt_p->bind_param($types, ...$params); $stmt_p->execute();
        $result_p = $stmt_p->get_result();

        $santri_data = [];
        while ($row = mysqli_fetch_assoc($result_p)) { $santri_data[] = $row; }

    } else {
        // ── Formula Semua Aspek: rapot×20 + reward - poin_pelanggaran ────────────────
        $bulan_indo   = ['1'=>'Januari','2'=>'Februari','3'=>'Maret','4'=>'April','5'=>'Mei','6'=>'Juni','7'=>'Juli','8'=>'Agustus','9'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'];
        $current_ts   = strtotime(date('Y-m-01', strtotime($start_date)));
        $end_ts_month = strtotime(date('Y-m-01', strtotime($end_date)));
        $valid_months = [];
        while ($current_ts <= $end_ts_month) {
            $y = date('Y', $current_ts); $m = date('n', $current_ts);
            $valid_months[] = "(tahun = $y AND bulan = '{$bulan_indo[$m]}')";
            $current_ts = strtotime("+1 month", $current_ts);
        }
        $where_rapot   = empty($valid_months) ? "1=0" : implode(" OR ", $valid_months);
        $start_dt_time = $start_date . ' 00:00:00';
        $end_dt_time   = $end_date   . ' 23:59:59';

        $sql  = "SELECT s.id, s.nis, s.nama, s.kelas, s.kamar, ";
        $sql .= ($hide_violators ? "0" : "COALESCE(pel.total_pelanggaran, 0)") . " AS poin_pelanggaran, ";
        $sql .= ($hide_violators ? "0" : "COALESCE(pel.jml_pelanggaran, 0)")   . " AS total_pelanggaran, ";
        $sql .= "COALESCE(rwd.total_reward, 0) AS poin_reward, 0 AS total_reward,
                 COALESCE(rpt.avg_rapot, 0) AS avg_rapot
                 FROM santri s ";

        if (!$hide_violators) {
            $sql .= "LEFT JOIN (SELECT p.santri_id, COUNT(p.id) AS jml_pelanggaran, SUM(jp.poin) AS total_pelanggaran
                                FROM pelanggaran p JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id
                                WHERE p.tanggal BETWEEN ? AND ? GROUP BY p.santri_id) pel ON s.id = pel.santri_id ";
        }
        $sql .= "LEFT JOIN (SELECT dr.santri_id, SUM(jr.poin_reward) AS total_reward
                             FROM daftar_reward dr JOIN jenis_reward jr ON dr.jenis_reward_id = jr.id
                             WHERE dr.tanggal BETWEEN ? AND ? GROUP BY dr.santri_id) rwd ON s.id = rwd.santri_id
                 LEFT JOIN (SELECT santri_id,
                                   ((AVG(puasa_sunnah)+AVG(sholat_duha)+AVG(sholat_malam)+AVG(sedekah)+AVG(sunnah_tidur)+AVG(ibadah_lainnya)+
                                     AVG(lisan)+AVG(sikap)+AVG(kesopanan)+AVG(muamalah)+
                                     AVG(tidur)+AVG(keterlambatan)+AVG(seragam)+AVG(makan)+AVG(arahan)+AVG(bahasa_arab)+
                                     AVG(mandi)+AVG(penampilan)+AVG(piket)+AVG(kerapihan_barang))/20) AS avg_rapot
                             FROM rapot_kepengasuhan WHERE $where_rapot GROUP BY santri_id) rpt ON s.id = rpt.santri_id
                 WHERE 1=1 ";

        $params = []; $types = "";
        if (!$hide_violators) { $params[] = $start_dt_time; $params[] = $end_dt_time; $types .= "ss"; }
        $params[] = $start_dt_time; $params[] = $end_dt_time; $types .= "ss";
        if (!empty($filter_kamar)) { $sql .= " AND s.kamar = ?"; $params[] = $filter_kamar; $types .= "s"; }
        if (!empty($filter_kelas))  { $sql .= " AND s.kelas = ?"; $params[] = $filter_kelas;  $types .= "s"; }
        if ($hide_violators) {
            $sql .= " AND s.id NOT IN (SELECT santri_id FROM pelanggaran WHERE tanggal BETWEEN ? AND ?)";
            $params[] = $start_dt_time; $params[] = $end_dt_time; $types .= "ss";
        }

        $stmt_p = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt_p, $types, ...$params);
        mysqli_stmt_execute($stmt_p);
        $result_p = mysqli_stmt_get_result($stmt_p);

        $santri_data = [];
        while ($row = mysqli_fetch_assoc($result_p)) {
            $p_poin  = (int)$row['poin_pelanggaran'];
            $p_jml   = (int)$row['total_pelanggaran'];
            $r_poin  = (int)$row['poin_reward'];
            $rapot   = (float)$row['avg_rapot'];
            $row['skor'] = ($rapot * 20) + $r_poin - (($p_poin * 2) + ($p_jml * 5));
            $santri_data[] = $row;
        }

        usort($santri_data, function ($a, $b) use ($sort_order) {
            $diff = $b['skor'] - $a['skor'];
            if ($sort_order === 'terburuk') $diff = $a['skor'] - $b['skor'];
            if (abs($diff) < 0.0001) {
                if ($a['kamar'] == $b['kamar']) return strcmp($a['nama'], $b['nama']);
                return (int)$a['kamar'] - (int)$b['kamar'];
            }
            return $diff > 0 ? 1 : -1;
        });
    }
}

// ================================================================
// AJAX: hanya return konten
// ================================================================
if ($is_ajax) { ob_start(); }
?>
<?php if (!$is_ajax): ?>
<style>
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap');
:root {
    --primary:#4f46e5; --primary-light:#e0e7ff; --primary-dark:#4338ca;
    --light-bg:#f8fafc; --card-bg:#fff; --border:#e2e8f0;
    --text-dark:#1e293b; --text-muted:#64748b;
    --gold:#f59e0b; --silver:#9ca3af; --bronze:#a16207;
    --danger:#ef4444; --success:#10b981;
}
body { background:var(--light-bg); font-family:'Poppins',sans-serif; }
.page-title { color:var(--text-dark); font-weight:700; }
.filter-card { background:var(--card-bg); border:1px solid var(--border); border-radius:.75rem; box-shadow:0 1px 3px rgba(0,0,0,.05); }
.filter-group label { font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:.5px; display:block; margin-bottom:5px; }
.search-box { position:relative; }
.search-box input { padding-left:38px; border-radius:10px; border:1px solid var(--border); background:#f8fafc; font-size:14px; width:100%; height:38px; }
.search-box input:focus { outline:none; border-color:var(--primary); background:#fff; box-shadow:0 0 0 3px rgba(79,70,229,.1); }
.search-box i { position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--text-muted); font-size:13px; }
.conditional-filter.hidden { display:none; }
/* Tabel Daftar Hitam & Peringkat */
.table-responsive { border-radius: 0.75rem; -webkit-overflow-scrolling: touch; }
.table th { background:var(--light-bg); color:var(--text-muted); text-transform:uppercase; font-size:.7rem; letter-spacing:.05em; }
.table tbody td { vertical-align:middle; padding:.85rem .75rem; }
.rank-icon { font-size:1.4rem; }
/* Daftar Hitam: rank 1=paling banyak pelanggaran → warna merah */
#tabelHitam tr.rank-1 .rank-icon { color: #dc2626; }
#tabelHitam tr.rank-2 .rank-icon { color: #f87171; }
#tabelHitam tr.rank-3 .rank-icon { color: #fca5a5; }
/* Peringkat Santri: rank 1=terbaik → warna emas */
#tabelPeringkat tr.rank-1 .rank-icon { color:var(--gold); }
#tabelPeringkat tr.rank-2 .rank-icon { color:var(--silver); }
#tabelPeringkat tr.rank-3 .rank-icon { color:var(--bronze); }
.poin-value { font-size:1.2rem; font-weight:700; color:var(--primary-dark); }
.periode-stats { font-size:.78rem; font-weight:500; color:var(--text-muted); background:#f1f5f9; padding:.2rem .6rem; border-radius:9999px; white-space:nowrap; display:inline-block; }
.poin-aktif-info { display:block; font-size:.72rem; font-weight:500; margin-top:2px; }
.btn-detail { background:var(--primary-light); color:var(--primary-dark); font-weight:600; text-decoration:none; transition:all .2s; }
.btn-detail:hover { background:var(--primary-dark); color:#fff; }
.tr-hidden { display:none !important; }
.hover-row { transition: background-color 0.2s; }
.hover-row:hover { background-color: #f8fafc !important; }

/* Preset Scroll Container & Chips Modern Minimalis */
.preset-scroll-container {
    display: flex;
    align-items: center;
    gap: 6px;
    overflow-x: auto;
    flex-wrap: nowrap;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
    -ms-overflow-style: none;
    padding: 2px 0;
}
.preset-scroll-container::-webkit-scrollbar {
    display: none;
}
.preset-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 12px;
    font-size: 0.76rem;
    font-weight: 600;
    color: #475569;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 50px;
    white-space: nowrap !important;
    flex-shrink: 0;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.2s ease;
    line-height: 1.25;
}
.preset-chip:hover {
    background: #f1f5f9;
    color: #1e293b;
    border-color: #cbd5e1;
}
.preset-chip i {
    font-size: 0.82rem;
}

/* State Aktif untuk Tiap Preset */
.preset-chip.active-primary {
    background: #4f46e5 !important;
    color: #ffffff !important;
    border-color: #4f46e5 !important;
    box-shadow: 0 2px 6px rgba(79, 70, 229, 0.2) !important;
}
.preset-chip.active-danger {
    background: #ef4444 !important;
    color: #ffffff !important;
    border-color: #ef4444 !important;
    box-shadow: 0 2px 6px rgba(239, 68, 68, 0.2) !important;
}
.preset-chip.active-warning {
    background: #d97706 !important;
    color: #ffffff !important;
    border-color: #d97706 !important;
    box-shadow: 0 2px 6px rgba(217, 119, 6, 0.2) !important;
}
.preset-chip.active-dark {
    background: #334155 !important;
    color: #ffffff !important;
    border-color: #334155 !important;
    box-shadow: 0 2px 6px rgba(51, 65, 85, 0.2) !important;
}
.preset-chip.active-primary i,
.preset-chip.active-danger i,
.preset-chip.active-warning i,
.preset-chip.active-dark i {
    color: #ffffff !important;
}

.no-data { text-align:center; padding:60px 20px; background:#fff; border-radius:16px; border:1px dashed #cbd5e1; }
.no-data .icon { font-size:50px; margin-bottom:12px; }
#gridWrapper { position: relative; min-height: 300px; }
#gridContainer { transition: opacity 0.3s ease; }
#loadingOverlay { display:none; position:absolute; top:40%; left:50%; transform:translate(-50%,-50%); z-index:20; background:rgba(255,255,255,0.95); padding:24px 40px; border-radius:16px; box-shadow:0 10px 30px rgba(79,70,229,0.15); border:1px solid rgba(79,70,229,0.1); text-align:center; }
</style>

<div class="container py-4">
    <h1 class="page-title mb-1"><i class="fas fa-users me-2 text-primary"></i>Rekap Per Santri</h1>
    <p class="text-muted mb-4" style="font-size:.9rem;">Daftar Hitam Pelanggar &amp; Peringkat Santri dalam satu halaman</p>

    <div class="filter-card mb-4 p-4">
        <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-2.5 mb-3">
            <h5 class="fw-bold mb-0 text-nowrap"><i class="fas fa-sliders-h me-2 text-primary"></i>Filter &amp; Pencarian</h5>
            
            <!-- Tombol Pintas Preset Cepat -->
            <div class="preset-scroll-container w-100 w-md-auto" id="presetButtonsGroup">
                <span class="small fw-semibold text-muted me-1 d-none d-md-inline flex-shrink-0"><i class="fas fa-bolt text-warning me-1"></i>Pintas:</span>
                <button type="button" id="btnPresetAktif" class="preset-chip" onclick="applyQuickPreset('aktif')">
                    <i class="bi bi-calendar-check text-primary"></i>Tahun Ajaran Aktif
                </button>
                <button type="button" id="btnPresetSangatBeratAktif" class="preset-chip" onclick="applyQuickPreset('sangat_berat_aktif')">
                    <i class="bi bi-shield-exclamation text-danger"></i>Sangat Berat
                </button>
                <button type="button" id="btnPresetSangatBeratLalu" class="preset-chip" onclick="applyQuickPreset('sangat_berat_lalu')">
                    <i class="bi bi-clock-history text-warning"></i>Sangat Berat (Periode Lalu)
                </button>
                <button type="button" id="btnPresetAllTime" class="preset-chip" onclick="applyQuickPreset('all_time')">
                    <i class="bi bi-infinity text-secondary"></i>Semua Waktu
                </button>
            </div>
        </div>

        <form method="get" id="filterForm">
            <div class="row g-3 mb-3">

                <!-- Jenis Rekap -->
                <div class="col-lg-2 col-md-4 col-6">
                    <div class="filter-group">
                        <label>Jenis Rekap</label>
                        <select name="tipe" id="tipeSelect" class="form-select fw-semibold text-primary">
                            <option value="daftar_hitam" <?= ($tipe==='daftar_hitam')?'selected':'' ?>>🚫 Daftar Hitam</option>
                            <option value="peringkat"    <?= ($tipe==='peringkat')   ?'selected':'' ?>>🏆 Peringkat Santri</option>
                        </select>
                    </div>
                </div>

                <!-- Dari Tanggal -->
                <div class="col-lg-2 col-md-4 col-6">
                    <div class="filter-group">
                        <label>Dari Tanggal</label>
                        <input type="date" class="form-control" name="start_date" id="start_date" value="<?= htmlspecialchars($start_date) ?>">
                    </div>
                </div>

                <!-- Sampai Tanggal -->
                <div class="col-lg-2 col-md-4 col-6">
                    <div class="filter-group">
                        <label>Sampai Tanggal</label>
                        <input type="date" class="form-control" name="end_date" id="end_date" value="<?= htmlspecialchars($end_date) ?>">
                    </div>
                </div>

                <!-- Kelas -->
                <div class="col-lg-2 col-md-4 col-6">
                    <div class="filter-group">
                        <label>Kelas</label>
                        <select name="kelas" id="kelas" class="form-select">
                            <option value="">Semua Kelas</option>
                            <?php mysqli_data_seek($kelas_result, 0); while ($k = mysqli_fetch_assoc($kelas_result)): ?>
                                <option value="<?= htmlspecialchars($k['kelas']) ?>" <?= ($filter_kelas==$k['kelas'])?'selected':'' ?>><?= htmlspecialchars($k['kelas']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>

                <!-- Kamar -->
                <div class="col-lg-2 col-md-4 col-6">
                    <div class="filter-group">
                        <label>Kamar</label>
                        <select name="kamar" id="kamar" class="form-select">
                            <option value="">Semua Kamar</option>
                            <?php mysqli_data_seek($kamars_result, 0); while ($k = mysqli_fetch_assoc($kamars_result)): ?>
                                <option value="<?= htmlspecialchars($k['kamar']) ?>" <?= ($filter_kamar==$k['kamar'])?'selected':'' ?>><?= htmlspecialchars($k['kamar']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>

                <!-- Search nama (client-side) -->
                <div class="col-lg-2 col-md-4 col-12 order-lg-first">
                    <div class="filter-group">
                        <label>Cari Nama Santri</label>
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" id="searchNama" placeholder="Ketik nama...">
                        </div>
                    </div>
                </div>

                <!-- Filter Khusus: Urutan (peringkat) -->
                <div class="col-lg-2 col-md-4 col-6 conditional-filter f-peringkat <?= ($tipe==='peringkat')?'':'hidden' ?>">
                    <div class="filter-group">
                        <label>Urutan</label>
                        <select name="sort_order" id="sort_order" class="form-select">
                            <option value="terbaik"  <?= (($_GET['sort_order']??'terbaik')==='terbaik') ?'selected':'' ?>>🏆 Terbaik</option>
                            <option value="terburuk" <?= (($_GET['sort_order']??'terbaik')==='terburuk')?'selected':'' ?>>🚨 Terburuk</option>
                        </select>
                    </div>
                </div>

                <!-- Filter Khusus: Formula (peringkat) -->
                <div class="col-lg-3 col-md-6 col-12 conditional-filter f-peringkat <?= ($tipe==='peringkat')?'':'hidden' ?>">
                    <div class="filter-group">
                        <label>Formula Penilaian</label>
                        <select name="formula" id="formula" class="form-select">
                            <option value="semua_aspek" <?= (($_GET['formula']??'semua_aspek')==='semua_aspek')?'selected':'' ?>>📊 Rapot + Reward − Poin Pelanggaran</option>
                            <option value="neraca"      <?= (($_GET['formula']??'semua_aspek')==='neraca')     ?'selected':'' ?>>⚖️ Reward vs Pelanggaran (Neraca)</option>
                        </select>
                    </div>
                </div>



            </div>

            <!-- Filter Khusus Daftar Hitam: Bagian, Kategori, Jenis -->
            <div class="row g-3 conditional-filter f-daftar-hitam <?= ($tipe==='daftar_hitam')?'':'hidden' ?>">
                <div class="col-lg-4 col-md-6">
                    <div class="filter-group">
                        <label>Bagian</label>
                        <select name="bagian" id="bagian" class="form-select">
                            <option value="">Semua Bagian</option>
                            <?php mysqli_data_seek($bagian_result, 0); while ($b = mysqli_fetch_assoc($bagian_result)): ?>
                                <option value="<?= htmlspecialchars($b['bagian']) ?>" <?= (($filter_bagian??'')==$b['bagian'])?'selected':'' ?>><?= htmlspecialchars(format_typing($b['bagian'])) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="filter-group">
                        <label>Kategori</label>
                        <select name="kategori" id="kategori" class="form-select">
                            <option value="">Semua Kategori</option>
                            <?php mysqli_data_seek($kategori_result, 0); while ($kat = mysqli_fetch_assoc($kategori_result)): ?>
                                <option value="<?= htmlspecialchars($kat['kategori']) ?>" <?= (($filter_kategori??'')==$kat['kategori'])?'selected':'' ?>><?= htmlspecialchars(format_typing($kat['kategori'])) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="col-lg-4 col-md-12">
                    <div class="filter-group">
                        <label>Jenis Pelanggaran</label>
                        <select name="jenis_pelanggaran" id="jenis_pelanggaran" class="form-select">
                            <option value="">Semua Jenis</option>
                            <?php mysqli_data_seek($jp_result, 0); while ($jp = mysqli_fetch_assoc($jp_result)): ?>
                                <option value="<?= $jp['id'] ?>" <?= (($filter_jp??'')==$jp['id'])?'selected':'' ?>><?= htmlspecialchars(format_typing($jp['nama_pelanggaran'])) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
            </div>
        </form>
    </div>



    <!-- Wrapper buat Animasi -->
    <div id="gridWrapper">
        <!-- Loading -->
        <div id="loadingOverlay">
            <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status"></div>
            <p class="mt-3 mb-0 fw-bold text-primary" style="letter-spacing: 0.5px;">Memperbarui Data...</p>
        </div>

        <div id="gridContainer">
<?php endif; // end !$is_ajax ?>

<?php require __DIR__ . '/info_banner.php'; ?>

<?php
$filter_qs = "";
if (!empty($filter_kamar))   $filter_qs .= "&kamar="             . urlencode($filter_kamar);
if (!empty($filter_kelas))   $filter_qs .= "&kelas="             . urlencode($filter_kelas);
if (!empty($filter_bagian))  $filter_qs .= "&bagian="            . urlencode($filter_bagian);
if (!empty($filter_kategori)) $filter_qs .= "&kategori="         . urlencode($filter_kategori);
if (!empty($filter_jp))      $filter_qs .= "&jenis_pelanggaran=" . urlencode($filter_jp);

// ================================================================
// RENDER KONTEN
// ================================================================
if ($tipe === 'daftar_hitam'):
?>
<div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
    <div class="table-responsive p-0">
        <table class="table table-hover mb-0 align-middle text-nowrap" id="tabelHitam" style="min-width: 580px;">
            <thead class="table-light">
                <tr>
                    <th class="text-center ps-3" style="width:80px;">Peringkat</th>
                    <th>Santri</th>
                    <?php if ($filter_bagian === 'Pengabdian'): ?>
                        <th class="text-center" style="width:110px;">Total</th>
                        <th class="text-start">Detail Pelanggaran</th>
                    <?php else: ?>
                        <th class="text-center" style="width:130px;">Poin Periode</th>
                        <th class="text-center" style="width:110px;">Kasus</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody id="bodyHitam">
            <?php if (mysqli_num_rows($query) === 0): ?>
                <tr><td colspan="4" class="text-center py-5 text-muted">
                    <i class="fas fa-check-circle fa-3x mb-3 d-block text-success opacity-50"></i>
                    Tidak ada data pelanggaran ditemukan.
                </td></tr>
            <?php else: ?>
                <?php 
                $no = 1;
                while ($row = mysqli_fetch_assoc($query)): 
                ?>
                <tr class="rank-<?= $no ?> hover-row" data-nama="<?= strtolower(htmlspecialchars($row['nama'])) ?>" onclick="window.location.href='detail_per_santri.php?id=<?= $row['id'] ?>&start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?><?= $filter_qs ?>'" style="cursor: pointer;">
                    <td class="text-center ps-3">
                        <?php if ($no <= 3): ?>
                            <i class="fas fa-trophy rank-icon"></i>
                        <?php else: ?>
                            <span class="badge bg-light text-secondary border rounded-pill px-2.5 py-1 fw-bold"><?= $no ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="fw-bold text-dark fs-6 text-decoration-none"><?= htmlspecialchars($row['nama']) ?></span>
                        <small class="text-muted d-block mt-0.5">NIS: <span class="fw-medium"><?= htmlspecialchars($row['nis'] ?? '-') ?></span> &bull; Kls <?= htmlspecialchars($row['kelas']) ?> &bull; Kmr <?= htmlspecialchars($row['kamar']) ?></small>
                    </td>
                    <?php if ($filter_bagian === 'Pengabdian'): ?>
                        <td class="text-center align-middle">
                            <span class="badge rounded-pill bg-danger-subtle text-danger border border-danger-subtle fs-6 px-3 py-1.5"><?= $row['total_pelanggaran_periode'] ?></span>
                        </td>
                        <td class="text-start align-middle">
                            <div class="d-flex flex-column gap-1">
                                <?php 
                                if (!empty($row['detail_kasus'])) {
                                    $kasus_list = explode('|', $row['detail_kasus']);
                                    foreach ($kasus_list as $k) {
                                        $parts = explode(':', $k);
                                        if (count($parts) == 2) {
                                            $nama_p = htmlspecialchars(trim($parts[0]), ENT_QUOTES, 'UTF-8');
                                            $cnt_p  = (int)trim($parts[1]);
                                            echo "<div class='small text-muted'><i class='fas fa-exclamation-circle text-primary me-1'></i><span class='text-dark fw-medium'>{$nama_p}:</span> <strong class='text-danger'>{$cnt_p}</strong></div>";
                                        }
                                    }
                                }
                                ?>
                            </div>
                        </td>
                    <?php else: ?>
                        <td class="text-center">
                            <span class="badge bg-danger text-white rounded-pill px-3 py-1.5 fw-bold fs-6"><?= $row['total_poin_periode'] ?> poin</span>
                        </td>
                        <td class="text-center">
                            <span class="periode-stats"><?= $row['total_pelanggaran_periode'] ?>×</span>
                        </td>
                    <?php endif; ?>
                </tr>
                <?php $no++; endwhile; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php else: // PERINGKAT SANTRI
    $formula_cur = $_GET['formula'] ?? 'semua_aspek';
    $sort_cur    = $_GET['sort_order'] ?? 'terbaik';
?>
<div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4" id="cardGrid">
    <div class="table-responsive p-0">
        <table class="table table-hover mb-0 align-middle text-nowrap" id="tabelPeringkat" style="min-width: 620px;">
            <thead class="table-light">
                <tr>
                    <th class="text-center ps-3" style="width:70px;">Peringkat</th>
                    <th>Santri</th>
                    <?php if ($formula_cur === 'neraca'): ?>
                    <th class="text-center" style="width:110px;">Pelanggaran</th>
                    <th class="text-center" style="width:110px;">Reward</th>
                    <th class="text-center" style="width:130px;">Poin Bersih</th>
                    <?php else: ?>
                    <th class="text-center" style="width:100px;">Langgar</th>
                    <th class="text-center" style="width:100px;">Reward</th>
                    <th class="text-center" style="width:100px;">Rapot</th>
                    <th class="text-center" style="width:130px;">Skor Teladan</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody id="bodyPeringkat">
            <?php
            if (empty($santri_data)) {
                echo "<tr><td colspan='" . ($formula_cur === 'neraca' ? 5 : 6) . "' class='text-center py-5 text-muted'><div style='font-size:3rem;margin-bottom:10px;'>🎉</div><p class='fw-semibold'>Belum ada data untuk ditampilkan.</p></td></tr>";
            } else {
                $no = 1;
                $limit = array_slice($santri_data, 0, 100);
                foreach ($limit as $row):
                    $p_poin = (int)($row['poin_pelanggaran'] ?? 0);
                    $r_poin = (int)($row['poin_reward'] ?? 0);
                    $rapot  = round((float)($row['avg_rapot'] ?? 0), 1);
                    $skor   = $row['skor'] ?? 0;
                    $onclick = "window.location.href='detail_per_santri.php?id=" . $row['id'] . "&start_date=" . urlencode($start_date) . "&end_date=" . urlencode($end_date) . $filter_qs . "'";
            ?>
                <tr class="hover-row rank-<?= $no ?>" data-nama="<?= strtolower(htmlspecialchars($row['nama'])) ?>" onclick="<?= $onclick ?>" style="cursor: pointer;">
                    <td class="text-center ps-3">
                        <?php if ($no <= 3): ?>
                            <i class="fas fa-trophy rank-icon"></i>
                        <?php else: ?>
                            <span class="badge bg-light text-secondary border rounded-pill px-2.5 py-1 fw-bold"><?= $no ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="fw-bold text-dark text-decoration-none"><?= htmlspecialchars($row['nama']) ?></span>
                        <small class="text-muted d-block mt-0.5">NIS: <?= htmlspecialchars($row['nis'] ?? '-') ?> &bull; Kls <?= htmlspecialchars($row['kelas']) ?> &bull; Kmr <?= htmlspecialchars($row['kamar']) ?></small>
                    </td>
                    <?php if ($formula_cur === 'neraca'): 
                        $pb = $skor;
                        $disp_pb = $pb < 0 ? 0 : $pb;
                    ?>
                    <td class="text-center"><span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2.5 py-1"><?= $p_poin ?></span></td>
                    <td class="text-center"><span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1">+<?= $r_poin ?></span></td>
                    <td class="text-center"><span class="badge <?= $disp_pb > 0 ? 'bg-danger text-white' : 'bg-success text-white' ?> rounded-pill px-3 py-1.5 fw-bold fs-6"><?= $disp_pb ?></span></td>
                    <?php else: 
                        $str_rapot = $rapot > 0 ? number_format($rapot, 1, '.', '') : '–';
                    ?>
                    <td class="text-center"><span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2.5 py-1"><?= $p_poin ?></span></td>
                    <td class="text-center"><span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1">+<?= $r_poin ?></span></td>
                    <td class="text-center"><span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2.5 py-1"><i class="fas fa-star fa-xs me-1"></i><?= $str_rapot ?></span></td>
                    <td class="text-center"><span class="badge bg-primary text-white rounded-pill px-3 py-1.5 fw-bold fs-6"><?= number_format($skor, 2) ?></span></td>
                    <?php endif; ?>
                </tr>
            <?php
                    $no++;
                endforeach;
                if (isset($stmt_p)) mysqli_stmt_close($stmt_p);
            }
            ?>
            </tbody>
        </table>
    </div>
</div>

<div id="emptySearch" class="no-data" style="display:none; margin-top:20px;">
    <div class="icon">🔍</div>
    <p class="text-muted fw-semibold mb-1">Pencarian tidak ditemukan</p>
    <small class="text-secondary">Tidak ada santri dengan nama tersebut yang sesuai dengan filter.</small>
</div>
<?php if (!empty($santri_data) && count($santri_data) > 100): ?>
<div class="text-center mt-4 text-muted small">
    <i class="fas fa-bolt text-warning me-1"></i> Menampilkan <strong>Top 100</strong> dari <?= count($santri_data) ?> santri.
</div>
<?php endif; ?>

<?php endif; // end tipe render ?>

<?php if ($is_ajax):
    echo ob_get_clean();
    exit;
else: ?>
    </div><!-- #gridContainer -->
    </div><!-- #gridWrapper -->
</div><!-- .container -->

<script>
document.addEventListener('DOMContentLoaded', function () {
    const filterForm    = document.getElementById('filterForm');
    const gridContainer = document.getElementById('gridContainer');
    const loadingOvl    = document.getElementById('loadingOverlay');
    const tipeSelect    = document.getElementById('tipeSelect');
    const formulaSel    = document.getElementById('formula');
    const searchInput   = document.getElementById('searchNama');

    // ── Conditional filters visibility ────────────────────────────────────────
    function updateFilters() {
        const tipe    = tipeSelect.value;
        const formula = formulaSel ? formulaSel.value : 'semua_aspek';

        document.querySelectorAll('.f-daftar-hitam').forEach(el => el.classList.toggle('hidden', tipe !== 'daftar_hitam'));
        document.querySelectorAll('.f-peringkat').forEach(el => el.classList.toggle('hidden', tipe !== 'peringkat'));
        document.querySelectorAll('.f-semua-aspek').forEach(el => el.classList.toggle('hidden', tipe !== 'peringkat' || formula !== 'semua_aspek'));
    }

    tipeSelect.addEventListener('change', updateFilters);
    if (formulaSel) formulaSel.addEventListener('change', updateFilters);

    // ── Client-side search ─────────────────────────────────────────────────────
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            const q = this.value.toLowerCase().trim();
            let visibleCount = 0;

            // Filter baris tabel
            document.querySelectorAll('#bodyHitam tr[data-nama], #bodyPeringkat tr[data-nama]').forEach(el => {
                const isMatch = q === '' || el.dataset.nama.includes(q);
                el.classList.toggle('tr-hidden', !isMatch);
                if (isMatch) visibleCount++;
            });
            
            // Tampilkan empty state jika pencarian tidak ditemukan
            const emptySearch = document.getElementById('emptySearch');
            if (emptySearch) {
                const hasRealData = document.querySelectorAll('#bodyHitam tr[data-nama], #bodyPeringkat tr[data-nama]').length > 0;
                if (hasRealData && visibleCount === 0 && q !== '') {
                    emptySearch.style.display = 'block';
                } else {
                    emptySearch.style.display = 'none';
                }
            }
        });

        // Reset search saat tipe berubah
        tipeSelect.addEventListener('change', () => { 
            searchInput.value = ''; 
            searchInput.dispatchEvent(new Event('input'));
        });
    }

    // ── AJAX fetch & pushState ─────────────────────────────────────────────────
    function fetchGrid(url) {
        gridContainer.style.opacity = '0.3';
        gridContainer.style.pointerEvents = 'none';
        loadingOvl.style.display    = 'block';
        if (searchInput) searchInput.value = '';

        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.text();
            })
            .then(html => {
                gridContainer.innerHTML     = html;
                gridContainer.style.opacity = '1';
                gridContainer.style.pointerEvents = 'auto';
                loadingOvl.style.display    = 'none';
                window.history.pushState({}, '', url);
                syncPresetButtons(); // Re-sync setelah konten baru dimuat
            })
            .catch(() => {
                gridContainer.style.opacity = '1';
                gridContainer.style.pointerEvents = 'auto';
                loadingOvl.innerHTML = '<div class="alert alert-danger mx-0 mb-0 shadow-sm"><i class="fas fa-wifi me-2"></i>Koneksi terputus. Silakan refresh halaman.</div>';
            });
    }

    filterForm.addEventListener('submit', function (e) {
        e.preventDefault();
        fetchGrid('?' + new URLSearchParams(new FormData(filterForm)).toString());
    });

    // ── Sinkronisasi Warna Tombol Preset ──────────────────────────────────────
    function syncPresetButtons() {
        const startVal = document.getElementById('start_date').value;
        const endVal   = document.getElementById('end_date').value;
        const katEl    = document.getElementById('kategori');
        const tipeEl   = document.getElementById('tipeSelect');
        const katVal   = katEl ? katEl.value : '';
        const tipeVal  = tipeEl ? tipeEl.value : '';
        const periodeAktif  = '<?= $periode_aktif ?>';
        const hariIni       = '<?= date('Y-m-d') ?>';
        const earliestDate  = '<?= $earliest_date ?>';
        const endLalu       = '<?= date('Y-m-d', strtotime($periode_aktif . ' -1 day')) ?>';

        const bAktif = document.getElementById('btnPresetAktif');
        const bSB    = document.getElementById('btnPresetSangatBeratAktif');
        const bSBL   = document.getElementById('btnPresetSangatBeratLalu');
        const bAll   = document.getElementById('btnPresetAllTime');

        if (!bAktif || !bSB || !bSBL || !bAll) return;

        // Reset class active dari semua tombol preset
        [bAktif, bSB, bSBL, bAll].forEach(btn => {
            btn.classList.remove('active-primary', 'active-danger', 'active-warning', 'active-dark');
        });

        if (tipeVal === 'daftar_hitam') {
            const isAktif = (startVal === periodeAktif && endVal === hariIni && !katVal);
            const isSB    = (startVal === periodeAktif && endVal === hariIni && katVal === 'Sangat Berat');
            const isSBL   = (startVal === earliestDate && endVal === endLalu && katVal === 'Sangat Berat');
            const isAll   = (startVal === earliestDate && endVal === hariIni && !katVal);

            if (isSB) {
                bSB.classList.add('active-danger');
            } else if (isSBL) {
                bSBL.classList.add('active-warning');
            } else if (isAll) {
                bAll.classList.add('active-dark');
            } else if (isAktif) {
                bAktif.classList.add('active-primary');
            }
        }
    }

    // Auto-submit & sync saat filter berubah
    filterForm.querySelectorAll('select, input[type="date"]').forEach(el => {
        el.addEventListener('change', () => {
            syncPresetButtons();
            filterForm.dispatchEvent(new Event('submit'));
        });
    });

    // ── Quick Presets Handler ───────────────────────────────────────────────
    window.applyQuickPreset = function(type) {
        const startInput = document.getElementById('start_date');
        const endInput   = document.getElementById('end_date');
        const katSelect  = document.getElementById('kategori');
        const tipeSelect = document.getElementById('tipeSelect');
        const bagSelect  = document.getElementById('bagian');
        const jpSelect   = document.getElementById('jenis_pelanggaran');

        if (tipeSelect) tipeSelect.value = 'daftar_hitam';
        if (bagSelect)  bagSelect.value = '';
        if (jpSelect)   jpSelect.value = '';
        if (searchInput) { searchInput.value = ''; searchInput.dispatchEvent(new Event('input')); }

        if (type === 'aktif') {
            startInput.value = '<?= $periode_aktif ?>';
            endInput.value   = '<?= date('Y-m-d') ?>';
            if (katSelect) katSelect.value = '';
        } else if (type === 'sangat_berat_aktif') {
            startInput.value = '<?= $periode_aktif ?>';
            endInput.value   = '<?= date('Y-m-d') ?>';
            if (katSelect) katSelect.value = 'Sangat Berat';
        } else if (type === 'sangat_berat_lalu') {
            startInput.value = '<?= $earliest_date ?>';
            endInput.value   = '<?= date('Y-m-d', strtotime($periode_aktif . ' -1 day')) ?>';
            if (katSelect) katSelect.value = 'Sangat Berat';
        } else if (type === 'all_time') {
            startInput.value = '<?= $earliest_date ?>';
            endInput.value   = '<?= date('Y-m-d') ?>';
            if (katSelect) katSelect.value = '';
        }

        updateFilters();
        syncPresetButtons();
        filterForm.dispatchEvent(new Event('submit'));
    };

    // Jalankan sync awal
    updateFilters();
    syncPresetButtons();
});
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
<?php endif; // end !$is_ajax ?>
