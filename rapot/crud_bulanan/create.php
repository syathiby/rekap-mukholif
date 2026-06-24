<?php
// File: rekap-mukholif/rapot/crud_bulanan/create.php
// VERSI FINAL TERPADU + AUTO CEK POIN & REWARD
// (dipindah dari rapot/create.php — path diupdate)

require_once __DIR__ . '/../../bootstrap/init.php';
require_once __DIR__ . '/../config/helper.php';

guard('rapot_create');

$page_title = "Buat Rapot Kepengasuhan Baru";

// ==========================================================
//       LOGIKA DUPLIKAT & EDIT
// ==========================================================
$data_sumber = null;
$is_edit = false;


if (isset($_GET['duplicate_id']) || isset($_GET['edit_id'])) {
    $rapot_id_lama = isset($_GET['edit_id']) ? (int)$_GET['edit_id'] : (int)$_GET['duplicate_id'];
    $is_edit = isset($_GET['edit_id']);
    try {
        $stmt_sumber = $conn->prepare("SELECT * FROM rapot_kepengasuhan WHERE id = ?");
        $stmt_sumber->bind_param("i", $rapot_id_lama);
        $stmt_sumber->execute();
        $data_sumber = $stmt_sumber->get_result()->fetch_assoc();
        $stmt_sumber->close();
        
        if ($data_sumber) {
            $page_title = $is_edit ? "Edit Rapot Kepengasuhan" : "Duplikat Rapot Kepengasuhan";
        } else {
            $is_edit = false; // reset if not found
        }
    } catch (Exception $e) {
        $data_sumber = null;
        $is_edit = false;
    }
}

// ==========================================================
// AMBIL DATA SANTRI
// ==========================================================
$kamar_filter_musyrif = checkMusyrifKamarAccess();

if ($data_sumber && $kamar_filter_musyrif !== null) {
    $stmt_kamar = $conn->prepare("SELECT kamar FROM santri WHERE id = ?");
    $stmt_kamar->bind_param("i", $data_sumber['santri_id']);
    $stmt_kamar->execute();
    $res_kamar = $stmt_kamar->get_result()->fetch_assoc();
    $stmt_kamar->close();
    if (!$res_kamar || (int)$res_kamar['kamar'] !== $kamar_filter_musyrif) {
        set_flash_message('Anda tidak memiliki akses ke rapot ini (Beda Kamar).', 'danger');
        header('Location: ../index.php');
        exit;
    }
}

