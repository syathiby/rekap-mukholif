<?php
// 1. Panggil 'Otak' aplikasi
require_once __DIR__ . '/../bootstrap/init.php';

// 2. Jalankan 'SATPAM' buat ngejaga halaman
guard('rekap_kamar');

$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if (!$is_ajax) {
    require_once __DIR__ . '/../layouts/header.php'; 
}

// 🔹 Ambil periode aktif
$periode_aktif = PERIODE_AKTIF;

if (!$periode_aktif) {
    die("<div class='container' style='padding-top:20px;'><div class='alert alert-danger'>⚠ Periode aktif belum diset. Silakan atur dulu di halaman pengaturan.</div></div>");
}

// 🔹 Ambil filter dari URL (jika ada)
$start_date = $_GET['start_date'] ?? $periode_aktif;
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// 🔹 Kueri 1: Master Kamar (O(1))
$q_master = mysqli_query($conn, "SELECT kamar, COUNT(id) AS jumlah_santri FROM santri WHERE kamar IS NOT NULL AND kamar != '' GROUP BY kamar");
$raw_data = [];
while ($row = mysqli_fetch_assoc($q_master)) {
    $raw_data[$row['kamar']] = [
        'kamar' => $row['kamar'],
        'jumlah_santri' => (int)$row['jumlah_santri'],
        'total_pelanggaran' => 0,
        'total_kasus' => 0,
        'total_reward' => 0,
        'total_rapot' => 0,
        'pelanggaran_kebersihan' => 0
    ];
}

$start_dt_time = $start_date . ' 00:00:00';
$end_dt_time   = $end_date . ' 23:59:59';

// 🔹 Kueri 2: Total Pelanggaran Individu
$stmt_pel = mysqli_prepare($conn, "SELECT s.kamar, COUNT(p.id) AS total_kasus, SUM(jp.poin) AS total_poin FROM pelanggaran p JOIN santri s ON p.santri_id = s.id JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id WHERE p.tanggal BETWEEN ? AND ? GROUP BY s.kamar");
mysqli_stmt_bind_param($stmt_pel, "ss", $start_dt_time, $end_dt_time);
mysqli_stmt_execute($stmt_pel);
$res_pel = mysqli_stmt_get_result($stmt_pel);
while ($r = mysqli_fetch_assoc($res_pel)) {
    if (isset($raw_data[$r['kamar']])) {
        $raw_data[$r['kamar']]['total_pelanggaran'] = (int)$r['total_poin'];
        $raw_data[$r['kamar']]['total_kasus'] = (int)$r['total_kasus'];
    }
}

// 🔹 Kueri 3: Total Reward Individu
$stmt_rwd = mysqli_prepare($conn, "SELECT s.kamar, SUM(jr.poin_reward) AS total FROM daftar_reward dr JOIN santri s ON dr.santri_id = s.id JOIN jenis_reward jr ON dr.jenis_reward_id = jr.id WHERE dr.tanggal BETWEEN ? AND ? GROUP BY s.kamar");
mysqli_stmt_bind_param($stmt_rwd, "ss", $start_dt_time, $end_dt_time);
mysqli_stmt_execute($stmt_rwd);
$res_rwd = mysqli_stmt_get_result($stmt_rwd);
while ($r = mysqli_fetch_assoc($res_rwd)) {
    if (isset($raw_data[$r['kamar']])) $raw_data[$r['kamar']]['total_reward'] = (int)$r['total'];
}

