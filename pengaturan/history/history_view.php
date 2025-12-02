<?php
// FILE: history_view.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../init.php';
guard('history_manage'); 
require_once __DIR__ . '/../../header.php'; 

// [LOGIC PENTING] Tangkap filter dari URL biar pas balik filternya gak ilang
$prev_tanggal = $_GET['tanggal'] ?? date('Y-m-d');
$prev_bagian  = $_GET['bagian'] ?? '';
$back_link    = "index.php?tanggal=" . urlencode($prev_tanggal) . "&bagian=" . urlencode($prev_bagian);

$query = "
    SELECT 
        l.*,
        s.nama AS nama_santri,
        jp.nama_pelanggaran,
        jp.bagian,
        u1.nama_lengkap AS pencatat_nama,
        u2.nama_lengkap AS penghapus_nama
    FROM log_history l
    LEFT JOIN santri s ON l.santri_id = s.id
    LEFT JOIN jenis_pelanggaran jp ON l.jenis_pelanggaran_id = jp.id
    LEFT JOIN users u1 ON l.dicatat_oleh = u1.id
    LEFT JOIN users u2 ON l.dihapus_oleh = u2.id
    ORDER BY l.dihapus_pada DESC
";
$result = mysqli_query($conn, $query);
?>

<style>
    /* Modern Minimalist Style (Sama kayak index.php) */
    body { background-color: #f8fafc; }
    .card-modern { border: none; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); background: white; }
    .table-responsive { border-radius: 16px; overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .table { margin-bottom: 0; white-space: nowrap; }
    .table thead th { background-color: #f8fafc; color: #64748b; font-weight: 700; font-size: 0.75rem; text-transform: uppercase; padding: 1rem 1.25rem; border-bottom: 2px solid #e2e8f0; }
    .table tbody td { padding: 1rem 1.25rem; vertical-align: middle; border-bottom: 1px solid #f1f5f9; color: #334155; font-size: 0.95rem; }
    .table tbody tr:last-child td { border-bottom: none; }
    .badge-soft-secondary { background-color: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
</style>

<div class="container py-4">
    <div class="d-flex align-items-center mb-4">
        <a href="<?= $back_link ?>" class="btn btn-white bg-white border rounded-circle shadow-sm me-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;" title="Kembali">
            <i class="fas fa-arrow-left text-secondary"></i>
        </a>
        
        <div>
            <h4 class="fw-bold text-dark mb-0">Log Penghapusan</h4>
            <p class="text-muted mb-0 small">Jejak audit data yang telah dibatalkan.</p>
        </div>
    </div>

    <div class="alert alert-warning border-0 shadow-sm rounded-3 d-flex align-items-center mb-4 p-3 bg-opacity-10" style="background-color: #fffbeb; border: 1px solid #fcd34d;">
        <i class="fas fa-info-circle me-3 fs-5 text-warning"></i>
        <div class="small text-dark">
            Data di bawah ini adalah pelanggaran yang <strong>sudah dibatalkan</strong>. Poin santri telah dikembalikan ke status semula.
        </div>
    </div>

    <div class="card card-modern overflow-hidden">
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
                    <?php if ($result && mysqli_num_rows($result) > 0): ?>
                        <?php $no = 1; while($row = mysqli_fetch_assoc($result)): ?>
                            <tr>
                                <td class="text-center text-muted"><?= $no++ ?></td>
                                <td>
                                    <div class="fw-bold text-dark mb-1">
                                        <?= htmlspecialchars($row['nama_santri'] ?? 'Santri Terhapus') ?>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="text-secondary small">
                                            <?= htmlspecialchars($row['nama_pelanggaran']) ?>
                                        </span>
                                        <span class="badge badge-soft-secondary fw-normal px-2 rounded-1" style="font-size: 0.75rem;">
                                            <?= htmlspecialchars($row['bagian']) ?>
                                        </span>
                                    </div>
                                    <div class="text-muted x-small mt-1 opacity-75" style="font-size: 0.75rem;">
                                        <i class="fas fa-calendar-alt me-1"></i> 
                                        Tgl Asli: <?= date('d/m/Y', strtotime($row['tanggal_pelanggaran'])) ?>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-soft-secondary rounded-pill px-3">
                                        <?= $row['poin'] ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="fw-medium text-danger">
                                        <?= date('d M Y', strtotime($row['dihapus_pada'])) ?>
                                    </div>
                                    <div class="small text-muted">
                                        <?= date('H:i', strtotime($row['dihapus_pada'])) ?> WIB
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
                                        <span class="text-muted fst-italic small">Sistem / Unknown</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-5">
                                <span class="text-muted opacity-50">Belum ada data history penghapusan.</span>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../footer.php'; ?>