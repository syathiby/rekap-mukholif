<?php
// arsip/pages/arsip_rapot_tahunan.php
require_once __DIR__ . '/../../bootstrap/init.php';

guard('arsip_view');

$arsip_id = (int)($_GET['id'] ?? 0);
if ($arsip_id <= 0) die('Arsip tidak valid.');

$stmt = $conn->prepare("SELECT judul, tanggal_mulai, tanggal_selesai FROM arsip WHERE id = ?");
$stmt->bind_param('i', $arsip_id);
$stmt->execute();
$arsip = $stmt->get_result()->fetch_assoc();
if (!$arsip) die('Arsip tidak ditemukan.');

$page_title = "Rapor Tahunan - Arsip " . htmlspecialchars($arsip['judul']);

// Get periods from arsip_data_rapot_tahunan
$periode_list = [];
$p_stmt = $conn->prepare("SELECT DISTINCT periode FROM arsip_data_rapot_tahunan WHERE arsip_id = ? ORDER BY periode DESC");
$p_stmt->bind_param('i', $arsip_id);
$p_stmt->execute();
$res_p = $p_stmt->get_result();
while ($row = $res_p->fetch_assoc()) {
    if (!empty($row['periode'])) {
        $periode_list[] = $row['periode'];
    }
}
$p_stmt->close();

$filter_periode = $_GET['periode'] ?? ($periode_list[0] ?? '');

// Get kamars from arsip_data_rapot_tahunan
$kamar_list = [];
if (!empty($filter_periode)) {
    $k_stmt = $conn->prepare("SELECT DISTINCT kamar FROM arsip_data_rapot_tahunan WHERE arsip_id = ? AND periode = ? ORDER BY kamar ASC");
    $k_stmt->bind_param('is', $arsip_id, $filter_periode);
    $k_stmt->execute();
    $res_k = $k_stmt->get_result();
    while ($row = $res_k->fetch_assoc()) {
        if (!empty($row['kamar'])) {
            $kamar_list[] = $row['kamar'];
        }
    }
    $k_stmt->close();
}

$filter_kamar = $_GET['kamar'] ?? '';

// Ambil data stats
$rows = [];
foreach ($kamar_list as $nama_kamar) {
    if (!empty($filter_kamar) && $nama_kamar !== $filter_kamar) continue;

    $r_stmt = $conn->prepare("
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN status = 'DRAFT'    THEN 1 ELSE 0 END) as draft,
            SUM(CASE WHEN status = 'APPROVED' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'EXPORTED' THEN 1 ELSE 0 END) as exported
        FROM arsip_data_rapot_tahunan
        WHERE arsip_id = ? AND kamar = ? AND periode = ?
    ");
    $r_stmt->bind_param("iss", $arsip_id, $nama_kamar, $filter_periode);
    $r_stmt->execute();
    $r_data = $r_stmt->get_result()->fetch_assoc();
    $r_stmt->close();

    $jumlah_santri = (int)$r_data['total'];

    $rows[] = [
        'kamar'         => $nama_kamar,
        'jumlah_santri' => $jumlah_santri,
        'jumlah_santri_aktif' => $jumlah_santri,
        'sudah_dibuat'  => (int)$r_data['total'],
        'draft'         => (int)$r_data['draft'],
        'approved'      => (int)$r_data['approved'],
        'exported'      => (int)$r_data['exported'],
    ];
}

require_once __DIR__ . '/../../layouts/header.php';
?>

