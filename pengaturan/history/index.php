<?php
// FILE: index.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../init.php';
guard('history_manage'); 
require_once __DIR__ . '/../../header.php'; 

$tanggal = $_GET['tanggal'] ?? date('Y-m-d');
$bagian  = $_GET['bagian'] ?? '';

// Link Log History bawa data filter
$history_link = "history_view.php?tanggal=" . urlencode($tanggal) . "&bagian=" . urlencode($bagian);

$params = [];
$types  = '';

$query = "
    SELECT 
        p.id, 
        s.nama AS nama_santri, 
        s.kelas,
        s.kamar,
        jp.nama_pelanggaran, 
        jp.poin, 
        jp.bagian,
        p.tanggal
    FROM pelanggaran p
    JOIN santri s ON p.santri_id = s.id
    JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id
    WHERE DATE(p.tanggal) = ?
";
$params[] = $tanggal;
$types .= 's';

if (!empty($bagian)) {
    $query .= " AND jp.bagian = ?";
    $params[] = $bagian;
    $types .= 's';
}
$query .= " ORDER BY p.tanggal DESC";

$stmt = $conn->prepare($query);
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    die("Query Gagal: " . $conn->error);
}

$total_data = mysqli_num_rows($result);
$bagian_result = mysqli_query($conn, "SELECT DISTINCT bagian FROM jenis_pelanggaran ORDER BY bagian ASC");
?>

