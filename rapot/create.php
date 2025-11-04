<?php
// File: rekap-mukholif/rapot/create.php

// 1. Panggil 'Otak' aplikasi dulu
require_once __DIR__ . '/../init.php';
// Panggil helper BARU kita
require_once __DIR__ . '/helper.php';

// 2. Jalankan 'SATPAM'
guard('rapot_create');

// 3. Ambil data santri untuk dropdown
try {
    $stmt = $conn->query("
        SELECT id, nama, kamar 
        FROM santri 
        WHERE kamar IS NOT NULL AND kamar != '' AND kamar != '0'
        ORDER BY CAST(kamar AS UNSIGNED), nama
    ");
    $santri_list = $stmt->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    die("Error fetching santri: " . $e->getMessage());
}

// 4. Siapin data bulan (Bahasa Indonesia)
$bulan_list = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
];
$bulan_sekarang_idx = (int)date('n') - 1; 
$bulan_rapot_idx = ($bulan_sekarang_idx == 0) ? 11 : $bulan_sekarang_idx - 1; 
$bulan_default = $bulan_list[$bulan_rapot_idx];
$tahun_default = ($bulan_sekarang_idx == 0) ? (int)date('Y') - 1 : (int)date('Y');


// 5. Panggil Header
$page_title = "Buat Rapot Kepengasuhan Baru";
require_once __DIR__ . '/../header.php'; 
?>

