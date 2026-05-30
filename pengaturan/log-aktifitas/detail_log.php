<?php
// FILE: detail_log.php (pengaturan/log-aktifitas/detail_log.php)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../bootstrap/init.php';
guard('activity_log_manage');

// ─── AMBIL ID LOG & BACK URL ────────────────────────────────────────
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

// Simpan query string filter dari index (dikirim via ?back=...)
$back_qs   = $_GET['back'] ?? '';
$back_url  = 'index.php' . ($back_qs !== '' ? '?' . ltrim($back_qs, '?') : '');

// ─── FETCH DATA LOG ──────────────────────────────────────────────────
$stmt = $conn->prepare("
    SELECT id, user_id, username, nama_lengkap, aksi, fitur, deskripsi, detail, ip_address, user_agent, dibuat_pada
    FROM log_aktifitas
    WHERE id = ?
    LIMIT 1
");
$stmt->bind_param("i", $id);
$stmt->execute();
$log = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$log) {
    header('Location: index.php');
    exit;
}

// ─── BADGE CONFIG ────────────────────────────────────────────────────
function get_badge_cfg($aksi) {
    switch ($aksi) {
        case 'CREATE':            return ['cls' => 'badge-create',  'icon' => 'fa-plus'];
        case 'UPDATE':
        case 'UPDATE_PERMISSION': return ['cls' => 'badge-update',  'icon' => 'fa-pencil'];
        case 'DELETE':            return ['cls' => 'badge-delete',  'icon' => 'fa-trash'];
        case 'LOGIN':             return ['cls' => 'badge-login',   'icon' => 'fa-sign-in-alt'];
        case 'LOGOUT':            return ['cls' => 'badge-logout',  'icon' => 'fa-sign-out-alt'];
        case 'RESET_POIN':
        case 'CHANGE_PERIODE':    return ['cls' => 'badge-system',  'icon' => 'fa-sync-alt'];
        case 'BACKUP':
        case 'RESTORE':           return ['cls' => 'badge-db',      'icon' => 'fa-database'];
        default:                  return ['cls' => 'badge-default', 'icon' => 'fa-circle'];
    }
}
$badge = get_badge_cfg($log['aksi']);

