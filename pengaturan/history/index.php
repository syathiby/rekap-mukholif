<?php
// FILE: pengaturan/history/index.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../bootstrap/init.php';
guard('history_manage');

// ─── KONFIGURASI PAGINATION ─────────────────────────────────────────
define('ROWS_PER_PAGE', 25);

// ─── AMBIL PARAMETER FILTER ─────────────────────────────────────────
$periode_aktif = PERIODE_AKTIF;

$start_date        = $_GET['start_date']        ?? $periode_aktif;
$end_date          = $_GET['end_date']          ?? date('Y-m-d');
$bagian            = $_GET['bagian']            ?? '';
$search            = trim($_GET['search']       ?? '');
$kamar             = $_GET['kamar']             ?? '';
$kelas             = $_GET['kelas']             ?? '';
$jenis_pelanggaran = $_GET['jenis_pelanggaran'] ?? '';
$page              = max(1, intval($_GET['page'] ?? 1));
$offset            = ($page - 1) * ROWS_PER_PAGE;

// ─── BANGUN QUERY TUNGGAL DENGAN UNION ALL ───────────────────────────
// Lebih efisien: semua merge & sort diserahkan ke MySQL, bukan PHP.
// Indeks yang relevan: DATE(tanggal), bagian, kamar, kelas, jenis_pelanggaran_id, nama santri

$bind_types = '';
$bind_vals  = [];

// ── WHERE Clause untuk pelanggaran individu
$where_ind = "DATE(p.tanggal) >= ? AND DATE(p.tanggal) <= ?";
$bind_types .= 'ss';
$bind_vals[] = $start_date;
$bind_vals[] = $end_date;

// Bagian: jika "kebersihan" → skip seluruh blok individu
$skip_individu = (strtolower($bagian) === 'kebersihan');

if (!$skip_individu && !empty($bagian)) {
    $where_ind .= " AND jp.bagian = ?";
    $bind_types .= 's';
    $bind_vals[] = $bagian;
}
if (!empty($search)) {
    $where_ind .= " AND s.nama LIKE ?";
    $bind_types .= 's';
    $bind_vals[] = '%' . $search . '%';
}
if (!empty($kamar)) {
    $where_ind .= " AND s.kamar = ?";
    $bind_types .= 's';
    $bind_vals[] = $kamar;
}
if (!empty($kelas)) {
    $where_ind .= " AND s.kelas = ?";
    $bind_types .= 's';
    $bind_vals[] = $kelas;
}
if (!empty($jenis_pelanggaran)) {
    $where_ind .= " AND p.jenis_pelanggaran_id = ?";
    $bind_types .= 'i';
    $bind_vals[] = intval($jenis_pelanggaran);
}

// ── WHERE Clause untuk pelanggaran kebersihan
$skip_kebersihan = $skip_individu
    ? false // jika filter = kebersihan, tampilkan kebersihan saja
    : ((!empty($bagian) && strtolower($bagian) !== 'pengabdian')
        || !empty($kelas)
        || !empty($jenis_pelanggaran)
        || !empty($search));

// Jika filter = "kebersihan", skip_kebersihan = false, tapi skip_individu = true
if ($skip_individu) {
    $skip_kebersihan = false;
}

$where_keb = "DATE(pk.tanggal) >= ? AND DATE(pk.tanggal) <= ?";
$bind_types_keb = 'ss';
$bind_vals_keb  = [$start_date, $end_date];
if (!empty($kamar)) {
    $where_keb .= " AND pk.kamar = ?";
    $bind_types_keb .= 's';
    $bind_vals_keb[] = $kamar;
}

// ── QUERY INDIVIDU (untuk UNION)
$sql_ind = $skip_individu ? null : "
    SELECT 
        p.id,
        CONVERT(s.nama USING utf8mb4) COLLATE utf8mb4_unicode_ci        AS nama_santri,
        CONVERT(s.kelas USING utf8mb4) COLLATE utf8mb4_unicode_ci       AS kelas,
        CONVERT(s.kamar USING utf8mb4) COLLATE utf8mb4_unicode_ci       AS kamar,
        CONVERT(jp.nama_pelanggaran USING utf8mb4) COLLATE utf8mb4_unicode_ci AS nama_pelanggaran,
        jp.poin,
        CONVERT(jp.bagian USING utf8mb4) COLLATE utf8mb4_unicode_ci     AS bagian,
        p.tanggal,
        'individu' COLLATE utf8mb4_unicode_ci                           AS tipe
    FROM pelanggaran p
    JOIN santri s              ON p.santri_id = s.id
    JOIN jenis_pelanggaran jp  ON p.jenis_pelanggaran_id = jp.id
    WHERE $where_ind
";

