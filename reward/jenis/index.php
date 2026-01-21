<?php 
require_once __DIR__ . '/../../init.php';
guard('jenis_reward_view'); 
require_once __DIR__ . '/../../header.php';

$can_create = has_permission('jenis_reward_create');
$can_edit = has_permission('jenis_reward_edit');
$can_delete = has_permission('jenis_reward_delete');

// Filter Search
$filter_search = $_GET['search'] ?? ''; 
$query = "SELECT * FROM jenis_reward";
$where_clauses = [];
$params = [];
$types = '';

if (!empty($filter_search)) {
    $where_clauses[] = "nama_reward LIKE ?";
    $params[] = "%" . $filter_search . "%";
    $types .= 's';
}

if (!empty($where_clauses)) {
    $query .= " WHERE " . implode(" AND ", $where_clauses);
}

$query .= " ORDER BY poin_reward ASC";

$stmt = mysqli_prepare($conn, $query);
if (!empty($params)) mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Hitung colspan
$colspan = 4;
if ($can_edit || $can_delete) $colspan++; 
if ($can_edit || $can_delete) $colspan++;
?>

<style>
    /* === SOFT MINIMALIST THEME === */
    :root {
        --primary-dark: #145c38;      /* Hijau Tua Elegant */
        --primary-soft: #e6f4ea;      /* Hijau Paling Lembut */
        --text-main: #344767;         /* Abu Gelap (bukan hitam pekat) */
        --text-muted: #8392ab;        /* Abu Kalem */
        --bg-body: #f8f9fa;           /* Background Abu Sangat Muda */
        --radius-std: 12px;           /* Sudut tumpul standar */
    }

    body { 
        background-color: var(--bg-body) !important; 
        color: var(--text-main);
        font-family: 'Inter', sans-serif; /* Pastikan font enak dibaca */
    }

    .content-wrapper { padding: 24px; }

    /* 1. Header Card - Clean White */
    .header-minimal {
        background: #fff;
        padding: 20px 24px;
        border-radius: var(--radius-std);
        box-shadow: 0 2px 12px rgba(0,0,0,0.03);
        margin-bottom: 24px;
        border-left: 5px solid var(--primary-dark); /* Aksen simpel di kiri */
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
    }

    .header-title h4 { font-weight: 700; color: var(--text-main); margin: 0; font-size: 1.25rem; }
    .header-title p { color: var(--text-muted); margin: 0; font-size: 0.85rem; }

    /* 2. Search & Toolbar - Floating Style */
    .toolbar-card {
        background: #fff;
        border-radius: var(--radius-std);
        padding: 16px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.03);
        margin-bottom: 20px;
        border: 1px solid rgba(0,0,0,0.02);
    }

    /* Input Search Minimalis */
    .input-minimal {
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        padding: 10px 15px;
        font-size: 0.9rem;
        transition: all 0.2s;
    }
    .input-minimal:focus {
        background: #fff;
        border-color: var(--primary-dark);
        box-shadow: 0 0 0 3px rgba(20, 92, 56, 0.1);
    }

    /* 3. Badge Poin - FIX HP (Anti Numpuk) */
    .badge-poin-soft {
        background-color: var(--primary-soft);
        color: var(--primary-dark);
        padding: 6px 14px;
        border-radius: 30px;
        font-weight: 700;
        font-size: 0.85rem;
        /* KUNCI SUPAYA TIDAK NUMPUK DI HP: */
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        white-space: nowrap !important; /* Teks dilarang turun baris */
        min-width: 80px; /* Lebar minimum biar lega */
    }

    /* 4. Table Styling - Bersih */
    .table-responsive { border-radius: var(--radius-std); background: #fff; }
    .table-clean th {
        background: #fff;
        color: var(--text-muted);
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.5px;
        padding: 16px 20px;
        border-bottom: 2px solid #f1f1f1;
        font-weight: 600;
    }
    .table-clean td {
        padding: 16px 20px;
        vertical-align: middle;
        border-bottom: 1px solid #f8f9fa;
        color: var(--text-main);
        font-size: 0.95rem;
    }
    .table-clean tr:last-child td { border-bottom: none; }
    
    /* Checkbox Soft */
    .form-check-input {
        cursor: pointer;
        width: 18px; height: 18px;
        border: 2px solid #d1d7e0;
    }
    .form-check-input:checked {
        background-color: var(--primary-dark);
        border-color: var(--primary-dark);
    }

    /* Tombol Bulk (Edit/Hapus) - Lebih Kalem */
    .bulk-btn-group {
        display: none; /* Default hidden */
        gap: 10px;
        animation: fadeIn 0.3s ease;
    }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }

    /* Responsif HP */
    @media (max-width: 768px) {
        .content-wrapper { padding: 15px; }
        .header-minimal { flex-direction: column; align-items: flex-start; }
        .header-minimal .btn { width: 100%; }
        .toolbar-card { padding: 15px; }
        
        /* Sembunyikan kolom deskripsi di HP biar lega */
        .hide-mobile { display: none; } 
    }
