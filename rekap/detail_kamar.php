<?php
require_once __DIR__ . '/../bootstrap/init.php';
guard('rekap_kamar');
require_once __DIR__ . '/../layouts/header.php'; 

$kamar = $_GET['kamar'] ?? '';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

if (empty($kamar)) {
    die("<div class='container mt-5'><div class='alert alert-danger'>Pilih kamar terlebih dahulu.</div></div>");
}

$start_dt_time = $start_date . ' 00:00:00';
$end_dt_time   = $end_date . ' 23:59:59';

// 1. Ambil Summary Kamar
$sql_summary = "
    SELECT 
        COUNT(s.id) AS jumlah_santri,
        COALESCE(SUM(pel.total_pelanggaran), 0) / COUNT(s.id) AS avg_pelanggaran,
        COALESCE(SUM(rwd.total_reward), 0) / COUNT(s.id) AS avg_reward,
        COALESCE(SUM(rpt.avg_rapot), 0) / COUNT(s.id) AS avg_rapot,
        COALESCE(kbs.total_kebersihan, 0) AS pelanggaran_kebersihan
    FROM santri s
    LEFT JOIN (
        SELECT santri_id, SUM(poin) AS total_pelanggaran
        FROM pelanggaran p JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id
        WHERE tanggal BETWEEN ? AND ? GROUP BY santri_id
    ) pel ON s.id = pel.santri_id
    LEFT JOIN (
        SELECT santri_id, SUM(poin_reward) AS total_reward
        FROM daftar_reward dr JOIN jenis_reward jr ON dr.jenis_reward_id = jr.id
        WHERE tanggal BETWEEN ? AND ? GROUP BY santri_id
    ) rwd ON s.id = rwd.santri_id
    LEFT JOIN (
        SELECT santri_id, 
               ((AVG(puasa_sunnah) + AVG(sholat_duha) + AVG(sholat_malam) + AVG(sedekah) + AVG(sunnah_tidur) + AVG(ibadah_lainnya) + 
                 AVG(lisan) + AVG(sikap) + AVG(kesopanan) + AVG(muamalah) + 
                 AVG(tidur) + AVG(keterlambatan) + AVG(seragam) + AVG(makan) + AVG(arahan) + AVG(bahasa_arab) + 
                 AVG(mandi) + AVG(penampilan) + AVG(piket) + AVG(kerapihan_barang)) / 20) AS avg_rapot
        FROM rapot_kepengasuhan
        WHERE STR_TO_DATE(CONCAT(tahun, '-', FIELD(bulan, 'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'), '-01'), '%Y-%c-%d') 
              BETWEEN STR_TO_DATE(CONCAT(DATE_FORMAT(?, '%Y-%m'), '-01'), '%Y-%m-%d') AND LAST_DAY(?)
        GROUP BY santri_id
    ) rpt ON s.id = rpt.santri_id
    LEFT JOIN (
        SELECT kamar, COUNT(*) AS total_kebersihan
        FROM pelanggaran_kebersihan
        WHERE DATE(tanggal) BETWEEN ? AND ? AND kamar = ?
        GROUP BY kamar
    ) kbs ON s.kamar = kbs.kamar
    WHERE s.kamar = ?
    GROUP BY s.kamar
";
$stmt_sum = mysqli_prepare($conn, $sql_summary);
mysqli_stmt_bind_param($stmt_sum, "ssssssssss", $start_dt_time, $end_dt_time, $start_dt_time, $end_dt_time, $start_date, $end_date, $start_date, $end_date, $kamar, $kamar);
mysqli_stmt_execute($stmt_sum);
$summary = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_sum));

// 2. Ambil Data Santri Individu
$sql_santri = "
    SELECT 
        s.id, s.nama, s.kelas,
        COALESCE(pel.total_pelanggaran, 0) AS total_pelanggaran,
        COALESCE(rwd.total_reward, 0) AS total_reward,
        COALESCE(rpt.avg_rapot, 0) AS avg_rapot
    FROM santri s
    LEFT JOIN (
        SELECT santri_id, SUM(poin) AS total_pelanggaran
        FROM pelanggaran p JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id
        WHERE tanggal BETWEEN ? AND ? GROUP BY santri_id
    ) pel ON s.id = pel.santri_id
    LEFT JOIN (
        SELECT santri_id, SUM(poin_reward) AS total_reward
        FROM daftar_reward dr JOIN jenis_reward jr ON dr.jenis_reward_id = jr.id
        WHERE tanggal BETWEEN ? AND ? GROUP BY santri_id
    ) rwd ON s.id = rwd.santri_id
    LEFT JOIN (
        SELECT santri_id, 
               ((AVG(puasa_sunnah) + AVG(sholat_duha) + AVG(sholat_malam) + AVG(sedekah) + AVG(sunnah_tidur) + AVG(ibadah_lainnya) + 
                 AVG(lisan) + AVG(sikap) + AVG(kesopanan) + AVG(muamalah) + 
                 AVG(tidur) + AVG(keterlambatan) + AVG(seragam) + AVG(makan) + AVG(arahan) + AVG(bahasa_arab) + 
                 AVG(mandi) + AVG(penampilan) + AVG(piket) + AVG(kerapihan_barang)) / 20) AS avg_rapot
        FROM rapot_kepengasuhan
        WHERE STR_TO_DATE(CONCAT(tahun, '-', FIELD(bulan, 'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'), '-01'), '%Y-%c-%d') 
              BETWEEN STR_TO_DATE(CONCAT(DATE_FORMAT(?, '%Y-%m'), '-01'), '%Y-%m-%d') AND LAST_DAY(?)
        GROUP BY santri_id
    ) rpt ON s.id = rpt.santri_id
    WHERE s.kamar = ?
    ORDER BY total_pelanggaran ASC, avg_rapot DESC, total_reward DESC
