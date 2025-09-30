<?php 
require_once __DIR__ . '/../../header.php';
guard('pelanggaran_kesantrian_input'); 

$bagian = 'Kesantrian';
$stmt = $conn->prepare("
    SELECT id, nama_pelanggaran, poin 
    FROM jenis_pelanggaran 
    WHERE bagian = ? 
    ORDER BY nama_pelanggaran ASC
");
$stmt->bind_param("s", $bagian);
$stmt->execute();
$jenis_pelanggaran_result = $stmt->get_result();

// Siapkan data pelanggaran untuk JavaScript
$pelanggaran_list_for_js = [];
while ($jp = $jenis_pelanggaran_result->fetch_assoc()) {
    $pelanggaran_list_for_js[] = [
        'id'    => $jp['id'],
        'value' => htmlspecialchars($jp['nama_pelanggaran']),
        'label' => htmlspecialchars($jp['nama_pelanggaran']) . ' (Poin: ' . $jp['poin'] . ')',
    ];
}
// Kembalikan pointer result set ke awal untuk jaga-jaga jika masih dibutuhkan
$jenis_pelanggaran_result->data_seek(0);
?>

<!-- CSS untuk jQuery UI Autocomplete -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.css">
<style>
    .ui-autocomplete { 
        z-index: 1050;
        max-height: 200px;
        overflow-y: auto;
        overflow-x: hidden;
    }
    .ui-menu-item-wrapper {
        font-size: 0.9rem;
    }
    .form-top-section { 
        background-color: #f8f9fa; 
        padding: 1.5rem; 
        border: 1px solid #dee2e6; 
        border-radius: 0.5rem; 
        margin-bottom: 1.5rem; 
    }
    @media (max-width: 576px) {
        .container {
            padding-left: 0.75rem;
            padding-right: 0.75rem;
        }
        .card-header h5 {
            font-size: 1rem;
        }
        .card-body {
            padding: 1rem;
        }
        .form-top-section {
            padding: 1rem;
        }
        .table th, .table td {
            font-size: 0.8rem;
            padding: 0.5rem 0.4rem;
            white-space: normal !important;
        }
        .btn {
            font-size: 0.9rem;
        }
        .button-group-bottom {
            flex-direction: column;
            gap: 0.5rem !important;
        }
        .button-group-bottom .btn {
            width: 100%;
        }
    }
</style>

<div class="container mt-4 mb-5">
    <div class="card shadow" style="max-width: 900px; margin: auto;">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-users-cog me-2"></i>Pencatatan Pelanggaran Kesantrian</h5>
            <a href="../" class="btn btn-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Kembali</a>
        </div>
        <div class="card-body">

            <!-- Notifikasi -->
            <?php
            if (isset($_GET['status'])) {
                $status = $_GET['status'];
                $pesan = '';
                $alert_type = '';
                switch ($status) {
                    case 'success':
                        $pesan = '<strong>Berhasil!</strong> Data pelanggaran telah sukses disimpan.';
                        $alert_type = 'success';
                        break;
                    case 'error_db':
                        $pesan = '<strong>Gagal!</strong> Terjadi kesalahan saat menyimpan ke database.';
                        $alert_type = 'danger';
                        break;
                    case 'error_validation':
                        $pesan = '<strong>Gagal!</strong> Data yang dikirim tidak lengkap.';
                        $alert_type = 'warning';
                        break;
                }
                if ($pesan) {
                    echo '<div class="alert alert-' . $alert_type . ' alert-dismissible fade show" role="alert">' . $pesan . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
                }
            }
            ?>
            
            <form id="pelanggaranForm" action="process.php" method="POST">
                
                <!-- Bagian Info Kesantrian -->
                <div class="form-top-section">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="pelanggaranSearch" class="form-label fw-bold">Jenis Pelanggaran</label>
                            <input type="text" id="pelanggaranSearch" class="form-control" placeholder="Ketik jenis pelanggaran..." required>
                            <input type="hidden" id="jenis_pelanggaran_id" name="jenis_pelanggaran_id">
                        </div>
                        <div class="col-md-6">
                            <label for="tanggal" class="form-label fw-bold">Waktu Kejadian</label>
                            <input type="datetime-local" class="form-control" id="tanggal" name="tanggal" value="<?= date('Y-m-d\TH:i'); ?>" required>
                        </div>
                    </div>
                </div>

                <!-- Bagian Cari & Tambah Santri -->
                <div class="row mb-3">
                    <div class="col-md-8">
                        <label for="santriSearch" class="form-label">Cari Santri:</label>
                        <input type="text" id="santriSearch" class="form-control" placeholder="Ketik min. 2 huruf nama santri...">
                    </div>
                    <div class="col-md-4 d-flex align-items-end mt-2 mt-md-0">
                        <button type="button" id="tambahSantri" class="btn btn-primary w-100">
                            <i class="fas fa-plus"></i>
                            <span class="d-none d-sm-inline"> Tambah ke Daftar</span>
                        </button>
                    </div>
                </div>
                
                <!-- Tabel Daftar Santri -->
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="daftarSantri">
                        <thead class="table-light">
                            <tr>
                                <th>Nama Santri</th>
                                <th class="text-center">Kelas</th>
                                <th class="text-center">Kamar</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Daftar santri yang melanggar akan muncul di sini -->
                        </tbody>
                    </table>
                </div>
                
                <!-- Tombol Aksi Form -->
                <div class="mt-3 d-flex justify-content-end gap-2 button-group-bottom">
                    <button type="button" id="resetForm" class="btn btn-danger"><i class="fas fa-sync-alt me-2"></i>Reset Form</button>
                    <button type="submit" name="simpan_pelanggaran_kesantrian" class="btn btn-success"><i class="fas fa-save me-2"></i>Simpan Pelanggaran</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Panggil jQuery dan jQuery UI -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>

<!-- Kirim data PHP ke JavaScript -->
<script>
const jenisPelanggaranData = <?= json_encode($pelanggaran_list_for_js); ?>;
</script>

<script>
$(document).ready(function() {

    let santriTerpilih = null;
    let pelanggaranTerpilih = null;

    // ✅ FIX: Pencarian pelanggaran diubah agar mencari di semua bagian teks
    $("#pelanggaranSearch").autocomplete({
        source: function(request, response) {
            // Buat pola pencarian (regex) yang case-insensitive
            var matcher = new RegExp($.ui.autocomplete.escapeRegex(request.term), "i");
            // Filter data pelanggaran berdasarkan pola di atas
            var filteredData = $.grep(jenisPelanggaranData, function(item) {
                // Cek apakah teks yang diketik ada di dalam label pelanggaran
                return matcher.test(item.label);
            });
            response(filteredData);
        },
        minLength: 1,
        select: function(event, ui) {
            pelanggaranTerpilih = ui.item;
            $("#pelanggaranSearch").val(ui.item.value); // Tampilkan nama pelanggaran
            $("#jenis_pelanggaran_id").val(ui.item.id); // Simpan ID di input hidden
            return false;
        }
    }).autocomplete("instance")._renderItem = function(ul, item) {
        return $("<li>")
            .append(`<div>${item.label}</div>`) 
            .appendTo(ul);
    };

    $("#santriSearch").autocomplete({
        source: function(request, response) {
            $.ajax({
                url: "search_santri.php",
                dataType: "json",
                data: {
                    term: request.term
                },
                success: function(data) {
                    response(data);
                }
            });
        },
        minLength: 2,
        select: function(event, ui) {
            santriTerpilih = ui.item; 
            $("#santriSearch").val(ui.item.nama);
            return false;
        }
    }).autocomplete("instance")._renderItem = function(ul, item) {
        return $("<li>")
            .append(`<div>${item.label}</div>`) 
            .appendTo(ul);
    };

    $("#tambahSantri").on('click', function() {
        if (!santriTerpilih) {
            alert("Silakan cari dan pilih santri terlebih dahulu!");
            return;
        }

        const sudahAda = $(`#daftarSantri tbody input[value='${santriTerpilih.id}']`).length > 0;
        if (sudahAda) {
            alert("Santri ini sudah ada di dalam daftar!");
            return;
        }

        const barisBaru = `
            <tr>
                <td>
                    <input type="hidden" name="santri_id[]" value="${santriTerpilih.id}">
                    ${santriTerpilih.nama}
                </td>
                <td class="text-center">${santriTerpilih.kelas}</td>
                <td class="text-center">${santriTerpilih.kamar}</td>
                <td class="text-center">
                    <button type="button" class="btn btn-danger btn-sm hapus-santri">Hapus</button>
                </td>
            </tr>
        `;
        $("#daftarSantri tbody").append(barisBaru);

        $("#santriSearch").val('');
        santriTerpilih = null;
    });

    $("#daftarSantri").on('click', '.hapus-santri', function() {
        $(this).closest('tr').remove();
    });
    
    $("#resetForm").on('click', function(){
        $("#daftarSantri tbody").empty();
        $("#pelanggaranForm")[0].reset();
        $("#santriSearch").val('');
        santriTerpilih = null;
        
        $("#pelanggaranSearch").val('');
        $("#jenis_pelanggaran_id").val('');
        pelanggaranTerpilih = null;
    });

    $("#pelanggaranForm").on('submit', function(e){
        if (!$("#jenis_pelanggaran_id").val()) {
            e.preventDefault();
            alert("Jenis pelanggaran belum dipilih!");
            return;
        }

        const jumlahSantri = $("#daftarSantri tbody tr").length;
        if(jumlahSantri === 0){
            e.preventDefault();
            alert("Daftar santri pelanggar tidak boleh kosong!");
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../footer.php'; ?>