<div class="container-fluid">

    <h1 class="h3 mb-4 text-gray-800"><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></h1>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Form Input Penilaian Santri</h6>
        </div>
        <div class="card-body">

            <form action="process.php" method="POST" id="form-rapot">

                <div class="card shadow mb-3 border-left-secondary">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-secondary"><i class="fas fa-user-check mr-2"></i>Data Santri</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-5">
                                <div class="form-group">
                                    <label for="santri_id">Pilih Santri</label>
                                    <select name="santri_id" id="santri_id" class="form-control" required>
                                        <option value="">-- Pilih Santri --</option>
                                        <?php foreach ($santri_list as $santri) : ?>
                                            <option value="<?php echo $santri['id']; ?>">
                                                (Kamar <?php echo htmlspecialchars($santri['kamar'], ENT_QUOTES, 'UTF-8'); ?>) - <?php echo htmlspecialchars($santri['nama'], ENT_QUOTES, 'UTF-8'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="bulan">Bulan</label>
                                    <select name="bulan" id="bulan" class="form-control" required>
                                        <?php foreach ($bulan_list as $bulan) : ?>
                                            <option value="<?php echo $bulan; ?>" <?php echo ($bulan_default == $bulan) ? 'selected' : ''; ?>>
                                                <?php echo $bulan; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="tahun">Tahun</label>
                                    <input type="number" name="tahun" id="tahun" class="form-control" value="<?php echo $tahun_default; ?>" required>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow mb-3 border-left-success">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-success"><i class="fas fa-pray mr-2"></i>Mutu: Ibadah</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="puasa_sunnah">Puasa Sunnah</label>
                                    <select name="puasa_sunnah" id="puasa_sunnah" class="form-control" required><?php echo generatePenilaianDropdown('puasa_sunnah'); ?></select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="sholat_duha">Sholat Duha</label>
                                    <select name="sholat_duha" id="sholat_duha" class="form-control" required><?php echo generatePenilaianDropdown('sholat_duha'); ?></select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="sholat_malam">Sholat Malam</label>
                                    <select name="sholat_malam" id="sholat_malam" class="form-control" required><?php echo generatePenilaianDropdown('sholat_malam'); ?></select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="sedekah">Sedekah & Berbagi</label>
                                    <select name="sedekah" id="sedekah" class="form-control" required><?php echo generatePenilaianDropdown('sedekah'); ?></select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="sunnah_tidur">Sunnah sebelum tidur</label>
                                    <select name="sunnah_tidur" id="sunnah_tidur" class="form-control" required><?php echo generatePenilaianDropdown('sunnah_tidur'); ?></select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="ibadah_lainnya">Ibadah lainnya</label>
                                    <select name="ibadah_lainnya" id="ibadah_lainnya" class="form-control" required><?php echo generatePenilaianDropdown('ibadah_lainnya'); ?></select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow mb-3 border-left-primary">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-smile-beam mr-2"></i>Mutu: Akhlaq</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="lisan">Lisan</label>
                                    <select name="lisan" id="lisan" class="form-control" required><?php echo generatePenilaianDropdown('lisan'); ?></select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="sikap">Sikap & tingkah laku</label>
                                    <select name="sikap" id="sikap" class="form-control" required><?php echo generatePenilaianDropdown('sikap'); ?></select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="kesopanan">Kesopanan</label>
                                    <select name="kesopanan" id="kesopanan" class="form-control" required><?php echo generatePenilaianDropdown('kesopanan'); ?></select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="muamalah">Muamalah</label>
                                    <select name="muamalah" id="muamalah" class="form-control" required><?php echo generatePenilaianDropdown('muamalah'); ?></select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow mb-3 border-left-warning">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-warning"><i class="fas fa-clock mr-2"></i>Mutu: Kedisiplinan</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="tidur">Tidur</label>
                                    <select name="tidur" id="tidur" class="form-control" required><?php echo generatePenilaianDropdown('tidur'); ?></select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="keterlambatan">Keterlambatan</label>
                                    <select name="keterlambatan" id="keterlambatan" class="form-control" required><?php echo generatePenilaianDropdown('keterlambatan'); ?></select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="seragam">Seragam</label>
                                    <select name="seragam" id="seragam" class="form-control" required><?php echo generatePenilaianDropdown('seragam'); ?></select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="makan">Makan</label>
                                    <select name="makan" id="makan" class="form-control" required><?php echo generatePenilaianDropdown('makan'); ?></select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="arahan">Mengikuti arahan</label>
                                    <select name="arahan" id="arahan" class="form-control" required><?php echo generatePenilaianDropdown('arahan'); ?></select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="bahasa_arab">Berbahasa arab di kamar</label>
                                    <select name="bahasa_arab" id="bahasa_arab" class="form-control" required><?php echo generatePenilaianDropdown('bahasa_arab'); ?></select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow mb-3 border-left-info">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-info"><i class="fas fa-broom mr-2"></i>Mutu: Kebersihan & Kerapihan</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="mandi">Mandi</label>
                                    <select name="mandi" id="mandi" class="form-control" required><?php echo generatePenilaianDropdown('mandi'); ?></select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="penampilan">Penampilan & berpakaian</label>
                                    <select name="penampilan" id="penampilan" class="form-control" required><?php echo generatePenilaianDropdown('penampilan'); ?></select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="piket">Piket</label>
                                    <select name="piket" id="piket" class="form-control" required><?php echo generatePenilaianDropdown('piket'); ?></select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="kerapihan_barang">Kerapihan barang</label>
                                    <select name="kerapihan_barang" id="kerapihan_barang" class="form-control" required><?php echo generatePenilaianDropdown('kerapihan_barang'); ?></select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-group d-flex justify-content-between align-items-center">
                    <label for="catatan_musyrif" class="mb-0">Catatan Musyrif</label>
                    <button type="button" id="btn-auto-catatan" class="btn btn-sm btn-info" 
                            data-toggle="tooltip" title="Harap isi semua data penilaian terlebih dahulu" disabled>
                        <i class="fas fa-magic"></i> Buat Catatan Otomatis
                    </button>
                </div>
                <div class="form-group">
                    <textarea name="catatan_musyrif" id="catatan_musyrif" class="form-control" rows="4" placeholder="Klik tombol 'Buat Catatan Otomatis' di atas untuk membuat rangkuman, atau ketik manual..."></textarea>
                </div>

                <hr>

                <button type="submit" name="simpan_rapot" class="btn btn-primary btn-lg" 
                        data-toggle="tooltip" title="Harap isi semua data penilaian terlebih dahulu" disabled>
                    <i class="fas fa-save"></i> Simpan Data Rapot
                </button>
            </form>

        </div>
    </div>

</div>

<?php
// 6. Panggil Footer
require_once __DIR__ . '/../footer.php';
?>

<script>
$(document).ready(function() {
    
    // Inisialisasi Tooltip
    $('[data-toggle="tooltip"]').tooltip();

    // === BAGIAN VALIDASI FORM ===
    var autoCatatanBtn = $('#btn-auto-catatan');
    var simpanBtn = $('button[name="simpan_rapot"]');
    var requiredFields = $('#form-rapot [required]'); 

    function checkFormValidity() {
        var isFormValid = true;
        
        requiredFields.each(function() {
            if ($(this).val() === '' || $(this).val() === null) {
                isFormValid = false;
                return false; 
            }
        });

        if (isFormValid) {
            autoCatatanBtn.prop('disabled', false).attr('data-original-title', 'Buat catatan berdasarkan nilai di atas');
            simpanBtn.prop('disabled', false).attr('data-original-title', 'Simpan data rapot');
        } else {
            autoCatatanBtn.prop('disabled', true).attr('data-original-title', 'Harap isi semua data penilaian terlebih dahulu');
            simpanBtn.prop('disabled', true).attr('data-original-title', 'Harap isi semua data penilaian terlebih dahulu');
        }
        $('[data-toggle="tooltip"]').tooltip('hide');
    }
    checkFormValidity();
    requiredFields.on('change keyup', function() {
        checkFormValidity();
    });
    // === AKHIR BAGIAN VALIDASI ===


    // Pas tombol auto-catatan diklik
    autoCatatanBtn.on('click', function() {
        
        var btn = $(this);
        var originalText = btn.html();
        
        btn.html('<i class="fas fa-spinner fa-spin"></i> Memproses...');
        btn.prop('disabled', true);
        
        // ==========================================================
        //           PERUBAHAN JAVASCRIPT DI SINI
        //  Kita tambahin 3 data utama ke AJAX
        // ==========================================================
        var dataPenilaian = {
            // Data Utama
            'santri_id': $('#santri_id').val(),
            'bulan': $('#bulan').val(),
            'tahun': $('#tahun').val(),

            // Data Nilai
            'puasa_sunnah': $('#puasa_sunnah').val(),
            'sholat_duha': $('#sholat_duha').val(),
            'sholat_malam': $('#sholat_malam').val(),
            'sedekah': $('#sedekah').val(),
            'sunnah_tidur': $('#sunnah_tidur').val(),
            'ibadah_lainnya': $('#ibadah_lainnya').val(),
            'lisan': $('#lisan').val(),
            'sikap': $('#sikap').val(),
            'kesopanan': $('#kesopanan').val(),
            'muamalah': $('#muamalah').val(),
            'tidur': $('#tidur').val(),
            'keterlambatan': $('#keterlambatan').val(),
            'seragam': $('#seragam').val(),
            'makan': $('#makan').val(),
            'arahan': $('#arahan').val(),
            'bahasa_arab': $('#bahasa_arab').val(),
            'mandi': $('#mandi').val(),
            'penampilan': $('#penampilan').val(),
            'piket': $('#piket').val(),
            'kerapihan_barang': $('#kerapihan_barang').val()
        };

        // Kirim data ke "otak" PHP pake AJAX
        $.post('generate_catatan.php', dataPenilaian, function(response) {
            // Kalo sukses, masukin responnya ke textarea
            $('#catatan_musyrif').val(response);
            
            // Balikin tombolnya ke semula
            btn.html(originalText);
            checkFormValidity(); 
            
        }).fail(function() {
            // Kalo gagal
            alert('Error: Gagal membuat catatan otomatis.');
            btn.html(originalText);
            checkFormValidity();
        });
    });
    
});
</script>