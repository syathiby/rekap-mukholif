<?php 
// 1. Panggil 'Otak' aplikasi dulu
require_once __DIR__ . '/../../init.php';

// 2. Jalankan 'SATPAM' buat ngejaga halaman
guard('pelanggaran_bahasa_input');

// 3. Kalau lolos, baru panggil Tampilan
require_once __DIR__ . '/../../header.php'; 

// =========================================================================
// ✅ Query Jenis Pelanggaran
// =========================================================================
$sql_query = "
    SELECT id, nama_pelanggaran, poin
    FROM jenis_pelanggaran 
    WHERE bagian = 'Bahasa' 
    ORDER BY CAST(REGEXP_SUBSTR(nama_pelanggaran, '[0-9]+') AS UNSIGNED) ASC
";

$jenis_pelanggaran_list_result = mysqli_query($conn, trim($sql_query)); 
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.13.2/themes/base/jquery-ui.min.css">

<style>
    /* -- Style Autocomplete & Select2 -- */
    .ui-autocomplete {
        z-index: 1051;
        max-height: 250px;
        overflow-y: auto;
        border-radius: 0.5rem;
        border: 1px solid #dee2e6;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    .select2-container--bootstrap-5 .select2-dropdown {
        border-radius: 0.5rem;
        border: 1px solid #dee2e6;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    .select2-container--bootstrap-5 .select2-selection {
        border-radius: 0.5rem !important;
        border: 1px solid #ced4da;
        min-height: calc(1.5em + 0.75rem + 2px);
        padding: 0.375rem 0.75rem;
        font-size: 1rem;
    }
    .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
        line-height: 1.5;
        padding-left: 0;
    }
    
    /* -- Style Stepper -- */
    .form-step { margin-bottom: 2rem; }
    .form-step-label {
        display: flex; align-items: center; font-size: 1.15rem; font-weight: 600; color: #343a40; margin-bottom: 0.85rem;
    }
    .form-step-label .step-number {
        display: inline-flex; align-items: center; justify-content: center; width: 30px; height: 30px;
        border-radius: 50%; background-color: var(--bs-primary); color: #fff; font-size: 1rem; font-weight: 700; margin-right: 0.75rem;
    }

    /* -- Style Table -- */
    .table-wrapper { border: 1px solid #dee2e6; border-radius: 0.5rem; overflow: hidden; }
    .table { margin-bottom: 0; }
    .table thead th { background-color: #f8f9fa; color: #343a40; font-weight: 600; border-bottom: 2px solid #dee2e6; padding: 0.75rem 1rem; white-space: nowrap; }
    .table tbody td { padding: 0.75rem 1rem; vertical-align: middle; }
    
    /* -- INFO CARD STYLE -- */
    .info-logika-card {
        background-color: #f0fdf4; 
        border: 1px solid #bbf7d0;
        border-radius: 0.75rem;
    }
    .info-icon-wrapper {
        width: 40px; height: 40px; background: white; border-radius: 50%; 
        display: flex; align-items: center; justify-content: center;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05); color: #16a34a;
    }
    .btn-info-toggle {
        background: rgba(255,255,255,0.2); 
        border: 1px solid rgba(255,255,255,0.3);
        color: white;
    }
    .btn-info-toggle:hover {
        background: rgba(255,255,255,0.3); color: white;
    }

    /* Responsive Tweaks */
    @media (max-width: 767px) {
        .card-body { padding: 1.25rem !important; }
        .form-step-label { font-size: 1.05rem; }
        .form-step-label .step-number { width: 26px; height: 26px; font-size: 0.9rem; margin-right: 0.5rem; }
        
        /* Input date margin fix */
        input[type="datetime-local"] { margin-top: 0.5rem; }
        
        /* Hapus padding kanan kiri tabel biar full di layar hp */
        .table tbody td { padding: 0.75rem 0.5rem; }
    }
</style>

<div class="container my-4">
    <div class="card col-xl-10 col-lg-12 mx-auto shadow-sm">
        <div class="card-header bg-primary text-white py-3">
            <div class="d-flex justify-content-between align-items-center">
                <h4 class="mb-0 fs-5 fs-md-4">
                    <i class="fas fa-language me-2"></i> Input Bahasa
                </h4>
                
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-info-toggle d-flex align-items-center gap-2" type="button" data-bs-toggle="collapse" data-bs-target="#infoLogikaCollapse" aria-expanded="false">
                        <i class="fas fa-info-circle"></i> 
                        <span class="d-none d-sm-inline">Sistem Baru</span>
                    </button>

                    <?php if (has_permission('rekap_view_bahasa')): ?>
                    <a href="rekap.php" class="btn btn-light btn-sm d-flex align-items-center gap-2">
                        <i class="fas fa-chart-line"></i> 
                        <span class="d-none d-sm-inline">Rekap</span>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="card-body p-3 p-md-4">
            
            <div class="collapse mb-4" id="infoLogikaCollapse">
                <div class="info-logika-card p-4">
                    <h6 class="fw-bold text-success mb-3"><i class="fas fa-lightbulb me-2"></i>Bagaimana Sistem Bahasa Bekerja?</h6>
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="d-flex gap-3">
                                <div class="info-icon-wrapper flex-shrink-0">
                                    <i class="fas fa-sync-alt"></i>
                                </div>
                                <div>
                                    <strong class="text-dark">Otomatis Update Level</strong>
                                    <p class="small text-muted mb-0">Input baru akan langsung <strong>menggantikan</strong> level bahasa santri sebelumnya. Tidak perlu hapus manual.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex gap-3">
                                <div class="info-icon-wrapper flex-shrink-0">
                                    <i class="fas fa-history"></i>
                                </div>
                                <div>
                                    <strong class="text-dark">Riwayat Tersimpan</strong>
                                    <p class="small text-muted mb-0">Data lama tidak hilang, tapi masuk ke <strong>Log Riwayat</strong> untuk memantau grafik perkembangan santri.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['message']['type']; ?> alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['message']['text']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['message']); ?>
            <?php endif; ?>

            <form action="process.php" method="POST" id="form-pelanggaran">
                
                <div class="form-step">
                    <label class="form-step-label">
                        <span class="step-number">1</span> Pilih Level Bahasa Baru
                    </label>
                    <div class="row g-3">
                        <div class="col-12 col-md-8">
                            <select name="jenis_pelanggaran_id" id="jenis_pelanggaran_id" class="form-control" required>
                                <option value="">Pilih Level Bahasa...</option>
                                <?php
                                if ($jenis_pelanggaran_list_result && mysqli_num_rows($jenis_pelanggaran_list_result) > 0) {
                                    while ($jp = mysqli_fetch_assoc($jenis_pelanggaran_list_result)) {
                                        $label_bersih = str_ireplace(['(Bahasa)', '(bahasa)'], '', $jp['nama_pelanggaran']);
                                        $text_label = sprintf("%s - %d Poin", trim($label_bersih), $jp['poin']);
                                        echo '<option value="' . $jp['id'] . '">' . $text_label . '</option>';
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-4">
                            <input type="datetime-local" name="tanggal" id="tanggal" class="form-control" value="<?php echo date('Y-m-d\TH:i'); ?>" required>
                        </div>
                    </div>
                </div>

                <div class="form-step">
                    <label for="santri-search" class="form-step-label">
                        <span class="step-number">2</span> Cari Nama Santri
                    </label>
                    <div class="input-group">
                        <span class="input-group-text bg-white text-muted border-end-0"><i class="fas fa-search"></i></span>
                        <input type="text" id="santri-search" class="form-control border-start-0 ps-0" placeholder="Ketik nama santri, pilih, langsung masuk tabel...">
                    </div>
                </div>
                
                <div class="form-step">
                    <label class="form-step-label">
                        <span class="step-number">3</span> Daftar Santri
                    </label>
                    
                    <div class="table-wrapper table-responsive">
                        <table class="table table-striped table-hover" id="tabel-santri-pelanggar">
                            <thead>
                                <tr>
                                    <th>Nama Santri</th>
                                    <th class="d-none d-md-table-cell">Kelas</th>
                                    <th class="d-none d-md-table-cell">Kamar</th>
                                    <th class="text-center" style="width: 60px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                </tbody>
                        </table>
                    </div>
                    <div id="empty-table-message" class="text-center text-muted p-4">
                        <i class="fas fa-user-plus fa-2x mb-2 d-block opacity-50"></i>
                        Belum ada santri yang ditambahkan.
                    </div>
                </div>

                <div class="d-grid mt-4">
                    <button type="submit" name="create_bulk_pelanggaran" class="btn btn-success py-3 shadow-sm">
                        <i class="fas fa-save me-2"></i> Update Level Bahasa Santri
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../footer.php'; ?>

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

    // Init Select2 - Width 100% biar ga nyusut di HP
    $('#jenis_pelanggaran_id').select2({
        theme: "bootstrap-5",
        dropdownParent: $('#jenis_pelanggaran_id').parent(),
        placeholder: "Pilih Level Bahasa...",
        width: '100%' 
    });

    // Autocomplete
    $("#santri-search").autocomplete({
        source: "search_santri.php",
        minLength: 2,
        select: function(event, ui) {
            selectedSantri = ui.item;
            tambahSantri();
            $(this).val('');
            return false;
        }
    }).autocomplete("instance")._renderItem = function(ul, item) {
        return $("<li>").append("<div class='py-1 px-2'>" + item.label + "</div>").appendTo(ul);
    };

    // --- FUNGSI UTAMA ---
    function checkTableEmpty() {
        const tableBody = $('#tabel-santri-pelanggar tbody');
        const emptyMessage = $('#empty-table-message');
        const tableWrapper = $('.table-wrapper'); 

        if (tableBody.find('tr').length === 0) {
            emptyMessage.show();
            tableWrapper.hide(); 
        } else {
            emptyMessage.hide();
            tableWrapper.show(); 
        }
    }
    
    // ✅ INI PERBAIKANNYA BRO:
    // Kita bikin fungsinya jadi global biar tombol 'onclick' di HTML bisa baca
    window.checkTableEmpty = checkTableEmpty;
    
    // Jalanin pas awal load
    checkTableEmpty();

    function tambahSantri() {
        if (!selectedSantri) return;

        // Cek Duplikat
        if ($('#tabel-santri-pelanggar').find('tr[data-id="' + selectedSantri.id + '"]').length > 0) {
            alert('Santri atas nama ' + selectedSantri.value + ' sudah ada di dalam daftar.');
            return;
        }

        let namaSantri = escapeHTML(selectedSantri.value);
        let kelasSantri = escapeHTML(selectedSantri.kelas);
        let kamarSantri = escapeHTML(selectedSantri.kamar);

        // LOGIC HTML BARIS TABEL
        let barisBaru = `
            <tr data-id="${selectedSantri.id}">
                <td>
                    <div class="fw-bold text-dark">${namaSantri}</div>
                    
                    <div class="d-block d-md-none mt-1">
                        <span class="badge bg-light text-secondary border me-1">Kelas: ${kelasSantri}</span>
                        <span class="badge bg-light text-secondary border">Kamar: ${kamarSantri}</span>
                    </div>

                    <input type="hidden" name="santri_ids[]" value="${selectedSantri.id}">
                </td>

                <td class="d-none d-md-table-cell text-nowrap align-middle">${kelasSantri}</td>
                <td class="d-none d-md-table-cell text-nowrap align-middle">${kamarSantri}</td>
                
                <td class="text-center align-middle">
                    <button type="button" class="btn btn-sm btn-light text-danger border btn-delete-row" onclick="$(this).closest('tr').remove(); checkTableEmpty();" title="Hapus">
                        <i class="fas fa-times"></i>
                    </button>
                </td>
            </tr>
        `;
        
        $('#tabel-santri-pelanggar tbody').append(barisBaru);
        
        selectedSantri = null;
        checkTableEmpty(); 
    }

    $("#form-pelanggaran").on('submit', function(e) {
        if (!$("#jenis_pelanggaran_id").val()) {
            e.preventDefault();
            alert("Mohon pilih Level Bahasa terlebih dahulu.");
            $('#jenis_pelanggaran_id').select2('open');
            return;
        }
        if ($('#tabel-santri-pelanggar tbody tr').length === 0) {
            e.preventDefault();
            alert('Belum ada santri yang ditambahkan ke dalam daftar.');
            $('#santri-search').focus();
        }
    });
});
</script>