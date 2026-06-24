<?php
// rapot/crud_tahunan/detail.php
// Halaman detail rapor tahunan — format tabel identik dengan rapot bulanan

require_once __DIR__ . '/../../bootstrap/init.php';
require_once __DIR__ . '/../config/helper.php';
require_once __DIR__ . '/../api/generate_catatan.php';
guard('rapot_view');

$santri_id = (int)($_GET['santri_id'] ?? 0);
$periode   = trim($_GET['periode'] ?? '');
$page_title = "Detail Rapor Tahunan";

if (!$santri_id || !$periode) {
    set_flash_message('Parameter tidak lengkap.', 'danger');
    header('Location: index.php');
    exit;
}

// Ambil data rapor tahunan
try {
    $stmt = $conn->prepare("
        SELECT rt.*, s.nama AS nama_santri, s.kamar, s.kelas, u.nama_lengkap AS nama_musyrif
        FROM rapot_tahunan rt
        LEFT JOIN santri s ON rt.santri_id = s.id
        LEFT JOIN users  u ON rt.musyrif_id = u.id
        WHERE rt.santri_id = ? AND rt.periode = ?
        LIMIT 1
    ");
    $stmt->bind_param('is', $santri_id, $periode);
    $stmt->execute();
    $rapot = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}

if (!$rapot) {
    set_flash_message('Data rapor tahunan tidak ditemukan.', 'warning');
    header('Location: index.php');
    exit;
}

$kamar_filter_musyrif = checkMusyrifKamarAccess();
if ($kamar_filter_musyrif !== null && (string)$kamar_filter_musyrif !== (string)$rapot['kamar']) {
    set_flash_message('Anda tidak memiliki akses ke rapot ini.', 'danger');
    header('Location: index.php');
    exit;
}

// Proses simpan edit catatan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_catatan') {
    guard('rapot_create'); // ensure only authorized users can edit
    $edited_global = trim($_POST['catatan_global'] ?? '');
    
    if (strlen($edited_global) < 15 || preg_match_all('/[a-zA-Z0-9]/', $edited_global) < 10) {
        set_flash_message('Gagal menyimpan: Catatan keseluruhan tidak valid. Harap isi dengan jelas (minimal 15 karakter huruf/angka).', 'danger');
        header("Location: detail.php?santri_id=$santri_id&periode=" . urlencode($periode));
        exit;
    }
    
    // Update catatan per-aspek
    $edited_aspek = json_decode($rapot['nilai_snapshot'] ?? '[]', true) ?? [];
    foreach ($edited_aspek as $idx => &$aspek) {
        $key = 'catatan_aspek_' . $idx;
        if (isset($_POST[$key])) {
            $aspek['catatan'] = trim($_POST[$key]);
        }
    }
    unset($aspek);
    $new_snapshot = json_encode($edited_aspek, JSON_UNESCAPED_UNICODE);

    $stmt_upd = $conn->prepare("UPDATE rapot_tahunan SET narasi_ai = ?, nilai_snapshot = ? WHERE id = ?");
    $stmt_upd->bind_param('ssi', $edited_global, $new_snapshot, $rapot['id']);
    if ($stmt_upd->execute()) {
        set_flash_message('Catatan berhasil diperbarui.', 'success');
    } else {
        set_flash_message('Gagal memperbarui catatan.', 'danger');
    }
    $stmt_upd->close();
    header("Location: detail.php?santri_id=$santri_id&periode=" . urlencode($periode));
    exit;
}

$nilai_aspek = json_decode($rapot['nilai_snapshot'] ?? '[]', true) ?? [];

// Hitung poin real dari database
$tahun_awal = (int)explode('/', $periode)[0];
$tahun_akhir = $tahun_awal + 1;
$tgl_awal = "$tahun_awal-07-01";
$tgl_akhir = "$tahun_akhir-06-30";

$sql_pel = "SELECT SUM(jp.poin) as total FROM pelanggaran p
            JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id
            WHERE p.santri_id = ? AND p.tanggal BETWEEN ? AND ?";
$stmt_pel = $conn->prepare($sql_pel);
$stmt_pel->bind_param('iss', $santri_id, $tgl_awal, $tgl_akhir);
$stmt_pel->execute();
$total_pelanggaran = (int)$stmt_pel->get_result()->fetch_assoc()['total'];
$stmt_pel->close();

$sql_rew = "SELECT SUM(jr.poin_reward) as total FROM daftar_reward rwd
            JOIN jenis_reward jr ON rwd.jenis_reward_id = jr.id
            WHERE rwd.santri_id = ? AND rwd.tanggal BETWEEN ? AND ?";
$stmt_rew = $conn->prepare($sql_rew);
$stmt_rew->bind_param('iss', $santri_id, $tgl_awal, $tgl_akhir);
$stmt_rew->execute();
$total_reward = (int)$stmt_rew->get_result()->fetch_assoc()['total'];
$stmt_rew->close();

$sql_pel_list = "SELECT jp.nama_pelanggaran, SUM(jp.poin) AS poin, COUNT(*) as jumlah
                 FROM pelanggaran pel
                 JOIN jenis_pelanggaran jp ON pel.jenis_pelanggaran_id = jp.id
                 WHERE pel.santri_id = ? AND pel.tanggal BETWEEN ? AND ?
                 GROUP BY jp.id
                 ORDER BY poin DESC";
$stmt_pl = $conn->prepare($sql_pel_list);
$stmt_pl->bind_param('iss', $santri_id, $tgl_awal, $tgl_akhir);
$stmt_pl->execute();
$res_pl = $stmt_pl->get_result();
$pelanggaran_list = [];
while ($row = $res_pl->fetch_assoc()) {
    $pelanggaran_list[] = $row;
}
$stmt_pl->close();

$sql_rew_list = "SELECT jr.nama_reward, SUM(jr.poin_reward) AS poin, COUNT(*) as jumlah
                 FROM daftar_reward rwd
                 JOIN jenis_reward jr ON rwd.jenis_reward_id = jr.id
                 WHERE rwd.santri_id = ? AND rwd.tanggal BETWEEN ? AND ?
                 GROUP BY jr.id
                 ORDER BY poin DESC";
