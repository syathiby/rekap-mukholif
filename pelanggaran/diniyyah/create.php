<?php 
// 1. Panggil 'Otak' aplikasi dulu
require_once __DIR__ . '/../../init.php';

// 2. Jalankan 'SATPAM' buat ngejaga halaman
guard('pelanggaran_diniyyah_input'); 

// 3. Kalau lolos, baru panggil Tampilan
require_once __DIR__ . '/../../header.php';

// =========================================================================
// ✅ SAMA SEPERTI 'BAHASA', TAPI WHERE BEDA
// Query SQL dibersihkan dan disederhanakan
// =========================================================================
$sql_query = "
    SELECT id, nama_pelanggaran, poin
    FROM jenis_pelanggaran 
    WHERE bagian = 'Diniyyah' 
    ORDER BY nama_pelanggaran ASC
";

// ✅ Paksa TRIM() buat ngebersihin spasi aneh
$jenis_pelanggaran_list_result = mysqli_query($conn, trim($sql_query)); 

// ❌ Blok PHP untuk $pelanggaran_list_for_js dan mysqli_data_seek Dihapus
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.13.2/themes/base/jquery-ui.min.css">

<style>
    /* -- Style Autocomplete (Santri) -- */
    .ui-autocomplete {
        z-index: 1051;
        max-height: 250px;
        overflow-y: auto;
        border-radius: 0.5rem;
        border: 1px solid #dee2e6;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }

    /* -- Style Select2 (Pelanggaran) -- */
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
    .select2-container--bootstrap-5.select2-container--focus .select2-selection {
        /* ✅ WARNA DISESUAIKAN JADI SUCCESS */
        border-color: var(--bs-success);
        box-shadow: 0 0 0 0.25rem rgba(var(--bs-success-rgb), 0.25);
    }

    /* -- Style Input Form -- */
    .form-control, .form-select {
        border-radius: 0.5rem;
    }

    /* -- Style Stepper (BARU) -- */
    .form-step {
        margin-bottom: 2rem;
    }
    .form-step-label {
        display: flex;
        align-items: center;
        font-size: 1.15rem;
        font-weight: 600;
        color: #343a40;
        margin-bottom: 0.85rem;
    }
    .form-step-label .step-number {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 30px;
        height: 30px;
        border-radius: 50%;
        /* ✅ WARNA DISESUAIKAN JADI SUCCESS */
        background-color: var(--bs-success);
        color: #fff;
        font-size: 1rem;
        font-weight: 700;
        margin-right: 0.75rem;
    }

    /* -- Style Tombol "Tambah" Santri -- */
    .input-group .btn-success { /* Tetap success sesuai tema */
        border-top-right-radius: 0.5rem !important;
        border-bottom-right-radius: 0.5rem !important;
    }

    /* -- Style Tabel (Minimalis) -- */
    .table-wrapper {
        border: 1px solid #dee2e6;
        border-radius: 0.5rem;
        overflow: hidden;
    }
    .table {
        margin-bottom: 0;
    }
    .table thead th {
        background-color: #f8f9fa;
        color: #343a40;
        font-weight: 600;
        border-bottom: 2px solid #dee2e6;
        border-top: 0;
        white-space: nowrap;
        padding: 0.75rem 1rem;
    }
    .table tbody td {
        padding: 0.75rem 1rem;
        vertical-align: middle;
    }
    .table tbody tr:last-child td {
        border-bottom: 0;
    }

    /* -- Style Tombol Hapus (Minimalis) -- */
    .btn-hapus {
        background-color: #fdf2f2;
        color: #dc3545;
        border: 1px solid #fadddd;
        border-radius: 0.375rem;
        width: 38px;
        height: 38px;
    }
    .btn-hapus:hover {
        background-color: #dc3545;
        color: #fff;
        border-color: #dc3545;
    }
    
    /* -- Style Pesan Tabel Kosong -- */
    #empty-table-message {
        border: 2px dashed #dee2e6;
        border-radius: 0.5rem;
        background-color: #f8f9fa;
    }

    /* -- Style Tombol Simpan -- */
    .btn-primary { /* Sesuai warna asli tombol simpan diniyyah */
        font-size: 1.1rem;
        font-weight: 600;
        padding: 0.85rem;
        border-radius: 0.5rem;
    }

    /* -- Responsive di HP -- */
    @media (max-width: 767px) {
        .card-body {
            padding: 1.25rem !important;
        }
        .form-card-header h4 {
            font-size: 1.25rem;
        }
        .form-step-label {
            font-size: 1.05rem;
        }
        .form-step-label .step-number {
            width: 26px;
            height: 26px;
            font-size: 0.9rem;
        }
        .table thead th,
        .table tbody td {
            font-size: 0.9rem;
            padding: 0.6rem 0.5rem;
        }
        .btn-hapus {
            width: 34px;
            height: 34px;
        }
        #empty-table-message {
            font-size: 0.9rem;
        }
    }
