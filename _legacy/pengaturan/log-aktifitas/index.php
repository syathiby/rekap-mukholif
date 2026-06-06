<?php
// FILE: index.php (pengaturan/log-aktifitas/index.php)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../bootstrap/init.php';
guard('activity_log_manage');

// ─── PARAMETER FILTER & PAGINASI ────────────────────────────────────
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date   = $_GET['end_date'] ?? date('Y-m-d');
$aksi       = $_GET['aksi'] ?? '';
$fitur      = $_GET['fitur'] ?? '';
$search     = trim($_GET['search'] ?? '');
$page       = max(1, (int)($_GET['page'] ?? 1));
$limit      = 25;
$offset     = ($page - 1) * $limit;

// ─── QUERY BUILDER ──────────────────────────────────────────────────
$conditions = ["DATE(dibuat_pada) >= ?", "DATE(dibuat_pada) <= ?"];
$params = [$start_date, $end_date];
$types = 'ss';

if (!empty($aksi)) {
    $conditions[] = "aksi = ?";
    $params[] = $aksi;
    $types .= 's';
}

if (!empty($fitur)) {
    $conditions[] = "fitur = ?";
    $params[] = $fitur;
    $types .= 's';
}

if (!empty($search)) {
    $conditions[] = "(username LIKE ? OR nama_lengkap LIKE ? OR deskripsi LIKE ? OR ip_address LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ssss';
}

$where_clause = implode(" AND ", $conditions);

// 1. Hitung Total Data (untuk paginasi)
$count_query = "SELECT COUNT(*) FROM log_aktifitas WHERE $where_clause";
$stmt_count = $conn->prepare($count_query);
$stmt_count->bind_param($types, ...$params);
$stmt_count->execute();
$total_data = $stmt_count->get_result()->fetch_row()[0];
$stmt_count->close();

$total_pages = ceil($total_data / $limit);

// 2. Fetch Data Log Terfilter
$data_query = "
    SELECT id, user_id, username, nama_lengkap, aksi, fitur, deskripsi, detail, ip_address, user_agent, dibuat_pada 
    FROM log_aktifitas 
    WHERE $where_clause 
    ORDER BY dibuat_pada DESC 
    LIMIT ? OFFSET ?
";
$params_data = array_merge($params, [$limit, $offset]);
$types_data = $types . 'ii';

$stmt_data = $conn->prepare($data_query);
$stmt_data->bind_param($types_data, ...$params_data);
$stmt_data->execute();
$logs = $stmt_data->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_data->close();

// 3. (Dipindahkan ke bawah AJAX check agar filter lebih cepat)

// ─── BACK PARAM (dikirim ke detail_log agar tombol kembali ingat filter) ─
$back_param = urlencode(http_build_query(array_filter([
    'start_date' => $start_date,
    'end_date'   => $end_date,
    'aksi'       => $aksi,
    'fitur'      => $fitur,
    'search'     => $search,
    'page'       => $page > 1 ? (string)$page : '',
], fn($v) => $v !== '' && $v !== null)));

// ─── HELPER: Badge config ────────────────────────────────────────────
function get_badge_config($aksi) {
    switch ($aksi) {
        case 'CREATE':           return ['cls' => 'badge-create',  'icon' => 'fa-plus'];
        case 'UPDATE':
        case 'UPDATE_PERMISSION':return ['cls' => 'badge-update',  'icon' => 'fa-pencil'];
        case 'DELETE':           return ['cls' => 'badge-delete',  'icon' => 'fa-trash'];
        case 'LOGIN':            return ['cls' => 'badge-login',   'icon' => 'fa-sign-in-alt'];
        case 'LOGOUT':           return ['cls' => 'badge-logout',  'icon' => 'fa-sign-out-alt'];
        case 'RESET_POIN':
        case 'CHANGE_PERIODE':   return ['cls' => 'badge-system',  'icon' => 'fa-sync-alt'];
        case 'BACKUP':
        case 'RESTORE':          return ['cls' => 'badge-db',      'icon' => 'fa-database'];
        default:                 return ['cls' => 'badge-default', 'icon' => 'fa-circle'];
    }
}

