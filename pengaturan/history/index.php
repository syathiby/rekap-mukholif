<?php
// FILE: index.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../bootstrap/init.php';
guard('history_manage'); 

// Ambil periode aktif sebagai default start_date
$q_periode = mysqli_query($conn, "SELECT nilai FROM pengaturan WHERE nama = 'periode_aktif' LIMIT 1");
$periode_aktif = (mysqli_num_rows($q_periode) > 0) ? mysqli_fetch_assoc($q_periode)['nilai'] : date('Y-m-d');

$start_date = $_GET['start_date'] ?? $periode_aktif;
$end_date   = $_GET['end_date'] ?? date('Y-m-d');
$bagian     = $_GET['bagian'] ?? '';
$search     = trim($_GET['search'] ?? '');

// Link Log History bawa data filter
$history_link = "history_view.php?start_date=" . urlencode($start_date) . "&end_date=" . urlencode($end_date) . "&bagian=" . urlencode($bagian) . "&search=" . urlencode($search);

// 1. Fetch Pelanggaran Individu
$params_ind = [$start_date, $end_date];
$types_ind  = 'ss';
$query_ind = "
    SELECT 
        p.id, 
        s.nama AS nama_santri, 
        s.kelas,
        s.kamar,
        jp.nama_pelanggaran, 
        jp.poin, 
        jp.bagian,
        p.tanggal,
        'individu' AS tipe
    FROM pelanggaran p
    JOIN santri s ON p.santri_id = s.id
    JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id
    WHERE DATE(p.tanggal) >= ? AND DATE(p.tanggal) <= ?
";

if (!empty($bagian) && strtolower($bagian) !== 'kebersihan') {
    $query_ind .= " AND jp.bagian = ?";
    $params_ind[] = $bagian;
    $types_ind .= 's';
}

if (!empty($search)) {
    $search_param = "%" . $search . "%";
    // UPDATE: Cari berdasarkan nama, kelas, kamar, dan jenis pelanggaran
    $query_ind .= " AND (s.nama LIKE ? OR s.kelas LIKE ? OR s.kamar LIKE ? OR jp.nama_pelanggaran LIKE ?)";
    $params_ind[] = $search_param;
    $params_ind[] = $search_param;
    $params_ind[] = $search_param;
    $params_ind[] = $search_param;
    $types_ind .= 'ssss';
}

$data_individu = [];
// Jika filter yang dipilih khusus Kebersihan, jangan ambil data individu
if (strtolower($bagian) !== 'kebersihan') {
    $stmt_ind = $conn->prepare($query_ind);
    if ($stmt_ind) {
        $stmt_ind->bind_param($types_ind, ...$params_ind);
        $stmt_ind->execute();
        $res_ind = $stmt_ind->get_result();
        $data_individu = $res_ind->fetch_all(MYSQLI_ASSOC);
    }
}

// 2. Fetch Pelanggaran Kebersihan
$params_keb = [$start_date, $end_date];
$types_keb  = 'ss';
$query_keb = "
    SELECT 
        pk.id,
        'Kamar' AS nama_santri,
        '-' AS kelas,
        pk.kamar,
        CONCAT('Kebersihan: ', IFNULL(pk.catatan, '')) AS nama_pelanggaran,
        0 AS poin,
        'pengabdian' AS bagian,
        pk.tanggal,
        'kebersihan' AS tipe
    FROM pelanggaran_kebersihan pk
    WHERE DATE(pk.tanggal) >= ? AND DATE(pk.tanggal) <= ?
";

$skip_kebersihan = (!empty($bagian) && strtolower($bagian) !== 'pengabdian' && strtolower($bagian) !== 'kebersihan');

$data_kebersihan = [];
if (!$skip_kebersihan) {
    if (!empty($search)) {
        $search_param = "%" . $search . "%";
        // Untuk kebersihan, search di kamar dan catatan
        $query_keb .= " AND (pk.kamar LIKE ? OR pk.catatan LIKE ?)";
        $params_keb[] = $search_param;
        $params_keb[] = $search_param;
        $types_keb .= 'ss';
    }

    $stmt_keb = $conn->prepare($query_keb);
    if ($stmt_keb) {
        $stmt_keb->bind_param($types_keb, ...$params_keb);
        $stmt_keb->execute();
        $res_keb = $stmt_keb->get_result();
        $data_kebersihan = $res_keb->fetch_all(MYSQLI_ASSOC);
    }
}

