<?php
// arsip/pages/arsip_rapot_tahunan_list.php
require_once __DIR__ . '/../../bootstrap/init.php';

guard('arsip_view');

$arsip_id = (int)($_GET['id'] ?? 0);
$kamar    = trim($_GET['kamar']   ?? '');
$periode  = trim($_GET['periode'] ?? '');

if ($arsip_id <= 0 || empty($kamar) || empty($periode)) {
    die('Parameter tidak lengkap.');
}

$stmt_m = $conn->prepare("SELECT judul FROM arsip WHERE id = ?");
$stmt_m->bind_param('i', $arsip_id);
$stmt_m->execute();
$arsip = $stmt_m->get_result()->fetch_assoc();
if (!$arsip) die('Arsip tidak ditemukan.');

$page_title = "Daftar Rapor Tahunan — Arsip Kamar $kamar";

// Ambil semua rapor tahunan untuk kamar + periode di arsip
try {
    $stmt = $conn->prepare("
        SELECT s.santri_id, s.santri_nama AS nama_santri, s.santri_kelas AS kelas,
               rt.id as rapor_id, rt.status, rt.is_fallback, rt.generated_at, rt.approved_at,
               rt.approved_by_nama AS nama_approver,
               1 as data_bulanan
        FROM arsip_data_santri s
        LEFT JOIN arsip_data_rapot_tahunan rt ON s.santri_id = rt.santri_id AND rt.periode = ? AND rt.arsip_id = ?
        WHERE s.arsip_id = ? AND s.santri_kamar = ?
        ORDER BY s.santri_nama ASC
    ");
    $stmt->bind_param('siis', $periode, $arsip_id, $arsip_id, $kamar);
    $stmt->execute();
    $rapor_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    die('Gagal mengambil data: ' . $e->getMessage());
}

// Jika ada santri di arsip_data_rapot_tahunan yg belum tercakup di arsip_data_santri (walau jarang terjadi)
// Kita bisa gabungkan manual, tapi asumsikan arsip_data_santri itu lengkap.

// Hitung statistik
$total_santri_kamar = count($rapor_list);
$total    = count(array_filter($rapor_list, fn($r) => !empty($r['rapor_id'])));
$draft    = count(array_filter($rapor_list, fn($r) => $r['status'] === 'DRAFT'));
$approved = count(array_filter($rapor_list, fn($r) => in_array($r['status'], ['APPROVED', 'EXPORTED'])));
$fallback = count(array_filter($rapor_list, fn($r) => !empty($r['rapor_id']) && $r['is_fallback']));
$ai_ok    = $total - $fallback;

require_once __DIR__ . '/../../layouts/header.php';
?>

<style>
    /* ─── Warna tema — warm slate/teal ─── */
    :root {
        --c-primary:   #1d6fa4;   
        --c-primary-light: #e8f4fd;
        --c-success:   #1a7c4f;
        --c-success-light: #e6f4ee;
        --c-warning:   #b45309;
        --c-warning-light: #fef3c7;
        --c-danger:    #b91c1c;
        --c-danger-light:  #fee2e2;
        --c-avatar:    #1d4e7a;   
        --c-border:    #e2e8f0;
        --c-muted:     #64748b;
        --c-text:      #0f172a;
        --c-bg-soft:   #f8fafc;
        --radius-card: .875rem;
    }

    /* ─── Layout ─── */
    .list-page { max-width: 960px; margin: 0 auto; }

    /* ─── Stat Cards ─── */
    .stat-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 1rem;
        margin-bottom: 1.5rem;
    }
    @media (max-width: 680px) {
        .stat-grid { grid-template-columns: repeat(2, 1fr); gap: .75rem; }
    }

    .stat-card {
        background: #fff;
        border: 1px solid var(--c-border);
        border-radius: var(--radius-card);
        padding: 1.25rem 1.5rem;
        display: flex !important; flex-direction: row !important; align-items: center !important; justify-content: flex-start !important; text-align: left !important; gap: 1.25rem !important;
        box-shadow: 0 1px 3px rgba(0,0,0,.04);
        transition: box-shadow .2s;
    }
    .stat-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,.08); }
    .stat-card > div:not(.stat-icon) {
        flex: 0 1 auto !important;
        text-align: left !important;
        margin: 0 !important;
    }

    .stat-icon {
        width: 48px; height: 48px; border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.15rem; flex-shrink: 0;
        margin: 0 !important;
    }
    .stat-val  { font-size: 1.5rem; font-weight: 800; line-height: 1; color: var(--c-text); margin-bottom: .25rem; text-align: left !important; }
    .stat-lbl  { font-size: .7rem; font-weight: 600; text-transform: uppercase; letter-spacing: .03em; color: var(--c-muted); line-height: 1.35; margin: 0; text-align: left !important; }

    /* ─── Section Card ─── */
    .section-card {
        background: #fff;
        border: 1px solid var(--c-border);
        border-radius: var(--radius-card);
        box-shadow: 0 1px 4px rgba(0,0,0,.04);
        overflow: hidden;
    }
    .section-hdr {
        display: flex; align-items: center; gap: .625rem;
        padding: .875rem 1.25rem;
        border-bottom: 1px solid #f1f5f9;
        background: var(--c-bg-soft);
        font-weight: 700; font-size: .9rem; color: var(--c-text);
    }

    /* ─── Rapor row item ─── */
    .rapor-item {
        display: grid;
        grid-template-columns: 28px 44px 1fr auto;
        align-items: center;
        gap: .75rem;
        padding: .875rem 1.125rem;
        border-bottom: 1px solid #f1f5f9;
        transition: background .15s;
    }
    .rapor-item:last-child { border-bottom: none; }
    .rapor-item:hover { background: #fafbff; }

    .s-num { font-size: .8rem; font-weight: 600; color: var(--c-muted); text-align: center; }

    .s-avatar {
        width: 44px; height: 44px; border-radius: 50%;
        background: var(--c-avatar);
        color: #fff; font-weight: 700; font-size: .9rem;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
        border: 2px solid rgba(29,78,122,.15);
    }

    .s-info { min-width: 0; }
    .s-name {
        font-weight: 700; font-size: .9rem; color: var(--c-text);
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        margin-bottom: .25rem;
    }
    .s-meta { display: flex; align-items: center; gap: .5rem; flex-wrap: wrap; }

    .chip {
        display: inline-flex; align-items: center; gap: .25rem;
        font-size: .68rem; font-weight: 700; text-transform: uppercase;
        letter-spacing: .03em; padding: .2rem .55rem; border-radius: 9999px;
    }
    .chip-draft    { background: var(--c-warning-light); color: var(--c-warning); }
    .chip-approved { background: var(--c-success-light); color: var(--c-success); }
    .chip-exported { background: #e0f2fe; color: #0369a1; }
    .chip-statis   { background: var(--c-danger-light);  color: var(--c-danger); }
    .chip-ai       { background: var(--c-success-light); color: var(--c-success); }

    .s-kelas { font-size: .75rem; color: var(--c-muted); white-space: nowrap; }
    .s-date  { font-size: .72rem; color: #94a3b8; white-space: nowrap; }

    .act-row { display: flex; gap: .375rem; align-items: center; flex-shrink: 0; }
    .act-btn {
        width: 32px; height: 32px; border-radius: .5rem;
        display: inline-flex; align-items: center; justify-content: center;
        font-size: .78rem; border: none; cursor: pointer;
        transition: opacity .15s, transform .15s;
        text-decoration: none;
    }
    .act-btn:hover { opacity: .85; transform: translateY(-1px); }
    .ab-view   { background: #dbeafe; color: #1d4ed8; }
    .ab-pdf    { background: #dbeafe; color: #1d4ed8; }

    @media (max-width: 540px) {
        .rapor-item {
            grid-template-columns: 28px 40px 1fr;
            grid-template-rows: auto auto;
            row-gap: .5rem;
            padding: .875rem 1rem;
        }
        .rapor-item .s-num    { grid-row: 1; grid-column: 1; }
        .rapor-item .s-avatar { grid-row: 1; grid-column: 2; width: 40px; height: 40px; font-size: .85rem; }
        .rapor-item .s-info   { grid-row: 1; grid-column: 3; }
        .rapor-item .act-row  { grid-row: 2; grid-column: 2 / span 2; justify-content: flex-end; }
    }

    .hdr-btns { display: flex; gap: .625rem; flex-wrap: wrap; align-items: center; justify-content: flex-end; }
    @media (max-width: 576px) {
        .hdr-btns { flex-direction: column; align-items: stretch; }
        .hdr-btns .btn { width: 100%; text-align: center; }
    }

    .empty-box { padding: 3rem 1rem; text-align: center; color: var(--c-muted); }
    .empty-box i { font-size: 2.75rem; opacity: .25; display: block; margin-bottom: 1rem; }
</style>

<div class="container-fluid py-4 px-3 px-md-4">
<div class="list-page">

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="../index.php" class="text-decoration-none">Arsip</a></li>
            <li class="breadcrumb-item"><a href="../view.php?id=<?= $arsip_id ?>" class="text-decoration-none"><?= htmlspecialchars($arsip['judul']) ?></a></li>
            <li class="breadcrumb-item"><a href="arsip_rapot_tahunan.php?id=<?= $arsip_id ?>" class="text-decoration-none">Rapor Tahunan</a></li>
            <li class="breadcrumb-item active">Kamar <?= htmlspecialchars($kamar) ?></li>
        </ol>
    </nav>

    <!-- Header -->
    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-start mb-4 gap-3">
        <div>
            <h3 class="fw-bolder mb-1" style="color:var(--c-text); letter-spacing:-.02em;">
                <i class="fas fa-file-alt me-2" style="color:var(--c-primary);"></i>Daftar Rapor Tahunan (Arsip)
            </h3>
            <p class="text-muted mb-0 small">
                Kamar <strong><?= htmlspecialchars($kamar) ?></strong>
                &mdash; Periode <strong><?= htmlspecialchars($periode) ?></strong>
                &nbsp;&bull;&nbsp; <?= $total_santri_kamar ?> santri
            </p>
        </div>
        <div class="hdr-btns">
            <?php if ($approved > 0): ?>
            <div class="dropdown flex-grow-1 flex-sm-grow-0">
                <button class="btn btn-primary fw-semibold w-100" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="border-radius:.75rem;">
                    <i class="fas fa-download me-1"></i> Download Approved (<?= $approved ?>)
                </button>
                <ul class="dropdown-menu shadow-sm border-0 mt-2" style="border-radius:.75rem; box-shadow: 0 4px 12px rgba(0,0,0,0.1) !important;">
                    <li>
                        <a class="dropdown-item py-2" href="../export/generate_pdf_tahunan_arsip.php?arsip_id=<?= $arsip_id ?>&kamar=<?= urlencode($kamar) ?>&periode=<?= urlencode($periode) ?>">
                            <i class="fas fa-file-pdf me-2 text-danger"></i>Gabung 1 File PDF (Cetak)
                        </a>
                    </li>
                </ul>
            </div>
            <?php endif; ?>

            <a href="arsip_rapot_tahunan.php?id=<?= $arsip_id ?>" class="btn btn-light border fw-medium flex-grow-1 flex-sm-grow-0" style="border-radius:.75rem;">
                <i class="fas fa-arrow-left me-1"></i> Kembali
            </a>
        </div>
    </div>

    <!-- Stat Grid -->
    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background:var(--c-primary-light); color:var(--c-primary);">
                <i class="fas fa-file-alt"></i>
            </div>
            <div>
                <div class="stat-val"><?= $total ?></div>
                <div class="stat-lbl">Total Rapor</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:var(--c-warning-light); color:var(--c-warning);">
                <i class="fas fa-pen"></i>
            </div>
            <div>
                <div class="stat-val"><?= $draft ?></div>
                <div class="stat-lbl">Draft</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:var(--c-success-light); color:var(--c-success);">
                <i class="fas fa-check-circle"></i>
            </div>
            <div>
                <div class="stat-val"><?= $approved ?></div>
                <div class="stat-lbl">Approved</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#f0fdf4; color:#166534;">
                <i class="fas fa-users"></i>
            </div>
            <div>
                <div class="stat-val"><?= $total_santri_kamar ?></div>
                <div class="stat-lbl">Santri Kamar</div>
            </div>
        </div>
    </div>

    <!-- Main List Card -->
    <div class="section-card">
        <div class="section-hdr">
            <i class="fas fa-list-ul" style="color:var(--c-primary);"></i>
            <span>Daftar Santri</span>
            <span class="ms-auto text-muted fw-normal" style="font-size:.8rem;"><?= $total_santri_kamar ?> entri</span>
        </div>

        <!-- List -->
        <?php if (empty($rapor_list)): ?>
        <div class="empty-box">
            <i class="fas fa-inbox"></i>
            <p class="mb-3">Tidak ada data rapor tahunan untuk kamar ini di arsip.</p>
        </div>
        <?php else: ?>
        <?php foreach ($rapor_list as $i => $r):
            $punya_rapor = !empty($r['rapor_id']);
        ?>
        <div class="rapor-item <?= !$punya_rapor ? 'opacity-75 bg-light' : '' ?>">
            <div class="s-num"><?= $i + 1 ?>.</div>

            <div class="s-avatar <?= !$punya_rapor ? 'bg-secondary' : '' ?>"><?= strtoupper(substr($r['nama_santri'] ?? '?', 0, 2)) ?></div>

            <div class="s-info">
                <div class="s-name <?= !$punya_rapor ? 'text-muted' : '' ?>"><?= htmlspecialchars($r['nama_santri'] ?? 'N/A') ?></div>
                <div class="s-meta">
                    <?php if ($punya_rapor): ?>
                        <?php 
                        $status     = $r['status'] ?? 'DRAFT';
                        $chip_class = match($status) { 'APPROVED' => 'chip-approved', 'EXPORTED' => 'chip-exported', default => 'chip-draft' };
                        $chip_icon  = match($status) { 'APPROVED' => 'fa-check-circle', 'EXPORTED' => 'fa-file-download', default => 'fa-pen' };
                        $display_status = ($status === 'EXPORTED') ? 'DOWNLOADED' : $status;
                        ?>
                        <span class="chip <?= $chip_class ?>"><i class="fas <?= $chip_icon ?>"></i> <?= $display_status ?></span>
                        <?php if ($r['is_fallback']): ?>
                        <span class="chip chip-statis"><i class="fas fa-exclamation-triangle"></i> Statis</span>
                        <?php else: ?>
                        <span class="chip chip-ai" style="background:#e8f4fd; color:#1d6fa4;"><i class="fas fa-pen-nib"></i> Otomatis</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="chip" style="background:#e2e8f0; color:#64748b;"><i class="fas fa-minus"></i> Belum ada rapor</span>
                    <?php endif; ?>
                    
                    <span class="s-kelas"><i class="fas fa-graduation-cap me-1"></i>Kelas <?= htmlspecialchars($r['kelas'] ?? 'N/A') ?></span>
                    <?php if ($punya_rapor && !empty($r['generated_at'])): ?>
                    <span class="s-date d-none d-md-inline"><?= date('d M Y', strtotime($r['generated_at'])) ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Actions -->
            <div class="act-row">
                <?php if ($punya_rapor): ?>
                    <a href="arsip_rapot_tahunan_detail.php?id=<?= $arsip_id ?>&santri_id=<?= $r['santri_id'] ?>&periode=<?= urlencode($periode) ?>"
                       class="act-btn ab-view" title="Lihat detail">
                        <i class="fas fa-eye"></i>
                    </a>

                    <?php if (in_array($status, ['APPROVED', 'EXPORTED'])): ?>
                    <a href="../export/generate_pdf_tahunan_arsip.php?rapor_id=<?= $r['rapor_id'] ?>"
                       class="act-btn ab-pdf" title="Download PDF">
                        <i class="fas fa-file-pdf"></i>
                    </a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

        <!-- Footer stats -->
        <?php if ($total > 0): ?>
        <div class="px-4 py-2 border-top d-flex align-items-center gap-3 flex-wrap" style="background:var(--c-bg-soft);">
            <small class="text-muted">Total: <?= $total ?> &bull; <?= $approved ?> approved &bull; <?= $draft ?> draft</small>
        </div>
        <?php endif; ?>
    </div>

</div>
</div>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
