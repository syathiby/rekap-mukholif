<?php
require_once __DIR__ . '/../../init.php';
guard('reward_history');
require_once __DIR__ . '/../../header.php';

// === FILTER ===
$where_clauses = ["1=1"]; $params = []; $types = "";

if (!empty($_GET['search'])) {
    $where_clauses[] = "(s.nama LIKE ? OR s.kelas LIKE ?)";
    $params[] = "%".$_GET['search']."%"; $params[] = "%".$_GET['search']."%"; $types .= "ss";
}
if (!empty($_GET['reward_id'])) {
    $where_clauses[] = "dr.jenis_reward_id = ?";
    $params[] = $_GET['reward_id']; $types .= "i";
}
$start = $_GET['start_date'] ?? date('Y-m-01');
$end   = $_GET['end_date'] ?? date('Y-m-d');
if ($start && $end) {
    $where_clauses[] = "DATE(dr.tanggal) BETWEEN ? AND ?";
    $params[] = $start; $params[] = $end; $types .= "ss";
}

$where_sql = implode(" AND ", $where_clauses);

// === QUERY ===
$query = "SELECT dr.*, s.nama as nama_santri, s.kelas, jr.nama_reward, jr.poin_reward, u.nama_lengkap as pencatat 
          FROM daftar_reward dr
          JOIN santri s ON dr.santri_id = s.id
          JOIN jenis_reward jr ON dr.jenis_reward_id = jr.id
          LEFT JOIN users u ON dr.dicatat_oleh = u.id
          WHERE $where_sql ORDER BY dr.tanggal DESC";

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
            <a href="../input/create.php" class="btn btn-success fw-bold rounded-pill px-4 shadow-sm py-2 mt-3 mt-md-0" style="background: var(--primary-green); border: none;">
                <i class="fas fa-plus-circle me-2"></i> Input Reward
            </a>
        </div>

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

        <div class="modern-card p-4 mb-4">
            <form method="GET">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="filter-label">DARI TANGGAL</label>
                        <input type="date" name="start_date" class="form-control" value="<?= $start ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="filter-label">SAMPAI TANGGAL</label>
                        <input type="date" name="end_date" class="form-control" value="<?= $end ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="filter-label">JENIS REWARD</label>
                        <select name="reward_id" class="form-select">
                            <option value="">Semua Reward</option>
                            <?php while($r = mysqli_fetch_assoc($q_rewards)): ?>
                                <option value="<?= $r['id'] ?>" <?= ($_GET['reward_id']??'') == $r['id'] ? 'selected' : '' ?>><?= $r['nama_reward'] ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="filter-label">PENCARIAN</label>
                        <div class="input-group">
                            <input type="text" name="search" class="form-control" placeholder="Cari nama santri..." value="<?= htmlspecialchars($_GET['search']??'') ?>">
                            <button class="btn btn-dark px-3"><i class="fas fa-search"></i></button>
                        </div>
                    </div>
                </div>
            </form>
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

<?php 
mysqli_stmt_close($stmt);
require_once __DIR__ . '/../../footer.php'; 
?>