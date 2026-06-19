<?php
// rapot/crud_bulanan/detail.php
// Pengganti view.php — Halaman detail rapor bulanan dengan UI modern

require_once __DIR__ . '/../../bootstrap/init.php';
require_once __DIR__ . '/../config/helper.php';

guard('rapot_view');

if (empty($_GET['id'])) {
    set_flash_message('ID Rapot tidak ditemukan.', 'danger');
    header('Location: ../index.php');
    exit;
}
$rapot_id = (int)$_GET['id'];
$page_title = "Detail Rapor Bulanan";

try {
    $sql = "
        SELECT 
            r.*, 
            s.id AS santri_id, s.nama AS nama_santri, s.kamar AS kamar_santri, s.kelas AS kelas_santri,
            u.nama_lengkap AS nama_musyrif
        FROM rapot_kepengasuhan r
        LEFT JOIN santri s ON r.santri_id = s.id
        LEFT JOIN users u ON r.musyrif_id = u.id
        WHERE r.id = ?
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $rapot_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $rapot = $result->fetch_assoc();
    $stmt->close();

    if (!$rapot) {
        set_flash_message('Data rapor bulanan tidak ditemukan.', 'warning');
        header('Location: ../index.php');
        exit;
    }
    
    // Ambil rincian pelanggaran (hanya yg poin > 0)
    $pelanggaran_list = [];
    $sql_pelanggaran = "
        SELECT jp.nama_pelanggaran, jp.poin as poin, COUNT(*) as jumlah
        FROM pelanggaran p
        JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id
        WHERE p.santri_id = ? 
          AND MONTH(p.tanggal) = FIND_IN_SET(?, 'Januari,Februari,Maret,April,Mei,Juni,Juli,Agustus,September,Oktober,November,Desember')
          AND YEAR(p.tanggal) = ?
          AND jp.poin > 0
        GROUP BY jp.id
        ORDER BY poin DESC
    ";
    $stmt_pelanggaran = $conn->prepare($sql_pelanggaran);
    $stmt_pelanggaran->bind_param("isi", $rapot['santri_id'], $rapot['bulan'], $rapot['tahun']);
    $stmt_pelanggaran->execute();
    $pelanggaran_list = $stmt_pelanggaran->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_pelanggaran->close();

    // Ambil rincian REWARD (hanya yg poin > 0)
    $reward_list = [];
    $sql_reward = "
        SELECT jr.nama_reward, jr.poin_reward AS poin, COUNT(*) as jumlah
        FROM daftar_reward rwd
        JOIN jenis_reward jr ON rwd.jenis_reward_id = jr.id
        WHERE rwd.santri_id = ? 
          AND MONTH(rwd.tanggal) = FIND_IN_SET(?, 'Januari,Februari,Maret,April,Mei,Juni,Juli,Agustus,September,Oktober,November,Desember')
          AND YEAR(rwd.tanggal) = ?
          AND jr.poin_reward > 0
        GROUP BY jr.id
        ORDER BY poin DESC
    ";
    $stmt_reward = $conn->prepare($sql_reward);
    $stmt_reward->bind_param("isi", $rapot['santri_id'], $rapot['bulan'], $rapot['tahun']);
    $stmt_reward->execute();
    $reward_list = $stmt_reward->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_reward->close();

} catch (Exception $e) {
    die('Error querying database: ' . $e->getMessage());
}

$can_cetak = has_permission('rapot_cetak');

