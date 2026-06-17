<?php
// 1. Panggil 'Otak' aplikasi
require_once __DIR__ . '/../../bootstrap/init.php';

// 2. Jalankan 'SATPAM'
guard('arsip_view');

// 3. Ambil Parameter
$arsip_id = (int)($_GET['arsip_id'] ?? 0);
$santri_id = (int)($_GET['id'] ?? 0);

if ($arsip_id <= 0 || $santri_id <= 0) {
    $_SESSION['flash_error'] = "Data arsip atau santri tidak valid.";
    header("Location: arsip_per_santri.php?id=" . $arsip_id);
    exit;
}

$stmt_arsip = $conn->prepare("SELECT * FROM arsip WHERE id = ?");
$stmt_arsip->bind_param('i', $arsip_id);
$stmt_arsip->execute();
$arsip = $stmt_arsip->get_result()->fetch_assoc();
if (!$arsip) {
    $_SESSION['flash_error'] = "Arsip tidak ditemukan.";
    header("Location: ../view.php");
    exit;
}

$filter_bagian = $_GET['bagian'] ?? '';
$filter_kategori = $_GET['kategori'] ?? '';
$filter_jp = $_GET['jenis_pelanggaran'] ?? '';
$filter_tipe = $_GET['tipe'] ?? '';
$filter_kelas = $_GET['kelas'] ?? '';
$filter_kamar = $_GET['kamar'] ?? '';
$filter_sort_order = $_GET['sort_order'] ?? '';
$filter_formula = $_GET['formula'] ?? '';

$filter_qs = "&arsip_id=" . $arsip_id . "&id=" . $santri_id;
if (!empty($filter_bagian)) $filter_qs .= "&bagian=" . urlencode($filter_bagian);
if (!empty($filter_jp)) $filter_qs .= "&jenis_pelanggaran=" . urlencode($filter_jp);

$back_qs = "";
if (!empty($filter_tipe)) $back_qs .= "&tipe=" . urlencode($filter_tipe);
if (!empty($filter_kelas)) $back_qs .= "&kelas=" . urlencode($filter_kelas);
if (!empty($filter_kamar)) $back_qs .= "&kamar=" . urlencode($filter_kamar);
if (!empty($filter_bagian)) $back_qs .= "&bagian=" . urlencode($filter_bagian);
if (!empty($filter_jp)) $back_qs .= "&jenis_pelanggaran=" . urlencode($filter_jp);
if (!empty($filter_sort_order)) $back_qs .= "&sort_order=" . urlencode($filter_sort_order);
if (!empty($filter_formula)) $back_qs .= "&formula=" . urlencode($filter_formula);

// ─── 4. Info Santri ───────────────────────────────────────────────────────────
$stmt_santri = $conn->prepare("SELECT santri_id AS id, santri_nama AS nama, santri_kelas AS kelas, santri_kamar AS kamar, total_poin_saat_arsip AS poin_aktif FROM arsip_data_santri WHERE arsip_id = ? AND santri_id = ?");
$stmt_santri->bind_param("ii", $arsip_id, $santri_id);
$stmt_santri->execute();
$santri = $stmt_santri->get_result()->fetch_assoc();
if (!$santri) { die("Data santri tidak ditemukan di arsip ini."); }

// ─── 3.5 Build Filter Query ───────────────────────────────────────────────────
$where_p = "arsip_id = ? AND santri_id = ? AND tipe = 'Umum'";
$params_p = [$arsip_id, $santri_id];
$types_p = "ii";

if ($filter_bagian) {
    $where_p .= " AND bagian = ?";
    $params_p[] = $filter_bagian;
    $types_p .= "s";
}
if ($filter_jp) {
    $where_p .= " AND jenis_pelanggaran_id = ?";
    $params_p[] = $filter_jp;
    $types_p .= "i";
}

// ─── 5. Pelanggaran per bagian ──────────────────────────────────────────────
$stmt_pk = $conn->prepare(
    "SELECT bagian AS kategori, COUNT(id) as jumlah, SUM(poin) as total_poin
     FROM arsip_data_pelanggaran
     WHERE $where_p
     GROUP BY bagian"
);
$stmt_pk->bind_param($types_p, ...$params_p);
$stmt_pk->execute();
$res_pk = $stmt_pk->get_result();
$pelanggaran_kategori  = [];
$total_poin_pelanggaran = 0;
$total_kasus = 0;
while ($row = $res_pk->fetch_assoc()) {
    $pelanggaran_kategori[] = $row;
    $total_poin_pelanggaran += $row['total_poin'];
    $total_kasus += $row['jumlah'];
}

// ─── 6. Reward ringkasan ──────────────────────────────────────────────────────
$stmt_rk = $conn->prepare(
    "SELECT COUNT(id) as jumlah, SUM(poin_reward) as total_poin
     FROM arsip_data_reward
     WHERE arsip_id = ? AND santri_id = ?"
);
$stmt_rk->bind_param("ii", $arsip_id, $santri_id);
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

