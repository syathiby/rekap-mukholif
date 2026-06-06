<?php
// FILE: history_view.php — Log Penghapusan (Audit Trail)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../bootstrap/init.php';
guard('history_manage');

// ─── KONFIGURASI ───────────────────────────────────────────────────────
define('LOG_ROWS_PER_PAGE', 30);

// ─── PARAMETER FILTER & PAGINATION ────────────────────────────────────
$start_date        = $_GET['start_date']        ?? date('Y-m-d');
$end_date          = $_GET['end_date']          ?? date('Y-m-d');
$bagian            = $_GET['bagian']            ?? '';
$search            = trim($_GET['search']       ?? '');
$kamar             = $_GET['kamar']             ?? '';
$kelas             = $_GET['kelas']             ?? '';
$jenis_pelanggaran = $_GET['jenis_pelanggaran'] ?? '';
$page              = max(1, intval($_GET['page'] ?? 1));
$offset            = ($page - 1) * LOG_ROWS_PER_PAGE;

$back_link = "index.php?" . http_build_query([
    'start_date'        => $start_date,
    'end_date'          => $end_date,
    'bagian'            => $bagian,
    'search'            => $search,
    'kamar'             => $kamar,
    'kelas'             => $kelas,
    'jenis_pelanggaran' => $jenis_pelanggaran,
]);

// ─── QUERY LOG INDIVIDU ────────────────────────────────────────────────
// Gunakan UNION ALL agar merge & sort dilakukan MySQL, bukan PHP
$sql_log_ind = "
    SELECT 
        l.id,
        CONVERT(COALESCE(s.nama, 'Data Terhapus') USING utf8mb4) COLLATE utf8mb4_unicode_ci AS nama_santri,
        CONVERT(COALESCE(jp.nama_pelanggaran, '-') USING utf8mb4) COLLATE utf8mb4_unicode_ci AS nama_pelanggaran,
        CONVERT(COALESCE(jp.bagian, '-') USING utf8mb4) COLLATE utf8mb4_unicode_ci           AS bagian,
        l.poin,
        l.tanggal_pelanggaran,
        l.dihapus_pada,
        CONVERT(COALESCE(u2.nama_lengkap, '') USING utf8mb4) COLLATE utf8mb4_unicode_ci      AS penghapus_nama,
        'individu' COLLATE utf8mb4_unicode_ci                                                AS tipe
    FROM log_history l
    LEFT JOIN santri s              ON l.santri_id = s.id
    LEFT JOIN jenis_pelanggaran jp  ON l.jenis_pelanggaran_id = jp.id
    LEFT JOIN users u2              ON l.dihapus_oleh = u2.id
    WHERE 1=1
";

