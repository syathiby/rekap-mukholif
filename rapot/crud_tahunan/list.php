<?php
// rapot/crud_tahunan/list.php
// Daftar rapor tahunan per santri untuk kamar + periode tertentu

require_once __DIR__ . '/../../bootstrap/init.php';
require_once __DIR__ . '/../config/helper.php';

guard(['rapot_view', 'rapot_cetak', 'rapot_create', 'rapot_delete']);

$can_cetak  = has_permission('rapot_cetak');
$can_delete = has_permission('rapot_delete');
$can_create = has_permission('rapot_create');

$kamar   = trim($_GET['kamar']   ?? '');
$periode = trim($_GET['periode'] ?? '');
$page_title = "Daftar Rapor Tahunan — Kamar $kamar";

if (empty($kamar) || empty($periode)) {
    set_flash_message('Parameter tidak lengkap.', 'danger');
    header('Location: index.php');
    exit;
}

// Flash message ditangani global oleh footer.php

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // Approve single
    if ($_POST['action'] === 'approve') {
        guard('rapot_create');
        $rapor_id = (int)($_POST['rapor_id'] ?? 0);
        if ($rapor_id) {
            try {
                $stmt_cek = $conn->prepare("SELECT narasi_ai FROM rapot_tahunan WHERE id = ?");
                $stmt_cek->bind_param('i', $rapor_id);
                $stmt_cek->execute();
                $row_cek = $stmt_cek->get_result()->fetch_assoc();
                $stmt_cek->close();
                
                $narasi = trim($row_cek['narasi_ai'] ?? '');
                if (strlen($narasi) < 15 || preg_match_all('/[a-zA-Z0-9]/', $narasi) < 10) {
                    $flash = ['type' => 'danger', 'message' => 'Gagal approve: Catatan belum diisi atau tidak valid. Buka detail rapor untuk mengedit catatan.'];
                } else {
                    $uid = (int)($_SESSION['user_id'] ?? 0);
                    $stmt_ap = $conn->prepare("
                        UPDATE rapot_tahunan
                        SET status = 'APPROVED', approved_by = ?, approved_at = NOW()
                        WHERE id = ? AND status = 'DRAFT'
                    ");
                    $stmt_ap->bind_param('ii', $uid, $rapor_id);
                    $stmt_ap->execute();
                    if ($stmt_ap->affected_rows > 0) {
                        write_activity_log('UPDATE', 'rapot_tahunan', "Approve rapor tahunan ID $rapor_id", ['id' => $rapor_id]);
                        $flash = ['type' => 'success', 'message' => 'Rapor berhasil di-approve.'];
                    } else {
                        $flash = ['type' => 'warning', 'message' => 'Rapor tidak ditemukan atau sudah dalam status APPROVED.'];
                    }
                    $stmt_ap->close();
                }
            } catch (Exception $e) {
                $flash = ['type' => 'danger', 'message' => 'Gagal approve: ' . $e->getMessage()];
            }
        }
    }

    // Approve semua
    if ($_POST['action'] === 'approve_all') {
        guard('rapot_create');
        try {
            $stmt_cek = $conn->prepare("SELECT id, narasi_ai FROM rapot_tahunan WHERE kamar = ? AND periode = ? AND status = 'DRAFT'");
            $stmt_cek->bind_param('ss', $kamar, $periode);
            $stmt_cek->execute();
            $drafts = $stmt_cek->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt_cek->close();

            $invalid_count = 0;
            foreach ($drafts as $d) {
                $narasi = trim($d['narasi_ai'] ?? '');
                if (strlen($narasi) < 15 || preg_match_all('/[a-zA-Z0-9]/', $narasi) < 10) {
                    $invalid_count++;
                }
            }
            
            if ($invalid_count > 0) {
                $flash = ['type' => 'danger', 'message' => "Gagal approve: Terdapat $invalid_count rapor yang catatannya belum diisi atau tidak valid. Harap lengkapi terlebih dahulu."];
            } else {
                $uid = (int)($_SESSION['user_id'] ?? 0);
                $stmt_ap = $conn->prepare("
                    UPDATE rapot_tahunan
                    SET status = 'APPROVED', approved_by = ?, approved_at = NOW()
                    WHERE kamar = ? AND periode = ? AND status = 'DRAFT'
                ");
                $stmt_ap->bind_param('iss', $uid, $kamar, $periode);
                $stmt_ap->execute();
                $approved_count = $stmt_ap->affected_rows;
                $stmt_ap->close();
                if ($approved_count > 0) {
                    write_activity_log('UPDATE', 'rapot_tahunan', "Approve semua rapor DRAFT Kamar $kamar periode $periode", []);
                    $flash = ['type' => 'success', 'message' => "$approved_count rapor berstatus DRAFT berhasil di-approve."];
                } else {
                    $flash = ['type' => 'warning', 'message' => 'Tidak ada rapor DRAFT yang bisa di-approve.'];
                }
            }
        } catch (Exception $e) {
            $flash = ['type' => 'danger', 'message' => 'Gagal approve semua: ' . $e->getMessage()];
        }
    }

    // Hapus Bulk
    if ($_POST['action'] === 'delete_all') {
        guard('rapot_delete');
        $delete_type = $_POST['delete_type'] ?? 'draft_only';
        try {
            if ($delete_type === 'all') {
                $stmt_del = $conn->prepare("
                    DELETE FROM rapot_tahunan
                    WHERE kamar = ? AND periode = ?
                ");
            } else {
                $stmt_del = $conn->prepare("
                    DELETE FROM rapot_tahunan
                    WHERE kamar = ? AND periode = ? AND status = 'DRAFT'
                ");
            }
            $stmt_del->bind_param('ss', $kamar, $periode);
            $stmt_del->execute();
            $deleted = $stmt_del->affected_rows;
            $stmt_del->close();
            write_activity_log('DELETE', 'rapot_tahunan', "Hapus rapor tahunan ($delete_type) Kamar $kamar periode $periode", []);
            $flash = ['type' => 'success', 'message' => "$deleted rapor berhasil dihapus."];
        } catch (Exception $e) {
            $flash = ['type' => 'danger', 'message' => 'Gagal hapus: ' . $e->getMessage()];
        }
    }
}

// Ambil semua rapor tahunan untuk kamar + periode
try {
    [$tahun_awal] = explode('/', $periode);
    $tahun_akhir = (int)$tahun_awal + 1;
    
    $stmt = $conn->prepare("
        SELECT s.id as santri_id, s.nama AS nama_santri, s.kelas,
               rt.id as rapor_id, rt.status, rt.is_fallback, rt.generated_at, rt.approved_at,
               u.nama_lengkap AS nama_approver,
               (SELECT COUNT(*) FROM rapot_kepengasuhan rk WHERE rk.santri_id = s.id AND (rk.tahun = ? OR rk.tahun = ?)) as data_bulanan
        FROM santri s
        LEFT JOIN rapot_tahunan rt ON s.id = rt.santri_id AND rt.periode = ?
        LEFT JOIN users u ON rt.approved_by = u.id
        WHERE s.kamar = ?
        ORDER BY data_bulanan = 0 ASC, s.nama ASC
    ");
    $stmt->bind_param('iiss', $tahun_awal, $tahun_akhir, $periode, $kamar);
    $stmt->execute();
    $rapor_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    set_flash_message('Gagal mengambil data: ' . $e->getMessage(), 'danger');
    header('Location: index.php');
    exit;
}

// Hitung statistik
$total_santri_kamar = count($rapor_list);
$total    = count(array_filter($rapor_list, fn($r) => !empty($r['rapor_id'])));
$draft    = count(array_filter($rapor_list, fn($r) => $r['status'] === 'DRAFT'));
$approved = count(array_filter($rapor_list, fn($r) => $r['status'] === 'APPROVED'));
$fallback = count(array_filter($rapor_list, fn($r) => !empty($r['rapor_id']) && $r['is_fallback']));
$ai_ok    = $total - $fallback;

require_once __DIR__ . '/../../layouts/header.php';
?>

<style>
    /* ─── Warna tema — warm slate/teal, bukan ungu ─── */
    :root {
        --c-primary:   #1d6fa4;   /* biru teal hangat */
        --c-primary-light: #e8f4fd;
        --c-success:   #1a7c4f;
        --c-success-light: #e6f4ee;
        --c-warning:   #b45309;
        --c-warning-light: #fef3c7;
        --c-danger:    #b91c1c;
        --c-danger-light:  #fee2e2;
        --c-avatar:    #1d4e7a;   /* biru navy solid, hangat */
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
        /* num | avatar | info | actions */
        grid-template-columns: 28px 44px 1fr auto;
        align-items: center;
        gap: .75rem;
        padding: .875rem 1.125rem;
        border-bottom: 1px solid #f1f5f9;
        transition: background .15s;
    }
    .rapor-item:last-child { border-bottom: none; }
    .rapor-item:hover { background: #fafbff; }

    .s-num {
        font-size: .8rem; font-weight: 600; color: var(--c-muted);
        text-align: center;
    }

    /* Avatar — solid navy, bukan gradient ungu */
    .s-avatar {
        width: 44px; height: 44px; border-radius: 50%;
        background: var(--c-avatar);
        color: #fff; font-weight: 700; font-size: .9rem;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
        border: 2px solid rgba(29,78,122,.15);
    }

    /* Info block */
    .s-info { min-width: 0; }
    .s-name {
        font-weight: 700; font-size: .9rem; color: var(--c-text);
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        margin-bottom: .25rem;
    }
    .s-meta {
        display: flex; align-items: center; gap: .5rem; flex-wrap: wrap;
    }

    /* Status / narasi pills */
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

    .s-kelas {
        font-size: .75rem; color: var(--c-muted);
        white-space: nowrap;
    }
    .s-date  {
        font-size: .72rem; color: #94a3b8;
        white-space: nowrap;
    }

    /* Action buttons */
    .act-row {
        display: flex; gap: .375rem; align-items: center; flex-shrink: 0;
    }
    .act-btn {
        width: 32px; height: 32px; border-radius: .5rem;
        display: inline-flex; align-items: center; justify-content: center;
        font-size: .78rem; border: none; cursor: pointer;
        transition: opacity .15s, transform .15s;
        text-decoration: none;
    }
    .act-btn:hover { opacity: .85; transform: translateY(-1px); }
    .ab-view   { background: #dbeafe; color: #1d4ed8; }
    .ab-ok     { background: #dcfce7; color: #15803d; }
    .ab-pdf    { background: #dbeafe; color: #1d4ed8; }
    .ab-del    { background: #fee2e2; color: #b91c1c; }

    /* ─── Mobile: stack layout ─── */
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

    /* ─── Buttons header ─── */
    .hdr-btns { display: flex; gap: .625rem; flex-wrap: wrap; align-items: center; justify-content: flex-end; }
    @media (max-width: 576px) {
        .hdr-btns { flex-direction: column; align-items: stretch; }
        .hdr-btns .btn { width: 100%; text-align: center; }
    }

    /* Empty state */
    .empty-box { padding: 3rem 1rem; text-align: center; color: var(--c-muted); }
    .empty-box i { font-size: 2.75rem; opacity: .25; display: block; margin-bottom: 1rem; }
</style>

<div class="container-fluid py-4 px-3 px-md-4">
<div class="list-page">

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="../index.php" class="text-decoration-none">Rapot Kepengasuhan</a></li>
            <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Rapor Tahunan</a></li>
            <li class="breadcrumb-item active">Kamar <?= htmlspecialchars($kamar) ?></li>
        </ol>
    </nav>

    <!-- Header -->
    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-start mb-4 gap-3">
        <div>
            <h3 class="fw-bolder mb-1" style="color:var(--c-text); letter-spacing:-.02em;">
                <i class="fas fa-file-alt me-2" style="color:var(--c-primary);"></i>Daftar Rapor Tahunan
            </h3>
            <p class="text-muted mb-0 small">
                Kamar <strong><?= htmlspecialchars($kamar) ?></strong>
                &mdash; Periode <strong><?= htmlspecialchars($periode) ?></strong>
                &nbsp;&bull;&nbsp; <?= $total_santri_kamar ?> santri
            </p>
        </div>
        <div class="hdr-btns">
            <?php if ($can_create): ?>
            <a href="generate.php?kamar=<?= urlencode($kamar) ?>&periode=<?= urlencode($periode) ?>"
               class="btn btn-success fw-semibold" style="border-radius:.75rem;">
                <i class="fas fa-magic me-1"></i> Generate Ulang
            </a>
            <?php endif; ?>
            <?php if ($can_create && $draft > 0): ?>
            <form method="POST" class="m-0 p-0" id="form-approve-all">
                <input type="hidden" name="action" value="approve_all">
                <button type="button" class="btn btn-primary fw-semibold w-100" style="border-radius:.75rem;" onclick="confirmApproveAll()">
                    <i class="fas fa-check-double me-1"></i> Approve Semua Draft
                </button>
            </form>
            <?php endif; ?>
            <?php if ($can_delete && ($draft > 0 || $approved > 0)): ?>
            <button type="button" class="btn btn-danger fw-semibold" style="border-radius:.75rem;"
                    onclick="confirmDeleteAll()">
                <i class="fas fa-trash me-1"></i> Hapus Rapor
            </button>
            <?php endif; ?>
            <a href="index.php" class="btn btn-light border fw-medium" style="border-radius:.75rem;">
                <i class="fas fa-arrow-left me-1"></i> Kembali
            </a>
        </div>
    </div>

    <!-- Flash ditangani oleh SweetAlert Toast -->

    <!-- Stat Grid -->
    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background:var(--c-primary-light); color:var(--c-primary);">
                <i class="fas fa-users"></i>
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
            <div class="stat-icon" style="background:var(--c-success-light);color:var(--c-success);">
                <i class="fas fa-pen-nib"></i>
            </div>
            <div>
                <div class="stat-val"><?= $ai_ok ?></div>
                <div class="stat-lbl">Catatan Otomatis</div>
            </div>
        </div>
    </div>

    <!-- Main List Card -->
    <div class="section-card">
        <div class="section-hdr">
            <i class="fas fa-list-ul" style="color:var(--c-primary);"></i>
            <span>Daftar Santri</span>
            <span class="ms-auto text-muted fw-normal" style="font-size:.8rem;"><?= $total_santri_kamar ?> entri</span>
            <?php if ($approved === $total && $total > 0 && $can_cetak): ?>
            <a href="../export/generate_pdf_tahunan.php?kamar=<?= urlencode($kamar) ?>&periode=<?= urlencode($periode) ?>"
               class="btn btn-sm btn-primary ms-2 fw-semibold" style="border-radius:.625rem;">
                <i class="fas fa-download me-1"></i> Download Semua PDF
            </a>
            <?php endif; ?>
        </div>

        <!-- List -->
        <?php if (empty($rapor_list)): ?>
        <div class="empty-box">
            <i class="fas fa-inbox"></i>
            <p class="mb-3">Belum ada rapor tahunan yang di-generate untuk kamar ini.</p>
            <?php if ($can_create): ?>
            <a href="generate.php?kamar=<?= urlencode($kamar) ?>&periode=<?= urlencode($periode) ?>"
               class="btn btn-success fw-semibold px-4" style="border-radius:.75rem;">
                <i class="fas fa-magic me-2"></i>Generate Sekarang
            </a>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <?php foreach ($rapor_list as $i => $r):
            $punya_rapor = !empty($r['rapor_id']);
            $no_data = ($r['data_bulanan'] == 0);
        ?>
        <div class="rapor-item <?= $no_data ? 'opacity-75 bg-light' : '' ?>">
            <!-- Nomor -->
            <div class="s-num"><?= $i + 1 ?>.</div>

            <!-- Avatar -->
            <div class="s-avatar <?= $no_data ? 'bg-secondary' : '' ?>"><?= strtoupper(substr($r['nama_santri'] ?? '?', 0, 2)) ?></div>

            <!-- Info -->
            <div class="s-info">
                <div class="s-name <?= $no_data ? 'text-muted' : '' ?>"><?= htmlspecialchars($r['nama_santri'] ?? 'N/A') ?></div>
                <div class="s-meta">
                    <?php if ($punya_rapor): ?>
                        <?php 
                        $status     = $r['status'] ?? 'DRAFT';
                        $chip_class = match($status) { 'APPROVED' => 'chip-approved', 'EXPORTED' => 'chip-exported', default => 'chip-draft' };
                        $chip_icon  = match($status) { 'APPROVED' => 'fa-check-circle', 'EXPORTED' => 'fa-file-download', default => 'fa-pen' };
                        ?>
                        <span class="chip <?= $chip_class ?>"><i class="fas <?= $chip_icon ?>"></i> <?= $status ?></span>
                        <?php if ($r['is_fallback']): ?>
                        <span class="chip chip-statis"><i class="fas fa-exclamation-triangle"></i> Statis</span>
                        <?php else: ?>
                        <span class="chip chip-ai" style="background:#e8f4fd; color:#1d6fa4;"><i class="fas fa-pen-nib"></i> Otomatis</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php if ($no_data): ?>
                        <span class="chip" style="background:#e2e8f0; color:#64748b;"><i class="fas fa-minus"></i> Belum ada data</span>
                        <?php else: ?>
                        <span class="chip chip-draft"><i class="fas fa-clock"></i> Belum Digenerate</span>
                        <?php endif; ?>
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
                    <a href="detail.php?santri_id=<?= $r['santri_id'] ?>&periode=<?= urlencode($periode) ?>"
                       class="act-btn ab-view" title="Lihat detail">
                        <i class="fas fa-eye"></i>
                    </a>

                    <?php if ($status === 'DRAFT' && $can_create): ?>
                    <form method="POST" class="d-inline" onsubmit="confirmSubmit(event, this, 'Approve Rapor?', 'Approve rapor <?= htmlspecialchars(addslashes($r['nama_santri'] ?? '')) ?>?');">
                        <input type="hidden" name="action"   value="approve">
                        <input type="hidden" name="rapor_id" value="<?= $r['rapor_id'] ?>">
                        <button type="submit" class="act-btn ab-ok" title="Approve">
                            <i class="fas fa-check"></i>
                        </button>
                    </form>
                    <?php endif; ?>

                    <?php if ($status === 'APPROVED' && $can_cetak): ?>
                    <a href="../export/generate_pdf_tahunan.php?id=<?= $r['rapor_id'] ?>"
                       class="act-btn ab-pdf" title="Download PDF">
                        <i class="fas fa-file-pdf"></i>
                    </a>
                    <?php endif; ?>

                    <?php if ($can_delete && $status !== 'APPROVED'): ?>
                    <a href="delete.php?id=<?= $r['rapor_id'] ?>"
                       class="act-btn ab-del" title="Hapus"
                       onclick="confirmSubmit(event, this, 'Hapus Rapor?', 'Hapus draft rapor <?= htmlspecialchars(addslashes($r['nama_santri'] ?? '')) ?>?');">
                        <i class="fas fa-trash"></i>
                    </a>
                    <?php endif; ?>
                <?php else: ?>
                    <?php if (!$no_data && $can_create): ?>
                    <a href="generate.php?kamar=<?= urlencode($kamar) ?>&periode=<?= urlencode($periode) ?>" class="btn btn-sm btn-outline-success fw-medium" style="border-radius:.5rem;">
                        Generate
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

</div><!-- .list-page -->
</div><!-- .container-fluid -->

<form id="form-delete-all" method="POST" class="d-none">
    <input type="hidden" name="action" value="delete_all">
    <input type="hidden" name="delete_type" id="delete-type-val" value="draft_only">
</form>

<script>
function confirmApproveAll() {
    Swal.fire({
        title: 'Approve Semua Draft?',
        text: "Semua rapor berstatus DRAFT di kamar ini akan disetujui sekaligus dan siap dicetak.",
        icon: 'info',
        showCancelButton: true,
        confirmButtonColor: '#0d6efd',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fas fa-check-double"></i> Ya, Approve Semua!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('form-approve-all').submit();
        }
    });
}

function confirmDeleteAll() {
    Swal.fire({
        title: 'Hapus Rapor Tahunan',
        html: `
            <div class="text-start mb-2">
                <p class="text-muted mb-3" style="font-size:.85rem; line-height:1.4;">Pilih data rapor yang dihapus untuk Kamar <strong><?= htmlspecialchars($kamar) ?></strong> periode <strong><?= htmlspecialchars($periode) ?></strong>:</p>
                
                <label class="d-flex align-items-start gap-2 p-2 mb-2 border rounded shadow-sm" style="cursor:pointer; background:#fff;">
                    <input class="form-check-input mt-1 ms-1" type="radio" name="swal_delete_type" value="draft_only" checked style="flex-shrink:0;">
                    <div style="line-height:1.3;">
                        <div class="fw-bold text-dark mb-1" style="font-size:.9rem;">Hanya Draft <span class="badge bg-warning text-dark ms-1"><?= $draft ?> data</span></div>
                        <div class="text-muted" style="font-size:.75rem;">Rapor yang Approved tetap aman.</div>
                    </div>
                </label>

                <label class="d-flex align-items-start gap-2 p-2 border border-danger-subtle bg-danger-subtle rounded shadow-sm" style="cursor:pointer;">
                    <input class="form-check-input mt-1 ms-1" type="radio" name="swal_delete_type" value="all" style="flex-shrink:0;">
                    <div style="line-height:1.3;">
                        <div class="fw-bold text-danger mb-1" style="font-size:.9rem;">Semua Rapor <span class="badge bg-danger ms-1"><?= $total ?> data</span></div>
                        <div class="text-danger" style="font-size:.75rem;">Hati-hati! Data akan terhapus permanen.</div>
                    </div>
                </label>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fas fa-trash"></i> Hapus Terpilih',
        cancelButtonText: 'Batal',
        preConfirm: () => {
            return document.querySelector('input[name="swal_delete_type"]:checked').value;
        }
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('delete-type-val').value = result.value;
            document.getElementById('form-delete-all').submit();
        }
    });
}
</script>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
