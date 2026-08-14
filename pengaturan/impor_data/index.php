<?php
/**
 * pengaturan/impor_data/index.php
 * Halaman Sinkronisasi Data (Impor Massal dari Excel/CSV)
 *
 * Tipe data yang didukung:
 *   - santri              → tabel santri
 *   - jenis_pelanggaran   → tabel jenis_pelanggaran
 *   - jenis_reward        → tabel jenis_reward
 */
require_once __DIR__ . '/../../bootstrap/init.php';
guard('impor_data');

require_once __DIR__ . '/smart_reader.php';

// ── CSRF Token ───────────────────────────────────────────────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ── Bersihkan file temp lama (> 1 jam) ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $files = glob(sys_get_temp_dir() . '/sync_*') ?: [];
    $now = time();
    foreach ($files as $f) {
        if (is_file($f) && ($now - filemtime($f)) > 3600) @unlink($f);
    }
}

// ── Batalkan Pratinjau via GET ──────────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'cancel') {
    if (!empty($_SESSION['sync_temp_file']) && file_exists($_SESSION['sync_temp_file'])) @unlink($_SESSION['sync_temp_file']);
    if (!empty($_SESSION['sync_preview_file']) && file_exists($_SESSION['sync_preview_file'])) @unlink($_SESSION['sync_preview_file']);
    unset($_SESSION['sync_preview_data'], $_SESSION['sync_temp_file'], $_SESSION['sync_type'], $_SESSION['sync_mode'], $_SESSION['sync_preview_file']);
    header("Location: index.php");
    exit;
}

// ── Flash messages dari session ────────────────────────────────────────────
$error_msg   = null;
$error_type  = null;
$success_msg = null;

if (isset($_SESSION['sync_error_msg'])) {
    $error_msg  = $_SESSION['sync_error_msg'];
    $error_type = $_SESSION['sync_error_type'] ?? null;
    unset($_SESSION['sync_error_msg'], $_SESSION['sync_error_type']);
}
if (isset($_SESSION['sync_success_msg'])) {
    $success_msg = $_SESSION['sync_success_msg'];
    unset($_SESSION['sync_success_msg']);
}

$type         = $_SESSION['sync_type']         ?? null;
$preview_data = null;

// Initialize counter variables to prevent IDE lint warnings
$cnt_insert  = 0;
$cnt_fatal   = 0;
$cnt_error   = 0;
$cnt_update  = 0;
$cnt_delete  = 0;

// BUG FIX 1: Read preview data from JSON file instead of Session
if (!empty($_SESSION['sync_preview_file']) && file_exists($_SESSION['sync_preview_file'])) {
    $preview_data = json_decode(file_get_contents($_SESSION['sync_preview_file']), true);
} elseif (isset($_SESSION['sync_preview_data'])) {
    $preview_data = $_SESSION['sync_preview_data'];
}

// Cek status data aktif untuk Tutup Buku
$q_aktif = mysqli_query($conn, "SELECT (SELECT COUNT(*) FROM pelanggaran) + (SELECT COUNT(*) FROM daftar_reward) AS total");
$cek_data_aktif = $q_aktif ? (int)mysqli_fetch_assoc($q_aktif)['total'] : 0;
$has_active_data = $cek_data_aktif > 0;

// ── Helper format tampilan ─────────────────────────────────────────────────
function fmt_kelas($k): string {
    $s = trim((string)$k);
    if ($s === '00' || $s === '0') return '<span class="sf-badge slate" style="font-size:.72rem">Staff/Umum</span>';
    return '<span>' . htmlspecialchars($s) . '</span>';
}
function fmt_kamar($k): string {
    $s = trim((string)$k);
    if ($s === '00' || $s === '0') return '<span class="text-muted">—</span>';
    return htmlspecialchars($s);
}
function fmt_bagian(string $b): string {
    $colors = [
        'Bahasa'     => '#2563eb',
        'Diniyyah'   => '#d97706',
        'Kesantrian' => '#475569',
        'Pengabdian' => '#059669',
        'Tahfidz'    => '#7c3aed',
    ];
    $c = $colors[$b] ?? '#475569';
    return "<span style=\"background:rgba(0,0,0,.06);color:{$c};padding:2px 8px;border-radius:4px;font-size:.78rem;font-weight:600\">"
         . htmlspecialchars($b) . "</span>";
}
function fmt_kategori(string $k): string {
    $s = [
        'Ringan'       => 'background:#dcfce7;color:#166534',
        'Sedang'       => 'background:#fef9c3;color:#854d0e',
        'Berat'        => 'background:#fee2e2;color:#991b1b',
        'Sangat Berat' => 'background:#4c0519;color:#fecdd3',
    ];
    $style = $s[$k] ?? '';
    return "<span style=\"{$style};padding:2px 9px;border-radius:4px;font-size:.78rem;font-weight:600\">"
         . htmlspecialchars($k) . "</span>";
}

require_once __DIR__ . '/../../layouts/header.php';
?>

<style>
/* ── Alerts ─────────────────────────────────────── */
.sf-alert { border-radius:12px; padding:18px 20px; font-size:.9rem; margin-bottom:1.5rem;
            border-left:4px solid; box-shadow:0 4px 6px -1px rgba(0,0,0,.05); }
