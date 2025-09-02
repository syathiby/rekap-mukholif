<?php 
require_once __DIR__ . '/../../header.php';
guard('pelanggaran_pengabdian_input'); 
?>

<?php

// Ambil jenis pelanggaran individu (Telat Sholat & KBM)
$jp_individu_list = mysqli_query($conn, "SELECT id, nama_pelanggaran, poin FROM jenis_pelanggaran WHERE bagian = 'Pengabdian' AND (nama_pelanggaran LIKE '%Telat Sholat%' OR nama_pelanggaran LIKE '%Telat KBM%') ORDER BY nama_pelanggaran ASC");

// Ambil daftar kamar unik untuk checkbox
$kamarQuery = mysqli_query($conn, "
    SELECT DISTINCT kamar FROM santri
    WHERE kamar IS NOT NULL AND kamar != ''
    ORDER BY
        REGEXP_REPLACE(kamar, '[0-9]', '') ASC,
        CAST(REGEXP_REPLACE(kamar, '[^0-9]', '') AS UNSIGNED) ASC
");
?>

<!-- Include JQuery UI CSS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.13.2/themes/base/jquery-ui.min.css">

<style>
    /* Style biar makin keren & responsif */
    .card-header-tabs {
        margin-bottom: -0.8rem; /* Biar nempel sama card body */
    }
    .nav-tabs .nav-link {
        cursor: pointer;
    }
    /* === Penyesuaian Warna Tema Kuning === */
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
    /* === STYLE BARU UNTUK PILIHAN BOX PELANGGARAN (WARNA BIRU) === */
    .radio-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 10px;
    }
    .radio-grid .btn-check:checked + .btn-outline-primary {
        background-color: #0d6efd;
        color: #fff;
        border-color: #0d6efd;
    }
    .radio-grid .btn {
        text-align: left;
        padding: 0.5rem 0.75rem;
        font-size: 0.9rem;
        line-height: 1.5;
        display: flex;
        justify-content: space-between;
        align-items: center;
        height: 100%;
    }
    .radio-grid .btn .poin {
        font-weight: bold;
        background-color: rgba(0,0,0,0.05);
        padding: 2px 8px;
        border-radius: 5px;
        white-space: nowrap;
        margin-left: 8px;
    }
    .radio-grid .btn-check:checked + .btn-outline-primary .poin {
         background-color: rgba(255,255,255,0.25);
    }
    @media (max-width: 767px) {
        h4, h5 { font-size: 1.1rem; }
        .form-label, .form-control, .form-select, .table th, .table td { font-size: 0.9rem; }
    }
</style>

<div class="container my-4">
    <div class="card col-xl-10 col-lg-12 mx-auto shadow-sm">
        
        <!-- Header dengan Navigasi Tab -->
        <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
             <h4 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i> Catat Pelanggaran Pengabdian</h4>
        </div>
        <div class="card-header bg-light border-bottom">
            <ul class="nav nav-tabs card-header-tabs" id="pelanggaranTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="individu-tab" data-bs-toggle="tab" data-bs-target="#individu" type="button" role="tab" aria-controls="individu" aria-selected="true">
                        <i class="fas fa-user-clock me-1"></i> Individu (Telat Sholat/KBM)
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
                
                <!-- TAB 1: FORM PELANGGARAN INDIVIDU -->
                <div class="tab-pane fade show active" id="individu" role="tabpanel" aria-labelledby="individu-tab">
                    <form action="process.php" method="POST">
                        <input type="hidden" name="tipe_pelanggaran" value="individu">
                        <div class="row g-3 mb-3">
                            <div class="col-md-8">
                                <label class="form-label fw-bold">1. Pilih Jenis Pelanggaran</label>
                                <div class="radio-grid">
                                    <?php
                                    $is_first_radio = true;
                                    while ($jp = mysqli_fetch_assoc($jp_individu_list)):
                                    ?>
                                        <div>
                                            <input type="radio" class="btn-check" name="jenis_pelanggaran_id" id="jp-<?= $jp['id'] ?>" value="<?= $jp['id'] ?>" autocomplete="off" <?= $is_first_radio ? 'required' : '' ?>>
                                            <label class="btn btn-outline-primary w-100" for="jp-<?= $jp['id'] ?>">
                                                <span><?= htmlspecialchars($jp['nama_pelanggaran']) ?></span>
                                                <span class="poin"><?= $jp['poin'] ?> Poin</span>
                                            </label>
                                        </div>
                                    <?php
                                    $is_first_radio = false;
                                    endwhile;
                                    ?>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label for="tanggal_individu" class="form-label fw-bold">2. Tentukan Tanggal</label>
                                <input type="datetime-local" name="tanggal_individu" id="tanggal_individu" class="form-control" value="<?php echo date('Y-m-d\TH:i'); ?>" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="santri-search" class="form-label fw-bold">3. Cari dan Tambahkan Santri</label>
                            <div class="input-group">
                                    <input type="text" id="santri-search" class="form-control" placeholder="Ketik nama santri...">
                                    <!-- ✅ FIX: Tombol diubah jadi ikon di mobile -->
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

                <!-- TAB 2: FORM PELANGGARAN KEBERSIHAN KAMAR -->
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
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label for="catatan" class="form-label">2. Catatan (Opsional)</label>
                                <textarea name="catatan" id="catatan" class="form-control" rows="1" placeholder="Cth: Sampah tidak dibuang, kasur berantakan."></textarea>
                            </div>
                            <div class="col-md-4">
                                <label for="tanggal_kamar" class="form-label fw-bold">3. Tanggal</label>
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

<!-- JQuery & JQuery UI Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>

<script>
$(document).ready(function() {
    let selectedSantri = null;

    // Fungsi Autocomplete untuk cari santri
    $("#santri-search").autocomplete({
        source: "search_santri.php", // Pastikan file ini ada dan berfungsi
        minLength: 2,
        select: function(event, ui) {
            selectedSantri = ui.item;
            $('#btn-tambah-santri').prop('disabled', false);
        }
    });

    // Fungsi untuk nambahin santri ke tabel
    function tambahSantri() {
        if (!selectedSantri) return;

        // Cek duplikasi
        if ($('#tabel-santri-pelanggar tr[data-id="' + selectedSantri.id + '"]').length > 0) {
            alert('Santri sudah ada dalam daftar.');
            resetInput();
            return;
        }

        // Bikin baris baru
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

    // Validasi form kamar biar nggak kosong
    $('#kamar form').submit(function(e) {
        if ($('input[name="kamar[]"]:checked').length === 0) {
            alert('Pilih minimal satu kamar yang melanggar.');
            e.preventDefault(); // Mencegah form dikirim
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../footer.php'; ?>