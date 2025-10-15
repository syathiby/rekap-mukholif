<?php 
// 1. Panggil 'Otak' aplikasi dulu
require_once __DIR__ . '/../../init.php';

// 2. Jalankan 'SATPAM' buat ngejaga halaman
guard('pelanggaran_bahasa_input');

// 3. Kalau lolos, baru panggil Tampilan
require_once __DIR__ . '/../../header.php'; 

// Ambil daftar jenis pelanggaran KHUSUS BAGIAN BAHASA
$jenis_pelanggaran_list_result = mysqli_query($conn, "SELECT id, nama_pelanggaran, poin FROM jenis_pelanggaran WHERE bagian = 'Bahasa' ORDER BY nama_pelanggaran ASC");

// Siapkan data pelanggaran untuk JavaScript
$pelanggaran_list_for_js = [];
while ($jp = mysqli_fetch_assoc($jenis_pelanggaran_list_result)) {
    $pelanggaran_list_for_js[] = [
        'id'    => $jp['id'],
        'value' => htmlspecialchars($jp['nama_pelanggaran']),
        'label' => htmlspecialchars($jp['nama_pelanggaran']) . ' (Poin: ' . $jp['poin'] . ')',
    ];
}
// Kembalikan pointer result set ke awal
mysqli_data_seek($jenis_pelanggaran_list_result, 0);
?>

<!-- Butuh jQuery UI untuk autocomplete, jadi kita tambahkan CSS-nya -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.13.2/themes/base/jquery-ui.min.css">
<style>
    .ui-autocomplete {
        max-height: 200px;
        overflow-y: auto;
        overflow-x: hidden;
        z-index: 1050;
    }
    .btn-hapus { font-size: 0.8rem; }
    
    .table-dark th {
        background-color: #e9ecef;
        border-color: #21252994;
        white-space: nowrap;
        vertical-align: middle;
        color: #212529;
    }

    @media (max-width: 767px) {
        h4 { font-size: 1.1rem; }
        .form-label { font-size: 0.9rem; }
        .form-control, .form-select { font-size: 0.9rem; }
        .table th, .table td {
            font-size: 0.85rem;
            padding: 0.6rem 0.5rem;
        }
    }
</style>

<div class="container my-4">
    <div class="card col-xl-10 col-lg-12 mx-auto shadow-sm">
        <!-- ✅ PERUBAHAN FINAL TAMPILAN HEADER (MOBILE & DESKTOP) -->
        <div class="card-header bg-primary text-white">
            <!-- Tampilan untuk Desktop (md ke atas) -->
            <div class="d-none d-md-flex justify-content-between align-items-center">
                <h4 class="mb-0">
                    <i class="fas fa-language me-2"></i> Catat Pelanggaran Bahasa
                </h4>
                <a href="rekap.php" class="btn btn-light btn-sm">
                    Lihat Rekap <i class="fas fa-chart-line ms-1"></i>
                </a>
            </div>

            <!-- Tampilan untuk Mobile (di bawah md) -->
            <div class="d-md-none text-center"> <!-- ✅ tambahin text-center disini -->
                <h4 class="mb-2">
                    <i class="fas fa-language me-2"></i> Catat Pelanggaran Bahasa
                </h4>
                <a href="rekap.php" class="btn btn-light btn-sm w-100 d-flex justify-content-center align-items-center">
                    <span>Lihat Rekap</span>
                    <i class="fas fa-chart-line ms-1"></i>
                </a>
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
                <!-- Step 1: Pilih Pelanggaran & Tanggal -->
                <div class="row g-3 mb-3">
                    <div class="col-md-8">
                        <label for="pelanggaranSearch" class="form-label fw-bold">1. Pilih Jenis Pelanggaran</label>
                        <!-- ✅ FITUR SEARCHABLE DROPDOWN DITERAPKAN -->
                        <input type="text" id="pelanggaranSearch" class="form-control" placeholder="Ketik jenis pelanggaran..." required>
                        <input type="hidden" name="jenis_pelanggaran_id" id="jenis_pelanggaran_id">
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
                        <button class="btn btn-primary" type="button" id="btn-tambah-santri" disabled>
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
                    <button type="submit" name="create_bulk_pelanggaran" class="btn btn-success">
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

<!-- Kirim data PHP ke JavaScript -->
<script>
const jenisPelanggaranData = <?= json_encode($pelanggaran_list_for_js); ?>;
</script>

<script>
$(document).ready(function() {
    let selectedSantri = null;
    let pelanggaranTerpilih = null;

    // Fungsi autocomplete untuk pelanggaran
    $("#pelanggaranSearch").autocomplete({
        source: function(request, response) {
            var matcher = new RegExp($.ui.autocomplete.escapeRegex(request.term), "i");
            var filteredData = $.grep(jenisPelanggaranData, function(item) {
                return matcher.test(item.label);
            });
            response(filteredData);
        },
        minLength: 1,
        select: function(event, ui) {
            pelanggaranTerpilih = ui.item;
            $("#pelanggaranSearch").val(ui.item.value); // Tampilkan nama
            $("#jenis_pelanggaran_id").val(ui.item.id); // Simpan ID
            return false;
        }
    }).autocomplete("instance")._renderItem = function(ul, item) {
        return $("<li>")
            .append("<div>" + item.label + "</div>")
            .appendTo(ul);
    };

    // Script untuk santri search (tidak diubah)
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

    function tambahSantri() {
        if (!selectedSantri) return;

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
    
    $('#btn-tambah-santri').click(tambahSantri);
    
    $('#santri-search').keypress(function(e) {
        if (e.which == 13 && selectedSantri) {
            e.preventDefault();
            tambahSantri();
        }
    });

    $('#tabel-santri-pelanggar').on('click', '.btn-hapus', function() {
        $(this).closest('tr').remove();
    });
    
    function resetInput() {
        $("#santri-search").val('');
        $('#btn-tambah-santri').prop('disabled', true);
        selectedSantri = null;
    }

    $("#form-pelanggaran").on('submit', function(e) {
        if (!$("#jenis_pelanggaran_id").val()) {
            e.preventDefault();
            alert("Jenis pelanggaran belum dipilih!");
            return;
        }
        if ($('#tabel-santri-pelanggar tbody tr').length === 0) {
            e.preventDefault();
            alert('Daftar santri pelanggar tidak boleh kosong!');
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../footer.php'; ?>