<?php
// 1. Panggil 'Otak' aplikasi
require_once __DIR__ . '/../bootstrap/init.php';

// 2. Jalankan 'SATPAM'
guard('rekap_detail_santri');

// 3. Ambil Parameter
$santri_id = (int)($_GET['id'] ?? 0);
if ($santri_id <= 0) {
    $_SESSION['flash_error'] = "Data santri tidak valid.";
    header("Location: karakter.php");
    exit;
}

$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-1 year'));
$end_date   = $_GET['end_date']   ?? date('Y-m-d');

// ─── 4. Info Santri ───────────────────────────────────────────────────────────
$stmt_santri = $conn->prepare("SELECT id, nama, kelas, kamar, poin_aktif FROM santri WHERE id = ?");
$stmt_santri->bind_param("i", $santri_id);
$stmt_santri->execute();
$santri = $stmt_santri->get_result()->fetch_assoc();
if (!$santri) { die("Data santri tidak ditemukan."); }

// ─── 5. Pelanggaran per kategori ──────────────────────────────────────────────
// Pakai p.tanggal >= ? AND p.tanggal < ? agar index dapat dipakai (tidak wrap DATE())
$start_dt = $start_date . ' 00:00:00';
$end_dt   = $end_date   . ' 23:59:59';

$stmt_pk = $conn->prepare(
    "SELECT jp.kategori, COUNT(p.id) as jumlah, SUM(jp.poin) as total_poin
     FROM pelanggaran p
     JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id
     WHERE p.santri_id = ? AND p.tanggal BETWEEN ? AND ?
     GROUP BY jp.kategori"
);
$stmt_pk->bind_param("iss", $santri_id, $start_dt, $end_dt);
$stmt_pk->execute();
$res_pk = $stmt_pk->get_result();
$pelanggaran_kategori  = [];
$total_poin_pelanggaran = 0;
while ($row = $res_pk->fetch_assoc()) {
    $pelanggaran_kategori[] = $row;
    $total_poin_pelanggaran += $row['total_poin'];
}

// ─── 6. Reward ringkasan ──────────────────────────────────────────────────────
$stmt_rk = $conn->prepare(
    "SELECT COUNT(dr.id) as jumlah, SUM(jr.poin_reward) as total_poin
     FROM daftar_reward dr
     JOIN jenis_reward jr ON dr.jenis_reward_id = jr.id
     WHERE dr.santri_id = ? AND dr.tanggal BETWEEN ? AND ?"
);
$stmt_rk->bind_param("iss", $santri_id, $start_dt, $end_dt);
$stmt_rk->execute();
$res_rk = $stmt_rk->get_result();
$reward_kategori  = [];
$total_poin_reward = 0;
if ($row = $res_rk->fetch_assoc()) {
    if ($row['jumlah'] > 0) {
        $reward_kategori[] = array_merge(['kategori' => 'Reward'], $row);
        $total_poin_reward  = $row['total_poin'];
    }
}

// ─── 7. Tren bulanan — GABUNG 1 query UNION ───────────────────────────────────
// Sebelumnya 2 query terpisah, sekarang 1 UNION lalu merge di PHP
$stmt_tren = $conn->prepare(
    "SELECT 'p' AS src, DATE_FORMAT(p.tanggal,'%Y-%m') AS bulan, SUM(jp.poin) AS poin
     FROM pelanggaran p
     JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id
     WHERE p.santri_id = ? AND p.tanggal BETWEEN ? AND ?
     GROUP BY bulan
     UNION ALL
     SELECT 'r' AS src, DATE_FORMAT(dr.tanggal,'%Y-%m') AS bulan, SUM(jr.poin_reward) AS poin
     FROM daftar_reward dr
     JOIN jenis_reward jr ON dr.jenis_reward_id = jr.id
     WHERE dr.santri_id = ? AND dr.tanggal BETWEEN ? AND ?
     GROUP BY bulan
     ORDER BY bulan ASC"
);
$stmt_tren->bind_param("ississ", $santri_id, $start_dt, $end_dt, $santri_id, $start_dt, $end_dt);
$stmt_tren->execute();
$res_tren = $stmt_tren->get_result();
$tren_p = [];
$tren_r = [];
while ($row = $res_tren->fetch_assoc()) {
    if ($row['src'] === 'p') $tren_p[$row['bulan']] = $row['poin'];
    else                     $tren_r[$row['bulan']] = $row['poin'];
}

$all_months = array_unique(array_merge(array_keys($tren_p), array_keys($tren_r)));
sort($all_months);
$labels = $data_p = $data_r = [];
foreach ($all_months as $m) {
    $labels[] = date('M Y', strtotime($m . '-01'));
    $data_p[] = $tren_p[$m] ?? 0;
    $data_r[] = $tren_r[$m] ?? 0;
}