// ── QUERY KEBERSIHAN (untuk UNION)
$sql_keb = $skip_kebersihan ? null : "
    SELECT 
        pk.id,
        'Kamar' COLLATE utf8mb4_unicode_ci                                              AS nama_santri,
        '-' COLLATE utf8mb4_unicode_ci                                                  AS kelas,
        CONVERT(pk.kamar USING utf8mb4) COLLATE utf8mb4_unicode_ci                     AS kamar,
        CONVERT(CONCAT('Kebersihan: ', IFNULL(pk.catatan,'')) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS nama_pelanggaran,
        0                                                                               AS poin,
        'pengabdian' COLLATE utf8mb4_unicode_ci                                         AS bagian,
        pk.tanggal,
        'kebersihan' COLLATE utf8mb4_unicode_ci                                         AS tipe
    FROM pelanggaran_kebersihan pk
    WHERE $where_keb
";

// ── GABUNGKAN (UNION ALL) untuk COUNT dan SELECT data
function build_union_parts($sql_ind, $sql_keb, $bind_types, $bind_vals, $bind_types_keb, $bind_vals_keb) {
    $parts      = [];
    $all_types  = '';
    $all_vals   = [];

    if ($sql_ind !== null) {
        $parts[]    = $sql_ind;
        $all_types .= $bind_types;
        $all_vals   = array_merge($all_vals, $bind_vals);
    }
    if ($sql_keb !== null) {
        $parts[]    = $sql_keb;
        $all_types .= $bind_types_keb;
        $all_vals   = array_merge($all_vals, $bind_vals_keb);
    }

    return ['parts' => $parts, 'types' => $all_types, 'vals' => $all_vals];
}

$union_info = build_union_parts($sql_ind, $sql_keb, $bind_types, $bind_vals, $bind_types_keb, $bind_vals_keb);

// Jika tidak ada bagian yang perlu di-query
$total_data = 0;
$all_data   = [];

if (!empty($union_info['parts'])) {
    $union_sql = implode(" UNION ALL ", $union_info['parts']);

    // ── COUNT total (untuk info & pagination)
    $count_sql  = "SELECT COUNT(*) AS total FROM ($union_sql) AS merged";
    $stmt_count = $conn->prepare($count_sql);
    if ($stmt_count && !empty($union_info['types'])) {
        $stmt_count->bind_param($union_info['types'], ...$union_info['vals']);
    }
    if ($stmt_count) {
        $stmt_count->execute();
        $total_data = $stmt_count->get_result()->fetch_assoc()['total'] ?? 0;
        $stmt_count->close();
    }

    // ── Ambil data halaman saat ini saja (LIMIT + OFFSET)
    $data_sql  = "SELECT * FROM ($union_sql) AS merged ORDER BY tanggal DESC LIMIT ? OFFSET ?";
    $data_types = $union_info['types'] . 'ii';
    $data_vals  = array_merge($union_info['vals'], [ROWS_PER_PAGE, $offset]);

    $stmt_data = $conn->prepare($data_sql);
    if ($stmt_data && !empty($data_types)) {
        $stmt_data->bind_param($data_types, ...$data_vals);
    }
    if ($stmt_data) {
        $stmt_data->execute();
        $all_data = $stmt_data->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_data->close();
    }
}

$total_pages = $total_data > 0 ? (int)ceil($total_data / ROWS_PER_PAGE) : 1;

// ─── HISTORY LINK (bawa filter ke log penghapusan) ──────────────────
$history_link = "history_view.php?"
    . http_build_query([
        'start_date'        => $start_date,
        'end_date'          => $end_date,
        'bagian'            => $bagian,
        'search'            => $search,
        'kamar'             => $kamar,
        'kelas'             => $kelas,
        'jenis_pelanggaran' => $jenis_pelanggaran,
    ]);

// ─── RENDER ROWS ────────────────────────────────────────────────────
function render_table_rows($all_data, $start_date, $end_date, $bagian, $search, $kamar, $kelas, $jenis_pelanggaran, $page_offset = 0, $page = 1) {
    ob_start();
    if (count($all_data) > 0):
        $no = $page_offset + 1;
        foreach ($all_data as $row): ?>
            <tr>
                <td class="text-center text-muted fw-medium"><?= $no++ ?></td>
                <td>
                    <?php if ($row['tipe'] === 'kebersihan'): ?>
                        <div class="fw-bold text-dark"><i class="fas fa-door-closed text-muted me-2"></i>Kamar <?= htmlspecialchars($row['kamar']) ?></div>
                    <?php else: ?>
                        <div class="fw-bold text-dark"><?= htmlspecialchars($row['nama_santri']) ?></div>
                        <div class="small text-muted mt-1">
                            <span class="badge badge-soft-secondary rounded-2 fw-normal px-2 py-1">Kls <?= htmlspecialchars($row['kelas']) ?></span>
                            <span class="ms-1 fw-medium">Kmr <?= htmlspecialchars($row['kamar']) ?></span>
                        </div>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($row['tipe'] === 'kebersihan'): ?>
                        <span class="text-dark fw-medium"><?= htmlspecialchars($row['nama_pelanggaran']) ?></span>
                    <?php else: ?>
                        <?= htmlspecialchars($row['nama_pelanggaran']) ?>
                    <?php endif; ?>
                </td>
                <td class="text-center">
                    <?php if ($row['tipe'] === 'kebersihan'): ?>
                        <span class="text-muted small">-</span>
                    <?php else: ?>
                        <span class="badge badge-soft-danger rounded-pill px-3 py-2 fw-bold"><?= htmlspecialchars($row['poin']) ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="text-dark fw-medium"><?= date('d M Y', strtotime($row['tanggal'])) ?></div>
                    <div class="text-muted small"><i class="far fa-clock me-1"></i><?= date('H:i', strtotime($row['tanggal'])) ?></div>
                </td>
                <td>
                    <?php if ($row['tipe'] === 'kebersihan'): ?>
                        <span class="badge badge-soft-warning rounded-pill fw-medium px-3 py-2"><i class="fas fa-broom me-1"></i> Kebersihan</span>
                    <?php else: ?>
                        <span class="badge badge-soft-primary rounded-pill fw-medium px-3 py-2"><?= htmlspecialchars(ucwords(strtolower($row['bagian']))) ?></span>
                    <?php endif; ?>
                </td>
                <td class="text-center">
                    <form action="process.php" method="POST" onsubmit="return confirmCancel(event, this)">
                        <input type="hidden" name="id"                value="<?= $row['id'] ?>">
                        <input type="hidden" name="tipe"              value="<?= $row['tipe'] ?>">
                        <input type="hidden" name="start_date"        value="<?= htmlspecialchars($start_date) ?>">
                        <input type="hidden" name="end_date"          value="<?= htmlspecialchars($end_date) ?>">
                        <input type="hidden" name="bagian"            value="<?= htmlspecialchars($bagian) ?>">
                        <input type="hidden" name="search"            value="<?= htmlspecialchars($search) ?>">
                        <input type="hidden" name="kamar"             value="<?= htmlspecialchars($kamar) ?>">
                        <input type="hidden" name="kelas"             value="<?= htmlspecialchars($kelas) ?>">
                        <input type="hidden" name="jenis_pelanggaran" value="<?= htmlspecialchars($jenis_pelanggaran) ?>">
                        <input type="hidden" name="page"              value="<?= intval($page) ?>">
                        <button type="submit" name="batalkan" class="btn btn-action-icon" title="Batalkan Pelanggaran">
                            <i class="fas fa-times"></i>
                        </button>
                    </form>
                </td>
            </tr>
        <?php endforeach;
    else: ?>
        <tr>
            <td colspan="7" class="text-center py-5">
                <div class="text-muted d-flex flex-column align-items-center">
                    <i class="fas fa-clipboard-check fa-4x mb-3 text-primary" style="opacity:0.15;"></i>
                    <h5 class="fw-bold mb-1">Papan Bersih!</h5>
                    <p class="mb-0 small">Tidak ada data pelanggaran yang ditemukan.</p>
                </div>
            </td>
        </tr>
    <?php endif;
    return ob_get_clean();
}

// ─── AJAX HANDLER ────────────────────────────────────────────────────
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($is_ajax) {
    header('Content-Type: application/json');
    echo json_encode([
        'html'         => render_table_rows($all_data, $start_date, $end_date, $bagian, $search, $kamar, $kelas, $jenis_pelanggaran, $offset, $page),
        'total'        => $total_data,
        'total_pages'  => $total_pages,
        'current_page' => $page,
        'history_link' => $history_link,
    ]);
    exit;
}

// ─── BUKAN AJAX: Ambil option filter dropdown ────────────────────────
$bagian_result = mysqli_query($conn, "SELECT DISTINCT bagian FROM jenis_pelanggaran WHERE bagian IS NOT NULL AND bagian != '' ORDER BY bagian ASC");
$kamar_result  = mysqli_query($conn, "SELECT DISTINCT kamar FROM santri WHERE kamar IS NOT NULL AND kamar != '' ORDER BY kamar ASC");
$kelas_result  = mysqli_query($conn, "SELECT DISTINCT kelas FROM santri WHERE kelas IS NOT NULL AND kelas != '' ORDER BY kelas ASC");
$jp_result     = mysqli_query($conn, "SELECT DISTINCT jp.id, jp.nama_pelanggaran FROM pelanggaran p JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id ORDER BY jp.nama_pelanggaran ASC");

// ─── Bangun HTML Pagination ────────────────────────────────────────
function buildPaginationHtml($current, $total) {
    if ($total <= 1) return '';
    ob_start();
    $range = 2;
    // Prev
    echo '<button class="h-page-btn" data-page="' . ($current - 1) . '"' . ($current <= 1 ? ' disabled' : '') . '><i class="fas fa-chevron-left"></i></button>';
    for ($i = 1; $i <= $total; $i++) {
        if ($i === 1 || $i === $total || abs($i - $current) <= $range) {
            $active = ($i === $current) ? ' active' : '';
            echo "<button class=\"h-page-btn$active\" data-page=\"$i\">$i</button>";
        } elseif (abs($i - $current) === $range + 1) {
            echo '<span class="h-page-btn" style="border:none;background:transparent;cursor:default;pointer-events:none;">…</span>';
        }
    }
    // Next
    echo '<button class="h-page-btn" data-page="' . ($current + 1) . '"' . ($current >= $total ? ' disabled' : '') . '><i class="fas fa-chevron-right"></i></button>';
    return ob_get_clean();
}

require_once __DIR__ . '/../../layouts/header.php';
?>

<style>
/* ═══════════════════════════════════════════
   HISTORY PAGE — Performance-Optimized CSS
   Hanya variabel yang belum ada di style.css
   ═══════════════════════════════════════════ */
:root {
    --h-primary:        #4f46e5;
    --h-primary-light:  #e0e7ff;
    --h-danger:         #ef4444;
    --h-danger-light:   #fee2e2;
    --h-success:        #10b981;
    --h-warning-bg:     #fef3c7;
    --h-warning-color:  #d97706;
    --h-secondary:      #64748b;
    --h-bg:             #f1f5f9;
    --h-card-bg:        #ffffff;
    --h-border:         #e2e8f0;
}

/* ── Kartu Glass ── */
.h-card {
    background: var(--h-card-bg);
    border: 1px solid var(--h-border);
    border-radius: 1.2rem;
    box-shadow: 0 4px 20px -4px rgba(0,0,0,.06);
}

/* ── Filter inputs ── */
.h-form-control,
.h-form-select {
    border-radius: 10px;
    border: 1.5px solid var(--h-border);
    padding: .65rem 1rem;
    font-size: .88rem;
    background: #f8fafc;
    transition: border-color .2s, box-shadow .2s;
    width: 100%;
    color: #334155;
}
.h-form-control:focus,
.h-form-select:focus {
    outline: none;
    border-color: var(--h-primary);
    box-shadow: 0 0 0 3px rgba(79,70,229,.15);
    background: #fff;
}
.h-input-wrap {
    position: relative;
}
.h-input-icon {
    position: absolute;
    left: .9rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--h-secondary);
    font-size: .85rem;
    pointer-events: none;
}
.h-input-wrap .h-form-control {
    padding-left: 2.4rem;
}

