<?php
// 1. Panggil 'Otak' aplikasi dulu
require_once __DIR__ . '/../../bootstrap/init.php';

// 2. Jalankan 'SATPAM' buat ngejaga halaman
guard('arsip_view'); 

// 3. Kalau lolos, baru panggil Tampilan
require_once __DIR__ . '/../../layouts/header.php';

// Ambil SEMUA filter dari URL
$arsip_id = (int)($_GET['id'] ?? 0);
$santri_id = (int)($_GET['santri_id'] ?? 0);

if ($arsip_id <= 0 || $santri_id <= 0) {
    die("<div class='container my-4'><div class='alert alert-danger'>Informasi tidak lengkap untuk menampilkan detail.</div></div>");
}

$stmt_arsip = $conn->prepare("SELECT * FROM arsip WHERE id = ?");
$stmt_arsip->bind_param('i', $arsip_id);
$stmt_arsip->execute();
$arsip = $stmt_arsip->get_result()->fetch_assoc();
if (!$arsip) die("<div class='container my-4'><div class='alert alert-danger'>Arsip tidak ditemukan.</div></div>");

$filter_kamar = $_GET['kamar'] ?? null;
$filter_bagian = $_GET['bagian'] ?? null;
$filter_jp = $_GET['jenis_reward'] ?? null;

// Query 1: Ambil data profil santri     
$stmt_santri = $conn->prepare("SELECT santri_nama AS nama, santri_kelas AS kelas, santri_kamar AS kamar, total_poin_saat_arsip AS poin_aktif FROM arsip_data_santri WHERE arsip_id = ? AND santri_id = ?");
$stmt_santri->bind_param("ii", $arsip_id, $santri_id);
$stmt_santri->execute();
$santri = $stmt_santri->get_result()->fetch_assoc();

if (!$santri) {
    die("<div class='container my-4'><div class='alert alert-danger'>Data santri tidak ditemukan di arsip ini.</div></div>");
}

// Hitung Poin Bersih Dinamis
$sql_pb = "
    SELECT 
        (SELECT COALESCE(SUM(poin), 0) FROM arsip_data_pelanggaran WHERE arsip_id = ? AND santri_id = ? AND tipe = 'Umum') -
        (SELECT COALESCE(SUM(poin_reward), 0) FROM arsip_data_reward WHERE arsip_id = ? AND santri_id = ?) 
    AS poin_bersih
";
$stmt_pb = $conn->prepare($sql_pb);
$stmt_pb->bind_param("iiii", $arsip_id, $santri_id, $arsip_id, $santri_id);
$stmt_pb->execute();
$poin_bersih_dinamis = (int)$stmt_pb->get_result()->fetch_assoc()['poin_bersih'];
$poin_bersih_display = $poin_bersih_dinamis < 0 ? 0 : $poin_bersih_dinamis;

// Query 2: Ambil detail reward
$sql_detail = "
    SELECT tanggal, '' AS kategori, poin_reward AS poin,
           nama_reward AS nama_pelanggaran, dicatat_oleh_nama
    FROM arsip_data_reward
    WHERE arsip_id = ? AND santri_id = ?
";
$params_detail = [$arsip_id, $santri_id];
$types_detail = "ii";
$sql_detail .= " ORDER BY tanggal DESC";
$stmt_detail = $conn->prepare($sql_detail);
$stmt_detail->bind_param($types_detail, ...$params_detail);
$stmt_detail->execute();
$detail_list = $stmt_detail->get_result()->fetch_all(MYSQLI_ASSOC);

// Kalkulasi statistik
$total_reward = count($detail_list);
$total_poin = array_sum(array_column($detail_list, 'poin'));

// Helper function untuk ikon
function getKategoriInfo($kategori) {
    return match (strtolower($kategori)) {
        'berat' => ['color' => 'danger', 'icon' => 'fa-exclamation-triangle'],
        'sedang' => ['color' => 'warning', 'icon' => 'fa-exclamation-circle'],
        'ringan' => ['color' => 'success', 'icon' => 'fa-info-circle'],
        default => ['color' => 'success', 'icon' => 'fa-star'],
    };
}

