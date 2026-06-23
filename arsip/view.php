<?php
require_once __DIR__ . '/../bootstrap/init.php';
guard('arsip_view');
require_once __DIR__ . '/../layouts/header.php';

$arsip_id = (int)($_GET['id'] ?? 0);
if ($arsip_id < 1) die('Arsip tidak ditemukan');

// Ambil meta arsip
$stmt_meta = $conn->prepare("SELECT * FROM arsip WHERE id = ?");
$stmt_meta->bind_param('i', $arsip_id);
$stmt_meta->execute();
$meta = $stmt_meta->get_result()->fetch_assoc();
if (!$meta) die('Arsip tidak ditemukan');

// Ambil ringkasan statistik cepat (query ringan, hanya COUNT)
$stmt_s1 = $conn->prepare("SELECT COUNT(*) as c FROM arsip_data_santri WHERE arsip_id = ?");
$stmt_s1->bind_param('i', $arsip_id); $stmt_s1->execute();
$total_santri = $stmt_s1->get_result()->fetch_assoc()['c'];

$stmt_s2 = $conn->prepare("SELECT COUNT(*) as c, COALESCE(SUM(poin),0) as poin FROM arsip_data_pelanggaran WHERE arsip_id = ? AND tipe = 'Umum'");
$stmt_s2->bind_param('i', $arsip_id); $stmt_s2->execute();
$row_pel = $stmt_s2->get_result()->fetch_assoc();
$total_pelanggaran = $row_pel['c']; $total_poin_pel = $row_pel['poin'];

$stmt_s3 = $conn->prepare("SELECT COUNT(*) as c FROM arsip_data_pelanggaran_kebersihan WHERE arsip_id = ?");
$stmt_s3->bind_param('i', $arsip_id); $stmt_s3->execute();
$total_kebersihan = $stmt_s3->get_result()->fetch_assoc()['c'];

$stmt_s4 = $conn->prepare("SELECT COUNT(*) as c, COALESCE(SUM(poin_reward),0) as poin FROM arsip_data_reward WHERE arsip_id = ?");
$stmt_s4->bind_param('i', $arsip_id); $stmt_s4->execute();
$row_rew = $stmt_s4->get_result()->fetch_assoc();
$total_reward = $row_rew['c']; $total_poin_reward = $row_rew['poin'];

$stmt_s5 = $conn->prepare("SELECT COUNT(*) as c FROM arsip_data_rapot WHERE arsip_id = ?");
$stmt_s5->bind_param('i', $arsip_id); $stmt_s5->execute();
$total_rapot = $stmt_s5->get_result()->fetch_assoc()['c'];

$stmt_s6 = $conn->prepare("SELECT COUNT(*) as c FROM arsip_data_rapot_tahunan WHERE arsip_id = ?");
$stmt_s6->bind_param('i', $arsip_id); $stmt_s6->execute();
$total_rapot_tahunan = $stmt_s6->get_result()->fetch_assoc()['c'];
?>