// 3. Merge and Sort
$all_data = array_merge($data_individu, $data_kebersihan);
usort($all_data, function($a, $b) {
    return strtotime($b['tanggal']) - strtotime($a['tanggal']);
});

$total_data = count($all_data);

// ==============================================================================
// FUNGSI RENDER ROWS UNTUK AJAX & INITIAL LOAD
// ==============================================================================
function render_table_rows($all_data, $start_date, $end_date, $bagian, $search) {
    ob_start();
    if (count($all_data) > 0): 
        $no = 1; foreach($all_data as $row): ?>
            <tr>
                <td class="text-center text-muted fw-medium"><?= $no++ ?></td>
                <td>
                    <?php if ($row['tipe'] === 'kebersihan'): ?>
                        <div class="fw-bold text-dark"><i class="fas fa-door-closed text-muted me-2"></i>Kamar <?= htmlspecialchars($row['kamar']) ?></div>
                    <?php else: ?>
                        <div class="fw-bold text-dark"><?= htmlspecialchars($row['nama_santri']) ?></div>
                        <div class="small text-muted mt-1">
                            <span class="badge badge-soft-secondary rounded-2 fw-normal px-2 py-1">Kls <?= $row['kelas'] ?></span>
                            <span class="ms-1 fw-medium">Kmr <?= $row['kamar'] ?></span>
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
                        <span class="badge badge-soft-danger rounded-pill px-3 py-2 fw-bold">
                            <?= htmlspecialchars($row['poin']) ?>
                        </span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="text-dark fw-medium"><?= date('d M Y', strtotime($row['tanggal'])) ?></div>
                    <div class="text-muted small">
                        <i class="far fa-clock me-1"></i><?= date('H:i', strtotime($row['tanggal'])) ?>
                    </div>
                </td>
                <td>
                    <?php if ($row['tipe'] === 'kebersihan'): ?>
                        <span class="badge badge-soft-warning rounded-pill fw-medium px-3 py-2">
                            <i class="fas fa-broom me-1"></i> Kebersihan
                        </span>
                    <?php else: ?>
                        <span class="badge badge-soft-primary rounded-pill fw-medium px-3 py-2">
                            <?= htmlspecialchars(ucwords(strtolower($row['bagian']))) ?>
                        </span>
                    <?php endif; ?>
                </td>
                <td class="text-center">
                    <form action="process.php" method="POST" onsubmit="return confirmCancel(event, this)">
                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                        <input type="hidden" name="tipe" value="<?= $row['tipe'] ?>">
                        
                        <!-- Keep filter state for redirect -->
                        <input type="hidden" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
                        <input type="hidden" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
                        <input type="hidden" name="bagian" value="<?= htmlspecialchars($bagian) ?>">
                        <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                        
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
                <div class="text-muted opacity-50 d-flex flex-column align-items-center">
                    <i class="fas fa-clipboard-check fa-4x mb-3 text-primary" style="opacity: 0.2;"></i>
                    <h5 class="fw-bold mb-1">Papan Bersih!</h5>
                    <p class="mb-0 small">Tidak ada data pelanggaran yang ditemukan.</p>
                </div>
            </td>
        </tr>
    <?php endif;
    return ob_get_clean();
}

// Jika ini adalah request AJAX, hanya render HTML table body dan stats
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
if ($is_ajax) {
    header('Content-Type: application/json');
    echo json_encode([
        'html' => render_table_rows($all_data, $start_date, $end_date, $bagian, $search),
        'total' => $total_data,
        'history_link' => $history_link
    ]);
    exit;
}

// BUKAN AJAX (Load halaman utuh)
$bagian_result = mysqli_query($conn, "SELECT DISTINCT bagian FROM jenis_pelanggaran WHERE bagian IS NOT NULL AND bagian != '' ORDER BY bagian ASC");
require_once __DIR__ . '/../../layouts/header.php'; 
?>

