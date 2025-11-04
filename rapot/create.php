<?php
// File: rekap-mukholif/rapot/create.php
// VERSI FINAL: Pake layout Pilihan Ganda + BISA DUPLIKAT + AJAX Cek Poin

// 1. Panggil 'Otak' aplikasi dulu
require_once __DIR__ . '/../init.php';
// Panggil helper kita
require_once __DIR__ . '/helper.php';

// 2. Jalankan 'SATPAM'
guard('rapot_create');

// ==========================================================
//           LOGIKA DUPLIKAT
// ==========================================================
$data_duplikat = null;
$page_title = "Buat Rapot Kepengasuhan Baru";

if (isset($_GET['duplicate_id'])) {
    $rapot_id_lama = (int)$_GET['duplicate_id'];
    try {
        $stmt_duplikat = $conn->prepare("SELECT * FROM rapot_kepengasuhan WHERE id = ?");
        $stmt_duplikat->bind_param("i", $rapot_id_lama);
        $stmt_duplikat->execute();
        $data_duplikat = $stmt_duplikat->get_result()->fetch_assoc();
        $stmt_duplikat->close();
        if ($data_duplikat) {
            $page_title = "Duplikat Rapot Kepengasuhan";
        }
    } catch (Exception $e) {
        $data_duplikat = null;
    }
}
// ==========================================================


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
require_once __DIR__ . '/../header.php'; 
?>

<style>
    .btn-group-toggle .btn {
        border-radius: 0.25rem !important; margin-right: 5px; margin-bottom: 5px;
        font-size: 0.85rem; background-color: #f8f9fa; border: 1px solid #ddd; color: #666;
    }
    .btn-group-toggle .btn.active {
        background-color: #0d6efd; color: #fff; border-color: #0d6efd;
    }
    .btn-group-toggle input[type="radio"]:checked[value="5"] + label {
        background-color: #198754; border-color: #198754; color: white;
    }
    .btn-group-toggle input[type="radio"]:checked[value="4"] + label {
        background-color: #0dcaf0; border-color: #0dcaf0; color: white;
    }
    .btn-group-toggle input[type="radio"]:checked[value="3"] + label {
        background-color: #ffc107; border-color: #ffc107; color: #333;
    }
    .btn-group-toggle input[type="radio"]:checked[value="2"] + label,
    .btn-group-toggle input[type="radio"]:checked[value="1"] + label {
        background-color: #dc3545; border-color: #dc3545; color: white;
    }
    .card-header-flex {
        display: flex; justify-content: space-between; align-items: center;
    }
    .question-label {
        font-weight: 600; margin-bottom: 0.5rem; display: block; 
    }
