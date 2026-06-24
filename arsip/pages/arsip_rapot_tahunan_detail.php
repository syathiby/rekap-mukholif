<?php
// arsip/pages/arsip_rapot_tahunan_detail.php
require_once __DIR__ . '/../../bootstrap/init.php';

guard('arsip_view');

$arsip_id  = (int)($_GET['id'] ?? 0);
$santri_id = (int)($_GET['santri_id'] ?? 0);
$periode   = trim($_GET['periode'] ?? '');

$page_title = "Detail Rapor Tahunan (Arsip)";

if ($arsip_id <= 0 || !$santri_id || !$periode) {
    die('Parameter tidak lengkap.');
}

// Ambil data rapor tahunan dari arsip
try {
    $stmt = $conn->prepare("
        SELECT rt.*, rt.santri_nama AS nama_santri, rt.kamar, rt.santri_kelas as kelas, rt.approved_by_nama AS nama_musyrif
        FROM arsip_data_rapot_tahunan rt
        WHERE rt.arsip_id = ? AND rt.santri_id = ? AND rt.periode = ?
        LIMIT 1
    ");
    $stmt->bind_param('iis', $arsip_id, $santri_id, $periode);
    $stmt->execute();
    $rapot = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}

if (!$rapot) {
    die('Data rapor tahunan di arsip tidak ditemukan.');
}

$nilai_aspek = json_decode($rapot['nilai_snapshot'] ?? '[]', true) ?? [];

// Hitung poin real dari database arsip
$sql_pel = "SELECT COALESCE(SUM(poin),0) as total FROM arsip_data_pelanggaran WHERE arsip_id = ? AND santri_id = ? AND tipe = 'Umum'";
$stmt_pel = $conn->prepare($sql_pel);
$stmt_pel->bind_param('ii', $arsip_id, $santri_id);
$stmt_pel->execute();
$total_pelanggaran = (int)$stmt_pel->get_result()->fetch_assoc()['total'];
$stmt_pel->close();

$sql_rew = "SELECT COALESCE(SUM(poin_reward),0) as total FROM arsip_data_reward WHERE arsip_id = ? AND santri_id = ?";
$stmt_rew = $conn->prepare($sql_rew);
$stmt_rew->bind_param('ii', $arsip_id, $santri_id);
$stmt_rew->execute();
$total_reward = (int)$stmt_rew->get_result()->fetch_assoc()['total'];
$stmt_rew->close();

$sql_pel_list = "SELECT jenis_pelanggaran_nama as nama_pelanggaran, SUM(poin) AS poin, COUNT(*) as jumlah
                 FROM arsip_data_pelanggaran
                 WHERE arsip_id = ? AND santri_id = ? AND tipe = 'Umum'
                 GROUP BY jenis_pelanggaran_nama
                 ORDER BY poin DESC";
$stmt_pl = $conn->prepare($sql_pel_list);
$stmt_pl->bind_param('ii', $arsip_id, $santri_id);
$stmt_pl->execute();
$res_pl = $stmt_pl->get_result();
$pelanggaran_list = [];
while ($row = $res_pl->fetch_assoc()) {
    $pelanggaran_list[] = $row;
}
$stmt_pl->close();

$sql_rew_list = "SELECT nama_reward, SUM(poin_reward) AS poin, COUNT(*) as jumlah
                 FROM arsip_data_reward
                 WHERE arsip_id = ? AND santri_id = ?
                 GROUP BY nama_reward
                 ORDER BY poin DESC";
$stmt_rl = $conn->prepare($sql_rew_list);
$stmt_rl->bind_param('ii', $arsip_id, $santri_id);
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
$status     = $rapot['status'] ?? 'DRAFT';

// Hitung total nilai
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
    .tbl-nilai .td-penjelasan { color: var(--c-muted); text-align: center; font-size: .82rem; }
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

    .sc { display: inline-block; width: 32px; height: 32px; border-radius: 50%;
          line-height: 32px; text-align: center; font-weight: 800; font-size: .85rem; }
    .sc-5 { background: #d1fae5; color: #065f46; }
    .sc-4 { background: #dbeafe; color: #1d4ed8; }
    .sc-3 { background: #fef3c7; color: #b45309; }
    .sc-2 { background: #ffedd5; color: #c2410c; }
    .sc-1 { background: #fee2e2; color: #b91c1c; }
    .sc-0 { background: #f1f5f9; color: #94a3b8; }

    .kor-tag { font-size: .65rem; background: #fef3c7; color: #b45309; border: 1px solid #f59e0b; padding: 1px 4px; border-radius: 4px; margin-left: 2px; vertical-align: super; }
    .kor-tag.kor-minus { background: #fee2e2; color: #b91c1c; border-color: #fca5a5; }
    .kor-tag.kor-plus  { background: #dcfce7; color: #15803d; border-color: #86efac; }
    .kor-tag.kor-mix   { background: #e0e7ff; color: #4338ca; border-color: #a5b4fc; } 

    .catatan-mutu {
        background: #f8fafc; font-size: .82rem; color: #334155; line-height: 1.6;
    }
    .catatan-mutu td { border: 1px solid #e9edf3 !important; }

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

    .narasi-box {
        background: #f0f9ff; border-left: 4px solid var(--c-primary);
        border-radius: 0 .625rem .625rem 0;
        padding: 1rem 1.25rem; font-size: .9rem; line-height: 1.75; color: #334155;
    }

    .act-row { display: flex; gap: .75rem; flex-wrap: wrap; align-items: center; }

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

    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="../index.php" class="text-decoration-none">Arsip</a></li>
            <li class="breadcrumb-item"><a href="../view.php?id=<?= $arsip_id ?>" class="text-decoration-none">Arsip View</a></li>
            <li class="breadcrumb-item"><a href="arsip_rapot_tahunan.php?id=<?= $arsip_id ?>" class="text-decoration-none">Rapor Tahunan</a></li>
            <li class="breadcrumb-item"><a href="arsip_rapot_tahunan_list.php?id=<?= $arsip_id ?>&kamar=<?= urlencode($rapot['kamar']) ?>&periode=<?= urlencode($rapot['periode']) ?>" class="text-decoration-none">Kamar <?= htmlspecialchars($rapot['kamar']) ?></a></li>
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
                        <th style="width:20%;">Nilai</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($nilai_aspek as $idx_aspek => $aspek):
                        $subs       = $aspek['sub_mutu'] ?? [];
                        $row_count  = count($subs);
                        $total_rows = $row_count + 1;
                        $catatan_m  = $aspek['catatan'] ?? '';
                    ?>
                    <?php foreach ($subs as $i => $sub):
                        $nf  = (float)($sub['nilai_final'] ?? 0);
                        $nfr = (int)round($nf);
                        $sc  = max(0, min(5, $nfr));
                    ?>
                    <tr>
                        <?php if ($i === 0): ?>
                        <td rowspan="<?= $total_rows ?>" class="td-mutu">
                            <?= htmlspecialchars($aspek['aspek']) ?>
                        </td>
                        <?php endif; ?>
                        <td class="td-sub"><?= htmlspecialchars($sub['nama']) ?></td>
                        <td class="td-penjelasan"><?= htmlspecialchars($sub['deskripsi_nilai'] ?? '') ?></td>
                        <td class="td-nilai">
                            <span class="sc sc-<?= $sc ?>"><?= number_format($nf, $nf == floor($nf) ? 0 : 1) ?></span>
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
                    </tr>
                    <?php endforeach; ?>
                    <tr class="catatan-mutu">
                        <td colspan="2" style="background:#f8fafc; font-style: italic; color: #334155; font-size: .82rem; padding: .5rem .875rem;">
                            <i class="fas fa-comment-dots me-1 opacity-50"></i><span><?= htmlspecialchars($catatan_m) ?></span>
                        </td>
                        <td colspan="1" style="background:#f8fafc;"></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr>
                        <td colspan="3" class="td-jumlah">Jumlah</td>
                        <td colspan="1" class="td-jumlah-val"><?= number_format($total_nilai, $total_nilai == floor($total_nilai) ? 0 : 1) ?></td>
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

    <!-- Catatan Pembinaan -->
    <div class="sec-card">
        <div class="sec-hdr">
            <div class="sec-icon" style="background:var(--c-primary-light);color:var(--c-primary);"><i class="fas fa-pen-nib"></i></div>
            Catatan Pembinaan
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
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Aksi -->
    <div class="sec-card">
        <div class="sec-body">
            <div class="act-row">
                <button type="button" class="btn btn-info fw-semibold text-white px-4" style="border-radius:.75rem;" onclick="openViewModal('arsip_rapot_tahunan_view.php?id=<?= $rapot['id'] ?>&arsip_id=<?= $arsip_id ?>')">
                    <i class="fas fa-eye me-2"></i>Preview Rapot
                </button>

                <?php if (in_array($status, ['APPROVED', 'EXPORTED'])): ?>
                <a href="../export/generate_pdf_tahunan_arsip.php?rapor_id=<?= $rapot['id'] ?>"
                   class="btn btn-primary fw-semibold px-4" style="border-radius:.75rem;">
                    <i class="fas fa-file-pdf me-2"></i>Download PDF
                </a>
                <?php else: ?>
                <button class="btn btn-secondary fw-semibold px-4" style="border-radius:.75rem;" disabled>
                    <i class="fas fa-file-pdf me-2"></i>Download PDF
                </button>
                <?php endif; ?>

                <div class="ms-auto">
                    <a href="arsip_rapot_tahunan_list.php?id=<?= $arsip_id ?>&kamar=<?= urlencode($rapot['kamar']) ?>&periode=<?= urlencode($rapot['periode']) ?>"
                       class="btn btn-light border fw-medium px-4" style="border-radius:.75rem;">
                        <i class="fas fa-arrow-left me-1"></i> Kembali ke Daftar
                    </a>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Modal View Rapot -->
<div class="modal fade" id="viewRapotModal" tabindex="-1" aria-labelledby="viewRapotModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="background-color: #525659; overflow: hidden; max-height: 95vh;">
            <div class="modal-header bg-dark text-white border-0 py-2">
                <h6 class="modal-title text-white m-0"><i class="fas fa-eye text-white me-2"></i>Preview Rapot</h6>
                <div class="d-flex align-items-center gap-3">
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" onclick="document.getElementById('viewRapotIframe').src=''"></button>
                </div>
            </div>
            <div class="modal-body p-0 position-relative" style="height: 85vh; background-color: #525659;">
                <div id="loadingIframe" class="position-absolute top-50 start-50 translate-middle text-center text-white">
                    <div class="spinner-border text-light mb-2" role="status"></div>
                    <div>Memuat Preview...</div>
                </div>
                <iframe id="viewRapotIframe" src="" style="width: 100%; height: 100%; border: none; background: transparent; position: relative; z-index: 2;" onload="document.getElementById('loadingIframe').style.display='none';"></iframe>
            </div>
        </div>
    </div>
</div>

<script>
window.openViewModal = function(url) {
    document.getElementById('loadingIframe').style.display = 'block';
    document.getElementById('viewRapotIframe').src = url;
    var myModal = new bootstrap.Modal(document.getElementById('viewRapotModal'));
    myModal.show();
}
</script>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