$stmt_rl = $conn->prepare($sql_rew_list);
$stmt_rl->bind_param('iss', $santri_id, $tgl_awal, $tgl_akhir);
$stmt_rl->execute();
$res_rl = $stmt_rl->get_result();
$reward_list = [];
while ($row = $res_rl->fetch_assoc()) {
    $reward_list[] = $row;
}
$stmt_rl->close();

$meta = json_decode($rapot['catatan_musyrif'] ?? '{}', true) ?? [];
$jumlah_bulan = (int)($meta['jumlah_bulan'] ?? 0);

$catatan_otomatis = trim($rapot['narasi_ai'] ?? '');

$can_cetak  = has_permission('rapot_cetak');
$can_create = has_permission('rapot_create');
$can_ai     = has_permission('catatan_otomatis');
$status     = $rapot['status'] ?? 'DRAFT';

// Hitung total nilai (jumlah semua nilai_final)
$total_nilai = 0;
foreach ($nilai_aspek as $aspek) {
    foreach ($aspek['sub_mutu'] ?? [] as $sub) {
        $total_nilai += (float)($sub['nilai_final'] ?? 0);
    }
}

// Validasi catatan lengkap
$missing_notes = [];
$cat_global_cek = trim($rapot['narasi_ai'] ?? '');
$word_count_global = count(preg_split('/\s+/', $cat_global_cek));
if (strlen($cat_global_cek) < 15 || preg_match_all('/[a-zA-Z0-9]/', $cat_global_cek) < 10 || $word_count_global < 3) {
    $missing_notes[] = 'Catatan Keseluruhan (minimal 3 kata & harus jelas)';
}
foreach ($nilai_aspek as $aspek) {
    $cat_cek = trim($aspek['catatan'] ?? '');
    $word_count_aspek = count(preg_split('/\s+/', $cat_cek));
    if (strlen($cat_cek) < 10 || preg_match_all('/[a-zA-Z0-9]/', $cat_cek) < 8 || $word_count_aspek < 2) {
        $missing_notes[] = 'Catatan Mutu: ' . htmlspecialchars($aspek['aspek']) . ' (minimal 2 kata & harus jelas)';
    }
}
$is_notes_complete = empty($missing_notes);

require_once __DIR__ . '/../../layouts/header.php';
?>