// ─── 8. Kesimpulan karakter (dari poin pelanggaran/reward) ────────────────────
$rasio = $karakter_class = $karakter_icon = '';
if ($total_poin_reward == 0 && $total_poin_pelanggaran == 0) {
    $rasio = "Santri belum memiliki catatan pelanggaran maupun reward pada periode ini.";
    $karakter_class = "text-secondary"; $karakter_icon = "fa-minus-circle";
} elseif ($total_poin_reward >= $total_poin_pelanggaran * 2) {
    $rasio = "Sangat Baik. Santri menunjukkan prestasi yang jauh melampaui catatan pelanggarannya.";
    $karakter_class = "text-success";   $karakter_icon = "fa-star";
} elseif ($total_poin_reward > $total_poin_pelanggaran) {
    $rasio = "Baik. Santri lebih banyak mendapatkan apresiasi/reward dibanding melakukan pelanggaran.";
    $karakter_class = "text-primary";   $karakter_icon = "fa-thumbs-up";
} elseif ($total_poin_pelanggaran >= $total_poin_reward * 2 && $total_poin_reward > 0) {
    $rasio = "Perlu Perhatian Khusus. Pelanggaran santri jauh mendominasi dibandingkan prestasinya.";
    $karakter_class = "text-danger";    $karakter_icon = "fa-exclamation-triangle";
} elseif ($total_poin_pelanggaran > $total_poin_reward) {
    $rasio = "Kurang Disiplin. Catatan pelanggaran masih lebih tinggi daripada prestasi/reward.";
    $karakter_class = "text-warning";   $karakter_icon = "fa-exclamation-circle";
} else {
    $rasio = "Seimbang. Santri memiliki jumlah poin pelanggaran dan reward yang seimbang.";
    $karakter_class = "text-info";      $karakter_icon = "fa-balance-scale";
}

// ─── 9. Status peringatan SP ──────────────────────────────────────────────────
$sp_status = $sp_class = '';
if ($santri['poin_aktif'] >= 300)      { $sp_status = "PERINGATAN 3"; $sp_class = "bg-danger text-white"; }
elseif ($santri['poin_aktif'] >= 200)  { $sp_status = "PERINGATAN 2"; $sp_class = "bg-warning text-dark"; }
elseif ($santri['poin_aktif'] >= 100)  { $sp_status = "PERINGATAN 1"; $sp_class = "bg-info text-white"; }

// ─── 10. Rapot: radar (1 query saja, cukup untuk radar + stat cards) ──────────
// Query tren karakter HANYA dijalankan jika ada rapot (skip jika kosong)
$stmt_radar = $conn->prepare(
    "SELECT
        AVG(puasa_sunnah) as puasa_sunnah, AVG(sholat_duha) as sholat_duha,
        AVG(sholat_malam) as sholat_malam, AVG(sedekah) as sedekah,
        AVG(sunnah_tidur) as sunnah_tidur, AVG(ibadah_lainnya) as ibadah_lainnya,
        AVG(lisan) as lisan, AVG(sikap) as sikap,
        AVG(kesopanan) as kesopanan, AVG(muamalah) as muamalah,
        AVG(tidur) as tidur, AVG(keterlambatan) as keterlambatan,
        AVG(seragam) as seragam, AVG(makan) as makan,
        AVG(arahan) as arahan, AVG(bahasa_arab) as bahasa_arab,
        AVG(mandi) as mandi, AVG(penampilan) as penampilan,
        AVG(piket) as piket, AVG(kerapihan_barang) as kerapihan_barang,
        COUNT(*) as total_rapot
     FROM rapot_kepengasuhan 
     WHERE santri_id = ?
       AND STR_TO_DATE(CONCAT(tahun, '-', FIELD(bulan, 'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'), '-01'), '%Y-%c-%d') 
           BETWEEN STR_TO_DATE(CONCAT(DATE_FORMAT(?, '%Y-%m'), '-01'), '%Y-%m-%d') 
           AND LAST_DAY(?)"
);
$stmt_radar->bind_param("iss", $santri_id, $start_date, $end_date);
$stmt_radar->execute();
$radar_data  = $stmt_radar->get_result()->fetch_assoc();
$total_rapot = (int)($radar_data['total_rapot'] ?? 0);

// Radar labels & values
$radar_labels = [
    'Puasa Sunnah','Sholat Duha','Sholat Malam','Sedekah','Sunnah Tidur','Ibadah Lainnya',
    'Lisan','Sikap','Kesopanan','Muamalah',
    'Tidur','Keterlambatan','Seragam','Makan','Ikuti Arahan','Bahasa Arab',
    'Mandi','Penampilan','Piket','Kerapihan Barang'
];
$radar_keys = [
    'puasa_sunnah','sholat_duha','sholat_malam','sedekah','sunnah_tidur','ibadah_lainnya',
    'lisan','sikap','kesopanan','muamalah',
    'tidur','keterlambatan','seragam','makan','arahan','bahasa_arab',
    'mandi','penampilan','piket','kerapihan_barang'
];
$radar_values = [];
foreach ($radar_keys as $key) { $radar_values[] = round((float)($radar_data[$key] ?? 0), 2); }

// Stat cards — hitung dari data radar yang sudah ada (tanpa query tambahan)
$avg_ibadah = $avg_akhlaq = $avg_kedisiplinan = $avg_kebersihan = 0;
if ($total_rapot > 0) {
    $sum = fn(array $keys) => array_sum(array_map(fn($k) => (float)($radar_data[$k] ?? 0), $keys));
    $avg_ibadah      = round($sum(['puasa_sunnah','sholat_duha','sholat_malam','sedekah','sunnah_tidur','ibadah_lainnya']) / 6, 1);
    $avg_akhlaq      = round($sum(['lisan','sikap','kesopanan','muamalah']) / 4, 1);
    $avg_kedisiplinan = round($sum(['tidur','keterlambatan','seragam','makan','arahan','bahasa_arab']) / 6, 1);
    $avg_kebersihan  = round($sum(['mandi','penampilan','piket','kerapihan_barang']) / 4, 1);
}