";
$stmt_santri = mysqli_prepare($conn, $sql_santri);
mysqli_stmt_bind_param($stmt_santri, "sssssss", $start_dt_time, $end_dt_time, $start_dt_time, $end_dt_time, $start_date, $end_date, $kamar);
mysqli_stmt_execute($stmt_santri);
$santri_res = mysqli_stmt_get_result($stmt_santri);
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap');
body { background-color: #f8f9fa; font-family: 'Poppins', sans-serif; color: #333; }
.container { max-width: 1100px; margin: 30px auto; padding: 0 15px; animation: fadeIn 0.4s ease-out; }

/* Breadcrumb & Header */
.header-wrapper { display: flex; align-items: center; justify-content: space-between; margin-bottom: 25px; flex-wrap: wrap; gap: 15px; }
.header-title h2 { color: #1e293b; font-weight: 800; font-size: 28px; margin: 0; display: flex; align-items: center; gap: 12px; }
.header-title h2::before { content: '🚪'; font-size: 28px; }
.header-title .subtitle { color: #64748b; font-size: 14px; margin-top: 5px; }
.btn-back { display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; background-color: white; border: 1px solid #e2e8f0; border-radius: 10px; color: #475569; font-weight: 600; font-size: 14px; text-decoration: none; transition: 0.2s; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
.btn-back:hover { background-color: #f8fafc; border-color: #cbd5e1; color: #0f172a; transform: translateY(-1px); }

/* Summary Cards */
.summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 35px; }
.sum-card { background: white; padding: 20px; border-radius: 16px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); border: 1px solid #f1f5f9; display: flex; align-items: center; gap: 18px; transition: 0.2s; }
.sum-card:hover { transform: translateY(-3px); box-shadow: 0 10px 25px rgba(0,0,0,0.06); }
.sum-icon { width: 54px; height: 54px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 24px; flex-shrink: 0; }
.sum-info h4 { margin: 0; font-size: 24px; font-weight: 800; color: #0f172a; line-height: 1.2; }
.sum-info p { margin: 4px 0 0 0; font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }

/* Table Wrapper */
.table-wrapper { background: white; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.04); border: 1px solid #f1f5f9; overflow: hidden; }
.table-header { padding: 20px 25px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; background: #fafafa; flex-wrap: wrap; gap: 12px; }
.table-header h3 { margin: 0; font-size: 18px; font-weight: 700; color: #1e293b; }
.table-responsive { overflow-x: auto; }
.custom-table { width: 100%; border-collapse: collapse; }
.custom-table th { background-color: white; color: #64748b; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; padding: 16px 25px; border-bottom: 2px solid #f1f5f9; white-space: nowrap; }
.custom-table td { padding: 18px 25px; border-bottom: 1px solid #f1f5f9; color: #334155; font-size: 14.5px; font-weight: 500; vertical-align: middle; white-space: nowrap; }
.custom-table tbody tr { transition: background-color 0.2s; }
.custom-table tbody tr:hover { background-color: #f8fafc; }
.custom-table tbody tr:last-child td { border-bottom: none; }

/* Styling for Badges/Pills in Table */
.pill { display: inline-block; padding: 6px 12px; border-radius: 20px; font-size: 13px; font-weight: 700; text-align: center; min-width: 45px; }
.pill-danger { background-color: #fee2e2; color: #ef4444; }
.pill-success { background-color: #d1fae5; color: #10b981; }
.pill-primary { background-color: #e0e7ff; color: #4f46e5; }
.pill-gray { background-color: #f1f5f9; color: #64748b; }

.btn-detail { display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 10px; background-color: #f1f5f9; color: #64748b; text-decoration: none; transition: 0.2s; }
.btn-detail:hover { background-color: #ec4899; color: white; transform: scale(1.05); }

@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
</style>

<div class="container">
    <div class="header-wrapper">
        <div class="header-title">
            <h2>Detail Kamar <?= htmlspecialchars($kamar) ?></h2>
            <div class="subtitle">
                <i class="far fa-calendar-alt me-1"></i> Periode Filter: <?= date('d M Y', strtotime($start_date)) ?> s/d <?= date('d M Y', strtotime($end_date)) ?>
            </div>
        </div>
        <a href="peringkat_kamar.php?start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" class="btn-back">
            <i class="fas fa-arrow-left"></i> Kembali ke Peringkat
        </a>
    </div>

    <!-- Summary Kamar -->
    <div class="summary-grid">
        <div class="sum-card">
            <div class="sum-icon" style="background: #e0e7ff; color: #4f46e5;"><i class="fas fa-users"></i></div>
            <div class="sum-info">
                <h4><?= $summary['jumlah_santri'] ?></h4>
                <p>Total Santri</p>
            </div>
        </div>
        <div class="sum-card">
            <div class="sum-icon" style="background: #fee2e2; color: #ef4444;"><i class="fas fa-exclamation-triangle"></i></div>
            <div class="sum-info">
                <h4><?= round($summary['avg_pelanggaran'], 1) ?></h4>
                <p>Avg Pelanggaran Individu</p>
            </div>
        </div>
        <div class="sum-card">
            <div class="sum-icon" style="background: #fef3c7; color: #d97706;"><i class="fas fa-broom"></i></div>
            <div class="sum-info">
                <h4><?= $summary['pelanggaran_kebersihan'] ?></h4>
                <p>Pelanggaran Kebersihan</p>
            </div>
        </div>
        <div class="sum-card">
            <div class="sum-icon" style="background: #d1fae5; color: #10b981;"><i class="fas fa-trophy"></i></div>
            <div class="sum-info">
                <h4>+<?= round($summary['avg_reward'], 1) ?></h4>
                <p>Avg Reward</p>
            </div>
        </div>
    </div>

    <!-- Tabel Rincian Santri -->
    <div class="table-wrapper">
        <div class="table-header">
            <h3>Rincian Kontribusi Santri</h3>
            <span class="badge bg-light text-dark border"><i class="fas fa-sort-amount-down me-1"></i> Diurutkan dari yang terbaik</span>
        </div>
        <div class="table-responsive">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th style="width: 50px; text-align: center;">No</th>
                        <th>Nama Santri</th>
                        <th>Kelas</th>
                        <th style="text-align: center;">Pelanggaran</th>
                        <th style="text-align: center;">Reward</th>
                        <th style="text-align: center;">Rapot</th>
                        <th style="text-align: center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($santri_res) > 0): ?>
                        <?php $no = 1; while($s = mysqli_fetch_assoc($santri_res)): ?>
                            <tr>
                                <td style="text-align: center; font-weight: 700; color: #94a3b8;"><?= $no++ ?></td>
                                <td>
                                    <div style="font-weight: 700; color: #0f172a;"><?= htmlspecialchars($s['nama']) ?></div>
                                </td>
                                <td><?= htmlspecialchars($s['kelas']) ?></td>
                                
                                <!-- Pelanggaran -->
                                <td style="text-align: center;">
                                    <?php if ($s['total_pelanggaran'] > 0): ?>
                                        <span class="pill pill-danger"><?= $s['total_pelanggaran'] ?></span>
                                    <?php else: ?>
                                        <span class="pill pill-success">0</span>
                                    <?php endif; ?>
                                </td>
                                
                                <!-- Reward -->
                                <td style="text-align: center;">
                                    <?php if ($s['total_reward'] > 0): ?>
                                        <span class="pill pill-success">+<?= $s['total_reward'] ?></span>
                                    <?php else: ?>
                                        <span class="pill pill-gray">0</span>
                                    <?php endif; ?>
                                </td>
                                
                                <!-- Rapot -->
                                <td style="text-align: center;">
                                    <?php if ($s['avg_rapot'] > 0): ?>
                                        <span class="pill pill-primary"><i class="fas fa-star" style="font-size: 10px; margin-right: 3px;"></i><?= number_format($s['avg_rapot'], 1) ?></span>
                                    <?php else: ?>
                                        <span class="pill pill-gray">-</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Aksi -->
                                <td style="text-align: center;">
                                    <?php if (has_permission('rekap_detail_santri')): ?>
                                        <a href="detail_karakter.php?id=<?= $s['id'] ?>&start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>" class="btn-detail" title="Lihat Grafik Karakter">
                                            <i class="fas fa-chart-line"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted"><i class="fas fa-lock"></i></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 40px; color: #94a3b8;">
                                <i class="fas fa-box-open fa-3x mb-3" style="opacity: 0.3;"></i>
                                <p>Tidak ada data santri di kamar ini.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