.sf-alert-danger  { background:#fff5f5; border-color:#f56565; color:#9b2c2c; }
.sf-alert-success { background:#f0fdf4; border-color:#22c55e; color:#166534; }
.sf-alert-title   { font-weight:700; font-size:.97rem; margin-bottom:6px;
                    display:flex; align-items:center; gap:8px; }
.sf-alert-action  { display:inline-flex; align-items:center; gap:6px;
                    background:#fff; border:1px solid #feb2b2; color:#e53e3e;
                    padding:7px 14px; border-radius:8px; font-weight:600;
                    font-size:.82rem; cursor:pointer; transition:all .2s; margin-top:12px; }
.sf-alert-action:hover { background:#e53e3e; color:#fff; }

/* ── Cards ──────────────────────────────────────── */
.sf-card { background:#fff; border-radius:12px; border:1px solid #e5e7eb;
           box-shadow:0 4px 6px -1px rgba(0,0,0,.04); margin-bottom:24px; overflow:hidden; }
.sf-card-header { background:#f8fafc; border-bottom:1px solid #e5e7eb;
                  padding:14px 20px; font-weight:600; color:#1e293b;
                  display:flex; align-items:center; gap:8px; font-size:.95rem; }
.sf-card-body { padding:24px; }

/* ── Badge aksi ─────────────────────────────────── */
.act-badge { padding:3px 10px; border-radius:6px; font-size:.74rem; font-weight:700; letter-spacing:.4px; }
.act-insert { background:#dcfce7; color:#166534; }
.act-update { background:#fef9c3; color:#854d0e; }
.act-delete { background:#fee2e2; color:#991b1b; }
.act-fatal  { background:#fed7d7; color:#9b2c2c; border:1px solid #feb2b2; }
.act-error  { background:#fce7f3; color:#be185d; border:1px solid #f9a8d4; }

.row-insert { background:#f0fdf4 !important; }
.row-update { background:#fefce8 !important; }
.row-delete { background:#fef2f2 !important; }
.row-fatal  { background:#fff5f5 !important; border-left:4px solid #e53e3e !important; }
.row-error  { background:#fdf2f8 !important; border-left:4px solid #db2777 !important; }

/* ── Filter badges (summary bar) ─────────────────── */
.filter-badge { cursor:pointer; transition:opacity .15s; user-select:none; opacity:1; }
.filter-badge:hover { opacity:.78; }
.filter-badge.active { outline:2.5px solid #334155; outline-offset:2px; opacity:1; }
.filter-badge.active::before { content:"✕ "; font-size:.7rem; }
.preview-row-hidden { display:none !important; }
#no-filter-result { display:none; }

.diff-old { font-size:.8rem; color:#ef4444; text-decoration:line-through; text-decoration-color:#ef4444; text-decoration-thickness:1.5px; background:#fef2f2; padding:2px 6px; border-radius:4px; border:1px solid #fca5a5; margin-right:20px; position:relative; display:inline-block; opacity:0.85; }
.diff-old::after { content:"➔"; position:absolute; right:-18px; top:50%; transform:translateY(-50%); color:#94a3b8; font-size:0.85rem; text-decoration:none !important; display:inline-block; }
.diff-new { font-size:.85rem; font-weight:600; color:#059669; background:#ecfdf5; padding:2px 6px; border-radius:4px; border:1px solid #6ee7b7; display:inline-block; }
.fatal-reason { font-size:.77rem; color:#9b2c2c; line-height:1.4; margin-top:6px;
                background:#fff; padding:7px 12px; border-radius:6px;
                border:1px solid #feb2b2; display:block; }

/* ── Fatal warning card ──────────────────────────── */
.fatal-card { background:#fff5f5; border:1px solid #feb2b2; border-left:6px solid #e53e3e;
              border-radius:12px; padding:22px; margin:12px 12px 0; }
.fatal-icon { width:44px; height:44px; background:#fed7d7; border-radius:10px;
              display:flex; align-items:center; justify-content:center; color:#e53e3e; }
.pulse { animation:pulse 2s cubic-bezier(.4,0,.6,1) infinite; }
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.45} }

/* ── Upload zone ─────────────────────────────────── */
.upload-zone { position:relative; border:2px dashed #cbd5e1; border-radius:14px;
               padding:44px 24px; text-align:center;
               background:linear-gradient(145deg,#f8fafc,#f1f5f9);
               transition:all .3s; cursor:pointer; }
.upload-zone:hover { border-color:#3b82f6; background:linear-gradient(145deg,#eff6ff,#dbeafe);
                     transform:translateY(-2px); }
.upload-zone.dragover { border-color:#10b981; background:linear-gradient(145deg,#ecfdf5,#d1fae5); }
.upload-zone input[type=file] { position:absolute; inset:0; opacity:0; cursor:pointer; }

/* ── Template buttons ────────────────────────────── */
.tpl-btn { display:inline-flex; align-items:center; gap:7px; padding:8px 16px;
           border-radius:10px; font-size:.82rem; font-weight:600;
           text-decoration:none; border:1.5px solid; transition:all .2s; }
.tpl-btn:hover { transform:translateY(-1px); box-shadow:0 4px 12px rgba(0,0,0,.1); }
.tpl-santri       { background:#eef2ff; border-color:#4f46e5; color:#4338ca; }
.tpl-santri:hover { background:#4f46e5; border-color:#4f46e5; color:#fff; }
.tpl-pelanggaran  { background:#fff1f2; border-color:#e11d48; color:#be123c; }
.tpl-pelanggaran:hover { background:#e11d48; border-color:#e11d48; color:#fff; }
.tpl-reward       { background:#ecfdf5; border-color:#10b981; color:#047857; }
.tpl-reward:hover { background:#10b981; border-color:#10b981; color:#fff; }

</style>

<div class="container-fluid py-4 px-4">

    <!-- Breadcrumb -->
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="<?= $preview_data !== null ? '?action=cancel' : '../index.php' ?>" class="btn btn-light rounded-circle shadow-sm"
           style="width:38px;height:38px;display:flex;align-items:center;justify-content:center">
            <i class="fas fa-arrow-left text-secondary"></i>
        </a>
        <div>
            <h4 class="fw-bold mb-0 text-dark d-flex align-items-center gap-2">
                Sinkronisasi Data
                <button type="button" class="btn btn-sm btn-light rounded-circle shadow-sm p-0 d-flex align-items-center justify-content-center" data-bs-toggle="modal" data-bs-target="#infoSyncModal" title="Panduan Detail Sinkronisasi" style="width: 28px; height: 28px;">
                    <i class="bi bi-info text-primary fs-5"></i>
                </button>
            </h4>
            <p class="text-muted small mb-0">Impor data dari Excel / CSV sebagai sumber kebenaran (Source of Truth)</p>
        </div>
    </div>

    <?php display_flash_message(); ?>

    <!-- ── Flash: Error ────────────────────────────────────────────────────── -->
    <?php if ($error_msg): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    title: '<?= $error_type === "csrf" ? "Sesi Berakhir" : "Kendala Sinkronisasi" ?>',
                    html: <?= json_encode('<div class="small text-muted mb-3">Harap perhatikan detail berikut sebelum melanjutkan.</div><div class="p-3 bg-danger bg-opacity-10 rounded-3 border border-danger border-opacity-25 text-start" style="font-size:0.9rem; max-height:60vh; overflow-y:auto;">' . $error_msg . '</div>') ?>,
                    icon: 'error',
                    width: '420px',
                    confirmButtonColor: '#e53e3e',
                    confirmButtonText: '<i class="bi bi-x-circle me-1"></i> Tutup & Perbaiki',
                    customClass: {
                        popup: 'rounded-4 shadow-lg border-0 p-3 p-md-4 max-w-sm',
                        title: 'fw-bold fs-5 text-danger mb-1',
                        htmlContainer: 'px-0 mt-0',
                        confirmButton: 'rounded-pill px-4 fw-bold shadow-sm'
                    }
                }).then(() => {
                    <?php if ($error_type === "csrf"): ?>
                    location.reload();
                    <?php endif; ?>
                });
            });
        </script>
    <?php endif; ?>

    <!-- ── Flash: Sukses ──────────────────────────────────────────────────── -->
    <?php if ($success_msg): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    title: 'Sinkronisasi Selesai!',
                    html: <?= json_encode('<div class="p-3 bg-success bg-opacity-10 rounded-3 border border-success border-opacity-25 text-start" style="font-size:0.92rem; max-height:60vh; overflow-y:auto;">' . $success_msg . '</div>') ?>,
                    icon: 'success',
                    width: '400px',
                    confirmButtonColor: '#059669',
                    confirmButtonText: '<i class="bi bi-check2-circle me-1"></i> Selesai',
                    customClass: {
                        popup: 'rounded-4 shadow-lg border-0 p-3 p-md-4 max-w-sm',
                        title: 'fw-bold fs-4 text-success mb-2',
                        htmlContainer: 'px-0 mt-0',
                        confirmButton: 'rounded-pill px-4 fw-bold shadow-sm'
                    }
                });
            });
        </script>
    <?php endif; ?>

    <!-- ── Panduan Mode ─────────────────────────────────────────────────── -->
    <div class="sf-card" style="border-radius:14px">
        <div class="sf-card-body p-0">
            <div class="row g-0">
                <div class="col-lg-auto bg-primary bg-opacity-10 d-none d-lg-flex align-items-center justify-content-center" style="min-width:72px">
                    <i class="bi bi-info-circle-fill text-primary fs-3"></i>
                </div>
                <div class="col p-4">
                    <h5 class="fw-bold text-dark mb-1">Panduan Mode Sinkronisasi</h5>
                    <div class="row g-3 mt-1">
                        <div class="col-md-6">
                            <div class="d-flex gap-3">
                                <div class="flex-shrink-0"><span class="badge bg-success rounded-circle p-2"><i class="bi bi-shield-check"></i></span></div>
                                <div>
                                    <div class="fw-bold text-success small">MODE AMAN (Update &amp; Insert)</div>
                                    <p class="mb-0 text-muted" style="font-size:.78rem;line-height:1.5">Hanya menambah data baru atau memperbarui yang berubah. Data lama yang tidak ada di Excel <strong>tetap aman</strong>.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex gap-3">
                                <div class="flex-shrink-0"><span class="badge bg-danger rounded-circle p-2"><i class="bi bi-exclamation-octagon"></i></span></div>
                                <div>
                                    <div class="fw-bold text-danger small">SINKRONISASI PENUH (+ Hapus)</div>
                                    <p class="mb-0 text-muted" style="font-size:.78rem;line-height:1.5">File Excel jadi sumber acuan tunggal. Data yang <strong>tidak ada di Excel</strong> akan <strong>dihapus permanen</strong>.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($preview_data === null): ?>
    <!-- ════════════════════════════════════════════════════════
         FORM UNGGAH FILE
    ═════════════════════════════════════════════════════════ -->

        <!-- Unduh Template -->
        <div class="sf-card">
            <div class="sf-card-header"><i class="bi bi-file-earmark-arrow-down text-success"></i> Unduh Template Excel</div>
            <div class="sf-card-body">
                <p class="text-muted small mb-3">Unduh template berikut agar Anda tahu format kolom yang benar sebelum mengisi dan mengunggah file.</p>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <a href="download_template.php?tipe=santri" class="tpl-btn tpl-santri" id="btn-tpl-santri">
                        <i class="bi bi-people-fill"></i> Template Santri
                    </a>
                    <a href="download_template.php?tipe=jenis_pelanggaran" class="tpl-btn tpl-pelanggaran" id="btn-tpl-pelanggaran">
                        <i class="bi bi-exclamation-octagon-fill"></i> Template Jenis Pelanggaran
                    </a>
                    <a href="download_template.php?tipe=jenis_reward" class="tpl-btn tpl-reward" id="btn-tpl-reward">
                        <i class="bi bi-trophy-fill"></i> Template Jenis Reward
                    </a>
                </div>
            </div>
        </div>



        <!-- Form Unggah -->
        <div class="sf-card">
            <div class="sf-card-header"><i class="bi bi-cloud-arrow-up text-primary"></i> Unggah File Sinkronisasi</div>
            <div class="sf-card-body">
                <form action="proses.php" method="post" enctype="multipart/form-data" id="uploadForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                    <input type="hidden" name="action" value="preview">

                    <div class="row mb-3">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <label class="form-label fw-bold small" for="tipe_data">Tipe Data</label>
                            <select name="tipe_data" id="tipe_data" class="form-select" required>
                                <option value="santri">Data Santri</option>
                                <option value="jenis_pelanggaran">Jenis Pelanggaran</option>
                                <option value="jenis_reward">Jenis Reward</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small" for="mode_sinkronisasi">Mode Sinkronisasi</label>
                            <select name="mode_sinkronisasi" id="mode_sinkronisasi" class="form-select" required>
                                <option value="update_insert">Update &amp; Insert Saja (Aman)</option>
                                <option value="full_sync">Sinkronisasi Penuh (+ Hapus Data)</option>
                            </select>
                        </div>
                    </div>



                    <div class="mb-4">
                        <label class="form-label fw-bold small">Pilih File (.xlsx / .csv)</label>
                        <div class="upload-zone" id="dropzone">
                            <i class="bi bi-file-earmark-excel" style="font-size:2.8rem;color:#10b981" id="upload-icon"></i>
                            <h5 class="mt-3 mb-1 fw-bold text-dark" id="upload-title">Klik atau Seret File ke Sini</h5>
                            <p class="text-muted mb-0 small" id="file-name-display">Mendukung format Excel (.xlsx) dan CSV (.csv)</p>
                            <input type="file" name="file_impor" id="file_impor" accept=".xlsx,.xls,.csv" required>
                        </div>
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-search me-1"></i> Pratinjau Perbandingan
                        </button>
                    </div>
                </form>
            </div>
        </div>

    <?php else: ?>
    <!-- ════════════════════════════════════════════════════════
         PANEL PRATINJAU
    ═════════════════════════════════════════════════════════ -->

        <?php
        $type_label = match ($type) {
            'jenis_pelanggaran' => 'Jenis Pelanggaran',
            'jenis_reward'      => 'Jenis Reward',
            default             => 'Santri',
        };
        $cnt_insert  = count(array_filter($preview_data, fn($d) => $d['action'] === 'INSERT' && empty($d['is_fatal'])));
        $cnt_fatal   = count(array_filter($preview_data, fn($d) => !empty($d['is_fatal'])));
        $cnt_error   = count(array_filter($preview_data, fn($d) => $d['action'] === 'ERROR'));
        $cnt_update  = count(array_filter($preview_data, fn($d) => $d['action'] === 'UPDATE'));
        $cnt_delete  = count(array_filter($preview_data, fn($d) => $d['action'] === 'DELETE'));
        ?>

        <div class="sf-card" style="border-color:#f59e0b">
            <div class="sf-card-header bg-warning bg-opacity-10 d-flex justify-content-between align-items-center" style="border-color:#f59e0b;color:#78350f">
                <div>
                    <i class="bi bi-eye-fill"></i>
                    Pratinjau Perbandingan Data — <?= htmlspecialchars($type_label) ?>
                </div>
                <?php if (($_SESSION['sync_mode'] ?? '') === 'full_sync'): ?>
                    <span class="badge bg-danger text-white rounded-pill px-3 py-2 border border-danger border-opacity-50 shadow-sm" style="font-size:0.75rem"><i class="bi bi-exclamation-triangle-fill me-1"></i> Sinkronisasi Penuh</span>
                <?php else: ?>
                    <span class="badge bg-success text-white rounded-pill px-3 py-2 border border-success border-opacity-50 shadow-sm" style="font-size:0.75rem"><i class="bi bi-shield-check me-1"></i> Mode Aman</span>
                <?php endif; ?>
            </div>

            <?php if (empty($preview_data)): ?>
                <div class="sf-card-body text-center py-5">
                    <i class="bi bi-check-circle text-success" style="font-size:2.8rem"></i>
                    <h5 class="mt-3 fw-bold">Data Sudah Sinkron</h5>
                    <p class="text-muted">Tidak ada perbedaan antara file Excel dan Database.</p>
                    <form action="proses.php" method="post" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                        <input type="hidden" name="action" value="cancel">
                        <button type="submit" class="btn btn-outline-secondary mt-2">Kembali</button>
                    </form>
                </div>
            <?php else: ?>

                <!-- Ringkasan -->
                <div class="px-3 pt-3 pb-2 border-bottom bg-light d-flex flex-wrap gap-2 align-items-center">
                    <span class="act-badge act-insert filter-badge" data-filter="INSERT" title="Klik untuk filter INSERT">+ INSERT: <?= $cnt_insert ?></span>
                    <span class="act-badge act-update filter-badge" data-filter="UPDATE" title="Klik untuk filter UPDATE">~ UPDATE: <?= $cnt_update ?></span>
                    <span class="act-badge act-delete filter-badge" data-filter="DELETE" title="Klik untuk filter DELETE">– DELETE: <?= $cnt_delete ?></span>
                    <?php if ($cnt_fatal): ?>
                        <span class="act-badge act-fatal filter-badge" data-filter="FATAL" title="Klik untuk filter FATAL"><i class="bi bi-exclamation-octagon-fill me-1"></i>FATAL: <?= $cnt_fatal ?></span>
                    <?php endif; ?>
                    <?php if ($cnt_error): ?>
                        <span class="act-badge act-error filter-badge" data-filter="ERROR" title="Klik untuk filter ERROR"><i class="bi bi-x-circle-fill me-1"></i>ERROR: <?= $cnt_error ?></span>
                    <?php endif; ?>
                    <span class="ms-auto text-muted" style="font-size:.72rem"><i class="bi bi-funnel me-1"></i>Klik badge untuk filter</span>
                </div>

                <!-- Peringatan FATAL / ERROR -->
                <?php if ($cnt_error): ?>
                <div class="fatal-card mb-0" style="background:#fdf2f8;border-color:#f9a8d4;border-left-color:#db2777">
                    <div class="d-flex gap-3 align-items-start">
                        <div class="fatal-icon pulse fs-4 flex-shrink-0" style="background:#fce7f3;color:#db2777"><i class="bi bi-x-circle-fill"></i></div>
                        <div>
                            <h6 class="fw-bold mb-2" style="color:#831843">Ditemukan <?= $cnt_error ?> Baris Bermasalah!</h6>
                            <p class="mb-2" style="font-size:.86rem;color:#9d174d;line-height:1.5">
                                Sinkronisasi tidak dapat dilanjutkan karena ada baris yang kosong, tidak valid, atau duplikat. 
                                Tombol Konfirmasi sementara dikunci.
                            </p>
                            <p class="mb-0" style="font-size:.82rem;color:#9d174d">
                                <i class="bi bi-lightbulb-fill text-warning me-1"></i>
                                <strong>Solusi:</strong> Perbaiki data di Excel berdasarkan pesan di baris bertanda merah muda, lalu unggah ulang.
                            </p>
                        </div>
                    </div>
                </div>
                <?php elseif ($cnt_fatal): ?>
                <div class="fatal-card mb-0">
                    <div class="d-flex gap-3 align-items-start">
                        <div class="fatal-icon pulse fs-4 flex-shrink-0"><i class="bi bi-shield-x"></i></div>
                        <div>
                            <h6 class="fw-bold mb-2" style="color:#742a2a">Terdeteksi <?= $cnt_fatal ?> Konflik ID &amp; Nama</h6>
                            <p class="mb-2" style="font-size:.86rem;color:#9b2c2c;line-height:1.5">
                                ID di Excel sudah terdaftar dengan nama yang <strong>sangat berbeda</strong> di database.
                                Sebagai langkah pengamanan, data lama <strong>tetap aman</strong> — baris konflik akan dibuat
                                sebagai entri baru dengan ID otomatis.
                            </p>
                            <p class="mb-0" style="font-size:.82rem;color:#9b2c2c">
                                <i class="bi bi-lightbulb-fill text-warning me-1"></i>
                                <strong>Solusi:</strong> Kosongkan kolom <strong>"ID"</strong> pada baris bersangkutan di Excel,
                                lalu unggah kembali.
                            </p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Tabel pratinjau -->
                <div id="no-filter-result" class="text-center py-4 text-muted" style="font-size:.88rem">
                    <i class="bi bi-inbox fs-4 d-block mb-2"></i>Tidak ada data untuk filter ini.
                </div>
                <div class="table-responsive" style="max-height:520px;overflow-y:auto" id="preview-table-wrap">
                    <table class="table table-hover table-bordered mb-0 align-middle" style="font-size:.88rem; white-space: nowrap;">
                        <thead class="table-light" style="position:sticky;top:0;z-index:1">
                            <tr>
                                <th style="min-width:100px" class="text-center">Aksi</th>
                                <th style="min-width:70px" class="text-center">ID</th>
                                <?php if ($type === 'santri'): ?>
                                    <th style="min-width:160px">NIS</th><th style="min-width:220px">Nama Santri</th><th style="min-width:100px">Kelas</th><th style="min-width:100px">Kamar</th>
                                <?php elseif ($type === 'jenis_pelanggaran'): ?>
                                    <th style="min-width:220px">Nama Pelanggaran</th><th style="min-width:140px">Bagian</th>
                                    <th style="min-width:80px" class="text-center">Poin</th>
                                    <th style="min-width:140px" class="text-center">Kategori</th>
                                <?php elseif ($type === 'jenis_reward'): ?>
                                    <th style="min-width:220px">Nama Reward</th><th style="min-width:120px" class="text-center">Poin</th><th style="min-width:200px">Deskripsi</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody id="preview-tbody">
                        <?php
                        // ── Urutkan: ERROR → FATAL → INSERT → UPDATE → DELETE → lainnya, lalu by nama
                        $action_order = ['ERROR'=>0,'FATAL'=>1,'INSERT'=>2,'UPDATE'=>3,'DELETE'=>4];
                        usort($preview_data, function($a, $b) use ($action_order) {
                            $aKey = ($a['action'] === 'ERROR') ? 'ERROR' : (!empty($a['is_fatal']) ? 'FATAL' : ($a['action'] ?? ''));
                            $bKey = ($b['action'] === 'ERROR') ? 'ERROR' : (!empty($b['is_fatal']) ? 'FATAL' : ($b['action'] ?? ''));
                            $aOrd = $action_order[$aKey] ?? 9;
                            $bOrd = $action_order[$bKey] ?? 9;
                            if ($aOrd !== $bOrd) return $aOrd - $bOrd;
                            // secondary sort by name
                            $aN = $a['data']['nama'] ?? $a['data']['nama_pelanggaran'] ?? $a['data']['nama_reward'] ?? '';
                            $bN = $b['data']['nama'] ?? $b['data']['nama_pelanggaran'] ?? $b['data']['nama_reward'] ?? '';
                            return strcasecmp($aN, $bN);
                        });
                        foreach ($preview_data as $row):
                            $is_fatal = !empty($row['is_fatal']);
                            $act      = $row['action'];
                            $rowCls   = match(true) {
                                $act === 'ERROR'    => 'row-error',
                                $is_fatal           => 'row-fatal',
                                $act === 'INSERT'   => 'row-insert',
                                $act === 'UPDATE'   => 'row-update',
                                $act === 'DELETE'   => 'row-delete',
                                default             => '',
                            };
                            $badgeCls = match(true) {
                                $act === 'ERROR'    => 'act-error',
                                $is_fatal           => 'act-fatal',
                                $act === 'INSERT'   => 'act-insert',
                                $act === 'UPDATE'   => 'act-update',
                                $act === 'DELETE'   => 'act-delete',
                                default             => '',
                            };
                            $actLabel = $act === 'ERROR' ? 'ERROR' : ($is_fatal ? 'FATAL' : $act);
                            $d  = $row['data'];
                            $od = $row['old_data'] ?? [];
                        ?>
                        <tr class="<?= $rowCls ?>" data-action="<?= $act === 'ERROR' ? 'ERROR' : ($is_fatal ? 'FATAL' : $act) ?>">
                            <td class="text-center"><span class="act-badge <?= $badgeCls ?>"><?= $actLabel ?></span></td>
                            <td class="text-center fw-bold <?= $is_fatal ? 'text-danger' : 'text-muted' ?>"><?= htmlspecialchars($d['id'] ?? '—') ?></td>

                            <?php if ($type === 'santri'): ?>
                                <td>
                                    <?php if ($act === 'UPDATE' && ($d['nis'] ?? '') !== ($od['nis'] ?? '')): ?>
                                        <span class="diff-old"><?= htmlspecialchars($od['nis'] ?? '—') ?></span>
                                        <span class="diff-new"><?= htmlspecialchars($d['nis'] ?? '—') ?></span>
                                    <?php else: ?>
                                        <?= htmlspecialchars($d['nis'] ?? '—') ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($act === 'ERROR'): ?>
                                        <span class="diff-new"><?= htmlspecialchars($d['nama'] ?? '') ?></span>
                                        <span class="fatal-reason" style="background:#fdf2f8;border-color:#f9a8d4;color:#9d174d"><i class="bi bi-x-circle-fill me-1"></i><?= htmlspecialchars($row['error_msg']) ?></span>
                                    <?php elseif ($is_fatal): ?>
                                        <span class="diff-new"><?= htmlspecialchars($d['nama']) ?></span>
                                        <span class="fatal-reason"><i class="bi bi-exclamation-octagon-fill me-1"></i><?= htmlspecialchars($row['fatal_reason']) ?></span>
                                    <?php elseif ($act === 'UPDATE' && ($d['nama'] ?? '') !== ($od['nama'] ?? '')): ?>
                                        <span class="diff-old"><?= htmlspecialchars($od['nama']) ?></span>
                                        <span class="diff-new"><?= htmlspecialchars($d['nama']) ?></span>
                                    <?php else: ?>
                                        <?= htmlspecialchars($d['nama'] ?? '') ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($act === 'UPDATE' && ($d['kelas'] ?? '') !== ($od['kelas'] ?? '')): ?>
                                        <span class="diff-old"><?= fmt_kelas($od['kelas']) ?></span>
                                        <span class="diff-new"><?= fmt_kelas($d['kelas']) ?></span>
                                    <?php else: ?>
                                        <?= fmt_kelas($d['kelas'] ?? '') ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($act === 'UPDATE' && ($d['kamar'] ?? '') !== ($od['kamar'] ?? '')): ?>
                                        <span class="diff-old"><?= fmt_kamar($od['kamar']) ?></span>
                                        <span class="diff-new"><?= fmt_kamar($d['kamar']) ?></span>
                                    <?php else: ?>
                                        <?= fmt_kamar($d['kamar'] ?? '') ?>
                                    <?php endif; ?>
                                </td>

                            <?php elseif ($type === 'jenis_pelanggaran'): ?>
                                <td>
                                    <?php if ($act === 'ERROR'): ?>
                                        <span class="diff-new"><?= htmlspecialchars($d['nama_pelanggaran'] ?? '') ?></span>
                                        <span class="fatal-reason" style="background:#fdf2f8;border-color:#f9a8d4;color:#9d174d"><i class="bi bi-x-circle-fill me-1"></i><?= htmlspecialchars($row['error_msg']) ?></span>
                                    <?php elseif ($is_fatal): ?>
                                        <span class="diff-new"><?= htmlspecialchars($d['nama_pelanggaran']) ?></span>
                                        <span class="fatal-reason"><i class="bi bi-exclamation-octagon-fill me-1"></i><?= htmlspecialchars($row['fatal_reason']) ?></span>
                                    <?php elseif ($act === 'UPDATE' && ($d['nama_pelanggaran'] ?? '') !== ($od['nama_pelanggaran'] ?? '')): ?>
                                        <span class="diff-old"><?= htmlspecialchars($od['nama_pelanggaran']) ?></span>
                                        <span class="diff-new"><?= htmlspecialchars($d['nama_pelanggaran']) ?></span>
                                    <?php else: ?>
                                        <?= htmlspecialchars($d['nama_pelanggaran'] ?? '') ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($act === 'UPDATE' && ($d['bagian'] ?? '') !== ($od['bagian'] ?? '')): ?>
                                        <span class="diff-old"><?= fmt_bagian($od['bagian'] ?? '') ?></span>
                                        <span class="diff-new"><?= fmt_bagian($d['bagian'] ?? '') ?></span>
                                    <?php else: ?>
                                        <?= fmt_bagian($d['bagian'] ?? '') ?>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($act === 'UPDATE' && ($d['poin'] ?? 0) != ($od['poin'] ?? 0)): ?>
                                        <span class="diff-old"><?= (int)($od['poin'] ?? 0) ?></span>
                                        <span class="diff-new"><?= (int)($d['poin'] ?? 0) ?></span>
                                    <?php else: ?>
                                        <?= (int)($d['poin'] ?? 0) ?>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($act === 'UPDATE' && ($d['kategori'] ?? '') !== ($od['kategori'] ?? '')): ?>
                                        <span class="diff-old"><?= fmt_kategori($od['kategori'] ?? '') ?></span>
                                        <span class="diff-new"><?= fmt_kategori($d['kategori'] ?? '') ?></span>
                                    <?php else: ?>
                                        <?= fmt_kategori($d['kategori'] ?? '') ?>
                                    <?php endif; ?>
                                </td>

                            <?php elseif ($type === 'jenis_reward'): ?>
                                <td>
                                    <?php if ($act === 'ERROR'): ?>
                                        <span class="diff-new"><?= htmlspecialchars($d['nama_reward'] ?? '') ?></span>
                                        <span class="fatal-reason" style="background:#fdf2f8;border-color:#f9a8d4;color:#9d174d"><i class="bi bi-x-circle-fill me-1"></i><?= htmlspecialchars($row['error_msg']) ?></span>
                                    <?php elseif ($is_fatal): ?>
                                        <span class="diff-new"><?= htmlspecialchars($d['nama_reward']) ?></span>
                                        <span class="fatal-reason"><i class="bi bi-exclamation-octagon-fill me-1"></i><?= htmlspecialchars($row['fatal_reason']) ?></span>
                                    <?php elseif ($act === 'UPDATE' && ($d['nama_reward'] ?? '') !== ($od['nama_reward'] ?? '')): ?>
                                        <span class="diff-old"><?= htmlspecialchars($od['nama_reward']) ?></span>
                                        <span class="diff-new"><?= htmlspecialchars($d['nama_reward']) ?></span>
                                    <?php else: ?>
                                        <?= htmlspecialchars($d['nama_reward'] ?? '') ?>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($act === 'UPDATE' && ($d['poin_reward'] ?? 0) != ($od['poin_reward'] ?? 0)): ?>
                                        <span class="diff-old"><?= htmlspecialchars($od['poin_reward']) ?></span>
                                        <span class="diff-new"><?= htmlspecialchars($d['poin_reward']) ?></span>
                                    <?php else: ?>
                                        <?= htmlspecialchars($d['poin_reward'] ?? 0) ?>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:.84rem;color:#475569">
                                    <?php if ($act === 'UPDATE' && ($d['deskripsi'] ?? '') !== ($od['deskripsi'] ?? '')): ?>
                                        <span class="diff-old"><?= htmlspecialchars($od['deskripsi'] ?? '') ?></span>
                                        <span class="diff-new"><?= htmlspecialchars($d['deskripsi'] ?? '') ?></span>
                                    <?php else: ?>
                                        <?= $d['deskripsi'] !== '' ? htmlspecialchars($d['deskripsi']) : '<span class="text-muted fst-italic">—</span>' ?>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Tombol aksi -->
                <div class="p-3 p-md-4 border-top bg-white d-flex flex-column-reverse flex-md-row justify-content-end gap-2 gap-md-3">
                    <form action="proses.php" method="post" class="m-0 d-grid d-md-block">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                        <input type="hidden" name="action" value="cancel">
                        <button type="submit" class="btn btn-outline-secondary py-2 w-100">Batal &amp; Kembali</button>
                    </form>
                    <form action="proses.php" method="post" class="m-0 d-grid d-md-block" id="syncConfirmForm">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                        <input type="hidden" name="action" value="confirm">
                        <button type="button" class="btn btn-primary fw-bold text-white py-2 w-100" id="btnConfirmSync" 
                                style="background-color: <?= $cnt_error ? '#94a3b8' : '#4f46e5' ?>; border-color: <?= $cnt_error ? '#94a3b8' : '#4f46e5' ?>; <?= $cnt_error ? 'cursor:not-allowed;' : '' ?>" 
                                <?= $cnt_error ? 'disabled' : '' ?>>
                            <i class="<?= $cnt_error ? 'bi bi-lock-fill' : 'bi bi-check-circle-fill' ?> me-1"></i> 
                            <?= $cnt_error ? 'Perbaiki File Excel Dahulu' : 'Konfirmasi &amp; Terapkan' ?>
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>

    <?php endif; ?>

</div><!-- /container -->

<script>
// ── Sync stats (block-scoped) ──────────────────────────────────────────────
{
<?php if ($preview_data !== null): ?>
window._syncStats = {
    insert: <?= $cnt_insert ?>,
    fatal:  <?= $cnt_fatal ?>,
    update: <?= $cnt_update ?>,
    delete: <?= $cnt_delete ?>
};
<?php else: ?>
window._syncStats = { insert:0, fatal:0, update:0, delete:0 };
<?php endif; ?>
}

// ── Badge filter (IIFE, zero global leak) ──────────────────────────────────
(function() {
    const badges    = document.querySelectorAll('.filter-badge');
    const tbody     = document.getElementById('preview-tbody');
    const noResult  = document.getElementById('no-filter-result');
    const tableWrap = document.getElementById('preview-table-wrap');
    if (!badges.length || !tbody) return;

    let activeFilter = null;

    function applyFilter(filter) {
        const rows = tbody.querySelectorAll('tr');
        let visible = 0;
        rows.forEach(row => {
            if (!filter || row.dataset.action === filter) {
                row.classList.remove('preview-row-hidden');
                visible++;
            } else {
                row.classList.add('preview-row-hidden');
            }
        });
        // show/hide empty state
        if (noResult && tableWrap) {
            if (visible === 0) {
                noResult.style.display = 'block';
                tableWrap.style.display = 'none';
            } else {
                noResult.style.display = 'none';
                tableWrap.style.display = '';
            }
        }
        // scroll the TABLE CONTAINER (not the element) to top of first match
        if (filter && tableWrap && visible > 0) {
            const firstVisible = tbody.querySelector('tr:not(.preview-row-hidden)');
            if (firstVisible) {
                // offsetTop relative to tableWrap
                tableWrap.scrollTop = Math.max(0, firstVisible.offsetTop - 8);
            }
        } else if (!filter && tableWrap) {
            tableWrap.scrollTop = 0;
        }
    }

    badges.forEach(badge => {
        badge.addEventListener('click', function() {
            const filter = this.dataset.filter;
            if (activeFilter === filter) {
                activeFilter = null;
                badges.forEach(b => b.classList.remove('active'));
                applyFilter(null);
            } else {
                activeFilter = filter;
                badges.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                applyFilter(filter);
            }
        });
    });
})();

// ── File name display ──────────────────────────────────────────────────────
document.getElementById('file_impor')?.addEventListener('change', function(e) {
    const display = document.getElementById('file-name-display');
    const icon    = document.getElementById('upload-icon');
    const title   = document.getElementById('upload-title');
    const dz      = document.getElementById('dropzone');
    
    if (e.target.files.length > 0) {
        display.innerHTML = '<span class="fw-bold text-success" style="font-size:0.95rem">' + e.target.files[0].name + '</span>';
        if (title) title.textContent = 'File Berhasil Dipilih';
        icon.className = 'bi bi-check-circle-fill';
        icon.style.color = '#10b981';
        dz.style.borderColor = '#10b981';
        dz.style.background = 'linear-gradient(145deg,#ecfdf5,#d1fae5)';
    } else {
        display.textContent = 'Mendukung format Excel (.xlsx) dan CSV (.csv)';
        if (title) title.textContent = 'Klik atau Seret File ke Sini';
        icon.className = 'bi bi-file-earmark-excel';
        icon.style.color = '#10b981';
        dz.style.borderColor = '';
        dz.style.background = '';
    }
});

// ── Drag & drop ───────────────────────────────────────────────────────────
const dz = document.getElementById('dropzone');
if (dz) {
    ['dragenter','dragover'].forEach(ev => dz.addEventListener(ev, e => { e.preventDefault(); dz.classList.add('dragover'); }));
    ['dragleave','drop'].forEach(ev => dz.addEventListener(ev, e => { e.preventDefault(); dz.classList.remove('dragover'); }));
}

// ── Template button highlight ────────────────────────────────────────────
const tipeSelect = document.getElementById('tipe_data');
const modeSelect = document.getElementById('mode_sinkronisasi');
const tplMap     = { santri:'#btn-tpl-santri', jenis_pelanggaran:'#btn-tpl-pelanggaran', jenis_reward:'#btn-tpl-reward' };

// replace syncStats references (was renamed to _syncStats) ──────────────────
const syncStats = window._syncStats || { insert:0, fatal:0, update:0, delete:0 };

function updateTpl() {
    if (!tipeSelect) return;
    document.querySelectorAll('.tpl-btn').forEach(b => b.style.outline = '');
    const t = document.querySelector(tplMap[tipeSelect.value]);
    if (t) t.style.outline = '3px solid rgba(59,130,246,.45)';
}
let lastMode = modeSelect?.value;
function handleModeChange() {
    if (modeSelect?.value === 'full_sync' && lastMode !== 'full_sync') {
        const hasActiveData = <?= json_encode((bool)$has_active_data) ?>;
        const activeDataCount = <?= json_encode((int)$cek_data_aktif) ?>;
        
        if (hasActiveData) {
            Swal.fire({
                title: 'Peringatan Tutup Buku!',
                html: `<div style="font-size: 0.9rem; line-height: 1.5;">Terdapat <strong>${activeDataCount} riwayat aktif</strong> (pelanggaran/reward).<br><br>Mode Sinkronisasi Penuh <u>tidak akan menghapus</u> data (santri/pelanggaran/reward) yang masih terikat dengan riwayat aktif tersebut.</div>`,
                icon: 'warning',
                width: '420px',
                showCancelButton: true,
                confirmButtonColor: '#d97706',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="bi bi-archive-fill me-1"></i> Tutup Buku',
                cancelButtonText: 'Lanjutkan',
                customClass: { 
                    popup: 'rounded-4 shadow-lg border-0 p-3 p-md-4 max-w-sm',
                    title: 'fw-bold fs-5 text-dark mb-1',
                    htmlContainer: 'px-0 mt-0',
                    confirmButton: 'rounded-pill px-4 fw-bold shadow-sm mx-1',
                    cancelButton: 'rounded-pill px-4 fw-bold shadow-sm mx-1',
                    actions: 'mt-3 flex-wrap'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../reset-poin/index.php';
                }
            });
        } else {
            Swal.fire({
                title: 'Status Aman',
                html: '<div style="font-size: 0.9rem;">Laci data sudah bersih. Sistem siap melakukan Sinkronisasi Penuh tanpa hambatan data yang tertahan.</div>',
                icon: 'success',
                timer: 3500,
                width: '400px',
                showConfirmButton: false,
                customClass: { 
                    popup: 'rounded-4 shadow-lg border-0 p-3 p-md-4 max-w-sm',
                    title: 'fw-bold fs-5 text-success mb-1',
                    htmlContainer: 'px-0 mt-0'
                }
            });
        }
    }
    lastMode = modeSelect?.value;
}

tipeSelect?.addEventListener('change', () => { updateTpl(); });
modeSelect?.addEventListener('change', handleModeChange);
updateTpl();

// ── SweetAlert konfirmasi ─────────────────────────────────────────────────
document.getElementById('btnConfirmSync')?.addEventListener('click', function(e) {
    e.preventDefault();
    let html = `<div class="text-start mb-0">
        <div class="d-flex flex-column border-top">`;
    if (syncStats.insert) html += `<div class="d-flex justify-content-between align-items-center py-2 border-bottom"><span class="text-success fw-semibold" style="font-size:0.85rem"><i class="bi bi-plus-circle-fill me-2"></i>Data Baru (Insert)</span><span class="badge bg-success text-white rounded-pill px-2">${syncStats.insert}</span></div>`;
    if (syncStats.update) html += `<div class="d-flex justify-content-between align-items-center py-2 border-bottom"><span class="text-warning-emphasis fw-semibold" style="font-size:0.85rem"><i class="bi bi-pencil-square me-2"></i>Perbarui (Update)</span><span class="badge bg-warning text-dark rounded-pill px-2">${syncStats.update}</span></div>`;
    if (syncStats.delete) html += `<div class="d-flex justify-content-between align-items-center py-2 border-bottom"><span class="text-danger fw-semibold" style="font-size:0.85rem"><i class="bi bi-trash-fill me-2"></i>Hapus (Delete)</span><span class="badge bg-danger text-white rounded-pill px-2">${syncStats.delete}</span></div>`;
    if (syncStats.fatal) html += `<div class="d-flex justify-content-between align-items-center py-2 border-bottom"><span class="text-danger fw-bold" style="font-size:0.85rem"><i class="bi bi-exclamation-octagon-fill me-2"></i>Konflik ID (Fatal)</span><span class="badge bg-danger text-white rounded-pill px-2">${syncStats.fatal}</span></div><p class="text-danger mt-1 mb-0" style="font-size:.72rem;line-height:1.2"><i class="bi bi-info-circle-fill me-1"></i>Data konflik akan di-generate ID baru (Aman).</p>`;
    if (!syncStats.insert && !syncStats.update && !syncStats.delete && !syncStats.fatal)
        html += `<div class="text-center py-3 text-muted" style="font-size:0.85rem"><i class="bi bi-info-circle me-1"></i> Tidak ada perubahan data.</div>`;
    html += `</div></div>`;

    Swal.fire({
        title: 'Terapkan Perubahan?',
        html, icon: 'info',
        width: '420px',
        showCancelButton: true, buttonsStyling: false,
        confirmButtonText: 'Terapkan',
        cancelButtonText: 'Batal',
        reverseButtons: true,
        customClass: {
            popup: 'rounded-4 shadow-lg border-0 p-3 p-md-4 max-w-sm',
            title: 'fw-bold text-dark mb-1 fs-5',
            confirmButton: 'btn fw-bold px-4 py-2 rounded-3 text-white flex-grow-1 mx-1',
            cancelButton:  'btn btn-light border px-4 py-2 rounded-3 text-secondary flex-grow-1 mx-1',
            actions: 'd-flex flex-row w-100 mt-3 mb-0'
        },
        didOpen: () => {
             const confirmBtn = Swal.getConfirmButton();
             confirmBtn.style.backgroundColor = '#4f46e5';
             confirmBtn.style.borderColor = '#4f46e5';
        }
    }).then(r => {
        if (r.isConfirmed) {
            Swal.fire({
                title: 'Sedang Menyinkronisasi…',
                text: 'Mohon tunggu, sistem sedang memproses dan mengamankan perubahan data.',
                allowOutsideClick: false, showConfirmButton: false, buttonsStyling: false,
                customClass: { popup: 'rounded-4 shadow-lg border border-light p-4' },
                didOpen: () => Swal.showLoading(),
            });
            document.getElementById('syncConfirmForm').submit();
        }
    });
});
</script>

<!-- ── Modal Panduan Sinkronisasi ────────────────────────────────────────── -->
<div class="modal fade" id="infoSyncModal" tabindex="-1" aria-labelledby="infoSyncModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header bg-light border-bottom-0 pb-2" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
                <h5 class="modal-title fw-bold text-dark d-flex align-items-center gap-2" id="infoSyncModalLabel">
                    <i class="bi bi-info-circle-fill text-primary"></i> Panduan Teknis Sinkronisasi
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-4 pt-3 pb-4" style="font-size: 0.92rem;">
                
                <style>
                    /* Tab Base Styling */
                    #syncInfoTab .nav-link {
                        color: #6c757d;
                        background-color: #fff;
                        transition: all 0.3s ease;
                        box-shadow: 0 2px 4px rgba(0,0,0,0.02);
                        border: 1px solid #e9ecef !important;
                    }
                    #syncInfoTab .nav-link:hover {
                        transform: translateY(-1px);
                        box-shadow: 0 4px 6px rgba(0,0,0,0.05);
                    }
                    
                    /* Santri Tab Active */
                    #santri-tab.active {
                        background-color: #4f46e5 !important;
                        color: white !important;
                        border-color: transparent !important;
                        box-shadow: 0 4px 10px rgba(79, 70, 229, 0.3) !important;
                    }
                    
                    /* Pelanggaran Tab Active */
                    #pelanggaran-tab.active {
                        background-color: #e11d48 !important;
                        color: white !important;
                        border-color: transparent !important;
                        box-shadow: 0 4px 10px rgba(225, 29, 72, 0.3) !important;
                    }
                    
                    /* Reward Tab Active */
                    #reward-tab.active {
                        background-color: #10b981 !important;
                        color: white !important;
                        border-color: transparent !important;
                        box-shadow: 0 4px 10px rgba(16, 185, 129, 0.3) !important;
                    }

                    /* Content Icons */
                    #santri-info h6 i { color: #4f46e5 !important; }
                    #pelanggaran-info h6 i { color: #e11d48 !important; }
                    #reward-info h6 i { color: #10b981 !important; }

                    /* Custom Reward Alert */
                    .bg-success-soft {
                        background-color: rgba(16, 185, 129, 0.08) !important;
                        border: 1px solid rgba(16, 185, 129, 0.2) !important;
                    }
                </style>
                
                <!-- Nav pills -->
                <ul class="nav nav-pills nav-fill mb-4 gap-2" id="syncInfoTab" role="tablist">
                  <li class="nav-item" role="presentation">
                    <button class="nav-link active rounded-pill fw-bold border" id="santri-tab" data-bs-toggle="pill" data-bs-target="#santri-info" type="button" role="tab" aria-controls="santri-info" aria-selected="true"><i class="bi bi-people-fill me-1"></i> Santri</button>
                  </li>
                  <li class="nav-item" role="presentation">
                    <button class="nav-link rounded-pill fw-bold border" id="pelanggaran-tab" data-bs-toggle="pill" data-bs-target="#pelanggaran-info" type="button" role="tab" aria-controls="pelanggaran-info" aria-selected="false"><i class="bi bi-exclamation-triangle-fill me-1"></i> Pelanggaran</button>
                  </li>
                  <li class="nav-item" role="presentation">
                    <button class="nav-link rounded-pill fw-bold border" id="reward-tab" data-bs-toggle="pill" data-bs-target="#reward-info" type="button" role="tab" aria-controls="reward-info" aria-selected="false"><i class="bi bi-star-fill me-1"></i> Reward</button>
                  </li>
                </ul>

                <!-- Tab content -->
                <div class="tab-content" id="syncInfoTabContent">
                  
                  <!-- SANTRI INFO -->
                  <div class="tab-pane fade show active" id="santri-info" role="tabpanel" aria-labelledby="santri-tab" tabindex="0">
                      <h6 class="fw-bold text-dark mb-2"><i class="bi bi-search me-1"></i> Hierarki Pencocokan (Santri)</h6>
                      <ol class="text-muted mb-4" style="line-height: 1.6; padding-left: 1.2rem;">
                          <li><strong>ID (Prioritas Utama):</strong> Jika kolom ID diisi, sistem akan langsung menimpa data santri dengan ID tersebut secara presisi.</li>
                          <li><strong>NIS (Nomor Induk):</strong> Jika ID kosong, sistem akan mencari data berdasarkan NIS santri.</li>
                          <li><strong>Nama (Pilihan Terakhir):</strong> Jika ID & NIS kosong, sistem mencari berdasarkan Nama.<br><small class="text-danger">⚠️ <strong>Awas:</strong> Santri dengan nama persis sama (misal ada dua "Ahmad") <strong>tidak bisa</strong> dicocokkan otomatis via Nama. Anda wajib memberikan mereka NIS/ID yang unik.</small></li>
                      </ol>

                      <h6 class="fw-bold text-dark mb-2"><i class="bi bi-file-earmark-excel me-1"></i> Penamaan Kolom di Excel</h6>
                      <p class="text-muted mb-4" style="line-height: 1.5;">
                          Sistem cukup cerdas untuk mendeteksi berbagai variasi nama kolom. Untuk kolom NIS, judul/header di file Excel Anda harus salah satu dari: <code>NIS</code>, <code>Nomor Induk</code>, atau <code>No Induk</code>. Isi nilainya pun bebas, bisa berupa kombinasi huruf dan angka.
                      </p>

                      <h6 class="fw-bold text-dark mb-2"><i class="bi bi-clipboard-x me-1"></i> Aturan Sel Kosong (Santri)</h6>
                      <ul class="text-muted mb-4" style="line-height: 1.5; padding-left: 1.2rem;">
                          <li><strong>Nama:</strong> Wajib diisi! Baris tanpa nama akan ditandai error.</li>
                          <li><strong>NIS Kosong:</strong> Jika sel NIS dikosongkan di Excel saat update, sistem <strong>TIDAK AKAN menghapus</strong> NIS lama yang sudah ada di database.</li>
                          <li><strong>Kelas/Kamar Kosong:</strong> Sama halnya, sistem juga tidak akan menimpa kelas atau kamar lama menjadi kosong.</li>
                      </ul>

                      <h6 class="fw-bold text-dark mb-2"><i class="bi bi-shield-exclamation me-1"></i> Peringatan Kesalahan (Santri)</h6>
                      <ul class="text-muted mb-4" style="line-height: 1.5; padding-left: 1.2rem;">
                          <li class="mb-2"><strong>Konflik NIS (FATAL):</strong> Jika NIS di Excel Anda ternyata bentrok/sudah dipakai santri lain di database, sinkronisasi otomatis diblokir demi mencegah tertukarnya data.</li>
                          <li class="mb-2"><strong>Duplikasi di Excel (ERROR):</strong> Jika Anda menginput 2 baris dengan NIS yang kembar di dalam satu file, baris kedua akan ditolak.</li>
                      </ul>

                      <h6 class="fw-bold text-dark mb-2"><i class="bi bi-arrow-left-right me-1"></i> Ingin Menukar NIS?</h6>
                      <div class="bg-primary bg-opacity-10 border border-primary border-opacity-25 rounded-3 p-3 text-dark">
                          <p class="mb-0" style="font-size: 0.88rem; line-height: 1.5;">
                              Jika Anda <strong>memang sengaja</strong> ingin menukar atau mengubah NIS santri yang salah, Anda <strong>WAJIB mengisi kolom ID</strong> santri tersebut di Excel (ID didapat dari hasil export).
                          </p>
                      </div>
                  </div>

                  <!-- PELANGGARAN INFO -->
                  <div class="tab-pane fade" id="pelanggaran-info" role="tabpanel" aria-labelledby="pelanggaran-tab" tabindex="0">
                      <h6 class="fw-bold text-dark mb-2"><i class="bi bi-search me-1"></i> Hierarki Pencocokan (Pelanggaran)</h6>
                      <ol class="text-muted mb-4" style="line-height: 1.6; padding-left: 1.2rem;">
                          <li><strong>ID (Prioritas Utama):</strong> ID adalah pencocokan mutlak. Selalu sertakan ID jika Anda ingin melakukan update data lama.</li>
                          <li><strong>Nama & Bagian:</strong> Jika ID kosong, sistem mencari kesamaan <strong>Nama Pelanggaran</strong> DAN <strong>Bagian</strong> sekaligus. <br><small class="text-muted">(Contoh: "Terlambat" bagian "KBM" dianggap berbeda dan dipisah dari "Terlambat" bagian "Kesantrian").</small></li>
                      </ol>

                      <h6 class="fw-bold text-dark mb-2"><i class="bi bi-clipboard-x me-1"></i> Aturan Sel Kosong (Pelanggaran)</h6>
                      <ul class="text-muted mb-4" style="line-height: 1.5; padding-left: 1.2rem;">
                          <li class="mb-2"><strong>Bagian, Poin, & Kategori Kosong:</strong> Jika Anda mengosongkan sel-sel vital ini di Excel saat melakukan update, sistem <strong>TIDAK AKAN meresetnya menjadi 0 atau kosong</strong>. Data lama di database Anda dipastikan aman dan dipertahankan.</li>
                          <li><strong class="text-danger">Ingin Mereset Poin Jadi 0?</strong> Jika Anda sengaja ingin menghapus poin (menjadikannya 0), Anda <strong>WAJIB mengetik angka 0</strong> pada sel tersebut, jangan dibiarkan kosong.</li>
                      </ul>

                      <h6 class="fw-bold text-dark mb-2"><i class="bi bi-shield-exclamation me-1"></i> Duplikasi Internal (Pelanggaran)</h6>
                      <div class="bg-danger bg-opacity-10 border border-danger border-opacity-25 rounded-3 p-3 text-dark">
                          <p class="mb-0" style="font-size: 0.88rem; line-height: 1.5;">
                              Sistem akan menolak file Excel Anda (menandainya sebagai ERROR) jika Anda mengetik Nama Pelanggaran dengan Bagian yang <strong>sama persis</strong> lebih dari satu kali di dalam file Excel.
                          </p>
                      </div>
                  </div>

                  <!-- REWARD INFO -->
                  <div class="tab-pane fade" id="reward-info" role="tabpanel" aria-labelledby="reward-tab" tabindex="0">
                      <h6 class="fw-bold text-dark mb-2"><i class="bi bi-search me-1"></i> Hierarki Pencocokan (Reward)</h6>
                      <ol class="text-muted mb-4" style="line-height: 1.6; padding-left: 1.2rem;">
                          <li><strong>ID (Prioritas Utama):</strong> Pencocokan mutlak menggunakan ID.</li>
                          <li><strong>Nama Reward:</strong> Jika ID kosong, sistem mencari berdasarkan Nama Reward. <br><small class="text-danger">⚠️ <strong>Awas:</strong> Reward dengan nama kembar/sama persis di database tidak bisa di-update via Nama. Anda wajib menyertakan ID-nya.</small></li>
                      </ol>

                      <h6 class="fw-bold text-dark mb-2"><i class="bi bi-clipboard-x me-1"></i> Aturan Sel Kosong (Reward)</h6>
                      <ul class="text-muted mb-4" style="line-height: 1.5; padding-left: 1.2rem;">
                          <li class="mb-2"><strong>Poin Reward Kosong:</strong> Jika sel Poin dikosongkan, sistem <strong>TIDAK AKAN mereset</strong> poin lama menjadi 0. Poin lama tetap utuh.</li>
                          <li class="mb-2"><strong class="text-success">Ingin Mereset Poin Jadi 0?</strong> Jika Anda sengaja ingin menghapus poin reward (menjadikannya 0), Anda <strong>WAJIB mengetik angka 0</strong> pada sel tersebut.</li>
                          <li><strong>Deskripsi Kosong:</strong> Pengecualian untuk Deskripsi! Karena sifatnya opsional, jika Anda mengosongkan sel Deskripsi di Excel, maka deskripsi lama di database <strong>AKAN DIHAPUS (dikosongkan)</strong>.</li>
                      </ul>
                      
                      <h6 class="fw-bold text-dark mb-2"><i class="bi bi-shield-exclamation me-1"></i> Duplikasi Internal (Reward)</h6>
                      <div class="bg-success-soft rounded-3 p-3 text-dark">
                          <p class="mb-0" style="font-size: 0.88rem; line-height: 1.5;">
                              Sistem akan menandai baris sebagai ERROR jika Anda menuliskan dua nama reward yang sama persis di dalam file Excel.
                          </p>
                      </div>
                  </div>

                </div>

            </div>
            <div class="modal-footer bg-light border-top-0 pt-2 pb-3">
                <button type="button" class="btn btn-primary rounded-pill px-4 fw-bold" data-bs-dismiss="modal">Saya Mengerti</button>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
