<?php
// File: rekap-mukholif/rapot/index.php
// VERSI 9.0: Fix 'Aria-Hidden' paling 'paksa' dengan listener 'click' di tombol [data-bs-dismiss]

// 1. Panggil 'Otak' aplikasi dulu
require_once __DIR__ . '/../init.php';
guard('rapot_view');

// === PENGECEKAN IZIN DI AWAL ===
$can_create = has_permission('rapot_create');
$can_cetak = has_permission('rapot_cetak');
$can_delete = has_permission('rapot_delete');
$can_view = has_permission('rapot_view');

// 2. Ambil data untuk filter dropdown
try {
    $kamar_list_stmt = $conn->query("
        SELECT DISTINCT kamar FROM santri 
        WHERE kamar IS NOT NULL AND kamar != '' AND kamar != '0'
        ORDER BY CAST(kamar AS UNSIGNED)
    ");
    $kamar_list = $kamar_list_stmt->fetch_all(MYSQLI_ASSOC);
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

// 4. Ambil data rapot
try {
    $sql = "
        SELECT 
            r.id, r.bulan, r.tahun, r.dibuat_pada,
            s.nama AS nama_santri, s.kamar AS kamar_santri,
            u.nama_lengkap AS nama_musyrif
        FROM rapot_kepengasuhan r
        LEFT JOIN santri s ON r.santri_id = s.id
        LEFT JOIN users u ON r.musyrif_id = u.id
        WHERE 1=1 
    "; 
    $params = []; $types = "";
    if (!empty($filter_kamar)) { $sql .= " AND s.kamar = ?"; $params[] = $filter_kamar; $types .= "s"; }
    if (!empty($filter_bulan)) { $sql .= " AND r.bulan = ?"; $params[] = $filter_bulan; $types .= "s"; }
    if (!empty($filter_tahun)) { $sql .= " AND r.tahun = ?"; $params[] = $filter_tahun; $types .= "i"; }
    $sql .= " ORDER BY CAST(s.kamar AS UNSIGNED) ASC, s.nama ASC";
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    $rapot_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $total_data = count($rapot_list);
} catch (Exception $e) {
    die("Error fetching rapot list: ". $e->getMessage());
}

// 5. Panggil Header
$page_title = "Daftar Rapot Kepengasuhan";
require_once __DIR__ . '/../header.php';
?>

<div class="container-fluid">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><?php echo htmlspecialchars($page_title); ?></h1>
        
        <div class="d-flex align-items-center">
            <?php if ($can_create): ?>
                <a href="create.php?kamar=<?php echo urlencode($filter_kamar); ?>" class="btn btn-primary shadow-sm">
                    <i class="fas fa-plus fa-sm text-white-50"></i>
                    <span class="ms-1">CREATE</span>
                </a>
            <?php endif; ?>
        </div>
    </div>
    
    <?php
    // SISTEM NOTIFIKASI TOAST
    if (isset($_SESSION['flash_message'])) {
        $flash = $_SESSION['flash_message'];
        $tipe = 'success'; $pesan = 'Operasi berhasil.'; // Default

        if (is_array($flash)) {
            $tipe = $flash['type'] ?? 'success';
            $pesan = $flash['message'] ?? 'Operasi berhasil.';
        } elseif (is_string($flash)) {
            if (in_array($flash, ['success', 'danger', 'warning', 'info'])) {
                $tipe = $flash;
                if ($tipe == 'success') $pesan = 'Operasi berhasil.';
                if ($tipe == 'danger') $pesan = 'Terjadi kesalahan.';
            } else {
                $pesan = $flash;
            }
        }
        
        if (strtolower(trim($pesan)) === 'success') {
            $pesan = 'Operasi berhasil.';
        }
        
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                if (typeof tampilkanNotif === 'function') {
                    tampilkanNotif('" . addslashes($pesan) . "', '" . addslashes($tipe) . "');
                }
            });
        </script>";
        unset($_SESSION['flash_message']);
    }
    ?>

    <div class="alert alert-info shadow-sm" role="alert">
        <h5 class="alert-heading" style="font-size: 1.1rem;"><i class="fas fa-book-reader me-2"></i>Panduan Cepat!</h5>
        <p class="mb-2">Baru pertama kali ke halaman ini?</p>
        <p>Disarankan <strong>Baca panduan terlebih dahulu</strong> untuk mengerti cara kerja filter, checkbox pintar, dan tombol aksi massal.</p>
        <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#guideModal">
            <i class="fas fa-book-open me-1"></i> Buka Buku Panduan
        </button>
    </div>
    
    <div class="card shadow mb-4">
        <a href="#collapseFilter" class="d-block card-header py-3" data-bs-toggle="collapse" role="button" aria-expanded="true" aria-controls="collapseFilter">
            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-filter"></i> Filter Data Rapot</h6>
        </a>
        <div class="collapse show" id="collapseFilter">
            <div class="card-body">
                <form action="index.php" method="GET" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label for="kamar" class="form-label">Kamar:</label>
                        <select name="kamar" id="kamar" class="form-select">
                            <option value="">Semua Kamar</option>
                            <?php foreach ($kamar_list as $kamar): ?>
                                <option value="<?php echo htmlspecialchars($kamar['kamar']); ?>" <?php echo ($filter_kamar == $kamar['kamar']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($kamar['kamar']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="bulan" class="form-label">Bulan:</label>
                        <select name="bulan" id="bulan" class="form-select">
                            <option value="">Semua Bulan</option>
                            <?php foreach ($bulan_list as $bulan): ?>
                                <option value="<?php echo $bulan; ?>" <?php echo ($filter_bulan == $bulan) ? 'selected' : ''; ?>>
                                    <?php echo $bulan; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="tahun" class="form-label">Tahun:</label>
                        <select name="tahun" id="tahun" class="form-select">
                            <option value="">Semua Tahun</option>
                            <?php foreach ($tahun_list as $tahun): ?>
                                <option value="<?php echo $tahun; ?>" <?php echo ($filter_tahun == $tahun) ? 'selected' : ''; ?>>
                                    <?php echo $tahun; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex">
                        <a href="index.php?reset=1" id="resetFilterBtn" class="btn btn-outline-secondary w-100"><i class="fas fa-sync"></i> Reset</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <form method="POST" action="" id="bulkActionForm">
        
        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <div class="btn-group" role="group">
                            <?php if ($can_cetak): ?>
                                <button type="button" id="bulk-download-pdf-btn" class="btn btn-primary btn-sm shadow-sm" disabled>
                                    <i class="fas fa-file-pdf me-1"></i> PDF (ZIP)
                                </button>
                                <button type="button" id="bulk-download-png-btn" class="btn btn-success btn-sm shadow-sm" disabled>
                                    <i class="fas fa-file-image me-1"></i> PNG (ZIP)
                                </button>
                            <?php endif; ?>
                            <?php if ($can_delete): ?>
                                <button type="button" id="bulk-delete-btn" class="btn btn-danger btn-sm shadow-sm" disabled>
                                    <i class="fas fa-trash me-1"></i> Hapus
                                </button>
                            <?php endif; ?>
                        </div>
                        <div id="selected-count-info" class="ms-2 d-none">
                            <span class="badge bg-secondary">0 data terpilih</span>
                        </div>
                    </div>

                    <div class="text-muted">
                        Total Data: <strong><?= $total_data; ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow mb-4">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="dataTable" width="100%" cellspacing="0">
                        <thead class="table-dark-blue">
                            <tr>
                                <th width="1%" class="text-center align-middle">
                                    <input type="checkbox" id="selectAll">
                                </th>
                                <th width="5%">No.</th>
                                <th>Nama Santri</th>
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
                                            <input type="checkbox" class="row-checkbox" 
                                                   value="<?php echo $rapot['id']; ?>"
                                                   data-filename="<?php echo htmlspecialchars($filename_base); ?>">
                                        </td>
                                        <td class="align-middle text-center"><?php echo $index + 1; ?></td>
                                        <td class="align-middle"><?php echo htmlspecialchars($rapot['nama_santri'] ?? 'Santri Dihapus'); ?></td>
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
                                                        <a class="dropdown-item" href="view.php?id=<?php echo $rapot['id']; ?>" target="_blank" data-bs-toggle="tooltip" title="Lihat Rapot di Halaman Web">
                                                            <i class="fas fa-eye fa-sm fa-fw me-2 text-gray-400"></i> View Rapot
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if ($can_cetak): ?>
                                                        <a class="dropdown-item" href="generate_pdf.php?id=<?php echo $rapot['id']; ?>" target="_blank" data-bs-toggle="tooltip" title="Unduh PDF">
                                                            <i class="fas fa-file-pdf fa-sm fa-fw me-2 text-gray-400"></i> Unduh PDF
                                                        </a>
                                                        <a class="dropdown-item" href="generate_png.php?id=<?php echo $rapot['id']; ?>" target="_blank" data-bs-toggle="tooltip" title="Unduh PNG (Maks 2MB)">
                                                            <i class="fas fa-file-image fa-sm fa-fw me-2 text-gray-400"></i> Unduh PNG
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if ($can_create): ?>
                                                        <a class="dropdown-item" href="create.php?duplicate_id=<?php echo $rapot['id']; ?>&kamar=<?php echo urlencode($filter_kamar); ?>" data-bs-toggle="tooltip" title="Duplikat rapot ini ke bulan baru">
                                                            <i class="fas fa-copy fa-sm fa-fw me-2 text-gray-400"></i> Duplikat
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
    <div class="modal-content">
      <div class="modal-header bg-info text-white">
        <h5 class="modal-title" id="guideModalLabel"><i class="fas fa-book-open me-2"></i>Buku Panduan - Halaman Rapot</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p>Halaman ini adalah pusat untuk mengelola semua rapot kepengasuhan.</p>
        
        <h6><i class="fas fa-filter me-1"></i> 1. Filter Data</h6>
        <ul class="mb-3">
          <li>Gunakan dropdown <strong>Kamar</strong>, <strong>Bulan</strong>, atau <strong>Tahun</strong> untuk menyaring data.</li>
          <li>Filter akan otomatis berjalan saat Anda memilih.</li>
          <li>Klik <strong>Reset</strong> untuk membersihkan filter dan semua centang yang tersimpan.</li>
        </ul>

        <div class="alert alert-warning small">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Sangat Disarankan:</strong> Atur filter (terutama <strong>Kamar</strong>) sebelum klik "CREATE". Filter ini "kokoh" dan akan menentukan santri siapa saja yang muncul di halaman pembuatan rapot.
        </div>

        <h6><i class="fas fa-tasks me-1"></i> 2. Aksi Massal (Bulk Action)</h6>
        <p>Anda bisa memilih banyak rapot sekaligus untuk melakukan aksi cepat:</p>
        <ul class="mb-3">
          <li>Centang data yang Anda inginkan. Centang di header tabel akan memilih semua data yang <strong>terlihat di halaman ini</strong>.</li>
          <li><strong>Centang Anda DISIMPAN:</strong> Walaupun Anda berganti filter, centang Anda tidak akan hilang (tersimpan di browser).</li>
          <li><strong>Tombol Aksi:</strong> Tombol "PDF (ZIP)", "PNG (ZIP)", dan "Hapus" hanya akan aktif jika ada minimal 1 data terpilih.</li>
          <li><strong>PDF (ZIP):</strong> Mengunduh semua rapot terpilih sebagai file PDF dalam satu file ZIP.</li>
          <li><strong>PNG (ZIP):</strong> Mengunduh semua rapot terpilih sebagai gambar PNG dalam satu file ZIP. (Proses ini butuh waktu).</li>
        </ul>

        <h6><i class="fas fa-cog me-1"></i> 3. Aksi Satuan</h6>
        <p>Klik tombol <i class="fas fa-cog"></i> di setiap baris data untuk:</p>
        <ul class="mb-0">
          <li><strong>View Rapot:</strong> Melihat tampilan rapot di tab baru.</li>
          <li><strong>Unduh PDF/PNG:</strong> Mengunduh rapot *itu saja* sebagai PDF atau PNG.</li>
          <li><strong>Duplikat:</strong> Menyalin isi rapot ini untuk membuat rapot baru (misal untuk bulan depan).</li>
          <li><strong>Hapus:</strong> Menghapus rapot *itu saja*.</li>
        </ul>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Saya Mengerti</button>
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
    .table-dark-blue, .table-dark-blue > th, .table-dark-blue > td {
        background-color: #4e73df !important;
        color: #fff !important;
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

    // 6. REVISI 9.0: Kita tetap pake 'hidden.bs.modal' sebagai failsafe (jaga-jaga)
    // kalo modal ditutup pake 'Esc' atau klik di luar.
    if (guideModalElement) guideModalElement.addEventListener('hidden.bs.modal', returnFocusToTrigger);
    if (pngWarningModalElement) pngWarningModalElement.addEventListener('hidden.bs.modal', returnFocusToTrigger);
    if (deleteConfirmModalElement) deleteConfirmModalElement.addEventListener('hidden.bs.modal', returnFocusToTrigger);

    // === AKHIR REVISI 9.0 ===


    // === Auto-submit Filter ===
    document.querySelectorAll('#kamar, #bulan, #tahun').forEach(function(selectElement) {
        selectElement.addEventListener('change', function() {
            this.closest('form').submit();
        });
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
    const rowCheckboxes = document.querySelectorAll('.row-checkbox');
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
                selectedCountInfo.innerHTML = `<span class="badge bg-secondary">${checkedCount} data terpilih</span>`;
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
            window.open('bulk_processor.php?type=pdf', '_blank');
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
            window.open('bulk_processor.php?type=png', '_blank');
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
            form.action = 'bulk_delete.php';
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
</script>

<?php
// 7. Panggil Footer (Gak berubah)
require_once __DIR__ . '/../footer.php';
?>