<style>
    :root {
        --primary: #4f46e5;
        --primary-light: #eef2ff;
        --bg: #f8fafc;
        --card-bg: #ffffff;
        --border: #e2e8f0;
        --text-dark: #0f172a;
        --text-muted: #64748b;
    }

    .arsip-view-page {
        background: var(--bg);
        min-height: 100vh;
        padding: 1.5rem;
    }

    /* ── Header ── */
    .arsip-header {
        background: var(--card-bg);
        border: 1px solid var(--border);
        border-radius: 1.25rem;
        padding: 1.5rem 2rem;
        margin-bottom: 1.75rem;
        box-shadow: 0 1px 3px rgba(0,0,0,.04);
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
    }
    .arsip-header-title { font-size: 1.5rem; font-weight: 700; color: var(--text-dark); display: flex; align-items: center; gap: .6rem; margin: 0; }
    .arsip-header-title i { color: var(--primary); }
    .arsip-header-meta { color: var(--text-muted); font-size: .9rem; margin: .25rem 0 0 0; }
    .arsip-header-meta strong { color: var(--text-dark); }

    /* ── Stat chips ── */
    .stat-chips { display: flex; flex-wrap: wrap; gap: .5rem; margin-bottom: 1.75rem; }
    .stat-chip {
        background: var(--card-bg);
        border: 1px solid var(--border);
        border-radius: 999px;
        padding: .45rem 1rem;
        font-size: .82rem;
        font-weight: 600;
        color: var(--text-muted);
        display: flex;
        align-items: center;
        gap: .4rem;
        box-shadow: 0 1px 2px rgba(0,0,0,.04);
    }
    .stat-chip .chip-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
    .stat-chip .chip-num { color: var(--text-dark); font-size: .9rem; }

    /* ── Section title ── */
    .section-label {
        font-size: .7rem;
        font-weight: 700;
        letter-spacing: 1.2px;
        text-transform: uppercase;
        color: var(--text-muted);
        margin-bottom: .75rem;
        padding-left: .25rem;
    }

    /* ── Card Grid ── */
    .menu-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 1rem;
        margin-bottom: 1.75rem;
    }

    .menu-card {
        background: var(--card-bg);
        border: 1px solid var(--border);
        border-radius: 1rem;
        padding: 1.25rem 1.5rem;
        display: flex;
        align-items: flex-start;
        gap: 1rem;
        text-decoration: none;
        color: inherit;
        transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
        position: relative;
        overflow: hidden;
    }
    .menu-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 25px -5px rgba(0,0,0,.1), 0 4px 10px -5px rgba(0,0,0,.06);
        border-color: transparent;
        color: inherit;
        text-decoration: none;
    }
    .menu-card::before {
        content: '';
        position: absolute;
        inset: 0;
        border-radius: 1rem;
        opacity: 0;
        transition: opacity .18s ease;
    }
    .menu-card:hover::before { opacity: 1; }

    .card-icon-wrap {
        width: 52px;
        height: 52px;
        border-radius: .875rem;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        flex-shrink: 0;
    }
    .card-body-text { flex: 1; min-width: 0; }
    .card-body-text h6 { font-size: 1rem; font-weight: 700; margin: 0 0 .25rem 0; color: var(--text-dark); }
    .card-body-text p { font-size: .83rem; color: var(--text-muted); margin: 0 0 .5rem 0; line-height: 1.4; }
    .card-badge {
        display: inline-flex;
        align-items: center;
        gap: .25rem;
        font-size: .75rem;
        font-weight: 700;
        padding: .25rem .65rem;
        border-radius: 999px;
    }
    .card-arrow {
        color: var(--text-muted);
        font-size: .9rem;
        flex-shrink: 0;
        align-self: center;
        opacity: 0;
        transform: translateX(-4px);
        transition: opacity .18s ease, transform .18s ease;
    }
    .menu-card:hover .card-arrow { opacity: 1; transform: translateX(0); }

    /* ── Back btn ── */
    .btn-back-arsip {
        display: inline-flex;
        align-items: center;
        gap: .5rem;
        padding: .55rem 1.2rem;
        background: #f1f5f9;
        border: 1px solid var(--border);
        border-radius: .75rem;
        font-weight: 600;
        font-size: .875rem;
        color: var(--text-dark);
        text-decoration: none;
        transition: background .15s;
        flex-shrink: 0;
    }
    .btn-back-arsip:hover { background: #e2e8f0; color: var(--text-dark); text-decoration: none; }

    @media (max-width: 576px) {
        .arsip-view-page { padding: 1rem; }
        .arsip-header { padding: 1.25rem; }
        .menu-grid { grid-template-columns: 1fr; }
    }
</style>

<div class="arsip-view-page">

    <!-- Header -->
    <div class="arsip-header">
        <div>
            <h1 class="arsip-header-title">
                <i class="fas fa-box-archive"></i>
                <?= htmlspecialchars($meta['judul']) ?>
            </h1>
            <p class="arsip-header-meta">
                Periode: <strong><?= date('d M Y', strtotime($meta['tanggal_mulai'])) ?></strong>
                &nbsp;—&nbsp;
                <strong><?= date('d M Y', strtotime($meta['tanggal_selesai'])) ?></strong>
                &nbsp;&nbsp;•&nbsp;&nbsp;
                Diarsipkan pada <?= date('d M Y', strtotime($meta['dibuat_pada'])) ?>
            </p>
        </div>
        <a href="index.php" class="btn-back-arsip">
            <i class="fas fa-arrow-left"></i> Kembali
        </a>
    </div>

    <!-- Stat Chips -->
    <div class="stat-chips">
        <div class="stat-chip">
            <span class="chip-dot" style="background:#6366f1;"></span>
            <span class="chip-num"><?= number_format($total_santri) ?></span> Santri
        </div>
        <div class="stat-chip">
            <span class="chip-dot" style="background:#ef4444;"></span>
            <span class="chip-num"><?= number_format($total_pelanggaran) ?></span> Pelanggaran Umum
        </div>
        <div class="stat-chip">
            <span class="chip-dot" style="background:#f59e0b;"></span>
            <span class="chip-num"><?= number_format($total_kebersihan) ?></span> Pelanggaran Kebersihan
        </div>
        <div class="stat-chip">
            <span class="chip-dot" style="background:#10b981;"></span>
            <span class="chip-num"><?= number_format($total_reward) ?></span> Data Reward
        </div>
        <div class="stat-chip">
            <span class="chip-dot" style="background:#3b82f6;"></span>
            <span class="chip-num"><?= number_format($total_rapot) ?></span> Rapot
        </div>
        <div class="stat-chip">
            <span class="chip-dot" style="background:#8b5cf6;"></span>
            <span class="chip-num"><?= number_format($total_rapot_tahunan) ?></span> Rapot Tahunan
        </div>
    </div>

    <!-- Menu Cards -->
    <p class="section-label">Pilih Data yang Ingin Dilihat</p>

    <div class="menu-grid">

        <!-- Per Santri -->
        <a href="pages/arsip_per_santri.php?id=<?= $arsip_id ?>" class="menu-card" style="border-left: 4px solid #6366f1;">
            <div class="card-icon-wrap" style="background:#eef2ff;">
                <i class="fas fa-users" style="color:#4f46e5;"></i>
            </div>
            <div class="card-body-text">
                <h6>Rekap Per Santri</h6>
                <p>Lihat Daftar Hitam & Peringkat Santri dari semua pelanggaran, reward, dan rapot</p>
                <span class="card-badge" style="background:#eef2ff;color:#4338ca;">
                    <i class="fas fa-users fa-xs"></i>
                    <?= number_format($total_santri) ?> santri
                </span>
            </div>
            <i class="fas fa-chevron-right card-arrow"></i>
        </a>



        <!-- Peringkat Kamar -->
        <a href="pages/arsip_kamar.php?id=<?= $arsip_id ?>" class="menu-card" style="border-left: 4px solid #ec4899;">
            <div class="card-icon-wrap" style="background:#fdf2f8;">
                <i class="fas fa-bed" style="color:#db2777;"></i>
            </div>
            <div class="card-body-text">
                <h6>Peringkat Kamar</h6>
                <p>Analisis dan peringkat performa karakter asrama berdasarkan poin arsip</p>
            </div>
            <i class="fas fa-chevron-right card-arrow"></i>
        </a>

        <!-- Kebersihan Kamar -->
        <a href="pages/arsip_kebersihan.php?id=<?= $arsip_id ?>" class="menu-card" style="border-left: 4px solid #f59e0b;">
            <div class="card-icon-wrap" style="background:#fffbeb;">
                <i class="fas fa-broom" style="color:#d97706;"></i>
            </div>
            <div class="card-body-text">
                <h6>Kebersihan Kamar</h6>
                <p>Rekap dan peringkat pelanggaran kebersihan per kamar asrama</p>
                <span class="card-badge" style="background:#fffbeb;color:#92400e;">
                    <i class="fas fa-door-open fa-xs"></i>
                    <?= number_format($total_kebersihan) ?> insiden
                </span>
            </div>
            <i class="fas fa-chevron-right card-arrow"></i>
        </a>

        <!-- Grafik Statistik -->
        <a href="pages/arsip_chart.php?id=<?= $arsip_id ?>" class="menu-card" style="border-left: 4px solid #10b981;">
            <div class="card-icon-wrap" style="background:#ecfdf5;">
                <i class="fas fa-chart-pie" style="color:#059669;"></i>
            </div>
            <div class="card-body-text">
                <h6>Grafik Statistik</h6>
                <p>Visualisasi dan analisis komprehensif dari data arsip periode ini</p>
            </div>
            <i class="fas fa-chevron-right card-arrow"></i>
        </a>

        <!-- Rapot Tahunan -->
        <a href="pages/arsip_rapot_tahunan.php?id=<?= $arsip_id ?>" class="menu-card" style="border-left: 4px solid #8b5cf6;">
            <div class="card-icon-wrap" style="background:#f3e8ff;">
                <i class="fas fa-file-invoice" style="color:#7c3aed;"></i>
            </div>
            <div class="card-body-text">
                <h6>Rapot Tahunan</h6>
                <p>Data rapor tahunan santri yang telah diarsipkan pada periode ini</p>
                <span class="card-badge" style="background:#f3e8ff;color:#5b21b6;">
                    <i class="fas fa-file-invoice fa-xs"></i>
                    <?= number_format($total_rapot_tahunan) ?> data
                </span>
            </div>
            <i class="fas fa-chevron-right card-arrow"></i>
        </a>

    </div>

</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>