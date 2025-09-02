<?php 
require_once __DIR__ . '/../header.php';
guard('rekap_view_umum'); 

// --- PHP LOGIC (TIDAK ADA PERUBAHAN) ---
// Logika untuk mengambil data dari URL dan database tetap sama karena sudah solid.

// Ambil SEMUA filter dari URL
if (!isset($_GET['id'], $_GET['start_date'], $_GET['end_date'])) {
    die("<div class='container my-4'><div class='alert alert-danger'>Informasi tidak lengkap untuk menampilkan detail.</div></div>");
}

$santri_id = (int)$_GET['id'];
$start_date = $_GET['start_date'];
$end_date = $_GET['end_date'];
$filter_kamar = $_GET['kamar'] ?? null;
$filter_bagian = $_GET['bagian'] ?? null;
$filter_kategori = $_GET['kategori'] ?? null;

// Query 1: Ambil data profil santri
$stmt_santri = $conn->prepare("SELECT nama, kelas, kamar FROM santri WHERE id = ?");
$stmt_santri->bind_param("i", $santri_id);
$stmt_santri->execute();
$santri = $stmt_santri->get_result()->fetch_assoc();

if (!$santri) {
    die("<div class='container my-4'><div class='alert alert-danger'>Data santri tidak ditemukan.</div></div>");
}

// Query 2: Ambil detail pelanggaran
$sql_detail = "
    SELECT p.tanggal, jp.nama_pelanggaran, jp.kategori, jp.poin
    FROM pelanggaran p
    JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id
    WHERE p.santri_id = ?
      AND DATE(p.tanggal) BETWEEN ? AND ?
      AND p.jenis_pelanggaran_id != 3
";
$params_detail = [$santri_id, $start_date, $end_date];
$types_detail = "iss";
if ($filter_bagian) {
    $sql_detail .= " AND jp.bagian = ?";
    $params_detail[] = $filter_bagian;
    $types_detail .= "s";
}
if ($filter_kategori) {
    $sql_detail .= " AND jp.kategori = ?";
    $params_detail[] = $filter_kategori;
    $types_detail .= "s";
}
$sql_detail .= " ORDER BY p.tanggal DESC";
$stmt_detail = $conn->prepare($sql_detail);
$stmt_detail->bind_param($types_detail, ...$params_detail);
$stmt_detail->execute();
$detail_list = $stmt_detail->get_result()->fetch_all(MYSQLI_ASSOC);

// Kalkulasi statistik
$total_pelanggaran = count($detail_list);
$total_poin = array_sum(array_column($detail_list, 'poin'));

// Helper function untuk ikon
function getKategoriInfo($kategori) {
    return match (strtolower($kategori)) {
        'berat' => ['color' => 'danger', 'icon' => 'fa-exclamation-triangle'],
        'sedang' => ['color' => 'warning', 'icon' => 'fa-exclamation-circle'],
        'ringan' => ['color' => 'success', 'icon' => 'fa-info-circle'],
        default => ['color' => 'secondary', 'icon' => 'fa-question-circle'],
    };
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
        border: 1px solid var(--border-color);
        border-radius: 0.75rem;
        box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.05);
        padding: 1.5rem;
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
            <h1 class="page-title">Detail Pelanggaran</h1>
            <p class="text-muted mb-0">Rincian lengkap pelanggaran yang tercatat.</p>
        </div>
        <?php
            $kembali_url = "umum.php?start_date=" . urlencode($start_date) 
                         . "&end_date=" . urlencode($end_date) 
                         . "&kamar=" . urlencode($filter_kamar ?? '') 
                         . "&bagian=" . urlencode($filter_bagian ?? '')
                         . "&kategori=" . urlencode($filter_kategori ?? '');
        ?>
        <a href="<?= $kembali_url ?>" class="btn btn-back"><i class="fas fa-arrow-left me-2"></i>Kembali ke Rekap</a>
    </div>

    <div class="row g-4">
        <!-- ✅ KIRI: SUMMARY CARD (Info Santri + Statistik jadi satu) -->
        <div class="col-lg-4">
            <div class="summary-card">
                <h2 class="santri-name"><?= htmlspecialchars($santri['nama']) ?></h2>
                <p class="santri-info">Kelas: <strong><?= htmlspecialchars($santri['kelas']) ?></strong> | Kamar: <strong><?= htmlspecialchars($santri['kamar']) ?></strong></p>
                
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-number"><?= $total_pelanggaran ?></div>
                        <div class="stat-label">Total Pelanggaran</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?= $total_poin ?></div>
                        <div class="stat-label">Total Poin</div>
                    </div>
                </div>
            </div>
            <div class="filters-display mt-3">
                <strong><i class="fas fa-filter me-2"></i>Filter Aktif:</strong><br>
                <i class="fas fa-calendar-alt fa-fw me-1"></i> <?= date('d M Y', strtotime($start_date)) ?> s/d <?= date('d M Y', strtotime($end_date)) ?><br>
                <?php if ($filter_bagian): ?>
                    <i class="fas fa-tag fa-fw me-1"></i> Bagian: <strong><?= htmlspecialchars($filter_bagian) ?></strong><br>
                <?php endif; ?>
                <?php if ($filter_kategori): ?>
                    <i class="fas fa-exclamation-circle fa-fw me-1"></i> Kategori: <strong><?= htmlspecialchars($filter_kategori) ?></strong>
                <?php endif; ?>
            </div>
        </div>

        <!-- ✅ KANAN: TABEL RINCIAN PELANGGARAN -->
        <div class="col-lg-8">
            <div class="detail-card">
                <div class="detail-card-header">
                    <i class="fas fa-list-ul me-2"></i>Rincian Pelanggaran
                </div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th class="text-center" style="width: 5%;">No</th>
                                <th>Tanggal</th>
                                <th>Nama Pelanggaran</th>
                                <th class="text-center">Poin</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($detail_list)): ?>
                                <tr><td colspan="4" class="text-center p-5 text-muted"><i class="fas fa-check-circle fa-3x mb-3"></i><br>Tidak ada data pelanggaran ditemukan dengan filter ini.</td></tr>
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
                                        <i class="fas <?= $info['icon'] ?> me-2 text-<?= $info['color'] ?>" title="<?= htmlspecialchars($row['kategori']) ?>"></i>
                                        <?= htmlspecialchars($row['nama_pelanggaran']) ?>
                                        <span class="badge bg-<?= $info['color'] ?> bg-opacity-10 text-<?= $info['color'] ?> ms-2"><?= htmlspecialchars($row['kategori']) ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="point-value"><?= $row['poin'] ?></span>
                                    </td>
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

<?php require_once __DIR__ . '/../footer.php'; ?>