/* ── Label filter ── */
.filter-label {
    display: block;
    font-size: .72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: var(--h-secondary);
    margin-bottom: .35rem;
}

/* ── Tombol Terapkan / Reset ── */
.btn-apply {
    background: linear-gradient(135deg, #6366f1, #4f46e5);
    color: #fff;
    border: none;
    border-radius: 10px;
    padding: .65rem 1.4rem;
    font-weight: 600;
    font-size: .88rem;
    transition: all .25s;
    cursor: pointer;
}
.btn-apply:hover {
    background: linear-gradient(135deg, #4f46e5, #4338ca);
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(79,70,229,.3);
    color: #fff;
}
.btn-reset {
    background: #fff;
    color: var(--h-secondary);
    border: 1.5px solid var(--h-border);
    border-radius: 10px;
    padding: .65rem 1rem;
    font-size: .88rem;
    transition: all .2s;
    cursor: pointer;
    white-space: nowrap;
}
.btn-reset:hover {
    background: #f1f5f9;
    border-color: #94a3b8;
    color: #334155;
}

/* ── Stat Box ── */
.stat-box {
    background: linear-gradient(135deg, #f8fafc, #f1f5f9);
    border: 1px solid var(--h-border);
    border-radius: 12px;
    padding: .9rem 1.2rem;
    text-align: center;
}

/* ── Tabel ── */
.h-table-wrap {
    border-radius: 1.2rem;
    overflow: hidden;
}
.h-table {
    width: 100%;
    border-collapse: collapse;
    white-space: nowrap;
    font-size: .9rem;
    margin: 0;
}
.h-table thead th {
    background: #f8fafc;
    color: #475569;
    font-weight: 700;
    font-size: .72rem;
    text-transform: uppercase;
    letter-spacing: .05em;
    padding: 1rem 1.25rem;
    border-bottom: 2px solid var(--h-border);
}
.h-table tbody td {
    padding: .95rem 1.25rem;
    vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
    color: #334155;
}
.h-table tbody tr {
    transition: background .15s;
    will-change: background-color;
}
.h-table tbody tr:hover {
    background: #f8fafc;
}
.h-table tbody tr:last-child td {
    border-bottom: none;
}

/* ── Badge ── */
.badge-soft-danger    { background: var(--h-danger-light);  color: var(--h-danger);         border: 1px solid #fca5a5; }
.badge-soft-primary   { background: var(--h-primary-light); color: var(--h-primary);        border: 1px solid #a5b4fc; }
.badge-soft-secondary { background: #f1f5f9;                color: var(--h-secondary);       border: 1px solid #cbd5e1; }
.badge-soft-warning   { background: var(--h-warning-bg);    color: var(--h-warning-color);   border: 1px solid #fde68a; }
.badge-soft-danger, .badge-soft-primary,
.badge-soft-secondary, .badge-soft-warning {
    border-radius: 999px;
    padding: .25rem .75rem;
    font-size: .78rem;
    font-weight: 600;
}

/* ── Aksi ── */
.btn-action-icon {
    width: 34px; height: 34px;
    display: inline-flex; align-items: center; justify-content: center;
    border-radius: 9px;
    background: #fff;
    border: 1.5px solid var(--h-border);
    color: var(--h-danger);
    transition: all .2s;
    cursor: pointer;
}
.btn-action-icon:hover {
    background: var(--h-danger);
    color: #fff;
    border-color: var(--h-danger);
    transform: rotate(90deg);
}

/* ── Skeleton Loading ── */
.skeleton-row td {
    padding: .95rem 1.25rem;
    border-bottom: 1px solid #f1f5f9;
}
.skeleton-pulse {
    display: inline-block;
    height: 14px;
    border-radius: 6px;
    background: linear-gradient(90deg, #e2e8f0 25%, #f1f5f9 50%, #e2e8f0 75%);
    background-size: 200% 100%;
    animation: skeleton-anim .9s ease-in-out infinite;
}
@keyframes skeleton-anim {
    0%   { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

/* ── Spinner overlay tabel ── */
.table-loading-overlay {
    display: none;
    position: absolute;
    inset: 0;
    background: rgba(255,255,255,.65);
    backdrop-filter: blur(2px);
    z-index: 10;
    align-items: center;
    justify-content: center;
    border-radius: 1.2rem;
}
.table-loading-overlay.show { display: flex; }
.spinner-ring {
    width: 42px; height: 42px;
    border: 4px solid #e0e7ff;
    border-top-color: var(--h-primary);
    border-radius: 50%;
    animation: spin .7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Pagination ── */
.h-pagination {
    display: flex;
    align-items: center;
    gap: .35rem;
    flex-wrap: wrap;
    justify-content: center;
}
.h-page-btn {
    min-width: 34px; height: 34px;
    padding: 0 .6rem;
    border-radius: 8px;
    border: 1.5px solid var(--h-border);
    background: #fff;
    color: #475569;
    font-size: .82rem;
    font-weight: 600;
    cursor: pointer;
    transition: all .2s;
    display: inline-flex; align-items: center; justify-content: center;
}
.h-page-btn:hover:not(:disabled):not(.active) {
    background: var(--h-primary-light);
    border-color: var(--h-primary);
    color: var(--h-primary);
}
.h-page-btn.active {
    background: var(--h-primary);
    border-color: var(--h-primary);
    color: #fff;
    box-shadow: 0 3px 8px rgba(79,70,229,.3);
}
.h-page-btn:disabled {
    opacity: .4;
    cursor: not-allowed;
}

/* ── Filter Active Badge ── */
.filter-active-badge {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    background: var(--h-primary-light);
    color: var(--h-primary);
    border: 1px solid #a5b4fc;
    border-radius: 999px;
    padding: .18rem .65rem;
    font-size: .75rem;
    font-weight: 600;
    cursor: pointer;
    transition: all .2s;
}
.filter-active-badge:hover {
    background: #c7d2fe;
}
.filter-active-badge .remove-icon {
    font-size: .65rem;
    opacity: .7;
}
</style>

<div class="container py-4">

    <!-- ── Header ── -->
    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center mb-4 gap-3">
        <div>
            <h3 class="fw-bolder text-dark mb-1">
                <i class="fas fa-history text-primary me-2"></i>Riwayat Pelanggaran
            </h3>
            <p class="text-muted mb-0 small">Kelola dan pantau seluruh data pelanggaran. Data dimuat per <?= ROWS_PER_PAGE ?> baris.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= $history_link ?>" id="btn-log-penghapusan"
               class="btn btn-sm px-3 py-2 border text-danger bg-white fw-medium rounded-pill shadow-sm"
               style="transition:.2s;">
                <i class="fas fa-trash-restore me-2"></i>Log Penghapusan
            </a>
        </div>
    </div>

    <!-- ── Kartu Filter ── -->
    <div class="h-card mb-4 p-4">
        <form id="filterForm" action="index.php" method="GET" autocomplete="off">

            <!-- Baris 1 -->
            <div class="row g-3 align-items-end mb-3">
                <!-- Cari -->
                <div class="col-12 col-sm-6 col-lg-3">
                    <label class="filter-label">Cari Nama Santri</label>
                    <div class="h-input-wrap">
                        <i class="fas fa-search h-input-icon"></i>
                        <input type="text" name="search" id="searchInput"
                               value="<?= htmlspecialchars($search) ?>"
                               class="h-form-control" placeholder="Nama Santri...">
                    </div>
                </div>
                <!-- Mulai -->
                <div class="col-6 col-sm-3 col-lg-2">
                    <label class="filter-label">Mulai Tanggal</label>
                    <input type="date" id="start_date" name="start_date"
                           value="<?= htmlspecialchars($start_date) ?>" class="h-form-control">
                </div>
                <!-- Sampai -->
                <div class="col-6 col-sm-3 col-lg-2">
                    <label class="filter-label">Sampai Tanggal</label>
                    <input type="date" id="end_date" name="end_date"
                           value="<?= htmlspecialchars($end_date) ?>" class="h-form-control">
                </div>
                <!-- Bagian -->
                <div class="col-12 col-sm-6 col-lg-2">
                    <label class="filter-label">Bagian</label>
                    <select name="bagian" id="bagian" class="h-form-select">
                        <option value="">Semua Bagian</option>
                        <option value="kebersihan" <?= (strtolower($bagian) === 'kebersihan') ? 'selected' : '' ?>>Kebersihan</option>
                        <?php mysqli_data_seek($bagian_result, 0); ?>
                        <?php while ($b = mysqli_fetch_assoc($bagian_result)): ?>
                            <option value="<?= htmlspecialchars($b['bagian']) ?>"
                                <?= (strtolower($bagian) === strtolower($b['bagian'])) ? 'selected' : '' ?>>
                                <?= htmlspecialchars(ucwords(strtolower($b['bagian']))) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <!-- Kamar -->
                <div class="col-6 col-sm-3 col-lg-1">
                    <label class="filter-label">Kamar</label>
                    <select name="kamar" id="kamar" class="h-form-select">
                        <option value="">Semua</option>
                        <?php while ($kmr = mysqli_fetch_assoc($kamar_result)): ?>
                            <option value="<?= htmlspecialchars($kmr['kamar']) ?>"
                                <?= ($kamar === $kmr['kamar']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($kmr['kamar']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <!-- Kelas -->
                <div class="col-6 col-sm-3 col-lg-1">
                    <label class="filter-label">Kelas</label>
                    <select name="kelas" id="kelas" class="h-form-select">
                        <option value="">Semua</option>
                        <?php while ($kls = mysqli_fetch_assoc($kelas_result)): ?>
                            <option value="<?= htmlspecialchars($kls['kelas']) ?>"
                                <?= ($kelas === $kls['kelas']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($kls['kelas']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>

            <!-- Baris 2 -->
            <div class="row g-3 align-items-end">
                <!-- Jenis Pelanggaran -->
                <div class="col-12 col-sm-8 col-lg-5">
                    <label class="filter-label">Jenis Pelanggaran</label>
                    <select name="jenis_pelanggaran" id="jenis_pelanggaran" class="h-form-select">
                        <option value="">Semua Jenis Pelanggaran</option>
                        <?php while ($jp = mysqli_fetch_assoc($jp_result)): ?>
                            <option value="<?= htmlspecialchars($jp['id']) ?>"
                                <?= ($jenis_pelanggaran == $jp['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($jp['nama_pelanggaran']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <!-- Tombol -->
                <div class="col-6 col-sm-2 col-lg-auto">
                    <button type="submit" class="btn-apply w-100">
                        <i class="fas fa-filter me-1"></i> Terapkan
                    </button>
                </div>
                <div class="col-6 col-sm-2 col-lg-auto">
                    <button type="button" id="btnReset" class="btn-reset w-100">
                        <i class="fas fa-undo me-1"></i> Reset
                    </button>
                </div>
                <!-- Stat Total -->
                <div class="col-12 col-sm-12 col-lg-auto ms-lg-auto">
                    <div class="stat-box">
                        <small class="d-block text-muted fw-bold" style="font-size:.7rem;">TOTAL DATA</small>
                        <span class="fw-bolder text-primary fs-5" id="totalDataCount"><?= $total_data ?></span>
                    </div>
                </div>
            </div>

        </form>

        <!-- Active Filter Tags -->
        <div id="activeFilters" class="mt-3 d-flex gap-2 flex-wrap" style="min-height:24px;"></div>
    </div>

    <!-- ── Tabel ── -->
    <div class="h-card overflow-hidden" style="position:relative;">
        <!-- Loading Overlay -->
        <div class="table-loading-overlay" id="tableOverlay">
            <div class="spinner-ring"></div>
        </div>

        <div class="overflow-x-auto" style="overflow-x:auto;">
            <table class="h-table" id="historyTable">
                <thead>
                    <tr>
                        <th class="text-center" style="width:50px;">No</th>
                        <th>Nama / Kamar</th>
                        <th>Detail Pelanggaran</th>
                        <th class="text-center">Poin</th>
                        <th>Waktu</th>
                        <th>Bagian / Tipe</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <?= render_table_rows($all_data, $start_date, $end_date, $bagian, $search, $kamar, $kelas, $jenis_pelanggaran, $offset, $page) ?>
                </tbody>
            </table>
        </div>

        <!-- Footer Tabel: info + pagination -->
        <div class="d-flex flex-column flex-sm-row align-items-center justify-content-between gap-3 px-4 py-3 border-top" id="tablePaginationWrap">
            <div class="text-muted small" id="paginationInfo">
                <?php if ($total_data > 0): ?>
                    Menampilkan <?= $offset + 1 ?>–<?= min($offset + ROWS_PER_PAGE, $total_data) ?> dari <strong><?= $total_data ?></strong> data
                <?php else: ?>
                    Tidak ada data
                <?php endif; ?>
            </div>
            <div class="h-pagination" id="paginationBtns">
                <?= buildPaginationHtml($page, $total_pages) ?>
            </div>
        </div>
    </div>
</div>



<script>
(function() {
    'use strict';

    // ── Referensi DOM ──
    const filterForm     = document.getElementById('filterForm');
    const tableBody      = document.getElementById('tableBody');
    const totalCount     = document.getElementById('totalDataCount');
    const paginationBtns = document.getElementById('paginationBtns');
    const paginationInfo = document.getElementById('paginationInfo');
    const btnLogPen      = document.getElementById('btn-log-penghapusan');
    const tableOverlay   = document.getElementById('tableOverlay');
    const btnReset       = document.getElementById('btnReset');
    const activeFiltersEl = document.getElementById('activeFilters');

    let currentPage      = <?= $page ?>;
    let totalPages       = <?= $total_pages ?>;
    let totalData        = <?= $total_data ?>;
    let activeController = null; // AbortController untuk cancel AJAX duplikat

    // ── Skeleton rows (tampil saat loading) ──
    function skeletonRows(n = 8) {
        let html = '';
        for (let i = 0; i < n; i++) {
            html += `<tr class="skeleton-row">
                <td><span class="skeleton-pulse" style="width:20px;"></span></td>
                <td><span class="skeleton-pulse" style="width:120px;"></span><br>
                    <span class="skeleton-pulse mt-1" style="width:80px;height:10px;"></span></td>
                <td><span class="skeleton-pulse" style="width:160px;"></span></td>
                <td class="text-center"><span class="skeleton-pulse" style="width:32px;"></span></td>
                <td><span class="skeleton-pulse" style="width:80px;"></span><br>
                    <span class="skeleton-pulse mt-1" style="width:50px;height:10px;"></span></td>
                <td><span class="skeleton-pulse" style="width:90px;"></span></td>
                <td class="text-center"><span class="skeleton-pulse" style="width:32px;height:32px;border-radius:9px;"></span></td>
            </tr>`;
        }
        return html;
    }

    // ── Bangun Pagination HTML ──
    function buildPagination(current, total) {
        if (total <= 1) return '';
        let html = '';
        const range = 2;

        // Prev
        html += `<button class="h-page-btn" data-page="${current - 1}" ${current <= 1 ? 'disabled' : ''}>
                    <i class="fas fa-chevron-left"></i>
                 </button>`;

        for (let i = 1; i <= total; i++) {
            if (i === 1 || i === total || Math.abs(i - current) <= range) {
                const active = i === current ? ' active' : '';
                html += `<button class="h-page-btn${active}" data-page="${i}">${i}</button>`;
            } else if (Math.abs(i - current) === range + 1) {
                html += `<span class="h-page-btn" style="border:none;background:transparent;cursor:default;pointer-events:none;">…</span>`;
            }
        }

        // Next
        html += `<button class="h-page-btn" data-page="${current + 1}" ${current >= total ? 'disabled' : ''}>
                    <i class="fas fa-chevron-right"></i>
                 </button>`;
        return html;
    }

    // ── Info teks pagination ──
    function buildInfoText(current, total, totalDataCount) {
        if (totalDataCount === 0) return 'Tidak ada data';
        const rowsPerPage = <?= ROWS_PER_PAGE ?>;
        const from = (current - 1) * rowsPerPage + 1;
        const to   = Math.min(current * rowsPerPage, totalDataCount);
        return `Menampilkan ${from}–${to} dari <strong>${totalDataCount}</strong> data`;
    }

    // ── Label Filter Aktif ──
    const filterLabels = {
        search:            'Nama',
        bagian:            'Bagian',
        kamar:             'Kamar',
        kelas:             'Kelas',
        jenis_pelanggaran: 'Jenis',
    };
    function renderActiveBadges() {
        const fd = new FormData(filterForm);
        let html = '';
        for (const [key, label] of Object.entries(filterLabels)) {
            const val = fd.get(key);
            if (val && val.trim()) {
                // Ambil teks select jika bukan input teks
                let displayVal = val;
                const el = document.getElementById(key === 'search' ? 'searchInput' : key);
                if (el && el.tagName === 'SELECT') {
                    displayVal = el.options[el.selectedIndex]?.text || val;
                }
                html += `<span class="filter-active-badge" data-key="${key}" title="Hapus filter ${label}">
                            ${label}: <em>${displayVal}</em>
                            <i class="fas fa-times remove-icon"></i>
                         </span>`;
            }
        }
        activeFiltersEl.innerHTML = html;

        // Click badge → hapus filter tsb
        activeFiltersEl.querySelectorAll('.filter-active-badge').forEach(badge => {
            badge.addEventListener('click', function() {
                const key = this.dataset.key;
                const el  = filterForm.querySelector(`[name="${key}"]`);
                if (el) {
                    el.value = '';
                    currentPage = 1;
                    loadData();
                }
            });
        });
    }

    // ── Load Data via AJAX ──
    function loadData(targetPage) {
        if (targetPage !== undefined) currentPage = targetPage;

        // Cancel permintaan sebelumnya jika ada
        if (activeController) activeController.abort();
        activeController = new AbortController();

        const fd = new FormData(filterForm);
        fd.set('page', currentPage);
        const params = new URLSearchParams(fd);

        // Update URL (shareable)
        window.history.replaceState({}, '', window.location.pathname + '?' + params);

        // Tampilkan loading state
        tableBody.innerHTML = skeletonRows();
        tableOverlay.classList.add('show');

        fetch('index.php?' + params, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            signal: activeController.signal
        })
        .then(r => r.json())
        .then(data => {
            tableBody.innerHTML    = data.html;
            totalData              = data.total;
            totalPages             = data.total_pages;
            currentPage            = data.current_page;

            totalCount.textContent        = totalData;
            btnLogPen.href                = data.history_link;
            paginationBtns.innerHTML      = buildPagination(currentPage, totalPages);
            paginationInfo.innerHTML      = buildInfoText(currentPage, totalPages, totalData);

            bindPaginationClicks();
            renderActiveBadges();
        })
        .catch(err => {
            if (err.name !== 'AbortError') {
                console.error('AJAX Error:', err);
                tableBody.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>Gagal memuat data. Coba lagi.
                </td></tr>`;
            }
        })
        .finally(() => {
            tableOverlay.classList.remove('show');
        });
    }

    // ── Bind klik pagination ──
    function bindPaginationClicks() {
        paginationBtns.querySelectorAll('.h-page-btn[data-page]').forEach(btn => {
            btn.addEventListener('click', function() {
                const p = parseInt(this.dataset.page);
                if (!isNaN(p) && p !== currentPage && !this.disabled) {
                    loadData(p);
                    // Scroll ke atas tabel
                    document.getElementById('historyTable')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });
    }

    // ── Event Listeners ──
    // Dropdown → langsung load (reset ke halaman 1)
    ['bagian', 'start_date', 'end_date', 'kamar', 'kelas', 'jenis_pelanggaran'].forEach(id => {
        document.getElementById(id)?.addEventListener('change', () => { currentPage = 1; loadData(); });
    });

    // Search dengan debounce 380ms
    let searchTimeout = null;
    document.getElementById('searchInput')?.addEventListener('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => { currentPage = 1; loadData(); }, 380);
    });

    // Submit form → intercept
    filterForm.addEventListener('submit', e => { e.preventDefault(); currentPage = 1; loadData(); });

    // Reset filter
    btnReset.addEventListener('click', () => {
        filterForm.querySelectorAll('input[type="text"]').forEach(el => el.value = '');
        filterForm.querySelectorAll('select').forEach(el => el.value = '');
        // Kembalikan tanggal ke default: start = periode_aktif, end = hari ini
        const sd = filterForm.querySelector('#start_date');
        const ed = filterForm.querySelector('#end_date');
        if (sd) sd.value = '<?= htmlspecialchars($periode_aktif) ?>';
        if (ed) ed.value = '<?= date("Y-m-d") ?>';
        currentPage = 1;
        loadData();
    });

    // Inisialisasi pagination & badge
    bindPaginationClicks();
    renderActiveBadges();

    // ── Konfirmasi Batalkan Pelanggaran (menggunakan SweetAlert yang sudah dimuat di footer) ──
    window.confirmCancel = function(e, form) {
        e.preventDefault();
        const swalFn = typeof Swal !== 'undefined' ? Swal : null;
        if (!swalFn) { return confirm('Yakin batalkan pelanggaran ini?') || false; }
        swalFn.fire({
            title: 'Batalkan Pelanggaran?',
            text: 'Data akan dipindah ke log penghapusan, dan poin akan dikembalikan (jika ada).',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#94a3b8',
            confirmButtonText: '<i class="fas fa-check me-1"></i> Ya, Batalkan',
            cancelButtonText: 'Tutup',
            customClass: {
                popup: 'rounded-4 border-0 shadow-lg',
                confirmButton: 'rounded-pill px-4',
                cancelButton: 'rounded-pill px-4'
            }
        }).then(result => { if (result.isConfirmed) form.submit(); });
        return false;
    };

    // ── Flash Messages (pakai SweetAlert dari footer — tunggu sampai dimuat) ──
    <?php if (isset($_SESSION['pesan_sukses'])): ?>
    (function waitSwalSuccess() {
        if (typeof Swal !== 'undefined') {
            Swal.fire({ icon:'success', title:'Berhasil', text:'<?= addslashes($_SESSION['pesan_sukses']) ?>', timer:2500, showConfirmButton:false, customClass:{popup:'rounded-4'} });
        } else {
            setTimeout(waitSwalSuccess, 100);
        }
    })();
    <?php unset($_SESSION['pesan_sukses']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['pesan_error'])): ?>
    (function waitSwalError() {
        if (typeof Swal !== 'undefined') {
            Swal.fire({ icon:'error', title:'Gagal', text:'<?= addslashes($_SESSION['pesan_error']) ?>', timer:3000, showConfirmButton:false, customClass:{popup:'rounded-4'} });
        } else {
            setTimeout(waitSwalError, 100);
        }
    })();
    <?php unset($_SESSION['pesan_error']); ?>
    <?php endif; ?>

})();
</script>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>