// ─── 11. Tren karakter — SKIP jika tidak ada rapot ───────────────────────────
$tren_karakter_labels = $tren_ibadah = $tren_akhlaq = $tren_kedisiplinan = $tren_kebersihan = [];
if ($total_rapot > 0) {
    $bulan_order = "FIELD(bulan,'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember')";
    $stmt_tren_k = $conn->prepare(
        "SELECT CONCAT(SUBSTRING(bulan,1,3),' ',tahun) AS label_bulan,
                ROUND((puasa_sunnah+sholat_duha+sholat_malam+sedekah+sunnah_tidur+ibadah_lainnya)/6,2) AS avg_ibadah,
                ROUND((lisan+sikap+kesopanan+muamalah)/4,2) AS avg_akhlaq,
                ROUND((tidur+keterlambatan+seragam+makan+arahan+bahasa_arab)/6,2) AS avg_kedisiplinan,
                ROUND((mandi+penampilan+piket+kerapihan_barang)/4,2) AS avg_kebersihan
         FROM rapot_kepengasuhan
         WHERE santri_id = ?
           AND STR_TO_DATE(CONCAT(tahun, '-', FIELD(bulan, 'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'), '-01'), '%Y-%c-%d') 
               BETWEEN STR_TO_DATE(CONCAT(DATE_FORMAT(?, '%Y-%m'), '-01'), '%Y-%m-%d') 
               AND LAST_DAY(?)
         ORDER BY tahun ASC, $bulan_order ASC"
    );
    $stmt_tren_k->bind_param("iss", $santri_id, $start_date, $end_date);
    $stmt_tren_k->execute();
    $res_tren_k = $stmt_tren_k->get_result();
    while ($row = $res_tren_k->fetch_assoc()) {
        $tren_karakter_labels[] = $row['label_bulan'];
        $tren_ibadah[]          = (float)$row['avg_ibadah'];
        $tren_akhlaq[]          = (float)$row['avg_akhlaq'];
        $tren_kedisiplinan[]    = (float)$row['avg_kedisiplinan'];
        $tren_kebersihan[]      = (float)$row['avg_kebersihan'];
    }
}

// ─── 11.5. Hitung Skor Sinkronisasi (Untuk Info Banner) ──────────────────────
$total_kasus = 0;
foreach ($pelanggaran_kategori as $pk) {
    $total_kasus += $pk['jumlah'];
}
$rata_rapot = 0;
if ($total_rapot > 0) {
    $rata_rapot = array_sum($radar_values) / 20;
}
$skor_teladan = ($rata_rapot * 20) + $total_poin_reward - ($total_poin_pelanggaran * 2) - ($total_kasus * 5);
$skor_teladan_str = ($total_rapot > 0) ? number_format($skor_teladan, 1, '.', '') : '-';

$poin_aktif_val = (int)$santri['poin_aktif'];
$poin_bersih = $poin_aktif_val < 0 ? 0 : $poin_aktif_val;

// ─── 12. JSON encode (hanya sekali) ──────────────────────────────────────────
$json_labels_p      = json_encode($labels);
$json_data_p        = json_encode($data_p);
$json_data_r        = json_encode($data_r);
$json_radar_labels  = json_encode($radar_labels);
$json_radar_values  = json_encode($radar_values);
$json_tren_k_labels = json_encode($tren_karakter_labels);
$json_tren_ibadah   = json_encode($tren_ibadah);
$json_tren_akhlaq   = json_encode($tren_akhlaq);
$json_tren_disiplin = json_encode($tren_kedisiplinan);
$json_tren_bersih   = json_encode($tren_kebersihan);

// ─── Komposisi chart data (hitung PHP, bukan duplikat di JS) ─────────────────
$comp_labels = $comp_data = $comp_colors = [];
$colorMap = ['Sangat Berat'=>'#b91c1c','Berat'=>'#ef4444','Sedang'=>'#f59e0b','Ringan'=>'#fcd34d','Reward'=>'#10b981'];
foreach ($pelanggaran_kategori as $pk) {
    $comp_labels[] = 'Pelanggaran ' . $pk['kategori'];
    $comp_data[]   = $pk['total_poin'];
    $comp_colors[] = $colorMap[$pk['kategori']] ?? '#6b7280';
}
foreach ($reward_kategori as $rk) {
    $comp_labels[] = 'Reward';
    $comp_data[]   = $rk['total_poin'];
    $comp_colors[] = '#10b981';
}
$json_comp_labels = json_encode($comp_labels);
$json_comp_data   = json_encode($comp_data);
$json_comp_colors = json_encode($comp_colors);

// Flag untuk JS (kurangi logika kondisional inline di JS)
$has_tren_data    = !empty($labels);
$has_comp_data    = !empty($comp_data);
$has_rapot        = $total_rapot > 0;
$has_tren_karakter = count($tren_karakter_labels) >= 2;

