<?php
// FILE: history_view.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../bootstrap/init.php';
guard('history_manage'); 
require_once __DIR__ . '/../../layouts/header.php'; 

// Tangkap filter dari URL biar pas balik filternya gak ilang
$start_date = $_GET['start_date'] ?? date('Y-m-d');
$end_date   = $_GET['end_date'] ?? date('Y-m-d');
$bagian     = $_GET['bagian'] ?? '';
$search     = trim($_GET['search'] ?? '');

$back_link = "index.php?start_date=" . urlencode($start_date) . "&end_date=" . urlencode($end_date) . "&bagian=" . urlencode($bagian) . "&search=" . urlencode($search);

// 1. Fetch Log Pelanggaran Individu
$query_ind = "
    SELECT 
        l.id,
        s.nama AS nama_santri,
        jp.nama_pelanggaran,
        jp.bagian,
        l.poin,
        l.tanggal_pelanggaran,
        l.dihapus_pada,
        u2.nama_lengkap AS penghapus_nama,
        'individu' AS tipe
    FROM log_history l
    LEFT JOIN santri s ON l.santri_id = s.id
    LEFT JOIN jenis_pelanggaran jp ON l.jenis_pelanggaran_id = jp.id
    LEFT JOIN users u2 ON l.dihapus_oleh = u2.id
    WHERE 1=1
";

// We'll apply filters in PHP for simplicity or skip if not matching.
// Since the main page filters by `tanggal_pelanggaran`, we'll do the same here, or maybe we just show everything?
// Usually log view shows all logs or filters by the same criteria. Let's not filter log by date unless requested, 
// but wait, the prompt says "Log Penghapusan" is a general view. Let's just list top 100 or list all for simplicity, or keep original logic (which showed all).
// The original `history_view.php` did not have WHERE filters for the log. It just ordered by `dihapus_pada DESC`.

$query_ind .= " ORDER BY l.dihapus_pada DESC LIMIT 1000";
$res_ind = mysqli_query($conn, $query_ind);
$data_individu = $res_ind ? mysqli_fetch_all($res_ind, MYSQLI_ASSOC) : [];

// 2. Fetch Log Pelanggaran Kebersihan
$query_keb = "
    SELECT 
        lhk.id,
        CONCAT('Kamar ', lhk.kamar) AS nama_santri,
        CONCAT('Kebersihan: ', IFNULL(lhk.catatan, '')) AS nama_pelanggaran,
        'pengabdian' AS bagian,
        0 AS poin,
        lhk.tanggal_pelanggaran,
        lhk.dihapus_pada,
        u2.nama_lengkap AS penghapus_nama,
        'kebersihan' AS tipe
    FROM log_history_kebersihan lhk
    LEFT JOIN users u2 ON lhk.dihapus_oleh = u2.id
    ORDER BY lhk.dihapus_pada DESC LIMIT 1000
";
$res_keb = mysqli_query($conn, $query_keb);
$data_kebersihan = $res_keb ? mysqli_fetch_all($res_keb, MYSQLI_ASSOC) : [];

// 3. Merge and Sort by dihapus_pada DESC
$all_logs = array_merge($data_individu, $data_kebersihan);
usort($all_logs, function($a, $b) {
    return strtotime($b['dihapus_pada']) - strtotime($a['dihapus_pada']);
});

?>