<style>
    .rt-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        box-shadow: 0 1px 4px rgba(0,0,0,.05);
        transition: box-shadow .2s;
    }
    .rt-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,.08); }

    .status-pill {
        display: inline-flex; align-items: center; gap: .35rem;
        font-size: .72rem; font-weight: 600;
        padding: .3rem .75rem; border-radius: 9999px;
    }
    .status-pill.belum   { background: #f1f5f9; color: #64748b; }
    .status-pill.draft   { background: #fef3c7; color: #b45309; }
    .status-pill.done    { background: #d1fae5; color: #065f46; }
    .status-pill.partial { background: #e0e7ff; color: #4338ca; }

    .progress-bar-custom {
        height: 6px; border-radius: 999px;
        background: #e2e8f0; overflow: hidden;
    }
    .progress-bar-custom div { height: 100%; border-radius: 999px; background: #3b82f6; transition: width .4s; }

    .rt-table th { background: #f8fafc; font-size: .78rem; font-weight: 700;
                   text-transform: uppercase; color: #64748b; border-bottom: 2px solid #e2e8f0; white-space: nowrap; }
    .rt-table td { vertical-align: middle; border-color: #f1f5f9; white-space: nowrap; }
    .rt-table tr:hover td { background: #f8fafc; }

    @media (max-width: 768px) {
        .rt-card { border-radius: .75rem; }
        .info-banner-text { font-size: .8rem !important; }
        .btn-action-text { display: inline-block; font-size: .75rem; }
        .rt-table td .btn { padding: .25rem .6rem; }
    }
</style>

<div class="container-fluid py-4 px-3 px-md-4">

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="../index.php" class="text-decoration-none">Arsip</a></li>
            <li class="breadcrumb-item"><a href="../view.php?id=<?= $arsip_id ?>" class="text-decoration-none"><?= htmlspecialchars($arsip['judul']) ?></a></li>
            <li class="breadcrumb-item active">Rapor Tahunan</li>
        </ol>
    </nav>

    <!-- Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="fw-bolder text-dark mb-1">
                <i class="fas fa-file-invoice text-primary me-2"></i>Arsip Rapor Tahunan
            </h3>
            <p class="text-muted mb-0">Lihat data rapor tahunan santri per kamar pada periode arsip ini.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="../view.php?id=<?= $arsip_id ?>" class="btn btn-light border fw-medium shadow-sm">
                <i class="fas fa-arrow-left me-1"></i> Kembali ke Arsip
            </a>
        </div>
    </div>

    <!-- Filter -->
    <div class="rt-card p-3 mb-4">
        <form action="" method="GET" class="row g-3 align-items-end">
            <input type="hidden" name="id" value="<?= $arsip_id ?>">
            <div class="col-12 col-md-4">
                <label class="form-label text-muted small fw-bold mb-1">Periode (Tahun Ajaran)</label>
                <select name="periode" class="form-select">
                    <?php if (empty($periode_list)): ?>
                        <option value="">Tidak ada data</option>
                    <?php else: ?>
                        <?php foreach ($periode_list as $p): ?>
                            <option value="<?= $p ?>" <?= $filter_periode === $p ? 'selected' : '' ?>><?= $p ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label text-muted small fw-bold mb-1">Filter Kamar</label>
                <select name="kamar" class="form-select">
                    <option value="">Semua Kamar</option>
                    <?php foreach ($kamar_list as $km): ?>
                        <option value="<?= htmlspecialchars($km) ?>" <?= $filter_kamar === $km ? 'selected' : '' ?>>
                            <?= htmlspecialchars($km) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-auto d-flex gap-2">
                <button type="submit" class="btn btn-primary fw-medium px-4">
                    <i class="fas fa-filter me-1"></i> Tampilkan
                </button>
                <a href="?id=<?= $arsip_id ?>" class="btn btn-light border text-secondary fw-medium">
                    <i class="fas fa-sync-alt me-1"></i> Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Tabel Kamar -->
    <div class="rt-card overflow-hidden">
        <div class="table-responsive">
            <table class="table rt-table mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Nama Kamar</th>
                        <th class="text-center">Santri</th>
                        <th class="text-center">Rapor Dibuat</th>
                        <th>Progress</th>
                        <th class="text-center">Status</th>
                        <th class="text-center pe-4">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="fas fa-inbox fa-2x d-block mb-2 opacity-25"></i>
                                Tidak ada data rapor tahunan ditemukan untuk arsip ini.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <?php
                                $aktif   = (int)$row['jumlah_santri_aktif'];
                                $pct     = $aktif > 0 ? round(($row['sudah_dibuat'] / $aktif) * 100) : 0;
                                $all_done = ($row['sudah_dibuat'] >= $aktif && $aktif > 0);

                                if ($aktif === 0) {
                                    $pill = '<span class="status-pill text-muted" style="background:#f1f5f9;"><i class="fas fa-ban fa-sm"></i> Tidak Tersedia</span>';
                                } elseif ($row['sudah_dibuat'] === 0) {
                                    $pill = '<span class="status-pill belum"><i class="fas fa-circle-notch fa-sm"></i> Belum Dibuat</span>';
                                } elseif ($all_done && $row['approved'] === $row['sudah_dibuat']) {
                                    $pill = '<span class="status-pill done"><i class="fas fa-check-circle fa-sm"></i> Approved</span>';
                                } elseif ($all_done) {
                                    $pill = '<span class="status-pill draft"><i class="fas fa-pen fa-sm"></i> Draft</span>';
                                } else {
                                    $pill = '<span class="status-pill partial"><i class="fas fa-spinner fa-sm"></i> Sebagian</span>';
                                }

                                $kamar_enc = urlencode($row['kamar']);
                                $per_enc   = urlencode($filter_periode);
                            ?>
                            <tr>
                                <td class="ps-4 fw-semibold"><?= htmlspecialchars($row['kamar']) ?></td>
                                <td class="text-center">
                                    <span class="badge bg-light text-dark border px-3"><?= $row['jumlah_santri'] ?> santri</span>
                                </td>
                                <td class="text-center">
                                    <span class="fw-bold <?= $aktif === 0 ? 'text-muted' : 'text-primary' ?>"><?= $row['sudah_dibuat'] ?></span>
                                    <span class="text-muted">/ <?= $aktif ?></span>
                                </td>
                                <td style="min-width:130px;">
                                    <?php if ($aktif === 0): ?>
                                        <div class="text-muted small fw-semibold fst-italic"><i class="fas fa-exclamation-circle opacity-50"></i> Data kosong</div>
                                    <?php else: ?>
                                        <div class="progress-bar-custom mb-1">
                                            <div style="width:<?= $pct ?>%"></div>
                                        </div>
                                        <small class="text-muted"><?= $pct ?>%</small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center"><?= $pill ?></td>
                                <td class="text-center pe-4">
                                    <div class="d-flex gap-2 justify-content-center flex-wrap">
                                        <?php if ($row['sudah_dibuat'] > 0): ?>
                                            <a href="arsip_rapot_tahunan_list.php?id=<?= $arsip_id ?>&kamar=<?= $kamar_enc ?>&periode=<?= $per_enc ?>"
                                               class="btn btn-sm btn-outline-primary fw-medium"
                                               title="Lihat daftar rapor">
                                                <i class="fas fa-list"></i>
                                                <span class="btn-action-text ms-1">Lihat</span>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <!-- Footer summary -->
        <div class="px-4 py-2 border-top d-flex align-items-center gap-3 flex-wrap" style="background:#f8fafc;">
            <small class="text-muted">Periode: <strong><?= htmlspecialchars($filter_periode) ?></strong></small>
            <?php
                $total_santri = array_sum(array_column($rows, 'jumlah_santri'));
                $total_dibuat = array_sum(array_column($rows, 'sudah_dibuat'));
            ?>
            <small class="text-muted ms-auto">Total rapor arsip: <strong class="text-primary"><?= $total_dibuat ?></strong> / <?= $total_santri ?> santri</small>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
