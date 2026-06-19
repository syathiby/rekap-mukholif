<?php
// rapot/crud_tahunan/detail.php
// Halaman detail rapor tahunan — format tabel identik dengan rapot bulanan

require_once __DIR__ . '/../../bootstrap/init.php';
require_once __DIR__ . '/../config/helper.php';
require_once __DIR__ . '/../api/generate_catatan.php';
guard(['rapot_view', 'rapot_cetak', 'rapot_create']);

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
        LEFT JOIN users  u ON rt.approved_by = u.id
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
$sql_pel = "SELECT SUM(jp.poin) as total FROM pelanggaran p
            JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id
            WHERE p.santri_id = ? AND (YEAR(p.tanggal) = ? OR YEAR(p.tanggal) = ?)";
$stmt_pel = $conn->prepare($sql_pel);
$t2 = $tahun_awal + 1;
$stmt_pel->bind_param('iii', $santri_id, $tahun_awal, $t2);
$stmt_pel->execute();
$total_pelanggaran = (int)$stmt_pel->get_result()->fetch_assoc()['total'];
$stmt_pel->close();

$sql_rew = "SELECT SUM(jr.poin_reward) as total FROM daftar_reward rwd
            JOIN jenis_reward jr ON rwd.jenis_reward_id = jr.id
            WHERE rwd.santri_id = ? AND (YEAR(rwd.tanggal) = ? OR YEAR(rwd.tanggal) = ?)";
$stmt_rew = $conn->prepare($sql_rew);
$stmt_rew->bind_param('iii', $santri_id, $tahun_awal, $t2);
$stmt_rew->execute();
$total_reward = (int)$stmt_rew->get_result()->fetch_assoc()['total'];
$stmt_rew->close();

$sql_pel_list = "SELECT jp.nama_pelanggaran, SUM(jp.poin) AS poin, COUNT(*) as jumlah
                 FROM pelanggaran pel
                 JOIN jenis_pelanggaran jp ON pel.jenis_pelanggaran_id = jp.id
                 WHERE pel.santri_id = ? AND (YEAR(pel.tanggal) = ? OR YEAR(pel.tanggal) = ?)
                 GROUP BY jp.id
                 ORDER BY poin DESC";
$stmt_pl = $conn->prepare($sql_pel_list);
$stmt_pl->bind_param('iii', $santri_id, $tahun_awal, $t2);
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
                 WHERE rwd.santri_id = ? AND (YEAR(rwd.tanggal) = ? OR YEAR(rwd.tanggal) = ?)
                 GROUP BY jr.id
                 ORDER BY poin DESC";
$stmt_rl = $conn->prepare($sql_rew_list);
$stmt_rl->bind_param('iii', $santri_id, $tahun_awal, $t2);
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
$status     = $rapot['status'] ?? 'DRAFT';

// Hitung total nilai (jumlah semua nilai_final)
$total_nilai = 0;
foreach ($nilai_aspek as $aspek) {
    foreach ($aspek['sub_mutu'] ?? [] as $sub) {
        $total_nilai += (float)($sub['nilai_final'] ?? 0);
    }
}

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
    .kor-tag { font-size: .65rem; background: #fef3c7; color: #b45309;
               border: 1px solid #f59e0b; padding: 1px 4px; border-radius: 4px; margin-left: 2px; vertical-align: super; }

    /* ─── Catatan otomatis per mutu ─── */
    .catatan-mutu {
        background: #f8fafc; border-left: 3px solid var(--c-primary);
        padding: .5rem .875rem; font-size: .82rem; color: #334155;
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

    @media (max-width: 576px) {
        .info-bar { gap: .875rem; }
        .act-row  { flex-direction: column; }
        .act-row .ms-auto { margin-left: 0 !important; width: 100%; }
        .act-row a, .act-row button { width: 100%; text-align: center; }
        .sec-hdr { flex-wrap: wrap; }
    }
</style>

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
            <div class="info-lbl">Nama Santri</div>
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
                ?>
                <span class="chip <?= $pill_class ?>"><i class="fas <?= $pill_icon ?>"></i> <?= $status ?></span>
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
            <?php $koreksi_list = []; ?>
            <table class="tbl-nilai">
                <thead>
                    <tr>
                        <th style="width:13%;">Mutu</th>
                        <th style="width:22%;">Sub Mutu</th>
                        <th style="width:50%;">Penjelasan</th>
                        <th style="width:10%;">Nilai</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($nilai_aspek as $idx_aspek => $aspek):
                        $subs       = $aspek['sub_mutu'] ?? [];
                        $row_count  = count($subs);
                        // +1 untuk baris catatan per mutu
                        $total_rows = $row_count + 1;
                        $catatan_m  = $aspek['catatan'] ?? generate_catatan_per_aspek($aspek);
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
                        <td class="td-penjelasan"><?= htmlspecialchars($ket) ?></td>
                        <td class="td-nilai">
                            <span class="sc sc-<?= $sc ?>"><?= number_format($nf, $nf == floor($nf) ? 0 : 1) ?></span>
                            <?php if (!empty($sub['ada_koreksi'])): 
                                $koreksi_list[] = "<b>" . htmlspecialchars($sub['nama']) . "</b>: " . htmlspecialchars($sub['alasan_koreksi']);
                            ?>
                            <span class="kor-tag" title="<?= htmlspecialchars($sub['alasan_koreksi'] ?? '') ?>">✱</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <!-- Baris catatan per mutu -->
                    <tr class="catatan-mutu">
                        <td colspan="3" style="background:#f8fafc; font-style: italic; color: #334155; font-size: .82rem; padding: .5rem .875rem;">
                            <i class="fas fa-comment-dots me-1 opacity-50"></i><?= htmlspecialchars($catatan_m) ?>
                        </td>
                        <td style="background:#f8fafc;"></td>
                    </tr>
                    <?php endforeach; ?>

                    <!-- Baris Jumlah -->
                    <tr>
                        <td colspan="3" class="td-jumlah">Jumlah</td>
                        <td class="td-jumlah-val"><?= number_format($total_nilai, $total_nilai == floor($total_nilai) ? 0 : 1) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <?php if (!empty($koreksi_list)): ?>
        <div class="px-4 py-3" style="background:#fffbeb; border-bottom: 1px solid var(--c-border);">
            <div style="font-size:.8rem; color:#b45309; font-weight:600; margin-bottom:.4rem;">
                <i class="fas fa-info-circle me-1"></i> Penjelasan Koreksi Nilai (✱)
            </div>
            <ul style="margin:0; padding-left:1.2rem; font-size:.78rem; color:#92400e; line-height:1.5;">
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
            <div class="narasi-box"><?= nl2br(htmlspecialchars($catatan_otomatis)) ?></div>
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
                <form method="POST" 
                      action="list.php?kamar=<?= urlencode($rapot['kamar']) ?>&periode=<?= urlencode($rapot['periode']) ?>"
                      class="d-inline" 
                      onsubmit="confirmSubmit(event, this, 'Approve Rapor?', 'Apakah Anda yakin ingin melakukan Approve? Rapor yang di-approve siap untuk dicetak/download dan catatannya tidak dapat diubah lagi.');">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="rapor_id" value="<?= $rapot['id'] ?>">
                    <button type="submit" class="btn btn-success fw-semibold px-4" style="border-radius:.75rem;">
                        <i class="fas fa-check me-2"></i>Approve Rapor
                    </button>
                </form>
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

</div><!-- .detail-wrap -->
</div><!-- .container-fluid -->

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
                $catatan_m = $aspek['catatan'] ?? generate_catatan_per_aspek($aspek);
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

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
