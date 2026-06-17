<?php
// 1. Panggil 'Otak' aplikasi dulu
require_once __DIR__ . '/../bootstrap/init.php';

// 2. Jalankan 'SATPAM' buat ngejaga halaman
guard('santri_view');

// Generate CSRF token untuk proteksi hapus santri
$csrf_token = csrf_generate();

// =================================================================
// REVISI LOGIKA FILTER: DIPINDAH KE ATAS SEBELUM HEADER
// =================================================================
if (isset($_GET['reset'])) {
    unset($_SESSION['filter_santri']);
    // Baris echo <script> DIHAPUS karena itu yang bikin error headers already sent
    header('Location: index.php'); // <-- Sekarang aman dieksekusi
    exit;
}

// Cek jika ada filter BARU dari $_GET
if (isset($_GET['nama']) || isset($_GET['kelas']) || isset($_GET['kamar'])) {
    // Simpan filter baru ke session
    $_SESSION['filter_santri'] = [
        'nama' => $_GET['nama'] ?? '',
        'kelas' => $_GET['kelas'] ?? '',
        'kamar' => $_GET['kamar'] ?? '',
    ];
    // Ambil filter dari yg baru diset
    $nama_search = $_SESSION['filter_santri']['nama'];
    $kelas_search = $_SESSION['filter_santri']['kelas'];
    $kamar_search = $_SESSION['filter_santri']['kamar'];
} 
// Jika tidak ada filter baru, coba ambil dari session LAMA
elseif (isset($_SESSION['filter_santri'])) {
    $nama_search = $_SESSION['filter_santri']['nama'] ?? '';
    $kelas_search = $_SESSION['filter_santri']['kelas'] ?? '';
    $kamar_search = $_SESSION['filter_santri']['kamar'] ?? '';
} 
// Jika tidak ada sama sekali, kosongkan
else {
    $nama_search = '';
    $kelas_search = '';
    $kamar_search = '';
}
// =================================================================
// AKHIR BLOK LOGIKA FILTER
// =================================================================


// 3. Kalau lolos, baru panggil Tampilan
require_once __DIR__ . '/../layouts/header.php'; // <-- HTML MULAI DI SINI


// =================================================================
// BLOK KODE UNTUK MENAMPILKAN SEMUA JENIS NOTIFIKASI
// =================================================================

// 1. Cek notifikasi hasil dari BULK UPLOAD
if (isset($_SESSION['bulk_upload_result'])) {
    $result = $_SESSION['bulk_upload_result'];
    $success_count = $result['success'];
    $error_count = $result['error'];
    $errors = $result['errors'];
    
    $alert_class = ($error_count > 0) ? 'alert-warning' : 'alert-success';

    echo "
    <div class='alert {$alert_class} alert-dismissible fade show' role='alert'>
        <h5 class='alert-heading'>Proses Selesai!</h5>
        <p>
            Berhasil menambahkan <strong>{$success_count} santri</strong>.
            Gagal: <strong>{$error_count} santri</strong>.
        </p>";

    if ($error_count > 0) {
        echo "<hr>";
        echo "<details>";
        echo "  <summary style='cursor: pointer;'><strong>Lihat Detail Error</strong></summary>";
        echo "  <div class='mt-2' style='max-height: 150px; overflow-y: auto;'>";
        foreach ($errors as $error_message) {
            echo "<p class='mb-1 small text-danger'><i class='fas fa-times-circle me-1'></i> " . htmlspecialchars($error_message) . "</p>";
        }
        echo "  </div>";
        echo "</details>";
    }
    
    echo "
        <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
    </div>
    ";
    
    unset($_SESSION['bulk_upload_result']);
}

// 2. Jika tidak ada notif bulk, cek notif biasa (dari create, edit, delete)
else if (isset($_SESSION['success_message'])) {
    echo "
    <div class='alert alert-success alert-dismissible fade show' role='alert'>
        <i class='fas fa-check-circle me-2'></i> " . htmlspecialchars($_SESSION['success_message']) . "
        <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
    </div>
    ";
    unset($_SESSION['success_message']);
}
else if (isset($_SESSION['error_message'])) {
    echo "
    <div class='alert alert-danger alert-dismissible fade show' role='alert'>
        <i class='fas fa-exclamation-triangle me-2'></i> " . htmlspecialchars($_SESSION['error_message']) . "
        <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
    </div>
    ";
    unset($_SESSION['error_message']);
}