<style>
    :root {
        --primary: #4f46e5;
        --primary-light: #e0e7ff;
        --secondary: #64748b;
        --bg-color: #f1f5f9;
        --card-bg: rgba(255, 255, 255, 0.95);
        --danger: #ef4444;
        --danger-light: #fee2e2;
        --success: #10b981;
    }

    body { background-color: var(--bg-color); font-family: 'Inter', sans-serif; }
    
    .glass-card {
        background: var(--card-bg);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.5);
        border-radius: 1.2rem;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.01);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .form-control, .form-select, .input-group-text {
        border-radius: 10px;
        border: 1px solid #cbd5e1;
        padding: 0.75rem 1rem;
        font-size: 0.9rem;
        background-color: #f8fafc;
        transition: all 0.3s ease;
    }

    .form-control:focus, .form-select:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 4px var(--primary-light);
        background-color: white;
    }
    
    .input-group-text {
        background-color: #f8fafc;
        color: var(--secondary);
    }

    .btn-gradient {
        background: linear-gradient(135deg, #6366f1, #4f46e5);
        color: white;
        border: none;
        border-radius: 10px;
        padding: 0.75rem 1.5rem;
        font-weight: 600;
        transition: all 0.3s;
    }

    .btn-gradient:hover {
        background: linear-gradient(135deg, #4f46e5, #4338ca);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
        color: white;
    }

    .table-responsive {
        border-radius: 1.2rem;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    .table { margin-bottom: 0; white-space: nowrap; }

    .table thead th {
        background-color: #f8fafc;
        color: #475569;
        font-weight: 700;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        padding: 1.2rem 1.5rem;
        border-bottom: 2px solid #e2e8f0;
    }

    .table tbody {
        transition: opacity 0.2s ease;
    }

    .table tbody td {
        padding: 1.2rem 1.5rem;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
        color: #334155;
        font-size: 0.95rem;
        transition: background-color 0.2s;
    }

    .table tbody tr {
        transition: all 0.2s ease;
    }

    .table tbody tr:hover { 
        background-color: #f8fafc; 
        transform: scale(1.002);
    }

    .badge-soft-danger { background-color: var(--danger-light); color: var(--danger); border: 1px solid #fca5a5; }
    .badge-soft-primary { background-color: var(--primary-light); color: var(--primary); border: 1px solid #a5b4fc; }
    .badge-soft-secondary { background-color: #f1f5f9; color: var(--secondary); border: 1px solid #cbd5e1; }
    .badge-soft-warning { background-color: #fef3c7; color: #d97706; border: 1px solid #fde68a; }

    .btn-action-icon {
        width: 36px; height: 36px;
        display: inline-flex; align-items: center; justify-content: center;
        border-radius: 10px;
        background-color: white;
        border: 1px solid #e2e8f0;
        color: var(--danger);
        transition: all 0.2s;
    }
    .btn-action-icon:hover { 
        background-color: var(--danger); 
        color: white; 
        border-color: var(--danger);
        transform: rotate(90deg);
    }

    .stat-box {
        background: linear-gradient(135deg, #f8fafc, #f1f5f9);
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 1rem;
        text-align: center;
    }
</style>

<div class="container py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h3 class="fw-bolder text-dark mb-1"><i class="fas fa-history text-primary me-2"></i>Riwayat Pelanggaran</h3>
            <p class="text-muted mb-0">Kelola dan pantau seluruh data pelanggaran, termasuk pelanggaran kebersihan.</p>
        </div>
        <div>
            <a href="<?= $history_link ?>" id="btn-log-penghapusan" class="btn btn-white bg-white border text-danger fw-medium rounded-pill px-4 py-2 shadow-sm" style="transition: all 0.3s;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(239,68,68,0.2)';" onmouseout="this.style.transform='none'; this.style.boxShadow='var(--bs-box-shadow-sm)';">
                <i class="fas fa-trash-restore me-2"></i>Log Penghapusan
            </a>
        </div>
    </div>

    <div class="card glass-card mb-4">
        <div class="card-body p-4">
            <form id="filterForm" action="index.php" method="GET">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label fw-bold text-secondary small mb-1">CARI DATA</label>
                        <div class="input-group">
                            <span class="input-group-text border-end-0 bg-transparent"><i class="fas fa-search"></i></span>
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control border-start-0" placeholder="Nama, Kelas, Kamar, Pelanggaran..." id="searchInput">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold text-secondary small mb-1">MULAI TANGGAL</label>
                        <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold text-secondary small mb-1">SAMPAI TANGGAL</label>
                        <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold text-secondary small mb-1">FILTER BAGIAN</label>
                        <select name="bagian" id="bagian" class="form-select">
                            <option value="">Semua Bagian</option>
                            <!-- Opsi Khusus Kebersihan -->
                            <option value="kebersihan" <?= (strtolower($bagian) == 'kebersihan') ? 'selected' : '' ?>>Kebersihan</option>
                            <?php mysqli_data_seek($bagian_result, 0); ?>
                            <?php while ($b = mysqli_fetch_assoc($bagian_result)): ?>
                                <option value="<?= htmlspecialchars($b['bagian']) ?>" <?= (strtolower($bagian) == strtolower($b['bagian'])) ? 'selected' : '' ?>>
                                    <!-- Rapihkan Fontnya: Huruf Awal Kapital (Camel/Title Case) -->
                                    <?= htmlspecialchars(ucwords(strtolower($b['bagian']))) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <div class="stat-box shadow-sm">
                            <small class="d-block text-muted small fw-bold">TOTAL DATA</small>
                            <span class="fw-bolder text-primary fs-4" id="totalDataCount"><?= $total_data ?></span>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card glass-card overflow-hidden">
        <div class="table-responsive">
            <table class="table align-middle" id="historyTable">
                <thead>
                    <tr>
                        <th class="text-center" width="5%">No</th>
                        <th>Nama / Kamar</th>
                        <th>Detail Pelanggaran</th>
                        <th class="text-center">Poin</th>
                        <th>Waktu</th>
                        <th>Bagian / Tipe</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <?= render_table_rows($all_data, $start_date, $end_date, $bagian, $search) ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const filterForm = document.getElementById('filterForm');
    const tableBody = document.getElementById('tableBody');
    const totalDataCount = document.getElementById('totalDataCount');
    const btnLogPenghapusan = document.getElementById('btn-log-penghapusan');

    // AJAX Load Data
    function loadData() {
        const formData = new FormData(filterForm);
        const params = new URLSearchParams(formData);
        
        // Update URL via History API biar bisa dicopy-paste
        const newUrl = window.location.pathname + '?' + params.toString();
        window.history.replaceState({}, '', newUrl);

        // Kasih efek loading (opacity)
        tableBody.style.opacity = '0.3';
        
        fetch('index.php?' + params.toString(), {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            tableBody.innerHTML = data.html;
            totalDataCount.textContent = data.total;
            btnLogPenghapusan.href = data.history_link;
            tableBody.style.opacity = '1';
        })
        .catch(error => {
            console.error('Error AJAX:', error);
            tableBody.style.opacity = '1';
        });
    }

    // Event Listeners buat Auto-Filter (Dropdown & Date)
    document.getElementById('bagian').addEventListener('change', loadData);
    document.getElementById('start_date').addEventListener('change', loadData);
    document.getElementById('end_date').addEventListener('change', loadData);

    // Debounce buat input Search biar nggak spam AJAX tiap ngetik
    let timeout = null;
    document.getElementById('searchInput').addEventListener('input', () => {
        clearTimeout(timeout);
        timeout = setTimeout(loadData, 400); // Tunggu 400ms setelah selesai ngetik
    });

    // Mencegah form tersubmit secara tradisional (refresh halaman)
    filterForm.addEventListener('submit', (e) => {
        e.preventDefault();
        loadData();
    });
});

// Fungsi SweetAlert untuk Konfirmasi Pembatalan
function confirmCancel(e, form) {
    e.preventDefault();
    Swal.fire({
        title: 'Batalkan Pelanggaran?',
        text: "Data akan dipindah ke log penghapusan, dan poin akan dikembalikan (jika ada).",
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
    }).then((result) => {
        if (result.isConfirmed) form.submit();
    });
}

// Flash Messages
<?php if (isset($_SESSION['pesan_sukses'])): ?>
Swal.fire({ 
    icon: 'success', 
    title: 'Berhasil', 
    text: '<?= addslashes($_SESSION['pesan_sukses']) ?>', 
    timer: 2500, 
    showConfirmButton: false, 
    customClass: { popup: 'rounded-4' },
    backdrop: `rgba(0,0,0,0.4)`
});
<?php unset($_SESSION['pesan_sukses']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['pesan_error'])): ?>
Swal.fire({ 
    icon: 'error', 
    title: 'Gagal', 
    text: '<?= addslashes($_SESSION['pesan_error']) ?>', 
    timer: 3000, 
    showConfirmButton: false, 
    customClass: { popup: 'rounded-4' },
    backdrop: `rgba(0,0,0,0.4)`
});
<?php unset($_SESSION['pesan_error']); ?>
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>