// ─── 7. Tren bulanan — Dinamis untuk Pengabdian ─────────────────────────────────
if ($filter_bagian === 'Pengabdian') {
    $sql_tren = "SELECT 'p' AS src, DATE_FORMAT(tanggal,'%Y-%m') AS bulan, COUNT(id) AS poin
         FROM arsip_data_pelanggaran
         WHERE $where_p
         GROUP BY bulan
         ORDER BY bulan ASC";
    $stmt_tren = $conn->prepare($sql_tren);
    $stmt_tren->bind_param($types_p, ...$params_p);
} else {
    $sql_tren = "SELECT 'p' AS src, DATE_FORMAT(tanggal,'%Y-%m') AS bulan, SUM(poin) AS poin
         FROM arsip_data_pelanggaran
         WHERE $where_p
         GROUP BY bulan
         UNION ALL
         SELECT 'r' AS src, DATE_FORMAT(tanggal,'%Y-%m') AS bulan, SUM(poin_reward) AS poin
         FROM arsip_data_reward
         WHERE arsip_id = ? AND santri_id = ?
         GROUP BY bulan
         ORDER BY bulan ASC";
    $stmt_tren = $conn->prepare($sql_tren);
    $params_tren = array_merge($params_p, [$arsip_id, $santri_id]);
    $types_tren = $types_p . "ii";
    $stmt_tren->bind_param($types_tren, ...$params_tren);
}
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
if ($filter_bagian === 'Pengabdian') {
    if ($total_kasus == 0) {
        $rasio = "Sangat Disiplin. Tidak ada catatan keterlambatan sama sekali pada periode ini.";
        $karakter_class = "text-success"; $karakter_icon = "fa-star";
    } elseif ($total_kasus <= 3) {
        $rasio = "Cukup Disiplin. Hanya ada sedikit insiden keterlambatan yang wajar.";
        $karakter_class = "text-primary"; $karakter_icon = "fa-thumbs-up";
    } elseif ($total_kasus <= 10) {
        $rasio = "Perlu Peningkatan Kedisiplinan. Frekuensi keterlambatan cukup sering terjadi.";
        $karakter_class = "text-warning"; $karakter_icon = "fa-exclamation-circle";
    } else {
        $rasio = "Darurat Kedisiplinan! Santri ini sangat sering terlambat dan butuh evaluasi khusus.";
        $karakter_class = "text-danger";  $karakter_icon = "fa-exclamation-triangle";
    }
} else {
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
}

// ─── 9. Status peringatan SP ──────────────────────────────────────────────────
$poin_bersih_val = $total_poin_pelanggaran - $total_poin_reward;

$sp_status = $sp_class = '';
if ($poin_bersih_val >= 300)      { $sp_status = "PERINGATAN 3"; $sp_class = "bg-danger text-white"; }
elseif ($poin_bersih_val >= 200)  { $sp_status = "PERINGATAN 2"; $sp_class = "bg-warning text-dark"; }
elseif ($poin_bersih_val >= 100)  { $sp_status = "PERINGATAN 1"; $sp_class = "bg-info text-white"; }

// ─── 10. Rapot: radar (1 query saja, cukup untuk radar + stat cards) ──────────
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
     FROM arsip_data_rapot 
     WHERE arsip_id = ? AND santri_id = ?"
);
$stmt_radar->bind_param("ii", $arsip_id, $santri_id);
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
         FROM arsip_data_rapot
         WHERE arsip_id = ? AND santri_id = ?
         ORDER BY tahun ASC, $bulan_order ASC"
    );
    $stmt_tren_k->bind_param("ii", $arsip_id, $santri_id);
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
$rata_rapot = 0;
if ($total_rapot > 0) {
    $rata_rapot = array_sum($radar_values) / 20;
}
$skor_teladan = ($rata_rapot * 20) + $total_poin_reward - ($total_poin_pelanggaran * 2) - ($total_kasus * 5);
$skor_teladan_str = ($total_rapot > 0) ? number_format($skor_teladan, 1, '.', '') : '-';

$poin_bersih = $poin_bersih_val < 0 ? 0 : $poin_bersih_val;

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

