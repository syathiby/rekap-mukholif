<?php
// File: rekap-mukholif/rapot/create.php
// VERSI FINAL TERPADU + AUTO CEK POIN & REWARD

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/helper.php';

guard('rapot_create');

$data_duplikat = null;
$page_title = "Buat Rapot Kepengasuhan Baru";

// ==========================================================
//       LOGIKA DUPLIKAT
// ==========================================================
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
// AMBIL DATA SANTRI
// ==========================================================
$filter_kamar_create = $_GET['kamar'] ?? '';
try {
    $sql_santri = "SELECT id, nama, kamar FROM santri WHERE kamar IS NOT NULL AND kamar != '' AND kamar != '0'";
    $params_santri = [];
    $types_santri = "";
    if (!empty($filter_kamar_create) && !$data_duplikat) {
        $sql_santri .= " AND kamar = ?";
        $params_santri[] = $filter_kamar_create;
        $types_santri .= "s";
    }
    $sql_santri .= " ORDER BY CAST(kamar AS UNSIGNED), nama";
    $stmt = $conn->prepare($sql_santri);
    if (!empty($params_santri)) {
        $stmt->bind_param($types_santri, ...$params_santri);
    }
    $stmt->execute();
    $santri_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    die("Error fetching santri: " . $e->getMessage());
}

// ==========================================================
// SETTING BULAN & TAHUN
// ==========================================================
$bulan_list = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
];

if ($data_duplikat) {
    $bulan_lama_idx = array_search($data_duplikat['bulan'], $bulan_list);
    if ($bulan_lama_idx !== false) {
        $bulan_default = $bulan_list[($bulan_lama_idx + 1) % 12];
        $tahun_default = ($bulan_lama_idx == 11) ? (int)$data_duplikat['tahun'] + 1 : (int)$data_duplikat['tahun'];
    } else {
        $bulan_default = $bulan_list[(int)date('n') - 1];
        $tahun_default = (int)date('Y');
    }
} else {
    $bulan_default = $bulan_list[(int)date('n') - 1];
    $tahun_default = (int)date('Y');
}

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

    <div class="alert alert-info shadow-sm" role="alert">
        <h5 class="alert-heading" style="font-size: 1.1rem;"><i class="fas fa-book-reader me-2"></i>Panduan Cepat!</h5>
        <p>Halaman ini digunakan untuk mengisi nilai rapot bulanan santri satu per satu.</p>
        <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#guideModal">
            <i class="fas fa-book-open me-1"></i> Buka Buku Panduan
        </button>
    </div>

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
                                        <?php if (empty($santri_list) && !empty($filter_kamar_create) && !$data_duplikat): ?>
                                            <option value="">-- Tidak ada santri di kamar <?php echo htmlspecialchars($filter_kamar_create); ?> --</option>
                                        <?php else: ?>
                                            <option value="">-- Pilih Santri --</option>
                                        <?php endif; ?>
                                        <?php foreach ($santri_list as $santri): ?>
                                            <option value="<?php echo $santri['id']; ?>" <?php echo ($data_duplikat && $data_duplikat['santri_id'] == $santri['id']) ? 'selected' : ''; ?>>
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
                                        <?php foreach ($bulan_list as $bulan): ?>
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

                <!-- DUA CARD UNTUK PELANGGARAN & REWARD -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card shadow mb-3 border-left-danger">
                            <div class="card-header py-3 card-header-flex">
                                <h6 class="m-0 font-weight-bold text-danger"><i class="fas fa-exclamation-triangle me-2"></i>Rincian Poin Pelanggaran</h6>
                                <button type="button" id="btn-cek-data" class="btn btn-sm btn-danger" 
                                        data-bs-toggle="tooltip" title="Refresh data pelanggaran & reward">
                                    <i class="fas fa-sync-alt"></i> Refresh Data
                                </button>
                            </div>
                            <div class="card-body" id="card-rincian-poin" style="min-height: 80px;">
                                <div class="alert alert-info mb-0">
                                    Menunggu pemilihan santri, bulan, dan tahun...
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card shadow mb-3 border-left-success">
                            <div class="card-header py-3 card-header-flex">
                                <h6 class="m-0 font-weight-bold text-success"><i class="fas fa-gift me-2"></i>Rincian Poin Reward</h6>
                            </div>
                            <div class="card-body" id="card-rincian-reward" style="min-height: 80px;">
                                <div class="alert alert-info mb-0">
                                    Menunggu pemilihan santri, bulan, dan tahun...
                                </div>
                            </div>
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