$filter_kamar_create = isset($_GET['kamar']) ? $_GET['kamar'] : ($_SESSION['filter_rapot']['kamar'] ?? '');
try {
    $sql_santri = "SELECT id, nama, kamar FROM santri WHERE kamar IS NOT NULL AND kamar != '' AND kamar != '0'";
    $params_santri = [];
    $types_santri = "";
    if ($kamar_filter_musyrif !== null) {
        $sql_santri .= " AND kamar = ?";
        $params_santri[] = $kamar_filter_musyrif;
        $types_santri .= "i";
    } elseif (!empty($filter_kamar_create) && !$data_sumber) {
        $sql_santri .= " AND kamar = ?";
        $params_santri[] = $filter_kamar_create;
        $types_santri .= "s";
    }
    $sql_santri .= " ORDER BY kamar, nama";
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

if ($data_sumber) {
    if ($is_edit) {
        // === MODE EDIT: Pertahankan bulan & tahun ASLI rapot yang diedit ===
        $bulan_default = $data_sumber['bulan'];
        $tahun_default = (int)$data_sumber['tahun'];
    } else {
        // === MODE DUPLIKAT: Advance +1 bulan dari rapot sumber ===
        $bulan_lama_idx = array_search($data_sumber['bulan'], $bulan_list);
        if ($bulan_lama_idx !== false) {
            $bulan_default = $bulan_list[($bulan_lama_idx + 1) % 12];
            $tahun_default = ($bulan_lama_idx == 11) ? (int)$data_sumber['tahun'] + 1 : (int)$data_sumber['tahun'];
        } else {
            $bulan_default = $bulan_list[(int)date('n') - 1];
            $tahun_default = (int)date('Y');
        }
    }
} else {
    $bulan_default = $bulan_list[(int)date('n') - 1];
    $tahun_default = (int)date('Y');
}


require_once __DIR__ . '/../../layouts/header.php';
?>

<style>
    :root {
        --primary: #4f46e5;      /* Indigo 600 */
        --primary-hover: #4338ca;
        --secondary: #ec4899;    /* Pink 500 */
        --info: #3b82f6;         /* Blue 500 */
        --success: #10b981;      /* Emerald 500 */
        --warning: #f59e0b;      /* Amber 500 */
        --danger: #ef4444;       /* Red 500 */
        --bg-body: #f8fafc;      /* Slate 50 */
    }
    
    body {
        background-color: var(--bg-body);
    }
    
    .page-title-card {
        background: white;
        border-radius: 1rem;
        padding: 1.5rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        border: 1px solid #e2e8f0;
        margin-bottom: 1.5rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .btn-group-toggle .btn {
        border-radius: 2rem !important; /* Rounded pills */
        margin-right: 0.5rem; 
        margin-bottom: 0.5rem;
        font-size: 0.85rem; 
        background-color: #f8fafc; 
        border: 1px solid #cbd5e1; 
        color: #64748b;
        font-weight: 500;
        transition: all 0.2s ease;
    }
    
    .btn-group-toggle .btn:hover {
        background-color: #f1f5f9;
        color: #475569;
    }

    /* Selected states with softer colors */
    .btn-group-toggle input[type="radio"]:checked[value="5"] + label {
        background-color: #dcfce7; border-color: #22c55e; color: #166534; font-weight: 600;
    }
    .btn-group-toggle input[type="radio"]:checked[value="4"] + label {
        background-color: #dbeafe; border-color: #3b82f6; color: #1e40af; font-weight: 600;
    }
    .btn-group-toggle input[type="radio"]:checked[value="3"] + label {
        background-color: #fef3c7; border-color: #f59e0b; color: #b45309; font-weight: 600;
    }
    .btn-group-toggle input[type="radio"]:checked[value="2"] + label,
    .btn-group-toggle input[type="radio"]:checked[value="1"] + label {
        background-color: #fee2e2; border-color: #ef4444; color: #b91c1c; font-weight: 600;
    }
    
    .card-header-flex {
        display: flex; justify-content: space-between; align-items: center;
    }
    .question-label {
        font-weight: 600; margin-bottom: 0.5rem; display: block; color: #334155;
    }
    
    .form-card {
        border: none;
        border-radius: 1rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        margin-bottom: 1.5rem;
    }
    .form-card .card-header {
        background: transparent !important;
        border-bottom: 1px solid #f1f5f9;
        padding: 1.25rem 1.5rem;
    }
    .form-card .card-body {
        background: transparent !important;
        padding: 1.5rem;
    }
</style>

<?php
    // Tentukan atribut badge & ikon sesuai mode
    if ($is_edit && $data_sumber) {
        $mode_badge_class  = 'bg-warning text-dark';
        $mode_badge_icon   = 'fa-pencil-alt';
        $mode_badge_label  = 'Mode Edit';
        $circle_color      = 'color:#b45309;';
        $circle_style      = 'background-color:#fef3c7; color:#b45309;';
        $circle_icon       = 'fa-pencil-alt';
        $subtitle_text     = 'Anda sedang <strong>mengedit</strong> data rapot yang sudah ada. Perubahan akan langsung tersimpan.';
    } elseif (!$is_edit && $data_sumber) {
        $mode_badge_class  = 'bg-info text-white';
        $mode_badge_icon   = 'fa-copy';
        $mode_badge_label  = 'Duplikat';
        $circle_style      = 'background-color:#cff4fc; color:#0c6271;';
        $circle_icon       = 'fa-copy';
        $subtitle_text     = 'Anda sedang <strong>menduplikat</strong> rapot ke bulan berikutnya. Pastikan nilai & catatan sudah sesuai.';
    } else {
        $mode_badge_class  = 'bg-success text-white';
        $mode_badge_icon   = 'fa-plus-circle';
        $mode_badge_label  = 'Buat Baru';
        $circle_style      = 'background-color:#dbeafe; color:#1d4ed8;';
        $circle_icon       = 'fa-edit';
        $subtitle_text     = 'Isi nilai rapot bulanan santri satu per satu secara teliti.';
    }
?>
<div class="container-fluid pb-5">
    <!-- Redesigned Page Header -->
    <div class="page-title-card">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width:48px; height:48px; <?php echo $circle_style; ?>">
                <i class="fas <?php echo $circle_icon; ?> fs-5"></i>
            </div>
            <div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <h3 class="fw-bold mb-0 text-dark" style="letter-spacing: -0.5px;">
                        <?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?>
                    </h3>
                    <span class="badge <?php echo $mode_badge_class; ?> rounded-pill px-3 py-1 fw-semibold" style="font-size: 0.72rem; letter-spacing: 0.03em;">
                        <i class="fas <?php echo $mode_badge_icon; ?> me-1"></i><?php echo $mode_badge_label; ?>
                    </span>
                </div>
                <p class="text-secondary mb-0 small mt-1">
                    <i class="fas fa-info-circle me-1"></i> <?php echo $subtitle_text; ?>
                </p>
            </div>
        </div>
        <div class="mt-3 mt-md-0">
            <button type="button" class="btn btn-light border shadow-sm rounded-pill fw-medium text-secondary px-3" data-bs-toggle="modal" data-bs-target="#guideModal">
                <i class="fas fa-book-open text-primary me-2"></i> Panduan
            </button>
        </div>
    </div>

    <form action="process.php" method="POST" id="form-rapot">
        <input type="hidden" name="csrf_token" value="<?php echo csrf_generate(); ?>">
        <?php if ($is_edit && $data_sumber): ?>
            <input type="hidden" name="edit_id" value="<?php echo $data_sumber['id']; ?>">
        <?php endif; ?>

        <div class="card form-card border-start border-4 border-secondary">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 fw-bold text-secondary"><i class="fas fa-user-check me-2"></i>Data Utama</h6>
            </div>
            <div class="card-body bg-white">
                        <div class="row g-3">
                            <div class="col-md-5">
                                <div class="form-group mb-0">
                                    <label for="santri_id" class="fw-bold mb-2">Pilih Santri</label>
                                    <select name="santri_id" id="santri_id" class="form-control" required>
                                        <?php if (empty($santri_list) && !empty($filter_kamar_create) && !$data_sumber): ?>
                                            <option value="">-- Tidak ada santri di kamar <?php echo htmlspecialchars($filter_kamar_create); ?> --</option>
                                        <?php else: ?>
                                            <option value="">-- Pilih Santri --</option>
                                        <?php endif; ?>
                                        <?php foreach ($santri_list as $santri): ?>
                                            <option
                                                value="<?php echo $santri['id']; ?>"
                                                data-nama="<?php echo htmlspecialchars($santri['nama'], ENT_QUOTES, 'UTF-8'); ?>"
                                                data-kamar="<?php echo htmlspecialchars($santri['kamar'], ENT_QUOTES, 'UTF-8'); ?>"
                                                <?php echo ($data_sumber && $data_sumber['santri_id'] == $santri['id']) ? 'selected' : ''; ?>>
                                                (Kamar <?php echo htmlspecialchars($santri['kamar'], ENT_QUOTES, 'UTF-8'); ?>) - <?php echo htmlspecialchars($santri['nama'], ENT_QUOTES, 'UTF-8'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <!-- Info: akan diisi JS setelah cek existing rapot -->
                                    <div id="info-existing-santri" class="mt-2" style="display:none;">
                                        <div class="alert alert-info alert-sm py-2 px-3 mb-0 small d-flex align-items-start gap-2" style="border-radius: 0.6rem; font-size: 0.8rem;">
                                            <i class="fas fa-info-circle mt-1 flex-shrink-0 text-info"></i>
                                            <span>Santri yang <strong>sudah memiliki rapot</strong> untuk bulan &amp; tahun yang dipilih akan ditampilkan dengan tanda <strong class="text-danger">✗</strong> dan tidak dapat dipilih. Ganti bulan/tahun untuk melihat pilihan lain.</span>
                                        </div>
                                    </div>
                                    <!-- Warning: muncul jika santri yang dipilih ternyata sudah punya rapot -->
                                    <div id="warning-duplicate-santri" class="mt-2" style="display:none;">
                                        <div class="alert alert-danger py-2 px-3 mb-0 small d-flex align-items-start gap-2" style="border-radius: 0.6rem; font-size: 0.8rem;">
                                            <i class="fas fa-exclamation-triangle mt-1 flex-shrink-0"></i>
                                            <span id="warning-duplicate-text">Santri ini sudah memiliki rapot untuk bulan dan tahun yang dipilih. Rapot baru tidak dapat disimpan.</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-0">
                                    <label for="bulan" class="fw-bold mb-2">Bulan</label>
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
                                    <label for="tahun" class="fw-bold mb-2">Tahun</label>
                                    <input type="number" name="tahun" id="tahun" class="form-control"
                                           value="<?php echo $tahun_default; ?>"
                                           min="2000" max="2099" required>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-12">
                                <div id="rapot-progress-container"></div>
                            </div>
                        </div>
                    </div>
                </div>


                <?php
                function buatTombolPilihan($key, $data_sumber = null) {
                    $html = "<div class='btn-group btn-group-toggle flex-wrap' data-bs-toggle='buttons'>";
                    $dropdown_options_html = generatePenilaianDropdown($key);
                    $checked_value = $data_sumber ? ($data_sumber[$key] ?? null) : null;
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

                <div class="card form-card border-start border-4 border-success">
                    <div class="card-header bg-white py-3 card-header-flex">
                        <h6 class="m-0 fw-bold text-success"><i class="fas fa-pray me-2"></i>Mutu: Ibadah</h6>
                    </div>
                    <div class="card-body bg-white">
                        <div class="mb-3"><label class="question-label">Puasa Sunnah</label><?php echo buatTombolPilihan('puasa_sunnah', $data_sumber); ?></div>
                        <div class="mb-3"><label class="question-label">Sholat Duha</label><?php echo buatTombolPilihan('sholat_duha', $data_sumber); ?></div>
                        <div class="mb-3"><label class="question-label">Sholat Malam</label><?php echo buatTombolPilihan('sholat_malam', $data_sumber); ?></div>
                        <div class="mb-3"><label class="question-label">Sedekah & Berbagi</label><?php echo buatTombolPilihan('sedekah', $data_sumber); ?></div>
                        <div class="mb-3"><label class="question-label">Sunnah sebelum tidur</label><?php echo buatTombolPilihan('sunnah_tidur', $data_sumber); ?></div>
                        <div class="mb-3"><label class="question-label">Ibadah lainnya</label><?php echo buatTombolPilihan('ibadah_lainnya', $data_sumber); ?></div>
                    </div>
                </div>

                <div class="card form-card border-start border-4 border-primary">
                    <div class="card-header bg-white py-3">
                        <h6 class="m-0 fw-bold text-primary"><i class="fas fa-smile-beam me-2"></i>Mutu: Akhlaq</h6>
                    </div>
                    <div class="card-body bg-white">
                        <div class="mb-3"><label class="question-label">Lisan</label><?php echo buatTombolPilihan('lisan', $data_sumber); ?></div>
                        <div class="mb-3"><label class="question-label">Sikap & tingkah laku</label><?php echo buatTombolPilihan('sikap', $data_sumber); ?></div>
                        <div class="mb-3"><label class="question-label">Kesopanan</label><?php echo buatTombolPilihan('kesopanan', $data_sumber); ?></div>
                        <div class="mb-3"><label class="question-label">Muamalah</label><?php echo buatTombolPilihan('muamalah', $data_sumber); ?></div>
                    </div>
                </div>

                <div class="card form-card border-start border-4 border-warning">
                    <div class="card-header bg-white py-3">
                        <h6 class="m-0 fw-bold text-warning"><i class="fas fa-clock me-2"></i>Mutu: Kedisiplinan</h6>
                    </div>
                    <div class="card-body bg-white">
                        <div class="mb-3"><label class="question-label">Tidur</label><?php echo buatTombolPilihan('tidur', $data_sumber); ?></div>
                        <div class="mb-3"><label class="question-label">Keterlambatan</label><?php echo buatTombolPilihan('keterlambatan', $data_sumber); ?></div>
                        <div class="mb-3"><label class="question-label">Seragam</label><?php echo buatTombolPilihan('seragam', $data_sumber); ?></div>
                        <div class="mb-3"><label class="question-label">Makan</label><?php echo buatTombolPilihan('makan', $data_sumber); ?></div>
                        <div class="mb-3"><label class="question-label">Mengikuti arahan</label><?php echo buatTombolPilihan('arahan', $data_sumber); ?></div>
                        <div class="mb-3"><label class="question-label">Berbahasa arab di kamar</label><?php echo buatTombolPilihan('bahasa_arab', $data_sumber); ?></div>
                    </div>
                </div>

                <div class="card form-card border-start border-4 border-info">
                    <div class="card-header bg-white py-3">
                        <h6 class="m-0 fw-bold text-info"><i class="fas fa-broom me-2"></i>Mutu: Kebersihan & Kerapihan</h6>
                    </div>
                    <div class="card-body bg-white">
                        <div class="mb-3"><label class="question-label">Mandi</label><?php echo buatTombolPilihan('mandi', $data_sumber); ?></div>
                        <div class="mb-3"><label class="question-label">Penampilan & berpakaian</label><?php echo buatTombolPilihan('penampilan', $data_sumber); ?></div>
                        <div class="mb-3"><label class="question-label">Piket</label><?php echo buatTombolPilihan('piket', $data_sumber); ?></div>
                        <div class="mb-3"><label class="question-label">Kerapihan barang</label><?php echo buatTombolPilihan('kerapihan_barang', $data_sumber); ?></div>
                    </div>
                </div>

                <!-- DUA CARD UNTUK PELANGGARAN & REWARD -->
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="card form-card mb-0 h-100 border-start border-4 border-danger">
                            <div class="card-header bg-white py-3 card-header-flex">
                                <h6 class="m-0 fw-bold text-danger"><i class="fas fa-exclamation-triangle me-2"></i>Poin Pelanggaran</h6>
                                <button type="button" id="btn-cek-data" class="btn btn-sm btn-outline-danger rounded-pill" 
                                        data-bs-toggle="tooltip" title="Refresh data pelanggaran & reward">
                                    <i class="fas fa-sync-alt"></i>
                                </button>
                            </div>
                            <div class="card-body bg-light" id="card-rincian-poin" style="min-height: 100px;">
                                <div class="text-muted small text-center mt-3">
                                    Menunggu pemilihan santri...
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card form-card mb-0 h-100 border-start border-4 border-success">
                            <div class="card-header bg-white py-3 card-header-flex">
                                <h6 class="m-0 fw-bold text-success"><i class="fas fa-gift me-2"></i>Poin Reward</h6>
                            </div>
                            <div class="card-body bg-light" id="card-rincian-reward" style="min-height: 100px;">
                                <div class="text-muted small text-center mt-3">
                                    Menunggu pemilihan santri...
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card form-card">
                    <div class="card-body bg-white">
                        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-3">
                            <label for="catatan_musyrif" class="mb-0 fw-bold text-dark"><i class="fas fa-pen me-2 text-primary"></i>Catatan Musyrif</label>
                            <?php if (has_permission('catatan_otomatis')): ?>
                            <span tabindex="0" data-bs-toggle="tooltip" title="Harap isi semua data penilaian terlebih dahulu" id="wrapper-auto-catatan" class="align-self-stretch align-self-sm-auto d-inline-block">
                                <button type="button" id="btn-auto-catatan" class="btn btn-sm btn-info text-white border-0 shadow-sm rounded-pill fw-bold px-4 py-2 text-nowrap w-100" 
                                        style="letter-spacing: 0.3px; pointer-events: none;" disabled>
                                    <i class="fas fa-magic me-2"></i> Buat Otomatis
                                </button>
                            </span>
                            <?php endif; ?>
                        </div>
                        <div class="form-group mb-0">
                            <textarea name="catatan_musyrif" id="catatan_musyrif" class="form-control bg-light border-0" rows="5" style="border-radius: 0.75rem;" placeholder="Ketik manual atau klik tombol 'Buat Otomatis'..."><?php echo $data_sumber ? htmlspecialchars($data_sumber['catatan_musyrif']) : ''; ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="d-grid mt-4" tabindex="0" data-bs-toggle="tooltip" title="Harap isi semua data penilaian terlebih dahulu" id="wrapper-simpan">
                    <button type="submit" name="simpan_rapot" id="btn-simpan" class="btn btn-primary btn-lg rounded-pill fw-bold shadow-sm" style="padding: 0.85rem; pointer-events: none;" disabled>
                        <i class="fas fa-save me-2"></i> Simpan Data Rapot
                    </button>
                </div>
            </form>
</div>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>

<script>
$(document).ready(function() {
    $('#santri_id').select2({ 
        theme: "bootstrap-5", 
        dropdownParent: $('#santri_id').parent(),
        width: '100%'
    });

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
    if (guideModalElement) guideModalElement.addEventListener('hide.bs.modal', () => {
        if (modalTriggerElement) modalTriggerElement.focus();
        modalTriggerElement = null;
    });

    // ================================================================
    // === FILTER EXISTING RAPOT (CEGAH DUPLIKAT) =====================
    // ================================================================
    // Set ini menyimpan santri_id yang sudah punya rapot di bulan+tahun terpilih.
    var existingRapotIds = new Set();
    var hasDuplicate = false; // flag: apakah santri yg dipilih sudah punya rapot?
    var existingFilterTimeout = null;

    var existingXhr = null; // track in-flight XHR untuk di-abort jika ada request baru

    /**
     * Fetch daftar santri_id yang sudah punya rapot di bulan+tahun yang dipilih,
     * lalu update tampilan dropdown: disable opsi yang sudah ada, tampilkan info.
     */
    function filterExistingRapot() {
        var bulan = $('#bulan').val();
        var tahun = parseInt($('#tahun').val(), 10);

        // Reset semua opsi terlebih dahulu
        resetDropdownOptions();

        // Abort request sebelumnya jika masih berjalan (cegah race condition)
        if (existingXhr) {
            existingXhr.abort();
            existingXhr = null;
        }

        if (!bulan || !tahun || tahun < 2000 || tahun > 2099) {
            existingRapotIds.clear();
            $('#info-existing-santri').hide();
            checkSelectedSantriDuplicate();
            return;
        }

        // Debounce 250ms (terutama penting saat user ketik di input tahun)
        clearTimeout(existingFilterTimeout);
        // Capture nilai bulan/tahun saat ini agar closure tetap valid
        var requestedBulan = bulan;
        var requestedTahun = tahun;
        existingFilterTimeout = setTimeout(function() {
            var postData = { bulan: requestedBulan, tahun: requestedTahun };
            var edit_id_val = $('input[name="edit_id"]').val();
            if (edit_id_val) postData.edit_id = edit_id_val;

            existingXhr = $.post('../api/get_existing_rapot.php', postData, function(res) {
                existingXhr = null;
                // Pastikan response masih relevan (bulan/tahun saat ini sama dengan yang di-request)
                if ($('#bulan').val() !== requestedBulan || parseInt($('#tahun').val(), 10) !== requestedTahun) return;
                if (res && Array.isArray(res.existing)) {
                    existingRapotIds = new Set(res.existing.map(Number));
                    applyDropdownFilter(requestedBulan, requestedTahun);
                }
            }, 'json').fail(function(xhr, status) {
                existingXhr = null;
                if (status === 'abort') return; // diabort secara sengaja, abaikan
                // Gagal fetch = biarkan semua opsi aktif (aman, backend tetap protect)
                existingRapotIds.clear();
                applyDropdownFilter(requestedBulan, requestedTahun);
            });
        }, 250);
    }


    /**
     * Reset semua opsi dropdown ke kondisi awal (tidak disabled, tidak ada prefix).
     */
    function resetDropdownOptions() {
        $('#santri_id option').each(function() {
            var $opt = $(this);
            if ($opt.val() === '') return; // skip placeholder
            var nama   = $opt.data('nama')   || '';
            var kamar  = $opt.data('kamar')  || '';
            $opt.prop('disabled', false);
            $opt.text('(Kamar ' + kamar + ') - ' + nama);
        });
        // Refresh Select2 untuk menerapkan perubahan
        $('#santri_id').trigger('change.select2');
    }

    /**
     * Terapkan filter: disable opsi yang sudah ada rapotnya,
     * tambahkan prefix tanda ✗ pada nama mereka.
     */
    function applyDropdownFilter(bulan, tahun) {
        var currentVal = parseInt($('#santri_id').val(), 10);
        var anyExisting = existingRapotIds.size > 0;

        $('#santri_id option').each(function() {
            var $opt = $(this);
            if ($opt.val() === '') return; // skip placeholder
            var id     = parseInt($opt.val(), 10);
            var nama   = $opt.data('nama')  || '';
            var kamar  = $opt.data('kamar') || '';

            if (existingRapotIds.has(id)) {
                // Santri ini sudah punya rapot di bulan+tahun ini
                $opt.prop('disabled', true);
                $opt.text('✗ Sudah ada – (Kamar ' + kamar + ') ' + nama);
            } else {
                $opt.prop('disabled', false);
                $opt.text('(Kamar ' + kamar + ') - ' + nama);
            }
        });

        // Refresh Select2
        $('#santri_id').trigger('change.select2');

        // Tampilkan info banner jika ada santri yang sudah punya rapot
        if (anyExisting) {
            $('#info-existing-santri').show();
        } else {
            $('#info-existing-santri').hide();
        }

        // Cek apakah santri yang sedang dipilih masuk dalam daftar existing
        checkSelectedSantriDuplicate();
    }

    /**
     * Cek apakah santri yang sedang dipilih sudah punya rapot.
     * Tampilkan warning merah dan block tombol simpan jika ya.
     */
    function checkSelectedSantriDuplicate() {
        var selectedId = parseInt($('#santri_id').val(), 10);
        var bulan = $('#bulan').val();
        var tahun = $('#tahun').val();

        if (selectedId && existingRapotIds.has(selectedId)) {
            hasDuplicate = true;
            $('#warning-duplicate-text').text(
                'Santri ini sudah memiliki rapot untuk ' + bulan + ' ' + tahun +
                '. Rapot baru tidak dapat disimpan. Pilih santri lain atau ubah bulan/tahun.'
            );
            $('#warning-duplicate-santri').show();
        } else {
            hasDuplicate = false;
            $('#warning-duplicate-santri').hide();
        }

        // Re-evaluate form validity setelah flag berubah
        checkFormValidity();
    }

    // ================================================================
    // === VALIDASI FORM ===============================================
    // ================================================================
    var autoCatatanBtn = $('#btn-auto-catatan');
    var simpanBtn = $('#btn-simpan');
    var formRapot = $('#form-rapot');

    function checkFormValidity() {
        var santriVal  = $('#santri_id').val();
        var bulanVal   = $('#bulan').val();
        var tahunVal   = $('#tahun').val();
        var tahunNum   = parseInt(tahunVal, 10);
        var tahunOk    = tahunNum >= 2000 && tahunNum <= 2099;

        var isBaseOk = (santriVal !== '' && bulanVal !== '' && tahunVal !== '' && tahunOk && !hasDuplicate);
        var totalChecked = formRapot.find('input[type="radio"]:checked').length;
        var catatanText  = $('#catatan_musyrif').val().trim();
        var catatanValid = catatanText.length >= 15 && (catatanText.match(/[a-zA-Z0-9]/g) || []).length >= 10;
        
        var isAutoBtnValid = isBaseOk && (totalChecked >= 20);
        var isFormValid    = isAutoBtnValid && catatanValid;

        autoCatatanBtn.prop('disabled', !isAutoBtnValid);
        simpanBtn.prop('disabled', !isFormValid);

        // Toggle pointer-events so button can be clicked when valid
        autoCatatanBtn.css('pointer-events', isAutoBtnValid ? 'auto' : 'none');
        simpanBtn.css('pointer-events', isFormValid ? 'auto' : 'none');

        var wrapperAutoCatatan = document.getElementById('wrapper-auto-catatan');
        if (wrapperAutoCatatan) {
            var autoBtnMsg = isAutoBtnValid
                ? 'Buat catatan berdasarkan nilai di atas'
                : (hasDuplicate ? 'Santri ini sudah memiliki rapot di bulan & tahun ini' : 'Harap isi semua data penilaian terlebih dahulu');
            updateTooltip(wrapperAutoCatatan, autoBtnMsg);
        }

        var simpanMsg = isFormValid
            ? 'Simpan data rapot'
            : (hasDuplicate
                ? 'Santri ini sudah memiliki rapot di bulan & tahun ini — tidak bisa disimpan'
                : 'Harap lengkapi penilaian dan isi catatan (min 15 karakter jelas)');
        updateTooltip(document.getElementById('wrapper-simpan'), simpanMsg);
    }

    checkFormValidity();
    formRapot.on('change', checkFormValidity);
    $('#catatan_musyrif').on('input change', checkFormValidity);

    // Saat santri dipilih: cek apakah dia duplikat
    $('#santri_id').on('change', function() {
        checkSelectedSantriDuplicate();
        autoFetchPoinReward();
    });

    // Saat bulan/tahun berubah: re-filter dropdown DAN fetch ulang poin+reward
    // Untuk #tahun: pakai 'input' juga agar filter jalan saat user ketik, bukan hanya saat blur
    $('#bulan').on('change', function() {
        filterExistingRapot();
        autoFetchPoinReward();
    });
    $('#tahun').on('change input', function() {
        filterExistingRapot();
        autoFetchPoinReward();
    });

    // ================================================================
    // === AUTO FETCH POIN & REWARD ====================================
    // ================================================================
    let fetchTimeout = null;

    function autoFetchPoinReward() {
        var santri = $('#santri_id').val();
        var bulan  = $('#bulan').val();
        var tahun  = $('#tahun').val();

        if (!santri || !bulan || !tahun) {
            $('#card-rincian-poin, #card-rincian-reward').html('<div class="text-muted small text-center mt-3">Menunggu pemilihan santri, bulan, dan tahun...</div>');
            return;
        }

        // Clear previous timeout
        if (fetchTimeout) clearTimeout(fetchTimeout);

        // Debounce: tunggu 300ms setelah user berhenti mengubah
        fetchTimeout = setTimeout(function() {
            $('#card-rincian-poin, #card-rincian-reward').html('<div class="text-center p-3"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i></div>');

            var data = { santri_id: santri, bulan: bulan, tahun: tahun };
            var edit_id_val = $('input[name="edit_id"]').val();
            if (edit_id_val) {
                data.edit_id = edit_id_val;
            }

            $.post('../api/get_pelanggaran_santri.php', data, function(res) {
                $('#card-rincian-poin').html(res);
            }).fail(function() {
                $('#card-rincian-poin').html('<div class="alert alert-danger mb-0">Gagal memuat data pelanggaran.</div>');
            });

            $.post('../api/get_reward_santri.php', data, function(res) {
                $('#card-rincian-reward').html(res);
            }).fail(function() {
                $('#card-rincian-reward').html('<div class="alert alert-danger mb-0">Gagal memuat data reward.</div>');
            });

            $.post('../api/get_rapot_count.php', data, function(res) {
                $('#rapot-progress-container').html(res);
            });
        }, 300);
    }

    // Manual refresh via button
    $('#btn-cek-data').on('click', function() {
        filterExistingRapot();
        autoFetchPoinReward();
    });

    // Jalankan filter + fetch sekali saat halaman pertama kali load
    filterExistingRapot();
    if ($('#santri_id').val() && $('#bulan').val() && $('#tahun').val()) {
        autoFetchPoinReward();
    }

    // ================================================================
    // === AUTO CATATAN ================================================
    // ================================================================
    $('#btn-auto-catatan').on('click', function() {
        var btn = $(this);
        var originalText = btn.html();
        btn.html('<i class="fas fa-spinner fa-spin me-1"></i> Memproses...').prop('disabled', true);

        $.post('../api/generate_catatan.php', $('#form-rapot').serialize(), function(response) {
            $('#catatan_musyrif').val(response);
            btn.html(originalText).prop('disabled', false);
            checkFormValidity();
        }).fail(function(xhr, status, error) {
            showAlert('Gagal membuat catatan otomatis. Cek koneksi atau hubungi admin.', 'error');
            console.error('Error:', xhr.responseText);
            btn.html(originalText).prop('disabled', false);
        });
    });
});
</script>


<!-- MODAL PANDUAN -->
<div class="modal fade" id="guideModal" tabindex="-1" aria-labelledby="guideModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 1rem;">
      <div class="modal-header border-bottom-0 pb-0 mt-2 mx-2">
        <h5 class="modal-title fw-bolder text-dark" id="guideModalLabel">
            <i class="fas fa-book-open text-primary me-2"></i>Panduan Form Rapot
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body pt-3 px-4 pb-4 text-muted">
        <p class="mb-4 small">Gunakan halaman ini untuk mengisi nilai atau menduplikat rapot dari bulan sebelumnya.</p>
        
        <h6 class="text-dark fw-bold mb-2"><i class="fas fa-user-check text-primary me-2"></i>1. Data Utama</h6>
        <ul class="mb-4 small" style="padding-left: 1.25rem;">
          <li class="mb-1"><strong>Pilih Santri:</strong> Anda bisa mengetik untuk mencari nama santri.</li>
          <li><strong>Bulan & Tahun:</strong> Otomatis menyesuaikan bulan berjalan.</li>
        </ul>

        <h6 class="text-dark fw-bold mb-2"><i class="fas fa-copy text-primary me-2"></i>2. Mode Duplikat</h6>
        <ul class="mb-4 small" style="padding-left: 1.25rem;">
          <li class="mb-1">Bila masuk lewat tombol <strong>Duplikat</strong>, semua nilai bulan lalu sudah terisi.</li>
          <li class="mb-1">Bulan & Tahun otomatis maju <strong>satu bulan</strong>.</li>
          <li>Periksa lagi nilainya sebelum menekan tombol simpan!</li>
        </ul>

        <h6 class="text-dark fw-bold mb-2"><i class="fas fa-sync-alt text-primary me-2"></i>3. Cek Pelanggaran & Reward</h6>
        <ul class="mb-4 small" style="padding-left: 1.25rem;">
          <li class="mb-1">Riwayat pelanggaran akan muncul setelah memilih santri & bulan.</li>
          <li>Gunakan tombol <i class="fas fa-sync-alt"></i> (Refresh) untuk memuat ulang data.</li>
        </ul>

        <h6 class="text-dark fw-bold mb-2"><i class="fas fa-magic text-primary me-2"></i>4. Catatan Otomatis</h6>
        <ul class="mb-0 small" style="padding-left: 1.25rem;">
          <li class="mb-1">Tombol <strong>Simpan</strong> dan <strong>Buat Otomatis</strong> aktif jika seluruh 20 nilai terisi.</li>
          <li>Sistem AI akan merangkum aspek nilai untuk dijadikan catatan wali asrama.</li>
        </ul>
      </div>
      <div class="modal-footer border-top-0 pt-0 pb-4 px-4">
        <button type="button" class="btn btn-primary w-100 fw-medium shadow-sm" style="border-radius: 0.75rem;" data-bs-dismiss="modal">Saya Mengerti</button>
      </div>
    </div>
  </div>
</div>