// ─── RENDER BARIS TABEL ─────────────────────────────────────────────
function render_log_rows($logs, $offset, $back_param = '') {
    ob_start();
    if (count($logs) > 0):
        $no = $offset + 1;
        foreach($logs as $row):
            $cfg = get_badge_config($row['aksi']);
            ?>
            <tr class="log-row" id="row-<?= $row['id'] ?>">
                <td class="text-center text-muted" style="font-size:0.8rem; width:44px;"><?= $no++ ?></td>
                <td style="width:170px;">
                    <span class="log-badge <?= $cfg['cls'] ?>">
                        <i class="fas <?= $cfg['icon'] ?> me-1"></i><?= htmlspecialchars($row['aksi']) ?>
                    </span>
                </td>
                <td style="width:120px;">
                    <span class="fitur-tag"><?= htmlspecialchars($row['fitur']) ?></span>
                </td>
                <td>
                    <span class="text-dark" style="font-size:0.855rem; line-height:1.4;"><?= htmlspecialchars(mb_strlen($row['deskripsi']) > 60 ? mb_substr($row['deskripsi'], 0, 60) . '…' : $row['deskripsi']) ?></span>
                </td>
                <td style="width:140px;">
                    <div class="fw-semibold text-dark text-truncate" style="max-width:130px; font-size:0.875rem;"><?= htmlspecialchars($row['nama_lengkap']) ?></div>
                    <div class="text-muted text-truncate" style="max-width:130px; font-size:0.775rem;">@<?= htmlspecialchars($row['username']) ?></div>
                </td>
                <td class="text-nowrap" style="width:130px;">
                    <div class="text-dark" style="font-size:0.875rem;"><?= date('d M Y', strtotime($row['dibuat_pada'])) ?></div>
                    <div class="text-muted" style="font-size:0.775rem;"><i class="far fa-clock me-1"></i><?= date('H:i:s', strtotime($row['dibuat_pada'])) ?> WIB</div>
                </td>
                <td class="text-center" style="width:52px;">
                    <a href="detail_log.php?id=<?= $row['id'] ?><?= $back_param !== '' ? '&back=' . $back_param : '' ?>" class="btn-detail" title="Lihat Detail">
                        <i class="fas fa-eye"></i>
                    </a>
                </td>
            </tr>
        <?php endforeach;
    else: ?>
        <tr>
            <td colspan="7" class="text-center py-5">
                <div class="text-muted d-flex flex-column align-items-center gap-2" style="opacity:0.5;">
                    <i class="fas fa-scroll fa-3x" style="opacity:0.3;"></i>
                    <div>
                        <div class="fw-semibold">Tidak ada log ditemukan</div>
                        <div class="small">Coba ubah filter pencarian Anda.</div>
                    </div>
                </div>
            </td>
        </tr>
    <?php endif;
    return ob_get_clean();
}

// ─── RENDER PAGINASI ────────────────────────────────────────────────
function render_pagination_html($page, $total_pages) {
    ob_start();
    if ($total_pages > 1): ?>
        <nav aria-label="Navigasi Halaman Log">
            <ul class="pagination pagination-sm justify-content-center mb-0 gap-1">
                <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                    <a class="page-link" href="#" data-page="<?= $page - 1 ?>" aria-label="Previous">
                        <i class="fas fa-chevron-left" style="font-size:0.65rem;"></i>
                    </a>
                </li>
                <?php
                $start = max(1, $page - 2);
                $end = min($total_pages, $page + 2);
                if ($start > 1) {
                    echo '<li class="page-item"><a class="page-link" href="#" data-page="1">1</a></li>';
                    if ($start > 2) echo '<li class="page-item disabled"><span class="page-link border-0 bg-transparent px-2">…</span></li>';
                }
                for ($i = $start; $i <= $end; $i++) {
                    $act = ($i === $page) ? 'active' : '';
                    echo '<li class="page-item ' . $act . '"><a class="page-link" href="#" data-page="' . $i . '">' . $i . '</a></li>';
                }
                if ($end < $total_pages) {
                    if ($end < $total_pages - 1) echo '<li class="page-item disabled"><span class="page-link border-0 bg-transparent px-2">…</span></li>';
                    echo '<li class="page-item"><a class="page-link" href="#" data-page="' . $total_pages . '">' . $total_pages . '</a></li>';
                }
                ?>
                <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                    <a class="page-link" href="#" data-page="<?= $page + 1 ?>" aria-label="Next">
                        <i class="fas fa-chevron-right" style="font-size:0.65rem;"></i>
                    </a>
                </li>
            </ul>
        </nav>
    <?php endif;
    return ob_get_clean();
}

