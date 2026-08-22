<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../bootstrap/init.php';

// Satpam: Hak akses khusus rekonsiliasi poin
guard('rekonsiliasi_poin_manage');

// --- PROSES EKSEKUSI REKONSILIASI (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'sync_all') {
    csrf_validate();

    mysqli_begin_transaction($conn);
    try {
        // 1. Ambil data santri yang mengalami selisih sebelum diupdate untuk catatan log
        $sql_cek = "
            SELECT 
                s.id, s.nama, s.poin_aktif AS poin_lama,
                (COALESCE(p.total_pelanggaran, 0) - COALESCE(r.total_reward, 0)) AS poin_baru
            FROM santri s
            LEFT JOIN (
                SELECT p.santri_id, SUM(jp.poin) AS total_pelanggaran
                FROM pelanggaran p
                JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id
                GROUP BY p.santri_id
            ) p ON s.id = p.santri_id
            LEFT JOIN (
                SELECT dr.santri_id, SUM(jr.poin_reward) AS total_reward
                FROM daftar_reward dr
                JOIN jenis_reward jr ON dr.jenis_reward_id = jr.id
                GROUP BY dr.santri_id
            ) r ON s.id = r.santri_id
            HAVING s.poin_aktif != poin_baru
        ";
        $res_cek = $conn->query($sql_cek);
        $perubahan_list = [];
        while ($row = $res_cek->fetch_assoc()) {
            $perubahan_list[] = $row;
        }

        // 2. Eksekusi Rekonsiliasi Massal Seluruh Santri
        $sql_reconcile = "
            UPDATE santri s
            SET s.poin_aktif = 
                COALESCE((
                    SELECT SUM(jp.poin)
                    FROM pelanggaran p
                    JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id
                    WHERE p.santri_id = s.id
                ), 0)
                -
                COALESCE((
                    SELECT SUM(jr.poin_reward)
                    FROM daftar_reward dr
                    JOIN jenis_reward jr ON dr.jenis_reward_id = jr.id
                    WHERE dr.santri_id = s.id
                ), 0)
        ";
        $conn->query($sql_reconcile);

        mysqli_commit($conn);

        $total_disesuaikan = count($perubahan_list);

        write_activity_log('REKONSILIASI_POIN', 'pengaturan', "Melakukan Rekonsiliasi & Sinkronisasi Saldo Poin Massal ($total_disesuaikan santri disesuaikan)", [
            'total_disesuaikan' => $total_disesuaikan,
            'detail_perubahan'  => array_slice($perubahan_list, 0, 50)
        ]);

        if ($total_disesuaikan > 0) {
            $_SESSION['message'] = [
                'type' => 'success',
                'text' => "<b>Rekonsiliasi Selesai!</b> Saldo poin sebanyak <b>{$total_disesuaikan} santri</b> berhasil disesuaikan dan sekarang 100% sinkron dengan riwayat transaksinya."
            ];
        } else {
            $_SESSION['message'] = [
                'type' => 'info',
                'text' => "<b>Pemeriksaan Selesai!</b> Seluruh saldo poin santri sudah dalam status 100% sinkron dan akurat."
            ];
        }

    } catch (Exception $e) {
        mysqli_rollback($conn);
        // Log error internal, jangan expose detail teknis ke user
        write_activity_log('ERROR', 'pengaturan', 'Gagal rekonsiliasi poin: ' . $e->getMessage());
        $_SESSION['message'] = [
            'type' => 'danger',
            'text' => '<b>Rekonsiliasi Gagal!</b> Terjadi kesalahan sistem. Silakan coba lagi atau hubungi administrator.'
        ];
    }

    header("Location: index.php");
    exit();
}

$csrf_token = csrf_generate();

// --- DIAGNOSTIK: CEK STATUS SINKRONISASI SAAT INI ---
// 1. Total Santri
$total_santri = (int)($conn->query("SELECT COUNT(*) AS c FROM santri")->fetch_assoc()['c'] ?? 0);

