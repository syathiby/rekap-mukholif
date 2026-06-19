<?php
// rapot/crud_tahunan/index.php
// Halaman Daftar & Generate Rapor Tahunan — terintegrasi DB
require_once __DIR__ . '/../../bootstrap/init.php';
require_once __DIR__ . '/../config/helper.php';

guard(['rapot_view', 'rapot_create', 'rapot_cetak', 'rapot_delete']);

$can_create = has_permission('rapot_create');
$can_cetak  = has_permission('rapot_cetak');
$can_delete = has_permission('rapot_delete');

$page_title = "Rapor Tahunan";

// ── Flash message kini ditangani secara global di footer.php ─────────────────
// ── Ambil daftar kamar & periode ───────────────────────────
$bulan_sekarang = (int)date('n');
$tahun_sekarang = (int)date('Y');

// Tahun ajaran baru dimulai setiap bulan Juli (bulan 7)
$tahun_ajaran_aktif = ($bulan_sekarang < 7) ? $tahun_sekarang - 1 : $tahun_sekarang;

$periode_list   = [];
// Tampilkan 3 tahun ajaran terakhir
for ($i = 0; $i < 3; $i++) {
    $y1 = $tahun_ajaran_aktif - $i;
    $y2 = $y1 + 1;
    $periode_list[] = "$y1/$y2";
}