<?php require_once __DIR__ . '/../footer.php'; ?>

<script>
$(document).ready(function() {
    $('#santri_id').select2({ theme: "bootstrap-5", dropdownParent: $('#santri_id').parent() });

    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    function updateTooltip(element, newTitle) {
        var tooltipInstance = bootstrap.Tooltip.getInstance(element);
        if (tooltipInstance) {
            tooltipInstance.setContent({ '.tooltip-inner': newTitle });
        }
    }

    // === MODAL PANDUAN ===
    let modalTriggerElement = null;
    const guideModalTriggers = document.querySelectorAll('button[data-bs-target="#guideModal"]');
    function setModalTrigger(e) { modalTriggerElement = this; }
    guideModalTriggers.forEach(trigger => trigger.addEventListener('click', setModalTrigger));
    const allModalCloseButtons = document.querySelectorAll('#guideModal [data-bs-dismiss="modal"]');
    allModalCloseButtons.forEach(btn => btn.addEventListener('click', () => {
        if (modalTriggerElement) modalTriggerElement.focus();
        modalTriggerElement = null;
    }));
    const guideModalElement = document.getElementById('guideModal');
    if (guideModalElement) guideModalElement.addEventListener('hidden.bs.modal', () => {
        if (modalTriggerElement) modalTriggerElement.focus();
        modalTriggerElement = null;
    });

    // === VALIDASI FORM ===
    var autoCatatanBtn = $('#btn-auto-catatan');
    var simpanBtn = $('button[name="simpan_rapot"]');
    var formRapot = $('#form-rapot');

    function checkFormValidity() {
        var isBaseOk = ($('#santri_id').val() !== '' && $('#bulan').val() !== '' && $('#tahun').val() !== '');
        var totalChecked = formRapot.find('input[type="radio"]:checked').length;
        var isFormValid = isBaseOk && (totalChecked >= 20);

        autoCatatanBtn.prop('disabled', !isFormValid);
        simpanBtn.prop('disabled', !isFormValid);

        updateTooltip(autoCatatanBtn.get(0), isFormValid ? 'Buat catatan berdasarkan nilai di atas' : 'Harap isi semua data penilaian terlebih dahulu');
        updateTooltip(simpanBtn.get(0), isFormValid ? 'Simpan data rapot' : 'Harap isi semua data penilaian terlebih dahulu');
    }

    checkFormValidity();
    formRapot.on('change', checkFormValidity);
    $('#santri_id').on('change', checkFormValidity);

    // === AUTO FETCH POIN & REWARD ===
    let fetchTimeout = null;

    function autoFetchPoinReward() {
        var santri = $('#santri_id').val();
        var bulan = $('#bulan').val();
        var tahun = $('#tahun').val();

        if (!santri || !bulan || !tahun) {
            $('#card-rincian-poin, #card-rincian-reward').html('<div class="alert alert-info mb-0">Menunggu pemilihan santri, bulan, dan tahun...</div>');
            return;
        }

        // Clear previous timeout
        if (fetchTimeout) clearTimeout(fetchTimeout);

        // Debounce: tunggu 300ms setelah user berhenti mengubah
        fetchTimeout = setTimeout(function() {
            $('#card-rincian-poin, #card-rincian-reward').html('<div class="text-center p-3"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i></div>');

            var data = { santri_id: santri, bulan: bulan, tahun: tahun };

            $.post('get_pelanggaran_santri.php', data, function(res) {
                $('#card-rincian-poin').html(res);
            }).fail(function() {
                $('#card-rincian-poin').html('<div class="alert alert-danger mb-0">Gagal memuat data pelanggaran.</div>');
            });

            $.post('get_reward_santri.php', data, function(res) {
                $('#card-rincian-reward').html(res);
            }).fail(function() {
                $('#card-rincian-reward').html('<div class="alert alert-danger mb-0">Gagal memuat data reward.</div>');
            });
        }, 300);
    }

    // Trigger auto-fetch saat ada perubahan
    $('#santri_id, #bulan, #tahun').on('change', autoFetchPoinReward);

    // Manual refresh via button
    $('#btn-cek-data').on('click', autoFetchPoinReward);

    // Jalankan sekali saat halaman pertama kali load (jika duplikat)
    if ($('#santri_id').val() && $('#bulan').val() && $('#tahun').val()) {
        autoFetchPoinReward();
    }

    // === AUTO CATATAN (DITAMBAHKAN) ===
    $('#btn-auto-catatan').on('click', function() {
        var btn = $(this);
        var originalText = btn.html();
        btn.html('<i class="fas fa-spinner fa-spin"></i> Memproses...').prop('disabled', true);

        $.post('generate_catatan.php', $('#form-rapot').serialize(), function(response) {
            $('#catatan_musyrif').val(response);
            btn.html('<i class="fas fa-magic"></i> Buat Catatan Otomatis').prop('disabled', false);
            checkFormValidity();
        }).fail(function(xhr, status, error) {
            alert('Gagal membuat catatan otomatis. Cek koneksi atau hubungi admin.');
            console.error('Error:', xhr.responseText);
            btn.html('<i class="fas fa-magic"></i> Buat Catatan Otomatis').prop('disabled', false);
        });
    });
});
</script>