// Persiapan untuk query yang dinamis dan aman
$where_clauses = [];
$params = [];
$types = '';

if (!empty($nama_search)) {
    $where_clauses[] = "nama LIKE ?";
    $params[] = "%" . $nama_search . "%";
    $types .= 's';
}
if (!empty($kelas_search)) {
    $where_clauses[] = "kelas LIKE ?";
    $params[] = "%" . $kelas_search . "%";
    $types .= 's';
}
if (!empty($kamar_search)) {
    $where_clauses[] = "kamar LIKE ?";
    $params[] = "%" . $kamar_search . "%";
    $types .= 's';
}

$where_sql = '';
if (!empty($where_clauses)) {
    $where_sql = " WHERE " . implode(" AND ", $where_clauses);
}

// === PENGECEKAN IZIN DI AWAL BIAR RAPI ===
$can_create = has_permission('santri_create');
$can_edit = has_permission('santri_edit');
$can_delete = has_permission('santri_delete');

// Hitung colspan dinamis untuk tabel
$colspan = 5; // Kolom dasar: No, Nama, Kelas, Kamar, Poin Histori
if ($can_delete) $colspan++; // Tambah 1 jika bisa hapus (untuk checkbox)
if ($can_edit || $can_delete) $colspan++; // Tambah 1 jika bisa edit atau hapus (untuk kolom Aksi)

// =================================================================
// PAGINATION — Batas data per halaman (skalabilitas)
// =================================================================
$per_page = 30;  // Tampilkan 30 santri per halaman
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page - 1) * $per_page;

// Ambil total data (dihitung sekali saja)
$total = 0;
$count_query = "SELECT COUNT(*) as total FROM santri" . $where_sql;
$stmt_count = mysqli_prepare($conn, $count_query);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt_count, $types, ...$params);
}
mysqli_stmt_execute($stmt_count);
$count_result = mysqli_stmt_get_result($stmt_count);
$total = mysqli_fetch_assoc($count_result)['total'];
$total_pages = (int)ceil($total / $per_page);
mysqli_stmt_close($stmt_count);
?>

<style>
    :root {
        --primary: #4f46e5;      /* Indigo 600 */
        --primary-hover: #4338ca; /* Indigo 700 */
        --bg-body: #f8fafc;       /* Slate 50 */
        --bg-card: #ffffff;
        --text-main: #0f172a;     /* Slate 900 */
        --text-muted: #64748b;    /* Slate 500 */
        --border: #e2e8f0;        /* Slate 200 */
    }
    
    .page-title-card {
        background: var(--bg-card);
        color: var(--text-main);
        border-radius: 1rem;
        box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
        border: 1px solid var(--border);
    }
    .filter-card {
        background-color: var(--bg-card);
        border-radius: 1rem;
        box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
        border: 1px solid var(--border);
    }
    .table-container {
        background-color: var(--bg-card);
        border-radius: 1rem;
        box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
        border: 1px solid var(--border);
        overflow: hidden;
    }
    .table thead {
        background-color: #f1f5f9; /* Slate 100 */
        color: var(--text-muted);
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.8rem;
        letter-spacing: 0.05em;
    }
    .table th {
        border-bottom-width: 1px;
    }
    .action-cell {
        width: 120px;
    }
    .btn-info-circle {
        width: 32px;
        height: 32px;
        padding: 0;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.9rem;
        background-color: #f1f5f9;
        color: var(--primary);
        border: 1px solid var(--border);
        transition: all 0.2s;
    }
    .btn-info-circle:hover {
        background-color: #e2e8f0;
        color: var(--primary-hover);
        transform: scale(1.05);
    }
    #infoModal .modal-body {
        max-height: 60vh;
        overflow-y: auto;
    }
    
    .card-action-bulk {
        padding: 1rem;
        background-color: var(--bg-card);
        border-radius: 1rem;
        border: 1px solid var(--border);
        box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1);
    }

    @media (max-width: 768px) {
        .page-title-card h2 { font-size: 1.25rem; }
        .filter-buttons {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 0.5rem;
        }
    }