$kamar_list = $conn->query("
    SELECT DISTINCT kamar FROM santri
    WHERE kamar IS NOT NULL AND kamar != 0
    ORDER BY kamar ASC
")->fetch_all(MYSQLI_ASSOC);

// ── Filter aktif ────────────────────────────────────────────
$filter_periode = $_GET['periode'] ?? $periode_list[0];
$filter_kamar   = $_GET['kamar']   ?? '';

// ── Ambil data kamar + status rapor tahunan ─────────────────
$rows = [];
foreach ($kamar_list as $km) {
    $nama_kamar = $km['kamar'];

    // Hitung jumlah santri di kamar ini
    $s_stmt = $conn->prepare("SELECT COUNT(*) as total FROM santri WHERE kamar = ?");
    $s_stmt->bind_param("s", $nama_kamar);
    $s_stmt->execute();
    $jumlah_santri = (int)($s_stmt->get_result()->fetch_assoc()['total']);
    $s_stmt->close();

    // Hitung santri yang punya data bulanan (bisa dibuat rapor tahunan)
    $tahun_awal = (int)explode('/', $filter_periode)[0];
    $tahun_akhir = $tahun_awal + 1;
    $a_stmt = $conn->prepare("
        SELECT COUNT(DISTINCT s.id) as total 
        FROM santri s
        JOIN rapot_kepengasuhan rk ON s.id = rk.santri_id
        WHERE s.kamar = ? AND (rk.tahun = ? OR rk.tahun = ?)
    ");
    $a_stmt->bind_param("sii", $nama_kamar, $tahun_awal, $tahun_akhir);
    $a_stmt->execute();
    $jumlah_santri_aktif = (int)($a_stmt->get_result()->fetch_assoc()['total']);
    $a_stmt->close();

    // Hitung jumlah rapor tahunan yang sudah di-generate untuk kamar + periode ini
    $r_stmt = $conn->prepare("
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN status = 'DRAFT'    THEN 1 ELSE 0 END) as draft,
            SUM(CASE WHEN status = 'APPROVED' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'EXPORTED' THEN 1 ELSE 0 END) as exported
        FROM rapot_tahunan
        WHERE kamar = ? AND periode = ?
    ");
    $r_stmt->bind_param("ss", $nama_kamar, $filter_periode);
    $r_stmt->execute();
    $r_data = $r_stmt->get_result()->fetch_assoc();
    $r_stmt->close();

    $rows[] = [
        'kamar'         => $nama_kamar,
        'jumlah_santri' => $jumlah_santri,
        'jumlah_santri_aktif' => $jumlah_santri_aktif,
        'sudah_dibuat'  => (int)$r_data['total'],
        'draft'         => (int)$r_data['draft'],
        'approved'      => (int)$r_data['approved'],
        'exported'      => (int)$r_data['exported'],
    ];
}

// Filter kamar jika dipilih
if (!empty($filter_kamar)) {
    $rows = array_filter($rows, fn($r) => $r['kamar'] === $filter_kamar);
    $rows = array_values($rows);
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
            <li class="breadcrumb-item"><a href="../index.php" class="text-decoration-none">Rapot Kepengasuhan</a></li>
            <li class="breadcrumb-item active">Rapor Tahunan</li>
        </ol>
    </nav>

    <!-- Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="fw-bolder text-dark mb-1">
                <i class="fas fa-star text-warning me-2"></i>Rapor Tahunan
                <button type="button" class="btn btn-sm btn-link text-info p-0 ms-2" data-bs-toggle="modal" data-bs-target="#guideModal" title="Buku Panduan">
                    <i class="fas fa-info-circle fs-5"></i>
                </button>
            </h3>
            <p class="text-muted mb-0">Generate dan kelola rapor tahunan santri per kamar.</p>
        </div>
        <div class="d-flex gap-2">
            <?php if ($can_create): ?>
            <a href="../index.php" class="btn btn-light border fw-medium shadow-sm">
                <i class="fas fa-arrow-left me-1"></i> Rapot Bulanan
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Flash message kini ditangani oleh SweetAlert Toast di footer.php -->

    <!-- Info banner -->
    <div class="d-flex align-items-start gap-3 p-3 p-md-4 mb-4 rounded-4"
         style="background: linear-gradient(to right, #f8fafc, #ffffff); border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02);">
        <div class="flex-shrink-0 mt-1">
            <div style="width: 40px; height: 40px; border-radius: 10px; background: #e0f2fe; color: #0284c7; display: flex; align-items: center; justify-content: center;">
                <i class="fas fa-info-circle fa-lg"></i>
            </div>
        </div>
        <div>
            <h6 class="fw-bold mb-1 text-dark" style="font-size: .95rem; letter-spacing: -0.01em;">Info Rapor Tahunan</h6>
            <p class="mb-0 text-secondary info-banner-text" style="font-size: 0.85rem; line-height: 1.5;">
                Sistem akan merekap rata-rata nilai dan total poin pelanggaran/reward santri selama 1 tahun ajaran secara otomatis.<br>
                <span class="text-primary mt-1 d-inline-block fw-medium">Pastikan semua rapor bulanan sudah diisi lengkap sebelum melakukan Generate.</span>
            </p>
        </div>
    </div>

    <!-- Filter -->
    <div class="rt-card p-3 mb-4">
        <form action="" method="GET" class="row g-3 align-items-end">
            <div class="col-12 col-md-4">
                <label class="form-label text-muted small fw-bold mb-1">Periode (Tahun Ajaran)</label>
                <select name="periode" class="form-select">
                    <?php foreach ($periode_list as $p): ?>
                        <option value="<?= $p ?>" <?= $filter_periode === $p ? 'selected' : '' ?>><?= $p ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label text-muted small fw-bold mb-1">Filter Kamar</label>
                <select name="kamar" class="form-select">
                    <option value="">Semua Kamar</option>
                    <?php foreach ($kamar_list as $km): ?>
                        <option value="<?= htmlspecialchars($km['kamar']) ?>" <?= $filter_kamar === $km['kamar'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($km['kamar']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-auto d-flex gap-2">
                <button type="submit" class="btn btn-primary fw-medium px-4">
                    <i class="fas fa-filter me-1"></i> Tampilkan
                </button>
                <a href="?" class="btn btn-light border text-secondary fw-medium">
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
                                Tidak ada data kamar ditemukan.
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
                                    <?php if ($row['jumlah_santri'] > $aktif): ?>
                                    <div class="mt-1" style="font-size:.7rem;color:#b45309;"><i class="fas fa-info-circle"></i> <?= $row['jumlah_santri'] - $aktif ?> absen data</div>
                                    <?php endif; ?>
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
                                        <?php if ($can_create): ?>
                                            <?php if ($aktif === 0): ?>
                                                <button type="button" class="btn btn-sm btn-secondary fw-medium shadow-sm opacity-50" disabled title="Tidak ada santri yang memiliki data bulanan">
                                                    <i class="fas fa-ban"></i>
                                                    <span class="btn-action-text ms-1">Generate</span>
                                                </button>
                                            <?php else: ?>
                                                <a href="generate.php?kamar=<?= $kamar_enc ?>&periode=<?= $per_enc ?>"
                                                   class="btn btn-sm btn-success fw-medium shadow-sm"
                                                   title="Generate Rapor Tahunan">
                                                    <i class="fas fa-magic"></i>
                                                    <span class="btn-action-text ms-1"><?= $row['sudah_dibuat'] > 0 ? 'Regenerate' : 'Generate' ?></span>
                                                </a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <?php if ($row['sudah_dibuat'] > 0): ?>
                                            <?php if ($can_cetak): ?>
                                            <a href="list.php?kamar=<?= $kamar_enc ?>&periode=<?= $per_enc ?>"
                                               class="btn btn-sm btn-outline-primary fw-medium"
                                               title="Lihat daftar rapor yang sudah di-generate">
                                                <i class="fas fa-list"></i>
                                                <span class="btn-action-text ms-1">Lihat</span>
                                            </a>
                                            <?php endif; ?>
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
            <small class="text-muted ms-auto">Total rapor dibuat: <strong class="text-primary"><?= $total_dibuat ?></strong> / <?= $total_santri ?> santri</small>
        </div>
    </div>

</div>

<!-- Modal Panduan -->
<div class="modal fade" id="guideModal" tabindex="-1" aria-labelledby="guideModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 1rem;">
      <div class="modal-header border-bottom-0 pb-0 mt-2 mx-2">
        <h5 class="modal-title fw-bolder text-dark" id="guideModalLabel">
            <i class="fas fa-spinner fa-spin text-primary me-2"></i>Memuat Panduan...
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body pt-3 px-4 pb-4 text-muted" id="guideModalBody">
         <div class="text-center py-4">
             <div class="spinner-border text-primary" role="status">
                 <span class="visually-hidden">Loading...</span>
             </div>
         </div>
      </div>
      <div class="modal-footer border-top-0 pt-0 pb-4 px-4">
        <button type="button" class="btn btn-primary w-100 fw-medium shadow-sm" style="border-radius: 0.75rem;" data-bs-dismiss="modal">Saya Mengerti</button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const guideModal = document.getElementById('guideModal');
    let guideLoaded = false;
    
    guideModal.addEventListener('show.bs.modal', function () {
        if (guideLoaded) return;
        
        fetch('../api/guide_tahunan.php')
            .then(response => response.json())
            .then(res => {
                if(res.status === 'success') {
                    document.getElementById('guideModalLabel').innerHTML = res.data.title;
                    document.getElementById('guideModalBody').innerHTML = res.data.content;
                    guideLoaded = true;
                } else {
                    document.getElementById('guideModalLabel').innerHTML = '<i class="fas fa-exclamation-triangle text-danger me-2"></i>Gagal Memuat';
                    document.getElementById('guideModalBody').innerHTML = '<div class="alert alert-danger">Gagal memuat panduan: ' + (res.message || 'Error tidak diketahui') + '</div>';
                }
            })
            .catch(err => {
                document.getElementById('guideModalLabel').innerHTML = '<i class="fas fa-exclamation-triangle text-danger me-2"></i>Koneksi Error';
                document.getElementById('guideModalBody').innerHTML = '<div class="alert alert-danger">Terjadi kesalahan saat menghubungi server.</div>';
            });
    });
});
</script>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