// Struktur Mutu & Sub Mutu berdasarkan template PDF lama
$nilai_aspek = [
    [
        'aspek' => 'Ibadah',
        'sub_mutu' => [
            ['nama' => 'Puasa Sunnah', 'field' => 'puasa_sunnah', 'nilai_final' => $rapot['puasa_sunnah']],
            ['nama' => 'Sholat Duha', 'field' => 'sholat_duha', 'nilai_final' => $rapot['sholat_duha']],
            ['nama' => 'Sholat Malam', 'field' => 'sholat_malam', 'nilai_final' => $rapot['sholat_malam']],
            ['nama' => 'Sedekah & Berbagi', 'field' => 'sedekah', 'nilai_final' => $rapot['sedekah']],
            ['nama' => 'Sunnah sebelum tidur', 'field' => 'sunnah_tidur', 'nilai_final' => $rapot['sunnah_tidur']],
            ['nama' => 'Ibadah lainnya', 'field' => 'ibadah_lainnya', 'nilai_final' => $rapot['ibadah_lainnya']],
        ]
    ],
    [
        'aspek' => 'Akhlaq',
        'sub_mutu' => [
            ['nama' => 'Lisan', 'field' => 'lisan', 'nilai_final' => $rapot['lisan']],
            ['nama' => 'Sikap & tingkah laku', 'field' => 'sikap', 'nilai_final' => $rapot['sikap']],
            ['nama' => 'Kesopanan', 'field' => 'kesopanan', 'nilai_final' => $rapot['kesopanan']],
            ['nama' => 'Muamalah', 'field' => 'muamalah', 'nilai_final' => $rapot['muamalah']],
        ]
    ],
    [
        'aspek' => 'Kedisiplinan',
        'sub_mutu' => [
            ['nama' => 'Tidur', 'field' => 'tidur', 'nilai_final' => $rapot['tidur']],
            ['nama' => 'Keterlambatan', 'field' => 'keterlambatan', 'nilai_final' => $rapot['keterlambatan']],
            ['nama' => 'Seragam', 'field' => 'seragam', 'nilai_final' => $rapot['seragam']],
            ['nama' => 'Makan', 'field' => 'makan', 'nilai_final' => $rapot['makan']],
            ['nama' => 'Arahan', 'field' => 'arahan', 'nilai_final' => $rapot['arahan']],
            ['nama' => 'Bahasa Arab', 'field' => 'bahasa_arab', 'nilai_final' => $rapot['bahasa_arab']],
        ]
    ],
    [
        'aspek' => 'Kerapihan',
        'sub_mutu' => [
            ['nama' => 'Mandi', 'field' => 'mandi', 'nilai_final' => $rapot['mandi']],
            ['nama' => 'Penampilan', 'field' => 'penampilan', 'nilai_final' => $rapot['penampilan']],
            ['nama' => 'Piket', 'field' => 'piket', 'nilai_final' => $rapot['piket']],
            ['nama' => 'Kerapihan Barang', 'field' => 'kerapihan_barang', 'nilai_final' => $rapot['kerapihan_barang']],
        ]
    ]
];

$total_nilai = 0;
foreach ($nilai_aspek as $aspek) {
    foreach ($aspek['sub_mutu'] as $sub) {
        $total_nilai += (float)$sub['nilai_final'];
    }
}