</style>

<div class="container-fluid py-4 px-4">
    <!-- Header Page -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="fw-bolder text-dark mb-1">
                <i class="fas fa-users text-primary me-2"></i>Data Santri
                <button type="button" class="btn btn-sm btn-link text-info p-0 ms-2" data-bs-toggle="modal" data-bs-target="#infoModal" title="Informasi Penting">
                    <i class="fas fa-info-circle fs-5"></i>
                </button>
            </h3>
            <p class="text-muted mb-0">Kelola data santri secara terpusat</p>
        </div>
        <div class="d-flex align-items-center">
            <span class="badge bg-white border text-dark fs-6 px-3 py-2 rounded-pill shadow-sm">
                <i class="fas fa-database me-1 text-primary"></i>
                <?php echo number_format($total) . " Santri"; ?>
            </span>
        </div>
    </div>
    
    <div class="filter-card p-3 p-md-4 mb-4">
        <form class="row g-3" method="GET" action="index.php" id="filterForm">
            <div class="col-12 col-md-4">
                <input class="form-control" type="search" name="nama" placeholder="Cari Nama Santri..." value="<?= htmlspecialchars($nama_search) ?>">
            </div>
            <div class="col-12 col-md-3">
                <input class="form-control" type="search" name="kelas" placeholder="Filter Kelas..." value="<?= htmlspecialchars($kelas_search) ?>">
            </div>
            <div class="col-12 col-md-3">
                <input class="form-control" type="search" name="kamar" placeholder="Filter Kamar..." value="<?= htmlspecialchars($kamar_search) ?>">
            </div>
            <div class="col-12 col-md-2 filter-buttons">
                <button class="btn btn-primary" type="submit"><i class="fas fa-filter me-1"></i> Cari</button>
                <a href="index.php?reset=1" class="btn btn-outline-secondary" id="resetFilterBtn" title="Reset Filter"><i class="fas fa-times"></i></a>
            </div>
        </form>
    </div>

    <form method="POST" action="" id="bulkDeleteForm">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <div class="card shadow-sm mb-3 card-action-bulk">
            <div class="row g-2 align-items-center">
                <!-- Group Kiri: Tambah & Bulk Input -->
                <div class="col-12 col-md-auto d-flex gap-2 flex-grow-1">
                    <?php if ($can_create): ?>
                        <a href="create.php" class="btn btn-success flex-grow-1 flex-md-grow-0 fw-medium">
                            <i class="fas fa-user-plus me-1"></i> Tambah Santri
                        </a>
                        <a href="bulk-create.php" class="btn btn-info text-white flex-grow-1 flex-md-grow-0 fw-medium">
                            <i class="fas fa-file-import me-1"></i> Bulk Input
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Group Kanan: Bulk Edit & Hapus -->
                <div class="col-12 col-md-auto d-flex gap-2 justify-content-md-end align-items-center">
                    <div id="selected-count-info" class="d-none me-2"></div>
                    <?php if ($can_edit): ?>
                        <a href="bulk-edit.php" class="btn btn-warning flex-grow-1 flex-md-grow-0 fw-medium text-dark" id="bulkEditBtn">
                            <i class="fas fa-pen-to-square me-1"></i> Bulk Edit
                        </a>
                    <?php endif; ?>
                    <?php if ($can_delete): ?>
                        <button type="submit" class="btn btn-danger flex-grow-1 flex-md-grow-0 fw-medium" id="bulkDeleteBtn" disabled form="bulkDeleteForm">
                            <i class="fas fa-user-minus me-1"></i> Hapus Terpilih
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div id="table-data-wrapper">
            <div class="table-container">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <?php if ($can_delete): ?>
                                <th class="text-center" width="50px"><input type="checkbox" id="selectAll"></th>
                            <?php endif; ?>
                            <th width="5%" class="text-center">No.</th>
                            <th>Nama Santri</th>
                            <th>Kelas</th>
                            <th>Kamar</th>
                            <th class="text-center">
                                Poin Histori
                                <i class="fas fa-question-circle text-muted ms-1" data-bs-toggle="tooltip" data-bs-placement="top" title="Total akumulasi pelanggaran dikurang reward santri semenjak masuk (Seumur Hidup). Tidak terpengaruh filter tanggal."></i>
                            </th>
                            <?php if ($can_edit || $can_delete): ?>
                                <th class="action-cell">Aksi</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $types_paged = $types . 'ii';
                        $params_paged = array_merge($params, [$per_page, $offset]);
                        $query = "SELECT * FROM santri" . $where_sql . " ORDER BY CAST(kamar AS UNSIGNED) ASC, nama ASC LIMIT ? OFFSET ?";
                        $stmt = mysqli_prepare($conn, $query);
                        if (!empty($params_paged)) {
                            mysqli_stmt_bind_param($stmt, $types_paged, ...$params_paged);
                        }
                        mysqli_stmt_execute($stmt);
                        $result = mysqli_stmt_get_result($stmt);
                        
                        if (mysqli_num_rows($result) > 0) {
                            $no = 1; // Inisialisasi nomor
                            while ($row = mysqli_fetch_assoc($result)) {
                        ?>
                        <tr>
                            <?php if ($can_delete): ?>
                                <td class="text-center align-middle">
                                    <input type="checkbox" class="row-checkbox" value="<?= $row['id'] ?>">
                                </td>
                            <?php endif; ?>
                            <td class="text-center align-middle"><?= $no++; ?></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar me-3">
                                        <div class="rounded-circle text-white d-flex align-items-center justify-content-center shadow-sm" 
                                             style="background-color: #<?= substr(md5($row['id']), 0, 6) ?>; width: 40px; height: 40px; font-weight: 600;">
                                            <?= strtoupper(substr($row['nama'], 0, 1)) ?>
                                        </div>
                                    </div>
                                    <div>
                                        <strong class="text-dark"><?= htmlspecialchars($row['nama']) ?></strong>
                                        <div class="text-muted" style="font-size: 0.8rem;">ID: <?= $row['id'] ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="align-middle"><span class="badge bg-primary bg-opacity-10 text-primary px-2 py-1 rounded-pill"><?= htmlspecialchars($row['kelas']) ?></span></td>
                            <td class="align-middle">
                                <span class="badge bg-light text-dark">
                                    <i class="fas fa-door-open me-1"></i>
                                    <?= htmlspecialchars($row['kamar']) ?>
                                </span>
                            </td>
                            <td class="align-middle text-center">
                                <?php 
                                $poin_histori = (int)$row['poin_aktif']; 
                                if ($poin_histori > 0) {
                                    echo '<span class="badge bg-danger rounded-pill px-2 py-1">' . $poin_histori . ' Poin</span>';
                                } elseif ($poin_histori < 0) {
                                    echo '<span class="badge bg-success rounded-pill px-2 py-1"><i class="fas fa-star me-1 text-warning"></i>' . abs($poin_histori) . ' Poin Reward</span>';
                                } else {
                                    echo '<span class="badge bg-secondary rounded-pill px-2 py-1">0 Poin (Bersih)</span>';
                                }
                                ?>
                            </td>
                            <?php if ($can_edit || $can_delete): ?>
                                <td class="align-middle">
                                    <div class="d-flex">
                                        <?php if ($can_edit): ?>
                                            <a href="edit.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-warning me-2" title="Edit"><i class="fas fa-edit"></i></a>
                                        <?php endif; ?>
                                        <?php if ($can_delete): ?>
                                            <a href="#" onclick="showConfirmDelete('delete.php?id=<?= $row['id'] ?>&csrf_token=<?= $csrf_token ?>')" class="btn btn-sm btn-danger" title="Hapus"><i class="fas fa-trash"></i></a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            <?php endif; ?>
                        </tr>
                        <?php 
                            }
                        } else {
                            echo '<tr><td colspan="' . $colspan . '" class="text-center py-4"><h5>Tidak ada data santri ditemukan.</h5><p class="text-muted">Coba ubah atau reset filter pencarian Anda.</p></td></tr>';
                        }
                        mysqli_stmt_close($stmt);
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
        </div> <!-- Close table-data-wrapper -->
    </form> </div>

