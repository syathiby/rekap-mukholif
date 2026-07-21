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

// Backward compat: URL lama (pelanggaran_umum, karakter, santri_teladan) → tipe baru
$tipe_raw = $_GET['tipe'] ?? 'daftar_hitam';
$compat_map = ['pelanggaran_umum' => 'daftar_hitam', 'karakter' => 'peringkat', 'santri_teladan' => 'peringkat'];
if (isset($compat_map[$tipe_raw])) $tipe_raw = $compat_map[$tipe_raw];
$tipe = in_array($tipe_raw, ['daftar_hitam', 'peringkat']) ? $tipe_raw : 'daftar_hitam';

$filter_kamar = $_GET['kamar']      ?? null;
$filter_kelas = $_GET['kelas']      ?? null;
$start_date   = $_GET['start_date'] ?? $periode_aktif;
$end_date     = $_GET['end_date']   ?? date("Y-m-d");

// Dropdown kamar & kelas (dipakai semua tipe)
$kamars_result = mysqli_query($conn, "SELECT DISTINCT kamar FROM santri WHERE kamar IS NOT NULL AND kamar != '' ORDER BY CAST(REGEXP_REPLACE(kamar, '[^0-9]', '') AS UNSIGNED) ASC, REGEXP_REPLACE(kamar, '[0-9]', '') ASC");
$kelas_result  = mysqli_query($conn, "SELECT DISTINCT kelas FROM santri WHERE kelas IS NOT NULL AND kelas != '' ORDER BY CAST(REGEXP_REPLACE(kelas, '[^0-9]', '') AS UNSIGNED) ASC, REGEXP_REPLACE(kelas, '[0-9]', '') ASC");

// Dropdown filter khusus daftar hitam
$ff = [$start_date, $end_date];
$bagian_stmt = $conn->prepare("SELECT DISTINCT jp.bagian FROM pelanggaran p JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id WHERE DATE(p.tanggal) BETWEEN ? AND ? AND jp.bagian IS NOT NULL AND jp.bagian != '' ORDER BY jp.bagian ASC");
$bagian_stmt->bind_param("ss", ...$ff); $bagian_stmt->execute(); $bagian_result = $bagian_stmt->get_result();

$kategori_stmt = $conn->prepare("SELECT DISTINCT jp.kategori FROM pelanggaran p JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id WHERE DATE(p.tanggal) BETWEEN ? AND ? AND jp.kategori IS NOT NULL AND jp.kategori != '' ORDER BY FIELD(jp.kategori, 'Sangat Berat', 'Berat', 'Sedang', 'Ringan')");
$kategori_stmt->bind_param("ss", ...$ff); $kategori_stmt->execute(); $kategori_result = $kategori_stmt->get_result();