// 2. Total Pelanggaran & Reward Aktif
$total_pelanggaran_row = $conn->query("
    SELECT COUNT(p.id) AS jml, COALESCE(SUM(jp.poin), 0) AS tot_poin 
    FROM pelanggaran p 
    JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id
")->fetch_assoc();
$total_pelanggaran_kasus = (int)($total_pelanggaran_row['jml'] ?? 0);
$total_pelanggaran_poin  = (int)($total_pelanggaran_row['tot_poin'] ?? 0);

$total_reward_row = $conn->query("
    SELECT COUNT(dr.id) AS jml, COALESCE(SUM(jr.poin_reward), 0) AS tot_poin 
    FROM daftar_reward dr 
    JOIN jenis_reward jr ON dr.jenis_reward_id = jr.id
")->fetch_assoc();
$total_reward_kasus = (int)($total_reward_row['jml'] ?? 0);
$total_reward_poin  = (int)($total_reward_row['tot_poin'] ?? 0);

// 3. Breakdown Rincian Pelanggaran per Jenis
$res_breakdown_pel = $conn->query("
    SELECT 
        jp.nama_pelanggaran, jp.poin, jp.kategori, jp.bagian,
        COUNT(p.id) AS jml_kasus,
        COUNT(DISTINCT p.santri_id) AS jml_santri,
        SUM(jp.poin) AS total_poin
    FROM pelanggaran p
    JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id
    GROUP BY jp.id
    ORDER BY total_poin DESC, jml_kasus DESC
");
$breakdown_pelanggaran = [];
while ($row = $res_breakdown_pel->fetch_assoc()) {
    $breakdown_pelanggaran[] = $row;
}

// 4. Breakdown Rincian Reward per Jenis
$res_breakdown_rew = $conn->query("
    SELECT 
        jr.nama_reward, jr.poin_reward,
        COUNT(dr.id) AS jml_kasus,
        COUNT(DISTINCT dr.santri_id) AS jml_santri,
        SUM(jr.poin_reward) AS total_poin
    FROM daftar_reward dr
    JOIN jenis_reward jr ON dr.jenis_reward_id = jr.id
    GROUP BY jr.id
    ORDER BY total_poin DESC, jml_kasus DESC
");
$breakdown_reward = [];
while ($row = $res_breakdown_rew->fetch_assoc()) {
    $breakdown_reward[] = $row;
}

// 5. Deteksi Santri yang Mengalami Selisih Saldo
$sql_anomali = "
    SELECT 
        s.id, s.nis, s.nama, s.kelas, s.kamar, s.poin_aktif AS poin_tersimpan,
        COALESCE(p.total_pelanggaran, 0) AS total_pelanggaran,
        COALESCE(r.total_reward, 0) AS total_reward,
        (COALESCE(p.total_pelanggaran, 0) - COALESCE(r.total_reward, 0)) AS poin_seharusnya,
        ((COALESCE(p.total_pelanggaran, 0) - COALESCE(r.total_reward, 0)) - s.poin_aktif) AS selisih
    FROM santri s
    LEFT JOIN (
        SELECT p.santri_id, SUM(jp.poin) AS total_pelanggaran
        FROM pelanggaran p
        JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id
        GROUP BY p.santri_id
    ) p ON s.id = p.santri_id
    LEFT JOIN (
        SELECT dr.santri_id, SUM(jr.poin_reward) AS total_reward
        FROM daftar_reward dr
        JOIN jenis_reward jr ON dr.jenis_reward_id = jr.id
        GROUP BY dr.santri_id
    ) r ON s.id = r.santri_id
    HAVING selisih != 0
    ORDER BY ABS(selisih) DESC, s.nama ASC
";
$res_anomali = $conn->query($sql_anomali);
$anomali_list = [];
if ($res_anomali) {
    while ($row = $res_anomali->fetch_assoc()) {
        $anomali_list[] = $row;
    }
}
$total_anomali = count($anomali_list);

require_once __DIR__ . '/../../layouts/header.php';
?>

<style>
    .page-reconcile-wrap {
        padding-bottom: 95px;
    }
    .reconcile-card {
        border-radius: 1.25rem;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        background: #ffffff;
    }
    .stat-badge-box {
        border-radius: 1rem;
        padding: 1.15rem;
        border: 1px solid #e2e8f0;
        background: #ffffff;
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }
    .stat-clickable {
        cursor: pointer;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
    }
    .stat-clickable:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 16px -4px rgba(0, 0, 0, 0.08);
        border-color: #cbd5e1;
    }
    .stat-clickable:active {
        transform: translateY(0);
    }
    .stat-badge-box .stat-val {
        font-size: 1.45rem;
        font-weight: 700;
        line-height: 1.2;
    }
    .stat-badge-box .stat-sub {
        font-size: 0.75rem;
        color: #64748b;
        margin-top: 4px;
    }
    .badge-diff-plus {
        background: #fef2f2;
        color: #dc2626;
        border: 1px solid #fecaca;
    }
    .badge-diff-minus {
        background: #f0fdf4;
        color: #16a34a;
        border: 1px solid #bbf7d0;
    }
    .btn-kembali {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 14px;
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 9999px;
        color: #475569;
        font-weight: 500;
        font-size: 12px;
        transition: all 0.2s ease;
        text-decoration: none;
    }
    .btn-kembali:hover {
        background-color: #f1f5f9;
        border-color: #cbd5e1;
        color: #0f172a;
    }

    @media (max-width: 576px) {
        .reconcile-header-title {
            font-size: 1.25rem !important;
        }
        .reconcile-header-sub {
            font-size: 0.8rem !important;
        }
        .stat-badge-box {
            padding: 0.85rem 0.95rem;
            border-radius: 0.875rem;
        }
        .stat-badge-box .stat-val {
            font-size: 1.2rem;
        }
        .stat-badge-box .stat-sub {
            font-size: 0.68rem;
        }
        .reconcile-card {
            padding: 1.5rem 1.25rem !important;
            border-radius: 1rem;
        }
        .status-circle-icon {
            width: 56px !important;
            height: 56px !important;
            font-size: 1.35rem !important;
        }
        .reconcile-action-btn {
            width: 100% !important;
            padding: 0.75rem 1rem !important;
            font-size: 0.9rem !important;
        }
    }
</style>

<div class="page-container page-reconcile-wrap">
    
    <!-- Top Header -->
    <div class="d-flex flex-row justify-content-between align-items-center gap-2 mb-3">
        <div class="d-flex align-items-center gap-2">
            <div class="d-inline-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle" style="width: 38px; height: 38px; flex-shrink: 0;">
                <i class="fas fa-arrows-rotate"></i>
            </div>
            <div>
                <h2 class="fw-bold text-dark mb-0 reconcile-header-title" style="font-size: 1.45rem;">
                    Rekonsiliasi Saldo Poin
                </h2>
                <p class="text-muted small mb-0 reconcile-header-sub d-none d-sm-block">Audit integritas data dan sinkronkan saldo poin santri dengan transaksi riil secara universal.</p>
            </div>
        </div>
        <div>
            <a href="../index.php" class="btn-kembali">
                <i class="fas fa-arrow-left"></i> <span class="d-none d-sm-inline">Kembali ke</span> Pengaturan
            </a>
        </div>
    </div>
    <p class="text-muted small mb-3 d-block d-sm-none" style="font-size: 0.78rem;">Audit integritas data dan sinkronkan saldo poin santri dengan total transaksi riil secara universal.</p>

    <!-- Stat Grid -->
    <div class="row g-2 g-sm-3 mb-3 mb-sm-4">
        <div class="col-6 col-md-3">
            <div class="stat-badge-box">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="text-muted small fw-medium" style="font-size: 0.75rem;">Total Santri</span>
                    <i class="fas fa-users text-primary opacity-75 small"></i>
                </div>
                <div>
                    <div class="stat-val text-dark"><?= number_format($total_santri) ?></div>
                    <div class="stat-sub">Santri aktif</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-badge-box stat-clickable" onclick="showPelanggaranBreakdown()" title="Klik untuk melihat rincian sumber poin pelanggaran">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="text-muted small fw-medium" style="font-size: 0.75rem;">Poin Pelanggaran</span>
                    <i class="fas fa-triangle-exclamation text-danger opacity-75 small"></i>
                </div>
                <div>
                    <div class="stat-val text-danger"><?= number_format($total_pelanggaran_poin) ?></div>
                    <div class="stat-sub"><?= number_format($total_pelanggaran_kasus) ?> kasus santri</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-badge-box stat-clickable" onclick="showRewardBreakdown()" title="Klik untuk melihat rincian sumber poin reward">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="text-muted small fw-medium" style="font-size: 0.75rem;">Poin Reward</span>
                    <i class="fas fa-star text-success opacity-75 small"></i>
                </div>
                <div>
                    <div class="stat-val text-success"><?= number_format($total_reward_poin) ?></div>
                    <div class="stat-sub"><?= number_format($total_reward_kasus) ?> reward santri</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-badge-box" style="border-left: 4px solid <?= $total_anomali > 0 ? '#f59e0b' : '#10b981' ?>;">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="text-muted small fw-medium" style="font-size: 0.75rem;">Status Saldo</span>
                    <i class="fas <?= $total_anomali > 0 ? 'fa-exclamation-triangle text-warning' : 'fa-check-circle text-success' ?> small"></i>
                </div>
                <div>
                    <div class="stat-val <?= $total_anomali > 0 ? 'text-warning-emphasis' : 'text-success' ?>" style="white-space: nowrap; font-size: <?= $total_anomali > 0 ? '1.05rem' : '1.25rem' ?>;">
                        <?= $total_anomali > 0 ? $total_anomali . ' Perlu Disinkron' : '100% Sinkron' ?>
                    </div>
                    <div class="stat-sub"><?= $total_anomali > 0 ? 'Ditemukan selisih' : 'Semua saldo akurat' ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Status Banner & Action Card -->
    <div class="reconcile-card p-4 p-md-5 mb-4">
        <?php if ($total_anomali === 0): ?>
            <div class="text-center py-3 py-md-4">
                <div class="status-circle-icon d-inline-flex align-items-center justify-content-center bg-success-subtle text-success rounded-circle mb-3" style="width: 68px; height: 68px; font-size: 2rem;">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h4 class="fw-bold text-dark mb-2 fs-5 fs-md-4">Saldo Poin Santri 100% Sinkron & Akurat!</h4>
                <p class="text-muted mx-auto mb-4 small" style="max-width: 560px; line-height: 1.5;">
                    Tidak ditemukan selisih antara saldo <code>poin_aktif</code> yang tersimpan di profil santri dengan rincian transaksi riil di tabel pelanggaran dan reward.
                </p>
                <form method="POST" id="formReconcile" onsubmit="return confirmSync(event, 0);">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="action" value="sync_all">
                    <button type="submit" class="btn btn-outline-secondary rounded-pill px-4 py-2 fw-semibold reconcile-action-btn shadow-sm">
                        <i class="fas fa-arrows-rotate me-1"></i> Jalankan Ulang Rekonsiliasi
                    </button>
                </form>
            </div>
        <?php else: ?>
            <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3 p-3 mb-4 rounded-3" style="background: #fffbeb; border: 1px solid #fde68a;">
                <div class="d-flex align-items-start align-items-sm-center gap-3">
                    <i class="fas fa-triangle-exclamation fa-2x text-warning flex-shrink-0 mt-1 mt-sm-0"></i>
                    <div>
                        <h6 class="fw-bold text-warning-emphasis mb-1">Ditemukan <?= $total_anomali ?> Santri Memiliki Selisih Saldo</h6>
                        <small class="text-muted d-block" style="line-height: 1.4;">Hal ini biasanya terjadi setelah perubahan nilai master poin atau impor Excel. Rekonsiliasi akan menyamakan saldo dengan rumus: <code>Saldo = Total Pelanggaran - Total Reward</code>.</small>
                    </div>
                </div>
                <div class="w-100 w-md-auto text-end flex-shrink-0">
                    <form method="POST" id="formReconcile" onsubmit="return confirmSync(event, <?= $total_anomali ?>);">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <input type="hidden" name="action" value="sync_all">
                        <button type="submit" class="btn btn-primary rounded-pill px-4 py-2 fw-semibold reconcile-action-btn shadow-sm">
                            <i class="fas fa-arrows-rotate me-1"></i> Sinkronkan Semua Saldo Sekarang
                        </button>
                    </form>
                </div>
            </div>

            <!-- Header List -->
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h5 class="fw-bold text-dark mb-0 fs-6 fs-md-5">Santri yang Memerlukan Penyesuaian:</h5>
                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill px-2.5 py-1">
                    <?= $total_anomali ?> santri
                </span>
            </div>

            <!-- Mode Desktop: Tabel -->
            <div class="d-none d-md-block table-responsive rounded-3 border">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center" style="width: 50px;">No</th>
                            <th>Nama Santri</th>
                            <th class="text-center">Kelas/Kamar</th>
                            <th class="text-center">Total Pelanggaran</th>
                            <th class="text-center">Total Reward</th>
                            <th class="text-center">Saldo Tersimpan</th>
                            <th class="text-center">Saldo Seharusnya</th>
                            <th class="text-center">Penyesuaian</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($anomali_list as $idx => $s): 
                            $selisih = $s['selisih'];
                        ?>
                            <tr>
                                <td class="text-center fw-bold text-muted"><?= $idx + 1 ?></td>
                                <td>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($s['nama']) ?></div>
                                    <small class="text-muted font-monospace">NIS: <?= htmlspecialchars($s['nis'] ?: '-') ?></small>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-light text-secondary border rounded-pill px-2.5 py-1">
                                        Kls <?= htmlspecialchars($s['kelas'] ?? '-') ?> &bull; Kmr <?= htmlspecialchars($s['kamar'] ?? '-') ?>
                                    </span>
                                </td>
                                <td class="text-center fw-bold text-danger"><?= $s['total_pelanggaran'] ?></td>
                                <td class="text-center fw-bold text-success"><?= $s['total_reward'] ?></td>
                                <td class="text-center fw-semibold text-secondary"><?= $s['poin_tersimpan'] ?></td>
                                <td class="text-center fw-bold fs-6 text-primary"><?= $s['poin_seharusnya'] ?></td>
                                <td class="text-center">
                                    <?php if ($selisih > 0): ?>
                                        <span class="badge badge-diff-plus rounded-pill px-2.5 py-1 font-monospace">
                                            +<?= $selisih ?> poin
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-diff-minus rounded-pill px-2.5 py-1 font-monospace">
                                            <?= $selisih ?> poin
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mode Mobile: Kartu Interaktif -->
            <div class="d-block d-md-none">
                <?php foreach ($anomali_list as $idx => $s): 
                    $selisih = $s['selisih'];
                ?>
                <div class="card border rounded-3 p-3 mb-2 bg-light">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div>
                            <div class="fw-bold text-dark"><?= htmlspecialchars($s['nama']) ?></div>
                            <small class="text-muted">Kls <?= htmlspecialchars($s['kelas'] ?? '-') ?> &bull; Kmr <?= htmlspecialchars($s['kamar'] ?? '-') ?></small>
                        </div>
                        <div>
                            <?php if ($selisih > 0): ?>
                                <span class="badge badge-diff-plus rounded-pill px-2.5 py-1 font-monospace" style="font-size: 0.75rem;">
                                    +<?= $selisih ?> poin
                                </span>
                            <?php else: ?>
                                <span class="badge badge-diff-minus rounded-pill px-2.5 py-1 font-monospace" style="font-size: 0.75rem;">
                                    <?= $selisih ?> poin
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="d-flex align-items-center justify-content-between pt-2 border-top small text-muted">
                        <div>Pelanggaran: <b class="text-danger"><?= $s['total_pelanggaran'] ?></b> | Reward: <b class="text-success"><?= $s['total_reward'] ?></b></div>
                        <div>Saldo: <span class="text-decoration-line-through text-secondary"><?= $s['poin_tersimpan'] ?></span> &rarr; <b class="text-primary"><?= $s['poin_seharusnya'] ?></b></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</div>

<!-- Data JSON untuk Pop-Up Rincian -->
<script>
const dataPelanggaranBreakdown = <?= json_encode($breakdown_pelanggaran) ?>;
const dataRewardBreakdown = <?= json_encode($breakdown_reward) ?>;

// Helper untuk mencegah XSS saat menyisipkan data ke innerHTML
function escHtml(str) {
    const d = document.createElement('div');
    d.appendChild(document.createTextNode(String(str ?? '')));
    return d.innerHTML;
}

function showPelanggaranBreakdown() {
    let listHtml = `<div class="rounded-3 p-2 bg-light text-start mb-3 border" style="max-height: 400px; overflow-y: auto;">`;
    
    if (dataPelanggaranBreakdown.length === 0) {
        listHtml += `<p class="text-muted text-center py-4 mb-0">Belum ada transaksi pelanggaran tercatat.</p>`;
    } else {
        dataPelanggaranBreakdown.forEach((item, idx) => {
            const totalPoin = Number(item.total_poin).toLocaleString();
            const poinPerKasus = Number(item.poin).toLocaleString();
            const jmlKasus = Number(item.jml_kasus).toLocaleString();
            const jmlSantri = Number(item.jml_santri).toLocaleString();
            const isSangatBerat = (item.kategori === 'Sangat Berat');
            const namaPel = escHtml(item.nama_pelanggaran);
            const kategoriPel = escHtml(item.kategori);

            listHtml += `
                <div class="p-3 mb-2 rounded-3 border bg-white shadow-xs" style="transition: all 0.15s ease;">
                    <div class="fw-bold text-dark mb-2" style="font-size: 0.9rem; line-height: 1.35;">
                        ${namaPel}
                    </div>
                    <div class="d-flex align-items-center justify-content-between pt-2 border-top text-muted small" style="font-size: 0.76rem;">
                        <div class="d-flex flex-wrap align-items-center gap-1.5">
                            <span class="fw-bold text-danger">${poinPerKasus} poin/kasus</span>
                            <span>&bull;</span>
                            <span>${jmlKasus} kasus (${jmlSantri} santri)</span>
                            <span class="badge ${isSangatBerat ? 'bg-danger-subtle text-danger border border-danger-subtle' : 'bg-light text-secondary border'} rounded-pill px-2 py-0.5" style="font-size: 0.68rem;">
                                ${kategoriPel}
                            </span>
                        </div>
                        <div class="text-end flex-shrink-0 ms-2">
                            <span class="badge ${isSangatBerat ? 'bg-danger' : 'bg-dark'} text-white rounded-pill px-2.5 py-1.5 fw-bold" style="font-size: 0.82rem;">
                                ${totalPoin} poin
                            </span>
                        </div>
                    </div>
                </div>
            `;
        });
    }
    listHtml += `</div>`;

    Swal.fire({
        html: `
            <div class="text-center mb-3">
                <div class="d-inline-flex align-items-center justify-content-center bg-danger-subtle text-danger rounded-circle mb-2" style="width: 48px; height: 48px; font-size: 1.3rem;">
                    <i class="fas fa-triangle-exclamation"></i>
                </div>
                <h4 class="fw-bold text-dark mb-1" style="font-size: 1.2rem;">Rincian Sumber Poin Pelanggaran</h4>
                <p class="text-muted small mb-0">Total Akumulasi: <strong class="text-danger fs-6"><?= number_format($total_pelanggaran_poin) ?> Poin</strong> dari <?= number_format($total_pelanggaran_kasus) ?> kasus santri</p>
            </div>
            ${listHtml}
        `,
        width: '94%',
        maxWidth: '620px',
        showConfirmButton: true,
        confirmButtonText: '<i class="fas fa-times me-1"></i> Tutup',
        confirmButtonColor: '#475569',
        customClass: {
            popup: 'rounded-4 p-3 p-sm-4 text-start',
            confirmButton: 'rounded-pill px-4 py-2 fw-semibold'
        }
    });
}

function showRewardBreakdown() {
    let listHtml = `<div class="rounded-3 p-2 bg-light text-start mb-3 border" style="max-height: 400px; overflow-y: auto;">`;
    
    if (dataRewardBreakdown.length === 0) {
        listHtml += `<p class="text-muted text-center py-4 mb-0">Belum ada transaksi reward tercatat.</p>`;
    } else {
        dataRewardBreakdown.forEach((item, idx) => {
            const totalPoin = Number(item.total_poin).toLocaleString();
            const poinPerReward = Number(item.poin_reward).toLocaleString();
            const jmlKasus = Number(item.jml_kasus).toLocaleString();
            const jmlSantri = Number(item.jml_santri).toLocaleString();
            const namaReward = escHtml(item.nama_reward);

            listHtml += `
                <div class="p-3 mb-2 rounded-3 border bg-white shadow-xs" style="transition: all 0.15s ease;">
                    <div class="fw-bold text-dark mb-2" style="font-size: 0.9rem; line-height: 1.35;">
                        ${namaReward}
                    </div>
                    <div class="d-flex align-items-center justify-content-between pt-2 border-top text-muted small" style="font-size: 0.76rem;">
                        <div class="d-flex flex-wrap align-items-center gap-1.5">
                            <span class="fw-bold text-success">+${poinPerReward} poin/reward</span>
                            <span>&bull;</span>
                            <span>${jmlKasus} apresiasi (${jmlSantri} santri)</span>
                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-0.5" style="font-size: 0.68rem;">
                                Reward Aktif
                            </span>
                        </div>
                        <div class="text-end flex-shrink-0 ms-2">
                            <span class="badge bg-success text-white rounded-pill px-2.5 py-1.5 fw-bold" style="font-size: 0.82rem;">
                                ${totalPoin} poin
                            </span>
                        </div>
                    </div>
                </div>
            `;
        });
    }
    listHtml += `</div>`;

    Swal.fire({
        html: `
            <div class="text-center mb-3">
                <div class="d-inline-flex align-items-center justify-content-center bg-success-subtle text-success rounded-circle mb-2" style="width: 48px; height: 48px; font-size: 1.3rem;">
                    <i class="fas fa-star"></i>
                </div>
                <h4 class="fw-bold text-dark mb-1" style="font-size: 1.2rem;">Rincian Sumber Poin Reward</h4>
                <p class="text-muted small mb-0">Total Akumulasi: <strong class="text-success fs-6"><?= number_format($total_reward_poin) ?> Poin</strong> dari <?= number_format($total_reward_kasus) ?> reward santri</p>
            </div>
            ${listHtml}
        `,
        width: '94%',
        maxWidth: '620px',
        showConfirmButton: true,
        confirmButtonText: '<i class="fas fa-times me-1"></i> Tutup',
        confirmButtonColor: '#475569',
        customClass: {
            popup: 'rounded-4 p-3 p-sm-4 text-start',
            confirmButton: 'rounded-pill px-4 py-2 fw-semibold'
        }
    });
}
</script>

<!-- SweetAlert2 Modal Confirmation Script -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function confirmSync(e, totalAnomali) {
    e.preventDefault();
    const form = document.getElementById('formReconcile');

    let titleText = 'Sinkronkan Saldo Poin?';
    let htmlText = totalAnomali > 0 
        ? `Sistem akan merekonsiliasi dan menyesuaikan saldo <b>${totalAnomali} santri</b> secara universal agar 100% cocok dengan akumulasi riwayat transaksinya.`
        : `Seluruh data saat ini sudah sinkron. Anda tetap dapat menjalankan rekonsiliasi diagnostik ulang untuk memastikan akurasi saldo.`;

    Swal.fire({
        title: titleText,
        html: htmlText,
        icon: 'question',
        showCancelButton: true,
        reverseButtons: true, /* <--- POSISI TOMBOL BATAL DI KIRI */
        confirmButtonColor: '#2563eb',
        cancelButtonColor: '#64748b',
        confirmButtonText: '<i class="fas fa-arrows-rotate me-1"></i> Ya, Sinkronkan Sekarang',
        cancelButtonText: 'Batal',
        customClass: {
            popup: 'rounded-4 p-4',
            confirmButton: 'rounded-pill px-4 py-2',
            cancelButton: 'rounded-pill px-4 py-2'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Sedang Menyinkronkan Data...',
                text: 'Mohon tunggu sebentar, sistem sedang merekonsiliasi seluruh saldo santri.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            form.submit();
        }
    });

    return false;
}
</script>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