// ─── TANGGAPI REQUEST AJAX ──────────────────────────────────────────
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
if ($is_ajax) {
    header('Content-Type: application/json');
    echo json_encode([
        'html'       => render_log_rows($logs, $offset, $back_param),
        'total'      => $total_data,
        'pagination' => render_pagination_html($page, $total_pages)
    ]);
    exit;
}

// 3. Fetch Statistik All-Time (Hanya saat render halaman pertama, BUKAN saat AJAX filter)
$q_stats = mysqli_query($conn, "
    SELECT 
        COUNT(*) as total_all,
        COUNT(CASE WHEN aksi = 'LOGIN' THEN 1 END) as total_login,
        COUNT(CASE WHEN aksi IN ('CREATE', 'UPDATE', 'DELETE') THEN 1 END) as total_crud,
        COUNT(CASE WHEN aksi IN ('BACKUP', 'RESTORE') THEN 1 END) as total_db
    FROM log_aktifitas
");
$stats = mysqli_fetch_assoc($q_stats);

require_once __DIR__ . '/../../layouts/header.php';
?>

<style>
    /* ── LOCAL VARS ── */
    :root {
        --p:      #4f46e5;
        --p-h:    #4338ca;
        --p-lt:   #e0e7ff;
        --p-xlt:  #eef2ff;
        --bd:     #e5e7eb;
        --tx:     #111827;
        --mu:     #6b7280;
        --bg:     #f3f4f6;
    }

    /* ── STAT CARD — clean, no gimmicks ── */
    .stat-item {
        background: #fff;
        border: 1px solid var(--bd);
        border-radius: 0.875rem;
        padding: 1.25rem 1rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        transition: box-shadow 0.2s ease, transform 0.2s ease;
    }
    .stat-item:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 18px rgba(0,0,0,0.07);
    }
    .stat-item-icon {
        width: 44px; height: 44px;
        border-radius: 10px;
        background: var(--p-xlt);
        color: var(--p);
        display: flex; align-items: center; justify-content: center;
        font-size: 1rem;
        flex-shrink: 0;
    }
    .stat-item-label {
        font-size: 0.7rem;
        font-weight: 600;
        color: var(--mu);
        text-transform: uppercase;
        letter-spacing: 0.06em;
        margin-bottom: 0.15rem;
    }
    .stat-item-value {
        font-size: 1.4rem;
        font-weight: 700;
        color: var(--tx);
        line-height: 1;
    }
    .stat-item-sub {
        font-size: 0.72rem;
        color: var(--mu);
        margin-top: 0.15rem;
    }

    /* ── FILTER CARD ── */
    .filter-card {
        background: #fff;
        border: 1px solid var(--bd);
        border-radius: 0.875rem;
        padding: 1.25rem 1.5rem;
    }
    .filter-label {
        font-size: 0.72rem;
        font-weight: 600;
        color: var(--mu);
        text-transform: uppercase;
        letter-spacing: 0.06em;
        margin-bottom: 0.35rem;
        display: block;
    }
    .form-control, .form-select {
        border-radius: 0.5rem;
        border: 1px solid #d1d5db;
        padding: 0.55rem 0.875rem;
        font-size: 0.875rem;
        background: #f9fafb;
        transition: border-color 0.2s, box-shadow 0.2s;
    }
    .form-control:focus, .form-select:focus {
        border-color: var(--p);
        box-shadow: 0 0 0 3px rgba(79,70,229,0.1);
        background: #fff;
    }
    .input-group-text {
        background: #f9fafb;
        border: 1px solid #d1d5db;
        color: var(--mu);
        border-radius: 0.5rem;
        font-size: 0.875rem;
    }

    /* ── TABLE ── */
    .table-card {
        background: #fff;
        border: 1px solid var(--bd);
        border-radius: 0.875rem;
        overflow: hidden;
    }
    .table { margin: 0; }
    .table thead th {
        background: #f9fafb;
        color: var(--mu);
        font-weight: 600;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.07em;
        padding: 0.75rem 1rem;
        border-bottom: 1px solid var(--bd);
        white-space: nowrap;
    }
    .table tbody td {
        padding: 0.75rem 1rem;
        vertical-align: middle;
        border-bottom: 1px solid #f3f4f6;
        font-size: 0.875rem;
    }
    .table tbody tr:last-child td { border-bottom: none; }
    .table tbody tr:hover td { background: #f9fafb; }

    /* ── BADGES ── */
    .log-badge {
        display: inline-flex; align-items: center;
        font-size: 0.68rem; font-weight: 600;
        letter-spacing: 0.04em; text-transform: uppercase;
        padding: 0.25rem 0.6rem; border-radius: 5px;
        white-space: nowrap;
        border: 1px solid transparent;
    }
    .badge-create  { background:#f0fdf4; color:#166534; border-color:#bbf7d0; }
    .badge-update  { background:#eff6ff; color:#1e40af; border-color:#bfdbfe; }
    .badge-delete  { background:#fef2f2; color:#991b1b; border-color:#fecaca; }
    .badge-login   { background:var(--p-xlt); color:var(--p-h); border-color:var(--p-lt); }
    .badge-logout  { background:#f9fafb; color:#6b7280; border-color:#e5e7eb; }
    .badge-system  { background:#faf5ff; color:#6d28d9; border-color:#e9d5ff; }
    .badge-db      { background:#fffbeb; color:#92400e; border-color:#fde68a; }
    .badge-default { background:#f3f4f6; color:#6b7280; border-color:#e5e7eb; }

    /* ── FITUR TAG ── */
    .fitur-tag {
        display: inline-block;
        background: #f3f4f6;
        color: #374151;
        border: 1px solid #e5e7eb;
        border-radius: 4px;
        padding: 0.2rem 0.5rem;
        font-size: 0.72rem;
        font-weight: 500;
        white-space: nowrap;
    }

    /* ── DETAIL BUTTON ── */
    .btn-detail {
        width: 28px; height: 28px;
        display: inline-flex; align-items: center; justify-content: center;
        border-radius: 6px;
        background: var(--p-xlt);
        border: 1px solid var(--p-lt);
        color: var(--p);
        font-size: 0.72rem;
        transition: all 0.18s ease;
        text-decoration: none;
    }
    .btn-detail:hover {
        background: var(--p);
        color: #fff;
        border-color: var(--p-h);
        transform: translateY(-1px);
        box-shadow: 0 3px 10px rgba(79,70,229,0.28);
    }

    /* ── BERSIHKAN LOG BUTTON ── */
    .btn-clean {
        display: inline-flex; align-items: center; gap: 0.5rem;
        background: transparent;
        border: 1px solid #e5e7eb;
        color: #6b7280;
        border-radius: 0.5rem;
        padding: 0.55rem 1.1rem;
        font-size: 0.875rem;
        font-weight: 500;
        transition: all 0.2s;
        text-decoration: none;
        cursor: pointer;
    }
    .btn-clean:hover {
        border-color: #fca5a5;
        color: #b91c1c;
        background: #fef2f2;
    }

    /* ── PAGINATION ── */
    .page-link {
        color: var(--p);
        font-size: 0.825rem;
        padding: 0.4rem 0.75rem;
        border-color: var(--bd);
        border-radius: 0.375rem !important;
        transition: all 0.15s;
    }
    .page-link:hover { background: var(--p-xlt); border-color: var(--p-lt); color: var(--p-h); }
    .page-item.active .page-link { background: var(--p); border-color: var(--p-h); color: #fff; }
    .page-item.disabled .page-link { opacity: 0.4; }

    /* ── LOADING ── */
    #tableBody.loading { opacity: 0.35; pointer-events: none; transition: opacity 0.2s; }

    /* ── INFO BUTTON ── */
    .btn-info-trigger {
        width: 22px; height: 22px;
        display: inline-flex; align-items: center; justify-content: center;
        border-radius: 50%;
        background: #f3f4f6;
        border: 1px solid #d1d5db;
        color: #6b7280;
        font-size: 0.72rem;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.18s ease;
        flex-shrink: 0;
        vertical-align: middle;
        font-style: italic;
        font-family: Georgia, serif;
        line-height: 1;
    }
    .btn-info-trigger:hover {
        background: var(--p-xlt);
        border-color: var(--p-lt);
        color: var(--p);
    }

    /* ── INFO MODAL ── */
    .info-modal-icon-wrap {
        width: 40px; height: 40px;
        border-radius: 10px;
        background: var(--p-xlt);
        display: flex; align-items: center; justify-content: center;
        color: var(--p); font-size: 1rem;
        flex-shrink: 0;
    }
    .info-section-title {
        font-size: 0.7rem;
        font-weight: 700;
        color: var(--mu);
        text-transform: uppercase;
        letter-spacing: 0.07em;
        margin-bottom: 0.5rem;
    }
    .info-badge-row {
        display: flex; flex-wrap: wrap; gap: 0.4rem;
        margin-bottom: 0.75rem;
    }
    .info-note {
        background: #fffbeb;
        border: 1px solid #fde68a;
        border-radius: 0.5rem;
        padding: 0.6rem 0.875rem;
        font-size: 0.8rem;
        color: #92400e;
        display: flex; align-items: flex-start; gap: 0.5rem;
    }
    .info-note i { margin-top: 2px; flex-shrink: 0; }

    /* ── RESPONSIVE ── */
    @media (max-width: 575px) {
        /* Stat card: susun vertikal + rata tengah di mobile */
        .stat-item {
            flex-direction: column;
            align-items: center;
            text-align: center;
            padding: 0.85rem 0.75rem;
            gap: 0.4rem;
        }
        .stat-item-icon {
            width: 34px; height: 34px;
            border-radius: 8px;
            font-size: 0.85rem;
        }
        .stat-item-label {
            font-size: 0.62rem;
            letter-spacing: 0.04em;
        }
        .stat-item-value { font-size: 1.15rem; }
        .stat-item-sub   { font-size: 0.65rem; }
    }
</style>

<div class="container-fluid py-4 px-4">

    <!-- ── PAGE HEADER ── -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-start mb-4 gap-2">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <h4 class="fw-bold mb-0" style="color:var(--tx); letter-spacing:-0.3px;">Audit Log Aktivitas</h4>
                <button type="button" class="btn-info-trigger" data-bs-toggle="modal" data-bs-target="#modalInfoLog" title="Informasi fitur ini">i</button>
            </div>
            <p class="mb-0 text-muted" style="font-size:0.875rem;">Catatan terperinci aktivitas pengguna, perubahan sistem, dan aksi CRUD.</p>
        </div>
        <?php if ($_SESSION['role'] === 'admin'): ?>
        <div class="flex-shrink-0">
            <button type="button" class="btn-clean" onclick="triggerCleanLogs()">
                <i class="fas fa-trash-alt" style="font-size:0.8rem;"></i> Bersihkan Log
            </button>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── STATISTIK ── -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6 col-6">
            <div class="stat-item">
                <div class="stat-item-icon"><i class="fas fa-list-ul"></i></div>
                <div>
                    <div class="stat-item-label">Ditampilkan</div>
                    <div class="stat-item-value" id="stat-total"><?= number_format($total_data) ?></div>
                    <div class="stat-item-sub">dari filter aktif</div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 col-6">
            <div class="stat-item">
                <div class="stat-item-icon"><i class="fas fa-sign-in-alt"></i></div>
                <div>
                    <div class="stat-item-label">Total Login</div>
                    <div class="stat-item-value"><?= number_format($stats['total_login']) ?></div>
                    <div class="stat-item-sub">semua waktu</div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 col-6">
            <div class="stat-item">
                <div class="stat-item-icon"><i class="fas fa-pen"></i></div>
                <div>
                    <div class="stat-item-label">Aksi CRUD</div>
                    <div class="stat-item-value"><?= number_format($stats['total_crud']) ?></div>
                    <div class="stat-item-sub">semua waktu</div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 col-6">
            <div class="stat-item">
                <div class="stat-item-icon"><i class="fas fa-database"></i></div>
                <div>
                    <div class="stat-item-label">Operasi DB</div>
                    <div class="stat-item-value"><?= number_format($stats['total_db']) ?></div>
                    <div class="stat-item-sub">semua waktu</div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── FILTER ── -->
    <div class="filter-card mb-4">
        <form id="filterForm" autocomplete="off">
            <input type="hidden" name="page" id="pageInput" value="<?= $page ?>">
            <div class="row g-3 align-items-end">
                <div class="col-xl-3 col-lg-3 col-md-12">
                    <label class="filter-label">Cari</label>
                    <div class="input-group">
                        <span class="input-group-text border-end-0 bg-transparent pe-0">
                            <i class="fas fa-search text-muted" style="font-size:0.8rem;"></i>
                        </span>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                            class="form-control border-start-0"
                            placeholder="Nama, deskripsi, IP…" id="searchInput">
                    </div>
                </div>
                <div class="col-xl-2 col-lg-2 col-md-3 col-6">
                    <label class="filter-label">Dari Tanggal</label>
                    <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" class="form-control">
                </div>
                <div class="col-xl-2 col-lg-2 col-md-3 col-6">
                    <label class="filter-label">Sampai Tanggal</label>
                    <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" class="form-control">
                </div>
                <div class="col-xl-2 col-lg-2 col-md-3 col-6">
                    <label class="filter-label">Jenis Aksi</label>
                    <select name="aksi" id="aksi" class="form-select">
                        <option value="">Semua Aksi</option>
                        <?php foreach (['CREATE','UPDATE','DELETE','LOGIN','LOGOUT','RESET_POIN'=>'RESET POIN','CHANGE_PERIODE'=>'GANTI PERIODE','UPDATE_PERMISSION'=>'IZIN / RBAC','BACKUP','RESTORE'] as $val => $lbl):
                            $v = is_int($val) ? $lbl : $val;
                            $l = $lbl;
                        ?>
                        <option value="<?= $v ?>" <?= ($aksi === $v) ? 'selected' : '' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-xl-3 col-lg-3 col-md-3 col-6">
                    <label class="filter-label">Fitur / Modul</label>
                    <select name="fitur" id="fitur" class="form-select">
                        <option value="">Semua Fitur</option>
                        <?php foreach ([
                            'auth'             => 'Authentication',
                            'santri'           => 'Master Santri',
                            'pelanggaran'      => 'Pelanggaran',
                            'reward'           => 'Reward / Prestasi',
                            'jenis_pelanggaran'=> 'Katalog Pelanggaran',
                            'users'            => 'User / Staf',
                            'izin'             => 'Izin Akses (RBAC)',
                            'periode-aktif'    => 'Periode Aktif',
                            'reset-poin'       => 'Reset Poin',
                            'backup-restore'   => 'Backup & Restore',
                            'log-aktifitas'    => 'Log Aktivitas',
                        ] as $v => $l): ?>
                        <option value="<?= $v ?>" <?= ($fitur === $v) ? 'selected' : '' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </form>
    </div>

    <!-- ── TABEL ── -->
    <div class="table-card mb-3">
        <div class="table-responsive">
            <table class="table align-middle" id="logTable" style="min-width:720px; table-layout:fixed;">
                <thead>
                    <tr>
                        <th class="text-center" style="width:44px;">No</th>
                        <th style="width:170px;">Aksi</th>
                        <th style="width:120px;">Fitur</th>
                        <th>Deskripsi</th>
                        <th style="width:140px;">Pengguna</th>
                        <th style="width:130px;">Waktu</th>
                        <th class="text-center" style="width:52px;"></th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <?= render_log_rows($logs, $offset, $back_param) ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ── PAGINASI ── -->
    <div id="paginationContainer" class="d-flex justify-content-center py-2">
        <?= render_pagination_html($page, $total_pages) ?>
    </div>

</div>

<!-- ── MODAL INFORMASI LOG AKTIVITAS ── -->
<div class="modal fade" id="modalInfoLog" tabindex="-1" aria-labelledby="modalInfoLogLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 520px;">
        <div class="modal-content border-0" style="border-radius: 1rem; box-shadow: 0 20px 40px rgba(0,0,0,0.12);">

            <!-- Header -->
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="info-modal-icon-wrap"><i class="fas fa-scroll"></i></div>
                    <div>
                        <h6 class="fw-bold mb-0" id="modalInfoLogLabel" style="color:var(--tx);">Tentang Audit Log Aktivitas</h6>
                        <p class="mb-0 text-muted" style="font-size:0.78rem;">Panduan singkat memahami fitur ini</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>

            <!-- Body -->
            <div class="modal-body px-4 py-3">

                <!-- Apa itu? -->
                <p style="font-size:0.85rem; color:#374151; line-height:1.65;">
                    Halaman ini mencatat setiap <strong>aktivitas penting</strong> yang dilakukan oleh pengguna sistem — mulai dari login, perubahan data, hingga operasi database. Log bersifat <strong>read-only</strong> dan tidak bisa dimanipulasi oleh pengguna biasa.
                </p>

                <hr style="border-color:#f3f4f6; margin: 0.75rem 0;">

                <!-- Jenis Aksi -->
                <div class="info-section-title">Jenis Aksi yang Dicatat</div>
                <div class="info-badge-row">
                    <span class="log-badge badge-create"><i class="fas fa-plus me-1"></i>CREATE</span>
                    <span class="log-badge badge-update"><i class="fas fa-pencil me-1"></i>UPDATE</span>
                    <span class="log-badge badge-delete"><i class="fas fa-trash me-1"></i>DELETE</span>
                    <span class="log-badge badge-login"><i class="fas fa-sign-in-alt me-1"></i>LOGIN</span>
                    <span class="log-badge badge-logout"><i class="fas fa-sign-out-alt me-1"></i>LOGOUT</span>
                    <span class="log-badge badge-system"><i class="fas fa-sync-alt me-1"></i>SYSTEM</span>
                    <span class="log-badge badge-db"><i class="fas fa-database me-1"></i>BACKUP / RESTORE</span>
                </div>

                <!-- Statistik -->
                <div class="info-section-title mt-1">Catatan Statistik</div>
                <p style="font-size:0.82rem; color:#6b7280; margin-bottom:0.75rem;">
                    Angka pada kartu statistik di atas (Total Login, Aksi CRUD, Operasi DB) bersifat <strong>all-time</strong> — menghitung seluruh data sejak sistem berjalan, tidak terpengaruh oleh filter tanggal atau pencarian.
                    Hanya angka <strong>"Ditampilkan"</strong> yang mengikuti filter aktif.
                </p>

                <!-- Peringatan -->
                <div class="info-note">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>Fitur <strong>Bersihkan Log</strong> hanya tersedia untuk Admin dan bersifat permanen. Log yang dihapus <u>tidak dapat dipulihkan</u>. Gunakan dengan bijak.</span>
                </div>

            </div>

            <!-- Footer -->
            <div class="modal-footer border-0 pt-0 px-4 pb-4">
                <button type="button" class="btn btn-sm btn-primary rounded-3 px-4" data-bs-dismiss="modal" style="background:var(--p); border-color:var(--p-h); font-size:0.825rem;">Mengerti</button>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const filterForm          = document.getElementById('filterForm');
    const tableBody           = document.getElementById('tableBody');
    const paginationContainer = document.getElementById('paginationContainer');
    const statTotal           = document.getElementById('stat-total');
    const pageInput           = document.getElementById('pageInput');

    function loadData(pageNumber = 1) {
        pageInput.value = pageNumber;
        const params = new URLSearchParams(new FormData(filterForm));
        window.history.replaceState({}, '', window.location.pathname + '?' + params.toString());
        tableBody.classList.add('loading');
        fetch('index.php?' + params.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => { if (!r.ok) throw new Error(r.status); return r.json(); })
            .then(data => {
                tableBody.innerHTML = data.html;
                statTotal.textContent = Number(data.total).toLocaleString('id-ID');
                paginationContainer.innerHTML = data.pagination;
                tableBody.classList.remove('loading');
                bindPagination();
            })
            .catch(() => tableBody.classList.remove('loading'));
    }

    function bindPagination() {
        document.querySelectorAll('#paginationContainer .page-link').forEach(link => {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                const tp = this.getAttribute('data-page');
                if (tp && !this.closest('.page-item').classList.contains('disabled') && !this.closest('.page-item').classList.contains('active')) {
                    loadData(parseInt(tp));
                }
            });
        });
    }

    document.getElementById('aksi').addEventListener('change', () => loadData(1));
    document.getElementById('fitur').addEventListener('change', () => loadData(1));
    document.getElementById('start_date').addEventListener('change', () => loadData(1));
    document.getElementById('end_date').addEventListener('change', () => loadData(1));

    let timeout = null;
    document.getElementById('searchInput').addEventListener('input', () => {
        clearTimeout(timeout);
        timeout = setTimeout(() => loadData(1), 400);
    });

    filterForm.addEventListener('submit', e => { e.preventDefault(); loadData(1); });
    bindPagination();
});

function triggerCleanLogs() {
    Swal.fire({
        title: 'Bersihkan Log Aktivitas?',
        html: '<p class="text-muted mb-0" style="font-size:0.875rem;">Pilih opsi pembersihan. Tindakan ini membutuhkan konfirmasi password admin.</p>',
        icon: 'warning',
        showCancelButton: true,
        showDenyButton: true,
        confirmButtonColor: '#4f46e5',
        denyButtonColor: '#6b7280',
        cancelButtonColor: '#9ca3af',
        confirmButtonText: '<i class="fas fa-trash-alt me-1"></i> Hapus Semua',
        denyButtonText: '<i class="fas fa-calendar-times me-1"></i> Hapus > 6 Bulan',
        cancelButtonText: 'Batal',
        customClass: { popup: 'rounded-4 border-0 shadow-lg', confirmButton: 'rounded-3 px-3 py-2', denyButton: 'rounded-3 px-3 py-2', cancelButton: 'rounded-3 px-3 py-2' }
    }).then((result) => {
        let actionOpsi = '';
        if (result.isConfirmed) actionOpsi = 'all';
        else if (result.isDenied) actionOpsi = 'older_6_months';
        if (actionOpsi !== '') {
            Swal.fire({
                title: 'Konfirmasi Keamanan',
                html: '<p class="text-muted mb-0" style="font-size:0.875rem;">Masukkan password admin Anda untuk melanjutkan.</p>',
                input: 'password',
                inputAttributes: { autocapitalize: 'off', autocorrect: 'off', placeholder: 'Password admin…' },
                inputValidator: (value) => { if (!value || value.trim() === '') return 'Password wajib diisi!'; },
                showCancelButton: true,
                confirmButtonColor: '#4f46e5',
                cancelButtonColor: '#9ca3af',
                confirmButtonText: 'Verifikasi & Bersihkan',
                cancelButtonText: 'Kembali',
                customClass: { popup: 'rounded-4', confirmButton: 'rounded-3 px-4', cancelButton: 'rounded-3 px-4' },
                showLoaderOnConfirm: true,
                preConfirm: (password) => {
                    const formData = new FormData();
                    formData.append('action', 'clean_logs');
                    formData.append('scope', actionOpsi);
                    formData.append('password', password);
                    return fetch('process.php', { method: 'POST', body: formData })
                        .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
                        .then(data => { if (!data.success) throw new Error(data.message || 'Gagal memverifikasi.'); return data; })
                        .catch(err => Swal.showValidationMessage(`Gagal: ${err.message}`));
                },
                allowOutsideClick: () => !Swal.isLoading()
            }).then((result) => {
                if (result.isConfirmed && result.value && result.value.success) {
                    Swal.fire({
                        icon: 'success', title: 'Berhasil!',
                        text: result.value.message || 'Log berhasil dibersihkan.',
                        confirmButtonColor: '#4f46e5',
                        customClass: { popup: 'rounded-4', confirmButton: 'rounded-3 px-4' }
                    }).then(() => window.location.reload());
                }
            });
        }
    });
}
</script>

<?php
if (isset($_SESSION['pesan_sukses'])):
    $msg_sukses = json_encode($_SESSION['pesan_sukses']);
    unset($_SESSION['pesan_sukses']);
?>
<script>
Swal.fire({ icon: 'success', title: 'Berhasil', text: <?= $msg_sukses ?>, timer: 2500, showConfirmButton: false, customClass: { popup: 'rounded-4' } });
</script>
<?php endif; ?>

<?php if (isset($_SESSION['pesan_error'])):
    $msg_error = json_encode($_SESSION['pesan_error']);
    unset($_SESSION['pesan_error']);
?>
<script>
Swal.fire({ icon: 'error', title: 'Gagal', text: <?= $msg_error ?>, timer: 3000, showConfirmButton: false, customClass: { popup: 'rounded-4' } });
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