$jp_stmt = $conn->prepare("SELECT DISTINCT jp.id, jp.nama_pelanggaran FROM pelanggaran p JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id WHERE DATE(p.tanggal) BETWEEN ? AND ? ORDER BY jp.nama_pelanggaran ASC");
$jp_stmt->bind_param("ss", ...$ff); $jp_stmt->execute(); $jp_result = $jp_stmt->get_result();

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
/* Tabel Daftar Hitam */
.table th { background:var(--light-bg); color:var(--text-muted); text-transform:uppercase; font-size:.7rem; letter-spacing:.05em; }
.table tbody td { vertical-align:middle; padding:.85rem .75rem; }
.rank-icon { font-size:1.4rem; }
tr.rank-1 .rank-icon { color:var(--gold); }
tr.rank-2 .rank-icon { color:var(--silver); }
tr.rank-3 .rank-icon { color:var(--bronze); }
.poin-value { font-size:1.2rem; font-weight:700; color:var(--primary-dark); }
.periode-stats { font-size:.78rem; font-weight:500; color:var(--text-muted); background:#f1f5f9; padding:.2rem .6rem; border-radius:9999px; white-space:nowrap; display:inline-block; }
.poin-aktif-info { display:block; font-size:.72rem; font-weight:500; margin-top:2px; }
.btn-detail { background:var(--primary-light); color:var(--primary-dark); font-weight:600; text-decoration:none; transition:all .2s; }
.btn-detail:hover { background:var(--primary-dark); color:#fff; }
.tr-hidden { display:none !important; }
.hover-row { transition: background-color 0.2s; }
.hover-row:hover { background-color: #f8fafc !important; }
/* Grid Kartu Peringkat */
.card-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(300px,1fr)); gap:18px; }
.santri-card { background:#fff; border-radius:16px; box-shadow:0 4px 15px rgba(0,0,0,.04); padding:20px 18px 16px; position:relative; overflow:hidden; transition:transform .25s,box-shadow .25s; border:1px solid #f1f5f9; cursor:pointer; }
.santri-card.card-hidden { display:none; }
.santri-card:hover { transform:translateY(-4px); box-shadow:0 12px 25px rgba(0,0,0,.08); }
.santri-card.has-violation { border-top:4px solid var(--danger); }
.card-rank { position:absolute; top:15px; right:15px; height:36px; min-width:36px; padding:0 9px; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:14px; border-radius:50px; }
.card-rank.cr-1 { background:linear-gradient(135deg,#fbbf24,#d97706); color:#fff; box-shadow:0 4px 12px rgba(217,119,6,.3); border:2px solid #fff; }
.card-rank.cr-2 { background:linear-gradient(135deg,#cbd5e1,#64748b); color:#fff; box-shadow:0 4px 12px rgba(100,116,139,.3); border:2px solid #fff; }
.card-rank.cr-3 { background:linear-gradient(135deg,#fca5a5,#b91c1c); color:#fff; box-shadow:0 4px 12px rgba(185,28,28,.3); border:2px solid #fff; }
.card-rank.cr-other { background:#f1f5f9; color:#64748b; border:2px solid #e2e8f0; }
.santri-card.has-violation .card-rank { background:#fef2f2; color:var(--danger); border:2px solid #fecaca; box-shadow:none; }
.card-name { margin:0 0 10px 0; font-size:16px; font-weight:700; color:#0f172a; padding-right:44px; line-height:1.3; }
.card-name a { color:inherit; text-decoration:none; transition:color .2s; }
.card-name a:hover { color:var(--primary); }
.loc-badge { display:inline-flex; align-items:center; gap:6px; background:#f8fafc; color:#475569; padding:4px 10px; border-radius:20px; font-size:11px; font-weight:600; margin-bottom:14px; border:1px solid #f1f5f9; }
.stats-box { display:flex; justify-content:space-between; background:#f8fafc; border-radius:10px; padding:9px; border:1px solid #f1f5f9; }
.stat-col { text-align:center; flex:1; }
.stat-col:not(:last-child) { border-right:1px solid #e2e8f0; }
.stat-val { font-size:16px; font-weight:800; display:block; line-height:1.2; letter-spacing:-.5px; }
.stat-lbl { font-size:9px; color:#64748b; text-transform:uppercase; letter-spacing:.8px; margin-top:3px; font-weight:600; }
.val-red   { color:var(--danger); }
.val-green { color:var(--success); }
.val-blue  { color:var(--primary); }
.val-muted { color:var(--text-muted); }
.score-row { margin-top:12px; padding-top:12px; border-top:1px dashed #e2e8f0; display:flex; justify-content:space-between; align-items:center; }
.score-lbl  { font-size:10px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:.5px; }
.score-num  { font-size:17px; font-weight:800; color:var(--primary); }
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
        <h5 class="fw-bold mb-3"><i class="fas fa-sliders-h me-2 text-primary"></i>Filter &amp; Pencarian</h5>
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
if (!empty($filter_bagian)) $filter_qs .= "&bagian=" . urlencode($filter_bagian);
if (!empty($filter_kategori)) $filter_qs .= "&kategori=" . urlencode($filter_kategori);
if (!empty($filter_jp)) $filter_qs .= "&jenis_pelanggaran=" . urlencode($filter_jp);

// ================================================================
// RENDER KONTEN
// ================================================================

if ($tipe === 'daftar_hitam'):
?>
<div class="card border-0 shadow-sm">
    <div class="card-body table-responsive p-0">
        <table class="table table-hover mb-0" id="tabelHitam">
            <thead>
                <tr>
                    <th class="text-center ps-3" style="width:90px;">Peringkat</th>
                    <th>Santri</th>
                    <?php if ($filter_bagian === 'Pengabdian'): ?>
                        <th class="text-center">Total</th>
                        <th class="text-start">Detail Pelanggaran</th>
                    <?php else: ?>
                        <th class="text-center">Poin</th>
                        <th class="text-center">Kasus</th>
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
                <?php $no = 1; while ($row = mysqli_fetch_assoc($query)): ?>
                <tr class="rank-<?= $no ?> hover-row" data-nama="<?= strtolower(htmlspecialchars($row['nama'])) ?>" onclick="window.location.href='detail_per_santri.php?id=<?= $row['id'] ?>&start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?><?= $filter_qs ?>'" style="cursor: pointer;">
                    <td class="text-center ps-3">
                        <?php if ($no <= 3): ?><i class="fas fa-trophy rank-icon"></i><?php else: ?><span class="fw-bold fs-5 text-dark"><?= $no ?></span><?php endif; ?>
                    </td>
                    <td>
                        <span class="fw-bold text-dark fs-6 text-decoration-none"><?= htmlspecialchars($row['nama']) ?></span>
                        <small class="text-muted d-block mt-1 mb-1">NIS: <span class="fw-medium"><?= htmlspecialchars($row['nis'] ?? '-') ?></span></small>
                        <small class="text-muted d-block mb-1">Kls: <?= htmlspecialchars($row['kelas']) ?> &bull; Kmr: <?= htmlspecialchars($row['kamar']) ?></small>
                        <?php $pa = (int)$row['poin_aktif']; ?>
                        <?php if ($pa > 0): ?><small class="poin-aktif-info text-danger"><i class="fas fa-history fa-xs"></i> Histori: <?= $pa ?></small>
                        <?php elseif ($pa < 0): ?><small class="poin-aktif-info text-success"><i class="fas fa-star fa-xs text-warning"></i> Surplus: <?= abs($pa) ?> Reward</small>
                        <?php else: ?><small class="poin-aktif-info text-secondary"><i class="fas fa-history fa-xs"></i> Histori: 0 (Bersih)</small><?php endif; ?>
                    </td>
                    </td>
                    <?php if ($filter_bagian === 'Pengabdian'): ?>
                        <td class="text-center align-middle">
                            <span class="badge rounded-pill bg-danger bg-opacity-10 text-danger fs-6 px-3 py-2"><?= $row['total_pelanggaran_periode'] ?></span>
                        </td>
                        <td class="text-start align-middle">
                            <div class="d-flex flex-column gap-1">
                                <?php 
                                if (!empty($row['detail_kasus'])) {
                                    $kasus_list = explode('|', $row['detail_kasus']);
                                    foreach ($kasus_list as $k) {
                                        $parts = explode(':', $k);
                                        if (count($parts) == 2) {
                                            $nama_p = trim($parts[0]);
                                            $cnt_p = trim($parts[1]);
                                            // Pilih warna/ikon dinamis
                                            $icon = 'fas fa-exclamation-circle';
                                            $color = 'text-primary';
                                            if (stripos($nama_p, 'telat sholat') !== false) {
                                                $icon = 'fas fa-praying-hands';
                                                $color = 'text-danger';
                                            } elseif (stripos($nama_p, 'telat kbm') !== false) {
                                                $icon = 'fas fa-book-open';
                                                $color = 'text-primary';
                                            }
                                            echo "<div style='font-size: 0.9rem; font-weight: 500;'><i class='{$icon} {$color} me-2' style='width:16px;text-align:center;'></i><span class='{$color}'>{$nama_p}:</span> <strong class='text-dark'>{$cnt_p}</strong></div>";
                                        }
                                    }
                                }
                                ?>
                            </div>
                        </td>
                    <?php else: ?>
                        <td class="text-center"><span class="poin-value"><?= $row['total_poin_periode'] ?></span></td>
                        <td class="text-center"><span class="periode-stats"><?= $row['total_pelanggaran_periode'] ?>×</span></td>
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
<div class="card border-0 shadow-sm" id="cardGrid">
    <div class="card-body table-responsive p-0">
        <table class="table table-hover mb-0" id="tabelPeringkat">
            <thead>
                <tr>
                    <th class="text-center ps-3" style="width:60px;">No.</th>
                    <th>Santri</th>
                    <?php if ($formula_cur === 'neraca'): ?>
                    <th class="text-center">Pelanggaran</th>
                    <th class="text-center">Reward</th>
                    <th class="text-center">Poin Bersih</th>
                    <?php else: ?>
                    <th class="text-center">Langgar</th>
                    <th class="text-center">Reward</th>
                    <th class="text-center">Rapot</th>
                    <th class="text-center">Skor Teladan</th>
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
                        <?php if ($no <= 3): ?><i class="fas fa-trophy rank-icon"></i><?php else: ?><span class="fw-bold fs-6 text-muted"><?= $no ?></span><?php endif; ?>
                    </td>
                    <td>
                        <span class="fw-bold text-dark text-decoration-none"><?= htmlspecialchars($row['nama']) ?></span>
                        <small class="text-muted d-block mb-1">NIS: <span class="fw-medium"><?= htmlspecialchars($row['nis'] ?? '-') ?></span></small>
                        <small class="text-muted d-block">Kls <?= htmlspecialchars($row['kelas']) ?> &bull; Kmr <?= htmlspecialchars($row['kamar']) ?></small>
                    </td>
                    <?php if ($formula_cur === 'neraca'): 
                        $pb = $skor;
                        $disp_pb = $pb < 0 ? 0 : $pb;
                    ?>
                    <td class="text-center"><span class="periode-stats <?= $p_poin > 0 ? 'text-danger' : 'text-success' ?>"><?= $p_poin ?></span></td>
                    <td class="text-center"><span class="periode-stats text-success">+<?= $r_poin ?></span></td>
                    <td class="text-center"><span class="poin-value <?= $disp_pb > 0 ? 'text-danger' : 'text-success' ?>"><?= $disp_pb ?></span></td>
                    <?php else: 
                        $str_rapot = $rapot > 0 ? number_format($rapot, 1, '.', '') : '–';
                    ?>
                    <td class="text-center"><span class="periode-stats <?= $p_poin > 0 ? 'text-danger' : 'text-success' ?>"><?= $p_poin ?></span></td>
                    <td class="text-center"><span class="periode-stats text-success"><?= $r_poin > 0 ? '+' : '' ?><?= $r_poin ?></span></td>
                    <td class="text-center"><span class="periode-stats text-primary"><i class="fas fa-star" style="font-size:9px;"></i> <?= $str_rapot ?></span></td>
                    <td class="text-center"><span class="poin-value text-primary"><?= number_format($skor, 2) ?></span></td>
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

            // Tabel rows
            document.querySelectorAll('#bodyHitam tr[data-nama]').forEach(tr => {
                const isMatch = q === '' || tr.dataset.nama.includes(q);
                tr.classList.toggle('tr-hidden', !isMatch);
                if (isMatch) visibleCount++;
            });

            // Tabel Peringkat
            document.querySelectorAll('#bodyPeringkat tr[data-nama]').forEach(tr => {
                const isMatch = q === '' || tr.dataset.nama.includes(q);
                tr.classList.toggle('tr-hidden', !isMatch);
                if (isMatch) visibleCount++;
            });
            
            // Tampilkan empty state jika pencarian tidak ditemukan
            const emptySearch = document.getElementById('emptySearch');
            if (emptySearch) {
                // Jangan hitung row "Tidak ada data pelanggaran ditemukan" yang tidak punya data-nama
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
            .then(r => r.text())
            .then(html => {
                gridContainer.innerHTML     = html;
                gridContainer.style.opacity = '1';
                gridContainer.style.pointerEvents = 'auto';
                loadingOvl.style.display    = 'none';
                window.history.pushState({}, '', url);
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

    // Auto-submit saat filter DB berubah (bukan search)
    filterForm.querySelectorAll('select, input[type="date"]').forEach(el => {
        el.addEventListener('change', () => filterForm.dispatchEvent(new Event('submit')));
    });
});
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
<?php endif; // end !$is_ajax ?>