</style>

<div class="container my-4">
    <div class="card col-xl-10 col-lg-12 mx-auto shadow-sm">
        <div class="card-header bg-success text-white">
            <div class="d-none d-md-flex justify-content-between align-items-center">
                <h4 class="mb-0">
                    <i class="fas fa-book-quran me-2"></i> Catat Pelanggaran Diniyyah
                </h4>
                <?php if (has_permission('rekap_view_diniyyah')): // Asumsi permission-nya ini ?>
                <a href="rekap.php" class="btn btn-light btn-sm">
                    Lihat Rekap <i class="fas fa-chart-line ms-1"></i>
                </a>
                <?php endif; ?>
            </div>

            <div class="d-md-none text-center">
                <h4 class="mb-2">
                    <i class="fas fa-book-quran me-2"></i> Catat Pelanggaran Diniyyah
                </h4>
                <?php if (has_permission('rekap_view_diniyyah')): ?>
                <a href="rekap.php" class="btn btn-light btn-sm w-100 d-flex justify-content-center align-items-center">
                    <span>Lihat Rekap</span>
                    <i class="fas fa-chart-line ms-1"></i>
                </a>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card-body p-3 p-md-4">
            
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
                        <span class="step-number">1</span> Pilih Jenis Pelanggaran
                    </label>
                    <div class="row g-3">
                        <div class="col-md-8">
                            <select name="jenis_pelanggaran_id" id="jenis_pelanggaran_id" class="form-control" required>
                                <option value="">Pilih atau ketik nama pelanggaran</option>
                                <?php
                                // ✅ Logika loop sama seperti 'bahasa', tapi datanya dari query 'Diniyyah'
                                if ($jenis_pelanggaran_list_result && mysqli_num_rows($jenis_pelanggaran_list_result) > 0) {
                                    while ($jp = mysqli_fetch_assoc($jenis_pelanggaran_list_result)) {
                                        $text_label = sprintf("%s (Poin: %d)", htmlspecialchars($jp['nama_pelanggaran']), $jp['poin']);
                                        echo '<option value="' . $jp['id'] . '">' . $text_label . '</option>';
                                    }
                                } else {
                                    echo '<option value="" disabled>Gagal memuat data pelanggaran. Cek query.</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <input type="datetime-local" name="tanggal" id="tanggal" class="form-control" value="<?php echo date('Y-m-d\TH:i'); ?>" required>
                        </div>
                    </div>
                </div>

                <div class="form-step">
                    <label for="santri-search" class="form-step-label">
                        <span class="step-number">2</span> Cari dan Tambahkan Santri
                    </label>
                    <div class="input-group">
                        <input type="text" id="santri-search" class="form-control" placeholder="Ketik nama santri untuk mencari...">
                        <button class="btn btn-success" type="button" id="btn-tambah-santri" disabled>
                            <i class="fas fa-plus"></i><span class="d-none d-sm-inline ms-1">Tambah</span>
                        </button>
                    </div>
                </div>
                
                <div class="form-step">
                    <label class="form-step-label">
                        <span class="step-number">3</span> Daftar Santri Ditambahkan
                    </label>
                    
                    <div class="table-wrapper table-responsive">
                        <table class="table table-striped table-hover" id="tabel-santri-pelanggar">
                            <thead>
                                <tr>
                                    <th>Nama Santri</th>
                                    <th>Kelas</th>
                                    <th>Kamar</th>
                                    <th class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                </tbody>
                        </table>
                    </div>
                    <div id="empty-table-message" class="text-center text-muted p-4">
                        <i class="fas fa-users-slash fa-2x mb-2 d-block"></i>
                        Belum ada santri yang ditambahkan.
                    </div>
                </div>

                <div class="d-grid mt-4">
                    <button type="submit" name="create_bulk_pelanggaran" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Simpan Semua Pelanggaran
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

    // =================================================================
    // ✅ FUNGSI HELPER UNTUK MENCEGAH XSS (Security)
    // =================================================================
    function escapeHTML(str) {
        if (str === null || typeof str === 'undefined') {
            return '';
        }
        return $('<div>').text(str).html();
    }

    // =================================================================
    // ✅ INISIALISASI Select2 (Untuk Pelanggaran)
    // =================================================================
    $('#jenis_pelanggaran_id').select2({
        theme: "bootstrap-5",
        dropdownParent: $('#jenis_pelanggaran_id').parent()
    });


    // =================================================================
    // ✅ INISIALISASI Autocomplete (Santri)
    // =================================================================
    $("#santri-search").autocomplete({
        source: "search_santri.php",
        minLength: 2,
        select: function(event, ui) {
            selectedSantri = ui.item;
            $('#btn-tambah-santri').prop('disabled', false);
        }
    }).autocomplete("instance")._renderItem = function(ul, item) {
        return $("<li>")
            .append("<div>" + item.label + "</div>")
            .appendTo(ul);
    };

    // =================================================================
    // ✅ Reset pilihan jika input dikosongkan manual (UX)
    // =================================================================
    $("#santri-search").on('keyup', function() {
        if ($(this).val().trim() === '') {
            selectedSantri = null;
            $('#btn-tambah-santri').prop('disabled', true);
        }
    });

    // =================================================================
    // ✅ Fungsi untuk cek tabel kosong
    // =================================================================
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
    
    checkTableEmpty();

    // =================================================================
    // ✅ FUNGSI TABEL (Update dengan escapeHTML & Tombol Hapus Minimalis)
    // =================================================================
    function tambahSantri() {
        if (!selectedSantri) return;

        if ($('#tabel-santri-pelanggar').find('tr[data-id="' + selectedSantri.id + '"]').length > 0) {
            alert('Santri sudah ada dalam daftar.');
            resetInput();
            return;
        }

        let namaSantri = escapeHTML(selectedSantri.value);
        let kelasSantri = escapeHTML(selectedSantri.kelas);
        let kamarSantri = escapeHTML(selectedSantri.kamar);

        let barisBaru = `
            <tr data-id="${selectedSantri.id}">
                <td>
                    ${namaSantri}
                    <input type="hidden" name="santri_ids[]" value="${selectedSantri.id}">
                </td>
                <td>${kelasSantri}</td>
                <td>${kamarSantri}</td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-hapus" title="Hapus Santri">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `;
        $('#tabel-santri-pelanggar tbody').append(barisBaru);
        resetInput();
        checkTableEmpty(); 
    }
    
    $('#btn-tambah-santri').click(tambahSantri);
    
    $('#santri-search').on('keydown', function(e) {
        if (e.key === 'Enter' && selectedSantri) {
            e.preventDefault();
            tambahSantri();
        }
    });

    $('#tabel-santri-pelanggar').on('click', '.btn-hapus', function() {
        $(this).closest('tr').remove();
        checkTableEmpty(); 
    });
    
    function resetInput() {
        $("#santri-search").val('');
        $('#btn-tambah-santri').prop('disabled', true);
        selectedSantri = null;
        $("#santri-search").focus(); 
    }

    // =================================================================
    // ✅ VALIDASI SUBMIT
    // =================================================================
    $("#form-pelanggaran").on('submit', function(e) {
        if (!$("#jenis_pelanggaran_id").val()) {
            e.preventDefault();
            alert("Jenis pelanggaran belum dipilih!");
            $('#jenis_pelanggaran_id').select2('open');
            return;
        }
        if ($('#tabel-santri-pelanggar tbody tr').length === 0) {
            e.preventDefault();
            alert('Daftar santri pelanggar tidak boleh kosong!');
            $('#santri-search').focus();
        }
    });
});
</script>