// ─── QUERY LOG KEBERSIHAN ──────────────────────────────────────────────
$sql_log_keb = "
    SELECT 
        lhk.id,
        CONVERT(CONCAT('Kamar ', lhk.kamar) USING utf8mb4) COLLATE utf8mb4_unicode_ci           AS nama_santri,
        CONVERT(CONCAT('Kebersihan: ', IFNULL(lhk.catatan,'')) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS nama_pelanggaran,
        'pengabdian' COLLATE utf8mb4_unicode_ci                                                  AS bagian,
        0                                                                                         AS poin,
        lhk.tanggal_pelanggaran,
        lhk.dihapus_pada,
        CONVERT(COALESCE(u2.nama_lengkap, '') USING utf8mb4) COLLATE utf8mb4_unicode_ci          AS penghapus_nama,
        'kebersihan' COLLATE utf8mb4_unicode_ci                                                  AS tipe
    FROM log_history_kebersihan lhk
    LEFT JOIN users u2 ON lhk.dihapus_oleh = u2.id
    WHERE 1=1
";

// ─── UNION ALL, COUNT, PAGINATE ───────────────────────────────────────
$union_sql  = "($sql_log_ind) UNION ALL ($sql_log_keb)";
$count_sql  = "SELECT COUNT(*) AS total FROM ($union_sql) AS merged_log";
$res_count  = mysqli_query($conn, $count_sql);
$total_data = $res_count ? (mysqli_fetch_assoc($res_count)['total'] ?? 0) : 0;
$total_pages = max(1, (int)ceil($total_data / LOG_ROWS_PER_PAGE));

$data_sql  = "SELECT * FROM ($union_sql) AS merged_log ORDER BY dihapus_pada DESC LIMIT " . intval(LOG_ROWS_PER_PAGE) . " OFFSET " . intval($offset);
$res_data  = mysqli_query($conn, $data_sql);
$all_logs  = $res_data ? mysqli_fetch_all($res_data, MYSQLI_ASSOC) : [];

// ─── HELPER: Pagination HTML ───────────────────────────────────────────
function buildLogPaginationHtml($current, $total) {
    if ($total <= 1) return '';
    ob_start();
    $range = 2;
    echo '<button class="h-page-btn" data-page="' . ($current - 1) . '"' . ($current <= 1 ? ' disabled' : '') . '><i class="fas fa-chevron-left"></i></button>';
    for ($i = 1; $i <= $total; $i++) {
        if ($i === 1 || $i === $total || abs($i - $current) <= $range) {
            $active = ($i === $current) ? ' active' : '';
            echo "<button class=\"h-page-btn$active\" data-page=\"$i\">$i</button>";
        } elseif (abs($i - $current) === $range + 1) {
            echo '<span class="h-page-btn" style="border:none;background:transparent;cursor:default;pointer-events:none;">…</span>';
        }
    }
    echo '<button class="h-page-btn" data-page="' . ($current + 1) . '"' . ($current >= $total ? ' disabled' : '') . '><i class="fas fa-chevron-right"></i></button>';
    return ob_get_clean();
}

require_once __DIR__ . '/../../layouts/header.php';
?>

<style>
:root {
    --lv-primary:      #4f46e5;
    --lv-primary-lt:   #e0e7ff;
    --lv-danger:       #ef4444;
    --lv-warning-bg:   #fef3c7;
    --lv-warning-c:    #d97706;
    --lv-secondary:    #64748b;
    --lv-border:       #e2e8f0;
}

.lv-card {
    background: #fff;
    border: 1px solid var(--lv-border);
    border-radius: 1.2rem;
    box-shadow: 0 4px 20px -4px rgba(0,0,0,.06);
    overflow: hidden;
}

.lv-table {
    width: 100%;
    border-collapse: collapse;
    white-space: nowrap;
    font-size: .9rem;
    margin: 0;
}
.lv-table thead th {
    background: #f8fafc;
    color: #475569;
    font-weight: 700;
    font-size: .72rem;
    text-transform: uppercase;
    letter-spacing: .05em;
    padding: 1rem 1.25rem;
    border-bottom: 2px solid var(--lv-border);
}
.lv-table tbody td {
    padding: .95rem 1.25rem;
    vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
    color: #334155;
}
.lv-table tbody tr:hover { background: #f8fafc; }
.lv-table tbody tr:last-child td { border-bottom: none; }

.badge-soft-secondary {
    background: #f1f5f9; color: var(--lv-secondary); border: 1px solid #cbd5e1;
    border-radius: 999px; padding: .25rem .75rem; font-size: .78rem; font-weight: 600;
}
.badge-soft-warning {
    background: var(--lv-warning-bg); color: var(--lv-warning-c); border: 1px solid #fde68a;
    border-radius: 999px; padding: .25rem .75rem; font-size: .78rem; font-weight: 600;
}
.badge-soft-danger {
    background: #fee2e2; color: var(--lv-danger); border: 1px solid #fca5a5;
    border-radius: 999px; padding: .25rem .75rem; font-size: .78rem; font-weight: 600;
}

.btn-back {
    width: 40px; height: 40px;
    display: flex; align-items: center; justify-content: center;
    border-radius: 50%; background: #fff;
    border: 1.5px solid var(--lv-border);
    color: var(--lv-secondary);
    transition: all .2s;
    text-decoration: none;
    flex-shrink: 0;
}
.btn-back:hover {
    background: var(--lv-primary);
    color: #fff;
    border-color: var(--lv-primary);
    transform: translateX(-3px);
}

/* Pagination reuse dari index.php */
.h-pagination { display: flex; align-items: center; gap: .35rem; flex-wrap: wrap; justify-content: center; }
.h-page-btn {
    min-width: 34px; height: 34px; padding: 0 .6rem;
    border-radius: 8px; border: 1.5px solid var(--lv-border);
    background: #fff; color: #475569; font-size: .82rem; font-weight: 600;
    cursor: pointer; transition: all .2s;
    display: inline-flex; align-items: center; justify-content: center;
}
.h-page-btn:hover:not(:disabled):not(.active) {
    background: var(--lv-primary-lt); border-color: var(--lv-primary); color: var(--lv-primary);
}
.h-page-btn.active {
    background: var(--lv-primary); border-color: var(--lv-primary); color: #fff;
    box-shadow: 0 3px 8px rgba(79,70,229,.3);
}
.h-page-btn:disabled { opacity: .4; cursor: not-allowed; }
</style>

<div class="container py-4">

    <!-- ── Header ── -->
    <div class="d-flex align-items-center mb-4 gap-3">
        <a href="<?= $back_link ?>" class="btn-back shadow-sm" title="Kembali ke Riwayat">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <h3 class="fw-bolder text-dark mb-1">
                <i class="fas fa-trash-restore text-warning me-2"></i>Log Penghapusan
            </h3>
            <p class="text-muted mb-0 small">Jejak audit data pelanggaran yang telah dibatalkan.</p>
        </div>
        <div class="ms-auto">
            <span class="badge bg-white border text-secondary px-3 py-2 rounded-3 fw-medium">
                Total: <strong class="text-primary"><?= number_format($total_data) ?></strong> record
            </span>
        </div>
    </div>

    <!-- ── Alert Info ── -->
    <div class="d-flex align-items-start gap-3 p-3 mb-4 rounded-4 border"
         style="background:#fffbeb; border-color:#fcd34d !important;">
        <i class="fas fa-info-circle fs-5 text-warning mt-1 flex-shrink-0"></i>
        <div class="small text-dark">
            Data di bawah ini adalah pelanggaran yang <strong>sudah dibatalkan</strong>.
            Jika ada poin sanksi, poin tersebut telah dikembalikan ke status semula.
            Data dimuat <strong><?= LOG_ROWS_PER_PAGE ?> baris per halaman</strong>.
        </div>
    </div>

    <!-- ── Tabel ── -->
    <div class="lv-card">
        <div style="overflow-x:auto;">
            <table class="lv-table">
                <thead>
                    <tr>
                        <th class="text-center" style="width:50px;">No</th>
                        <th>Detail Pelanggaran (Dihapus)</th>
                        <th class="text-center">Poin Batal</th>
                        <th>Waktu Dihapus</th>
                        <th>Dihapus Oleh</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($all_logs) > 0): ?>
                        <?php $no = $offset + 1; foreach ($all_logs as $row): ?>
                            <tr>
                                <td class="text-center text-muted fw-medium"><?= $no++ ?></td>
                                <td>
                                    <div class="fw-bold text-dark mb-1">
                                        <?php if ($row['tipe'] === 'kebersihan'): ?>
                                            <i class="fas fa-door-closed text-muted me-1"></i>
                                        <?php endif; ?>
                                        <?= htmlspecialchars($row['nama_santri'] ?? 'Data Terhapus') ?>
                                    </div>
                                    <div class="d-flex align-items-center flex-wrap gap-2 mt-1">
                                        <span class="text-secondary small fw-medium">
                                            <?= htmlspecialchars($row['nama_pelanggaran']) ?>
                                        </span>
                                        <?php if ($row['tipe'] === 'kebersihan'): ?>
                                            <span class="badge-soft-warning" style="font-size:.7rem;padding:.18rem .55rem;">
                                                <i class="fas fa-broom me-1"></i>Kebersihan
                                            </span>
                                        <?php else: ?>
                                            <span class="badge-soft-secondary" style="font-size:.7rem;padding:.18rem .55rem;">
                                                <?= htmlspecialchars(ucwords(strtolower($row['bagian'] ?? ''))) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-muted mt-1" style="font-size:.75rem;">
                                        <i class="fas fa-calendar-alt me-1"></i>
                                        Tgl Asli: <?= date('d M Y, H:i', strtotime($row['tanggal_pelanggaran'])) ?>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <?php if ($row['tipe'] === 'kebersihan'): ?>
                                        <span class="text-muted small">-</span>
                                    <?php else: ?>
                                        <span class="badge-soft-secondary fw-bold px-3"><?= intval($row['poin']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-medium text-danger"><?= date('d M Y', strtotime($row['dihapus_pada'])) ?></div>
                                    <div class="small text-muted mt-1">
                                        <i class="far fa-clock me-1"></i><?= date('H:i', strtotime($row['dihapus_pada'])) ?> WIB
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($row['penghapus_nama'])): ?>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="bg-light border rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                                                 style="width:32px;height:32px;">
                                                <i class="fas fa-user text-secondary" style="font-size:.8rem;"></i>
                                            </div>
                                            <span class="fw-medium text-dark small">
                                                <?= htmlspecialchars($row['penghapus_nama']) ?>
                                            </span>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted fst-italic small">
                                            <i class="fas fa-robot me-1"></i>Sistem
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-5">
                                <div class="text-muted d-flex flex-column align-items-center">
                                    <i class="fas fa-trash-restore fa-3x mb-3 text-warning" style="opacity:.25;"></i>
                                    <p class="mb-0 fw-medium">Belum ada data history penghapusan.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- ── Pagination Footer ── -->
        <div class="d-flex flex-column flex-sm-row align-items-center justify-content-between gap-3 px-4 py-3 border-top">
            <div class="text-muted small">
                <?php if ($total_data > 0): ?>
                    Menampilkan <?= $offset + 1 ?>–<?= min($offset + LOG_ROWS_PER_PAGE, $total_data) ?>
                    dari <strong><?= number_format($total_data) ?></strong> record
                <?php else: ?>
                    Tidak ada data
                <?php endif; ?>
            </div>
            <div class="h-pagination" id="logPaginationBtns">
                <?= buildLogPaginationHtml($page, $total_pages) ?>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    // Klik pagination → navigasi ke halaman terpilih
    document.getElementById('logPaginationBtns')?.querySelectorAll('.h-page-btn[data-page]').forEach(btn => {
        btn.addEventListener('click', function () {
            const p = parseInt(this.dataset.page);
            if (!isNaN(p) && !this.disabled && !this.classList.contains('active')) {
                const url = new URL(window.location.href);
                url.searchParams.set('page', p);
                window.location.href = url.toString();
            }
        });
    });
})();
</script>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>