// ─── FORMAT JSON DETAIL ──────────────────────────────────────────────
$detail_formatted = null;
$detail_parsed    = null;
if (!empty($log['detail'])) {
    $detail_parsed    = json_decode($log['detail'], true);
    $detail_formatted = json_encode($detail_parsed, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

// ─── NAVIGASI PREV / NEXT dihapus ───────────────────────────────────

// ─── PARSE USER AGENT ────────────────────────────────────────────────
function parse_ua($ua) {
    $browser = 'Unknown Browser';
    $os      = 'Unknown OS';

    if (preg_match('/Edg\/([\d.]+)/', $ua, $m))         $browser = 'Microsoft Edge ' . $m[1];
    elseif (preg_match('/OPR\/([\d.]+)/', $ua, $m))     $browser = 'Opera ' . $m[1];
    elseif (preg_match('/Chrome\/([\d.]+)/', $ua, $m))  $browser = 'Google Chrome ' . $m[1];
    elseif (preg_match('/Firefox\/([\d.]+)/', $ua, $m)) $browser = 'Mozilla Firefox ' . $m[1];
    elseif (preg_match('/Safari\/([\d.]+)/', $ua, $m))  $browser = 'Apple Safari ' . $m[1];
    elseif (strpos($ua, 'MSIE') !== false || strpos($ua, 'Trident') !== false) $browser = 'Internet Explorer';

    if (preg_match('/Windows NT ([\d.]+)/', $ua, $m)) {
        $ver = ['10.0'=>'10/11','6.3'=>'8.1','6.2'=>'8','6.1'=>'7','6.0'=>'Vista','5.1'=>'XP'];
        $os  = 'Windows ' . ($ver[$m[1]] ?? $m[1]);
    } elseif (preg_match('/Mac OS X ([\d_]+)/', $ua, $m)) {
        $os = 'macOS ' . str_replace('_', '.', $m[1]);
    } elseif (preg_match('/Android ([\d.]+)/', $ua, $m)) {
        $os = 'Android ' . $m[1];
    } elseif (strpos($ua, 'Linux') !== false) {
        $os = 'Linux';
    } elseif (strpos($ua, 'iPhone') !== false || strpos($ua, 'iPad') !== false) {
        $os = 'iOS';
    }

    return compact('browser', 'os');
}
$ua_info = parse_ua($log['user_agent']);

require_once __DIR__ . '/../../layouts/header.php';
?>

<style>
    /* ── LOCAL VARS ── */
    :root {
        --p:     #4f46e5;
        --p-h:   #4338ca;
        --p-lt:  #e0e7ff;
        --p-xlt: #eef2ff;
        --bd:    #e5e7eb;
        --tx:    #111827;
        --mu:    #6b7280;
        --bg:    #f9fafb;
    }

    /* ── INFO CARD ── */
    .info-card {
        background: #fff;
        border: 1px solid var(--bd);
        border-radius: 0.875rem;
        overflow: hidden;
    }
    .info-card-head {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.875rem 1.25rem;
        border-bottom: 1px solid var(--bd);
        background: #f9fafb;
    }
    .info-card-head-icon {
        width: 32px; height: 32px;
        border-radius: 8px;
        background: var(--p-xlt);
        color: var(--p);
        display: flex; align-items: center; justify-content: center;
        font-size: 0.875rem;
        flex-shrink: 0;
    }
    .info-card-head-title {
        font-size: 0.78rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.07em;
        color: var(--tx);
        margin: 0;
    }
    .info-card-body { padding: 0.25rem 0; }

    /* ── INFO ROW ── */
    .info-row {
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        padding: 0.75rem 1.25rem;
        border-bottom: 1px solid #f3f4f6;
        font-size: 0.875rem;
    }
    .info-row:last-child { border-bottom: none; }
    .info-row:hover { background: #f9fafb; }
    .info-row-icon {
        width: 16px;
        text-align: center;
        color: var(--mu);
        font-size: 0.8rem;
        flex-shrink: 0;
        margin-top: 2px;
    }
    .info-row-label {
        min-width: 120px;
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--mu);
        flex-shrink: 0;
        padding-top: 1px;
    }
    .info-row-value {
        color: var(--tx);
        font-weight: 500;
        word-break: break-word;
        flex: 1;
    }

    /* ── LOG HERO STRIP ── */
    .log-hero {
        background: #fff;
        border: 1px solid var(--bd);
        border-left: 4px solid var(--p);
        border-radius: 0.875rem;
        padding: 1.5rem 1.75rem;
        margin-bottom: 1.5rem;
    }
    .log-id-tag {
        display: inline-flex; align-items: center; gap: 0.35rem;
        font-size: 0.7rem; font-weight: 700;
        color: var(--mu);
        letter-spacing: 0.08em; text-transform: uppercase;
        background: #f3f4f6; border: 1px solid var(--bd);
        border-radius: 5px; padding: 0.2rem 0.6rem;
        margin-bottom: 0.75rem;
    }
    .log-hero-desc {
        font-size: 1.05rem;
        font-weight: 600;
        color: var(--tx);
        margin: 0.5rem 0 0.75rem;
        line-height: 1.5;
    }
    .log-hero-meta {
        display: flex; align-items: center; gap: 0.5rem;
        font-size: 0.82rem; color: var(--mu); font-weight: 500;
        flex-wrap: wrap;
    }
    .log-hero-meta .sep { color: #d1d5db; }

    /* ── BADGES ── */
    .log-badge {
        display: inline-flex; align-items: center;
        font-size: 0.7rem; font-weight: 600;
        letter-spacing: 0.04em; text-transform: uppercase;
        padding: 0.3rem 0.7rem; border-radius: 5px;
        border: 1px solid transparent;
        white-space: nowrap;
    }
    .badge-create  { background:#f0fdf4; color:#166534; border-color:#bbf7d0; }
    .badge-update  { background:#eff6ff; color:#1e40af; border-color:#bfdbfe; }
    .badge-delete  { background:#fef2f2; color:#991b1b; border-color:#fecaca; }
    .badge-login   { background:var(--p-xlt); color:var(--p-h); border-color:var(--p-lt); }
    .badge-logout  { background:#f9fafb; color:#6b7280; border-color:#e5e7eb; }
    .badge-system  { background:#faf5ff; color:#6d28d9; border-color:#e9d5ff; }
    .badge-db      { background:#fffbeb; color:#92400e; border-color:#fde68a; }
    .badge-default { background:#f3f4f6; color:#6b7280; border-color:#e5e7eb; }

    /* ── FITUR TAG ── */
    .fitur-tag {
        display: inline-block;
        background: #f3f4f6; color: #374151;
        border: 1px solid #e5e7eb; border-radius: 4px;
        padding: 0.2rem 0.55rem;
        font-size: 0.72rem; font-weight: 500;
    }

    /* ── IP BOX ── */
    .ip-box {
        display: inline-flex; align-items: center; gap: 0.4rem;
        font-family: 'Fira Code', 'JetBrains Mono', monospace;
        background: #f3f4f6; color: var(--tx);
        border: 1px solid var(--bd); border-radius: 5px;
        padding: 0.25rem 0.65rem; font-size: 0.82rem; font-weight: 500;
    }
    .ip-note {
        font-size: 0.75rem; color: var(--mu);
        margin-top: 0.3rem; display: flex; align-items: center; gap: 0.25rem;
    }

    /* ── UA RAW BOX ── */
    .ua-box {
        background: #0f172a; color: #cbd5e1;
        border: 1px solid #1e293b; border-radius: 0.625rem;
        padding: 0.875rem 1rem;
        font-family: 'Fira Code', 'JetBrains Mono', monospace;
        font-size: 0.78rem; word-break: break-all; line-height: 1.6;
    }

    /* ── JSON VIEWER ── */
    pre.json-viewer {
        background: #0f172a; color: #7dd3fc;
        padding: 1.25rem; border-radius: 0.625rem;
        max-height: 450px; overflow-y: auto;
        font-family: 'Fira Code', 'JetBrains Mono', monospace;
        font-size: 0.82rem; border: 1px solid #1e293b;
        line-height: 1.7; margin: 0; white-space: pre-wrap; word-break: break-word;
    }
    .json-viewer::-webkit-scrollbar { width: 6px; }
    .json-viewer::-webkit-scrollbar-track { background: #0f172a; }
    .json-viewer::-webkit-scrollbar-thumb { background: #334155; border-radius: 3px; }

    /* ── DIFF TABLE ── */
    .diff-wrap {
        border: 1px solid var(--bd);
        border-radius: 0.625rem;
        overflow: hidden;
    }
    .diff-head {
        display: flex; align-items: center; gap: 0.5rem;
        padding: 0.6rem 1rem;
        font-size: 0.72rem; font-weight: 700;
        text-transform: uppercase; letter-spacing: 0.06em;
        border-bottom: 1px solid var(--bd);
    }
    .diff-head.before { background:#fef2f2; color:#991b1b; }
    .diff-head.after  { background:#f0fdf4; color:#166534; }
    .diff-dot { width:8px; height:8px; border-radius:50%; }
    .diff-dot.before { background:#ef4444; }
    .diff-dot.after  { background:#22c55e; }
    .kv-table { width:100%; border-collapse:collapse; }
    .kv-table td {
        padding: 0.6rem 0.875rem; font-size: 0.82rem;
        border-bottom: 1px solid #f3f4f6; vertical-align: middle;
    }
    .kv-table tr:last-child td { border-bottom: none; }
    .kv-table tr:hover td { background: #fafafa; }
    .kv-table td:first-child {
        font-weight: 600; color: var(--mu); width: 36%;
        border-right: 1px solid #f3f4f6; background: #fafafa;
    }
    .kv-table td:last-child {
        font-family: 'Fira Code', monospace; word-break: break-word; font-weight: 500; color: var(--tx);
    }

    /* ── BACK BUTTON ── */
    .btn-back-action {
        display: inline-flex; align-items: center; gap: 0.4rem;
        padding: 0.5rem 1.1rem; border-radius: 0.5rem;
        font-size: 0.825rem; font-weight: 600;
        text-decoration: none;
        background: var(--p); color: #fff; border: 1px solid var(--p-h);
        transition: all 0.18s ease;
    }
    .btn-back-action:hover { background: var(--p-h); color: #fff; transform: translateY(-1px); box-shadow: 0 3px 10px rgba(79,70,229,0.28); }

    /* ── COPY BUTTON ── */
    .btn-copy {
        display: inline-flex; align-items: center; gap: 0.35rem;
        padding: 0.25rem 0.65rem; font-size: 0.72rem; font-weight: 500;
        background: #f3f4f6; border: 1px solid var(--bd); color: #374151;
        border-radius: 5px; cursor: pointer; transition: all 0.18s;
    }
    .btn-copy:hover { background: var(--p-xlt); border-color: var(--p-lt); color: var(--p); }
</style>

<div class="container-fluid py-4 px-4">

    <!-- ── PAGE HEADER ── -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-start mb-4 gap-3">
        <div>
            <h4 class="fw-bold mb-1" style="color:var(--tx); letter-spacing:-0.3px;">Detail Log Aktivitas</h4>
            <p class="mb-0 text-muted" style="font-size:0.875rem;">Informasi lengkap satu entri log sistem &mdash; ID #<?= str_pad($log['id'], 6, '0', STR_PAD_LEFT) ?></p>
        </div>
        <div class="flex-shrink-0">
            <a href="<?= htmlspecialchars($back_url) ?>" class="btn-back-action">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
        </div>
    </div>

    <!-- ── HERO STRIP ── -->
    <div class="log-hero">
        <div>
            <span class="log-id-tag"><i class="fas fa-fingerprint"></i> LOG-<?= str_pad($log['id'], 6, '0', STR_PAD_LEFT) ?></span>
        </div>
        <div class="d-flex flex-wrap gap-2 mb-2">
            <span class="log-badge <?= $badge['cls'] ?>"><i class="fas <?= $badge['icon'] ?> me-1"></i><?= htmlspecialchars($log['aksi']) ?></span>
            <span class="fitur-tag"><?= htmlspecialchars($log['fitur']) ?></span>
        </div>
        <p class="log-hero-desc"><?= htmlspecialchars($log['deskripsi']) ?></p>
        <div class="log-hero-meta">
            <i class="far fa-calendar-alt"></i>
            <?= date('l, d F Y', strtotime($log['dibuat_pada'])) ?>
            <span class="sep">•</span>
            <i class="far fa-clock"></i>
            <?= date('H:i:s', strtotime($log['dibuat_pada'])) ?> WIB
        </div>
    </div>

    <!-- ── DETAIL GRID ── -->
    <div class="row g-3 mb-3">

        <!-- KOLOM KIRI -->
        <div class="col-lg-6">

            <!-- Informasi Pengguna -->
            <div class="info-card mb-3">
                <div class="info-card-head">
                    <div class="info-card-head-icon"><i class="fas fa-user"></i></div>
                    <h6 class="info-card-head-title">Informasi Pengguna</h6>
                </div>
                <div class="info-card-body">
                    <div class="info-row">
                        <i class="fas fa-id-badge info-row-icon"></i>
                        <span class="info-row-label">Nama Lengkap</span>
                        <span class="info-row-value fw-semibold"><?= htmlspecialchars($log['nama_lengkap']) ?></span>
                    </div>
                    <div class="info-row">
                        <i class="fas fa-at info-row-icon"></i>
                        <span class="info-row-label">Username</span>
                        <span class="info-row-value font-monospace" style="color:var(--p);">@<?= htmlspecialchars($log['username']) ?></span>
                    </div>
                    <div class="info-row">
                        <i class="fas fa-hashtag info-row-icon"></i>
                        <span class="info-row-label">User ID</span>
                        <span class="info-row-value text-muted"><?= $log['user_id'] ?? '<em>Sistem</em>' ?></span>
                    </div>
                </div>
            </div>

            <!-- Informasi Aksi -->
            <div class="info-card">
                <div class="info-card-head">
                    <div class="info-card-head-icon"><i class="fas fa-bolt"></i></div>
                    <h6 class="info-card-head-title">Informasi Aksi</h6>
                </div>
                <div class="info-card-body">
                    <div class="info-row">
                        <i class="fas fa-tag info-row-icon"></i>
                        <span class="info-row-label">Jenis Aksi</span>
                        <span class="info-row-value">
                            <span class="log-badge <?= $badge['cls'] ?>"><i class="fas <?= $badge['icon'] ?> me-1"></i><?= htmlspecialchars($log['aksi']) ?></span>
                        </span>
                    </div>
                    <div class="info-row">
                        <i class="fas fa-cube info-row-icon"></i>
                        <span class="info-row-label">Fitur / Modul</span>
                        <span class="info-row-value"><span class="fitur-tag"><?= htmlspecialchars($log['fitur']) ?></span></span>
                    </div>
                    <div class="info-row">
                        <i class="fas fa-align-left info-row-icon"></i>
                        <span class="info-row-label">Deskripsi</span>
                        <span class="info-row-value"><?= htmlspecialchars($log['deskripsi']) ?></span>
                    </div>
                    <div class="info-row">
                        <i class="far fa-calendar-alt info-row-icon"></i>
                        <span class="info-row-label">Tanggal</span>
                        <span class="info-row-value"><?= date('d F Y', strtotime($log['dibuat_pada'])) ?></span>
                    </div>
                    <div class="info-row">
                        <i class="far fa-clock info-row-icon"></i>
                        <span class="info-row-label">Waktu</span>
                        <span class="info-row-value font-monospace"><?= date('H:i:s', strtotime($log['dibuat_pada'])) ?> <span class="text-muted">WIB</span></span>
                    </div>
                </div>
            </div>

        </div>

        <!-- KOLOM KANAN -->
        <div class="col-lg-6">

            <!-- Informasi Jaringan -->
            <div class="info-card mb-3">
                <div class="info-card-head">
                    <div class="info-card-head-icon"><i class="fas fa-network-wired"></i></div>
                    <h6 class="info-card-head-title">Informasi Jaringan</h6>
                </div>
                <div class="info-card-body">
                    <div class="info-row">
                        <i class="fas fa-globe info-row-icon"></i>
                        <span class="info-row-label">IP Address</span>
                        <span class="info-row-value">
                            <span class="ip-box">
                                <i class="fas fa-circle-dot" style="font-size:0.65rem;"></i>
                                <?= htmlspecialchars($log['ip_address']) ?>
                            </span>
                            <?php
                            $ip = $log['ip_address'];
                            if ($ip === '::1' || $ip === '127.0.0.1') {
                                echo '<div class="ip-note"><i class="fas fa-info-circle"></i> Loopback (akses dari localhost)</div>';
                            } elseif (str_starts_with($ip, '192.168.') || str_starts_with($ip, '10.') || str_starts_with($ip, '172.')) {
                                echo '<div class="ip-note"><i class="fas fa-home"></i> IP Lokal (jaringan internal)</div>';
                            } else {
                                echo '<div class="ip-note"><i class="fas fa-cloud"></i> IP Publik</div>';
                            }
                            ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Browser & Perangkat -->
            <div class="info-card">
                <div class="info-card-head">
                    <div class="info-card-head-icon"><i class="fas fa-laptop"></i></div>
                    <h6 class="info-card-head-title">Browser &amp; Perangkat</h6>
                </div>
                <div class="info-card-body">
                    <div class="info-row">
                        <i class="fas fa-globe info-row-icon"></i>
                        <span class="info-row-label">Browser</span>
                        <span class="info-row-value"><?= htmlspecialchars($ua_info['browser']) ?></span>
                    </div>
                    <div class="info-row">
                        <i class="fas fa-desktop info-row-icon"></i>
                        <span class="info-row-label">Sistem Operasi</span>
                        <span class="info-row-value"><?= htmlspecialchars($ua_info['os']) ?></span>
                    </div>
                    <div class="info-row" style="flex-direction:column; gap:0.5rem;">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fas fa-code info-row-icon"></i>
                            <span class="info-row-label mb-0">User Agent Raw</span>
                        </div>
                        <div class="ua-box"><?= htmlspecialchars($log['user_agent']) ?></div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- ── DETAIL JSON ── -->
    <?php if ($detail_parsed !== null): ?>
    <div class="info-card mb-4">
        <div class="info-card-head">
            <div class="info-card-head-icon"><i class="fas fa-code"></i></div>
            <h6 class="info-card-head-title">Detail Perubahan Data</h6>
        </div>
        <div style="padding: 1.25rem 1.25rem 1rem;">
            <p class="text-muted mb-3" style="font-size:0.82rem;">Data sebelum dan sesudah perubahan tersimpan di bawah ini.</p>

            <?php
            $has_before  = isset($detail_parsed['sebelum']) || isset($detail_parsed['before']) || isset($detail_parsed['old']);
            $has_after   = isset($detail_parsed['sesudah']) || isset($detail_parsed['after'])  || isset($detail_parsed['new']);
            $before_data = $detail_parsed['sebelum'] ?? $detail_parsed['before'] ?? $detail_parsed['old'] ?? null;
            $after_data  = $detail_parsed['sesudah'] ?? $detail_parsed['after']  ?? $detail_parsed['new']  ?? null;
            ?>

            <?php if ($has_before || $has_after): ?>
            <div class="row g-3 mb-4">
                <?php if ($before_data !== null): ?>
                <div class="col-md-6">
                    <div class="diff-wrap">
                        <div class="diff-head before"><span class="diff-dot before"></span> Sebelum Perubahan</div>
                        <table class="kv-table">
                            <?php foreach ((array)$before_data as $k => $v): ?>
                            <tr>
                                <td><?= htmlspecialchars($k) ?></td>
                                <td><?= htmlspecialchars(is_array($v) ? json_encode($v) : (string)$v) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($after_data !== null): ?>
                <div class="col-md-6">
                    <div class="diff-wrap">
                        <div class="diff-head after"><span class="diff-dot after"></span> Sesudah Perubahan</div>
                        <table class="kv-table">
                            <?php foreach ((array)$after_data as $k => $v): ?>
                            <tr>
                                <td><?= htmlspecialchars($k) ?></td>
                                <td><?= htmlspecialchars(is_array($v) ? json_encode($v) : (string)$v) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Raw JSON -->
            <div class="d-flex align-items-center gap-2 mb-2">
                <span style="font-size:0.72rem; font-weight:700; color:var(--mu); text-transform:uppercase; letter-spacing:0.06em;">Raw JSON</span>
                <button type="button" class="btn-copy" id="btnCopyJson" onclick="copyJson()">
                    <i class="fas fa-copy"></i> Salin
                </button>
            </div>
            <pre class="json-viewer" id="jsonRaw"><?= htmlspecialchars($detail_formatted) ?></pre>
        </div>
    </div>
    <?php endif; ?>



</div>

<script>
function copyJson() {
    const text = document.getElementById('jsonRaw').textContent;
    navigator.clipboard.writeText(text).then(() => {
        const btn = document.getElementById('btnCopyJson');
        btn.innerHTML = '<i class="fas fa-check"></i> Tersalin!';
        btn.style.background = '#f0fdf4';
        btn.style.borderColor = '#bbf7d0';
        btn.style.color = '#166534';
        setTimeout(() => {
            btn.innerHTML = '<i class="fas fa-copy"></i> Salin';
            btn.style.background = '';
            btn.style.borderColor = '';
            btn.style.color = '';
        }, 2000);
    });
}
</script>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