<style>
    :root {
        --c-primary:       #1d6fa4;
        --c-primary-light: #e8f4fd;
        --c-success:       #1a7c4f;
        --c-success-light: #e6f4ee;
        --c-warning:       #b45309;
        --c-warning-light: #fef3c7;
        --c-danger:        #b91c1c;
        --c-danger-light:  #fee2e2;
        --c-avatar:        #1d4e7a;
        --c-border:        #e2e8f0;
        --c-muted:         #64748b;
        --c-text:          #0f172a;
        --c-bg-soft:       #f8fafc;
    }

    .detail-wrap { max-width: 900px; margin: 0 auto; }

    /* ─── Info bar atas ─── */
    .info-bar {
        background: #fff; border: 1px solid var(--c-border);
        border-radius: .875rem; padding: 1rem 1.375rem;
        margin-bottom: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,.04);
        display: flex; flex-wrap: wrap; gap: 1rem;
    }
    .info-item .info-lbl {
        font-size: .68rem; font-weight: 700; text-transform: uppercase;
        letter-spacing: .05em; color: #94a3b8; margin-bottom: .2rem;
    }
    .info-item .info-val { font-size: .875rem; font-weight: 600; color: var(--c-text); }

    /* ─── Section card ─── */
    .sec-card {
        background: #fff; border: 1px solid var(--c-border);
        border-radius: .875rem; margin-bottom: 1rem;
        box-shadow: 0 1px 3px rgba(0,0,0,.04); overflow: hidden;
    }
    .sec-hdr {
        display: flex; align-items: center; gap: .625rem;
        padding: .75rem 1.25rem; border-bottom: 1px solid #f1f5f9;
        background: var(--c-bg-soft); font-weight: 700; font-size: .9rem;
        color: var(--c-text);
    }
    .sec-icon {
        width: 30px; height: 30px; border-radius: 8px;
        display: flex; align-items: center; justify-content: center;
        font-size: .8rem; flex-shrink: 0;
    }
    .sec-body { padding: 1.25rem; }

    /* ─── Tabel nilai (identik dengan rapot bulanan) ─── */
    .tbl-nilai {
        width: 100%; border-collapse: collapse;
        font-size: .875rem; min-width: 650px;
    }
    .tbl-nilai th {
        background: #f1f5f9; font-size: .75rem; font-weight: 700;
        text-transform: uppercase; letter-spacing: .04em;
        color: #475569; padding: .625rem .875rem;
        border: 1px solid var(--c-border); text-align: center;
    }
    .tbl-nilai td {
        padding: .5rem .875rem; border: 1px solid #e9edf3;
        vertical-align: middle; font-size: .875rem;
    }
    .tbl-nilai .td-mutu {
        font-weight: 700; text-align: center; background: #fafbff;
        color: var(--c-text); vertical-align: middle;
    }
    .tbl-nilai .td-sub { color: var(--c-text); }
    .tbl-nilai .td-penjelasan {
        color: var(--c-muted); text-align: center; font-size: .82rem;
    }
    .tbl-nilai .td-nilai {
        text-align: center; font-weight: 800; font-size: .9rem;
        color: var(--c-text); white-space: nowrap;
    }
    .tbl-nilai .td-jumlah {
        text-align: right; font-weight: 700;
        background: #fef9c3; color: #713f12;
    }
    .tbl-nilai .td-jumlah-val {
        text-align: center; font-weight: 800; font-size: 1rem;
        background: #fef9c3; color: #713f12;
    }

    /* Score badge warna */
    .sc { display: inline-block; width: 32px; height: 32px; border-radius: 50%;
          line-height: 32px; text-align: center; font-weight: 800; font-size: .85rem; }
    .sc-5 { background: #d1fae5; color: #065f46; }
    .sc-4 { background: #dbeafe; color: #1d4ed8; }
    .sc-3 { background: #fef3c7; color: #b45309; }
    .sc-2 { background: #ffedd5; color: #c2410c; }
    .sc-1 { background: #fee2e2; color: #b91c1c; }
    .sc-0 { background: #f1f5f9; color: #94a3b8; }

    /* koreksi tag */
    .kor-tag { font-size: .65rem; background: #fef3c7; color: #b45309; border: 1px solid #f59e0b; padding: 1px 4px; border-radius: 4px; margin-left: 2px; vertical-align: super; }
    .kor-tag.kor-minus { background: #fee2e2; color: #b91c1c; border-color: #fca5a5; }
    .kor-tag.kor-plus  { background: #dcfce7; color: #15803d; border-color: #86efac; }
    .kor-tag.kor-mix   { background: #e0e7ff; color: #4338ca; border-color: #a5b4fc; } /* Biru/Ungu untuk kombinasi +/- */

    /* ─── Catatan otomatis per mutu ─── */
    .catatan-mutu {
        background: #f8fafc;
        font-size: .82rem; color: #334155;
        line-height: 1.6;
    }
    .catatan-mutu td { border: 1px solid #e9edf3 !important; }

    /* ─── Poin bar ─── */
    .poin-bar {
        display: flex; gap: 1rem; flex-wrap: wrap;
        margin-top: 1rem; margin-bottom: 1rem;
    }
    .poin-box {
        flex: 1; min-width: 160px; border-radius: .75rem;
        padding: .875rem 1rem;
    }
    .poin-box.merah { background: #fef2f2; border-left: 4px solid #b91c1c; }
    .poin-box.hijau { background: #f0fdf4; border-left: 4px solid #1a7c4f; }
    .poin-lbl { font-size: .7rem; font-weight: 700; text-transform: uppercase;
                letter-spacing: .04em; color: var(--c-muted); margin-bottom: .25rem; }
    .poin-val { font-size: 1.5rem; font-weight: 800; line-height: 1; }
    .poin-box.merah .poin-val { color: #b91c1c; }
    .poin-box.hijau .poin-val { color: #1a7c4f; }
    .poin-sub { font-size: .75rem; margin-top: .2rem; color: var(--c-muted); }

    /* ─── Catatan pembinaan ─── */
    .narasi-box {
        background: #f0f9ff; border-left: 4px solid var(--c-primary);
        border-radius: 0 .625rem .625rem 0;
        padding: 1rem 1.25rem; font-size: .9rem; line-height: 1.75; color: #334155;
    }

    /* ─── Action row ─── */
    .act-row { display: flex; gap: .75rem; flex-wrap: wrap; align-items: center; }

    /* ─── Status chip ─── */
    .chip {
        display: inline-flex; align-items: center; gap: .3rem;
        font-size: .7rem; font-weight: 700; text-transform: uppercase;
        letter-spacing: .03em; padding: .25rem .65rem; border-radius: 9999px;
    }
    .chip-draft    { background: var(--c-warning-light); color: var(--c-warning); }
    .chip-approved { background: var(--c-success-light); color: var(--c-success); }
    .chip-exported { background: #e0f2fe; color: #0369a1; }

    /* ─── Global Loader Overlay ─── */
    .global-loader {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(4px);
        z-index: 9999;
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        opacity: 0; pointer-events: none;
        transition: opacity 0.3s ease;
    }
    .global-loader.show {
        opacity: 1; pointer-events: auto;
    }
    .spinner-modern {
        width: 50px; height: 50px;
        border: 4px solid var(--c-border);
        border-top-color: var(--c-primary);
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin-bottom: 1rem;
    }
    @keyframes spin { 100% { transform: rotate(360deg); } }
    .loader-icon-success {
        display: none; font-size: 3rem; color: var(--c-success);
        margin-bottom: 1rem;
        animation: scaleIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    @keyframes scaleIn { 0% { transform: scale(0); } 100% { transform: scale(1); } }
    .loader-text {
        font-size: 1.1rem; font-weight: 700; color: var(--c-text);
        letter-spacing: -0.01em;
    }
    
    /* Tombol Edit Nilai */
    .btn-edit-nilai {
        background: transparent; color: var(--c-primary);
        border: 1px solid var(--c-primary-light);
        width: 28px; height: 28px; border-radius: 6px;
        display: inline-flex; align-items: center; justify-content: center;
        cursor: pointer; transition: all 0.2s; font-size: 0.8rem;
    }
    .btn-edit-nilai:hover {
        background: var(--c-primary); color: #fff;
    }

    @media (max-width: 576px) {
        .info-bar { gap: .875rem; }
        .act-row  { flex-direction: column; }
        .act-row .ms-auto { margin-left: 0 !important; width: 100%; }
        .act-row a, .act-row button { width: 100%; text-align: center; }
        .sec-hdr { flex-wrap: wrap; }
        
        /* SweetAlert Minimalis di Mobile */
        div:where(.swal2-container) div:where(.swal2-popup) {
            padding: 1.25rem 1rem 1rem 1rem !important;
            width: 92% !important;
            border-radius: 1.25rem !important;
        }
        div:where(.swal2-icon) {
            transform: scale(0.65) !important;
            margin: 0 auto 0.5rem auto !important;
        }
        div:where(.swal2-container) h2:where(.swal2-title) {
            font-size: 1.15rem !important;
            margin-bottom: 0.5rem !important;
        }
        div:where(.swal2-container) div:where(.swal2-html-container) {
            font-size: 0.85rem !important;
            margin: 0 0 1rem 0 !important;
            line-height: 1.5;
        }
        div:where(.swal2-container) div:where(.swal2-actions) {
            margin-top: 0.5rem !important;
            flex-direction: column-reverse;
            gap: 0.5rem;
            width: 100%;
        }
        div:where(.swal2-container) .swal2-actions button {
            width: 100% !important;
            margin: 0 !important;
            padding: 0.6rem 1rem !important;
            font-size: 0.9rem !important;
            border-radius: 0.75rem !important;
        }
    }
</style>

<!-- Global Loader -->
<div id="globalLoader" class="global-loader">
    <div id="loaderSpinner" class="spinner-modern"></div>
    <i id="loaderSuccess" class="fas fa-check-circle loader-icon-success"></i>
    <div id="loaderText" class="loader-text">Memproses data...</div>
</div>

<div class="container-fluid py-4 px-3 px-md-4">
<div class="detail-wrap">

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="../index.php" class="text-decoration-none">Rapot Kepengasuhan</a></li>
            <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Rapor Tahunan</a></li>
            <li class="breadcrumb-item"><a href="list.php?kamar=<?= urlencode($rapot['kamar']) ?>&periode=<?= urlencode($rapot['periode']) ?>" class="text-decoration-none">Kamar <?= htmlspecialchars($rapot['kamar']) ?></a></li>
            <li class="breadcrumb-item active"><?= htmlspecialchars($rapot['nama_santri'] ?? 'Detail') ?></li>
        </ol>
    </nav>

    <!-- Info bar -->
    <div class="info-bar mb-3">
        <div class="info-item flex-grow-1">
            <div class="info-lbl">
                Nama Santri 
                <button type="button" class="btn btn-sm btn-link text-info p-0 ms-1" data-bs-toggle="modal" data-bs-target="#guideModalDetail" title="Buku Panduan">
                    <i class="fas fa-info-circle" style="font-size:1rem;"></i>
                </button>
            </div>
            <div class="info-val fw-bold fs-6"><?= htmlspecialchars($rapot['nama_santri'] ?? '—') ?></div>
        </div>
        <div class="info-item">
            <div class="info-lbl">Kamar</div>
            <div class="info-val"><?= htmlspecialchars($rapot['kamar'] ?? '—') ?></div>
        </div>
        <div class="info-item">
            <div class="info-lbl">Kelas</div>
            <div class="info-val"><?= htmlspecialchars($rapot['kelas'] ?? '—') ?></div>
        </div>
        <div class="info-item">
            <div class="info-lbl">Periode</div>
            <div class="info-val"><?= htmlspecialchars($periode) ?></div>
        </div>
        <div class="info-item">
            <div class="info-lbl">Status</div>
            <div class="info-val">
                <?php
                $pill_class = match($status) { 'APPROVED' => 'chip-approved', 'EXPORTED' => 'chip-exported', default => 'chip-draft' };
                $pill_icon  = match($status) { 'APPROVED' => 'fa-check-circle', 'EXPORTED' => 'fa-file-download', default => 'fa-pen' };
                $display_status = ($status === 'EXPORTED') ? 'DOWNLOADED' : $status;
                ?>
                <span class="chip <?= $pill_class ?>"><i class="fas <?= $pill_icon ?>"></i> <?= $display_status ?></span>
            </div>
        </div>
        <?php if ($jumlah_bulan > 0): ?>
        <div class="info-item">
            <div class="info-lbl">Data Bulanan</div>
            <div class="info-val"><?= $jumlah_bulan ?> bulan</div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Tabel Nilai -->
    <div class="sec-card">
        <div class="sec-hdr">
            <div class="sec-icon" style="background:var(--c-primary-light);color:var(--c-primary);"><i class="fas fa-table"></i></div>
            Rekap Nilai Rata-rata Setahun
            <span class="ms-auto text-muted fw-normal" style="font-size:.78rem;">Rata-rata dari <?= $jumlah_bulan ?: '?' ?> bulan rapot</span>
        </div>
        <div style="overflow-x:auto;">
            <?php 
            $koreksi_list = []; 
            $has_manual_edit = false;
            ?>
            <table class="tbl-nilai">
                <thead>
                    <tr>
                        <th style="width:13%;">Mutu</th>
                        <th style="width:22%;">Sub Mutu</th>
                        <th style="width:45%;">Penjelasan</th>
                        <th style="width:10%;">Nilai</th>
                        <th style="width:10%;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($nilai_aspek as $idx_aspek => $aspek):
                        $subs       = $aspek['sub_mutu'] ?? [];
                        $row_count  = count($subs);
                        // +1 untuk baris catatan per mutu
                        $total_rows = $row_count + 1;
                        $catatan_m  = $aspek['catatan'] ?? ($can_ai ? generate_catatan_per_aspek($aspek) : '');
                    ?>
                    <?php foreach ($subs as $i => $sub):
                        $nf  = (float)($sub['nilai_final'] ?? 0);
                        $nfr = (int)round($nf);
                        $sc  = max(0, min(5, $nfr));
                        $field_key = $sub['field'] ?? strtolower(str_replace([' ', '&', '/'], ['_', '', ''], $sub['nama']));
                        $ket = getDeskripsiPenilaian($field_key, $nfr);
                    ?>
                    <tr>
                        <?php if ($i === 0): ?>
                        <td rowspan="<?= $total_rows ?>" class="td-mutu">
                            <?= htmlspecialchars($aspek['aspek']) ?>
                        </td>
                        <?php endif; ?>
                        <td class="td-sub"><?= htmlspecialchars($sub['nama']) ?></td>
                        <td class="td-penjelasan" id="td-penjelasan-<?= $idx_aspek ?>-<?= $i ?>"><?= htmlspecialchars($ket) ?></td>
                        <td class="td-nilai">
                            <span class="sc sc-<?= $sc ?>" id="sc-<?= $idx_aspek ?>-<?= $i ?>"><?= number_format($nf, $nf == floor($nf) ? 0 : 1) ?></span>
                            <?php if (!empty($sub['ada_koreksi']) || !empty($sub['diubah_manual'])): 
                                $is_minus = false;
                                $is_plus = false;
                                if (!empty($sub['ada_koreksi'])) {
                                    $koreksi_list[] = "<b>" . htmlspecialchars($sub['nama']) . "</b>: " . htmlspecialchars($sub['alasan_koreksi']);
                                    $is_minus = strpos($sub['alasan_koreksi'], 'Dikurangi') !== false;
                                    $is_plus  = strpos($sub['alasan_koreksi'], 'Ditambah') !== false;
                                }
                                if (!empty($sub['diubah_manual'])) {
                                    $has_manual_edit = true;
                                }
                                
                                $tag_class = 'kor-tag';
                                if ($is_minus && $is_plus) {
                                    $tag_class .= ' kor-mix';
                                } elseif ($is_minus) {
                                    $tag_class .= ' kor-minus';
                                } elseif ($is_plus) {
                                    $tag_class .= ' kor-plus';
                                }
                            ?>
                            <span class="<?= $tag_class ?>" title="<?= htmlspecialchars($sub['alasan_koreksi'] ?? 'Diedit manual') ?>">✱</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if ($status === 'DRAFT' && $can_create): ?>
                            <button type="button" class="btn-edit-nilai" 
                                    onclick="editNilai(<?= $idx_aspek ?>, <?= $i ?>, '<?= htmlspecialchars(addslashes($sub['nama'])) ?>', <?= $nf ?>)"
                                    title="Edit Nilai">
                                <i class="fas fa-edit"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <!-- Baris catatan per mutu -->
                    <tr class="catatan-mutu">
                        <td colspan="3" style="background:#f8fafc; font-style: italic; color: #334155; font-size: .82rem; padding: .5rem .875rem;">
                            <i class="fas fa-comment-dots me-1 opacity-50"></i><span id="catatan-aspek-<?= $idx_aspek ?>"><?= htmlspecialchars($catatan_m) ?></span>
                        </td>
                        <td colspan="2" style="background:#f8fafc;"></td>
                    </tr>
                    <?php endforeach; ?>

                    <!-- Baris Jumlah -->
                    <tr>
                        <td colspan="3" class="td-jumlah">Jumlah</td>
                        <td colspan="2" class="td-jumlah-val" id="total-nilai-global"><?= number_format($total_nilai, $total_nilai == floor($total_nilai) ? 0 : 1) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <?php if (!empty($koreksi_list) || $has_manual_edit): ?>
        <div class="px-4 py-3" style="background:#fffbeb; border-bottom: 1px solid var(--c-border);">
            <div style="font-size:.8rem; color:#b45309; font-weight:600; margin-bottom:.4rem;">
                <i class="fas fa-info-circle me-1"></i> Penjelasan Tanda Bintang (✱)
            </div>
            <ul style="margin:0; padding-left:1.2rem; font-size:.78rem; color:#92400e; line-height:1.5;">
                <?php if ($has_manual_edit): ?>
                <li>Nilai yang memiliki tanda bintang (✱) tanpa keterangan khusus menandakan bahwa <b>nilai tersebut telah diedit secara manual</b> oleh Musyrif.</li>
                <?php endif; ?>
                <?php foreach ($koreksi_list as $kor_text): ?>
                <li><?= $kor_text ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <!-- Poin Pelanggaran & Reward -->
        <div class="sec-body pt-0">
            <div class="poin-bar">
                <div class="poin-box merah">
                    <div class="poin-lbl"><i class="fas fa-exclamation-triangle me-1"></i>Poin Pelanggaran</div>
                    <div class="poin-val"><?= $total_pelanggaran > 0 ? $total_pelanggaran : '–' ?></div>
                    <div class="poin-sub">Akumulasi setahun</div>
                </div>
                <div class="poin-box hijau">
                    <div class="poin-lbl"><i class="fas fa-star me-1"></i>Poin Reward</div>
                    <div class="poin-val"><?= $total_reward > 0 ? $total_reward : '–' ?></div>
                    <div class="poin-sub">Akumulasi setahun</div>
                </div>
            </div>
            
            <div class="row mt-1 mb-2">
                <div class="col-md-6 mb-3 mb-md-0">
                    <div style="font-size:.75rem; font-weight:700; color:#b91c1c; text-transform:uppercase; margin-bottom:.5rem; letter-spacing:.03em;"><i class="fas fa-list me-1"></i> Rincian Pelanggaran</div>
                    <?php if (empty($pelanggaran_list)): ?>
                        <div class="text-muted" style="font-size:.8rem; font-style:italic;">Tidak ada pelanggaran setahun ini.</div>
                    <?php else: ?>
                        <ul class="list-group list-group-flush border rounded-3 overflow-hidden">
                            <?php foreach ($pelanggaran_list as $p): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-3" style="font-size:.8rem; background:#fff;">
                                <div>
                                    <?= htmlspecialchars($p['nama_pelanggaran']) ?>
                                    <?php if ($p['jumlah'] > 1): ?>
                                        <span class="badge bg-secondary ms-1" style="font-size:.65rem;"><?= $p['jumlah'] ?>x</span>
                                    <?php endif; ?>
                                </div>
                                <span class="badge rounded-pill" style="background:#fee2e2; color:#b91c1c; font-size:.75rem;"><?= $p['poin'] ?> poin</span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <div style="font-size:.75rem; font-weight:700; color:#1a7c4f; text-transform:uppercase; margin-bottom:.5rem; letter-spacing:.03em;"><i class="fas fa-list me-1"></i> Rincian Reward</div>
                    <?php if (empty($reward_list)): ?>
                        <div class="text-muted" style="font-size:.8rem; font-style:italic;">Tidak ada reward setahun ini.</div>
                    <?php else: ?>
                        <ul class="list-group list-group-flush border rounded-3 overflow-hidden">
                            <?php foreach ($reward_list as $r): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-3" style="font-size:.8rem; background:#fff;">
                                <div>
                                    <?= htmlspecialchars($r['nama_reward']) ?>
                                    <?php if ($r['jumlah'] > 1): ?>
                                        <span class="badge bg-secondary ms-1" style="font-size:.65rem;"><?= $r['jumlah'] ?>x</span>
                                    <?php endif; ?>
                                </div>
                                <span class="badge rounded-pill" style="background:#d1fae5; color:#065f46; font-size:.75rem;">+<?= $r['poin'] ?> poin</span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Catatan Pembinaan Otomatis -->
    <div class="sec-card">
        <div class="sec-hdr">
            <div class="sec-icon" style="background:var(--c-primary-light);color:var(--c-primary);"><i class="fas fa-pen-nib"></i></div>
            Catatan Pembinaan
            <span class="ms-2 badge rounded-pill" style="background:var(--c-primary-light);color:var(--c-primary);font-size:.7rem;">Otomatis</span>
        </div>
        <div class="sec-body">
            <?php if (!empty($catatan_otomatis)): ?>
            <div class="narasi-box" id="narasi-global-box"><?= nl2br(htmlspecialchars($catatan_otomatis)) ?></div>
            <?php else: ?>
            <div class="d-flex align-items-center gap-3 py-2" style="color:#94a3b8;">
                <div style="width:36px;height:36px;border-radius:10px;background:#f1f5f9;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="fas fa-comment-slash"></i>
                </div>
                <div>
                    <div style="font-size:.875rem;font-weight:600;color:#64748b;">Catatan belum tersedia</div>
                    <div style="font-size:.78rem;">
                        <a href="generate.php?kamar=<?= urlencode($rapot['kamar']) ?>&periode=<?= urlencode($rapot['periode']) ?>" class="text-primary text-decoration-none fw-semibold">Generate ulang rapor</a> untuk membuat catatan otomatis.
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($status === 'DRAFT' && $can_create): ?>
            <div class="mt-3 d-grid d-sm-block text-sm-end">
                <button type="button" class="btn btn-primary fw-semibold px-4" style="border-radius:.75rem;" data-bs-toggle="modal" data-bs-target="#editCatatanModal">
                    <i class="fas fa-edit me-2"></i>Edit Catatan
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Aksi -->
    <div class="sec-card">
        <div class="sec-body">
            <?php if ($status !== 'APPROVED'): ?>
            <div style="background:#eff6ff;border-left:4px solid #2563eb;border-radius:0 .625rem .625rem 0;padding:.7rem 1rem;font-size:.82rem;color:#1d4ed8;margin-bottom:1rem;">
                <i class="fas fa-info-circle me-1"></i>
                Rapor tahunan hanya tersedia dalam format <strong>PDF</strong>.
                Download hanya tersedia setelah berstatus <strong>APPROVED</strong>.
            </div>
            <?php endif; ?>

            <div class="act-row">
                <button type="button" class="btn btn-info fw-semibold text-white px-4" style="border-radius:.75rem;" onclick="openViewModal('view.php?id=<?= $rapot['id'] ?>')">
                    <i class="fas fa-eye me-2"></i>Preview Rapot
                </button>

                <?php if ($status === 'APPROVED' && $can_cetak): ?>
                <a href="../export/generate_pdf_tahunan.php?id=<?= $rapot['id'] ?>"
                   class="btn btn-primary fw-semibold px-4" style="border-radius:.75rem;">
                    <i class="fas fa-file-pdf me-2"></i>Download PDF
                </a>
                <?php else: ?>
                <button class="btn btn-secondary fw-semibold px-4" style="border-radius:.75rem;" disabled>
                    <i class="fas fa-file-pdf me-2"></i>Download PDF
                </button>
                <?php endif; ?>

                <?php if ($status === 'DRAFT' && $can_create): ?>
                    <?php if ($is_notes_complete): ?>
                    <button type="button" class="btn btn-success fw-semibold px-4" style="border-radius:.75rem;" onclick="approveRapor()">
                        <i class="fas fa-check me-2"></i>Approve Rapor
                    </button>
                    <?php else: ?>
                    <button type="button" class="btn btn-success fw-semibold px-4" style="border-radius:.75rem;" onclick="showIncompleteAlert()">
                        <i class="fas fa-check me-2"></i>Approve Rapor
                    </button>
                    <script>
                    function showIncompleteAlert() {
                        let missing = <?php echo json_encode($missing_notes); ?>;
                        let listHtml = '<ul style="text-align:left; font-size:0.9rem; color:#b91c1c; margin-top:10px;">';
                        missing.forEach(item => { listHtml += '<li>' + item + '</li>'; });
                        listHtml += '</ul>';
                        
                        Swal.fire({
                            title: 'Belum Bisa Approve',
                            html: '<div style="font-size:0.95rem; color:#334155;">Harap lengkapi catatan berikut di menu <strong>Edit Catatan</strong> sebelum melakukan Approve:</div>' + listHtml,
                            icon: 'error',
                            confirmButtonColor: '#1d6fa4'
                        });
                    }
                    </script>
                    <?php endif; ?>
                <?php endif; ?>

                <div class="ms-auto">
                    <a href="list.php?kamar=<?= urlencode($rapot['kamar']) ?>&periode=<?= urlencode($rapot['periode']) ?>"
                       class="btn btn-light border fw-medium px-4" style="border-radius:.75rem;">
                        <i class="fas fa-arrow-left me-1"></i> Kembali ke Daftar
                    </a>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Modal Edit Catatan -->
<div class="modal fade" id="editCatatanModal" tabindex="-1" aria-labelledby="editCatatanModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form method="POST" action="">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title fw-bold" id="editCatatanModalLabel">Edit Catatan Rapor</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
            <input type="hidden" name="action" value="save_catatan">
            
            <div class="mb-4">
                <label class="form-label fw-bold">Catatan Keseluruhan</label>
                <textarea name="catatan_global" class="form-control" rows="4"><?= htmlspecialchars($catatan_otomatis) ?></textarea>
                <div class="form-text">Catatan global yang tampil di bagian akhir Halaman 1 PDF.</div>
            </div>

            <hr>
            <h6 class="fw-bold mb-3">Catatan Per Mutu</h6>
            <?php foreach ($nilai_aspek as $idx => $aspek): 
                $catatan_m = $aspek['catatan'] ?? ($can_ai ? generate_catatan_per_aspek($aspek) : '');
            ?>
            <div class="mb-3">
                <label class="form-label fw-semibold text-primary">Aspek <?= htmlspecialchars($aspek['aspek']) ?></label>
                <textarea name="catatan_aspek_<?= $idx ?>" class="form-control" rows="3"><?= htmlspecialchars($catatan_m) ?></textarea>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Simpan Perubahan</button>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
// --- Global Loader Controller ---
const GlobalLoader = {
    show: function(text = 'Memproses data...') {
        document.getElementById('loaderText').innerText = text;
        document.getElementById('loaderSpinner').style.display = 'block';
        document.getElementById('loaderSuccess').style.display = 'none';
        document.getElementById('globalLoader').classList.add('show');
    },
    success: function(text = 'Berhasil!', callback = null) {
        document.getElementById('loaderText').innerText = text;
        document.getElementById('loaderSpinner').style.display = 'none';
        document.getElementById('loaderSuccess').style.display = 'block';
        if(callback) {
            setTimeout(callback, 800);
        } else {
            setTimeout(() => { this.hide(); }, 1200);
        }
    },
    hide: function() {
        document.getElementById('globalLoader').classList.remove('show');
    }
};

// --- Edit Nilai per Baris ---
function editNilai(idxAspek, idxSub, namaSub, paramVal) {
    let currentVal = paramVal;
    const spanEl = document.getElementById(`sc-${idxAspek}-${idxSub}`);
    if (spanEl && spanEl.innerText) {
        currentVal = parseFloat(spanEl.innerText);
    }

    Swal.fire({
        title: 'Edit Nilai',
        html: `Ubah nilai untuk <b>${namaSub}</b><br><br>
               <input type="number" id="inputEditNilai" class="form-control text-center fs-4 fw-bold mx-auto" 
                      style="width:120px;" step="0.1" min="1" max="5" value="${currentVal}">`,
        showCancelButton: true,
        reverseButtons: true,
        confirmButtonColor: '#1d6fa4',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Lanjutkan <i class="fas fa-arrow-right ms-1"></i>',
        cancelButtonText: 'Batal',
        didOpen: () => {
            document.getElementById('inputEditNilai').focus();
            document.getElementById('inputEditNilai').select();
        }
    }).then((res) => {
        if(res.isConfirmed) {
            const newVal = parseFloat(document.getElementById('inputEditNilai').value);
            if(isNaN(newVal) || newVal < 1 || newVal > 5) {
                Swal.fire('Error', 'Nilai harus di antara 1.0 - 5.0', 'error');
                return;
            }
            if(newVal === parseFloat(currentVal)) {
                return; // Tidak ada perubahan
            }
            
            const canAi = <?= $can_ai ? 'true' : 'false' ?>;
            if (!canAi) {
                // Langsung simpan nilai tanpa regen AI jika tak punya izin
                simpanNilaiKeServer(idxAspek, idxSub, newVal, false);
                return;
            }
            
            // Tanya apakah ingin update catatan AI
            Swal.fire({
                title: 'Perbarui Catatan AI?',
                html: `
                    <div style="font-size: 0.95rem; color: #334155; margin-bottom: 1.2rem;">
                        Anda telah mengubah nilai santri. Apakah narasi catatan AI juga perlu disesuaikan dengan nilai yang baru?
                    </div>
                    <div style="text-align: left; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 1rem; font-size: 0.85rem; line-height: 1.5; color: #475569;">
                        <div style="margin-bottom: 0.8rem;">
                            <strong style="color: #1a7c4f;"><i class="fas fa-robot me-1"></i> Ya, Perbarui</strong> &mdash; Simpan nilai dan biarkan AI meracik ulang narasinya.
                        </div>
                        <div style="margin-bottom: 0.8rem;">
                            <strong style="color: #b45309;"><i class="fas fa-ban me-1"></i> Tidak, Biarkan</strong> &mdash; Simpan nilai saja. Catatan yang sudah ada tidak akan diubah.
                        </div>
                        <div>
                            <strong style="color: #64748b;">Batal</strong> &mdash; Batalkan perubahan (nilai tidak jadi disimpan).
                        </div>
                    </div>
                `,
                icon: 'question',
                showCancelButton: true,
                reverseButtons: true,
                showDenyButton: true,
                confirmButtonColor: '#1a7c4f',
                denyButtonColor: '#b45309',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Ya, Perbarui',
                denyButtonText: 'Tidak, Biarkan',
                cancelButtonText: 'Batal'
            }).then((res2) => {
                if(res2.isConfirmed || res2.isDenied) {
                    const regenerate = res2.isConfirmed;
                    simpanNilaiKeServer(idxAspek, idxSub, newVal, regenerate);
                }
            });
        }
    });
}

function simpanNilaiKeServer(idxAspek, idxSub, newVal, regenerate) {
    GlobalLoader.show('Menyimpan nilai...');
    
    const formData = new FormData();
    formData.append('rapor_id', <?= $rapot['id'] ?>);
    formData.append('idx_aspek', idxAspek);
    formData.append('idx_sub', idxSub);
    formData.append('new_score', newVal);
    formData.append('regenerate_notes', regenerate);

    fetch('../api/update_nilai_tahunan.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        if(res.status === 'success') {
            const d = res.data;
            
            // Update tabel UI
            const spanNilai = document.getElementById(`sc-${idxAspek}-${idxSub}`);
            if(spanNilai) {
                spanNilai.className = `sc sc-${d.rounded_score}`;
                spanNilai.innerText = Number.isInteger(d.new_score) ? d.new_score : d.new_score.toFixed(1);
            }
            const tdPenjelasan = document.getElementById(`td-penjelasan-${idxAspek}-${idxSub}`);
            if(tdPenjelasan) tdPenjelasan.innerText = d.penjelasan;
            
            const tdTotal = document.getElementById('total-nilai-global');
            if(tdTotal) tdTotal.innerText = Number.isInteger(d.total_nilai) ? d.total_nilai : d.total_nilai.toFixed(1);
            
            // Update Narasi jika diregenerate
            if(regenerate) {
                const tdAspek = document.getElementById(`catatan-aspek-${idxAspek}`);
                if(tdAspek && d.catatan_aspek) tdAspek.innerText = d.catatan_aspek;
                
                const boxGlobal = document.getElementById('narasi-global-box');
                if(boxGlobal && d.catatan_global) {
                    // ganti nl2br
                    boxGlobal.innerHTML = d.catatan_global.replace(/(?:\r\n|\r|\n)/g, '<br>');
                }
            }

            GlobalLoader.success('Nilai Diperbarui!');
        } else {
            GlobalLoader.hide();
            Swal.fire('Gagal!', res.message || 'Terjadi kesalahan.', 'error');
        }
    })
    .catch(err => {
        GlobalLoader.hide();
        Swal.fire('Error Server', err.toString(), 'error');
    });
}

// --- Approve Rapor dengan Animasi Transisi ---
function approveRapor() {
    Swal.fire({
        title: 'Approve Rapor?',
        text: 'Apakah Anda yakin ingin melakukan Approve? Rapor yang di-approve siap untuk dicetak/download dan catatannya tidak dapat diubah lagi.',
        icon: 'warning',
        showCancelButton: true,
        reverseButtons: true,
        confirmButtonColor: '#1a7c4f',
        cancelButtonColor: '#64748b',
        confirmButtonText: '<i class="fas fa-check me-1"></i> Ya, Approve',
        cancelButtonText: 'Batal'
    }).then((res) => {
        if(res.isConfirmed) {
            GlobalLoader.show('Memproses persetujuan...');
            
            const formData = new FormData();
            formData.append('action', 'approve');
            formData.append('rapor_id', <?= $rapot['id'] ?>);
            
            fetch('list.php?kamar=<?= urlencode($rapot['kamar']) ?>&periode=<?= urlencode($rapot['periode']) ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if(response.ok) {
                    GlobalLoader.success('Rapor Approved!', () => {
                        window.location.href = 'list.php?kamar=<?= urlencode($rapot['kamar']) ?>&periode=<?= urlencode($rapot['periode']) ?>';
                    });
                } else {
                    throw new Error('Gagal terhubung ke server');
                }
            })
            .catch(err => {
                GlobalLoader.hide();
                Swal.fire('Gagal', err.message, 'error');
            });
        }
    });
}
</script>

<!-- Modal Panduan Detail -->
<div class="modal fade" id="guideModalDetail" tabindex="-1" aria-labelledby="guideModalDetailLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 1rem;">
      <div class="modal-header border-bottom-0 pb-0 mt-2 mx-2">
        <h5 class="modal-title fw-bolder text-dark" id="guideModalDetailLabel">
            <i class="fas fa-spinner fa-spin text-primary me-2"></i>Memuat Panduan...
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body pt-3 px-4 pb-4 text-muted" id="guideModalDetailBody">
         <div class="text-center py-4">
             <div class="spinner-border text-primary" role="status">
                 <span class="visually-hidden">Loading...</span>
             </div>
         </div>
      </div>
      <div class="modal-footer border-top-0 pt-0 pb-4 px-4">
        <button type="button" class="btn btn-primary w-100 fw-medium shadow-sm" style="border-radius: 0.75rem;" data-bs-dismiss="modal">Saya Mengerti</button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const guideModalDetail = document.getElementById('guideModalDetail');
    let guideDetailLoaded = false;
    
    if (guideModalDetail) {
        guideModalDetail.addEventListener('show.bs.modal', function () {
            if (guideDetailLoaded) return;
            
            fetch('../api/guide_tahunan_detail.php')
                .then(response => response.json())
                .then(res => {
                    if(res.status === 'success') {
                        document.getElementById('guideModalDetailLabel').innerHTML = res.data.title;
                        document.getElementById('guideModalDetailBody').innerHTML = res.data.content;
                        guideDetailLoaded = true;
                    } else {
                        document.getElementById('guideModalDetailLabel').innerHTML = '<i class="fas fa-exclamation-triangle text-danger me-2"></i>Gagal Memuat';
                        document.getElementById('guideModalDetailBody').innerHTML = '<div class="alert alert-danger">Gagal memuat panduan: ' + (res.message || 'Error tidak diketahui') + '</div>';
                    }
                })
                .catch(err => {
                    document.getElementById('guideModalDetailLabel').innerHTML = '<i class="fas fa-exclamation-triangle text-danger me-2"></i>Koneksi Error';
                    document.getElementById('guideModalDetailBody').innerHTML = '<div class="alert alert-danger">Terjadi kesalahan saat menghubungi server.</div>';
                });
        });
    }
});

// === Logika View Modal ===
window.openViewModal = function(url) {
    document.getElementById('viewRapotIframe').src = url;
    var myModal = new bootstrap.Modal(document.getElementById('viewRapotModal'));
    myModal.show();
};
</script>

<!-- Modal View Rapot -->
<div class="modal fade" id="viewRapotModal" tabindex="-1" aria-labelledby="viewRapotModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="background-color: #525659; overflow: hidden; max-height: 95vh;">
            <div class="modal-header bg-dark text-white border-0 py-2">
                <h6 class="modal-title text-white m-0"><i class="fas fa-eye text-white me-2"></i>Preview Rapot</h6>
                <div class="ms-auto d-flex align-items-center">
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" onclick="document.getElementById('viewRapotIframe').src=''"></button>
                </div>
            </div>
            <div class="modal-body p-0 position-relative" style="height: 85vh; background-color: #525659;">
                <iframe id="viewRapotIframe" src="" style="width:100%; height:100%; border:none; background-color: white;"></iframe>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