<?php
// ─── PAGINATION HTML ───────────────────────────────────────────────
if ($total_pages > 1):
    $from = $offset + 1;
    $to   = min($offset + $per_page, $total);
    // Buat URL dasar untuk pagination (pertahankan filter yang aktif)
    $pagination_base_params = [];
    if (!empty($nama_search))  $pagination_base_params['nama']  = $nama_search;
    if (!empty($kelas_search)) $pagination_base_params['kelas'] = $kelas_search;
    if (!empty($kamar_search)) $pagination_base_params['kamar'] = $kamar_search;
?>
<div class="d-flex flex-column flex-md-row justify-content-between align-items-center mt-3 px-1 gap-2" id="pagination-nav">
    <span class="text-muted small">
        Menampilkan <strong><?= $from ?>–<?= $to ?></strong> dari <strong><?= number_format($total) ?></strong> santri
    </span>
    <nav>
        <ul class="pagination pagination-sm mb-0">
            <?php
            // Tombol Sebelumnya
            if ($page > 1):
                $prev_params = array_merge($pagination_base_params, ['page' => $page - 1]);
            ?>
            <li class="page-item">
                <a class="page-link" href="index.php?<?= http_build_query($prev_params) ?>">
                    <i class="fas fa-chevron-left"></i> Sebelumnya
                </a>
            </li>
            <?php else: ?>
            <li class="page-item disabled"><span class="page-link"><i class="fas fa-chevron-left"></i> Sebelumnya</span></li>
            <?php endif; ?>

            <?php
            // Nomor halaman (tampilkan maksimal 5 halaman di sekitar halaman aktif)
            $start_p = max(1, $page - 2);
            $end_p   = min($total_pages, $page + 2);
            if ($start_p > 1): ?>
                <li class="page-item"><a class="page-link" href="index.php?<?= http_build_query(array_merge($pagination_base_params, ['page' => 1])) ?>">1</a></li>
                <?php if ($start_p > 2): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
            <?php endif;
            for ($p = $start_p; $p <= $end_p; $p++):
                $p_params = array_merge($pagination_base_params, ['page' => $p]);
            ?>
            <li class="page-item <?= ($p === $page) ? 'active' : '' ?>">
                <a class="page-link" href="index.php?<?= http_build_query($p_params) ?>"><?= $p ?></a>
            </li>
            <?php endfor;
            if ($end_p < $total_pages):
                if ($end_p < $total_pages - 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                <li class="page-item"><a class="page-link" href="index.php?<?= http_build_query(array_merge($pagination_base_params, ['page' => $total_pages])) ?>"><?= $total_pages ?></a></li>
            <?php endif; ?>

            <?php
            // Tombol Berikutnya
            if ($page < $total_pages):
                $next_params = array_merge($pagination_base_params, ['page' => $page + 1]);
            ?>
            <li class="page-item">
                <a class="page-link" href="index.php?<?= http_build_query($next_params) ?>">
                    Berikutnya <i class="fas fa-chevron-right"></i>
                </a>
            </li>
            <?php else: ?>
            <li class="page-item disabled"><span class="page-link">Berikutnya <i class="fas fa-chevron-right"></i></span></li>
            <?php endif; ?>
        </ul>
    </nav>
</div>
<?php endif; ?>


<div class="modal fade" id="infoModal" tabindex="-1" aria-labelledby="infoModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="infoModalLabel"><i class="fas fa-exclamation-triangle text-warning me-2"></i>Informasi Penting</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="fw-bold">Sebelum mengubah (update) atau menghapus data santri, harap perhatikan hal-hal berikut:</p>
        <ul>
            <li><strong>Pastikan Data Telah Diarsipkan:</strong> Proses pengarsipan adalah snapshot data pada periode tertentu. Jika Anda menghapus santri sebelum periode saat ini diarsip, maka data pelanggaran santri tersebut tidak akan terekam dalam arsip periode ini.</li>
            <li><strong>Data yang Dihapus Bersifat Permanen:</strong> Data santri yang dihapus tidak dapat dikembalikan lagi. Riwayat pelanggarannya di periode aktif juga akan ikut terhapus.</li>
            <li><strong>Update Data:</strong> Mengubah data seperti nama, kelas, atau kamar akan langsung berlaku dan memengaruhi laporan-laporan yang sedang berjalan.</li>
        </ul>
        <p>Tindakan yang bijak adalah menjalankan proses "BUAT ARSIP BARU" di menu Manajemen Arsip secara berkala (misalnya setiap akhir semester) untuk mengamankan semua data sebelum melakukan perubahan besar.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Saya Mengerti</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Konfirmasi Penghapusan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="confirmMessage">Apakah Anda benar-benar yakin?</p>
                <p class="fw-bold text-danger">
                    PERINGATAN: Semua riwayat pelanggaran santri yang terkait juga akan terhapus secara permanen. Tindakan ini tidak dapat dibatalkan.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <a href="#" id="confirmDeleteButton" class="btn btn-danger">Yakin, Hapus Permanen</a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Inisialisasi Tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    
    // --- Bagian Modal Konfirmasi ---
    const confirmModalElement = document.getElementById('confirmDeleteModal');
    const confirmModal = new bootstrap.Modal(confirmModalElement);
    const confirmMessage = document.getElementById('confirmMessage');
    const confirmBtn = document.getElementById('confirmDeleteButton');

    // Fungsi untuk Hapus Satuan (Single Delete)
    window.showConfirmDelete = function(deleteUrl) {
        confirmMessage.textContent = 'Apakah Anda benar-benar yakin ingin menghapus santri ini?';
        confirmBtn.onclick = function() {
            window.location.href = deleteUrl;
        };
        confirmBtn.removeAttribute('href'); // Hapus href lama jika ada
        confirmModal.show();
    }

    // --- Bagian Checkbox Pinter & Bulk Action ---
    let selectAllCheckbox = document.getElementById('selectAll');
    let rowCheckboxes = document.querySelectorAll('.row-checkbox');
    const bulkDeleteForm = document.getElementById('bulkDeleteForm'); 
    const bulkEditBtn = document.getElementById('bulkEditBtn'); 
    const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
    const selectedCountInfo = document.getElementById('selected-count-info');
    const resetFilterBtn = document.getElementById('resetFilterBtn'); 

    // Kunci unik untuk halaman ini
    const STORAGE_KEY = 'selectedSantriIds'; 

    function getStoredIds() {
        const storedIdsJson = sessionStorage.getItem(STORAGE_KEY);
        return storedIdsJson ? new Set(JSON.parse(storedIdsJson)) : new Set();
    }
    
    function saveStoredIds(idsSet) {
        sessionStorage.setItem(STORAGE_KEY, JSON.stringify([...idsSet]));
    }

    // Saat halaman load, centang checkbox berdasarkan data di sessionStorage
    function loadSelections() {
        const selectedIds = getStoredIds();
        rowCheckboxes.forEach(checkbox => {
            const checkboxId = parseInt(checkbox.value); 
            checkbox.checked = selectedIds.has(checkboxId);
        });
    }

    // Fungsi yang dipanggil setiap kali ada checkbox di-klik
    function handleSelectionChange() {
        const selectedIds = getStoredIds();
        rowCheckboxes.forEach(checkbox => {
            const checkboxId = parseInt(checkbox.value);
            if (checkbox.checked) {
                selectedIds.add(checkboxId);
            } else {
                selectedIds.delete(checkboxId);
            }
        });
        saveStoredIds(selectedIds);
        toggleActionButtons();
    }
    
    // Update status 'Select All' (dicentang atau tidak)
    function updateSelectAllState() {
        if (selectAllCheckbox) {
             const totalVisibleCheckboxes = rowCheckboxes.length;
             const checkedVisibleCount = document.querySelectorAll('.row-checkbox:checked').length;
             selectAllCheckbox.checked = totalVisibleCheckboxes > 0 && totalVisibleCheckboxes === checkedVisibleCount;
        }
    }

    // Atur tombol (disabled/enabled) dan badge (tampil/sembunyi)
    function toggleActionButtons() {
        const selectedIds = getStoredIds();
        const checkedCount = selectedIds.size;
        
        // Tombol Bulk Edit tidak lagi diatur
        if (bulkDeleteBtn) bulkDeleteBtn.disabled = checkedCount === 0;

        if (selectedCountInfo) {
            if (checkedCount > 0) {
                selectedCountInfo.innerHTML = `<span class="badge bg-secondary">${checkedCount} data terpilih</span>`;
                selectedCountInfo.classList.remove('d-none');
            } else {
                selectedCountInfo.classList.add('d-none');
            }
        }
        updateSelectAllState(); 
    }

    // --- Pasang Event Listener ---

    // 1. Listener untuk checkbox 'Select All'
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            rowCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            handleSelectionChange();
        });
    }

    // 2. Listener untuk semua checkbox di tiap baris
    rowCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', handleSelectionChange);
    });

    // 3. Listener untuk 'Bulk Edit' DIHAPUS (karena sudah jadi link biasa)

    // 4. Listener untuk form (saat tombol 'Hapus Terpilih' di-submit)
    if (bulkDeleteForm) {
        bulkDeleteForm.addEventListener('submit', function(e) {
            e.preventDefault(); // Stop submit dulu
            bulkDeleteForm.action = 'bulk-delete.php'; // Arahkan ke bulk-delete

            const selectedIds = getStoredIds();
            if (selectedIds.size > 0) {
                 bulkDeleteForm.innerHTML = ''; // Kosongkan form
                 selectedIds.forEach(id => {
                     const hiddenInput = document.createElement('input');
                     hiddenInput.type = 'hidden';
                     hiddenInput.name = 'ids[]';
                     hiddenInput.value = id;
                     bulkDeleteForm.appendChild(hiddenInput);
                 });

                 // Tampilkan modal konfirmasi
                 confirmMessage.textContent = `Apakah Anda yakin ingin menghapus ${selectedIds.size} santri terpilih?`;
                 confirmBtn.onclick = function() {
                     bulkDeleteForm.submit(); // Lanjutkan submit
                 };
                 confirmBtn.removeAttribute('href');
                 confirmModal.show();
            }
        });
    }
    
    // 5. Listener untuk tombol 'Reset Filter'
    if (resetFilterBtn) {
        resetFilterBtn.addEventListener('click', function(e) {
            // Hapus storage SEBELUM pindah halaman
            sessionStorage.removeItem(STORAGE_KEY);
            // Biarkan link 'href="index.php?reset=1"' bekerja normal
        });
    }
    
    // 6. Listener untuk modal info (biar fokusnya bener)
    const infoModalEl = document.getElementById('infoModal');
    if (infoModalEl) {
        infoModalEl.addEventListener('hide.bs.modal', function () {
            if (document.activeElement && infoModalEl.contains(document.activeElement)) {
                document.activeElement.blur();
            }
        });
    }

    // --- INSTANT FILTERING & AJAX SUBMIT ---
    let debounceTimer;
    $('#filterForm input').on('input', function() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function() {
            $('#filterForm').submit();
        }, 300);
    });

    $('#filterForm').on('submit', function(e) {
        e.preventDefault();
        
        const form = $(this);
        const url = 'index.php?' + form.serialize();
        
        $('#table-data-wrapper').css('opacity', 0.5);
        
        $.ajax({
            url: url,
            method: 'GET',
            success: function(html) {
                const newTable = $(html).find('#table-data-wrapper').html();
                $('#table-data-wrapper').html(newTable);
                window.history.pushState(null, '', url);
                
                // Re-bind listeners because elements were swapped
                rebindCheckboxes();
                loadSelections();
                toggleActionButtons();
            },
            complete: function() {
                $('#table-data-wrapper').css('opacity', 1);
            }
        });
    });

    function rebindCheckboxes() {
        selectAllCheckbox = document.getElementById('selectAll');
        if (selectAllCheckbox) {
            selectAllCheckbox.replaceWith(selectAllCheckbox.cloneNode(true));
            selectAllCheckbox = document.getElementById('selectAll');
            selectAllCheckbox.addEventListener('change', function() {
                const visibleCheckboxes = document.querySelectorAll('.row-checkbox');
                visibleCheckboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
                handleSelectionChange();
            });
        }
        
        rowCheckboxes = document.querySelectorAll('.row-checkbox');
        rowCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', handleSelectionChange);
        });
    }

    // --- Inisialisasi Saat Halaman Load ---
    loadSelections();
    toggleActionButtons();

});
</script>