$total_pelanggaran = (int)$rapot['total_poin_pelanggaran_saat_itu'];
$total_reward = (int)$rapot['total_poin_reward_saat_itu'];
$catatan_musyrif = trim($rapot['catatan_musyrif'] ?? '');

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

    /* ─── Tabel nilai ─── */
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

    /* Poin Bar */
    .poin-bar {
        display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem; margin-bottom: 1.5rem;
    }
    .poin-box {
        padding: 1.25rem; border-radius: .875rem; text-align: center;
        border: 1px solid rgba(0,0,0,.05);
    }
    .poin-box.merah { background: #fef2f2; border-color: #fee2e2; }
    .poin-box.hijau { background: #f0fdf4; border-color: #dcfce7; }
    .poin-lbl { font-size: .75rem; font-weight: 700; text-transform: uppercase; margin-bottom: .25rem; }
    .poin-box.merah .poin-lbl { color: #991b1b; }
    .poin-box.hijau .poin-lbl { color: #166534; }
    .poin-val { font-size: 2rem; font-weight: 800; line-height: 1; margin-bottom: .25rem; }
    .poin-box.merah .poin-val { color: #dc2626; }
    .poin-box.hijau .poin-val { color: #16a34a; }
    .poin-sub { font-size: .7rem; color: #64748b; font-weight: 500; }

    /* Catatan Box */
    .narasi-box {
        background: #f8fafc; border: 1px solid var(--c-border);
        border-radius: .75rem; padding: 1.25rem;
        font-size: .875rem; line-height: 1.6; color: var(--c-text);
        position: relative; white-space: pre-wrap;
    }
</style>

<div class="container-fluid pb-5">
    <div class="detail-wrap">
        
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb small">
                <li class="breadcrumb-item"><a href="../index.php" class="text-decoration-none">Rapor Bulanan</a></li>
                <li class="breadcrumb-item"><a href="../index.php?kamar=<?= urlencode($rapot['kamar_santri']) ?>" class="text-decoration-none">Kamar <?= htmlspecialchars($rapot['kamar_santri']) ?></a></li>
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
                <div class="info-val"><?= htmlspecialchars($rapot['kamar_santri'] ?? '—') ?></div>
            </div>
            <div class="info-item">
                <div class="info-lbl">Kelas</div>
                <div class="info-val"><?= htmlspecialchars($rapot['kelas_santri'] ?? '—') ?></div>
            </div>
            <div class="info-item">
                <div class="info-lbl">Bulan</div>
                <div class="info-val"><?= htmlspecialchars($rapot['bulan']) . ' ' . $rapot['tahun'] ?></div>
            </div>
            <div class="info-item">
                <div class="info-lbl">Musyrif</div>
                <div class="info-val"><?= htmlspecialchars($rapot['nama_musyrif'] ?? '—') ?></div>
            </div>
        </div>

        <!-- Tabel Nilai -->
        <div class="sec-card">
            <div class="sec-hdr">
                <div class="sec-icon" style="background:var(--c-primary-light);color:var(--c-primary);"><i class="fas fa-table"></i></div>
                Rekap Nilai Bulanan
            </div>
            <div style="overflow-x:auto;">
                <table class="tbl-nilai">
                    <thead>
                        <tr>
                            <th style="width:15%;">Mutu</th>
                            <th style="width:25%;">Sub Mutu</th>
                            <th style="width:50%;">Penjelasan</th>
                            <th style="width:10%;">Nilai</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($nilai_aspek as $aspek):
                            $subs       = $aspek['sub_mutu'] ?? [];
                            $row_count  = count($subs);
                        ?>
                        <?php foreach ($subs as $i => $sub):
                            $nf  = (float)($sub['nilai_final'] ?? 0);
                            $nfr = (int)round($nf);
                            $sc  = max(0, min(5, $nfr));
                            $field_key = $sub['field'];
                            $ket = getDeskripsiPenilaian($field_key, $nfr);
                        ?>
                        <tr>
                            <?php if ($i === 0): ?>
                            <td rowspan="<?= $row_count ?>" class="td-mutu">
                                <?= htmlspecialchars($aspek['aspek']) ?>
                            </td>
                            <?php endif; ?>
                            <td class="td-sub"><?= htmlspecialchars($sub['nama']) ?></td>
                            <td class="td-penjelasan"><?= htmlspecialchars($ket) ?></td>
                            <td class="td-nilai">
                                <span class="sc sc-<?= $sc ?>"><?= $nfr ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endforeach; ?>

                        <!-- Baris Jumlah -->
                        <tr>
                            <td colspan="3" class="td-jumlah">Jumlah</td>
                            <td class="td-jumlah-val"><?= $total_nilai ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Poin Pelanggaran & Reward -->
            <div class="sec-body pt-0 mt-4">
                <div class="poin-bar">
                    <div class="poin-box merah">
                        <div class="poin-lbl"><i class="fas fa-exclamation-triangle me-1"></i>Poin Pelanggaran</div>
                        <div class="poin-val"><?= $total_pelanggaran > 0 ? $total_pelanggaran : '–' ?></div>
                        <div class="poin-sub">Akumulasi di bulan ini</div>
                    </div>
                    <div class="poin-box hijau">
                        <div class="poin-lbl"><i class="fas fa-star me-1"></i>Poin Reward</div>
                        <div class="poin-val"><?= $total_reward > 0 ? $total_reward : '–' ?></div>
                        <div class="poin-sub">Akumulasi di bulan ini</div>
                    </div>
                </div>
                
                <div class="row mt-1 mb-2">
                    <div class="col-md-6 mb-3 mb-md-0">
                        <div style="font-size:.75rem; font-weight:700; color:#b91c1c; text-transform:uppercase; margin-bottom:.5rem; letter-spacing:.03em;"><i class="fas fa-list me-1"></i> Rincian Pelanggaran</div>
                        <?php if (empty($pelanggaran_list)): ?>
                            <div class="text-muted" style="font-size:.8rem; font-style:italic;">Tidak ada pelanggaran yang dicatat bulan ini.</div>
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
                        <div style="font-size:.75rem; font-weight:700; color:#166534; text-transform:uppercase; margin-bottom:.5rem; letter-spacing:.03em;"><i class="fas fa-award me-1"></i> Rincian Reward</div>
                        <?php if (empty($reward_list)): ?>
                            <div class="text-muted" style="font-size:.8rem; font-style:italic;">Tidak ada reward yang dicatat bulan ini.</div>
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
                                    <span class="badge rounded-pill" style="background:#dcfce7; color:#166534; font-size:.75rem;">+<?= $r['poin'] ?> poin</span>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Catatan Musyrif -->
        <div class="sec-card">
            <div class="sec-hdr">
                <div class="sec-icon" style="background:var(--c-success-light);color:var(--c-success);"><i class="fas fa-comment-alt"></i></div>
                Catatan Musyrif
            </div>
            <div class="sec-body">
                <div class="narasi-box">
                    <?= $catatan_musyrif !== '' ? htmlspecialchars($catatan_musyrif) : '<em>Tidak ada catatan khusus.</em>' ?>
                </div>
            </div>
        </div>

        <!-- Bottom Actions -->
        <div class="d-flex flex-wrap gap-2 mt-4">
            <a href="../index.php" class="btn btn-light border px-4 rounded-pill fw-medium text-secondary shadow-sm">
                <i class="fas fa-arrow-left me-2"></i>Kembali
            </a>
            <?php if ($can_cetak): ?>
            <a href="../export/generate_pdf.php?id=<?= $rapot['id'] ?>" target="_blank" class="btn btn-primary px-4 rounded-pill fw-bold shadow-sm ms-auto">
                <i class="fas fa-print me-2"></i>Cetak PDF
            </a>
            <?php endif; ?>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
