<?php
require_once __DIR__ . '/../../bootstrap/init.php';
guard(['reward_history', 'rekap_per_santri']);
require_once __DIR__ . '/../../layouts/header.php';

// === FILTER ===
$where_clauses = ["1=1"]; $params = []; $types = "";

if (!empty($_GET['search'])) {
    $where_clauses[] = "(s.nama LIKE ?)";
    $params[] = "%".$_GET['search']."%"; $types .= "s";
}
if (!empty($_GET['reward_id'])) {
    $where_clauses[] = "dr.jenis_reward_id = ?";
    $params[] = $_GET['reward_id']; $types .= "i";
}
if (!empty($_GET['kelas'])) {
    $where_clauses[] = "s.kelas = ?";
    $params[] = $_GET['kelas']; $types .= "s";
}
if (!empty($_GET['dicatat_oleh'])) {
    $where_clauses[] = "dr.dicatat_oleh = ?";
    $params[] = $_GET['dicatat_oleh']; $types .= "i";
}

$start = $_GET['start_date'] ?? PERIODE_AKTIF;
$end   = $_GET['end_date'] ?? date('Y-m-d');
if ($start && $end) {
    $where_clauses[] = "DATE(dr.tanggal) BETWEEN ? AND ?";
    $params[] = $start; $params[] = $end; $types .= "ss";
}

$where_sql = implode(" AND ", $where_clauses);

// SORTING
$sort_sql = "dr.tanggal DESC";
if (isset($_GET['sort'])) {
    if ($_GET['sort'] == 'terlama') $sort_sql = "dr.tanggal ASC";
    elseif ($_GET['sort'] == 'poin_tertinggi') $sort_sql = "jr.poin_reward DESC, dr.tanggal DESC";
    elseif ($_GET['sort'] == 'poin_terendah') $sort_sql = "jr.poin_reward ASC, dr.tanggal DESC";
}

// === QUERY ===
$query = "SELECT dr.*, s.nama as nama_santri, s.kelas, jr.nama_reward, jr.poin_reward, u.nama_lengkap as pencatat 
          FROM daftar_reward dr
          JOIN santri s ON dr.santri_id = s.id
          JOIN jenis_reward jr ON dr.jenis_reward_id = jr.id
          LEFT JOIN users u ON dr.dicatat_oleh = u.id
          WHERE $where_sql ORDER BY $sort_sql";

$stmt = mysqli_prepare($conn, $query);
if (!empty($params)) mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Stats & Data
$total_trx = 0; $total_poin = 0; $history = [];
while ($row = mysqli_fetch_assoc($result)) {
    $total_trx++; $total_poin += $row['poin_reward']; $history[] = $row;
}

$q_rewards = mysqli_query($conn, "SELECT DISTINCT jr.id, jr.nama_reward FROM daftar_reward dr JOIN jenis_reward jr ON dr.jenis_reward_id = jr.id ORDER BY jr.nama_reward ASC");
$q_kelas = mysqli_query($conn, "SELECT DISTINCT kelas FROM santri WHERE kelas IS NOT NULL AND kelas != '' ORDER BY kelas ASC");

// Filter Dropdown Pencatat berdasarkan data yg tampil
$p_where = ["1=1"]; $p_params = []; $p_types = "";
if (!empty($_GET['search'])) { $p_where[] = "(s.nama LIKE ?)"; $p_params[] = "%".$_GET['search']."%"; $p_types .= "s"; }
if (!empty($_GET['reward_id'])) { $p_where[] = "dr.jenis_reward_id = ?"; $p_params[] = $_GET['reward_id']; $p_types .= "i"; }
if (!empty($_GET['kelas'])) { $p_where[] = "s.kelas = ?"; $p_params[] = $_GET['kelas']; $p_types .= "s"; }
if ($start && $end) { $p_where[] = "DATE(dr.tanggal) BETWEEN ? AND ?"; $p_params[] = $start; $p_params[] = $end; $p_types .= "ss"; }

$p_sql = "SELECT DISTINCT u.id, u.nama_lengkap FROM daftar_reward dr 
          JOIN users u ON dr.dicatat_oleh = u.id 
          JOIN santri s ON dr.santri_id = s.id 
          WHERE " . implode(" AND ", $p_where) . " ORDER BY u.nama_lengkap ASC";
$stmt_pencatat = mysqli_prepare($conn, $p_sql);
if (!empty($p_params)) mysqli_stmt_bind_param($stmt_pencatat, $p_types, ...$p_params);
mysqli_stmt_execute($stmt_pencatat);
$q_pencatat = mysqli_stmt_get_result($stmt_pencatat);
?>