// Helper function khusus untuk mode Pengabdian (Waktu Keterlambatan)
function getTelatInfo($nama) {
    $n = strtolower($nama);
    if (strpos($n, 'subuh') !== false) {
        return ['color' => 'primary', 'bg' => '#e0f2fe', 'text' => '#0284c7', 'icon' => 'fa-moon', 'label' => 'Subuh', 'desc' => 'Sholat Subuh Berjamaah'];
    } elseif (strpos($n, 'dzuhur') !== false) {
        return ['color' => 'warning', 'bg' => '#fef3c7', 'text' => '#d97706', 'icon' => 'fa-sun', 'label' => 'Dzuhur', 'desc' => 'Sholat Dzuhur Berjamaah'];
    } elseif (strpos($n, 'ashar') !== false) {
        return ['color' => 'success', 'bg' => '#d1fae5', 'text' => '#059669', 'icon' => 'fa-cloud-sun', 'label' => 'Ashar', 'desc' => 'Sholat Ashar Berjamaah'];
    } elseif (strpos($n, 'maghrib') !== false) {
        return ['color' => 'danger', 'bg' => '#fee2e2', 'text' => '#dc2626', 'icon' => 'fa-cloud-moon', 'label' => 'Maghrib', 'desc' => 'Sholat Maghrib Berjamaah'];
    } elseif (strpos($n, 'isya') !== false) {
        return ['color' => 'dark', 'bg' => '#f3f4f6', 'text' => '#374151', 'icon' => 'fa-star-and-crescent', 'label' => 'Isya', 'desc' => 'Sholat Isya Berjamaah'];
    } elseif (strpos($n, 'kbm') !== false || strpos($n, 'kegiatan belajar') !== false) {
        return ['color' => 'info', 'bg' => '#e0e7ff', 'text' => '#4f46e5', 'icon' => 'fa-book-open', 'label' => 'KBM', 'desc' => 'Kegiatan Belajar Mengajar'];
    }
    return ['color' => 'secondary', 'bg' => '#f1f5f9', 'text' => '#64748b', 'icon' => 'fa-clock', 'label' => 'Lainnya', 'desc' => $nama];
}
?>

<!-- ✅ STYLE BARU YANG LEBIH MINIMALIS & MODERN -->
<style>
    :root {
        --primary: #4f46e5;
        --primary-dark: #4338ca;
        --secondary: #64748b;
        --light-bg: #f8fafc;
        --card-bg: #ffffff;
        --border-color: #e2e8f0;
        --table-border-color: #f1f5f9;
        --text-dark: #1e293b;
        --text-light: #64748b;
        --text-danger: #dc2626;
        --card-header-bg: #f8fafc;
    }

    body {
        background-color: var(--light-bg);
        font-family: 'Poppins', sans-serif;
        color: var(--text-dark);
    }

    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .page-title {
        font-weight: 700;
        font-size: 1.75rem;
        margin: 0;
    }

    .btn-back {
        background-color: var(--card-bg);
        border: 1px solid var(--border-color);
        color: var(--text-dark);
        font-weight: 600;
        transition: all 0.2s ease;
    }

    .btn-back:hover {
        background-color: var(--primary);
        color: white;
        border-color: var(--primary);
    }

    .summary-card {
        background-color: var(--card-bg);
        border-radius: 1rem;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
        padding: 2rem 1.5rem;
        position: relative;
        overflow: hidden;
        border: 1px solid var(--border-color);
    }
    .summary-card::before {
        content: '';
        position: absolute;
        top: 0; left: 0; width: 100%; height: 6px;
        background: linear-gradient(90deg, var(--primary), #8b5cf6);
    }

    .santri-name {
        font-size: 1.5rem;
        font-weight: 600;
        margin-bottom: 0.25rem;
    }

    .santri-info {
        color: var(--text-light);
        margin-bottom: 1.5rem;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.5rem;
        border-top: 1px solid var(--border-color);
        padding-top: 1.5rem;
    }

    .stat-item .stat-number {
        font-size: 2.25rem;
        font-weight: 700;
        color: var(--primary-dark);
        line-height: 1;
    }

    .stat-item .stat-label {
        color: var(--text-light);
        font-size: 0.875rem;
    }

    .filters-display {
        background-color: var(--light-bg);
        border-radius: 0.5rem;
        padding: 1rem;
        font-size: 0.875rem;
        color: var(--text-light);
    }
    .filters-display strong {
        color: var(--text-dark);
    }

    .detail-card {
        background-color: var(--card-bg);
        border: 1px solid var(--border-color);
        border-radius: 0.75rem;
        box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.05);
        overflow: hidden; /* Biar table-responsive nurut sama border-radius */
    }

    .detail-card-header {
        padding: 1rem 1.5rem;
        background-color: var(--card-header-bg);
        border-bottom: 1px solid var(--border-color);
        font-weight: 600;
        font-size: 1.1rem;
    }

    .table {
        margin-bottom: 0;
    }

    .table th, .table td {
        vertical-align: middle;
        padding: 1rem 1.5rem;
        border-bottom: 1px solid var(--table-border-color);
    }

    .table thead th {
        color: var(--text-light);
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.05em;
        border-bottom-width: 2px;
        border-color: var(--border-color);
        background-color: var(--card-header-bg);
    }
    
    .table tbody tr:last-child td {
        border-bottom: none;
    }

    .point-value {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--text-danger);
    }