</style>


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
                        <h6 class="m-0 font-weight-bold text-secondary"><i class="fas fa-user-check me-2"></i>Data Santri</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-5">
                                <div class="form-group mb-md-0">
                                    <label for="santri_id">Pilih Santri</label>
                                    <select name="santri_id" id="santri_id" class="form-control" required>
                                        <option value="">-- Pilih Santri --</option>
                                        <?php foreach ($santri_list as $santri) : ?>
                                            <?php $selected_santri = ($data_duplikat && $data_duplikat['santri_id'] == $santri['id']) ? 'selected' : ''; ?>
                                            <option value="<?php echo $santri['id']; ?>" <?php echo $selected_santri; ?>>
                                                (Kamar <?php echo htmlspecialchars($santri['kamar'], ENT_QUOTES, 'UTF-8'); ?>) - <?php echo htmlspecialchars($santri['nama'], ENT_QUOTES, 'UTF-8'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-md-0">
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
                                <div class="form-group mb-0">
                                    <label for="tahun">Tahun</label>
                                    <input type="number" name="tahun" id="tahun" class="form-control" value="<?php echo $tahun_default; ?>" required>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php
                // Helper bikin tombol pilihan (udah bener)
                function buatTombolPilihan($key, $data_duplikat = null) {
                    $html = "<div class='btn-group btn-group-toggle flex-wrap' data-bs-toggle='buttons'>";
                    $dropdown_options_html = generatePenilaianDropdown($key);
                    $checked_value = $data_duplikat ? $data_duplikat[$key] : null;
                    preg_match_all('/<option value="(\d+)">([^<]+)<\/option>/', $dropdown_options_html, $matches, PREG_SET_ORDER);
                    
                    foreach ($matches as $match) {
                        $value = $match[1];
                        $text = $match[2];
                        if ($value == "") continue;
                        $checked = ($checked_value !== null && $value == $checked_value) ? 'checked' : '';
                        $html .= "
                            <input type='radio' class='btn-check' name='{$key}' id='{$key}_{$value}' value='{$value}' autocomplete='off' required {$checked}>
                            <label class='btn' for='{$key}_{$value}'>{$text}</label>
                        ";
                    }
                    $html .= "</div>";
                    return $html;
                }
                ?>

                <div class="card shadow mb-3 border-left-success">
                    <div class="card-header py-3 card-header-flex">
                        <h6 class="m-0 font-weight-bold text-success"><i class="fas fa-pray me-2"></i>Mutu: Ibadah</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3"><label class="question-label">Puasa Sunnah</label><?php echo buatTombolPilihan('puasa_sunnah', $data_duplikat); ?></div>
                        <div class="mb-3"><label class="question-label">Sholat Duha</label><?php echo buatTombolPilihan('sholat_duha', $data_duplikat); ?></div>
                        <div class="mb-3"><label class="question-label">Sholat Malam</label><?php echo buatTombolPilihan('sholat_malam', $data_duplikat); ?></div>
                        <div class="mb-3"><label class="question-label">Sedekah & Berbagi</label><?php echo buatTombolPilihan('sedekah', $data_duplikat); ?></div>
                        <div class="mb-3"><label class="question-label">Sunnah sebelum tidur</label><?php echo buatTombolPilihan('sunnah_tidur', $data_duplikat); ?></div>
                        <div class="mb-3"><label class="question-label">Ibadah lainnya</label><?php echo buatTombolPilihan('ibadah_lainnya', $data_duplikat); ?></div>
                    </div>
                </div>

                <div class="card shadow mb-3 border-left-primary">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-smile-beam me-2"></i>Mutu: Akhlaq</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3"><label class="question-label">Lisan</label><?php echo buatTombolPilihan('lisan', $data_duplikat); ?></div>
                        <div class="mb-3"><label class="question-label">Sikap & tingkah laku</label><?php echo buatTombolPilihan('sikap', $data_duplikat); ?></div>
                        <div class="mb-3"><label class="question-label">Kesopanan</label><?php echo buatTombolPilihan('kesopanan', $data_duplikat); ?></div>
                        <div class="mb-3"><label class="question-label">Muamalah</label><?php echo buatTombolPilihan('muamalah', $data_duplikat); ?></div>
                    </div>
                </div>

                <div class="card shadow mb-3 border-left-warning">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-warning"><i class="fas fa-clock me-2"></i>Mutu: Kedisiplinan</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3"><label class="question-label">Tidur</label><?php echo buatTombolPilihan('tidur', $data_duplikat); ?></div>
                        <div class="mb-3"><label class="question-label">Keterlambatan</label><?php echo buatTombolPilihan('keterlambatan', $data_duplikat); ?></div>
                        <div class="mb-3"><label class="question-label">Seragam</label><?php echo buatTombolPilihan('seragam', $data_duplikat); ?></div>
                        <div class="mb-3"><label class="question-label">Makan</label><?php echo buatTombolPilihan('makan', $data_duplikat); ?></div>
                        <div class="mb-3"><label class="question-label">Mengikuti arahan</label><?php echo buatTombolPilihan('arahan', $data_duplikat); ?></div>
                        <div class="mb-3"><label class="question-label">Berbahasa arab di kamar</label><?php echo buatTombolPilihan('bahasa_arab', $data_duplikat); ?></div>
                    </div>
                </div>

                <div class="card shadow mb-3 border-left-info">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-info"><i class="fas fa-broom me-2"></i>Mutu: Kebersihan & Kerapihan</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3"><label class="question-label">Mandi</label><?php echo buatTombolPilihan('mandi', $data_duplikat); ?></div>
                        <div class="mb-3"><label class="question-label">Penampilan & berpakaian</label><?php echo buatTombolPilihan('penampilan', $data_duplikat); ?></div>
                        <div class="mb-3"><label class="question-label">Piket</label><?php echo buatTombolPilihan('piket', $data_duplikat); ?></div>
                        <div class="mb-3"><label class="question-label">Kerapihan barang</label><?php echo buatTombolPilihan('kerapihan_barang', $data_duplikat); ?></div>
                    </div>
                </div>


                <div class="card shadow mb-3 border-left-danger">
                    <div class="card-header py-3 card-header-flex">
                        <h6 class="m-0 font-weight-bold text-danger"><i class="fas fa-exclamation-triangle me-2"></i>Rincian Poin Pelanggaran</h6>
                        <button type="button" id="btn-cek-poin" class="btn btn-sm btn-danger" 
                                data-bs-toggle="tooltip" title="Cek poin santri di bulan & tahun yang dipilih" disabled>
                            <i class="fas fa-sync-alt"></i> Cek Poin
                        </button>
                    </div>
                    <div class="card-body" id="card-rincian-poin" style="min-height: 80px;">
                        <div class="alert alert-info mb-0">
                            Silakan pilih santri, bulan, dan tahun, lalu klik tombol "Cek Poin" di atas.
                        </div>
                    </div>
                </div>
                
                <div class="form-group d-flex justify-content-between align-items-center">
                    <label for="catatan_musyrif" class="mb-0">Catatan Musyrif</label>
                    <button type="button" id="btn-auto-catatan" class="btn btn-sm btn-info" 
                            data-bs-toggle="tooltip" title="Harap isi semua data penilaian terlebih dahulu" disabled>
                        <i class="fas fa-magic"></i> Buat Catatan Otomatis
                    </button>
                </div>
                <div class="form-group">
                    <textarea name="catatan_musyrif" id="catatan_musyrif" class="form-control" rows="4" placeholder="Klik tombol 'Buat Catatan Otomatis' di atas untuk membuat rangkuman, atau ketik manual..."><?php echo $data_duplikat ? htmlspecialchars($data_duplikat['catatan_musyrif']) : ''; ?></textarea>
                </div>

                <hr>

                <button type="submit" name="simpan_rapot" class="btn btn-primary btn-lg" 
                        data-bs-toggle="tooltip" title="Harap isi semua data penilaian terlebih dahulu" disabled>
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
    
    // ==========================================================
    //           PERBAIKANNYA DI SINI
    // 1. Inisialisasi Select2
    // ==========================================================
    $('#santri_id').select2({
        theme: "bootstrap-5",
        // Biar Select2-nya gak mental keluar card
        dropdownParent: $('#santri_id').parent() 
    });
    // ==========================================================

    
    // Inisialisasi Tooltip Bootstrap 5
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    })
    
    function updateTooltip(element, newTitle) {
        var tooltipInstance = bootstrap.Tooltip.getInstance(element);
        if (tooltipInstance) {
            tooltipInstance.setContent({ '.tooltip-inner': newTitle });
        }
    }

    // === BAGIAN VALIDASI FORM ===
    var autoCatatanBtn = $('#btn-auto-catatan');
    var simpanBtn = $('button[name="simpan_rapot"]');
    var cekPoinBtn = $('#btn-cek-poin'); 
    var formRapot = $('#form-rapot');

    // Cek khusus data utama (santri, bulan, tahun)
    function checkDataUtama() {
        if ($('#santri_id').val() !== '' && $('#bulan').val() !== '' && $('#tahun').val() !== '') {
            cekPoinBtn.prop('disabled', false).attr('data-original-title', 'Cek poin santri di bulan & tahun yang dipilih');
        } else {
            cekPoinBtn.prop('disabled', true).attr('data-original-title', 'Pilih Santri, Bulan, dan Tahun dulu');
        }
        updateTooltip(cekPoinBtn.get(0), cekPoinBtn.attr('data-original-title'));
    }

    function checkFormValidity() {
        var isFormValid = true;
        
        // Cek data utama
        if ($('#santri_id').val() === '' || $('#bulan').val() === '' || $('#tahun').val() === '') {
            isFormValid = false;
        }

        // Cek 20 grup tombol radio
        var checkedRadios = formRapot.find('input[type="radio"]:checked').length;
        if (checkedRadios < 20) {
            isFormValid = false;
        }

        // Update tombol Simpan & Auto-Catatan
        if (isFormValid) {
            autoCatatanBtn.prop('disabled', false);
            simpanBtn.prop('disabled', false);
            updateTooltip(autoCatatanBtn.get(0), 'Buat catatan berdasarkan nilai di atas');
            updateTooltip(simpanBtn.get(0), 'Simpan data rapot');
        } else {
            autoCatatanBtn.prop('disabled', true);
            simpanBtn.prop('disabled', true);
            updateTooltip(autoCatatanBtn.get(0), 'Harap isi semua data penilaian terlebih dahulu');
            updateTooltip(simpanBtn.get(0), 'Harap isi semua data penilaian terlebih dahulu');
        }
    }

    // Cek pas pertama kali load
    checkFormValidity();
    checkDataUtama();

    // Cek setiap kali ada perubahan di form
    formRapot.on('change', function() {
        checkFormValidity();
        checkDataUtama();
    });
    
    // ==========================================================
    //           PERBAIKANNYA DI SINI
    // 2. Tambahin listener khusus buat Select2
    // ==========================================================
    $('#santri_id').on('change', function() {
        checkFormValidity();
        checkDataUtama();
    });
    // ==========================================================


    // SCRIPT AJAX BUAT CEK POIN (Udah bener)
    cekPoinBtn.on('click', function() {
        var btn = $(this);
        var originalText = btn.html();
        var targetDiv = $('#card-rincian-poin');
        
        btn.html('<i class="fas fa-spinner fa-spin"></i> Loading...');
        btn.prop('disabled', true);
        targetDiv.html('<div class="text-center p-3"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i></div>');

        var dataPoin = {
            'santri_id': $('#santri_id').val(),
            'bulan': $('#bulan').val(),
            'tahun': $('#tahun').val()
        };

        $.post('get_pelanggaran_santri.php', dataPoin, function(response) {
            targetDiv.html(response);
            btn.html(originalText);
            checkDataUtama(); 
        }).fail(function() {
            targetDiv.html('<div class="alert alert-danger mb-0">Error: Gagal konek ke server.</div>');
            btn.html(originalText);
            checkDataUtama();
        });
    });


    // Pas tombol auto-catatan diklik (Udah bener)
    autoCatatanBtn.on('click', function() {
        
        var btn = $(this);
        var originalText = btn.html();
        
        btn.html('<i class="fas fa-spinner fa-spin"></i> Memproses...');
        btn.prop('disabled', true);
        
        var dataPenilaian = formRapot.serialize();

        $.post('generate_catatan.php', dataPenilaian, function(response) {
            $('#catatan_musyrif').val(response);
            btn.html(originalText);
            checkFormValidity(); 
            
        }).fail(function() {
            alert('Error: Gagal membuat catatan otomatis.');
            btn.html(originalText);
            checkFormValidity();
        });
    });
    
});
</script>