// ─── 11.6. Tren Keterlambatan (Waktu Sholat) ──────────────────────────────────
$sql_keterlambatan = "
    SELECT
        CASE
            WHEN jenis_pelanggaran_id = 2 THEN 'Telat KBM'
            WHEN TIME(tanggal) BETWEEN '03:30:00' AND '05:30:00' THEN 'Subuh'
            WHEN TIME(tanggal) BETWEEN '11:30:00' AND '13:00:00' THEN 'Dzuhur'
            WHEN TIME(tanggal) BETWEEN '14:45:00' AND '16:00:00' THEN 'Ashar'
            WHEN TIME(tanggal) BETWEEN '17:30:00' AND '18:45:00' THEN 'Maghrib'
            WHEN TIME(tanggal) BETWEEN '18:50:00' AND '20:30:00' THEN 'Isya'
            ELSE 'Lainnya'
        END AS kategori,
        COUNT(*) AS jumlah
    FROM arsip_data_pelanggaran
    WHERE $where_p AND jenis_pelanggaran_id IN (1, 2) 
    GROUP BY CASE
            WHEN jenis_pelanggaran_id = 2 THEN 'Telat KBM'
            WHEN TIME(tanggal) BETWEEN '03:30:00' AND '05:30:00' THEN 'Subuh'
            WHEN TIME(tanggal) BETWEEN '11:30:00' AND '13:00:00' THEN 'Dzuhur'
            WHEN TIME(tanggal) BETWEEN '14:45:00' AND '16:00:00' THEN 'Ashar'
            WHEN TIME(tanggal) BETWEEN '17:30:00' AND '18:45:00' THEN 'Maghrib'
            WHEN TIME(tanggal) BETWEEN '18:50:00' AND '20:30:00' THEN 'Isya'
            ELSE 'Lainnya'
        END
    ORDER BY jumlah DESC
";
$stmt_telat = $conn->prepare($sql_keterlambatan);
$stmt_telat->bind_param($types_p, ...$params_p);
$stmt_telat->execute();
$res_telat = $stmt_telat->get_result();

$telat_kategori_labels = [];
$telat_kategori_data = [];
$total_telat = 0;
$total_telat_kbm = 0;
$total_telat_sholat = 0;
while ($row = $res_telat->fetch_assoc()) {
    $telat_kategori_labels[] = $row['kategori'];
    $telat_kategori_data[] = $row['jumlah'];
    $total_telat += $row['jumlah'];
    if ($row['kategori'] === 'Telat KBM') {
        $total_telat_kbm += $row['jumlah'];
    } elseif ($row['kategori'] !== 'Lainnya') {
        $total_telat_sholat += $row['jumlah'];
    }
}
$json_telat_labels = json_encode($telat_kategori_labels);
$json_telat_data = json_encode($telat_kategori_data);

// ─── Komposisi chart data (hitung PHP, bukan duplikat di JS) ─────────────────
$comp_labels = $comp_data = $comp_colors = [];
$colorMap = ['Sangat Berat'=>'#b91c1c','Berat'=>'#ef4444','Sedang'=>'#f59e0b','Ringan'=>'#fcd34d','Reward'=>'#10b981'];
$palette = ['#3b82f6', '#8b5cf6', '#14b8a6', '#f97316', '#ec4899', '#6366f1', '#84cc16', '#06b6d4'];
$paletteIdx = 0;

// Assign warna dinamis untuk bagian/kategori yang tidak ada di colorMap (seperti Pengabdian, Diniyyah, dsb)
foreach ($pelanggaran_kategori as $pk) {
    if (!isset($colorMap[$pk['kategori']])) {
        $colorMap[$pk['kategori']] = $palette[$paletteIdx % count($palette)];
        $paletteIdx++;
    }
}

foreach ($pelanggaran_kategori as $pk) {
    $comp_labels[] = 'Pelanggaran ' . $pk['kategori'];
    $comp_data[]   = $pk['total_poin'];
    $comp_colors[] = $colorMap[$pk['kategori']];
}
foreach ($reward_kategori as $rk) {
    $comp_labels[] = 'Reward';
    $comp_data[]   = $rk['total_poin'];
    $comp_colors[] = '#10b981';
}
$json_comp_labels = json_encode($comp_labels);
$json_comp_data   = json_encode($comp_data);
$json_comp_colors = json_encode($comp_colors);

// Data Frekuensi Pelanggaran (Jumlah Kasus)
$freq_labels = $freq_data = $freq_colors = [];
foreach ($pelanggaran_kategori as $pk) {
    $freq_labels[] = 'Pelanggaran ' . $pk['kategori'];
    $freq_data[]   = $pk['jumlah'];
    $freq_colors[] = $colorMap[$pk['kategori']];
}
foreach ($reward_kategori as $rk) {
    $freq_labels[] = 'Reward';
    $freq_data[]   = $rk['jumlah'];
    $freq_colors[] = '#10b981';
}
$json_freq_labels = json_encode($freq_labels);
$json_freq_data   = json_encode($freq_data);
$json_freq_colors = json_encode($freq_colors);

// Flag untuk JS (kurangi logika kondisional inline di JS)
$has_tren_data    = !empty($labels);
$has_comp_data    = !empty($comp_data);
$has_rapot        = $total_rapot > 0;
$has_tren_karakter = count($tren_karakter_labels) >= 2;

