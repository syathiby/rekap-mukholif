<?php 
require_once '../../init.php';
guard('reward_input');  
require_once '../../header.php'; 

// Ambil data Reward
$stmt = $conn->prepare("SELECT id, nama_reward, poin_reward FROM jenis_reward ORDER BY nama_reward ASC");
$stmt->execute();
$jenis_reward_result = $stmt->get_result();
?>

<!-- Load Libraries -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.13.2/themes/base/jquery-ui.min.css">

<style>
    /* === VARIABLES === */
    :root {
        --primary: #198754;       
        --primary-dark: #145e3c;
        --step-line: #e9ecef;
        --bg-body: #f4f6f9;
        --text-dark: #2c3e50;
        --text-muted: #6c757d;
    }

    body { background-color: var(--bg-body); }

    /* === MAIN CARD === */
    .card-reward {
        border: none;
        border-radius: 16px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.05);
        background: #fff;
        position: relative;
        overflow: visible !important; /* PENTING: Biar dropdown gak kepotong */
    }

    .card-header-green {
        background: var(--primary);
        color: white;
        padding: 25px 30px;
        border-top-left-radius: 16px;
        border-top-right-radius: 16px;
        position: relative;
    }

    .date-badge {
        background: rgba(255,255,255,0.15);
        backdrop-filter: blur(5px);
        color: #fff;
        padding: 8px 16px;
        border-radius: 50px;
        font-weight: 600;
        font-size: 0.85rem;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        border: 1px solid rgba(255,255,255,0.2);
    }

    /* === VERTICAL STEPPER FIX === */
    .step-container {
        padding: 10px 0 0 10px;
    }

    .step-item {
        display: flex;
        position: relative;
        padding-bottom: 2.5rem;
    }
    
    /* Hapus padding bottom untuk step terakhir biar rapi */
    .step-item:last-child {
        padding-bottom: 0;
    }

    /* Garis Vertikal */
    .step-item:not(:last-child)::before {
        content: '';
        position: absolute;
        left: 15px; 
        top: 40px;
        bottom: 0;
        width: 2px;
        background-color: var(--step-line);
        z-index: 0;
    }

    .step-number {
        flex-shrink: 0;
        width: 32px;
        height: 32px;
        background-color: #d1e7dd; 
        color: var(--primary);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 0.9rem;
        margin-right: 20px;
        z-index: 2;
        position: relative;
        box-shadow: 0 0 0 5px #fff; /* Border putih biar garis ketutup rapi */
    }

    .step-content {
        flex-grow: 1;
        padding-top: 2px;
        min-width: 0; /* Fix overflow di flex item */
    }

    .step-title {
        font-weight: 700;
        color: var(--text-dark);
        font-size: 1.05rem;
        margin-bottom: 4px;
    }

    .step-desc {
        color: var(--text-muted);
        font-size: 0.85rem;
        margin-bottom: 12px;
        line-height: 1.4;
    }

    /* === FORM INPUTS === */
    .form-control-lg-custom {
        padding: 12px 16px;
        border-radius: 12px;
        border: 1px solid #dee2e6;
        font-size: 0.95rem;
        transition: all 0.2s;
        width: 100%;
    }
    .form-control-lg-custom:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 4px rgba(25, 135, 84, 0.1);
    }

    /* === AUTOCOMPLETE DROPDOWN === */
    .ui-autocomplete {
        border-radius: 12px;
        box-shadow: 0 15px 35px rgba(0,0,0,0.1), 0 5px 15px rgba(0,0,0,0.05);
        border: 1px solid #f0f0f0;
        padding: 0;
        max-height: 300px;
        overflow-y: auto;
        font-family: inherit;
        z-index: 9999 !important;
        background: #fff;
    }

    .ui-menu-item-wrapper {
        padding: 12px 16px;
        border-bottom: 1px solid #f8f9fa;
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: 0.2s;
        cursor: pointer;
    }

    .ui-menu-item-wrapper:hover, .ui-state-active {
        background: #f8f9fa !important;
        color: inherit !important;
        border: none;
        margin: 0;
    }

    /* Poin Stars */
    .star-green { color: #198754; font-weight: 700; font-size: 0.85rem; }
    .star-red { color: #e74c3c; font-weight: 700; font-size: 0.85rem; }

    /* === TABLE & EMPTY STATE === */
    .table-custom-wrapper {
        border: 1px solid #f0f0f0;
        border-radius: 12px;
        overflow: hidden;
    }
    
    .table-custom { width: 100%; margin: 0; }
    .table-custom th { background: #fcfcfc; text-transform: uppercase; font-size: 0.75rem; color: #888; padding: 15px; font-weight: 700; border-bottom: 1px solid #f0f0f0; }
    .table-custom td { padding: 15px; vertical-align: middle; border-bottom: 1px solid #f9f9f9; }
    .table-custom tr:last-child td { border-bottom: none; }

    .empty-box {
        background: #fcfcfc;
        border: 2px dashed #dee2e6;
        border-radius: 12px;
        padding: 30px 20px;
        text-align: center;
    }

    /* === BUTTON === */
    .btn-save {
        background-color: var(--primary);
        border: none;
        border-radius: 12px;
        padding: 16px;
        font-weight: 700;
        font-size: 1rem;
        letter-spacing: 0.5px;
        box-shadow: 0 4px 15px rgba(25, 135, 84, 0.3);
        transition: transform 0.2s, box-shadow 0.2s;
        width: 100%;
        margin-top: 20px;
    }
    .btn-save:hover { 
        background-color: var(--primary-dark); 
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(25, 135, 84, 0.4);
    }

    /* Mobile Fix */
    @media (max-width: 768px) {
        .card-header-green { padding: 20px; }
        .step-number { margin-right: 15px; }
        .ui-menu-item-wrapper { flex-direction: column; align-items: flex-start; gap: 5px; }
        
        .mobile-hide { display: none; }
        .table-custom td { font-size: 0.9rem; }
    }
</style>

<div class="content-wrapper" style="padding: 20px;">
    <div class="container-fluid p-0" style="max-width: 650px; margin: 0 auto;">
        
        <div class="card card-reward">
            
            <!-- HEADER -->
            <div class="card-header-green">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="fw-bold mb-1" style="font-size: 1.3rem;"><i class="fas fa-gift me-2"></i>Input Reward</h4>
                        <p class="mb-0 opacity-75 small" style="font-weight: 500;">Apresiasi untuk santri berprestasi</p>
                    </div>
                    <a href="../history/index.php" class="btn btn-sm btn-light text-success fw-bold px-3 shadow-sm" style="border-radius: 8px;">
                        <i class="fas fa-history me-1"></i> Riwayat
                    </a>
                </div>
            </div>

            <div class="card-body p-4">
                
                <!-- Alert -->
                <?php if (isset($_SESSION['message'])): ?>
                    <div class="alert alert-<?php echo $_SESSION['message']['type']; ?> alert-dismissible fade show border-0 shadow-sm mb-4 rounded-3">
                        <div class="d-flex align-items-center">
                            <i class="<?php echo ($_SESSION['message']['type'] == 'success') ? 'fas fa-check-circle' : 'fas fa-exclamation-circle'; ?> me-2"></i>
                            <div><?php echo $_SESSION['message']['text']; ?></div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['message']); ?>
                <?php endif; ?>

                <form action="process.php" method="POST" id="form-reward">
                    <div class="step-container">
                        
                        <!-- STEP 1 -->
                        <div class="step-item">
                            <div class="step-number">1</div>
                            <div class="step-content">
                                <div class="step-title">Pilih Apresiasi</div>
                                <div class="step-desc">Tentukan jenis reward dan tanggal pencatatan.</div>
                                
                                <select name="jenis_reward_id" id="jenis_reward_id" class="form-control mb-2" required>
                                    <option value="">Cari jenis reward...</option>
                                    <?php
                                    if ($jenis_reward_result && $jenis_reward_result->num_rows > 0) {
                                        $jenis_reward_result->data_seek(0); 
                                        while ($row = $jenis_reward_result->fetch_assoc()) {
                                            echo '<option value="' . $row['id'] . '">' . htmlspecialchars($row['nama_reward']) . ' (Kurangi ' . $row['poin_reward'] . ' Poin)</option>';
                                        }
                                    }
                                    ?>
                                </select>
                                
                                <div class="input-group mt-2">
                                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="far fa-calendar-alt"></i></span>
                                    <input type="datetime-local" name="tanggal" class="form-control form-control-lg-custom border-start-0 ps-0" value="<?= date('Y-m-d\TH:i'); ?>" required>
                                </div>
                            </div>
                        </div>

                        <!-- STEP 2 -->
                        <div class="step-item">
                            <div class="step-number">2</div>
                            <div class="step-content">
                                <div class="step-title">Cari Santri</div>
                                <div class="step-desc">Ketik nama santri untuk menambahkan ke daftar.</div>
                                
                                <div class="position-relative">
                                    <i class="fas fa-search position-absolute text-muted" style="top: 14px; left: 15px; z-index: 5;"></i>
                                    <input type="text" id="santri-search" class="form-control form-control-lg-custom" style="padding-left: 45px;" placeholder="Ketik nama santri...">
                                </div>
                            </div>
                        </div>

                        <!-- STEP 3 -->
                        <div class="step-item">
                            <div class="step-number">3</div>
                            <div class="step-content w-100">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div class="step-title mb-0">Daftar Penerima</div>
                                    <button type="button" class="btn btn-sm text-danger fw-bold text-decoration-none" onclick="clearAll()" id="btn-clear-all" style="display:none;">Hapus Semua</button>
                                </div>

                                <!-- Tabel Data -->
                                <div class="table-custom-wrapper mb-0" id="table-container" style="display:none;">
                                    <table class="table table-custom table-borderless mb-0" id="tabel-santri-reward">
                                        <thead>
                                            <tr>
                                                <th>Nama Santri</th>
                                                <th class="text-end mobile-hide">Sisa Poin</th>
                                                <th class="text-center" width="40"></th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>

                                <!-- Empty State -->
                                <div id="empty-state" class="empty-box">
                                    <img src="https://cdn-icons-png.flaticon.com/512/7486/7486754.png" width="50" class="mb-3 opacity-25" style="filter: grayscale(100%);">
                                    <p class="text-muted fw-bold mb-0 small">Belum ada santri dipilih</p>
                                </div>
                            </div>
                        </div>

                    </div> <!-- End Step Container -->

                    <button type="submit" name="add_reward_bulk" class="btn-save">
                        <i class="fas fa-check-circle me-2"></i> SIMPAN DATA
                    </button>

                </form>

            </div>
        </div>
        
    </div>
</div>

<?php require_once '../../footer.php'; ?>

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
    $('#jenis_reward_id').select2({
        theme: "bootstrap-5",
        dropdownParent: $('#jenis_reward_id').parent(),
        width: '100%',
        selectionCssClass: 'py-2 ps-2'
    });

    // ==========================================
    // ✅ AUTOCOMPLETE SETUP
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
        let poin = parseInt(item.poin);
        let starHtml = '';
        
        // Logika Tampilan Dropdown
        if (poin > 0) {
            // Merah (Ada Pelanggaran)
            starHtml = `<span class="star-red"><i class="fas fa-exclamation-circle"></i> ${poin} Poin</span>`;
        } else {
            // Hijau (Bersih)
            starHtml = `<span class="star-green"><i class="fas fa-star"></i> 0 Poin</span>`;
        }

        return $("<li>")
            .append(`
                <div class="ui-menu-item-wrapper">
                    <div>
                        <div class="fw-bold text-dark" style="font-size: 0.9rem;">${item.value}</div>
                        <div class="text-muted small mt-1"><i class="fas fa-id-card me-1"></i>Kls ${item.kelas}</div>
                    </div>
                    <div>${starHtml}</div>
                </div>
            `)
            .appendTo(ul);
    };

    // ==========================================
    // ✅ TABLE LOGIC
    // ==========================================
    function checkTableState() {
        if ($('#tabel-santri-reward tbody tr').length === 0) {
            $('#table-container').hide();
            $('#btn-clear-all').hide();
            $('#empty-state').show();
        } else {
            $('#table-container').show();
            $('#btn-clear-all').show();
            $('#empty-state').hide();
        }
    }
    
    window.clearAll = function() {
        if(confirm('Hapus semua daftar?')) { 
            $('#tabel-santri-reward tbody').empty(); 
            checkTableState(); 
        }
    }

    function tambahSantri() {
        if (!selectedSantri) return;
        if ($('#tabel-santri-reward').find('tr[data-id="' + selectedSantri.id + '"]').length > 0) {
            alert('Santri ini sudah ada di daftar.'); return;
        }

        let nama = escapeHTML(selectedSantri.value);
        let poin = parseInt(selectedSantri.poin);
        
        // Tampilan Poin di Tabel
        let displayPoin = '';
        let mobilePoin = '';

        if (poin > 0) {
             displayPoin = `<span class="badge bg-danger bg-opacity-10 text-danger fw-bold">${poin} Poin (Pelanggaran)</span>`;
             mobilePoin = `<span class="text-danger small fw-bold mt-1 d-block"><i class="fas fa-exclamation-circle"></i> Sisa: ${poin}</span>`;
        } else {
             displayPoin = `<span class="badge bg-success bg-opacity-10 text-success fw-bold">Bersih (0 Poin)</span>`;
             mobilePoin = `<span class="text-success small fw-bold mt-1 d-block"><i class="fas fa-check-circle"></i> Bersih</span>`;
        }

        let row = `
            <tr data-id="${selectedSantri.id}">
                <td>
                    <div class="fw-bold text-dark" style="font-size: 0.95rem;">${nama}</div>
                    <div class="d-md-none">${mobilePoin}</div> <!-- Info poin di HP -->
                    <input type="hidden" name="santri_ids[]" value="${selectedSantri.id}">
                </td>
                <td class="text-end align-middle mobile-hide">${displayPoin}</td>
                <td class="text-center align-middle">
                    <button type="button" class="btn btn-link text-muted hover-danger p-0" onclick="$(this).closest('tr').remove(); checkTableState();">
                        <i class="fas fa-times"></i>
                    </button>
                </td>
            </tr>
        `;
        $('#tabel-santri-reward tbody').append(row);
        selectedSantri = null;
        checkTableState();
    }

    $("#form-reward").on('submit', function(e) {
        if (!$("#jenis_reward_id").val()) { e.preventDefault(); alert("Pilih jenis reward dulu!"); return; }
        if ($('#tabel-santri-reward tbody tr').length === 0) { e.preventDefault(); alert("Belum ada santri dipilih."); $('#santri-search').focus(); return; }
    });
});
</script>