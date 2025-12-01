<?php 
// 1. Panggil 'Otak' aplikasi dulu
require_once __DIR__ . '/../../init.php';

// 2. Jalankan 'SATPAM' buat ngejaga halaman
guard('pelanggaran_pengabdian_input');   

// 3. Kalau lolos, baru panggil Tampilan
require_once __DIR__ . '/../../header.php';

// =========================================================================
// ✅ AMBIL DATA (PHP)
// =========================================================================

// 1. Jenis Pelanggaran Individu (Kecuali ID 3 - Kebersihan)
$jp_individu_list = mysqli_query($conn, "SELECT id, nama_pelanggaran, poin FROM jenis_pelanggaran WHERE bagian = 'Pengabdian' AND id != 3 ORDER BY nama_pelanggaran ASC");

// 2. Daftar Kamar untuk Checkbox
$kamarQuery = mysqli_query($conn, "
    SELECT DISTINCT kamar FROM santri
    WHERE kamar IS NOT NULL AND kamar != ''
    ORDER BY
        REGEXP_REPLACE(kamar, '[0-9]', '') ASC,
        CAST(REGEXP_REPLACE(kamar, '[^0-9]', '') AS UNSIGNED) ASC
");
?>

<!-- Library CSS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.13.2/themes/base/jquery-ui.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">

<style>
    /* -- VARS & THEME (Soft Gold Theme) -- */
    :root {
        --theme-primary: #d4a017; /* Emas yang lebih kalem/elegan */
        --theme-light: #fff9e6;   /* Background kuning pudar banget */
        --theme-text: #495057;
    }

    /* Card Styling */
    .card-custom {
        border: none;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        overflow: hidden;
    }
    
    /* Header Minimalis (One Tone) */
    .card-header-custom {
        background-color: #fff;
        border-bottom: 2px solid #f0f0f0;
        padding: 1.5rem;
    }
    .header-title {
        color: var(--theme-text);
        font-weight: 700;
        font-size: 1.25rem;
        display: flex;
        align-items: center;
    }
    .header-icon {
        background-color: var(--theme-light);
        color: var(--theme-primary);
        width: 40px; height: 40px;
        display: flex; align-items: center; justify-content: center;
        border-radius: 10px;
        margin-right: 12px;
    }

    /* -- TABS STYLING (Menyatu dengan Body) -- */
    .nav-tabs {
        border-bottom: 1px solid #dee2e6;
        padding: 0 1.5rem;
        background: #fff;
    }
    .nav-tabs .nav-item {
        margin-bottom: -1px;
    }
    .nav-tabs .nav-link {
        border: none;
        border-bottom: 3px solid transparent;
        color: #adb5bd;
        font-weight: 600;
        padding: 1rem 1.5rem;
        transition: all 0.3s;
    }
    .nav-tabs .nav-link:hover {
        color: var(--theme-primary);
        background: transparent;
    }
    .nav-tabs .nav-link.active {
        color: var(--theme-primary);
        background: #fff;
        border-bottom: 3px solid var(--theme-primary);
    }

    /* -- FORM ELEMENTS -- */
    .form-label {
        font-size: 0.9rem;
        color: #6c757d;
        font-weight: 600;
        margin-bottom: 0.5rem;
    }
    .form-control, .form-select, .select2-container--bootstrap-5 .select2-selection {
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 0.6rem 1rem;
        font-size: 0.95rem;
    }
    .form-control:focus, .form-select:focus, .select2-container--bootstrap-5.select2-container--focus .select2-selection {
        border-color: var(--theme-primary);
        box-shadow: 0 0 0 0.25rem rgba(212, 160, 23, 0.15);
    }

    /* -- CHECKBOX GRID (Kebersihan) -- */
    .checkbox-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(85px, 1fr));
        gap: 10px;
    }
    .btn-check:checked + .btn-outline-custom {
        background-color: var(--theme-primary);
        color: white;
        border-color: var(--theme-primary);
    }
    .btn-outline-custom {
        color: #6c757d;
        border-color: #dee2e6;
        border-radius: 8px;
        padding: 0.5rem;
        font-size: 0.9rem;
        font-weight: 600;
    }
    .btn-outline-custom:hover {
        background-color: #f8f9fa;
        color: var(--theme-primary);
        border-color: var(--theme-primary);
    }

    /* -- UI Autocomplete Fix (Soft & Clean) -- */
    .ui-autocomplete { 
        z-index: 1050; 
        max-height: 250px; 
        overflow-y: auto; 
        border-radius: 10px; 
        border: 1px solid #eee;
        box-shadow: 0 10px 30px rgba(0,0,0,0.08); /* Shadow halus */
        padding: 5px;
        background: #fff;
    }
    /* Style pas item di-hover atau dipilih pake keyboard */
    .ui-menu-item .ui-menu-item-wrapper.ui-state-active {
        background: #f8f9fa !important; /* Abu muda soft */
        color: #212529 !important;
        border: none !important;
        border-radius: 6px;
    }
    .ui-menu-item {
        margin-bottom: 2px;
    }

    /* -- TABLE -- */
    .table-custom thead th {
        background-color: #f8f9fa;
        color: #495057;
        font-weight: 600;
        border-bottom: 2px solid #e9ecef;
        padding: 1rem;
    }
    .table-custom tbody td {
        padding: 1rem;
        vertical-align: middle;
    }

    /* -- RESPONSIVE TWEAKS -- */
    @media (max-width: 767px) {
        /* Tab Sebelahan 50:50 */
        .nav-tabs {
            display: flex;
            flex-wrap: nowrap;
            padding: 0;
        }
        .nav-item { width: 50%; text-align: center; }
        .nav-link { width: 100%; padding: 1rem 0.5rem; font-size: 0.95rem; }
        
        .card-body { padding: 1.25rem !important; }
        .checkbox-grid { grid-template-columns: repeat(auto-fill, minmax(70px, 1fr)); }
        
        /* Tombol Simpan Full Width di HP */
        .btn-simpan-responsive { width: 100%; margin-top: 1rem; }
    }
