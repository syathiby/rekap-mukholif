<?php 
// 1. Panggil 'Otak' aplikasi dulu
require_once __DIR__ . '/../../init.php';

// 2. Jalankan 'SATPAM' buat ngejaga halaman
guard('pelanggaran_pengabdian_input');   

// 3. Kalau lolos, baru panggil Tampilan
require_once __DIR__ . '/../../header.php';
?>

<?php

// Ambil semua jenis pelanggaran 'Pengabdian' KECUALI id 3
$jp_individu_list = mysqli_query($conn, "SELECT id, nama_pelanggaran, poin FROM jenis_pelanggaran WHERE bagian = 'Pengabdian' AND id != 3 ORDER BY nama_pelanggaran ASC");

// Ambil daftar kamar unik untuk checkbox
$kamarQuery = mysqli_query($conn, "
    SELECT DISTINCT kamar FROM santri
    WHERE kamar IS NOT NULL AND kamar != ''
    ORDER BY
        REGEXP_REPLACE(kamar, '[0-9]', '') ASC,
        CAST(REGEXP_REPLACE(kamar, '[^0-9]', '') AS UNSIGNED) ASC
");
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.13.2/themes/base/jquery-ui.min.css">

<style>
    /* Style biar makin keren & responsif */
    .card-header-tabs {
        margin-bottom: -0.8rem; /* Biar nempel sama card body */
    }
    .nav-tabs .nav-link {
        cursor: pointer;
    }
    .nav-tabs .nav-link.active {
        background-color: #fff;
        border-color: #dee2e6 #dee2e6 #fff;
        color: #664d03; /* Warna teks kuning gelap biar kontras */
        font-weight: 600;
    }
    .ui-autocomplete {
        z-index: 1050;
        max-height: 200px;
        overflow-y: auto;
    }
    .table-dark th {
        background-color: #e9ecef;
        border-color: #dee2e6;
        color: #212529;
        white-space: nowrap;
    }
    .checkbox-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
        gap: 10px;
    }
    .checkbox-grid .btn-check:checked + .btn-outline-warning {
        background-color: #ffc107;
        color: #000;
    }
    
    @media (max-width: 767px) {
        h4, h5 { font-size: 1.1rem; }
        .form-label, .form-control, .form-select, .table th, .table td { font-size: 0.9rem; }
    }
</style>

<div class="container my-4">
    <div class="card col-xl-10 col-lg-12 mx-auto shadow-sm">
        
        <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
             <h4 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i> Catat Pelanggaran Pengabdian</h4>
        </div>
        <div class="card-header bg-light border-bottom">
            <ul class="nav nav-tabs card-header-tabs" id="pelanggaranTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="individu-tab" data-bs-toggle="tab" data-bs-target="#individu" type="button" role="tab" aria-controls="individu" aria-selected="true">
                        <i class="fas fa-user-clock me-1"></i> Individu
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="kamar-tab" data-bs-toggle="tab" data-bs-target="#kamar" type="button" role="tab" aria-controls="kamar" aria-selected="false">
                        <i class="fas fa-broom me-1"></i> Kebersihan Kamar
                    </button>
                </li>
            </ul>
        </div>

        <div class="card-body p-3 p-md-4">
            
            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['message']['type']; ?> alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['message']['text']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['message']); ?>
            <?php endif; ?>

            <div class="tab-content" id="pelanggaranTabContent">
                
                <div class="tab-pane fade show active" id="individu" role="tabpanel" aria-labelledby="individu-tab">
                    <form action="process.php" method="POST">
                        <input type="hidden" name="tipe_pelanggaran" value="individu">
                        <div class="row g-3 mb-3">
                            
                            <div class="col-md-7">
                                <label for="jenis_pelanggaran_id" class="form-label fw-bold">1. Pilih Jenis Pelanggaran</label>
                                <select name="jenis_pelanggaran_id" id="jenis_pelanggaran_id" class="form-select" required>
                                    <option value="" disabled selected>-- Pilih salah satu --</option>
                                    <?php 
                                    mysqli_data_seek($jp_individu_list, 0); 
                                    while ($jp = mysqli_fetch_assoc($jp_individu_list)): ?>
                                        <option value="<?= $jp['id'] ?>">
                                            <?= htmlspecialchars($jp['nama_pelanggaran']) ?> (<?= $jp['poin'] ?> Poin)
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <label for="tanggal_individu" class="form-label fw-bold">2. Tentukan Tanggal</label>
                                <input type="datetime-local" name="tanggal_individu" id="tanggal_individu" class="form-control" value="<?php echo date('Y-m-d\TH:i'); ?>" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="santri-search" class="form-label fw-bold">3. Cari dan Tambahkan Santri</label>
                            <div class="input-group">
                                    <input type="text" id="santri-search" class="form-control" placeholder="Ketik nama santri...">
                                    <button class="btn btn-primary" type="button" id="btn-tambah-santri" disabled>
                                        <i class="fas fa-plus"></i>
                                        <span class="d-none d-sm-inline"> Tambah</span>
                                    </button>
                            </div>
                        </div>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped" id="tabel-santri-pelanggar">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Nama Santri</th>
                                        <th>Kelas</th>
                                        <th>Kamar</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                        <hr>
                        <div class="d-grid">
                            <button type="submit" name="submit_pelanggaran" class="btn btn-success">
                                <i class="fas fa-save me-1"></i> Simpan Pelanggaran Individu
                            </button>
                        </div>
                    </form>
                </div>

                <div class="tab-pane fade" id="kamar" role="tabpanel" aria-labelledby="kamar-tab">
                    <form action="process.php" method="POST">
                        <input type="hidden" name="tipe_pelanggaran" value="kamar">
                        <div class="mb-3">
                            <label class="form-label fw-bold">1. Pilih Kamar yang Melanggar (Bisa Pilih Banyak)</label>
                            <div class="checkbox-grid">
                                <?php while($row = mysqli_fetch_assoc($kamarQuery)): ?>
                                    <div>
                                        <input type="checkbox" class="btn-check" name="kamar[]" id="kamar-<?= $row['kamar'] ?>" value="<?= htmlspecialchars($row['kamar']) ?>" autocomplete="off">
                                        <label class="btn btn-outline-warning w-100" for="kamar-<?= $row['kamar'] ?>"><?= htmlspecialchars($row['kamar']) ?></label>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        </div>
                        
                        <!-- ======================================================= -->
                        <!-- ✅ PERUBAHAN DI SINI: Kolom catatan sekarang dinamis -->
                        <!-- ======================================================= -->
                        <div class="mb-3">
                            <label class="form-label fw-bold">2. Tulis Catatan (Opsional)</label>
                            <div id="catatan-kamar-container">
                                <p class="text-muted fst-italic">Pilih kamar terlebih dahulu</p>
                            </div>
                        </div>
                        
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label for="tanggal_kamar" class="form-label fw-bold">3. Tanggal Pelanggaran</label>
                                <input type="datetime-local" name="tanggal_kamar" id="tanggal_kamar" class="form-control" value="<?php echo date('Y-m-d\TH:i'); ?>" required>
                            </div>
                        </div>
                        <hr>
                        <div class="d-grid">
                            <button type="submit" name="submit_pelanggaran" class="btn btn-success">
                                <i class="fas fa-save me-1"></i> Simpan Pelanggaran Kamar
                            </button>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../footer.php'; ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>

