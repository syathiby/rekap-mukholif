<?php 
require_once __DIR__ . '/../../header.php';
// Ganti guard ke izin Tahfidz
guard('pelanggaran_tahfidz_input'); 
?>

<?php
// Ambil daftar jenis pelanggaran KHUSUS BAGIAN TAHFIDZ
$jenis_pelanggaran_list = mysqli_query($conn, "SELECT id, nama_pelanggaran, poin FROM jenis_pelanggaran WHERE bagian = 'Tahfidz' ORDER BY nama_pelanggaran ASC");
?>

<!-- Butuh jQuery UI untuk autocomplete, jadi kita tambahkan CSS-nya -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.13.2/themes/base/jquery-ui.min.css">
<style>
    .ui-autocomplete {
        max-height: 200px;
        overflow-y: auto;
        overflow-x: hidden;
        z-index: 1050; /* Biar muncul di atas elemen lain */
    }
    .btn-hapus { font-size: 0.8rem; }
    
    /* Perbaikan Header Tabel (disamakan dengan rekap.php) */
    .table-dark th {
        background-color: #e9ecef;
        border-color: #21252994;
        white-space: nowrap;
        vertical-align: middle;
        color: #212529; /* INI DIA TAMBAHANNYA */
    }

    /* Penyesuaian untuk Tampilan Mobile */
    @media (max-width: 767px) {
        h4 {
            font-size: 1.1rem; /* Kecilin judul utama card */
        }
        .form-label {
            font-size: 0.9rem; /* Kecilin label form */
        }
        .form-control, .form-select {
            font-size: 0.9rem; /* Kecilin tulisan di dalam input */
        }
        .table th, .table td {
            font-size: 0.85rem; /* Kecilin font tabel */
            padding: 0.6rem 0.5rem;
        }
    }
</style>

<div class="container my-4">
    <div class="card col-xl-10 col-lg-12 mx-auto shadow-sm">
        <!-- ======================================================= -->
        <!-- === REVISI FINAL TEMA WARNA & IKON === -->
        <!-- ======================================================= -->
        <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h4 class="mb-0"><i class="fas fa-book-reader me-2"></i> Catat Pelanggaran Tahfidz</h4>
            <a href="rekap.php" class="btn btn-sm btn-light">Lihat Rekap <i class="fas fa-chart-line ms-1"></i></a>
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
                <!-- Step 1: Pilih Pelanggaran & Tanggal -->
                <div class="row g-3 mb-3">
                    <div class="col-md-8">
                        <label for="jenis_pelanggaran_id" class="form-label fw-bold">1. Pilih Jenis Pelanggaran</label>
                        <select name="jenis_pelanggaran_id" id="jenis_pelanggaran_id" class="form-select" required>
                            <option value="" disabled selected>-- Pilih Pelanggaran Tahfidz --</option>
                            <?php while ($jp = mysqli_fetch_assoc($jenis_pelanggaran_list)): ?>
                                <option value="<?php echo $jp['id']; ?>" data-poin="<?php echo $jp['poin']; ?>">
                                    <?php echo htmlspecialchars($jp['nama_pelanggaran']) . " (" . $jp['poin'] . " Poin)"; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="tanggal" class="form-label fw-bold">2. Tentukan Tanggal</label>
                        <input type="datetime-local" name="tanggal" id="tanggal" class="form-control" value="<?php echo date('Y-m-d\TH:i'); ?>" required>
                    </div>
                </div>
                <hr>

                <!-- Step 2: Tambah Santri -->
                <div class="mb-3">
                    <label for="santri-search" class="form-label fw-bold">3. Cari dan Tambahkan Santri</label>
                    <div class="input-group">
                        <input type="text" id="santri-search" class="form-control" placeholder="Ketik nama santri untuk mencari...">
                        <button class="btn btn-danger" type="button" id="btn-tambah-santri" disabled>
                            <i class="fas fa-plus"></i><span class="d-none d-sm-inline"> Tambah</span>
                        </button>
                    </div>
                </div>

                <!-- Step 3: Daftar Santri yang Melanggar -->
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
                        <tbody>
                            <!-- Santri yang ditambahkan akan muncul di sini -->
                        </tbody>
                    </table>
                </div>

                <hr>
                <div class="d-grid">
                    <button type="submit" name="create_bulk_pelanggaran" class="btn btn-danger">
                        <i class="fas fa-save me-1"></i> Simpan Semua Pelanggaran
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- jQuery & jQuery UI untuk autocomplete -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>

<script>
// Script Javascript tidak berubah, sudah bagus
$(document).ready(function() {
    let selectedSantri = null;

    // Inisialisasi Autocomplete
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

    // Fungsi untuk menambah santri ke tabel
    function tambahSantri() {
        if (!selectedSantri) return;

        // Cek duplikat
        if ($('#tabel-santri-pelanggar').find('tr[data-id="' + selectedSantri.id + '"]').length > 0) {
            alert('Santri sudah ada dalam daftar.');
            resetInput();
            return;
        }

        let barisBaru = `
            <tr data-id="${selectedSantri.id}">
                <td>
                    ${selectedSantri.value}
                    <input type="hidden" name="santri_ids[]" value="${selectedSantri.id}">
                </td>
                <td>${selectedSantri.kelas}</td>
                <td>${selectedSantri.kamar}</td>
                <td>
                    <button type="button" class="btn btn-danger btn-sm btn-hapus">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `;
        $('#tabel-santri-pelanggar tbody').append(barisBaru);
        resetInput();
    }
    
    // Klik tombol tambah
    $('#btn-tambah-santri').click(tambahSantri);
    
    // Enter di search box juga menambah
    $('#santri-search').keypress(function(e) {
        if (e.which == 13 && selectedSantri) {
            e.preventDefault();
            tambahSantri();
        }
    });

    // Hapus santri dari tabel
    $('#tabel-santri-pelanggar').on('click', '.btn-hapus', function() {
        $(this).closest('tr').remove();
    });
    
    // Reset input setelah ditambah
    function resetInput() {
        $("#santri-search").val('');
        $('#btn-tambah-santri').prop('disabled', true);
        selectedSantri = null;
    }
});
</script>

<?php require_once __DIR__ . '/../../footer.php'; ?>