</style>

<div class="container my-4">
    <div class="card card-custom col-xl-10 col-lg-12 mx-auto">
        
        <!-- HEADER: Clean & Minimalist -->
        <div class="card-header-custom">
             <div class="header-title">
                 <div class="header-icon">
                     <i class="fas fa-hand-holding-heart"></i>
                 </div>
                 <span>Input Pengabdian</span>
             </div>
        </div>

        <!-- TABS: Navigasi -->
        <ul class="nav nav-tabs" id="pelanggaranTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="individu-tab" data-bs-toggle="tab" data-bs-target="#individu" type="button" role="tab">
                    <i class="fas fa-user-clock me-2 d-none d-sm-inline"></i>Individu
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="kamar-tab" data-bs-toggle="tab" data-bs-target="#kamar" type="button" role="tab">
                    <i class="fas fa-broom me-2 d-none d-sm-inline"></i>Kebersihan
                </button>
            </li>
        </ul>

        <div class="card-body">
            
            <!-- Flash Message -->
            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['message']['type']; ?> alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
                    <?php echo $_SESSION['message']['text']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['message']); ?>
            <?php endif; ?>

            <div class="tab-content" id="pelanggaranTabContent">
                
                <!-- ============================================= -->
                <!-- TAB 1: PELANGGARAN INDIVIDU                   -->
                <!-- ============================================= -->
                <div class="tab-pane fade show active" id="individu" role="tabpanel">
                    <form action="process.php" method="POST" id="form-individu">
                        <input type="hidden" name="tipe_pelanggaran" value="individu">
                        
                        <div class="row g-4 mb-4">
                            <!-- 1. Jenis Pelanggaran -->
                            <div class="col-md-7">
                                <label for="jenis_pelanggaran_id" class="form-label">1. Jenis Pelanggaran</label>
                                <select name="jenis_pelanggaran_id" id="jenis_pelanggaran_id" class="form-select" required>
                                    <option value="">-- Cari Pelanggaran --</option>
                                    <?php 
                                    mysqli_data_seek($jp_individu_list, 0); 
                                    while ($jp = mysqli_fetch_assoc($jp_individu_list)): ?>
                                        <option value="<?= $jp['id'] ?>">
                                            <?= htmlspecialchars($jp['nama_pelanggaran']) ?> (<?= $jp['poin'] ?> Poin)
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <!-- 2. Tanggal -->
                            <div class="col-md-5">
                                <label for="tanggal_individu" class="form-label">2. Tanggal Kejadian</label>
                                <input type="datetime-local" name="tanggal_individu" id="tanggal_individu" class="form-control" value="<?php echo date('Y-m-d\TH:i'); ?>" required>
                            </div>
                        </div>

                        <!-- 3. Cari Santri -->
                        <div class="mb-4">
                            <label class="form-label">3. Cari Nama Santri</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0 text-muted ps-3"><i class="fas fa-search"></i></span>
                                <input type="text" id="santri-search" class="form-control border-start-0 ps-2" placeholder="Ketik nama santri...">
                            </div>
                        </div>

                        <!-- 4. Tabel Daftar -->
                        <div class="table-responsive border rounded-3 mb-4">
                            <table class="table table-custom mb-0" id="tabel-santri-pelanggar">
                                <thead>
                                    <tr>
                                        <th class="ps-3">Nama Santri</th>
                                        <th class="d-none d-md-table-cell">Kelas</th>
                                        <th class="d-none d-md-table-cell">Kamar</th>
                                        <th class="text-center" width="60"><i class="fas fa-trash-alt"></i></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Data masuk via JS -->
                                </tbody>
                            </table>
                            <!-- State Kosong -->
                            <div id="empty-table-message" class="text-center py-5 text-muted">
                                <div class="mb-2 opacity-25">
                                    <i class="fas fa-user-plus fa-3x"></i>
                                </div>
                                <small>Belum ada santri ditambahkan.</small>
                            </div>
                        </div>

                        <!-- Tombol Simpan -->
                        <div class="d-flex justify-content-end">
                            <button type="submit" name="submit_pelanggaran" class="btn btn-success px-4 py-2 fw-bold shadow-sm btn-simpan-responsive">
                                <i class="fas fa-check-circle me-2"></i> Simpan Data Individu
                            </button>
                        </div>
                    </form>
                </div>

                <!-- ============================================= -->
                <!-- TAB 2: KEBERSIHAN KAMAR                       -->
                <!-- ============================================= -->
                <div class="tab-pane fade" id="kamar" role="tabpanel">
                    <form action="process.php" method="POST">
                        <input type="hidden" name="tipe_pelanggaran" value="kamar">
                        
                        <div class="mb-4">
                            <label class="form-label d-block">1. Pilih Kamar (Bisa Banyak)</label>
                            <div class="checkbox-grid">
                                <?php mysqli_data_seek($kamarQuery, 0); // Reset pointer ?>
                                <?php while($row = mysqli_fetch_assoc($kamarQuery)): ?>
                                    <div>
                                        <input type="checkbox" class="btn-check" name="kamar[]" id="kamar-<?= $row['kamar'] ?>" value="<?= htmlspecialchars($row['kamar']) ?>" autocomplete="off">
                                        <label class="btn btn-outline-custom w-100" for="kamar-<?= $row['kamar'] ?>"><?= htmlspecialchars($row['kamar']) ?></label>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        </div>
                        
                        <!-- Catatan Dinamis -->
                        <div class="mb-4">
                            <label class="form-label">2. Catatan (Opsional)</label>
                            <div id="catatan-kamar-container" class="bg-light p-3 rounded-3 border border-dashed">
                                <small class="text-muted fst-italic d-flex align-items-center" id="catatan-placeholder">
                                    <i class="fas fa-info-circle me-2"></i> Pilih kamar di atas untuk menulis catatan...
                                </small>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="tanggal_kamar" class="form-label">3. Tanggal Pengecekan</label>
                            <input type="datetime-local" name="tanggal_kamar" id="tanggal_kamar" class="form-control" value="<?php echo date('Y-m-d\TH:i'); ?>" required>
                        </div>

                        <!-- Tombol Simpan -->
                        <div class="d-flex justify-content-end">
                            <button type="submit" name="submit_pelanggaran" class="btn btn-success px-4 py-2 fw-bold shadow-sm btn-simpan-responsive">
                                <i class="fas fa-broom me-2"></i> Simpan Kebersihan
                            </button>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../footer.php'; ?>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function() {
    let selectedSantri = null;

    function escapeHTML(str) {
        if (str === null || typeof str === 'undefined') return '';
        return $('<div>').text(str).html();
    }

    // Init Select2 Modern Style
    $('#jenis_pelanggaran_id').select2({
        theme: "bootstrap-5",
        dropdownParent: $('#jenis_pelanggaran_id').parent(),
        width: '100%',
        placeholder: "Cari jenis pelanggaran...",
    });

    // ==========================================
    // 1. LOGIKA AUTO ADD (INDIVIDU)
    // ==========================================
    $("#santri-search").autocomplete({
        source: "search_santri.php",
        minLength: 2,
        select: function(event, ui) {
            selectedSantri = ui.item;
            tambahSantri(); // Auto Add Trigger
            $(this).val('');
            return false;
        }
    }).autocomplete("instance")._renderItem = function(ul, item) {
        // ✨ UPDATE: Tambah text 'Kelas' & 'Kamar' biar jelas ✨
        return $("<li>")
            .append(`
                <div class='py-2 px-3 border-bottom'>
                    <div class='fw-semibold text-dark'>${item.value}</div>
                    <small class='text-muted'>Kelas: ${item.kelas} • Kamar: ${item.kamar}</small>
                </div>
            `)
            .appendTo(ul);
    };

    function checkTableEmpty() {
        if ($('#tabel-santri-pelanggar tbody tr').length === 0) {
            $('#empty-table-message').show();
            $('#tabel-santri-pelanggar').parent().addClass('d-none'); // Hide wrapper border
        } else {
            $('#empty-table-message').hide();
            $('#tabel-santri-pelanggar').parent().removeClass('d-none').show();
        }
    }
    
    // Global function
    window.checkTableEmpty = checkTableEmpty;
    checkTableEmpty(); // Init state

    function tambahSantri() {
        if (!selectedSantri) return;

        if ($('#tabel-santri-pelanggar').find('tr[data-id="' + selectedSantri.id + '"]').length > 0) {
            alert('Santri ini sudah masuk daftar, Bos!');
            return;
        }

        let namaSantri = escapeHTML(selectedSantri.value);
        let kelasSantri = escapeHTML(selectedSantri.kelas);
        let kamarSantri = escapeHTML(selectedSantri.kamar);

        // ✅ UPDATE: Tambah text 'Kelas:' dan 'Kamar:' di badge mobile
        let barisBaru = `
            <tr data-id="${selectedSantri.id}">
                <td class="ps-3">
                    <div class="fw-bold text-dark">${namaSantri}</div>
                    <!-- Badge Mobile -->
                    <div class="d-block d-md-none mt-1">
                        <span class="badge bg-light text-dark border me-1">Kelas: ${kelasSantri}</span>
                        <span class="badge bg-light text-dark border">Kamar: ${kamarSantri}</span>
                    </div>
                    <input type="hidden" name="santri_ids[]" value="${selectedSantri.id}">
                </td>
                <td class="d-none d-md-table-cell align-middle text-muted">${kelasSantri}</td>
                <td class="d-none d-md-table-cell align-middle text-muted">${kamarSantri}</td>
                <td class="text-center align-middle">
                    <button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="$(this).closest('tr').remove(); checkTableEmpty();" title="Hapus">
                        <i class="fas fa-times-circle fa-lg"></i>
                    </button>
                </td>
            </tr>
        `;
        $('#tabel-santri-pelanggar tbody').append(barisBaru);
        selectedSantri = null;
        checkTableEmpty(); 
    }

    $("#form-individu").on('submit', function(e) {
        if (!$("#jenis_pelanggaran_id").val()) {
            e.preventDefault();
            alert("⚠️ Eits, pilih jenis pelanggarannya dulu dong!");
            $('#jenis_pelanggaran_id').select2('open');
            return;
        }
        if ($('#tabel-santri-pelanggar tbody tr').length === 0) {
            e.preventDefault();
            alert('⚠️ Belum ada santri nih. Cari dulu namanya!');
            $('#santri-search').focus();
        }
    });

    // ==========================================
    // 2. LOGIKA CATATAN KAMAR (TAB 2)
    // ==========================================
    const catatanContainer = $('#catatan-kamar-container');
    const placeholderText = '<small class="text-muted fst-italic d-flex align-items-center" id="catatan-placeholder"><i class="fas fa-info-circle me-2"></i> Pilih kamar di atas untuk menulis catatan...</small>';

    $('input[name="kamar[]"]').on('change', function() {
        const checkbox = $(this);
        const kamar = checkbox.val();
        const kamarId = kamar.replace(/[^a-zA-Z0-9]/g, ''); 

        if (checkbox.is(':checked')) {
            $('#catatan-placeholder').remove();
            const newField = `
                <div class="mb-3 animate__animated animate__fadeIn" id="catatan-wrapper-${kamarId}">
                    <label class="form-label small mb-1 fw-bold text-muted">
                        Catatan untuk Kamar <span class="text-dark">${kamar}</span>
                    </label>
                    <input type="text" name="catatan[${kamar}]" class="form-control form-control-sm" placeholder="Contoh: Sprei berantakan">
                </div>
            `;
            catatanContainer.append(newField);
        } else {
            $(`#catatan-wrapper-${kamarId}`).remove();
            if (catatanContainer.children().length === 0) {
                catatanContainer.html(placeholderText);
            }
        }
    });
    
    $('form[action="process.php"]').not('#form-individu').submit(function(e) {
        if ($('input[name="kamar[]"]:checked').length === 0) {
            alert('⚠️ Pilih minimal satu kamar dulu ya!');
            e.preventDefault();
        }
    });
});
</script>