</style>

<div class="content-wrapper">
    
    <div class="header-minimal">
        <div class="header-title">
            <h4>Master Jenis Reward</h4>
            <p>Atur kategori apresiasi santri.</p>
        </div>
        <?php if ($can_create): ?>
            <a href="create.php" class="btn btn-dark btn-sm px-4 py-2 rounded-pill shadow-sm" style="background: var(--primary-dark); border: none;">
                <i class="fas fa-plus me-2"></i>Tambah Baru
            </a>
        <?php endif; ?>
    </div>

    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-light border-0 shadow-sm rounded-3 mb-4 d-flex align-items-center text-<?= $_SESSION['message']['type'] == 'success' ? 'success' : 'danger' ?>">
            <i class="fas fa-<?= $_SESSION['message']['type'] == 'success' ? 'check-circle' : 'exclamation-circle' ?> me-2"></i>
            <?= $_SESSION['message']['text']; ?>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['message']); ?>
    <?php endif; ?>

    <div class="toolbar-card">
        <form method="GET" class="d-flex flex-column flex-md-row gap-3 align-items-md-center justify-content-between">
            <div class="flex-grow-1" style="max-width: 400px;">
                <div class="input-group">
                    <span class="input-group-text bg-transparent border-0 ps-0 text-muted"><i class="fas fa-search"></i></span>
                    <input type="text" name="search" class="form-control input-minimal" placeholder="Cari reward..." value="<?= htmlspecialchars($filter_search); ?>">
                </div>
            </div>

            <div class="bulk-btn-group" id="bulkActions">
                <span class="text-muted small fw-bold align-self-center me-2"><span id="countSelected">0</span> dipilih</span>
                <?php if ($can_edit): ?>
                    <button type="button" id="btnBulkEdit" class="btn btn-sm btn-outline-warning fw-bold rounded-pill px-3">
                        <i class="fas fa-pen me-1"></i> Edit
                    </button>
                <?php endif; ?>
                <?php if ($can_delete): ?>
                    <button type="button" id="btnBulkDelete" class="btn btn-sm btn-outline-danger fw-bold rounded-pill px-3">
                        <i class="fas fa-trash me-1"></i> Hapus
                    </button>
                <?php endif; ?>
            </div>

            <a href="index.php" class="btn btn-link text-muted text-decoration-none small">Reset</a>
        </form>
    </div>

    <div class="card border-0 shadow-sm" style="border-radius: var(--radius-std); overflow: hidden;">
        <form method="POST" action="" id="bulkForm">
            <div class="table-responsive">
                <table class="table table-clean mb-0">
                    <thead>
                        <tr>
                            <?php if ($can_edit || $can_delete): ?>
                                <th width="5%" class="text-center">
                                    <input type="checkbox" id="checkAll" class="form-check-input">
                                </th>
                            <?php endif; ?>
                            <th width="5%" class="text-center">No</th>
                            <th>Nama Reward</th>
                            <th width="20%" class="text-center">Poin</th>
                            <th class="hide-mobile">Deskripsi</th>
                            <?php if ($can_edit || $can_delete): ?>
                                <th width="10%" class="text-center">Aksi</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(mysqli_num_rows($result) == 0): ?>
                            <tr><td colspan="<?= $colspan ?>" class="text-center py-5 text-muted">Data tidak ditemukan.</td></tr>
                        <?php endif; ?>

                        <?php $no=1; while($row = mysqli_fetch_assoc($result)): ?>
                        <tr>
                            <?php if ($can_edit || $can_delete): ?>
                                <td class="text-center">
                                    <input type="checkbox" name="ids[]" value="<?= $row['id'] ?>" class="form-check-input row-check">
                                </td>
                            <?php endif; ?>
                            <td class="text-center text-muted fw-bold small"><?= $no++ ?></td>
                            <td>
                                <span class="fw-bold text-dark"><?= htmlspecialchars($row['nama_reward']) ?></span>
                            </td>
                            <td class="text-center">
                                <div class="badge-poin-soft">
                                    <i class="fas fa-plus-circle" style="font-size: 0.75rem;"></i>
                                    <span><?= $row['poin_reward'] ?> Poin</span>
                                </div>
                            </td>
                            <td class="hide-mobile text-muted small">
                                <?= !empty($row['deskripsi']) ? htmlspecialchars($row['deskripsi']) : '-' ?>
                            </td>
                            <?php if ($can_edit || $can_delete): ?>
                                <td class="text-center">
                                    <div class="dropdown">
                                        <button class="btn btn-light btn-sm rounded-circle" type="button" data-bs-toggle="dropdown">
                                            <i class="fas fa-ellipsis-v text-muted"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end border-0 shadow">
                                            <?php if ($can_edit): ?>
                                                <li><a class="dropdown-item small" href="edit.php?id=<?= $row['id'] ?>"><i class="fas fa-pen text-warning me-2"></i>Edit</a></li>
                                            <?php endif; ?>
                                            <?php if ($can_delete): ?>
                                                <li><a class="dropdown-item small text-danger" href="delete.php?id=<?= $row['id'] ?>" onclick="return confirm('Yakin hapus?')"><i class="fas fa-trash me-2"></i>Hapus</a></li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </td>
                            <?php endif; ?>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </form>
    </div>
