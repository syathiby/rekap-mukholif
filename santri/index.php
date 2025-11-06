<?php
// 1. Panggil 'Otak' aplikasi dulu
require_once __DIR__ . '/../init.php';

// 2. Jalankan 'SATPAM' buat ngejaga halaman
guard('santri_view');

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
require_once __DIR__ . '/../header.php'; // <-- HTML MULAI DI SINI


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
$colspan = 4; // Kolom dasar: No, Nama, Kelas, Kamar
if ($can_delete) $colspan++; // Tambah 1 jika bisa hapus (untuk checkbox)
if ($can_edit || $can_delete) $colspan++; // Tambah 1 jika bisa edit atau hapus (untuk kolom Aksi)

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
mysqli_stmt_close($stmt_count);
?>

<style>
    /* CSS tidak ada perubahan, tetap sama */
    :root {
        --primary-color: #3498db;
        --secondary-color: #2c3e50;
        --accent-color: #e74c3c;
    }
    .page-title-card {
        background: linear-gradient(135deg, var(--secondary-color), var(--primary-color));
        color: white;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }
    .filter-card {
        background-color: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    }
    .table-container {
        background-color: white;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        overflow: hidden;
    }
    .table thead {
        background-color: var(--secondary-color);
        color: white;
    }
    .action-cell {
        width: 120px;
    }
    .btn-info-circle {
        width: 30px;
        height: 30px;
        padding: 0;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        line-height: 1;
        font-size: 0.9rem;
        background-color: rgba(255, 255, 255, 0.2);
        color: white;
        border: 1px solid rgba(255, 255, 255, 0.5);
        transition: background-color 0.2s;
    }
    .btn-info-circle:hover {
        background-color: rgba(255, 255, 255, 0.3);
        color: white;
    }
    #infoModal .modal-body {
        max-height: 60vh;
        overflow-y: auto;
    }
    
    /* Perbaikan layout tombol bulk */
    .card-action-bulk {
        padding: 1rem;
        background-color: #fff;
    }

    @media (max-width: 768px) {
        .page-title-card h2 { font-size: 1.25rem; }
        .filter-buttons {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 0.5rem;
        }
    }
    
    /* === CSS TAMBAHAN UNTUK STACKING TOMBOL DI HP === */
    @media (max-width: 767px) { /* Target 'md' breakpoint */
        .card-action-bulk > div {
            /* Container utama: justify-between */
            flex-direction: column;
            align-items: stretch !important;
            gap: 1rem;
        }
        .card-action-bulk .d-flex.gap-2 {
            /* Grup 1 (Tambah) & Grup 2 (Edit/Hapus) */
            flex-direction: column;
            align-items: stretch !important;
            width: 100%;
        }
        .card-action-bulk .btn-group {
            /* Grup khusus Edit/Hapus */
            display: flex;
        }
        .card-action-bulk .btn-group .btn {
            flex-grow: 1; /* Biar tombol Edit/Hapus sama rata */
        }
    }
</style>

<div class="container mt-4 mb-5">
    <div class="page-title-card p-3 p-md-4 mb-4">
        <div class="d-flex flex-column flex-md-row justify-content-md-between align-items-md-center">
            <div class="d-flex align-items-center mb-2 mb-md-0">
                <h2 class="mb-0 me-2"><i class="fas fa-users me-2"></i>Data Santri</h2>
                <button type="button" class="btn btn-info-circle" data-bs-toggle="modal" data-bs-target="#infoModal" title="Informasi Penting">
                    <i class="fas fa-info"></i>
                </button>
            </div>
            <div>
                <span class="badge bg-light text-dark fs-6">
                    <i class="fas fa-database me-1"></i>
                    <?php echo $total . " Santri"; ?>
                </span>
            </div>
        </div>
    </div>
    
    <div class="filter-card p-3 p-md-4 mb-4">
        <form class="row g-3" method="GET" action="index.php">
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
                <a href="index.php?reset=1" class="btn btn-outline-secondary" title="Reset Filter" id="resetFilterBtn"><i class="fas fa-times"></i></a>
            </div>
        </form>
    </div>

    <form method="POST" action="" id="bulkDeleteForm">
        <div class="card shadow-sm mb-3 card-action-bulk">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                
                <div class="d-flex flex-wrap gap-2">
                    <?php if ($can_create): ?>
                        <a href="create.php" class="btn btn-success">
                            <i class="fas fa-user-plus me-1"></i> 
                            <span>Tambah Santri</span>
                        </a>
                        <a href="bulk-create.php" class="btn btn-info">
                            <i class="fas fa-file-import me-1"></i> 
                            <span>Bulk Input</span>
                        </a>
                    <?php endif; ?>
                </div>

                <div class="d-flex flex-wrap justify-content-end align-items-center gap-2">
                    <div class="btn-group" role="group">
                        <?php if ($can_edit): ?>
                            <a href="bulk-edit.php" class="btn btn-warning" id="bulkEditBtn">
                                <i class="fas fa-pen-to-square me-1"></i> 
                                <span>Bulk Edit</span>
                            </a>
                        <?php endif; ?>
                        <?php if ($can_delete): ?>
                            <button type="submit" class="btn btn-danger" id="bulkDeleteBtn" disabled form="bulkDeleteForm">
                                <i class="fas fa-user-minus me-1"></i> 
                                <span>Hapus Terpilih</span>
                            </button>
                        <?php endif; ?>
                    </div>
                    <div id="selected-count-info" class="d-none">
                        </div>
                </div>

            </div>
        </div>

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
                            <?php if ($can_edit || $can_delete): ?>
                                <th class="action-cell">Aksi</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $query = "SELECT * FROM santri" . $where_sql . " ORDER BY CAST(kamar AS UNSIGNED) ASC, nama ASC";
                        $stmt = mysqli_prepare($conn, $query);
                        if (!empty($params)) {
                            mysqli_stmt_bind_param($stmt, $types, ...$params);
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
                                        <div class="rounded-circle text-white d-flex align-items-center justify-content-center" 
                                             style="width: 38px; height: 38px; background-color: #<?= substr(md5($row['id']), 0, 6) ?>; font-weight: 500;">
                                            <?= strtoupper(substr($row['nama'], 0, 1)) ?>
                                        </div>
                                    </div>
                                    <div>
                                        <strong><?= htmlspecialchars($row['nama']) ?></strong>
                                        <div class="text-muted small">ID: <?= $row['id'] ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="align-middle"><span class="badge bg-info text-dark"><?= htmlspecialchars($row['kelas']) ?></span></td>
                            <td class="align-middle">
                                <span class="badge bg-light text-dark">
                                    <i class="fas fa-door-open me-1"></i>
                                    <?= htmlspecialchars($row['kamar']) ?>
                                </span>
                            </td>
                            <?php if ($can_edit || $can_delete): ?>
                                <td class="align-middle">
                                    <div class="d-flex">
                                        <?php if ($can_edit): ?>
                                            <a href="edit.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-warning me-2" title="Edit"><i class="fas fa-edit"></i></a>
                                        <?php endif; ?>
                                        <?php if ($can_delete): ?>
                                            <a href="#" onclick="showConfirmDelete('delete.php?id=<?= $row['id'] ?>')" class="btn btn-sm btn-danger" title="Hapus"><i class="fas fa-trash"></i></a>
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
    </form> </div>

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

<?php require_once __DIR__ . '/../footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
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
    const selectAllCheckbox = document.getElementById('selectAll');
    const rowCheckboxes = document.querySelectorAll('.row-checkbox');
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

    // --- Inisialisasi Saat Halaman Load ---
    loadSelections();
    toggleActionButtons();

});
</script>