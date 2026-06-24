<?php
// File: rekap-mukholif/rapot/index.php
// VERSI 9.0: Fix 'Aria-Hidden' paling 'paksa' dengan listener 'click' di tombol [data-bs-dismiss]

// 1. Panggil 'Otak' aplikasi dulu
require_once __DIR__ . '/../bootstrap/init.php';
guard('rapot_view');

// === PENGECEKAN IZIN DI AWAL ===
$can_create = has_permission('rapot_create');
$can_cetak = has_permission('rapot_cetak');
$can_delete = has_permission('rapot_delete');
$can_view = has_permission('rapot_view');

// CEK AKSES KAMAR MUSYRIF
$kamar_filter_musyrif = checkMusyrifKamarAccess();

// 2. Ambil data untuk filter dropdown
try {
    if ($kamar_filter_musyrif !== null) {
        $kamar_list = [['kamar' => (string)$kamar_filter_musyrif]];
    } else {
        $kamar_list_stmt = $conn->query("
            SELECT DISTINCT kamar FROM santri 
            WHERE kamar IS NOT NULL AND kamar != 0
            ORDER BY kamar ASC
        ");
        $kamar_list = $kamar_list_stmt->fetch_all(MYSQLI_ASSOC);
    }
    $bulan_list = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $tahun_sekarang = (int)date('Y');
    $tahun_list = [$tahun_sekarang, $tahun_sekarang - 1, $tahun_sekarang - 2];
} catch (Exception $e) {
    die("Error fetching filter data: " . $e->getMessage());
}

// 3. PROSES FILTER (Session-based)
if (isset($_GET['reset'])) {
    unset($_SESSION['filter_rapot']);
    echo "<script>sessionStorage.removeItem('selectedRapotData');</script>";
    header('Location: index.php');
    exit;
}
if (isset($_GET['kamar']) || isset($_GET['bulan']) || isset($_GET['tahun'])) {
    $filter_kamar = $_GET['kamar'] ?? '';
    $filter_bulan = $_GET['bulan'] ?? '';
    $filter_tahun = $_GET['tahun'] ?? '';
    $_SESSION['filter_rapot'] = [
        'kamar' => $filter_kamar,
        'bulan' => $filter_bulan,
        'tahun' => $filter_tahun,
    ];
} elseif (isset($_SESSION['filter_rapot'])) {
    $filter_kamar = $_SESSION['filter_rapot']['kamar'] ?? '';
    $filter_bulan = $_SESSION['filter_rapot']['bulan'] ?? '';
    $filter_tahun = $_SESSION['filter_rapot']['tahun'] ?? '';
} else {
    $filter_kamar = '';
    $filter_bulan = '';
    $filter_tahun = '';
}

if ($kamar_filter_musyrif !== null) {
    $filter_kamar = (string)$kamar_filter_musyrif;
}

// 4. Ambil data rapot dengan paginasi
try {
    $limit = 100;
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    if ($page < 1) $page = 1;
    $offset = ($page - 1) * $limit;

    // Hitung total data
    $sql_count = "
        SELECT COUNT(r.id) AS total
        FROM rapot_kepengasuhan r
        JOIN santri s ON r.santri_id = s.id
        WHERE 1=1 
    ";
    $params_count = []; $types_count = "";
    
    // Terapkan filter wajib jika Musyrif
    if ($kamar_filter_musyrif !== null) {
        $sql_count .= " AND s.kamar = ?"; 
        $params_count[] = $kamar_filter_musyrif; 
        $types_count .= "i";
    }

    if (!empty($filter_kamar)) { $sql_count .= " AND s.kamar = ?"; $params_count[] = $filter_kamar; $types_count .= "s"; }
    if (!empty($filter_bulan)) { $sql_count .= " AND r.bulan = ?"; $params_count[] = $filter_bulan; $types_count .= "s"; }
    if (!empty($filter_tahun)) { $sql_count .= " AND r.tahun = ?"; $params_count[] = $filter_tahun; $types_count .= "i"; }
    
    $stmt_count = $conn->prepare($sql_count);
    if (!empty($params_count)) { $stmt_count->bind_param($types_count, ...$params_count); }
    $stmt_count->execute();
    $total_data = $stmt_count->get_result()->fetch_assoc()['total'];
    $total_pages = ceil($total_data / $limit);

    // Ambil data untuk halaman aktif
    $sql = "
        SELECT 
            r.id, r.bulan, r.tahun, r.dibuat_pada,
            s.nama AS nama_santri, s.kamar AS kamar_santri,
            u.nama_lengkap AS nama_musyrif
        FROM rapot_kepengasuhan r
        JOIN santri s ON r.santri_id = s.id
        LEFT JOIN users u ON r.musyrif_id = u.id
        WHERE 1=1 
    ";
    $params = [];
    $types  = "";

    // Filter wajib musyrif
    if ($kamar_filter_musyrif !== null) {
        $sql     .= " AND s.kamar = ?";
        $params[] = $kamar_filter_musyrif;
        $types   .= "i";
    }
    // Filter opsional dari form
    if (!empty($filter_kamar)) { $sql .= " AND s.kamar = ?"; $params[] = $filter_kamar; $types .= "s"; }
    if (!empty($filter_bulan)) { $sql .= " AND r.bulan = ?"; $params[] = $filter_bulan; $types .= "s"; }
    if (!empty($filter_tahun)) { $sql .= " AND r.tahun = ?"; $params[] = $filter_tahun; $types .= "i"; }

    $sql    .= " ORDER BY r.tahun DESC, FIELD(r.bulan, 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember') ASC, s.kamar ASC, s.nama ASC LIMIT ? OFFSET ?";
    $types  .= "ii";
    $params[] = $limit;
    $params[] = $offset;

    $stmt = $conn->prepare($sql);
    if (!empty($params)) { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    $rapot_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

} catch (Exception $e) {
    die("Error fetching rapot list: ". $e->getMessage());
}

// Helper: Generate HTML Paginasi
function render_pagination($current_page, $total_pages, $total_data = 0, $limit = 100) {
    if ($total_data == 0) return '';
    $start_item = (($current_page - 1) * $limit) + 1;
    $end_item = min($current_page * $limit, $total_data);
    
    $html = '<div class="d-flex flex-column flex-md-row justify-content-between align-items-center mt-2 pt-3 border-top w-100">';
    
    // Info text
    $html .= '<div class="text-muted small mb-3 mb-md-0">';
    $html .= "Menampilkan <strong>{$start_item}</strong> sampai <strong>{$end_item}</strong> dari <strong>{$total_data}</strong> data";
    $html .= '</div>';
    
    if ($total_pages > 1) {
        $html .= '<nav aria-label="Navigasi Halaman" class="w-100 mt-2 mt-md-0"><ul class="pagination pagination-sm mb-0 flex-wrap justify-content-center justify-content-md-end" style="gap: 0.35rem;">';
        
        $prev_page = $current_page - 1;
        
        // Tombol Previous
        if ($current_page > 1) {
            $html .= "<li class='page-item'><a class='page-link rounded px-3 text-dark fw-medium shadow-sm border w-auto' href='javascript:void(0)' data-page='{$prev_page}'><i class='fas fa-chevron-left fa-sm me-1'></i> Previous</a></li>";
        } else {
            $html .= "<li class='page-item disabled'><span class='page-link rounded px-3 text-muted fw-medium border bg-light w-auto'><i class='fas fa-chevron-left fa-sm me-1'></i> Previous</span></li>";
        }
        
        $start_page = max(1, $current_page - 1);
        $end_page = min($total_pages, $current_page + 1);
        
        if ($start_page > 1) {
            $html .= "<li class='page-item'><a class='page-link rounded text-dark border shadow-sm' href='javascript:void(0)' data-page='1'>1</a></li>";
            if ($start_page > 2) {
                $html .= "<li class='page-item disabled'><span class='page-link rounded text-muted border-0 bg-transparent'>...</span></li>";
            }
        }
        
        for ($i = $start_page; $i <= $end_page; $i++) {
            if ($i == $current_page) {
                $html .= "<li class='page-item active'><span class='page-link rounded px-3 bg-primary text-white border-primary shadow-sm'>{$i}</span></li>";
            } else {
                $html .= "<li class='page-item'><a class='page-link rounded text-dark border shadow-sm' href='javascript:void(0)' data-page='{$i}'>{$i}</a></li>";
            }
        }
        
        if ($end_page < $total_pages) {
            if ($end_page < $total_pages - 1) {
                $html .= "<li class='page-item disabled'><span class='page-link rounded text-muted border-0 bg-transparent'>...</span></li>";
            }
            $html .= "<li class='page-item'><a class='page-link rounded text-dark border shadow-sm' href='javascript:void(0)' data-page='{$total_pages}'>{$total_pages}</a></li>";
        }
        
        $next_page = $current_page + 1;
        
        // Tombol Next
        if ($current_page < $total_pages) {
            $html .= "<li class='page-item'><a class='page-link rounded px-3 text-dark fw-medium shadow-sm border w-auto' href='javascript:void(0)' data-page='{$next_page}'>Next <i class='fas fa-chevron-right fa-sm ms-1'></i></a></li>";
        } else {
            $html .= "<li class='page-item disabled'><span class='page-link rounded px-3 text-muted fw-medium border bg-light w-auto'>Next <i class='fas fa-chevron-right fa-sm ms-1'></i></span></li>";
        }
        
        $html .= '</ul></nav>';
    }
    
    $html .= '</div>';
    return $html;
}
$pagination_html = render_pagination($page, $total_pages, $total_data, $limit);

// 4.5. HANDLE AJAX REQUEST
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    ob_start();
    ?>
    <?php if ($total_data == 0): ?>
        <tr><td colspan="7" class="text-center py-4">
            <?php echo (!empty($filter_kamar) || !empty($filter_bulan) || !empty($filter_tahun)) ? 'Data rapot tidak ditemukan dengan filter yang dipilih.' : 'Belum ada data rapot yang dibuat.'; ?>
        </td></tr>
    <?php else: ?>
        <?php foreach ($rapot_list as $index => $rapot): ?>
            <tr>
                <td class="text-center align-middle">
                    <?php
                    $nama_santri_clean = preg_replace("/[^a-zA-Z0-9 ]/", "", $rapot['nama_santri'] ?? 'Santri');
                    $filename_base = "Rapot {$nama_santri_clean} - {$rapot['bulan']} {$rapot['tahun']}";
                    ?>
                    <input type="checkbox" class="row-checkbox form-check-input border-secondary" 
                           value="<?php echo $rapot['id']; ?>"
                           data-filename="<?php echo htmlspecialchars($filename_base); ?>">
                </td>
                <td class="align-middle text-center"><?php echo $offset + $index + 1; ?></td>
                <td class="align-middle fw-bold text-dark"><?php echo htmlspecialchars($rapot['nama_santri'] ?? 'Santri Dihapus'); ?></td>
                <td class="align-middle text-center"><?php echo htmlspecialchars($rapot['kamar_santri'] ?? 'N/A'); ?></td>
                <td class="align-middle"><?php echo htmlspecialchars($rapot['bulan']) . ' ' . $rapot['tahun']; ?></td>
                <td class="align-middle d-none d-md-table-cell"><?php echo htmlspecialchars($rapot['nama_musyrif'] ?? 'User Dihapus'); ?></td>
                <td class="text-nowrap text-center align-middle">
                    <div class="dropdown">
                        <button class="btn btn-sm btn-secondary dropdown-toggle" type="button" id="aksiDropdown-<?php echo $rapot['id']; ?>" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="fas fa-cog"></i>
                        </button>
                        <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in" aria-labelledby="aksiDropdown-<?php echo $rapot['id']; ?>">
                            <?php if ($can_view): ?>
                                <a class="dropdown-item" href="#" onclick="openViewModal('crud_bulanan/view.php?id=<?php echo $rapot['id']; ?>'); return false;" data-bs-toggle="tooltip" title="Lihat Rapot di Layar">
                                    <i class="fas fa-eye fa-sm fa-fw me-2 text-gray-400"></i> View Rapot
                                </a>
                            <?php endif; ?>
                            <?php if ($can_cetak): ?>
                                <a class="dropdown-item" href="export/generate_pdf.php?id=<?php echo $rapot['id']; ?>" target="_blank" data-bs-toggle="tooltip" title="Unduh PDF">
                                    <i class="fas fa-file-pdf fa-sm fa-fw me-2 text-gray-400"></i> Unduh PDF
                                </a>
                                <a class="dropdown-item" href="export/generate_png.php?id=<?php echo $rapot['id']; ?>" target="_blank" data-bs-toggle="tooltip" title="Unduh PNG (Maks 2MB)">
                                    <i class="fas fa-file-image fa-sm fa-fw me-2 text-gray-400"></i> Unduh PNG
                                </a>
                            <?php endif; ?>
                            <?php if ($can_create): ?>
                                <a class="dropdown-item" href="crud_bulanan/create.php?duplicate_id=<?php echo $rapot['id']; ?>&kamar=<?php echo urlencode($filter_kamar); ?>" data-bs-toggle="tooltip" title="Duplikat rapot ini ke bulan baru">
                                    <i class="fas fa-copy fa-sm fa-fw me-2 text-gray-400"></i> Duplikat
                                </a>
                                <a class="dropdown-item" href="crud_bulanan/create.php?edit_id=<?php echo $rapot['id']; ?>&kamar=<?php echo urlencode($filter_kamar); ?>" data-bs-toggle="tooltip" title="Edit rapot ini">
                                    <i class="fas fa-edit fa-sm fa-fw me-2 text-gray-400"></i> Edit
                                </a>
                            <?php endif; ?>
                            <?php if ($can_delete): ?>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item single-delete-btn" href="javascript:void(0);"
                                   data-id="<?php echo $rapot['id']; ?>"
                                   style="color: #e74a3b;" data-bs-toggle="tooltip" title="Hapus Rapot Ini">
                                    <i class="fas fa-trash fa-sm fa-fw me-2 text-gray-400"></i> Hapus
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
    <?php
    $html = ob_get_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'html' => $html,
        'pagination' => $pagination_html,
        'total' => $total_data
    ]);
    exit;
}