</style>

<div class="container py-4 py-lg-5">
    
    <!-- ✅ HEADER BARU: Lebih Rapi & Profesional -->
    <div class="page-header">
        <div>
            <h1 class="page-title">Detail Reward</h1>
            <p class="text-muted mb-0">Rincian lengkap reward yang tercatat.</p>
        </div>
        <?php
        $filter_qs = "";
        if (!empty($filter_bagian)) $filter_qs .= "&bagian=" . urlencode($filter_bagian);
        if (!empty($filter_jp)) $filter_qs .= "&jenis_reward=" . urlencode($filter_jp);
        ?>
        <a href="detail_arsip_santri.php?arsip_id=<?= $arsip_id ?>&id=<?= $santri_id ?><?= $filter_qs ?>" class="btn btn-back">
            <i class="fas fa-arrow-left me-2"></i>Kembali
        </a>
    </div>

    <div class="row g-4">
        <!-- ✅ KIRI: SUMMARY CARD (Info Santri + Statistik jadi satu) -->
        <div class="col-lg-4">
            <div class="summary-card">
                <h2 class="santri-name text-dark mb-1" style="letter-spacing: -0.5px; font-size: 1.6rem;"><?= htmlspecialchars($santri['nama']) ?></h2>
                <div class="text-secondary mb-4" style="font-size: 0.85rem; font-weight: 500;">
                    <i class="fas fa-user-graduate me-1 opacity-50"></i> Kls <?= htmlspecialchars($santri['kelas']) ?> &nbsp;<span class="text-muted mx-1">•</span>&nbsp;
                    <i class="fas fa-bed me-1 opacity-50"></i> Kmr <?= htmlspecialchars($santri['kamar']) ?>
                </div>
                
                <div class="stats-grid">
                    <?php if ($filter_bagian === 'Pengabdian'): ?>
                        <div class="stat-item d-flex align-items-center p-3 rounded-3" style="grid-column: 1 / -1; background-color: #fff1f2; border: 1px solid #ffe4e6;">
                            <div class="d-flex align-items-center justify-content-center rounded-circle me-3 flex-shrink-0" style="width: 48px; height: 48px; background-color: #fecdd3; color: #e11d48;">
                                <i class="fas fa-clock fs-4"></i>
                            </div>
                            <div>
                                <div class="stat-number text-danger" style="font-size: 2.2rem;"><?= $total_reward ?></div>
                                <div class="stat-label text-danger fw-medium" style="opacity: 0.9;">Total Keterlambatan (Kali)</div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="stat-item">
                            <div class="stat-number"><?= $total_reward ?></div>
                            <div class="stat-label">Total Reward</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?= $total_poin ?></div>
                            <div class="stat-label">Total Poin</div>
                        </div>
                        <div class="stat-item" style="grid-column: 1 / -1; padding-top: 1rem; border-top: 1px dashed var(--border-color);">
                            <div class="stat-number <?= $poin_bersih_display > 0 ? 'text-danger' : 'text-success' ?>"><?= $poin_bersih_display ?></div>
                            <div class="stat-label">Poin Bersih Periode</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="filters-display mt-3">
                <strong><i class="fas fa-filter me-2"></i>Filter Aktif:</strong><br>
                <i class="fas fa-calendar-alt fa-fw me-1"></i> <?= date('d M Y', strtotime($arsip['tanggal_mulai'])) ?> s/d <?= date('d M Y', strtotime($arsip['tanggal_selesai'])) ?><br>
                <?php if ($filter_bagian): ?>
                    <i class="fas fa-tag fa-fw me-1"></i> Bagian: <strong><?= htmlspecialchars($filter_bagian) ?></strong><br>
                <?php endif; ?>
            </div>
        </div>

        <!-- ✅ KANAN: TABEL RINCIAN PELANGGARAN -->
        <div class="col-lg-8">
            <div class="detail-card">
                <div class="detail-card-header">
                    <i class="fas fa-list-ul me-2"></i>Rincian Reward
                </div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th class="text-center" style="width: 5%;">No</th>
                                <th>Tanggal</th>
                                <th><?= $filter_bagian === 'Pengabdian' ? 'Nama Keterlambatan' : 'Nama Reward' ?></th>
                                <?php if ($filter_bagian !== 'Pengabdian'): ?>
                                <th class="text-center">Poin</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($detail_list)): ?>
                                <tr><td colspan="4" class="text-center p-5 text-muted"><i class="fas fa-check-circle fa-3x mb-3"></i><br>Tidak ada data reward ditemukan dengan filter ini.</td></tr>
                            <?php else: ?>
                                <?php foreach ($detail_list as $index => $row): 
                                    $info = getKategoriInfo($row['kategori']);
                                ?>
                                <tr>
                                    <td class="text-center fw-bold text-muted"><?= $index + 1 ?></td>
                                    <td>
                                        <div class="fw-500"><?= date('d M Y', strtotime($row['tanggal'])) ?></div>
                                        <small class="text-muted"><?= date('H:i', strtotime($row['tanggal'])) ?> WIB</small>
                                    </td>
                                    <td>
                                        <?php 
                                            $is_telat = (strpos(strtolower($row['nama_reward']), 'telat') !== false || strpos(strtolower($row['nama_reward']), 'kbm') !== false);
                                            
                                            if ($filter_bagian === 'Pengabdian' || $is_telat): 
                                                $telatInfo = getTelatInfo($row['nama_reward']);
                                        ?>
                                            <div class="d-flex align-items-center">
                                                <div class="d-flex align-items-center justify-content-center rounded-circle me-3 flex-shrink-0" style="width: 40px; height: 40px; background-color: <?= $telatInfo['bg'] ?>; color: <?= $telatInfo['text'] ?>;">
                                                    <i class="fas <?= $telatInfo['icon'] ?> fs-5"></i>
                                                </div>
                                                <div style="min-width: 0;">
                                                    <div class="fw-bold text-truncate" style="color: <?= $telatInfo['text'] ?>; font-size: 1.05rem;">
                                                        <?php if ($telatInfo['label'] !== 'Lainnya') echo 'Telat '; ?>
                                                        <?= $telatInfo['label'] !== 'Lainnya' ? $telatInfo['label'] : htmlspecialchars(format_typing($row['nama_reward'])) ?>
                                                    </div>
                                                    <div class="d-flex align-items-center flex-wrap gap-2 mt-1">
                                                        <span class="text-muted small text-truncate" style="max-width: 200px;"><?= $telatInfo['desc'] ?></span>
                                                        <?php if ($filter_bagian !== 'Pengabdian'): ?>
                                                            <span class="badge bg-<?= $info['color'] ?> bg-opacity-10 text-<?= $info['color'] ?> px-2 py-0 border border-<?= $info['color'] ?> border-opacity-25" style="font-size: 0.65rem; font-weight: 600;"><?= htmlspecialchars(format_typing($row['kategori'])) ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="d-flex align-items-center">
                                                <div class="d-flex align-items-center justify-content-center rounded-circle me-3 flex-shrink-0 bg-<?= $info['color'] ?> bg-opacity-10 text-<?= $info['color'] ?>" style="width: 40px; height: 40px;">
                                                    <i class="fas <?= $info['icon'] ?> fs-5"></i>
                                                </div>
                                                <div style="min-width: 0;">
                                                    <div class="fw-bold text-dark text-truncate" style="font-size: 1.05rem;"><?= htmlspecialchars(format_typing($row['nama_reward'])) ?></div>
                                                    <div class="mt-1">
                                                        <span class="badge bg-<?= $info['color'] ?> bg-opacity-10 text-<?= $info['color'] ?> px-2 py-0 border border-<?= $info['color'] ?> border-opacity-25" style="font-size: 0.65rem; font-weight: 600;"><?= htmlspecialchars(format_typing($row['kategori'])) ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <?php if ($filter_bagian !== 'Pengabdian'): ?>
                                    <td class="text-center">
                                        <span class="point-value"><?= $row['poin'] ?></span>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