// 🔹 Kueri 4: Total Rapot Individu (Menggabungkan 20 Indikator)
$bulan_indo   = ['1'=>'Januari','2'=>'Februari','3'=>'Maret','4'=>'April','5'=>'Mei','6'=>'Juni','7'=>'Juli','8'=>'Agustus','9'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'];
$current_ts   = strtotime(date('Y-m-01', strtotime($start_date)));
$end_ts_month = strtotime(date('Y-m-01', strtotime($end_date)));
$valid_months = [];
while ($current_ts <= $end_ts_month) {
    $y = date('Y', $current_ts); $m = date('n', $current_ts);
    $valid_months[] = "(r.tahun = $y AND r.bulan = '{$bulan_indo[$m]}')";
    $current_ts = strtotime("+1 month", $current_ts);
}
$where_rapot = empty($valid_months) ? "1=0" : "(" . implode(" OR ", $valid_months) . ")";

$stmt_rpt = mysqli_prepare($conn, "SELECT s.kamar, SUM(((puasa_sunnah + sholat_duha + sholat_malam + sedekah + sunnah_tidur + ibadah_lainnya + lisan + sikap + kesopanan + muamalah + tidur + keterlambatan + seragam + makan + arahan + bahasa_arab + mandi + penampilan + piket + kerapihan_barang) / 20)) AS total FROM rapot_kepengasuhan r JOIN santri s ON r.santri_id = s.id WHERE $where_rapot GROUP BY s.kamar");
mysqli_stmt_execute($stmt_rpt);
$res_rpt = mysqli_stmt_get_result($stmt_rpt);
while ($r = mysqli_fetch_assoc($res_rpt)) {
    if (isset($raw_data[$r['kamar']])) $raw_data[$r['kamar']]['total_rapot'] = (float)$r['total'];
}

// 🔹 Kueri 5: Total Mukholif Kamar
$stmt_kbs = mysqli_prepare($conn, "SELECT kamar, COUNT(*) AS total FROM pelanggaran_kebersihan WHERE DATE(tanggal) BETWEEN ? AND ? GROUP BY kamar");
mysqli_stmt_bind_param($stmt_kbs, "ss", $start_date, $end_date);
mysqli_stmt_execute($stmt_kbs);
$res_kbs = mysqli_stmt_get_result($stmt_kbs);
while ($r = mysqli_fetch_assoc($res_kbs)) {
    if (isset($raw_data[$r['kamar']])) $raw_data[$r['kamar']]['pelanggaran_kebersihan'] = (int)$r['total'];
}

// 🔹 Merakit dan Menghitung Agregat Rata-rata per Kamar
$kamar_data = [];

foreach ($raw_data as $d) {
    if ($d['jumlah_santri'] == 0) continue;

    $avg_pel = $d['total_pelanggaran'] / $d['jumlah_santri'];
    $avg_kasus = $d['total_kasus'] / $d['jumlah_santri'];
    $avg_rwd = $d['total_reward'] / $d['jumlah_santri'];
    $avg_rpt = $d['total_rapot'] / $d['jumlah_santri'];
    $kbs = $d['pelanggaran_kebersihan'];

    $kamar_data[] = [
        'kamar' => $d['kamar'],
        'jumlah_santri' => $d['jumlah_santri'],
        'avg_pelanggaran' => $avg_pel,
        'avg_kasus' => $avg_kasus,
        'avg_reward' => $avg_rwd,
        'avg_rapot' => $avg_rpt,
        'pelanggaran_kebersihan' => $kbs
    ];
}

// Hitung skor kamar berdasarkan formula absolut (skor teladan rata-rata kamar)
foreach ($kamar_data as &$k) {
    $pelanggaran = (float)$k['avg_pelanggaran'];
    $kasus = (float)$k['avg_kasus'];
    $reward = (float)$k['avg_reward'];
    $rapot = (float)$k['avg_rapot'];
    $kebersihan = (int)$k['pelanggaran_kebersihan'];

    // 1. Rata-rata Skor Teladan Santri
    $avg_skor_teladan = ($rapot * 20) + $reward - ($pelanggaran * 2) - ($kasus * 5);
    
    // 2. Skor Kamar (Avg Skor Teladan dikurangi pinalti kotor kamar)
    // Asumsi: Setiap mukholif kebersihan kamar memotong 10 poin
    $k['skor_kamar'] = $avg_skor_teladan - ($kebersihan * 10);
    $k['avg_skor_teladan'] = $avg_skor_teladan;
}
unset($k);

// Urutkan berdasarkan skor kamar
usort($kamar_data, function($a, $b) {
    if (abs($a['skor_kamar'] - $b['skor_kamar']) < 0.0001) {
        return (int)$a['kamar'] - (int)$b['kamar'];
    }
    return ($b['skor_kamar'] <=> $a['skor_kamar']);
});

if ($is_ajax) {
    ob_start();
} else {
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap');
body { background-color: #f8f9fa; font-family: 'Poppins', sans-serif; color: #333; }
.container { max-width: 1240px; margin: 20px auto; padding: 0 15px; animation: fadeIn 0.4s ease-out; }
.page-header { margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px solid #e9ecef; }
.page-header h2 { color: #1e293b; font-weight: 800; font-size: 28px; display: flex; align-items: center; gap: 12px; }
.page-header h2::before { content: '🏠'; font-size: 28px; }
.page-header .subtitle { color: #64748b; font-size: 15px; margin-top: -3px; }

/* Filter CSS */
.filter-form { background-color: #ffffff; border-radius: 14px; box-shadow: 0 4px 20px rgba(0,0,0,0.04); padding: 20px 24px; margin: 0 0 30px 0; border: 1px solid #f1f5f9; }
.filter-controls { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 18px; align-items: flex-end; }
.filter-group { display: flex; flex-direction: column; }
.filter-group label { font-size: 12px; color: #64748b; font-weight: 700; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px; }
.filter-input { padding: 10px 14px; border: 1px solid #e2e8f0; border-radius: 10px; font-family: 'Poppins', sans-serif; font-size: 14px; background-color: #f8fafc; width: 100%; color: #334155; }
.filter-input:focus { outline: none; border-color: #ec4899; background-color: #fff; box-shadow: 0 0 0 4px rgba(236, 72, 153, 0.1); }
.filter-actions { display: flex; align-items: center; justify-content: flex-end; gap: 15px; height: 42px; }
.btn-submit { padding: 0 24px; height: 100%; border-radius: 10px; background-color: #ec4899; color: white; font-weight: 600; border: none; font-size: 14px; display: inline-flex; align-items: center; gap: 8px; transition: 0.2s; }
.btn-submit:hover { background-color: #be185d; }

/* Card CSS */
.card-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 22px; }
.kamar-card { background: #ffffff; border-radius: 16px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04); padding: 24px 22px 20px; position: relative; overflow: hidden; transition: transform 0.25s ease, box-shadow 0.25s ease; border: 1px solid #f1f5f9; opacity: 0; transform: translateY(15px); animation: cardFadeIn 0.4s ease-out forwards; }
.kamar-card:hover { transform: translateY(-4px); box-shadow: 0 12px 25px rgba(0, 0, 0, 0.08); }

.card-rank { position: absolute; top: 18px; right: 18px; height: 40px; min-width: 40px; padding: 0 12px; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 16px; border-radius: 50px; letter-spacing: -0.5px; }
.rank-1 { background: linear-gradient(135deg, #fbbf24, #d97706); color: white; box-shadow: 0 4px 12px rgba(217, 119, 6, 0.3); border: 2px solid #fff; }
.rank-2 { background: linear-gradient(135deg, #cbd5e1, #64748b); color: white; box-shadow: 0 4px 12px rgba(100, 116, 139, 0.3); border: 2px solid #fff; }
.rank-3 { background: linear-gradient(135deg, #fca5a5, #b91c1c); color: white; box-shadow: 0 4px 12px rgba(185, 28, 28, 0.3); border: 2px solid #fff; }
.rank-other { background: #f1f5f9; color: #64748b; border: 2px solid #e2e8f0; }

.card-content h3 { margin: 0 0 14px 0; font-size: 22px; font-weight: 700; color: #0f172a; padding-right: 48px; }
.card-content h3 a { color: inherit; text-decoration: none; transition: 0.2s; }
.card-content h3 a:hover { color: #ec4899; }

.stats-container { display: flex; flex-wrap: wrap; background-color: #f8fafc; border-radius: 12px; padding: 12px; border: 1px solid #f1f5f9; gap: 10px; }
.stat-item { text-align: center; flex: 1 1 45%; }
.stat-value { font-size: 16px; font-weight: 800; display: block; line-height: 1.2; letter-spacing: -0.5px; }
.stat-label { font-size: 10px; color: #64748b; text-transform: uppercase; letter-spacing: 0.8px; margin-top: 4px; font-weight: 600; display:block; }

.no-data { grid-column: 1 / -1; text-align: center; padding: 60px 20px; background: #fff; border-radius: 16px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04); border: 1px dashed #cbd5e1; }
.no-data p { font-size: 17px; color: #475569; font-weight: 500; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }
@keyframes cardFadeIn { to { opacity: 1; transform: translateY(0); } }
</style>

<div class="container">
    <div class="page-header border-bottom-0 pb-0">
        <h2>Peringkat Kamar Terbaik</h2>
        
        <div class="alert mt-4 mb-1 border-0 shadow-sm" style="background: linear-gradient(to right, #ffffff, #f8fafc); border-radius: 14px; padding: 18px 24px; border-left: 4px solid #ec4899 !important;">
            <div class="d-flex align-items-center mb-2">
                <div class="d-flex align-items-center justify-content-center rounded-circle me-2" style="width: 28px; height: 28px; background-color: rgba(236, 72, 153, 0.1);">
                    <i class="fas fa-chart-pie" style="color: #ec4899; font-size: 0.85rem;"></i>
                </div>
                <strong style="color: #1e293b; font-size: 15px;">Formula Perhitungan Peringkat</strong>
            </div>
            <p class="mb-3 ms-1" style="font-size: 13.5px; color: #64748b; line-height: 1.5;">Peringkat kamar diukur secara absolut, selaras dengan Formula Skor Keteladanan, sehingga terukur secara pasti performa aslinya.</p>
            
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 ms-1">
                <div class="d-flex flex-wrap gap-2">
                    <div style="background-color: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; border-radius: 8px; padding: 6px 12px; font-size: 12.5px; font-weight: 600;">
                        <i class="fas fa-star me-1" style="color: #3b82f6;"></i> Rata-rata Skor Teladan Santri
                    </div>
                    <div style="background-color: #fffbeb; color: #92400e; border: 1px solid #fde68a; border-radius: 8px; padding: 6px 12px; font-size: 12.5px; font-weight: 600;">
                        <i class="fas fa-minus-circle me-1" style="color: #f59e0b;"></i> Dikurangi Mukholif Kamar (-10)
                    </div>
                </div>
                
                <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#infoPerhitunganModal" style="font-size: 12.5px; font-weight: 600; border-color: #ec4899; color: #ec4899; background-color: #fff;">
                    <i class="fas fa-info-circle me-1"></i> Baca Selengkapnya
                </button>
            </div>
        </div>
    </div>

    <!-- Form Filter -->
    <form id="filterForm" action="" method="GET" class="filter-form">
        <div class="filter-controls">
            <div class="filter-group">
                <label>Mulai Tanggal</label>
                <input type="date" name="start_date" class="filter-input" value="<?= htmlspecialchars($start_date) ?>">
            </div>
            <div class="filter-group">
                <label>Sampai Tanggal</label>
                <input type="date" name="end_date" class="filter-input" value="<?= htmlspecialchars($end_date) ?>">
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn-submit"><i class="fas fa-search"></i> Hitung Ulang Peringkat</button>
            </div>
        </div>
    </form>

    <div id="loadingOverlay" style="display:none; text-align:center; padding: 40px;">
        <i class="fas fa-circle-notch fa-spin fa-3x" style="color:#ec4899;"></i>
        <p class="mt-3 text-muted">Menganalisis data kamar...</p>
    </div>

    <div id="gridContainer">
<?php
} // End if (!$is_ajax)
?>
        <div class="card-grid">
        <?php
        if (empty($kamar_data)) {
            echo "
            <div class='no-data'>
                <p>Belum ada data untuk ditampilkan.</p>
            </div>";
        } else {
            $no = 1;
            foreach ($kamar_data as $row) {
                $animation_delay = min($no * 0.05, 1.5);
                
                $pelanggaran = round((float)$row['avg_pelanggaran'], 1);
                $reward = round((float)$row['avg_reward'], 1);
                $rapot = round((float)$row['avg_rapot'], 1);
                $kebersihan = (int)$row['pelanggaran_kebersihan'];
                $jml_santri = (int)$row['jumlah_santri'];

                if ($no === 1) $rank_class = 'rank-1';
                elseif ($no === 2) $rank_class = 'rank-2';
                elseif ($no === 3) $rank_class = 'rank-3';
                else $rank_class = 'rank-other';
                
        ?>
                <div class="kamar-card" style="animation-delay: <?= $animation_delay ?>s;">
                    <div class="card-rank <?= $rank_class ?>"><?= $no ?></div>
                    
                    <div class="card-content">
                        <h3>
                            <?php if (has_permission('rekap_kamar')): ?>
                                <a href="detail_kamar.php?kamar=<?= urlencode($row['kamar']) ?>&start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>" class="stretched-link" style="color: inherit; text-decoration: none;">
                                    Kamar <?= htmlspecialchars($row['kamar']) ?>
                                </a>
                            <?php else: ?>
                                Kamar <?= htmlspecialchars($row['kamar']) ?>
                            <?php endif; ?>
                        </h3>
                        
                        <div style="font-size: 13px; color: #64748b; margin-bottom: 15px;">
                            <i class="fas fa-users me-1"></i> Terdiri dari <?= $jml_santri ?> santri
                        </div>

                        <div class="stats-container">
                            <div class="stat-item" title="Rata-rata poin pelanggaran santri">
                                <span class="stat-value text-danger"><?= $pelanggaran ?></span>
                                <span class="stat-label">Avg Pelanggaran</span>
                            </div>
                            <div class="stat-item" title="Rata-rata poin reward santri">
                                <span class="stat-value text-success">+<?= $reward ?></span>
                                <span class="stat-label">Avg Reward</span>
                            </div>
                            <div class="stat-item" title="Rata-rata rapot kepengasuhan">
                                <span class="stat-value text-primary"><i class="fas fa-star" style="font-size: 11px;"></i> <?= $rapot ?></span>
                                <span class="stat-label">Avg Rapot</span>
                            </div>
                            <div class="stat-item" title="Total mukholif kebersihan kamar">
                                <span class="stat-value text-warning"><?= $kebersihan ?> x</span>
                                <span class="stat-label">Mukholif Kamar</span>
                            </div>
                        </div>

                        <div style="margin-top: 15px; padding-top: 15px; border-top: 1px dashed #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                            <span style="font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">Skor Analisis Kamar</span>
                            <span style="font-size: 18px; font-weight: 800; color: #ec4899;"><?= number_format($row['skor_kamar'], 2) ?></span>
                        </div>
                    </div>
                </div>
        <?php
                $no++;
            }
        }
        ?>
        </div>
<?php 
if ($is_ajax) {
    echo ob_get_clean();
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
            
            const cards = gridContainer.querySelectorAll('.kamar-card');
            cards.forEach(card => {
                card.style.animation = 'none';
                card.offsetHeight; 
                card.style.animation = null; 
            });
            window.history.pushState({}, '', url);
        })
        .catch(error => {
            loadingOverlay.innerHTML = '<div class="alert alert-danger">Gagal memuat data.</div>';
        });
    }

    filterForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(filterForm);
        const params = new URLSearchParams(formData);
        fetchGrid('?' + params.toString());
    });

    const selects = filterForm.querySelectorAll('input[type="date"]');
    selects.forEach(select => {
        select.addEventListener('change', function() {
            filterForm.dispatchEvent(new Event('submit'));
        });
    });
});
</script>

<!-- Modal Info Perhitungan -->
<div class="modal fade" id="infoPerhitunganModal" tabindex="-1" aria-labelledby="infoPerhitunganLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
      <div class="modal-header border-bottom-0 pb-0 px-4 pt-4">
        <h5 class="modal-title fw-bold text-dark d-flex align-items-center gap-2" id="infoPerhitunganLabel">
            <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center" style="width:36px; height:36px;">
                <i class="fas fa-square-root-alt"></i>
            </div>
            Detail Kalkulasi Peringkat
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body px-4 py-4">
        <p class="text-muted" style="font-size: 14.5px; line-height: 1.6;">
            Halo! Agar sistem penilaian 100% selaras dengan penobatan Santri Teladan, skor kamar dinilai secara adil berdasarkan akumulasi asli penghuninya, dengan formula absolut yang seragam.
        </p>

        <h6 class="fw-bold mt-4 mb-3 text-dark"><i class="fas fa-layer-group text-primary me-2"></i>Bagaimana Cara Hitungnya?</h6>
        
        <div class="card border-0 bg-light rounded-4 p-3 mb-3 shadow-sm">
            <h6 class="fw-bold text-primary mb-2">1. Skor Keteladanan Absolut</h6>
            <p class="text-muted small mb-0">Rata-rata Skor Teladan seluruh santri dihitung menggunakan formula aslinya: <strong>Nilai Rapot x 20 + Total Reward - (Poin Pelanggaran x 2) - (Kasus x 5)</strong>.</p>
        </div>

        <div class="card border-0 bg-light rounded-4 p-3 mb-3 shadow-sm">
            <h6 class="fw-bold text-success mb-2">2. Digabung Menjadi Rata-rata Kamar</h6>
            <p class="text-muted small mb-0">
                Lalu, skor keteladanan semua penghuni dijumlahkan, dan <strong>dibagi dengan jumlah santri</strong> di kamar tersebut, sehingga mendapat skor rata-rata kamar yang adil (tidak masalah besar kecilnya kamar).
            </p>
        </div>

        <div class="card border-0 bg-light rounded-4 p-3 shadow-sm">
            <h6 class="fw-bold text-warning mb-2" style="color:#d97706!important;">3. Pinalti Mukholif Kamar</h6>
            <p class="text-muted small mb-0">
                Dan yang terakhir, Rata-rata Skor Kamar akan <strong>dikurangi 10 skor</strong> untuk setiap kali kamar tersebut Mukholif kamar.
            </p>
        </div>
        
        <div class="alert alert-info border-0 rounded-4 mt-4 mb-0 shadow-sm" style="background-color: #eff6ff; color: #1e40af; border-left: 4px solid #3b82f6 !important;">
            <i class="fas fa-lightbulb me-2"></i><strong>Intinya:</strong> Kamar juara adalah kamar yang santri-santrinya berprestasi, rajin ibadah, kamarnya kinclong, dan jarang banget ngelanggar aturan!
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; } ?>