<!-- MODAL PANDUAN -->
<div class="modal fade" id="guideModal" tabindex="-1" aria-labelledby="guideModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-info text-white">
        <h5 class="modal-title" id="guideModalLabel"><i class="fas fa-book-open me-2"></i>Buku Panduan - Halaman Buat Rapot</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p>Gunakan halaman ini untuk mengisi nilai atau menduplikat rapot dari bulan sebelumnya.</p>
        
        <h6><i class="fas fa-user-check me-1"></i> 1. Data Utama</h6>
        <ul class="mb-3">
          <li><strong>Pilih Santri:</strong> Gunakan 'Select2' untuk mencari dan memilih santri.</li>
          <li><strong>Bulan & Tahun:</strong> Otomatis terisi ke bulan dan tahun saat ini (realtime).</li>
        </ul>

        <h6><i class="fas fa-copy me-1"></i> 2. Mode Duplikat</h6>
        <ul class="mb-3">
          <li>Jika Anda datang dari tombol "Duplikat", Santri & semua 20 nilai akan otomatis terisi.</li>
          <li>Bulan & Tahun akan otomatis maju <strong>Satu bulan</strong> dari rapot aslinya.</li>
          <li>Anda tinggal periksa lagi nilainya dan simpan.</li>
        </ul>

        <h6><i class="fas fa-sync-alt me-1"></i> 3. Cek Poin & Reward</h6>
        <ul class="mb-3">
          <li>Data pelanggaran & reward akan muncul otomatis setelah Santri, Bulan, dan Tahun dipilih.</li>
          <li>Gunakan tombol <strong>Refresh Data</strong> jika ingin memuat ulang.</li>
        </ul>

        <h6><i class="fas fa-magic me-1"></i> 4. Catatan Otomatis & Simpan</h6>
        <ul class="mb-0">
          <li>Tombol "Buat Catatan Otomatis" dan "Simpan" hanya akan aktif jika <strong>Semua 20 nilai</strong> sudah terisi.</li>
          <li>Klik "Buat Catatan Otomatis" untuk merangkum nilai-nilai yang buruk/baik ke dalam kolom catatan.</li>
        </ul>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Saya Mengerti</button>
      </div>
    </div>
  </div>
</div>