<style>
    :root {
        --primary: #4f46e5;
        --secondary: #64748b;
        --bg-color: #f1f5f9;
        --card-bg: rgba(255, 255, 255, 0.95);
        --danger: #ef4444;
        --warning: #f59e0b;
    }

    body { background-color: var(--bg-color); font-family: 'Inter', sans-serif; }
    
    .glass-card {
        background: var(--card-bg);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.5);
        border-radius: 1.2rem;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05);
    }

    .table-responsive { border-radius: 1.2rem; overflow-x: auto; }
    .table { margin-bottom: 0; white-space: nowrap; }
    .table thead th {
        background-color: #f8fafc; color: #475569; font-weight: 700; font-size: 0.75rem;
        text-transform: uppercase; padding: 1.2rem 1.5rem; border-bottom: 2px solid #e2e8f0;
    }
    .table tbody td { padding: 1.2rem 1.5rem; vertical-align: middle; border-bottom: 1px solid #f1f5f9; font-size: 0.95rem; }
    .table tbody tr:hover { background-color: #f8fafc; }
    
    .badge-soft-secondary { background-color: #f1f5f9; color: var(--secondary); border: 1px solid #cbd5e1; }
    .badge-soft-warning { background-color: #fef3c7; color: #d97706; border: 1px solid #fde68a; }

    .btn-back {
        width: 44px; height: 44px;
        display: flex; align-items: center; justify-content: center;
        border-radius: 50%; background: white; border: 1px solid #e2e8f0;
        color: var(--secondary); transition: all 0.2s;
    }
    .btn-back:hover { background: var(--primary); color: white; transform: translateX(-3px); }
</style>

<div class="container py-4">
    <div class="d-flex align-items-center mb-4 gap-3">
        <a href="<?= $back_link ?>" class="btn-back shadow-sm" title="Kembali ke Riwayat">
            <i class="fas fa-arrow-left"></i>
        </a>
        
        <div>
            <h3 class="fw-bolder text-dark mb-1"><i class="fas fa-trash-restore text-warning me-2"></i>Log Penghapusan</h3>
            <p class="text-muted mb-0">Jejak audit data pelanggaran individu dan kebersihan yang telah dibatalkan.</p>
        </div>
    </div>

    <div class="alert alert-warning border-0 shadow-sm rounded-4 d-flex align-items-center mb-4 p-3 bg-opacity-10" style="background-color: #fffbeb; border: 1px solid #fcd34d;">
        <i class="fas fa-info-circle me-3 fs-4 text-warning"></i>
        <div class="small text-dark">
            Data di bawah ini adalah pelanggaran yang <strong>sudah dibatalkan</strong>. Jika ada poin sanksi, poin tersebut telah dikembalikan ke status semula.
        </div>
    </div>

    <div class="card glass-card overflow-hidden">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th class="text-center" width="5%">No</th>
                        <th>Detail Pelanggaran (Dihapus)</th>
                        <th class="text-center">Poin Batal</th>
                        <th>Waktu Dihapus</th>
                        <th>Dihapus Oleh</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($all_logs) > 0): ?>
                        <?php $no = 1; foreach($all_logs as $row): ?>
                            <tr>
                                <td class="text-center text-muted fw-medium"><?= $no++ ?></td>
                                <td>
                                    <div class="fw-bold text-dark mb-1">
                                        <?php if ($row['tipe'] === 'kebersihan'): ?>
                                            <i class="fas fa-door-closed text-muted me-1"></i>
                                        <?php endif; ?>
                                        <?= htmlspecialchars($row['nama_santri'] ?? 'Data Terhapus') ?>
                                    </div>
                                    <div class="d-flex align-items-center gap-2 mt-1">
                                        <span class="text-secondary small fw-medium">
                                            <?= htmlspecialchars($row['nama_pelanggaran']) ?>
                                        </span>
                                        <?php if ($row['tipe'] === 'kebersihan'): ?>
                                            <span class="badge badge-soft-warning fw-normal px-2 rounded-1" style="font-size: 0.7rem;"><i class="fas fa-broom me-1"></i> Kebersihan</span>
                                        <?php else: ?>
                                            <span class="badge badge-soft-secondary fw-normal px-2 rounded-1" style="font-size: 0.7rem;">
                                                <?= htmlspecialchars($row['bagian']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-muted x-small mt-1 opacity-75" style="font-size: 0.75rem;">
                                        <i class="fas fa-calendar-alt me-1"></i> 
                                        Tgl Asli: <?= date('d M Y, H:i', strtotime($row['tanggal_pelanggaran'])) ?>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <?php if ($row['tipe'] === 'kebersihan'): ?>
                                        <span class="text-muted small">-</span>
                                    <?php else: ?>
                                        <span class="badge badge-soft-secondary rounded-pill px-3 py-1 fw-bold">
                                            <?= $row['poin'] ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-medium text-danger">
                                        <?= date('d M Y', strtotime($row['dihapus_pada'])) ?>
                                    </div>
                                    <div class="small text-muted mt-1">
                                        <i class="far fa-clock me-1"></i><?= date('H:i', strtotime($row['dihapus_pada'])) ?> WIB
                                    </div>
                                </td>
                                <td>
                                    <?php if($row['penghapus_nama']): ?>
                                        <div class="d-flex align-items-center">
                                            <div class="bg-light border rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                                                <i class="fas fa-user text-secondary x-small"></i>
                                            </div>
                                            <span class="fw-medium text-dark small">
                                                <?= htmlspecialchars($row['penghapus_nama']) ?>
                                            </span>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted fst-italic small"><i class="fas fa-robot me-1"></i>Sistem</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-5">
                                <div class="text-muted opacity-50 d-flex flex-column align-items-center">
                                    <i class="fas fa-trash-restore fa-3x mb-3 text-warning" style="opacity: 0.3;"></i>
                                    <p class="mb-0 fw-medium">Belum ada data history penghapusan.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>