require_once __DIR__ . '/../../layouts/header.php';
?>
<style>
:root{--ibadah:#10b981;--akhlaq:#6366f1;--disiplin:#f59e0b;--bersih:#3b82f6;--cr:16px}

/* Info Cards */
.info-card{background:#fff;border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.06);padding:1.4rem 1.5rem;height:100%;border-top:4px solid var(--primary)}
.info-card.danger{border-top-color:#ef4444}.info-card.success{border-top-color:#10b981}
.big-number{font-size:2.4rem;font-weight:800;line-height:1;margin-top:.5rem;letter-spacing:-1px}

/* Hero */
.character-summary {
    background: #ffffff;
    border-radius: 24px;
    padding: 2.5rem 2rem;
    text-align: center;
    margin-bottom: 2rem;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
}
.character-icon-wrapper {
    width: 84px;
    height: 84px;
    background: #ffffff;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.06);
    margin-bottom: 1.2rem;
    border: 4px solid #ffffff;
    margin-left: auto;
    margin-right: auto;
}
.character-icon { font-size: 2.5rem; }
.character-icon-wrapper.text-danger { color: #ef4444 !important; }
.character-icon-wrapper.text-success { color: #10b981 !important; }
.character-icon-wrapper.text-primary { color: #3b82f6 !important; }
.character-icon-wrapper.text-warning { color: #f59e0b !important; }
.character-icon-wrapper.text-info { color: #0ea5e9 !important; }
.character-icon-wrapper.text-secondary { color: #64748b !important; }

.summary-conclusion-box {
    background: rgba(248, 250, 252, 0.8);
    border-radius: 16px;
    padding: 1.25rem 1.5rem;
    max-width: 650px;
    margin: 0 auto;
    border: 1px solid rgba(226, 232, 240, 0.8);
}
.summary-conclusion-box.text-danger { background: rgba(254, 226, 226, 0.5); border-color: rgba(252, 165, 165, 0.6); color: #b91c1c !important; }
.summary-conclusion-box.text-success { background: rgba(209, 250, 229, 0.5); border-color: rgba(110, 231, 183, 0.6); color: #047857 !important; }
.summary-conclusion-box.text-primary { background: rgba(219, 234, 254, 0.5); border-color: rgba(147, 197, 253, 0.6); color: #1d4ed8 !important; }
.summary-conclusion-box.text-warning { background: rgba(254, 243, 199, 0.5); border-color: rgba(252, 211, 77, 0.6); color: #b45309 !important; }
.summary-conclusion-box.text-info { background: rgba(224, 242, 254, 0.5); border-color: rgba(125, 211, 252, 0.6); color: #0369a1 !important; }
.summary-conclusion-box.text-secondary { background: rgba(241, 245, 249, 0.5); border-color: rgba(203, 213, 225, 0.6); color: #334155 !important; }

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
    .character-summary{padding:1.5rem 1rem;border-radius:20px}
    .character-icon-wrapper{width: 68px; height: 68px; margin-bottom: 1rem;}
    .character-icon{font-size:2rem;}
    .character-summary h2{font-size:1.5rem !important;}
    .summary-conclusion-box{padding: 1rem; font-size: 0.9rem;}
    .analysis-tabs .nav-link{padding:.65rem .9rem;font-size:.82rem}
}
</style>

<div class="container py-4" style="overflow-x:hidden">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="page-title m-0"><i class="fas fa-chart-pie me-2"></i>Analisis Santri</h1>
        <a href="arsip_per_santri.php?id=<?= $arsip_id ?><?= $back_qs ?? '' ?>" class="btn btn-outline-secondary rounded-pill shadow-sm">
            <i class="fas fa-arrow-left me-1"></i> Kembali
        </a>
    </div>

    <!-- Hero summary -->
    <div class="profile-hero p-3 p-md-4 mb-4 bg-white border shadow-sm" style="border-radius: 16px; position: relative;">
        <div class="d-flex flex-column align-items-center mb-3">
            <!-- Icon Avatar -->
            <div class="character-icon-wrapper <?= $karakter_class ?> bg-light mb-3" style="width: 75px; height: 75px; border-radius: 18px; display: flex; align-items: center; justify-content: center; border: 1px solid #f1f5f9; margin: 0 !important;">
                <i class="fas <?= $karakter_icon ?> character-icon" style="font-size: 2.2rem; margin: 0;"></i>
            </div>
            
            <!-- Info -->
            <div class="text-center">
                <h2 class="fw-bold mb-1 text-dark" style="letter-spacing: -0.5px; font-size: 1.6rem;"><?= htmlspecialchars($santri['nama']) ?></h2>
                <div class="text-secondary" style="font-size: 0.85rem; font-weight: 500;">
                    <i class="fas fa-user-graduate me-1 opacity-50"></i> Kls <?= htmlspecialchars($santri['kelas']) ?> &nbsp;<span class="text-muted mx-1">•</span>&nbsp;
                    <i class="fas fa-bed me-1 opacity-50"></i> Kmr <?= htmlspecialchars($santri['kamar']) ?>
                </div>
            </div>
        </div>
        
        <div class="d-flex flex-wrap justify-content-center gap-2 mb-3">
            <!-- Date -->
            <div class="badge bg-light text-secondary border d-flex align-items-center px-2 py-1 fw-medium" style="font-size: 0.75rem; border-radius: 6px;">
                <i class="far fa-calendar-alt me-1 opacity-50"></i> 
                <?= date('d M y', strtotime($arsip['tanggal_mulai'])) ?> - <?= date('d M y', strtotime($arsip['tanggal_selesai'])) ?>
            </div>

            <!-- Filter -->
            <?php if ($filter_bagian || $filter_kategori || $filter_jp): ?>
                <?php
                    $f_arr = [];
                    if ($filter_bagian) $f_arr[] = htmlspecialchars($filter_bagian);
                    if ($filter_kategori) $f_arr[] = htmlspecialchars($filter_kategori);
                ?>
                <div class="badge bg-light text-secondary border d-flex align-items-center px-2 py-1 fw-medium" style="font-size: 0.75rem; border-radius: 6px;">
                    <i class="fas fa-filter me-1 opacity-50"></i> <?= implode(", ", $f_arr) ?>
                </div>
            <?php endif; ?>

            <!-- History/Surplus -->
            <?php if ($filter_bagian === 'Pengabdian'): ?>
                <?php if ($total_kasus > 0): ?>
                    <div class="badge bg-light text-secondary border d-flex align-items-center px-2 py-1 fw-medium" style="font-size: 0.75rem; border-radius: 6px;" title="Total keterlambatan">
                        <i class="fas fa-clock me-1 text-warning opacity-75"></i> Total Telat: <?= $total_kasus ?>x
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <?php if ($santri['poin_aktif'] > 0): ?>
                    <div class="badge bg-light text-secondary border d-flex align-items-center px-2 py-1 fw-medium" style="font-size: 0.75rem; border-radius: 6px;" title="Total poin seumur hidup">
                        <i class="fas fa-history me-1 text-danger opacity-75"></i> Histori: <?= $santri['poin_aktif'] ?>
                    </div>
                <?php elseif ($santri['poin_aktif'] < 0): ?>
                    <div class="badge bg-light text-secondary border d-flex align-items-center px-2 py-1 fw-medium" style="font-size: 0.75rem; border-radius: 6px;" title="Surplus poin seumur hidup">
                        <i class="fas fa-star me-1 text-success opacity-75"></i> Surplus: <?= abs($santri['poin_aktif']) ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- SP Status -->
            <?php if ($filter_bagian !== 'Pengabdian' && $sp_status): ?>
                <div class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 d-flex align-items-center px-2 py-1 fw-bold" style="font-size: 0.75rem; border-radius: 6px;">
                    <i class="fas fa-exclamation-triangle me-1"></i> <?= $sp_status ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Conclusion -->
        <div class="summary-conclusion-box <?= $karakter_class ?> bg-opacity-10 p-2 px-3 border rounded-3 text-dark text-center" style="border-color: currentColor !important; opacity: 0.85;">
            <p class="mb-0 fw-medium" style="font-size: 0.82rem; line-height: 1.4;"><i class="fas fa-quote-left me-1 opacity-50"></i> <?= $rasio ?></p>
        </div>
    </div>

    <!-- Info banner Sinkronisasi -->
    <div style="margin-bottom: 25px; padding: 12px 18px; border-radius: 10px; font-size: 13.5px; display: flex; align-items: flex-start; gap: 12px; background-color: #eff6ff; color: #1e3a8a; border: 1px solid #bfdbfe;">
        <i class="fas fa-link" style="font-size: 18px; margin-top: 2px;"></i>
        <div>
            <strong>Info Sinkronisasi:</strong> Halaman detail ini menyajikan data terpadu yang tersinkronisasi langsung dengan perhitungan di menu utama Rekap Per Santri. <strong>Skor Teladan</strong> dan <strong>Poin Bersih</strong> menjadi penentu utama pada fitur Peringkat, sedangkan poin <strong>Pelanggaran</strong> menjadi acuan di Daftar Hitam.
        </div>
    </div>

    <!-- Info cards poin -->
    <div class="row g-3 mb-4">
        <?php if ($filter_bagian === 'Pengabdian'): ?>
            <div class="col-6 col-lg-3">
                <div class="info-card" style="border-top-color:#3b82f6; padding: 1.1rem;">
                    <div class="text-muted text-uppercase fw-bold" style="letter-spacing:0.5px;font-size:.7rem">Skor Teladan</div>
                    <div class="big-number text-primary mt-1" style="font-size: 2rem;"><?= $skor_teladan_str ?></div>
                    <div class="text-muted small mt-1" style="font-size: 0.7rem; line-height: 1.1;">Formula Semua Aspek</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <a href="detail_pelanggaran_arsip.php?id=<?= $arsip_id ?>&santri_id=<?= $santri_id ?><?= $back_qs ?>" class="text-decoration-none" style="display: block; height: 100%;">
                    <div class="info-card danger" style="padding: 1.1rem; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 6px 20px rgba(0,0,0,0.1)';" onmouseout="this.style.transform='none'; this.style.boxShadow='0 2px 12px rgba(0,0,0,.06)';">
                        <div class="text-muted text-uppercase fw-bold" style="letter-spacing:0.5px;font-size:.7rem">Total Telat</div>
                        <div class="big-number text-danger mt-1" style="font-size: 2rem;">
                            <?= $total_kasus ?> <span style="font-size: 1.2rem; font-weight: 600;">x</span>
                        </div>
                        <div class="text-muted small mt-1" style="font-size: 0.7rem; line-height: 1.1;">Semua Keterlambatan</div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-lg-3">
                <div class="info-card warning" style="border-top-color:#f59e0b; padding: 1.1rem;">
                    <div class="text-muted text-uppercase fw-bold" style="letter-spacing:0.5px;font-size:.7rem">Telat Sholat</div>
                    <div class="big-number text-warning mt-1" style="font-size: 2rem;">
                        <?= $total_telat_sholat ?> <span style="font-size: 1.2rem; font-weight: 600;">x</span>
                    </div>
                    <div class="text-muted small mt-1" style="font-size: 0.7rem; line-height: 1.1;">Jamaah 5 Waktu</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="info-card info" style="border-top-color:#0ea5e9; padding: 1.1rem;">
                    <div class="text-muted text-uppercase fw-bold" style="letter-spacing:0.5px;font-size:.7rem">Telat KBM</div>
                    <div class="big-number text-info mt-1" style="font-size: 2rem;">
                        <?= $total_telat_kbm ?> <span style="font-size: 1.2rem; font-weight: 600;">x</span>
                    </div>
                    <div class="text-muted small mt-1" style="font-size: 0.7rem; line-height: 1.1;">Kegiatan Belajar</div>
                </div>
            </div>
        <?php else: ?>
            <div class="col-6 col-lg-3">
                <div class="info-card" style="border-top-color:#3b82f6; padding: 1.1rem;">
                    <div class="text-muted text-uppercase fw-bold" style="letter-spacing:0.5px;font-size:.7rem">Skor Teladan</div>
                    <div class="big-number text-primary mt-1" style="font-size: 2rem;"><?= $skor_teladan_str ?></div>
                    <div class="text-muted small mt-1" style="font-size: 0.7rem; line-height: 1.1;">Formula Semua Aspek</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="info-card" style="border-top-color:#0ea5e9; padding: 1.1rem;">
                    <div class="text-muted text-uppercase fw-bold" style="letter-spacing:0.5px;font-size:.7rem">Poin Bersih</div>
                    <div class="big-number mt-1 <?= $poin_bersih > 0 ? 'text-danger' : 'text-success' ?>" style="font-size: 2rem;"><?= $poin_bersih ?></div>
                    <div class="text-muted small mt-1" style="font-size: 0.7rem; line-height: 1.1;">Formula Neraca</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <a href="detail_pelanggaran_arsip.php?id=<?= $arsip_id ?>&santri_id=<?= $santri_id ?><?= $back_qs ?>" class="text-decoration-none" style="display: block; height: 100%;">
                    <div class="info-card danger" style="padding: 1.1rem; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 6px 20px rgba(0,0,0,0.1)';" onmouseout="this.style.transform='none'; this.style.boxShadow='0 2px 12px rgba(0,0,0,.06)';">
                        <div class="text-muted text-uppercase fw-bold" style="letter-spacing:0.5px;font-size:.7rem">Pelanggaran</div>
                        <div class="big-number text-danger mt-1" style="font-size: 2rem;">
                            <?= $total_poin_pelanggaran ?> 
                            <span style="font-size: 0.9rem; font-weight: 500; color: #94a3b8;">(<?= $total_kasus ?>x)</span>
                        </div>
                        <div class="text-muted small mt-1" style="font-size: 0.7rem; line-height: 1.1;">Acuan: Daftar Hitam</div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-lg-3">
                <a href="detail_reward_arsip.php?id=<?= $arsip_id ?>&santri_id=<?= $santri_id ?><?= $back_qs ?>" class="text-decoration-none" style="display: block; height: 100%;">
                    <div class="info-card success" style="padding: 1.1rem; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 6px 20px rgba(0,0,0,0.1)';" onmouseout="this.style.transform='none'; this.style.boxShadow='0 2px 12px rgba(0,0,0,.06)';">
                        <div class="text-muted text-uppercase fw-bold" style="letter-spacing:0.5px;font-size:.7rem">Total Reward</div>
                        <div class="big-number text-success mt-1" style="font-size: 2rem;">+<?= $total_poin_reward ?></div>
                        <div class="text-muted small mt-1" style="font-size: 0.7rem; line-height: 1.1;">Apresiasi periode ini</div>
                    </div>
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Tab navigation -->
    <ul class="nav analysis-tabs" id="analysisTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tab-pelanggaran" data-bs-toggle="tab" data-bs-target="#pane-pelanggaran" type="button" role="tab">
                <i class="fas <?= $filter_bagian === 'Pengabdian' ? 'fa-clock' : 'fa-exclamation-triangle' ?>"></i> 
                <?= $filter_bagian === 'Pengabdian' ? 'Keterlambatan' : 'Pelanggaran &amp; Reward' ?>
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
                    <span class="chart-icon-wrap" style="background:#fef3c7"><i class="fas <?= $filter_bagian === 'Pengabdian' ? 'fa-clock' : 'fa-chart-line' ?>" style="color:#d97706"></i></span>
                    <?= $filter_bagian === 'Pengabdian' ? 'Tren Keterlambatan per Bulan' : 'Tren Pelanggaran vs Reward' ?>
                </div>
                <div class="chart-subtitle"><?= $filter_bagian === 'Pengabdian' ? 'Pergerakan jumlah kasus keterlambatan selama periode yang dipilih' : 'Pergerakan poin per bulan selama periode yang dipilih' ?></div>
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

            <!-- Barisan Grafik Bawah (Kondisional) -->
            <?php if ($filter_bagian === 'Pengabdian'): ?>
                <?php if ($total_telat > 0): ?>
                <div class="row g-4 mt-2">
                    <!-- Komposisi Waktu Telat (Pie) -->
                    <div class="col-lg-5">
                        <div class="pro-chart-card h-100 mb-0">
                            <div class="chart-title">
                                <span class="chart-icon-wrap" style="background:#f3e8ff"><i class="fas fa-chart-pie" style="color:#7c3aed"></i></span>
                                Komposisi Waktu Telat
                            </div>
                            <div class="chart-subtitle">Proporsi kasus keterlambatan berdasarkan waktu</div>
                            <div class="chart-scroll-inner" style="height:280px;max-width:340px;margin:0 auto">
                                <canvas id="compositionChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <!-- Analisis Waktu Keterlambatan (Bar) -->
                    <div class="col-lg-7">
                        <div class="pro-chart-card h-100 mb-0" style="border-top: 4px solid #f97316;">
                            <div class="chart-title">
                                <span class="chart-icon-wrap" style="background:#ffedd5"><i class="fas fa-clock" style="color:#f97316"></i></span>
                                Analisis Waktu Keterlambatan
                            </div>
                            <div class="chart-subtitle">Frekuensi kasus Telat Sholat berjamaah dan Telat KBM</div>
                            <div class="chart-scroll-outer">
                                <div class="chart-scroll-inner" style="height:280px;min-width:300px;">
                                    <canvas id="telatChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            <?php else: ?>
                <!-- Komposisi kategori -->
                <div class="row g-4 mt-2">
                    <div class="col-lg-6">
                        <div class="pro-chart-card h-100 mb-0">
                            <div class="chart-title">
                                <span class="chart-icon-wrap" style="background:#f3e8ff"><i class="fas fa-chart-pie" style="color:#7c3aed"></i></span>
                                Komposisi Poin
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
                    <div class="col-lg-6">
                        <div class="pro-chart-card h-100 mb-0">
                            <div class="chart-title">
                                <span class="chart-icon-wrap" style="background:#e0f2fe"><i class="fas fa-chart-pie" style="color:#0284c7"></i></span>
                                Frekuensi Pelanggaran
                            </div>
                            <div class="chart-subtitle">Proporsi berdasarkan jumlah (kali) santri melakukan pelanggaran</div>
                            <?php if ($has_comp_data): ?>
                                <div class="chart-scroll-inner" style="height:280px;max-width:340px;margin:0 auto">
                                    <canvas id="freqChart"></canvas>
                                </div>
                            <?php else: ?>
                                <div class="empty-chart"><i class="fas fa-chart-pie"></i><p>Belum ada data pelanggaran/reward pada periode ini.</p></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if ($total_telat > 0): ?>
                <!-- Analisis Keterlambatan (Smart UI) -->
                <div class="row g-4 mt-1">
                    <div class="col-12">
                        <div class="pro-chart-card mb-0" style="border-top: 4px solid #f97316;">
                            <div class="chart-title">
                                <span class="chart-icon-wrap" style="background:#ffedd5"><i class="fas fa-clock" style="color:#f97316"></i></span>
                                Analisis Waktu Keterlambatan
                            </div>
                            <div class="chart-subtitle">Frekuensi kasus Telat Sholat berjamaah dan Telat KBM berdasarkan waktu</div>
                            <div class="chart-scroll-outer">
                                <div class="chart-scroll-inner" style="height:260px;min-width:300px;">
                                    <canvas id="telatChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>

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
                        <?php if ($filter_bagian === 'Pengabdian'): ?>
                        { label:'Jumlah Keterlambatan', data:<?= $json_data_p ?>,
                          borderColor:'#f97316', backgroundColor:linGrad(ctx,h,249,115,22),
                          borderWidth:2.5, tension:.4, fill:true,
                          pointRadius:5, pointHoverRadius:8,
                          pointBackgroundColor:'#f97316', pointBorderColor:'#fff', pointBorderWidth:2 }
                        <?php else: ?>
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
                        <?php endif; ?>
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
                        y:{ beginAtZero:true, grid:GRID, ticks:{...TICKS, callback:v=>v+'<?= $filter_bagian === 'Pengabdian' ? 'x' : ' poin' ?>'}, border:{display:false} },
                        x:{ grid:{display:false}, ticks:TICKS, border:{display:false} }
                    }
                }
            });

        })();
        <?php endif; ?>

        <?php if ($filter_bagian === 'Pengabdian'): ?>
            <?php if ($total_telat > 0): ?>
            (function() {
                const el = document.getElementById('telatChart');
                if (el) {
                    const ctx = el.getContext('2d');
                    new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: <?= $json_telat_labels ?>,
                            datasets: [{
                                label: 'Frekuensi Keterlambatan (Kali)',
                                data: <?= $json_telat_data ?>,
                                backgroundColor: '#f97316',
                                borderRadius: 6,
                                maxBarThickness: 60
                            }]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false },
                                tooltip: TOOLTIP
                            },
                            scales: {
                                y: { beginAtZero: true, grid: GRID, ticks: { ...TICKS, stepSize: 1, callback: v=>v+'x' } },
                                x: { grid: { display: false }, ticks: TICKS }
                            }
                        }
                    });
                }

                const compEl = document.getElementById('compositionChart');
                if (compEl) {
                    new Chart(compEl, {
                        type: 'doughnut',
                        data: {
                            labels: <?= $json_telat_labels ?>,
                            datasets:[{ data:<?= $json_telat_data ?>, backgroundColor:['#f97316', '#fbbf24', '#f87171', '#c084fc', '#60a5fa', '#34d399', '#9ca3af'],
                                        borderWidth:3, borderColor:'#fff', hoverBorderColor:'#fff', hoverOffset:6 }]
                        },
                        options: {
                            responsive:true, maintainAspectRatio:false, cutout:'50%',
                            plugins:{
                                legend:{ position:'bottom', labels:{ usePointStyle:true, pointStyle:'circle', padding:14, font:{size:11,family:FONT} } },
                                tooltip:{...TOOLTIP, callbacks: { label: function(context) { return ' ' + context.label + ': ' + context.raw + ' Kali'; } } }
                            }
                        }
                    });
                }
            })();
            <?php endif; ?>
        <?php else: ?>
            <?php if ($total_telat > 0): ?>
            (function() {
                const el = document.getElementById('telatChart');
                if (!el) return;
                const ctx = el.getContext('2d');
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: <?= $json_telat_labels ?>,
                        datasets: [{
                            label: 'Frekuensi Keterlambatan (Kali)',
                            data: <?= $json_telat_data ?>,
                            backgroundColor: '#f97316',
                            borderRadius: 6,
                            maxBarThickness: 60
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: TOOLTIP
                        },
                        scales: {
                            y: { beginAtZero: true, grid: GRID, ticks: { ...TICKS, stepSize: 1, callback: v=>v+'x' } },
                            x: { grid: { display: false }, ticks: TICKS }
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
                        tooltip:{...TOOLTIP, callbacks: { label: function(context) { return ' ' + context.label + ': ' + context.raw + ' Poin'; } } }
                    }
                }
            });

            new Chart(document.getElementById('freqChart'), {
                type: 'doughnut',
                data: {
                    labels: <?= $json_freq_labels ?>,
                    datasets:[{ data:<?= $json_freq_data ?>, backgroundColor:<?= $json_freq_colors ?>,
                                borderWidth:3, borderColor:'#fff', hoverBorderColor:'#fff', hoverOffset:6 }]
                },
                options: {
                    responsive:true, maintainAspectRatio:false, cutout:'50%',
                    plugins:{
                        legend:{ position:'bottom', labels:{ usePointStyle:true, pointStyle:'circle', padding:14, font:{size:11,family:FONT} } },
                        tooltip:{...TOOLTIP, callbacks: { label: function(context) { return ' ' + context.label + ': ' + context.raw + ' Kali'; } } }
                    }
                }
            });
            <?php endif; ?>
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

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