require_once __DIR__ . '/../layouts/header.php';
?>
<style>
:root{--ibadah:#10b981;--akhlaq:#6366f1;--disiplin:#f59e0b;--bersih:#3b82f6;--cr:16px}

/* Info Cards */
.info-card{background:#fff;border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.06);padding:1.4rem 1.5rem;height:100%;border-top:4px solid var(--primary)}
.info-card.danger{border-top-color:#ef4444}.info-card.success{border-top-color:#10b981}
.big-number{font-size:2.4rem;font-weight:800;line-height:1;margin-top:.5rem;letter-spacing:-1px}

/* Hero */
.character-summary{background:linear-gradient(135deg,#f8fafc,#eef2ff);border-radius:20px;padding:2rem;text-align:center;margin-bottom:2rem;border:1px solid #e0e7ff;box-shadow:0 4px 20px rgba(99,102,241,.07)}
.character-icon{font-size:3rem;margin-bottom:1rem}

/* Tabs */
.analysis-tabs{margin-bottom:1.5rem;border-bottom:2px solid #e2e8f0;gap:4px;display:flex;flex-wrap:nowrap}
.analysis-tabs .nav-link{color:#64748b;font-weight:600;font-size:.875rem;padding:.75rem 1.4rem;border:none;border-bottom:3px solid transparent;border-radius:0;transition:all .2s;margin-bottom:-2px;white-space:nowrap}
.analysis-tabs .nav-link:hover{color:#4f46e5;background:#f1f5f9;border-radius:8px 8px 0 0}
.analysis-tabs .nav-link.active{color:#4f46e5;background:transparent;border-bottom-color:#4f46e5}
.analysis-tabs .nav-link i{margin-right:7px}

/* Pro chart card */
.pro-chart-card{background:#fff;border-radius:var(--cr);box-shadow:0 2px 16px rgba(0,0,0,.07);padding:1.5rem 1.75rem 1.75rem;margin-bottom:1.5rem;border:1px solid #f1f5f9}
.chart-title{font-size:1rem;font-weight:700;color:#1e293b;margin-bottom:2px;display:flex;align-items:center;gap:8px}
.chart-subtitle{font-size:.8rem;color:#94a3b8;margin-bottom:1.25rem;padding-left:28px}
.chart-icon-wrap{width:28px;height:28px;border-radius:8px;display:inline-flex;align-items:center;justify-content:center;font-size:.8rem;flex-shrink:0}

/* Scrollable wrappers */
.chart-scroll-outer{overflow-x:auto;-webkit-overflow-scrolling:touch;scrollbar-width:thin;scrollbar-color:#cbd5e1 #f8fafc;border-radius:10px}
.chart-scroll-outer::-webkit-scrollbar{height:5px}
.chart-scroll-outer::-webkit-scrollbar-track{background:#f8fafc;border-radius:10px}
.chart-scroll-outer::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:10px}
.chart-scroll-inner{position:relative;height:300px}
.chart-scroll-inner.tall{height:360px}
.chart-scroll-inner.radar{height:420px}

/* Stat cards */
.ks-card{border-radius:14px;padding:1rem 1.2rem;display:flex;align-items:center;gap:.9rem;box-shadow:0 2px 10px rgba(0,0,0,.06);background:#fff;border:1px solid #f1f5f9;transition:transform .2s,box-shadow .2s}
.ks-card:hover{transform:translateY(-3px);box-shadow:0 6px 20px rgba(0,0,0,.1)}
.ks-icon{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.15rem;flex-shrink:0}
.ks-ibadah .ks-icon{background:#d1fae5;color:var(--ibadah)}
.ks-akhlaq .ks-icon{background:#ede9fe;color:var(--akhlaq)}
.ks-disiplin .ks-icon{background:#fef3c7;color:var(--disiplin)}
.ks-bersih .ks-icon{background:#dbeafe;color:var(--bersih)}
.ks-val{font-size:1.6rem;font-weight:800;line-height:1;letter-spacing:-.5px}
.ks-lbl{font-size:.72rem;font-weight:600;color:#94a3b8;text-transform:uppercase;letter-spacing:.6px;margin-top:3px}

/* Empty / no-data state */
.no-rapot-state{background:#f8fafc;border:2px dashed #e2e8f0;border-radius:20px;padding:3rem 2rem;text-align:center}
.no-rapot-state .icon-wrap{width:72px;height:72px;background:#e0e7ff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.8rem;color:#6366f1;margin:0 auto 1.25rem}
.empty-chart{display:flex;flex-direction:column;align-items:center;justify-content:center;color:#94a3b8;padding:3rem 1rem;gap:.75rem}
.empty-chart i{font-size:2rem;opacity:.3}
.empty-chart p{margin:0;font-size:.85rem;text-align:center}

/* Scroll hint (mobile only) */
.scroll-hint{font-size:.72rem;color:#94a3b8;text-align:right;margin-bottom:6px;display:none}
@media(max-width:767px){
    .scroll-hint{display:block}
    .pro-chart-card{padding:1.25rem 1rem 1.5rem}
    .info-card{padding:1.1rem 1.1rem}
    .big-number{font-size:1.9rem}
    .character-summary{padding:1.5rem 1rem;border-radius:14px}
    .character-icon{font-size:2.2rem;margin-bottom:.6rem}
    .character-summary h3{font-size:1.1rem}
    .character-summary .lead{font-size:.9rem}
    .analysis-tabs .nav-link{padding:.65rem .9rem;font-size:.82rem}
}
</style>

<div class="container py-4" style="overflow-x:hidden">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="page-title m-0"><i class="fas fa-chart-pie me-2"></i>Analisis Santri</h1>
        <a href="javascript:history.back()" class="btn btn-outline-secondary rounded-pill shadow-sm">
            <i class="fas fa-arrow-left me-1"></i> Kembali
        </a>
    </div>

    <!-- Hero summary -->
    <div class="character-summary">
        <i class="fas <?= $karakter_icon ?> character-icon <?= $karakter_class ?>"></i>
        <h3 class="fw-bold mb-2"><?= htmlspecialchars($santri['nama']) ?></h3>
        <p class="text-muted mb-2">Kelas: <?= htmlspecialchars($santri['kelas']) ?> &nbsp;|&nbsp; Kamar: <?= htmlspecialchars($santri['kamar']) ?></p>
        
        <div class="mb-3">
            <span class="badge bg-white text-secondary border px-3 py-2 shadow-sm text-wrap" style="font-size: 0.85rem; font-weight: 500; border-radius: 8px; line-height: 1.5;">
                <i class="far fa-calendar-alt me-1 text-primary"></i> Menampilkan data: <strong><?= date('d M Y', strtotime($start_date)) ?></strong> s/d <strong><?= date('d M Y', strtotime($end_date)) ?></strong>
            </span>
        </div>
        <?php if ($sp_status): ?>
        <div class="mb-3">
            <span class="badge rounded-pill <?= $sp_class ?> px-4 py-2 shadow-sm" style="font-size:.9rem;letter-spacing:1px">
                <i class="fas fa-exclamation-triangle me-2"></i><?= $sp_status ?>
            </span>
        </div>
        <?php endif; ?>
        <p class="lead mb-0 <?= $karakter_class ?> fw-semibold"><?= $rasio ?></p>
    </div>

    <!-- Info banner Sinkronisasi -->
    <div style="margin-bottom: 25px; padding: 12px 18px; border-radius: 10px; font-size: 13.5px; display: flex; align-items: flex-start; gap: 12px; background-color: #eff6ff; color: #1e3a8a; border: 1px solid #bfdbfe;">
        <i class="fas fa-link" style="font-size: 18px; margin-top: 2px;"></i>
        <div>
            <strong>Info Sinkronisasi:</strong> Halaman detail ini menyajikan data santri yang saling terhubung dengan ketiga halaman rekap utama. <strong>Skor Teladan</strong> untuk halaman Santri Teladan, <strong>Poin Bersih</strong> untuk halaman Rekap Karakter, dan poin <strong>Pelanggaran</strong> menjadi acuan di Daftar Hitam.
        </div>
    </div>

    <!-- Info cards poin -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="info-card" style="border-top-color:#3b82f6; padding: 1.1rem;">
                <div class="text-muted text-uppercase fw-bold" style="letter-spacing:0.5px;font-size:.7rem">Skor Teladan</div>
                <div class="big-number text-primary mt-1" style="font-size: 2rem;"><?= $skor_teladan_str ?></div>
                <div class="text-muted small mt-1" style="font-size: 0.7rem; line-height: 1.1;">Acuan: Santri Teladan</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="info-card" style="border-top-color:#0ea5e9; padding: 1.1rem;">
                <div class="text-muted text-uppercase fw-bold" style="letter-spacing:0.5px;font-size:.7rem">Poin Bersih</div>
                <div class="big-number mt-1 <?= $poin_bersih > 0 ? 'text-danger' : 'text-success' ?>" style="font-size: 2rem;"><?= $poin_bersih ?></div>
                <div class="text-muted small mt-1" style="font-size: 0.7rem; line-height: 1.1;">Acuan: Rekap Karakter</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="info-card danger" style="padding: 1.1rem;">
                <div class="text-muted text-uppercase fw-bold" style="letter-spacing:0.5px;font-size:.7rem">Pelanggaran</div>
                <div class="big-number text-danger mt-1" style="font-size: 2rem;">
                    <?= $total_poin_pelanggaran ?> 
                    <span style="font-size: 0.9rem; font-weight: 500; color: #94a3b8;">(<?= $total_kasus ?>x)</span>
                </div>
                <div class="text-muted small mt-1" style="font-size: 0.7rem; line-height: 1.1;">Acuan: Daftar Hitam</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="info-card success" style="padding: 1.1rem;">
                <div class="text-muted text-uppercase fw-bold" style="letter-spacing:0.5px;font-size:.7rem">Total Reward</div>
                <div class="big-number text-success mt-1" style="font-size: 2rem;">+<?= $total_poin_reward ?></div>
                <div class="text-muted small mt-1" style="font-size: 0.7rem; line-height: 1.1;">Apresiasi periode ini</div>
            </div>
        </div>
    </div>

    <!-- Tab navigation -->
    <ul class="nav analysis-tabs" id="analysisTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tab-pelanggaran" data-bs-toggle="tab" data-bs-target="#pane-pelanggaran" type="button" role="tab">
                <i class="fas fa-exclamation-triangle"></i> Pelanggaran &amp; Reward
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-karakter" data-bs-toggle="tab" data-bs-target="#pane-karakter" type="button" role="tab">
                <i class="fas fa-star-half-alt"></i> Karakter
                <?php if ($has_rapot): ?>
                    <span class="badge ms-1" style="background:#6366f1;font-size:.7rem"><?= $total_rapot ?></span>
                <?php endif; ?>
            </button>
        </li>
    </ul>

    <!-- Tab panes -->
    <div class="tab-content" id="analysisTabContent">

        <!-- ═══ TAB 1: PELANGGARAN & REWARD ═══════════════════════════════════ -->
        <div class="tab-pane fade show active" id="pane-pelanggaran" role="tabpanel">

            <!-- Tren bulanan -->
            <div class="pro-chart-card">
                <div class="chart-title">
                    <span class="chart-icon-wrap" style="background:#fef3c7"><i class="fas fa-chart-line" style="color:#d97706"></i></span>
                    Tren Pelanggaran vs Reward
                </div>
                <div class="chart-subtitle">Pergerakan poin per bulan selama periode yang dipilih</div>
                <?php if ($has_tren_data): ?>
                    <div class="scroll-hint"><i class="fas fa-arrows-alt-h me-1"></i>Geser untuk melihat selengkapnya</div>
                    <div class="chart-scroll-outer">
                        <div class="chart-scroll-inner tall" style="min-width:<?= max(count($labels)*80, 480) ?>px">
                            <canvas id="trendChart"></canvas>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="empty-chart"><i class="fas fa-chart-line"></i><p>Belum ada data untuk ditampilkan.</p></div>
                <?php endif; ?>
            </div>

            <!-- Komposisi kategori -->
            <div class="pro-chart-card">
                <div class="chart-title">
                    <span class="chart-icon-wrap" style="background:#f3e8ff"><i class="fas fa-chart-pie" style="color:#7c3aed"></i></span>
                    Komposisi Kategori
                </div>
                <div class="chart-subtitle">Proporsi poin pelanggaran &amp; reward berdasarkan kategori</div>
                <?php if ($has_comp_data): ?>
                    <div class="chart-scroll-inner" style="height:280px;max-width:340px;margin:0 auto">
                        <canvas id="compositionChart"></canvas>
                    </div>
                <?php else: ?>
                    <div class="empty-chart"><i class="fas fa-chart-pie"></i><p>Belum ada data pelanggaran/reward pada periode ini.</p></div>
                <?php endif; ?>
            </div>

        </div>

        <!-- ═══ TAB 2: KARAKTER ═════════════════════════════════════════ -->
        <div class="tab-pane fade" id="pane-karakter" role="tabpanel">

            <?php if (!$has_rapot): ?>
                <div class="no-rapot-state">
                    <div class="icon-wrap"><i class="fas fa-file-alt"></i></div>
                    <h5 class="fw-bold text-dark mb-2">Belum Ada Data Rapot</h5>
                    <p class="text-muted mb-4" style="max-width:420px;margin:0 auto">
                        Santri ini belum memiliki rapot kepengasuhan. Buat rapot terlebih dahulu di menu <strong>Rapot</strong>.
                    </p>
                    <a href="../rapot/index.php" class="btn btn-primary rounded-pill px-4">
                        <i class="fas fa-external-link-alt me-2"></i>Pergi ke Halaman Rapot
                    </a>
                </div>
            <?php else: ?>

                <!-- Stat cards -->
                <div class="row g-3 mb-4">
                    <?php
                    $stats = [
                        ['ibadah',   'fa-mosque',  $avg_ibadah,       '--ibadah',  'Ibadah'],
                        ['akhlaq',   'fa-heart',   $avg_akhlaq,       '--akhlaq',  'Akhlaq'],
                        ['disiplin', 'fa-clock',   $avg_kedisiplinan, '--disiplin','Kedisiplinan'],
                        ['bersih',   'fa-broom',   $avg_kebersihan,   '--bersih',  'Kebersihan'],
                    ];
                    foreach ($stats as [$key, $icon, $val, $color, $label]): ?>
                    <div class="col-6 col-md-3">
                        <div class="ks-card ks-<?= $key ?>">
                            <div class="ks-icon"><i class="fas <?= $icon ?>"></i></div>
                            <div>
                                <div class="ks-val" style="color:var(<?= $color ?>)"><?= $val ?><small style="font-size:.85rem;font-weight:600">/5</small></div>
                                <div class="ks-lbl"><?= $label ?></div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Info banner -->
                <div class="d-flex align-items-center gap-3 mb-4 px-3 py-2 rounded-3" style="background:#eef2ff;border:1px solid #c7d2fe;color:#3730a3;font-size:.82rem">
                    <i class="fas fa-info-circle"></i>
                    <span>Analisis berdasarkan <strong><?= $total_rapot ?> rapot</strong> kepengasuhan. Radar menampilkan rata-rata seluruh rapot.</span>
                </div>

                <div class="row g-4">
                    <!-- Radar chart -->
                    <div class="col-lg-5">
                        <div class="pro-chart-card">
                            <div class="chart-title">
                                <span class="chart-icon-wrap" style="background:#ede9fe"><i class="fas fa-spider" style="color:#7c3aed"></i></span>
                                Profil Karakter
                            </div>
                            <div class="chart-subtitle">Rata-rata nilai 20 aspek dari seluruh rapot (skala 1&ndash;5)</div>
                            <div class="scroll-hint"><i class="fas fa-arrows-alt-h me-1"></i>Geser untuk melihat selengkapnya</div>
                            <div class="chart-scroll-outer">
                                <div class="chart-scroll-inner radar" style="min-width:480px">
                                    <canvas id="radarChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tren karakter -->
                    <div class="col-lg-7">
                        <div class="pro-chart-card">
                            <div class="chart-title">
                                <span class="chart-icon-wrap" style="background:#d1fae5"><i class="fas fa-chart-line" style="color:#059669"></i></span>
                                Tren Karakter Per Bulan
                            </div>
                            <div class="chart-subtitle">Perkembangan rata-rata nilai per kategori dari bulan ke bulan</div>
                            <?php if (!$has_tren_karakter): ?>
                                <div class="empty-chart">
                                    <i class="fas fa-chart-line"></i>
                                    <p>Tren baru muncul saat santri memiliki minimal <strong>2 bulan</strong> rapot.<?php if (count($tren_karakter_labels)===1): ?> (Saat ini: <strong><?= htmlspecialchars($tren_karakter_labels[0]) ?></strong>)<?php endif; ?></p>
                                </div>
                            <?php else: ?>
                                <div class="scroll-hint"><i class="fas fa-arrows-alt-h me-1"></i>Geser untuk melihat selengkapnya</div>
                                <div class="chart-scroll-outer">
                                    <div class="chart-scroll-inner tall" style="min-width:<?= max(count($tren_karakter_labels)*90, 480) ?>px">
                                        <canvas id="trenKarakterChart"></canvas>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            <?php endif; ?>
        </div><!-- /pane-karakter -->

    </div><!-- /tab-content -->
</div>

<!-- Chart.js: defer agar tidak menghalangi render HTML -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js" defer></script>
<script defer>
// Semua chart diinit LAZY: Tab 1 init saat tab terlihat, Tab 2 saat diklik.
// Tidak ada chart yang render saat DOMContentLoaded pertama kali.

document.addEventListener('DOMContentLoaded', function() {

    // ── Shared defaults ──────────────────────────────────────────────────────
    const FONT = "'Poppins','Segoe UI',sans-serif";
    const TOOLTIP = {
        backgroundColor:'rgba(15,23,42,.92)', titleColor:'#f1f5f9', bodyColor:'#cbd5e1',
        padding:12, cornerRadius:10, titleFont:{weight:'700',size:13},
        bodyFont:{size:12}, displayColors:true, boxPadding:4
    };
    const GRID  = { color:'rgba(148,163,184,.12)', drawTicks:false };
    const TICKS = { padding:8, font:{size:11, family:FONT} };

    Chart.defaults.font.family = FONT;
    Chart.defaults.color       = '#64748b';

    // ── Gradients helper ─────────────────────────────────────────────────────
    function linGrad(ctx, h, r, g, b) {
        const gr = ctx.createLinearGradient(0, 0, 0, h);
        gr.addColorStop(0, `rgba(${r},${g},${b},.22)`);
        gr.addColorStop(1, `rgba(${r},${g},${b},0)`);
        return gr;
    }

    // ── State flags (prevent double init) ────────────────────────────────────
    let tab1Ready = false;
    let tab2Ready = false;

    // ═════════════════════════════════════════════════════════════════════════
    //  TAB 1 — INIT FUNCTION
    // ═════════════════════════════════════════════════════════════════════════
    function initTab1() {
        if (tab1Ready) return;
        tab1Ready = true;

        <?php if ($has_tren_data): ?>
        (function() {
            const el  = document.getElementById('trendChart');
            if (!el) return;
            const ctx = el.getContext('2d');
            const h   = el.parentElement.offsetHeight || 360;
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?= $json_labels_p ?>,
                    datasets: [
                        { label:'Poin Pelanggaran', data:<?= $json_data_p ?>,
                          borderColor:'#ef4444', backgroundColor:linGrad(ctx,h,239,68,68),
                          borderWidth:2.5, tension:.4, fill:true,
                          pointRadius:5, pointHoverRadius:8,
                          pointBackgroundColor:'#ef4444', pointBorderColor:'#fff', pointBorderWidth:2 },
                        { label:'Poin Reward', data:<?= $json_data_r ?>,
                          borderColor:'#10b981', backgroundColor:linGrad(ctx,h,16,185,129),
                          borderWidth:2.5, tension:.4, fill:true,
                          pointRadius:5, pointHoverRadius:8,
                          pointBackgroundColor:'#10b981', pointBorderColor:'#fff', pointBorderWidth:2 }
                    ]
                },
                options: {
                    responsive:true, maintainAspectRatio:false,
                    layout: { padding: { top: 15 } },
                    interaction:{ mode:'index', intersect:false },
                    plugins: {
                        legend:{ position:'top', align:'end', labels:{ usePointStyle:true, pointStyle:'circle', padding:18, font:{size:12,family:FONT} } },
                        tooltip:TOOLTIP
                    },
                    scales: {
                        y:{ beginAtZero:true, grid:GRID, ticks:{...TICKS, callback:v=>v+' poin'}, border:{display:false} },
                        x:{ grid:{display:false}, ticks:TICKS, border:{display:false} }
                    }
                }
            });

        })();
        <?php endif; ?>

        <?php if ($has_comp_data): ?>
        new Chart(document.getElementById('compositionChart'), {
            type: 'doughnut',
            data: {
                labels: <?= $json_comp_labels ?>,
                datasets:[{ data:<?= $json_comp_data ?>, backgroundColor:<?= $json_comp_colors ?>,
                            borderWidth:3, borderColor:'#fff', hoverBorderColor:'#fff', hoverOffset:6 }]
            },
            options: {
                responsive:true, maintainAspectRatio:false, cutout:'62%',
                plugins:{
                    legend:{ position:'bottom', labels:{ usePointStyle:true, pointStyle:'circle', padding:14, font:{size:11,family:FONT} } },
                    tooltip:TOOLTIP
                }
            }
        });
        <?php endif; ?>
    }

    <?php if ($has_rapot): ?>
    // ═════════════════════════════════════════════════════════════════════════
    //  TAB 2 — INIT FUNCTION (hanya jika ada rapot)
    // ═════════════════════════════════════════════════════════════════════════
    function initTab2() {
        if (tab2Ready) return;
        tab2Ready = true;

        // Radar
        const radarEl = document.getElementById('radarChart');
        if (radarEl) {
            new Chart(radarEl, {
                type: 'radar',
                data: {
                    labels: <?= $json_radar_labels ?>,
                    datasets:[{
                        label:'Rata-rata', data:<?= $json_radar_values ?>,
                        backgroundColor:'rgba(99,102,241,.18)', borderColor:'#6366f1', borderWidth:2.5,
                        pointBackgroundColor:'#6366f1', pointBorderColor:'#fff', pointBorderWidth:2,
                        pointRadius:5, pointHoverRadius:7
                    }]
                },
                options: {
                    responsive:true, maintainAspectRatio:false,
                    scales:{ r:{
                        min:0, max:5,
                        ticks:{ stepSize:1, font:{size:10}, color:'#94a3b8', backdropColor:'transparent', z:1 },
                        pointLabels:{ font:{size:11.5,weight:'600',family:FONT}, color:'#334155', padding:12 },
                        grid:{ color:'rgba(148,163,184,.18)' }, angleLines:{ color:'rgba(148,163,184,.25)' }
                    }},
                    plugins:{
                        legend:{ display:false },
                        tooltip:{ ...TOOLTIP, callbacks:{ title:c=>c[0].label, label:c=>'  Nilai rata-rata: '+c.raw+' / 5' } }
                    }
                }
            });
        }

        <?php if ($has_tren_karakter): ?>
        // Line tren karakter
        const trenEl = document.getElementById('trenKarakterChart');
        if (trenEl) {
            const ctx = trenEl.getContext('2d');
            const h   = trenEl.parentElement.offsetHeight || 360;
            new Chart(ctx, {
                type:'line',
                data:{
                    labels: <?= $json_tren_k_labels ?>,
                    datasets:[
                        { label:'Ibadah',       data:<?= $json_tren_ibadah ?>,   borderColor:'#10b981', backgroundColor:linGrad(ctx,h,16,185,129),  borderWidth:2.5, tension:.4, fill:true, pointRadius:5, pointHoverRadius:8, pointBackgroundColor:'#10b981', pointBorderColor:'#fff', pointBorderWidth:2 },
                        { label:'Akhlaq',       data:<?= $json_tren_akhlaq ?>,   borderColor:'#6366f1', backgroundColor:linGrad(ctx,h,99,102,241),  borderWidth:2.5, tension:.4, fill:true, pointRadius:5, pointHoverRadius:8, pointBackgroundColor:'#6366f1', pointBorderColor:'#fff', pointBorderWidth:2 },
                        { label:'Kedisiplinan', data:<?= $json_tren_disiplin ?>, borderColor:'#f59e0b', backgroundColor:linGrad(ctx,h,245,158,11),  borderWidth:2.5, tension:.4, fill:true, pointRadius:5, pointHoverRadius:8, pointBackgroundColor:'#f59e0b', pointBorderColor:'#fff', pointBorderWidth:2 },
                        { label:'Kebersihan',   data:<?= $json_tren_bersih ?>,   borderColor:'#3b82f6', backgroundColor:linGrad(ctx,h,59,130,246),  borderWidth:2.5, tension:.4, fill:true, pointRadius:5, pointHoverRadius:8, pointBackgroundColor:'#3b82f6', pointBorderColor:'#fff', pointBorderWidth:2 }
                    ]
                },
                options:{
                    clip: false,
                    responsive:true, maintainAspectRatio:false,
                    layout: { padding: { top: 15 } },
                    interaction:{ mode:'index', intersect:false },
                    scales:{
                        y:{ min:0, max:5, ticks:{...TICKS, stepSize:1, callback:v=>v+'/5'}, grid:GRID, border:{display:false} },
                        x:{ grid:{display:false}, ticks:TICKS, border:{display:false} }
                    },
                    plugins:{
                        legend:{ position:'top', align:'end', labels:{ usePointStyle:true, pointStyle:'circle', padding:16, font:{size:12,family:FONT} } },
                        tooltip:{ ...TOOLTIP, callbacks:{ label:c=>'  '+c.dataset.label+': '+c.raw+' / 5' } }
                    }
                }
            });

        }
        <?php endif; ?>
    }
    <?php endif; ?>

    // ── Intersection Observer: Tab 1 lazy-init saat card masuk viewport ──────
    // Ini memastikan chart Tab 1 tidak render sebelum user scroll ke sana
    const pane1 = document.getElementById('pane-pelanggaran');
    if (pane1 && 'IntersectionObserver' in window) {
        const obs = new IntersectionObserver(entries => {
            if (entries[0].isIntersecting) { initTab1(); obs.disconnect(); }
        }, { threshold: 0.05 });
        obs.observe(pane1);
    } else {
        // Fallback: langsung init jika IO tidak tersedia
        initTab1();
    }

    // ── Tab click: init Tab 2 lazy saat dibuka ───────────────────────────────
    <?php if ($has_rapot): ?>
    document.getElementById('tab-karakter')
        ?.addEventListener('shown.bs.tab', initTab2);
    <?php endif; ?>

    // ── Tab 1: reinit jika user kembali ke Tab 1 setelah Tab 2 (sudah ready) ─
    document.getElementById('tab-pelanggaran')
        ?.addEventListener('shown.bs.tab', initTab1);
});
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