<script>
$(document).ready(function() {
    let selectedSantri = null;

    // Fungsi Autocomplete untuk cari santri
    $("#santri-search").autocomplete({
        source: "search_santri.php",
        minLength: 2,
        select: function(event, ui) {
            selectedSantri = ui.item;
            $('#btn-tambah-santri').prop('disabled', false);
        }
    });

    // Fungsi untuk nambahin santri ke tabel
    function tambahSantri() {
        if (!selectedSantri) return;

        if ($('#tabel-santri-pelanggar tr[data-id="' + selectedSantri.id + '"]').length > 0) {
            alert('Santri sudah ada dalam daftar.');
            resetInput();
            return;
        }

        let barisBaru = `<tr data-id="${selectedSantri.id}">
            <td>${selectedSantri.value}<input type="hidden" name="santri_ids[]" value="${selectedSantri.id}"></td>
            <td>${selectedSantri.kelas}</td>
            <td>${selectedSantri.kamar}</td>
            <td><button type="button" class="btn btn-danger btn-sm btn-hapus"><i class="fas fa-trash"></i></button></td>
        </tr>`;
        $('#tabel-santri-pelanggar tbody').append(barisBaru);
        resetInput();
    }
    
    // Trigger nambah santri
    $('#btn-tambah-santri').click(tambahSantri);
    $('#santri-search').keypress(function(e) {
        if (e.which == 13 && selectedSantri) { // Enter key
            e.preventDefault();
            tambahSantri();
        }
    });

    // Hapus santri dari tabel
    $('#tabel-santri-pelanggar').on('click', '.btn-hapus', function() {
        $(this).closest('tr').remove();
    });
    
    // Fungsi buat reset input pencarian
    function resetInput() {
        $("#santri-search").val('');
        $('#btn-tambah-santri').prop('disabled', true);
        selectedSantri = null;
        $("#santri-search").focus();
    }

    // =============================================================
    // ✅ LOGIKA BARU UNTUK CATATAN KAMAR DINAMIS
    // =============================================================
    const catatanContainer = $('#catatan-kamar-container');
    const placeholderText = '<p class="text-muted fst-italic" id="catatan-placeholder">Pilih kamar terlebih dahulu</p>';

    $('input[name="kamar[]"]').on('change', function() {
        const checkbox = $(this);
        const kamar = checkbox.val();
        // Bikin ID yang aman buat elemen HTML
        const kamarId = kamar.replace(/[^a-zA-Z0-9]/g, '');

        if (checkbox.is(':checked')) {
            // Kalau ada placeholder, hapus dulu
            $('#catatan-placeholder').remove();

            // Bikin elemen textarea baru
            const newCatatanField = `
                <div class="mb-2" id="catatan-wrapper-${kamarId}">
                    <label for="catatan-${kamarId}" class="form-label mb-1">
                        <i class="fas fa-pencil-alt text-warning me-1"></i>
                        Catatan untuk Kamar <strong>${kamar}</strong>
                    </label>
                    <textarea name="catatan[${kamar}]" id="catatan-${kamarId}" class="form-control" rows="1" placeholder="Tuliskan Alasan"></textarea>
                </div>
            `;
            catatanContainer.append(newCatatanField);
        } else {
            // Kalau checkbox-nya di-uncheck, hapus textarea-nya
            $(`#catatan-wrapper-${kamarId}`).remove();

            // Kalau semua textarea udah kehapus, munculin lagi placeholder
            if (catatanContainer.children().length === 0) {
                catatanContainer.html(placeholderText);
            }
        }
    });

    // Validasi form kamar biar nggak kosong
    $('#kamar form').submit(function(e) {
        if ($('input[name="kamar[]"]:checked').length === 0) {
            alert('Pilih minimal satu kamar yang melanggar.');
            e.preventDefault();
        }
    });
});
</script>