// 5. Panggil Header
$page_title = "Daftar Rapot Kepengasuhan";
require_once __DIR__ . '/../layouts/header.php';
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
                <i class="fas fa-file-alt text-primary me-2"></i>Daftar Rapot Kepengasuhan
                <button type="button" class="btn btn-sm btn-link text-info p-0 ms-2" data-bs-toggle="modal" data-bs-target="#guideModal" title="Buku Panduan">
                    <i class="fas fa-info-circle fs-5"></i>
                </button>
            </h3>
            <p class="text-muted mb-0">Kelola dan cetak rapot kepengasuhan santri</p>
        </div>
        <div class="d-flex align-items-center">
            <span class="badge bg-white border text-dark fs-6 px-3 py-2 rounded-pill shadow-sm">
                <i class="fas fa-database me-1 text-primary"></i>
                Total Data: <strong class="ms-1 total-data-display"><?php echo number_format($total_data); ?></strong>
            </span>
        </div>
    </div>

    <!-- PERINGATAN PERMANEN: Rapot Bulanan sebagai dasar Rapor Tahunan -->
    <div class="d-flex align-items-start gap-3 p-3 mb-4 rounded-3 border border-warning-subtle"
         style="background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%); border-left: 4px solid #f59e0b !important;">
        <div class="flex-shrink-0 mt-1">
            <i class="fas fa-exclamation-triangle fa-lg text-warning"></i>
        </div>
        <div>
            <p class="fw-bold mb-1 text-dark" style="font-size: 0.9rem;">
                Perhatian: Data Rapor Bulanan adalah Dasar Rapor Tahunan
            </p>
            <p class="mb-0 text-secondary" style="font-size: 0.82rem; line-height: 1.6;">
                Seluruh data rapor bulanan yang tersimpan di halaman ini akan menjadi bahan perhitungan otomatis saat membuat <strong>Rapor Tahunan</strong>.
                Menghapus atau mengubah rapor bulanan yang sudah digunakan sebagai dasar rapor tahunan dapat mempengaruhi keakuratan nilai dan narasi rapor tahunan.
                <strong>Pastikan seluruh data sudah benar dan lengkap sebelum melakukan penghapusan.</strong>
            </p>
        </div>
    </div>
    
    <?php if ($kamar_filter_musyrif !== null): ?>
    <!-- Info banner Musyrif -->
    <div class="d-flex align-items-start gap-3 p-3 p-md-4 mb-4 rounded-4"
         style="background: linear-gradient(to right, #fefce8, #ffffff); border: 1px solid #fef08a; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02);">
        <div class="flex-shrink-0 mt-1">
            <div style="width: 40px; height: 40px; border-radius: 10px; background: #fef08a; color: #ca8a04; display: flex; align-items: center; justify-content: center;">
                <i class="fas fa-lock fa-lg"></i>
            </div>
        </div>
        <div>
            <h6 class="fw-bold mb-1 text-dark" style="font-size: .95rem; letter-spacing: -0.01em;">Hak Akses Musyrif Terkunci</h6>
            <p class="mb-0 text-secondary info-banner-text" style="font-size: 0.85rem; line-height: 1.5;">
                Anda login sebagai <strong>Musyrif Kamar <?= htmlspecialchars($kamar_filter_musyrif) ?></strong>. Akses Anda telah dibatasi. Anda hanya dapat melihat, mengelola, dan mencetak rapor untuk santri di kamar Anda. Data rapor dari kamar lain secara otomatis disembunyikan dan dikunci untuk menjaga privasi.
            </p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Notifikasi Toast kini ditangani secara global di footer.php -->
    
    <div class="filter-card p-3 p-md-4 mb-4">
        <form action="index.php" method="GET" class="row g-3 align-items-end">
            <div class="col-12 col-md-3">
                <label class="form-label text-muted small fw-bold mb-1">
                    Filter Kamar
                    <?php if ($kamar_filter_musyrif !== null): ?>
                        <span class="badge bg-warning text-dark ms-1" style="font-size: 0.65rem;"><i class="fas fa-lock"></i> Terkunci</span>
                    <?php endif; ?>
                </label>
                <select name="kamar" id="kamar" class="form-select" <?= $kamar_filter_musyrif !== null ? 'style="pointer-events: none; background-color: #e9ecef;" tabindex="-1"' : '' ?>>
                    <?php if ($kamar_filter_musyrif === null): ?>
                        <option value="">Semua Kamar</option>
                    <?php endif; ?>
                    <?php foreach ($kamar_list as $kamar): ?>
                        <option value="<?php echo htmlspecialchars($kamar['kamar']); ?>" <?php echo ($filter_kamar == $kamar['kamar']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($kamar['kamar']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label text-muted small fw-bold mb-1">Filter Bulan</label>
                <select name="bulan" id="bulan" class="form-select">
                    <option value="">Semua Bulan</option>
                    <?php foreach ($bulan_list as $bulan): ?>
                        <option value="<?php echo $bulan; ?>" <?php echo ($filter_bulan == $bulan) ? 'selected' : ''; ?>>
                            <?php echo $bulan; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label text-muted small fw-bold mb-1">Filter Tahun</label>
                <select name="tahun" id="tahun" class="form-select">
                    <option value="">Semua Tahun</option>
                    <?php foreach ($tahun_list as $tahun): ?>
                        <option value="<?php echo $tahun; ?>" <?php echo ($filter_tahun == $tahun) ? 'selected' : ''; ?>>
                            <?php echo $tahun; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-3">
                <a href="index.php?reset=1" id="resetFilterBtn" class="btn btn-light border w-100 text-secondary fw-medium" title="Reset Filter"><i class="fas fa-sync-alt me-1"></i> Reset</a>
            </div>
        </form>
    </div>

    <form method="POST" action="" id="bulkActionForm">
        <input type="hidden" name="csrf_token" value="<?php echo csrf_generate(); ?>">
        
        <div class="card shadow-sm mb-3 card-action-bulk">
            <div class="row g-3 align-items-center">
                <!-- Group Kiri: Create -->
                <div class="col-12 col-md-auto d-flex gap-2 mb-3 mb-md-0 flex-wrap">
                    <?php if ($can_create): ?>
                        <a href="crud_bulanan/create.php?kamar=<?php echo urlencode($filter_kamar); ?>" id="btn-create-rapot" class="btn btn-success flex-grow-1 flex-md-grow-0 fw-medium">
                            <i class="fas fa-plus-circle me-1"></i> Buat Rapot Baru
                        </a>
                        <a href="crud_tahunan/index.php" class="btn btn-primary flex-grow-1 flex-md-grow-0 fw-medium">
                            <i class="fas fa-star me-1"></i> Rapor Tahunan
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Group Kanan: Bulk Action -->
                <div class="col-12 col-md d-flex gap-2 justify-content-md-end align-items-center flex-wrap">
                    <div id="selected-count-info" class="d-none">
                        <span class="badge bg-secondary px-3 py-2 rounded-pill shadow-sm" style="font-size:0.85rem;">0 data terpilih</span>
                    </div>
                    <?php if ($can_cetak): ?>
                        <button type="button" id="bulk-download-pdf-btn" class="btn btn-primary flex-grow-1 flex-md-grow-0 fw-medium shadow-sm" disabled>
                            <i class="fas fa-file-pdf me-1"></i> PDF (ZIP)
                        </button>
                        <button type="button" id="bulk-download-png-btn" class="btn btn-info text-white flex-grow-1 flex-md-grow-0 fw-medium shadow-sm" disabled>
                            <i class="fas fa-file-image me-1"></i> PNG (ZIP)
                        </button>
                    <?php endif; ?>
                    <?php if ($can_delete): ?>
                        <button type="button" id="bulk-delete-btn" class="btn btn-danger flex-grow-1 flex-md-grow-0 fw-medium shadow-sm" disabled>
                            <i class="fas fa-trash-alt me-1"></i> Hapus
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="table-container mb-4 p-0">
            <div class="table-responsive" style="min-height: 300px;">
                <table class="table table-hover align-middle mb-0" id="dataTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th width="1%" class="text-center align-middle py-3">
                                <input type="checkbox" id="selectAll" class="form-check-input border-secondary">
                            </th>
                            <th width="5%" class="py-3">No.</th>
                            <th class="py-3">Nama Santri</th>
                                <th width="10%">Kamar</th>
                                <th width="15%">Periode</th>
                                <th width="20%" class="d-none d-md-table-cell">Dicatat Oleh</th>
                                <th width="10%">Aksi</th> 
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($total_data == 0): ?>
                                <tr><td colspan="7" class="text-center py-4">
                                    <?php echo (!empty($filter_kamar) || !empty($filter_bulan) || !empty($filter_tahun)) ? 'Data rapot tidak ditemukan dengan filter yang dipilih.' : 'Belum ada data rapot yang dibuat.'; ?>
                                </td></tr>
                            <?php else: ?>
                                <?php foreach ($rapot_list as $index => $rapot): ?>
                                    <tr>
                                        <td class="text-center align-middle">
                                            <?php
                                            $nama_santri_clean = preg_replace("/[^a-zA-Z0-9 ]/", "", $rapot['nama_santri'] ?? 'Santri');
                                            $filename_base = "Rapot {$nama_santri_clean} - {$rapot['bulan']} {$rapot['tahun']}";
                                            ?>
                                            <input type="checkbox" class="row-checkbox form-check-input border-secondary" 
                                                   value="<?php echo $rapot['id']; ?>"
                                                   data-filename="<?php echo htmlspecialchars($filename_base); ?>">
                                        </td>
                                        <td class="align-middle text-center"><?php echo $offset + $index + 1; ?></td>
                                        <td class="align-middle fw-bold text-dark"><?php echo htmlspecialchars($rapot['nama_santri'] ?? 'Santri Dihapus'); ?></td>
                                        <td class="align-middle text-center"><?php echo htmlspecialchars($rapot['kamar_santri'] ?? 'N/A'); ?></td>
                                        <td class="align-middle"><?php echo htmlspecialchars($rapot['bulan']) . ' ' . $rapot['tahun']; ?></td>
                                        <td class="align-middle d-none d-md-table-cell"><?php echo htmlspecialchars($rapot['nama_musyrif'] ?? 'User Dihapus'); ?></td>
                                        <td class="text-nowrap text-center align-middle">
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-secondary dropdown-toggle" type="button" id="aksiDropdown-<?php echo $rapot['id']; ?>" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                    <i class="fas fa-cog"></i>
                                                </button>
                                                <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in" aria-labelledby="aksiDropdown-<?php echo $rapot['id']; ?>">
                                                    <?php if ($can_view): ?>
                                                        <a class="dropdown-item" href="#" onclick="openViewModal('crud_bulanan/view.php?id=<?php echo $rapot['id']; ?>'); return false;" data-bs-toggle="tooltip" title="Lihat Rapot di Layar">
                                                            <i class="fas fa-eye fa-sm fa-fw me-2 text-gray-400"></i> View Rapot
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if ($can_cetak): ?>
                                                        <a class="dropdown-item" href="export/generate_pdf.php?id=<?php echo $rapot['id']; ?>" target="_blank" data-bs-toggle="tooltip" title="Unduh PDF">
                                                            <i class="fas fa-file-pdf fa-sm fa-fw me-2 text-gray-400"></i> Unduh PDF
                                                        </a>
                                                        <a class="dropdown-item" href="export/generate_png.php?id=<?php echo $rapot['id']; ?>" target="_blank" data-bs-toggle="tooltip" title="Unduh PNG (Maks 2MB)">
                                                            <i class="fas fa-file-image fa-sm fa-fw me-2 text-gray-400"></i> Unduh PNG
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if ($can_create): ?>
                                                        <a class="dropdown-item" href="crud_bulanan/create.php?duplicate_id=<?php echo $rapot['id']; ?>&kamar=<?php echo urlencode($filter_kamar); ?>" data-bs-toggle="tooltip" title="Duplikat rapot ini ke bulan baru">
                                                            <i class="fas fa-copy fa-sm fa-fw me-2 text-gray-400"></i> Duplikat
                                                        </a>
                                                        <a class="dropdown-item" href="crud_bulanan/create.php?edit_id=<?php echo $rapot['id']; ?>&kamar=<?php echo urlencode($filter_kamar); ?>" data-bs-toggle="tooltip" title="Edit rapot ini">
                                                            <i class="fas fa-edit fa-sm fa-fw me-2 text-gray-400"></i> Edit
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if ($can_delete): ?>
                                                        <div class="dropdown-divider"></div>
                                                        <a class="dropdown-item single-delete-btn" href="javascript:void(0);"
                                                           data-id="<?php echo $rapot['id']; ?>"
                                                           style="color: #e74a3b;" data-bs-toggle="tooltip" title="Hapus Rapot Ini">
                                                            <i class="fas fa-trash fa-sm fa-fw me-2 text-gray-400"></i> Hapus
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Container Paginasi -->
                <div id="pagination-container" class="px-3 pb-3">
                    <?php echo $pagination_html; ?>
                </div>
            </div>
        </div>
    </form> </div>

<?php // AKHIR DARI KONTEN HALAMAN ?>


<?php // ================================================================= ?>
<?php // SEMUA MODAL, CSS, DAN JS KHUSUS HALAMAN INI HARUS DI TARUH ?>
<?php // SEBELUM PANGGILAN FOOTER.PHP ?>
<?php // ================================================================= ?>

<div class="modal fade" id="guideModal" tabindex="-1" aria-labelledby="guideModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 1rem;">
      <div class="modal-header border-bottom-0 pb-0 mt-2 mx-2">
        <h5 class="modal-title fw-bolder text-dark" id="guideModalLabel">
            <i class="fas fa-book-open text-primary me-2"></i>Panduan Halaman
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body pt-3 px-4 pb-4 text-muted">
        <p class="mb-4 small">Halaman ini adalah pusat pengelolaan rapot kepengasuhan.</p>
        
        <h6 class="text-dark fw-bold mb-2"><i class="fas fa-filter text-primary me-2"></i>1. Filter Data</h6>
        <ul class="mb-4 small" style="padding-left: 1.25rem;">
          <li class="mb-1">Gunakan dropdown <strong>Kamar</strong>, <strong>Bulan</strong>, atau <strong>Tahun</strong> untuk menyaring.</li>
          <li class="mb-1">Tabel akan termuat ulang secara <strong>otomatis</strong>.</li>
          <li>Klik tombol <strong>Reset</strong> untuk menghapus filter dan centang tersimpan.</li>
        </ul>

        <div class="bg-light border-start border-4 border-warning rounded p-3 mb-4 small text-dark shadow-sm">
            <i class="fas fa-exclamation-circle text-warning me-1"></i>
            <strong>Sangat Disarankan:</strong> Atur filter <strong>Kamar</strong> terlebih dahulu sebelum menekan "Buat Rapot Baru" agar daftar santri yang termuat sesuai kelas/kamar.
            <div class="mt-2 text-muted">
                <i class="fas fa-info-circle text-info me-1"></i>
                <em>Catatan Khusus: Jika Anda login sebagai <strong>Musyrif</strong>, filter kamar telah dikunci otomatis pada kamar Anda sehingga Anda dapat langsung membuat rapot.</em>
            </div>
        </div>

        <h6 class="text-dark fw-bold mb-2"><i class="fas fa-tasks text-primary me-2"></i>2. Aksi Massal (Bulk Action)</h6>
        <ul class="mb-4 small" style="padding-left: 1.25rem;">
          <li class="mb-1">Centang kotak pada tabel untuk memilih rapot.</li>
          <li class="mb-1"><strong>Centang Disimpan:</strong> Saat filter diubah, centang tidak hilang.</li>
          <li>Tombol <strong>PDF</strong>, <strong>PNG</strong>, dan <strong>Hapus</strong> akan aktif otomatis saat data terpilih.</li>
        </ul>

        <h6 class="text-dark fw-bold mb-2"><i class="fas fa-cog text-primary me-2"></i>3. Aksi Satuan</h6>
        <p class="small mb-2">Klik tombol <i class="fas fa-cog"></i> di setiap baris untuk:</p>
        <ul class="mb-0 small" style="padding-left: 1.25rem;">
          <li class="mb-1">Melihat (View), mengunduh PDF/PNG, atau menghapus satuan.</li>
          <li><strong>Duplikat:</strong> Menyalin isi rapot untuk bulan/periode depan.</li>
        </ul>
      </div>
      <div class="modal-footer border-top-0 pt-0 pb-4 px-4">
        <button type="button" class="btn btn-primary w-100 fw-medium shadow-sm" style="border-radius: 0.75rem;" data-bs-dismiss="modal">Saya Mengerti</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="png-warning-modal" tabindex="-1" role="dialog" aria-labelledby="pngModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="pngModalLabel">Peringatan Proses PNG</h5>
                <button class="close" type="button" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Proses generate PNG (ZIP) akan dimulai di tab baru.</p>
                <p>Proses ini butuh waktu beberapa saat. <strong>Mohon JANGAN TUTUP tab baru tersebut</strong> sampai proses download ZIP selesai.</p>
                <p class="mb-0">Klik "Lanjutkan" untuk memulai.</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Batal</button>
                <button class="btn btn-primary" type="button" id="png-continue-btn">Lanjutkan</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="delete-confirm-modal" tabindex="-1" role="dialog" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteModalLabel"><i class="fas fa-exclamation-triangle me-2"></i>Konfirmasi Hapus</h5>
                <button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Yakin mau hapus <strong id="delete-count-placeholder">0</strong> rapot?</p>
                <p class="text-danger mb-0">Data yang sudah dihapus tidak bisa kembali.</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Batal</button>
                <button class="btn btn-danger" type="button" id="delete-confirm-btn">Ya, Hapus</button>
            </div>
        </div>
    </div>
</div>


<style>
    #notif-placeholder {
        position: fixed;
        top: 5rem;
        right: 1.5rem;
        z-index: 1100;
        min-width: 300px;
    }
    .table > :not(caption) > * > * {
        padding: 1rem 1.5rem;
    }
</style>
<div id="notif-placeholder"></div>


<script>
/**
 * Fungsi Notif Toast (Gak berubah)
 */
function tampilkanNotif(pesan, tipe = 'success') {
    const placeholder = document.getElementById('notif-placeholder');
    if (!placeholder) return;
    const wrapper = document.createElement('div');
    const notifClass = `alert-${tipe}`;
    wrapper.innerHTML = `
        <div class="alert ${notifClass} alert-dismissible fade show shadow-sm" role="alert" style="min-width: 300px;">
            ${pesan}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;
    placeholder.append(wrapper);
    setTimeout(() => {
        const bsAlert = bootstrap.Alert.getOrCreateInstance(wrapper.querySelector('.alert'));
        if (bsAlert) bsAlert.close();
        else wrapper.remove();
    }, 3000);
}

document.addEventListener('DOMContentLoaded', function() {

    // === Variabel Global ===
    let modalTriggerElement = null; // Untuk fix aria-hidden
    const STORAGE_KEY = 'selectedRapotData'; // Kunci sessionStorage

    // === Inisialisasi Tooltip ===
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });

    // === REVISI 9.0: Inisialisasi & Fix Fokus untuk SEMUA Modal ===

    // 1. Definisikan SEMUA elemen modal & tombol terkait
    const guideModalElement = document.getElementById('guideModal');
    const guideModalTriggers = document.querySelectorAll('button[data-bs-target="#guideModal"]');

    const pngWarningModalElement = document.getElementById('png-warning-modal');
    const pngWarningModal = new bootstrap.Modal(pngWarningModalElement);
    const pngContinueBtn = document.getElementById('png-continue-btn'); // (Fix V6.0)

    const deleteConfirmModalElement = document.getElementById('delete-confirm-modal');
    const deleteConfirmModal = new bootstrap.Modal(deleteConfirmModalElement);
    const deleteConfirmBtn = document.getElementById('delete-confirm-btn');
    const deleteCountPlaceholder = document.getElementById('delete-count-placeholder');

    // 2. Fungsi Global untuk balikin fokus
    function returnFocusToTrigger() {
        if (modalTriggerElement && document.body.contains(modalTriggerElement)) {
            modalTriggerElement.focus();
        } else if (document.activeElement && document.activeElement !== document.body) {
            document.activeElement.blur();
        }
        modalTriggerElement = null; // Bersihin
    }
    
    // 3. Fungsi Global untuk nyimpen tombol pemicu
    function setModalTrigger(e) {
        modalTriggerElement = this;
    }
    
    // 4. Pasang listener 'click' ke SEMUA pemicu modal
    guideModalTriggers.forEach(trigger => {
        if(trigger) trigger.addEventListener('click', setModalTrigger);
    });
    
    document.querySelectorAll('#bulk-download-png-btn, #bulk-delete-btn, .single-delete-btn').forEach(btn => {
        if (btn) btn.addEventListener('click', setModalTrigger);
    });
    
    // 5. REVISI 9.0: Pasang listener 'click' ke SEMUA TOMBOL PENUTUP MODAL
    // Ini adalah solusi "paksa" untuk mindahin fokus SEBELUM 'aria-hidden' di-set.
    const allModalCloseButtons = document.querySelectorAll(
        '#guideModal [data-bs-dismiss="modal"],' +
        '#png-warning-modal [data-bs-dismiss="modal"],' +
        '#delete-confirm-modal [data-bs-dismiss="modal"]'
    );
    allModalCloseButtons.forEach(btn => {
        btn.addEventListener('click', returnFocusToTrigger);
    });

    // 6. REVISI 9.0: Kita tetap pake 'hide.bs.modal' sebagai failsafe (jaga-jaga)
    if (guideModalElement) guideModalElement.addEventListener('hide.bs.modal', returnFocusToTrigger);
    if (pngWarningModalElement) pngWarningModalElement.addEventListener('hide.bs.modal', returnFocusToTrigger);
    if (deleteConfirmModalElement) deleteConfirmModalElement.addEventListener('hide.bs.modal', returnFocusToTrigger);

    // === AKHIR REVISI 9.0 ===


    // === AJAX Filter ===
    // Menggunakan closest selector untuk tabel dan filter form
    const tableBody = document.querySelector('#dataTable tbody');
    const totalDataDisplay = document.querySelector('.total-data-display');
    
    // Create loading overlay
    const loadingOverlay = document.createElement('div');
    loadingOverlay.innerHTML = '<div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status"><span class="visually-hidden">Loading...</span></div>';
    loadingOverlay.style.cssText = 'position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255,255,255,0.6); display: flex; justify-content: center; align-items: center; z-index: 10; border-radius: 0.35rem;';
    loadingOverlay.style.display = 'none';
    
    const tableResponsive = document.querySelector('.table-responsive');
    tableResponsive.style.position = 'relative';
    tableResponsive.appendChild(loadingOverlay);

    // === AJAX Data Fetch Function ===
    function fetchRapotData(page = 1) {
        loadingOverlay.style.display = 'flex';
        
        const currentForm = document.querySelector('.filter-card form');
        const formData = new FormData(currentForm);
        const params = new URLSearchParams(formData);
        params.append('ajax', '1');
        params.append('page', page);

        fetch(`index.php?${params.toString()}`)
            .then(response => response.json())
            .then(data => {
                tableBody.innerHTML = data.html;
                totalDataDisplay.textContent = data.total;
                
                const paginationContainer = document.getElementById('pagination-container');
                if (paginationContainer && data.pagination !== undefined) {
                    paginationContainer.innerHTML = data.pagination;
                }
                
                // Update variables
                rowCheckboxes = document.querySelectorAll('.row-checkbox');
                
                // Re-init tooltips
                const tooltips = [].slice.call(tableBody.querySelectorAll('[data-bs-toggle="tooltip"]'));
                tooltips.map(function (tooltipTriggerEl) {
                    return new bootstrap.Tooltip(tooltipTriggerEl);
                });

                // Re-init checkboxes events & restore state
                const selectedData = getStoredData();
                rowCheckboxes.forEach(checkbox => {
                    checkbox.checked = selectedData.has(checkbox.value);
                    checkbox.addEventListener('change', handleSelectionChange);
                });
                
                // Re-init single delete buttons
                tableBody.querySelectorAll('.single-delete-btn').forEach(function(btn) {
                    btn.addEventListener('click', setModalTrigger);
                    btn.addEventListener('click', function(e) {
                        e.preventDefault();
                        const rapotId = this.dataset.id;
                        deleteCountPlaceholder.textContent = '1';
                        deleteConfirmModalElement.dataset.checkedIds = JSON.stringify([rapotId]);
                        deleteConfirmModal.show();
                    });
                });

                updateSelectAllState();
                toggleActionButtons();
                
                loadingOverlay.style.display = 'none';
            })
            .catch(error => {
                console.error('Error fetching data:', error);
                tampilkanNotif('Gagal mengambil data, silakan coba lagi.', 'danger');
                loadingOverlay.style.display = 'none';
            });
    }

    // Filter Change Listener
    document.querySelectorAll('#kamar, #bulan, #tahun').forEach(function(selectElement) {
        selectElement.addEventListener('change', function() {
            // Update URL tombol Buat Rapot Baru agar mengikuti filter kamar secara instan
            if (this.id === 'kamar') {
                const btnCreate = document.getElementById('btn-create-rapot');
                if (btnCreate) {
                    btnCreate.href = 'crud_bulanan/create.php?kamar=' + encodeURIComponent(this.value);
                }
            }
            fetchRapotData(1);
        });
    });

    // Pagination Click Listener
    document.addEventListener('click', function(e) {
        const link = e.target.closest('#pagination-container a.page-link');
        if (link) {
            e.preventDefault();
            const page = link.dataset.page;
            if (page) {
                fetchRapotData(page);
            }
        }
    });

    // === Helper Canggih (Dari Contoh) ===
    function getStoredData() {
        const storedJson = sessionStorage.getItem(STORAGE_KEY);
        return storedJson ? new Map(JSON.parse(storedJson)) : new Map();
    }
    function saveStoredData(dataMap) {
        sessionStorage.setItem(STORAGE_KEY, JSON.stringify([...dataMap]));
    }

    // === Variabel Elemen Tombol & Checkbox ===
    const selectAllCheckbox = document.getElementById('selectAll');
    let rowCheckboxes = document.querySelectorAll('.row-checkbox');
    const bulkPdfBtn = document.getElementById('bulk-download-pdf-btn');
    const bulkPngBtn = document.getElementById('bulk-download-png-btn');
    const bulkDeleteBtn = document.getElementById('bulk-delete-btn');
    const selectedCountInfo = document.getElementById('selected-count-info');
    const resetFilterBtn = document.getElementById('resetFilterBtn');

    // === Fungsi Logika Checkbox (Gak berubah) ===
    function toggleActionButtons() {
        const selectedData = getStoredData();
        const checkedCount = selectedData.size;

        if (bulkPdfBtn) bulkPdfBtn.disabled = checkedCount === 0;
        if (bulkPngBtn) bulkPngBtn.disabled = checkedCount === 0;
        if (bulkDeleteBtn) bulkDeleteBtn.disabled = checkedCount === 0;

        if (selectedCountInfo) {
            if (checkedCount > 0) {
                selectedCountInfo.innerHTML = `<span class="badge bg-secondary px-3 py-2 rounded-pill shadow-sm" style="font-size:0.85rem;">${checkedCount} data terpilih</span>`;
                selectedCountInfo.classList.remove('d-none');
            } else {
                selectedCountInfo.classList.add('d-none');
            }
        }
        updateSelectAllState();
    }
    function updateSelectAllState() {
        if (selectAllCheckbox) {
            const totalVisibleCheckboxes = rowCheckboxes.length;
            const checkedVisibleCount = document.querySelectorAll('.row-checkbox:checked').length;
            selectAllCheckbox.checked = totalVisibleCheckboxes > 0 && totalVisibleCheckboxes === checkedVisibleCount;
        }
    }
    function handleSelectionChange() {
        const selectedData = getStoredData();
        rowCheckboxes.forEach(checkbox => {
            const id = checkbox.value;
            const filename = checkbox.dataset.filename;
            if (checkbox.checked) {
                selectedData.set(id, { filename: filename });
            } else {
                selectedData.delete(id);
            }
        });
        saveStoredData(selectedData);
        toggleActionButtons();
    }
    function loadSelections() {
        const selectedData = getStoredData();
        rowCheckboxes.forEach(checkbox => {
            checkbox.checked = selectedData.has(checkbox.value);
        });
    }

    // === Event Listener Checkbox (Gak berubah) ===
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            rowCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            handleSelectionChange();
        });
    }
    rowCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', handleSelectionChange);
    });
    if (resetFilterBtn) {
        resetFilterBtn.addEventListener('click', function(e) {
            sessionStorage.removeItem(STORAGE_KEY);
        });
    }
    
    // === Logika Tombol Bulk PDF (Gak berubah) ===
    if (bulkPdfBtn) {
        bulkPdfBtn.addEventListener('click', function() {
            const checkedDataMap = getStoredData();
            if (checkedDataMap.size === 0) {
                tampilkanNotif('Pilih minimal satu rapot dulu.', 'warning');
                return;
            }
            const bulkList = Array.from(checkedDataMap.entries()).map(([id, data]) => ({ 
                id: id, 
                filename: data.filename 
            }));
            sessionStorage.setItem('bulkProcessList', JSON.stringify(bulkList));
            window.open('crud_bulanan/bulk_processor.php?type=pdf', '_blank');
        });
    }
    
    // === Logika Tombol Bulk PNG ===
    if (bulkPngBtn) {
        bulkPngBtn.addEventListener('click', function() {
            const checkedDataMap = getStoredData();
            if (checkedDataMap.size === 0) {
                tampilkanNotif('Pilih minimal satu rapot dulu.', 'warning');
                return;
            }
            const bulkList = Array.from(checkedDataMap.entries()).map(([id, data]) => ({ 
                id: id, 
                filename: data.filename 
            }));
            pngWarningModalElement.dataset.bulkListData = JSON.stringify(bulkList);
            // (modalTriggerElement udah di-set di listener global)
            pngWarningModal.show();
        });
    }
    if (pngContinueBtn) {
        pngContinueBtn.addEventListener('click', function() {
            const bulkListString = pngWarningModalElement.dataset.bulkListData;
            if (!bulkListString) {
                tampilkanNotif('Error: Data tidak ditemukan. Coba ulangi.', 'danger');
                return;
            }
            sessionStorage.setItem('bulkProcessList', bulkListString);
            window.open('crud_bulanan/bulk_processor.php?type=png', '_blank');
            pngWarningModal.hide();
        });
    }
    
    // === Logika Modal Delete ===
    if (bulkDeleteBtn) {
        bulkDeleteBtn.addEventListener('click', function() {
            const checkedDataMap = getStoredData();
            const checkedIDs = [...checkedDataMap.keys()];
            if (checkedIDs.length === 0) {
                tampilkanNotif('Pilih minimal satu rapot dulu yang mau dihapus.', 'warning');
                return;
            }
            // (modalTriggerElement udah di-set di listener global)
            deleteCountPlaceholder.textContent = checkedIDs.length;
            deleteConfirmModalElement.dataset.checkedIds = JSON.stringify(checkedIDs);
            deleteConfirmModal.show();
        });
    }
    if (deleteConfirmBtn) {
        deleteConfirmBtn.addEventListener('click', function() {
            const checkedIdsString = deleteConfirmModalElement.dataset.checkedIds;
            if (!checkedIdsString) {
                tampilkanNotif('Error: Data ID tidak ditemukan. Coba ulangi.', 'danger');
                return;
            }
            const checkedIDs = JSON.parse(checkedIdsString);
            const form = document.getElementById('bulkActionForm');
            if (!form) {
                tampilkanNotif('Error: Form tidak ditemukan.', 'danger');
                return;
            }
            form.innerHTML = '';
            form.action = 'crud_bulanan/bulk_delete.php';
            checkedIDs.forEach(function(id) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'ids[]'; 
                input.value = id;
                form.appendChild(input);
            });
            form.submit(); 
            deleteConfirmModal.hide();
        });
    }
    document.querySelectorAll('.single-delete-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const rapotId = this.dataset.id;
            // (modalTriggerElement udah di-set di listener global)
            deleteCountPlaceholder.textContent = '1';
            deleteConfirmModalElement.dataset.checkedIds = JSON.stringify([rapotId]);
            deleteConfirmModal.show();
        });
    });
    
    // === INISIALISASI SAAT HALAMAN LOAD ===
    loadSelections();
    toggleActionButtons();

});
    window.openViewModal = function(url) {
        document.getElementById('viewRapotIframe').src = url;
        var myModal = new bootstrap.Modal(document.getElementById('viewRapotModal'));
        myModal.show();
    };

</script>

<!-- Modal View Rapot -->
<div class="modal fade" id="viewRapotModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="background-color: #525659; overflow: hidden; max-height: 95vh;">
            <div class="modal-header bg-dark text-white border-0 py-2">
                <h6 class="modal-title text-white m-0"><i class="fas fa-eye text-white me-2"></i>Preview Rapot</h6>
                <div class="ms-auto d-flex align-items-center">
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" onclick="document.getElementById('viewRapotIframe').src=''"></button>
                </div>
            </div>
            <div class="modal-body p-0 position-relative" style="height: 85vh; background-color: #525659;">
                <iframe id="viewRapotIframe" src="" style="width:100%; height:100%; border:none; background-color: white;"></iframe>
            </div>
        </div>
    </div>
</div>

<?php
// 7. Panggil Footer (Gak berubah)
require_once __DIR__ . '/../layouts/footer.php';
?>