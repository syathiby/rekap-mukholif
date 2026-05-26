<?php 
require_once __DIR__ . '/../../bootstrap/init.php';
guard('reward_input');  
require_once __DIR__ . '/../../layouts/header.php'; 

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
    :root {
        --primary: #198754;       
        --primary-dark: #145e3c;
        --bg-body: #f8fafc;
        --text-dark: #1e293b;
        --text-muted: #64748b;
        --card-border: rgba(0,0,0,0.05);
    }

    body { background-color: var(--bg-body); font-family: 'Inter', sans-serif; }

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
        box-shadow: 0 0 0 4px rgba(25, 135, 84, 0.15);
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
        margin-top: 8px; /* Jarak antara input dan dropdown */
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

    /* Poin Stars */
    .star-green { color: #10b981; font-weight: 600; font-size: 0.85rem; background: #ecfdf5; padding: 4px 8px; border-radius: 6px; }
    .star-red { color: #ef4444; font-weight: 600; font-size: 0.85rem; background: #fef2f2; padding: 4px 8px; border-radius: 6px; }

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

    /* === BUTTONS === */
    .btn-save {
        background: linear-gradient(135deg, #10b981, #059669);
        color: white;
        border: none;
        border-radius: 0.75rem;
        padding: 1rem;
        font-weight: 700;
        font-size: 1.05rem;
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        transition: all 0.3s;
        width: 100%;
    }
    .btn-save:hover {
        background: linear-gradient(135deg, #059669, #047857);
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(16, 185, 129, 0.4);
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
        box-shadow: 0 0 0 4px rgba(25, 135, 84, 0.15) !important;
    }

</style>

<div class="container-fluid py-4 px-4">
    <!-- Header Page -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h3 class="fw-bolder text-dark mb-1"><i class="fas fa-gift text-success me-2"></i>Input Reward Santri</h3>
            <p class="text-muted mb-0">Catat pemberian apresiasi untuk santri berprestasi.</p>
        </div>
        <a href="../history/index.php" class="btn-back shadow-sm text-decoration-none">
            <i class="fas fa-history me-1"></i> Riwayat Reward
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

    <form action="process.php" method="POST" id="form-reward">
        <div class="row g-4">
            <!-- LEFT COLUMN: Config & Search -->
            <div class="col-lg-4 d-flex flex-column gap-4">
                
                <!-- CARD 1: PENGATURAN -->
                <div class="pro-card">
                    <div class="pro-card-header d-flex align-items-center">
                        <div class="bg-success bg-opacity-10 text-success rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 36px; height: 36px;">
                            <i class="fas fa-cog"></i>
                        </div>
                        <h6 class="fw-bold text-dark mb-0">1. Pengaturan Reward</h6>
                    </div>
                    <div class="pro-card-body">
                        <div class="mb-4">
                            <label class="form-label-custom">JENIS REWARD</label>
                            <select name="jenis_reward_id" id="jenis_reward_id" class="form-select" required>
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
                        </div>
                        
                        <div>
                            <label class="form-label-custom">TANGGAL PENCATATAN</label>
                            <input type="datetime-local" name="tanggal" class="form-control-custom" value="<?= date('Y-m-d\TH:i'); ?>" required>
                        </div>
                    </div>
                </div>

                <!-- CARD 2: CARI SANTRI -->
                <div class="pro-card">
                    <div class="pro-card-header d-flex align-items-center">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 36px; height: 36px;">
                            <i class="fas fa-search"></i>
                        </div>
                        <h6 class="fw-bold text-dark mb-0">2. Cari Santri</h6>
                    </div>
                    <div class="pro-card-body">
                        <p class="text-muted small mb-3">Ketik nama santri untuk menambahkan ke dalam daftar penerima reward.</p>
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
                            <div class="bg-info bg-opacity-10 text-info rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 36px; height: 36px;">
                                <i class="fas fa-users"></i>
                            </div>
                            <h6 class="fw-bold text-dark mb-0">3. Daftar Penerima Reward</h6>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-danger fw-bold rounded-pill px-3 py-1" onclick="clearAll()" id="btn-clear-all" style="display:none; font-size: 0.8rem;">
                            <i class="fas fa-trash-alt me-1"></i> Hapus Semua
                        </button>
                    </div>
                    
                    <div class="pro-card-body d-flex flex-column flex-grow-1 p-4" style="min-height: 400px;">
                        
                        <!-- Tabel Data -->
                        <div class="table-responsive-custom mb-0" id="table-container" style="display:none; flex-grow: 1;">
                            <table class="table table-borderless m-0 align-middle" id="tabel-santri-reward">
                                <thead>
                                    <tr>
                                        <th class="ps-4">NAMA SANTRI</th>
                                        <th class="text-end d-none d-md-table-cell">STATUS POIN</th>
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
                            <p class="text-muted small mb-0" style="max-width: 250px;">Gunakan kolom pencarian di sebelah kiri untuk memilih santri penerima reward.</p>
                        </div>

                    </div>

                    <div class="pro-card-footer mt-auto">
                        <button type="submit" name="add_reward_bulk" class="btn-save">
                            <i class="fas fa-check-circle me-2"></i> SIMPAN REWARD SEKARANG
                        </button>
                    </div>
                </div>
            </div>

        </div> <!-- End Row -->
    </form>
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
    $('#jenis_reward_id').select2({
        theme: "bootstrap-5",
        dropdownParent: $('#jenis_reward_id').parent(),
        width: '100%',
        placeholder: "Cari jenis reward..."
    });

    // ==========================================
    // AUTOCOMPLETE SETUP
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
        
        if (poin > 0) {
            starHtml = `<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1"><i class="fas fa-exclamation-circle me-1"></i> ${poin} Poin</span>`;
        } else {
            starHtml = `<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1"><i class="fas fa-star text-warning me-1"></i> Bersih</span>`;
        }

        return $("<li>")
            .append(`
                <div class="ui-menu-item-wrapper gap-3">
                    <div class="bg-light rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 40px; height: 40px;">
                        <i class="fas fa-user text-secondary"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-bold text-dark mb-1" style="font-size: 0.95rem;">${item.value}</div>
                        <div class="d-flex gap-2">
                            <span class="badge bg-light text-secondary border fw-normal"><i class="fas fa-chalkboard-teacher me-1"></i> Kelas ${item.kelas}</span>
                            <span class="badge bg-light text-secondary border fw-normal"><i class="fas fa-bed me-1"></i> Kamar ${item.kamar}</span>
                        </div>
                    </div>
                    <div class="flex-shrink-0">${starHtml}</div>
                </div>
            `)
            .appendTo(ul);
    };

    // ==========================================
    // TABLE LOGIC
    // ==========================================
    function checkTableState() {
        if ($('#tabel-santri-reward tbody tr').length === 0) {
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
        Swal.fire({
            title: 'Hapus Semua?',
            text: 'Hapus semua daftar penerima?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Hapus'
        }).then((result) => {
            if (result.isConfirmed) {
                $('#tabel-santri-reward tbody').empty(); 
                checkTableState(); 
            }
        });
    }

    function tambahSantri() {
        if (!selectedSantri) return;
        if ($('#tabel-santri-reward').find('tr[data-id="' + selectedSantri.id + '"]').length > 0) {
            showAlert('Santri ini sudah ada di daftar.', 'warning'); return;
        }

        let nama = escapeHTML(selectedSantri.value);
        let poin = parseInt(selectedSantri.poin);
        
        let displayPoin = '';
        let mobilePoin = '';

        if (poin > 0) {
             displayPoin = `<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-3 py-2 rounded-pill"><i class="fas fa-exclamation-circle me-1"></i> ${poin} Poin</span>`;
             mobilePoin = `<span class="text-danger small fw-bold mt-2 d-block d-md-none"><i class="fas fa-exclamation-circle"></i> ${poin} Poin</span>`;
        } else {
             displayPoin = `<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-3 py-2 rounded-pill"><i class="fas fa-star text-warning me-1"></i> Bersih (0)</span>`;
             mobilePoin = `<span class="text-success small fw-bold mt-2 d-block d-md-none"><i class="fas fa-star text-warning"></i> Bersih</span>`;
        }

        let row = `
            <tr data-id="${selectedSantri.id}">
                <td class="ps-4">
                    <div class="d-flex align-items-center">
                        <div class="bg-light rounded-circle d-flex align-items-center justify-content-center me-3 d-none d-sm-flex" style="width: 40px; height: 40px;">
                            <i class="fas fa-user text-secondary"></i>
                        </div>
                        <div>
                            <div class="fw-bold text-dark" style="font-size: 0.95rem;">${nama}</div>
                            <div class="text-muted small mt-1"><i class="fas fa-id-card me-1 opacity-75"></i>Kelas ${selectedSantri.kelas}</div>
                            ${mobilePoin}
                        </div>
                    </div>
                    <input type="hidden" name="santri_ids[]" value="${selectedSantri.id}">
                </td>
                <td class="text-end align-middle d-none d-md-table-cell">${displayPoin}</td>
                <td class="text-center align-middle pe-4">
                    <button type="button" class="btn btn-sm btn-light text-danger rounded-circle shadow-sm" style="width: 32px; height: 32px;" onclick="$(this).closest('tr').remove(); checkTableState();" title="Hapus">
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
        if (!$("#jenis_reward_id").val()) { e.preventDefault(); showAlert("Pilih jenis reward terlebih dahulu!", "warning"); return; }
        if ($('#tabel-santri-reward tbody tr').length === 0) { e.preventDefault(); showAlert("Daftar santri masih kosong. Tambahkan minimal satu santri.", "warning"); $('#santri-search').focus(); return; }
    });
});
</script>