<style>
    :root { 
        --primary-green: #198754; 
        --soft-green: #d1e7dd;
        --text-dark: #344767;
        --card-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
    }
    
    body { background-color: #f8f9fa; }

    /* Card Styling */
    .modern-card {
        background: #fff;
        border-radius: 16px;
        border: none;
        box-shadow: var(--card-shadow);
        transition: transform 0.2s;
        height: 100%;
    }
    
    .stats-icon {
        width: 48px; 
        height: 48px; 
        border-radius: 12px; 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        font-size: 1.25rem;
    }

    /* Table Styling */
    .table-responsive { border-radius: 16px; }
    .table-modern { margin-bottom: 0; white-space: nowrap; }
    .table-modern th { 
        background: #f8f9fa; 
        color: #8392ab; 
        font-weight: 700; 
        text-transform: uppercase; 
        font-size: 0.7rem; 
        padding: 16px 24px; 
        letter-spacing: 0.5px;
        border-bottom: 1px solid #e9ecef;
    }
    .table-modern td { 
        padding: 16px 24px; 
        vertical-align: middle; 
        border-bottom: 1px solid #f0f2f5; 
        font-size: 0.9rem; 
        color: var(--text-dark);
    }
    .table-modern tr:last-child td { border-bottom: none; }
    .table-modern tr:hover td { background-color: #fcfcfc; }

    /* Badge & Avatar */
    .badge-poin { 
        background: var(--soft-green); 
        color: #0a3622; 
        padding: 6px 12px; 
        border-radius: 30px; /* Lebih rounded */
        font-weight: 700; 
        font-size: 0.85rem; 
        display: inline-flex; /* KUNCI BIAR GAK TURUN */
        align-items: center;
        gap: 6px; 
        min-width: 60px;
        justify-content: center;
    }
    
    .admin-avatar {
        width: 32px;
        height: 32px;
        background: #e9ecef;
        color: #495057;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 0.8rem;
        margin-right: 8px;
    }

    /* Form Inputs */
    .form-control, .form-select { 
        border-radius: 8px; 
        border: 1px solid #e2e8f0; 
        padding: 10px 14px;
        font-size: 0.9rem;
    }
    .form-control:focus, .form-select:focus {
        border-color: var(--primary-green);
        box-shadow: 0 0 0 3px rgba(25, 135, 84, 0.1);
    }
    .input-group .form-control {
        border-radius: 8px 0 0 8px !important;
    }
    .input-group .btn:not(:last-child) {
        border-radius: 0 !important;
    }
    .input-group .btn:last-child {
        border-radius: 0 8px 8px 0 !important;
    }
    
    .filter-label {
        font-size: 0.75rem;
        font-weight: 700;
        color: #8392ab;
        margin-bottom: 6px;
        display: block;
    }
</style>

<div class="content-wrapper" style="padding: 24px;">
    <div class="container-fluid p-0" style="max-width: 1200px; margin: 0 auto;">
        
        <div class="d-md-flex justify-content-between align-items-end mb-4">
            <div>
                <h4 class="fw-bold mb-1" style="color: #344767;">Riwayat Reward & Apresiasi</h4>
                <p class="text-muted small mb-0">Monitor jejak kebaikan dan prestasi santri.</p>
            </div>
            <?php if (has_permission('reward_input')): ?>
            <a href="../input/create.php" class="btn btn-success fw-bold rounded-pill px-4 shadow-sm py-2 mt-3 mt-md-0" style="background: var(--primary-green); border: none;">
                <i class="fas fa-plus-circle me-2"></i> Input Reward
            </a>
            <?php endif; ?>
        </div>

        <div class="modern-card p-4 mb-4">
            <form id="filter-form" method="GET">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="filter-label">DARI TANGGAL</label>
                        <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($start) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="filter-label">SAMPAI TANGGAL</label>
                        <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($end) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="filter-label">JENIS REWARD</label>
                        <select name="reward_id" class="form-select">
                            <option value="">Semua Reward</option>
                            <?php while($r = mysqli_fetch_assoc($q_rewards)): ?>
                                <option value="<?= $r['id'] ?>" <?= ($_GET['reward_id']??'') == $r['id'] ? 'selected' : '' ?>><?= htmlspecialchars($r['nama_reward']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="filter-label">KELAS</label>
                        <select name="kelas" class="form-select">
                            <option value="">Semua Kelas</option>
                            <?php while($k = mysqli_fetch_assoc($q_kelas)): ?>
                                <option value="<?= htmlspecialchars($k['kelas']) ?>" <?= ($_GET['kelas']??'') == $k['kelas'] ? 'selected' : '' ?>><?= htmlspecialchars($k['kelas']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="filter-label">PENCATAT</label>
                        <select name="dicatat_oleh" class="form-select">
                            <option value="">Semua Pencatat</option>
                            <?php while($p = mysqli_fetch_assoc($q_pencatat)): ?>
                                <option value="<?= $p['id'] ?>" <?= ($_GET['dicatat_oleh']??'') == $p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['nama_lengkap']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="filter-label">URUTKAN</label>
                        <select name="sort" class="form-select">
                            <option value="terbaru" <?= ($_GET['sort']??'') == 'terbaru' ? 'selected' : '' ?>>Terbaru</option>
                            <option value="terlama" <?= ($_GET['sort']??'') == 'terlama' ? 'selected' : '' ?>>Terlama</option>
                            <option value="poin_tertinggi" <?= ($_GET['sort']??'') == 'poin_tertinggi' ? 'selected' : '' ?>>Poin Tertinggi</option>
                            <option value="poin_terendah" <?= ($_GET['sort']??'') == 'poin_terendah' ? 'selected' : '' ?>>Poin Terendah</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="filter-label">PENCARIAN NAMA</label>
                        <div class="input-group">
                            <input type="text" name="search" class="form-control" placeholder="Cari nama santri..." value="<?= htmlspecialchars($_GET['search']??'') ?>">
                            <button type="submit" class="btn btn-dark px-4"><i class="fas fa-search me-2"></i>Cari</button>
                            <a href="index.php" class="btn btn-light border px-3" title="Reset Filter"><i class="fas fa-undo"></i></a>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <div id="ajax-data-container" style="transition: opacity 0.2s;">
            <div class="row g-3 mb-4">
                <div class="col-md-6 col-lg-4">
                    <div class="modern-card p-3 d-flex align-items-center">
                        <div class="stats-icon bg-gradient-primary bg-light text-primary me-3">
                            <i class="fas fa-receipt"></i>
                        </div>
                        <div>
                            <span class="d-block text-muted small fw-bold text-uppercase">Total Data Reward</span>
                            <h3 class="mb-0 fw-bold text-dark"><?= number_format($total_trx); ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="modern-card p-3 d-flex align-items-center">
                        <div class="stats-icon" style="background: #d1e7dd; color: #198754;">
                            <i class="fas fa-star"></i>
                        </div>
                        <div class="ms-3">
                            <span class="d-block text-muted small fw-bold text-uppercase">Total Poin Reward</span>
                            <h3 class="mb-0 fw-bold text-success"> <?= number_format($total_poin); ?></h3>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modern-card overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-modern align-middle">
                        <thead>
                            <tr>
                                <th class="text-center" width="5%">No</th>
                                <th width="15%">Waktu</th>
                                <th width="25%">Nama Santri</th>
                                <th width="20%">Bentuk Apresiasi</th>
                                <th class="text-center" width="15%">Nilai</th>
                                <th>Dicatat Oleh</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($history)): ?>
                                <tr><td colspan="6" class="text-center py-5 text-muted fw-bold">Belum ada data reward pada periode ini.</td></tr>
                            <?php else: $no=1; foreach($history as $row): ?>
                                <tr>
                                    <td class="text-center text-muted small fw-bold"><?= $no++ ?></td>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <span class="fw-bold text-dark" style="font-size: 0.9rem;"><?= date('d M Y', strtotime($row['tanggal'])) ?></span>
                                            <span class="text-muted" style="font-size: 0.75rem;"><i class="far fa-clock me-1"></i><?= date('H:i', strtotime($row['tanggal'])) ?> WIB</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark mb-0"><?= $row['nama_santri'] ?></div>
                                        <div class="small text-muted">Kelas <?= $row['kelas'] ?></div>
                                    </td>
                                    <td>
                                        <span class="text-dark fw-bold"><?= $row['nama_reward'] ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge-poin">
                                            <i class="fas fa-check-circle"></i> <?= $row['poin_reward'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                            $adminName = ucwords($row['pencatat'] ?? 'System');
                                            $initial = substr($adminName, 0, 1);
                                        ?>
                                        <div class="d-flex align-items-center">
                                            <div class="admin-avatar"><?= $initial ?></div>
                                            <span class="small fw-bold text-secondary"><?= $adminName ?></span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="p-3 border-top bg-light">
                    <small class="text-muted fw-bold ms-2">Menampilkan <?= count($history) ?> data Reward.</small>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    function submitAjax(url) {
        const form = document.getElementById('filter-form');
        const container = document.getElementById('ajax-data-container');
        
        let fetchUrl = url;
        if (!fetchUrl) {
            const formData = new FormData(form);
            const params = new URLSearchParams(formData);
            fetchUrl = '?' + params.toString();
        }
        
        container.style.opacity = '0.4';
        
        fetch(fetchUrl)
            .then(res => res.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                
                const newContent = doc.getElementById('ajax-data-container');
                if (newContent) container.innerHTML = newContent.innerHTML;
                
                const newForm = doc.getElementById('filter-form');
                if (newForm) form.innerHTML = newForm.innerHTML;
                
                container.style.opacity = '1';
                window.history.pushState({}, '', fetchUrl);
            })
            .catch(() => {
                // Fallback on error
                window.location.href = fetchUrl;
            });
    }

    document.addEventListener('change', function(e) {
        if(e.target.closest('#filter-form') && (e.target.type === 'date' || e.target.tagName === 'SELECT')) {
            submitAjax();
        }
    });

    document.addEventListener('submit', function(e) {
        if(e.target.id === 'filter-form') {
            e.preventDefault();
            submitAjax();
        }
    });

    document.addEventListener('click', function(e) {
        const resetBtn = e.target.closest('#filter-form a[href="index.php"]');
        if(resetBtn) {
            e.preventDefault();
            submitAjax('index.php');
        }
    });
});
</script>

<?php 
mysqli_stmt_close($stmt);
if (isset($stmt_pencatat)) mysqli_stmt_close($stmt_pencatat);
require_once __DIR__ . '/../../layouts/footer.php'; 
?>