<?php 
// 1. Panggil 'Otak' aplikasi dulu
require_once __DIR__ . '/../../bootstrap/init.php';

// 2. Jalankan 'SATPAM' buat ngejaga halaman
guard('pelanggaran_pengabdian_input');   

// 3. Kalau lolos, baru panggil Tampilan
require_once __DIR__ . '/../../layouts/header.php';

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

<!-- Load Libraries -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.13.2/themes/base/jquery-ui.min.css">

<style>
    :root {
        --primary: #d4a017;       
        --primary-dark: #b8860b;
        --bg-body: #f8fafc;
        --text-dark: #1e293b;
        --text-muted: #64748b;
        --card-border: rgba(0,0,0,0.05);
    }

    body { background-color: var(--bg-body); font-family: 'Inter', sans-serif; }

    /* === TABS === */
    .nav-tabs {
        border-bottom: 2px solid #e2e8f0;
        margin-bottom: 2rem;
        display: flex;
        flex-direction: row;
        flex-wrap: nowrap;
    }
    .nav-tabs .nav-link {
        border: none;
        color: var(--text-muted);
        font-weight: 600;
        padding: 1rem 1.5rem;
        background: transparent;
        transition: all 0.2s;
        border-bottom: 3px solid transparent;
        margin-bottom: -2px;
    }
    .nav-tabs .nav-link:hover {
        color: var(--primary);
    }
    .nav-tabs .nav-link.active {
        color: var(--primary);
        border-bottom: 3px solid var(--primary);
        background: transparent;
    }

    /* === CARD STYLES === */
    .pro-card {
        background: #ffffff;
        border: 1px solid var(--card-border);
        border-radius: 1.2rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
        transition: box-shadow 0.3s ease;
    }
    .pro-card:hover {
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.08), 0 4px 6px -2px rgba(0, 0, 0, 0.04);
    }
    .pro-card-header {
        background: transparent;
        border-bottom: 1px solid #f1f5f9;
        padding: 1.5rem 1.5rem 1rem 1.5rem;
    }
    .pro-card-body {
        padding: 1.5rem;
    }
    .pro-card-footer {
        background: #ffffff;
        border-top: 1px solid #f1f5f9;
        padding: 1.5rem;
        border-bottom-left-radius: 1.2rem;
        border-bottom-right-radius: 1.2rem;
    }

    /* === FORM INPUTS === */
    .form-control-custom {
        padding: 0.75rem 1rem;
        border-radius: 0.75rem;
        border: 1px solid #cbd5e1;
        font-size: 0.95rem;
        background-color: #f8fafc;
        transition: all 0.2s;
        width: 100%;
        color: var(--text-dark);
    }
    .form-control-custom:focus {
        border-color: var(--primary);
        background-color: #ffffff;
        box-shadow: 0 0 0 4px rgba(212, 160, 23, 0.15);
        outline: none;
    }
    .form-label-custom {
        font-weight: 700;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--text-muted);
        margin-bottom: 0.5rem;
    }

    /* === AUTOCOMPLETE DROPDOWN === */
    .ui-autocomplete {
        border-radius: 0.75rem;
        box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        border: 1px solid #e2e8f0;
        padding: 0;
        max-height: 320px;
        overflow-y: auto;
        font-family: inherit;
        z-index: 9999 !important;
        background: #fff;
        margin-top: 8px;
    }
    .ui-menu-item-wrapper {
        padding: 0.8rem 1.2rem;
        border-bottom: 1px solid #f1f5f9;
        display: flex;
        align-items: center;
        transition: all 0.2s;
        cursor: pointer;
    }
    .ui-menu-item:last-child .ui-menu-item-wrapper {
        border-bottom: none;
    }
    .ui-menu-item-wrapper:hover, .ui-state-active {
        background-color: #f8fafc !important;
        color: inherit !important;
        border-bottom: 1px solid #f1f5f9;
        margin: 0;
    }

    /* === TABLE === */
    .table-responsive-custom {
        border-radius: 0.75rem;
        border: 1px solid #e2e8f0;
        overflow: hidden;
    }
    .table > :not(caption) > * > * {
        padding: 1rem 1.2rem;
        border-bottom-color: #f1f5f9;
    }
    .table thead th {
        background-color: #f8fafc;
        color: var(--text-muted);
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border-bottom: 1px solid #e2e8f0;
    }
    .table tbody tr { transition: background-color 0.2s; }
    .table tbody tr:hover { background-color: #f8fafc; }

    /* === EMPTY STATE === */
    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        background-color: #f8fafc;
        border-radius: 0.75rem;
        border: 2px dashed #cbd5e1;
    }

    /* === CHECKBOX GRID (Kebersihan) === */
    .checkbox-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(85px, 1fr));
        gap: 10px;
    }
    .btn-outline-custom {
        color: var(--text-muted);
        border: 1px solid #cbd5e1;
        border-radius: 0.75rem;
        padding: 0.75rem;
        font-size: 0.95rem;
        font-weight: 600;
        background: #fff;
        transition: all 0.2s;
        width: 100%;
        text-align: center;
        display: inline-block;
        cursor: pointer;
    }
    .btn-check:checked + .btn-outline-custom {
        background-color: var(--primary);
        color: white;
        border-color: var(--primary);
        box-shadow: 0 4px 10px rgba(212, 160, 23, 0.3);
    }
    .btn-outline-custom:hover {
        border-color: var(--primary);
        color: var(--primary);
    }

    /* === BUTTONS === */
    .btn-save {
        background: linear-gradient(135deg, #d4a017, #b8860b);
        color: white;
        border: none;
        border-radius: 0.75rem;
        padding: 1rem;
        font-weight: 700;
        font-size: 1.05rem;
        box-shadow: 0 4px 12px rgba(212, 160, 23, 0.3);
        transition: all 0.3s;
        width: 100%;
    }
    .btn-save:hover {
        background: linear-gradient(135deg, #b8860b, #996e08);
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(212, 160, 23, 0.4);
        color: white;
    }
    .btn-back {
        background: #ffffff;
        border: 1px solid #cbd5e1;
        color: var(--text-dark);
        border-radius: 50px;
        font-weight: 600;
        padding: 0.5rem 1.25rem;
        transition: all 0.2s;
    }
    .btn-back:hover {
        background: #f1f5f9;
        border-color: #94a3b8;
        color: var(--primary);
    }
    
    /* Select2 customizations */
    .select2-container--bootstrap-5 .select2-selection {
        border-radius: 0.75rem !important;
        border: 1px solid #cbd5e1 !important;
        background-color: #f8fafc !important;
        padding: 0.3rem 0.5rem;
    }
    .select2-container--bootstrap-5 .select2-selection:focus,
    .select2-container--bootstrap-5.select2-container--focus .select2-selection {
        border-color: var(--primary) !important;
        background-color: #ffffff !important;
        box-shadow: 0 0 0 4px rgba(212, 160, 23, 0.15) !important;
    }
</style>

<div class="container-fluid py-4 px-4">
    <!-- Header Page -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h3 class="fw-bolder text-dark mb-1"><i class="fas fa-hand-holding-heart" style="color: var(--primary)"></i> Input Pengabdian</h3>
            <p class="text-muted mb-0">Catat pelanggaran individu atau ketidakbersihan kamar.</p>
        </div>
        <a href="../../rekap/keterlambatan.php" id="dynamic-rekap-btn" class="btn-back shadow-sm text-decoration-none">
            <i class="fas fa-chart-line me-1"></i> <span id="dynamic-rekap-text">Rekap Keterlambatan</span>
        </a>
    </div>

    <!-- Alert -->
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?php echo $_SESSION['message']['type']; ?> alert-dismissible fade show border-0 shadow-sm mb-4 rounded-3 d-flex align-items-center">
            <i class="<?php echo ($_SESSION['message']['type'] == 'success') ? 'fas fa-check-circle text-success' : 'fas fa-exclamation-circle text-danger'; ?> fs-4 me-3"></i>
            <div><?php echo $_SESSION['message']['text']; ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['message']); ?>
    <?php endif; ?>

    <!-- TABS: Navigasi -->
    <ul class="nav nav-tabs" id="pelanggaranTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="individu-tab" data-bs-toggle="tab" data-bs-target="#individu" type="button" role="tab">
                <i class="fas fa-user-clock me-2"></i>Individu
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="kamar-tab" data-bs-toggle="tab" data-bs-target="#kamar" type="button" role="tab">
                <i class="fas fa-broom me-2"></i>Kebersihan Kamar
            </button>
        </li>
    </ul>

    <div class="tab-content" id="pelanggaranTabContent">
        
        <!-- ============================================= -->
        <!-- TAB 1: PELANGGARAN INDIVIDU                   -->
        <!-- ============================================= -->
        <div class="tab-pane fade show active" id="individu" role="tabpanel">
            <form action="process.php" method="POST" id="form-individu">
                <input type="hidden" name="tipe_pelanggaran" value="individu">
                <div class="row g-4">
                    <!-- LEFT COLUMN: Config & Search -->
                    <div class="col-lg-4 d-flex flex-column gap-4">
                        
                        <!-- CARD 1: PENGATURAN -->
                        <div class="pro-card">
                            <div class="pro-card-header d-flex align-items-center">
                                <div class="rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 36px; height: 36px; background-color: rgba(212, 160, 23, 0.1); color: var(--primary);">
                                    <i class="fas fa-cog"></i>
                                </div>
                                <h6 class="fw-bold text-dark mb-0">1. Pengaturan Pelanggaran</h6>
                            </div>
                            <div class="pro-card-body">
                                <div class="mb-4">
                                    <label class="form-label-custom">JENIS PELANGGARAN</label>
                                    <select name="jenis_pelanggaran_id" id="jenis_pelanggaran_id" class="form-select" required>
                                        <option value="">Cari jenis pelanggaran...</option>
                                        <?php 
                                        mysqli_data_seek($jp_individu_list, 0); 
                                        while ($jp = mysqli_fetch_assoc($jp_individu_list)): ?>
                                            <option value="<?= $jp['id'] ?>">
                                                <?= htmlspecialchars($jp['nama_pelanggaran']) ?> (<?= $jp['poin'] ?> Poin)
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="form-label-custom">TANGGAL PENCATATAN</label>
                                    <input type="datetime-local" name="tanggal_individu" class="form-control-custom" value="<?= date('Y-m-d\TH:i'); ?>" required>
                                </div>
                            </div>
                        </div>

                        <!-- CARD 2: CARI SANTRI -->
                        <div class="pro-card">
                            <div class="pro-card-header d-flex align-items-center">
                                <div class="rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 36px; height: 36px; background-color: rgba(212, 160, 23, 0.1); color: var(--primary);">
                                    <i class="fas fa-search"></i>
                                </div>
                                <h6 class="fw-bold text-dark mb-0">2. Cari Santri</h6>
                            </div>
                            <div class="pro-card-body">
                                <p class="text-muted small mb-3">Ketik nama santri untuk menambahkan ke daftar pelanggar.</p>
                                <div class="position-relative">
                                    <i class="fas fa-search position-absolute text-muted" style="top: 14px; left: 16px; z-index: 5;"></i>
                                    <input type="text" id="santri-search" class="form-control-custom ps-5" placeholder="Ketik minimal 2 huruf...">
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- RIGHT COLUMN: Table Data -->
                    <div class="col-lg-8">
                        <div class="pro-card h-100 d-flex flex-column">
                            <div class="pro-card-header d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center">
                                    <div class="rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 36px; height: 36px; background-color: rgba(212, 160, 23, 0.1); color: var(--primary);">
                                        <i class="fas fa-users"></i>
                                    </div>
                                    <h6 class="fw-bold text-dark mb-0">3. Daftar Santri Pelanggar</h6>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-danger fw-bold rounded-pill px-3 py-1" onclick="clearAll()" id="btn-clear-all" style="display:none; font-size: 0.8rem;">
                                    <i class="fas fa-trash-alt me-1"></i> Hapus Semua
                                </button>
                            </div>
                            
                            <div class="pro-card-body d-flex flex-column flex-grow-1 p-4" style="min-height: 400px;">
                                
                                <!-- Tabel Data -->
                                <div class="table-responsive-custom mb-0" id="table-container" style="display:none; flex-grow: 1;">
                                    <table class="table table-borderless m-0 align-middle" id="tabel-santri-pelanggar">
                                        <thead>
                                            <tr>
                                                <th class="ps-4">NAMA SANTRI</th>
                                                <th class="text-center pe-4" width="80">AKSI</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>

                                <!-- Empty State -->
                                <div id="empty-state" class="empty-state d-flex flex-column align-items-center justify-content-center flex-grow-1">
                                    <div class="bg-light rounded-circle d-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                                        <i class="fas fa-user-graduate fa-2x text-muted opacity-50"></i>
                                    </div>
                                    <h6 class="fw-bold text-dark mb-2">Belum Ada Santri</h6>
                                    <p class="text-muted small mb-0" style="max-width: 250px;">Gunakan kolom pencarian di sebelah kiri untuk memilih santri pelanggar.</p>
                                </div>

                            </div>

                            <div class="pro-card-footer mt-auto">
                                <button type="submit" name="submit_pelanggaran" class="btn-save">
                                    <i class="fas fa-check-circle me-2"></i> SIMPAN DATA INDIVIDU
                                </button>
                            </div>
                        </div>
                    </div>
                </div> <!-- End Row -->
            </form>
        </div>

        <!-- ============================================= -->
        <!-- TAB 2: KEBERSIHAN KAMAR                       -->
        <!-- ============================================= -->
        <div class="tab-pane fade" id="kamar" role="tabpanel">
            <form action="process.php" method="POST" id="form-kamar">
                <input type="hidden" name="tipe_pelanggaran" value="kamar">
                <div class="row g-4">
                    <!-- LEFT COLUMN: Kamar Grid -->
                    <div class="col-lg-8">
                        <div class="pro-card h-100 d-flex flex-column">
                            <div class="pro-card-header d-flex align-items-center">
                                <div class="rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 36px; height: 36px; background-color: rgba(212, 160, 23, 0.1); color: var(--primary);">
                                    <i class="fas fa-bed"></i>
                                </div>
                                <h6 class="fw-bold text-dark mb-0">Pilih Kamar & Catatan (Opsional)</h6>
                            </div>
                            <div class="pro-card-body">
                                <p class="text-muted small mb-4">Klik satu atau beberapa kamar yang kotor. Form catatan tambahan akan muncul secara otomatis.</p>
                                
                                <div class="checkbox-grid mb-5">
                                    <?php mysqli_data_seek($kamarQuery, 0); // Reset pointer ?>
                                    <?php while($row = mysqli_fetch_assoc($kamarQuery)): ?>
                                        <div>
                                            <input type="checkbox" class="btn-check" name="kamar[]" id="kamar-<?= $row['kamar'] ?>" value="<?= htmlspecialchars($row['kamar']) ?>" autocomplete="off">
                                            <label class="btn-outline-custom" for="kamar-<?= $row['kamar'] ?>"><?= htmlspecialchars($row['kamar']) ?></label>
                                        </div>
                                    <?php endwhile; ?>
                                </div>

                                <div id="catatan-kamar-container" class="bg-light p-4 rounded-3 border border-dashed" style="border-color: #cbd5e1 !important; border-width: 2px !important;">
                                    <div class="text-center text-muted fst-italic" id="catatan-placeholder">
                                        <i class="fas fa-info-circle mb-2 fa-2x opacity-50 d-block"></i>
                                        <small>Pilih kamar di atas untuk menulis catatan tambahan...</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- RIGHT COLUMN: Config -->
                    <div class="col-lg-4 d-flex flex-column gap-4">
                        <div class="pro-card h-100 d-flex flex-column">
                            <div class="pro-card-header d-flex align-items-center">
                                <div class="rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 36px; height: 36px; background-color: rgba(212, 160, 23, 0.1); color: var(--primary);">
                                    <i class="fas fa-calendar-check"></i>
                                </div>
                                <h6 class="fw-bold text-dark mb-0">Pengaturan</h6>
                            </div>
                            <div class="pro-card-body flex-grow-1">
                                <div class="mb-4">
                                    <label class="form-label-custom">TANGGAL PENGECEKAN</label>
                                    <input type="datetime-local" name="tanggal_kamar" class="form-control-custom" value="<?php echo date('Y-m-d\TH:i'); ?>" required>
                                </div>
                            </div>
                            <div class="pro-card-footer mt-auto border-0 pt-0">
                                <button type="submit" name="submit_pelanggaran" class="btn-save">
                                    <i class="fas fa-broom me-2"></i> SIMPAN KEBERSIHAN
                                </button>
                            </div>
                        </div>
                    </div>
                </div> <!-- End Row -->
            </form>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>

<!-- Script JS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function() {
    let selectedSantri = null;

    function escapeHTML(str) {
        if (!str) return '';
        return $('<div>').text(str).html();
    }

    // Init Select2
    $('#jenis_pelanggaran_id').select2({
        theme: "bootstrap-5",
        dropdownParent: $('#jenis_pelanggaran_id').parent(),
        width: '100%',
        placeholder: "Cari jenis pelanggaran..."
    });

    // ==========================================
    // DYNAMIC REKAP BUTTON
    // ==========================================
    $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
        let targetTab = $(e.target).attr("data-bs-target"); // "#individu" or "#kamar"
        let btn = $('#dynamic-rekap-btn');
        let txt = $('#dynamic-rekap-text');
        
        if (targetTab === '#kamar') {
            btn.attr('href', '../../rekap/kebersihan.php');
            txt.text('Rekap Kebersihan');
        } else {
            btn.attr('href', '../../rekap/keterlambatan.php');
            txt.text('Rekap Keterlambatan');
        }
    });

    // ==========================================
    // AUTOCOMPLETE SETUP (WITH IMPROVED UI)
    // ==========================================
    $("#santri-search").autocomplete({
        source: "search_santri.php",
        minLength: 2,
        select: function(event, ui) {
            selectedSantri = ui.item;
            tambahSantri(); 
            $(this).val(''); 
            return false;
        }
    })
    .autocomplete("instance")._renderItem = function(ul, item) {
        return $("<li>")
            .append(`
                <div class="ui-menu-item-wrapper gap-3">
                    <div class="bg-light rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 40px; height: 40px;">
                        <i class="fas fa-user text-secondary"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-bold text-dark mb-1" style="font-size: 0.95rem;">${item.value}</div>
                        <div class="d-flex gap-2">
                            <span class="badge bg-light text-secondary border fw-normal"><i class="fas fa-chalkboard-teacher me-1"></i> ${item.kelas}</span>
                            <span class="badge bg-light text-secondary border fw-normal"><i class="fas fa-bed me-1"></i> ${item.kamar}</span>
                        </div>
                    </div>
                </div>
            `)
            .appendTo(ul);
    };

    // ==========================================
    // TABLE LOGIC
    // ==========================================
    function checkTableState() {
        if ($('#tabel-santri-pelanggar tbody tr').length === 0) {
            $('#table-container').hide();
            $('#btn-clear-all').hide();
            $('#empty-state').removeClass('d-none').addClass('d-flex');
        } else {
            $('#table-container').show();
            $('#btn-clear-all').show();
            $('#empty-state').removeClass('d-flex').addClass('d-none');
        }
    }
    
    window.clearAll = function() {
        if(confirm('Hapus semua santri dari daftar?')) { 
            $('#tabel-santri-pelanggar tbody').empty(); 
            checkTableState(); 
        }
    }
    
    window.checkTableState = checkTableState;
    checkTableState();

    function tambahSantri() {
        if (!selectedSantri) return;
        if ($('#tabel-santri-pelanggar').find('tr[data-id="' + selectedSantri.id + '"]').length > 0) {
            alert('Santri ini sudah ada di daftar.'); return;
        }

        let nama = escapeHTML(selectedSantri.value);
        
        let row = `
            <tr data-id="${selectedSantri.id}">
                <td class="ps-4">
                    <div class="d-flex align-items-center">
                        <div class="bg-light rounded-circle d-flex align-items-center justify-content-center me-3 d-none d-sm-flex" style="width: 40px; height: 40px;">
                            <i class="fas fa-user text-secondary"></i>
                        </div>
                        <div>
                            <div class="fw-bold text-dark" style="font-size: 0.95rem;">${nama}</div>
                            <div class="text-muted small mt-1">
                                <span class="me-2"><i class="fas fa-chalkboard-teacher me-1 opacity-75"></i>Kelas ${selectedSantri.kelas}</span>
                                <span><i class="fas fa-bed me-1 opacity-75"></i>Kamar ${selectedSantri.kamar}</span>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" name="santri_ids[]" value="${selectedSantri.id}">
                </td>
                <td class="text-center align-middle pe-4">
                    <button type="button" class="btn btn-sm btn-light text-danger rounded-circle shadow-sm" style="width: 32px; height: 32px;" onclick="$(this).closest('tr').remove(); checkTableState();" title="Hapus">
                        <i class="fas fa-times"></i>
                    </button>
                </td>
            </tr>
        `;
        $('#tabel-santri-pelanggar tbody').append(row);
        selectedSantri = null;
        checkTableState();
    }

    $("#form-individu").on('submit', function(e) {
        if (!$("#jenis_pelanggaran_id").val()) {
            e.preventDefault();
            alert("Pilih jenis pelanggaran terlebih dahulu.");
            $('#jenis_pelanggaran_id').select2('open');
            return;
        }
        if ($('#tabel-santri-pelanggar tbody tr').length === 0) {
            e.preventDefault();
            alert('Belum ada santri yang ditambahkan.');
            $('#santri-search').focus();
            return;
        }
    });

    // ==========================================
    // LOGIKA CATATAN KAMAR (TAB 2)
    // ==========================================
    const catatanContainer = $('#catatan-kamar-container');
    const placeholderText = `
        <div class="text-center text-muted fst-italic" id="catatan-placeholder">
            <i class="fas fa-info-circle mb-2 fa-2x opacity-50 d-block"></i>
            <small>Pilih kamar di atas untuk menulis catatan tambahan...</small>
        </div>
    `;

    $('input[name="kamar[]"]').on('change', function() {
        const checkbox = $(this);
        const kamar = checkbox.val();
        const kamarId = kamar.replace(/[^a-zA-Z0-9]/g, ''); 

        if (checkbox.is(':checked')) {
            $('#catatan-placeholder').remove();
            const newField = `
                <div class="mb-3 animate__animated animate__fadeIn" id="catatan-wrapper-${kamarId}">
                    <label class="form-label-custom mb-2">
                        Catatan Kamar <span class="text-primary">${kamar}</span>
                    </label>
                    <input type="text" name="catatan[${kamar}]" class="form-control-custom" placeholder="Contoh: Sprei berantakan">
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
    
    $('#form-kamar').submit(function(e) {
        if ($('input[name="kamar[]"]:checked').length === 0) {
            alert('Pilih minimal satu kamar terlebih dahulu.');
            e.preventDefault();
        }
    });
});
</script>