</div>

<?php 
mysqli_stmt_close($stmt);
require_once __DIR__ . '/../../footer.php'; 
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const checkAll = document.getElementById('checkAll');
    const rowChecks = document.querySelectorAll('.row-check');
    const bulkActions = document.getElementById('bulkActions');
    const countSelected = document.getElementById('countSelected');
    const bulkForm = document.getElementById('bulkForm');
    const btnEdit = document.getElementById('btnBulkEdit');
    const btnDelete = document.getElementById('btnBulkDelete');

    function updateBulkUI() {
        const checked = document.querySelectorAll('.row-check:checked');
        if(checked.length > 0) {
            bulkActions.style.display = 'flex'; // Munculin tombol edit/hapus
            countSelected.textContent = checked.length;
        } else {
            bulkActions.style.display = 'none'; // Umpetin lagi
        }
    }

    if(checkAll) {
        checkAll.addEventListener('change', function() {
            rowChecks.forEach(c => c.checked = this.checked);
            updateBulkUI();
        });
    }

    rowChecks.forEach(c => c.addEventListener('change', updateBulkUI));

    // Handle Edit
    if(btnEdit) {
        btnEdit.addEventListener('click', function() {
            bulkForm.action = 'bulk-edit.php';
            bulkForm.submit();
        });
    }

    // Handle Delete
    if(btnDelete) {
        btnDelete.addEventListener('click', function() {
            if(confirm('Hapus data terpilih?')) {
                bulkForm.action = 'bulk-delete.php';
                bulkForm.submit();
            }
        });
    }
});
</script>