<style>
    /* MODERN MINIMALIST STYLE */
    body { background-color: #f8fafc; }
    
    .card-modern {
        border: none;
        border-radius: 16px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
        background: white;
    }

    .form-control, .form-select {
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        padding: 0.6rem 1rem;
        font-size: 0.95rem;
        transition: all 0.2s;
    }

    .form-control:focus, .form-select:focus {
        border-color: #6366f1;
        box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
    }

    /* TABLE STYLING - CLEAN & RESPONSIVE SCROLL */
    .table-responsive {
        border-radius: 16px;
        overflow-x: auto; /* Kunci scroll horizontal di HP */
        -webkit-overflow-scrolling: touch; /* Biar smooth di iPhone */
    }
    
    .table { margin-bottom: 0; white-space: nowrap; /* Biar kolom gak kelipet di HP */ }

    .table thead th {
        background-color: #f8fafc;
        color: #64748b;
        font-weight: 700;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        padding: 1rem 1.25rem;
        border-bottom: 2px solid #e2e8f0;
    }

    .table tbody td {
        padding: 1rem 1.25rem;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
        color: #334155;
        font-size: 0.95rem;
    }

    .table tbody tr:last-child td { border-bottom: none; }
    .table-hover tbody tr:hover { background-color: #f8fafc; }

    /* Custom Badges */
    .badge-soft-danger { background-color: #fef2f2; color: #ef4444; border: 1px solid #fee2e2; }
    .badge-soft-primary { background-color: #eff6ff; color: #3b82f6; border: 1px solid #dbeafe; }
    .badge-soft-secondary { background-color: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0; }

    /* Tombol Aksi Minimalis */
    .btn-action-icon {
        width: 32px; height: 32px;
        display: inline-flex; align-items: center; justify-content: center;
        border-radius: 8px;
        transition: all 0.2s;
    }
    .btn-action-icon:hover { background-color: #fee2e2; color: #dc2626; }
</style>

<div class="container py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h4 class="fw-bold text-dark mb-1">Riwayat Pelanggaran</h4>
            <p class="text-muted mb-0 small">Monitoring data pelanggaran santri harian.</p>
        </div>
        <div>
            <a href="<?= $history_link ?>" class="btn btn-white bg-white border text-danger fw-medium rounded-pill px-4 shadow-sm hover-shadow">
                <i class="fas fa-trash-restore me-2"></i>Log Penghapusan
            </a>
        </div>
    </div>

    <div class="card card-modern mb-4">
        <div class="card-body p-4">
            <form id="filterForm" method="GET">
                <div class="row g-3 align-items-end">
                    <div class="col-md-5">
                        <label class="form-label fw-bold text-secondary x-small mb-1">TANGGAL</label>
                        <input type="date" id="tanggal" name="tanggal" value="<?= htmlspecialchars($tanggal) ?>" class="form-control">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label fw-bold text-secondary x-small mb-1">FILTER BAGIAN</label>
                        <select name="bagian" id="bagian" class="form-select">
                            <option value="">Semua Bagian</option>
                            <?php mysqli_data_seek($bagian_result, 0); ?>
                            <?php while ($b = mysqli_fetch_assoc($bagian_result)): ?>
                                <option value="<?= htmlspecialchars($b['bagian']) ?>" <?= ($bagian == $b['bagian']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars(ucfirst($b['bagian'])) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-2 text-md-end">
                        <div class="p-2 bg-light rounded-3 text-center border">
                            <small class="d-block text-muted x-small">Total Data</small>
                            <span class="fw-bold text-primary fs-5"><?= $total_data ?></span>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card card-modern overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th class="text-center" width="5%">No</th>
                        <th>Nama Santri</th>
                        <th>Pelanggaran</th>
                        <th class="text-center">Poin</th>
                        <th>Waktu</th>
                        <th>Bagian</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && mysqli_num_rows($result) > 0): ?>
                        <?php $no = 1; while($row = mysqli_fetch_assoc($result)): ?>
                            <tr>
                                <td class="text-center text-muted"><?= $no++ ?></td>
                                <td>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($row['nama_santri']) ?></div>
                                    <div class="small text-muted">
                                        <span class="badge badge-soft-secondary rounded-1 fw-normal px-2 py-0">Kls <?= $row['kelas'] ?></span>
                                        <span class="ms-1">Kmr <?= $row['kamar'] ?></span>
                                    </div>
                                </td>
                                <td>
                                    <?= htmlspecialchars($row['nama_pelanggaran']) ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-soft-danger rounded-pill px-3">
                                        -<?= htmlspecialchars($row['poin']) ?>
                                    </span>
                                </td>
                                <td class="text-muted small">
                                    <i class="far fa-clock me-1"></i>
                                    <?= date('H:i', strtotime($row['tanggal'])) ?>
                                </td>
                                <td>
                                    <span class="badge badge-soft-primary rounded-pill fw-normal px-2">
                                        <?= htmlspecialchars(ucfirst($row['bagian'])) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <form action="process.php" method="POST" onsubmit="return confirmCancel(event, this)">
                                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                        <input type="hidden" name="tanggal" value="<?= htmlspecialchars($tanggal) ?>">
                                        <input type="hidden" name="bagian" value="<?= htmlspecialchars($bagian) ?>">
                                        <button type="submit" name="batalkan" class="btn btn-action-icon text-danger" title="Batalkan Pelanggaran">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <div class="text-muted opacity-50">
                                    <i class="fas fa-clipboard-check fa-3x mb-3"></i>
                                    <p class="mb-0">Tidak ada data pelanggaran.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.getElementById('tanggal').addEventListener('change', () => document.getElementById('filterForm').submit());
document.getElementById('bagian').addEventListener('change', () => document.getElementById('filterForm').submit());

function confirmCancel(e, form) {
    e.preventDefault();
    Swal.fire({
        title: 'Batalkan?',
        text: "Poin akan dikembalikan & data masuk log.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'Ya, Batalkan',
        cancelButtonText: 'Tutup',
        customClass: { popup: 'rounded-4' }
    }).then((result) => {
        if (result.isConfirmed) form.submit();
    });
}

<?php if (isset($_SESSION['pesan_sukses'])): ?>
Swal.fire({ icon: 'success', title: 'Berhasil', text: '<?= addslashes($_SESSION['pesan_sukses']) ?>', timer: 2000, showConfirmButton: false, customClass: { popup: 'rounded-4' } });
<?php unset($_SESSION['pesan_sukses']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['pesan_error'])): ?>
Swal.fire({ icon: 'error', title: 'Gagal', text: '<?= addslashes($_SESSION['pesan_error']) ?>', timer: 3000, showConfirmButton: false, customClass: { popup: 'rounded-4' } });
<?php unset($_SESSION['pesan_error']); ?>
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../../footer.php'; ?>