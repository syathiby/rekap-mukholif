<?php 
require_once __DIR__ . '/../../bootstrap/init.php';
guard('izin_manage');    
require_once __DIR__ . '/../../layouts/header.php'; 

$loggedInUserId = (int)($_SESSION['user_id'] ?? 0);
$usersResult = $conn->query("SELECT id, nama_lengkap, username FROM users WHERE role != 'admin' AND id != $loggedInUserId ORDER BY nama_lengkap ASC");

$permissions = [];
$permResult = $conn->query("SELECT id, nama_izin, deskripsi, grup FROM permissions ORDER BY grup, nama_izin ASC");
while($row = $permResult->fetch_assoc()) {
    $permissions[$row['grup']][] = $row;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$users = [];
while($u = $usersResult->fetch_assoc()) $users[] = $u;
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

<style>
:root {
    --bg:         #f6f5f3;
    --surface:    #ffffff;
    --border:     #e8e5e1;
    --border-2:   #d4cfc9;
    --text-1:     #1a1917;
    --text-2:     #5c5650;
    --text-3:     #a89f96;
    --accent:     #2563eb;
    --accent-bg:  #eff6ff;
    --accent-dim: #dbeafe;
    --red:        #dc2626;
    --red-bg:     #fef2f2;
    --red-dim:    #fee2e2;
    --green:      #16a34a;
    --green-bg:   #f0fdf4;
    --green-dim:  #dcfce7;
    --font:       'DM Sans', sans-serif;
    --mono:       'JetBrains Mono', monospace;
}

body { background: var(--bg); }

/* ── Wrap ──────────────────────────────────────────────────────── */
.bm {
    max-width: 1200px;
    margin: 0 auto;
    padding: 3rem 2rem 8rem;
    font-family: var(--font);
    color: var(--text-1);
}

/* ── Page Header ───────────────────────────────────────────────── */
.bm-top {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 1.5rem;
    margin-bottom: 3rem;
    padding-bottom: 2rem;
    border-bottom: 1px solid var(--border);
}

.bm-top-left h1 {
    font-size: 2rem;
    font-weight: 600;
    letter-spacing: -.025em;
    color: var(--text-1);
    margin: 0 0 .5rem;
}

.bm-top-left p {
    font-size: 1.1rem;
    color: var(--text-3);
    margin: 0;
}

.btn-back {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    padding: .6rem 1rem;
    font-size: 1rem;
    font-family: var(--font);
    font-weight: 500;
    color: var(--text-2);
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 8px;
    text-decoration: none;
    white-space: nowrap;
    transition: all .15s;
}
.btn-back:hover { border-color: var(--border-2); color: var(--text-1); background: #faf9f7; }
.btn-back svg { opacity: .6; width: 14px; height: 14px; }

/* ── Alert ─────────────────────────────────────────────────────── */
.bm-alert {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1rem 1.25rem;
    border-radius: 12px;
    font-size: 1rem;
    margin-bottom: 2rem;
    border: 1px solid;
}
.bm-alert.success { background: var(--green-bg); border-color: #bbf7d0; color: #15803d; }
.bm-alert.error   { background: var(--red-bg);   border-color: #fecaca; color: #b91c1c; }
.bm-alert .dismiss { margin-left: auto; background: none; border: none; cursor: pointer; opacity: .5; padding: 0; line-height: 1; }
.bm-alert .dismiss:hover { opacity: 1; }
.bm-alert svg { width: 20px; height: 20px; flex-shrink: 0; }
.bm-alert .dismiss svg { width: 14px; height: 14px; }

/* ── Section label ─────────────────────────────────────────────── */
.section-label {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.section-label-num {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: var(--text-1);
    color: #fff;
    font-size: 1rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.section-label-text {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-1);
    letter-spacing: -.015em;
}

.section-label-sub {
    font-size: 1rem;
    color: var(--text-3);
    margin-left: .5rem;
    font-weight: 400;
}

/* ── Section divider ───────────────────────────────────────────── */
.section-divider {
    border: none;
    border-top: 1px solid var(--border);
    margin: 3rem 0;
}

/* ── Utility buttons ───────────────────────────────────────────── */
.btn-sm {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    height: 40px;
    padding: 0 1rem;
    font-size: 1rem;
    font-family: var(--font);
    font-weight: 500;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--surface);
    color: var(--text-2);
    cursor: pointer;
    white-space: nowrap;
    transition: all .15s;
}
.btn-sm:hover { border-color: var(--border-2); background: #faf9f7; color: var(--text-1); }
.btn-sm.danger:hover { border-color: var(--red-dim); background: var(--red-bg); color: var(--red); }
.btn-sm svg { width: 14px; height: 14px; }

/* ── User Picker ───────────────────────────────────────────────── */
.user-toolbar {
    display: flex;
    gap: .75rem;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
    align-items: center;
}

.user-search {
    flex: 1;
    min-width: 220px;
    height: 44px;
    padding: 0 1rem 0 2.75rem;
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 1rem;
    font-family: var(--font);
    color: var(--text-1);
    background: var(--surface) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='%23a89f96' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='11' cy='11' r='8'/%3E%3Cpath d='m21 21-4.35-4.35'/%3E%3C/svg%3E") no-repeat .85rem center;
    outline: none;
    transition: border-color .15s;
}
.user-search::placeholder { color: var(--text-3); }
.user-search:focus { border-color: var(--accent); }

.user-counter-pill {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    padding: .45rem 1rem;
    background: var(--accent-bg);
    border: 1px solid var(--accent-dim);
    border-radius: 99px;
    font-size: 1rem;
    font-weight: 600;
    color: var(--accent);
    transition: all .2s;
    margin-left: auto;
}
.user-counter-pill svg { width: 14px; height: 14px; }

/* User grid — open, no scroll prison */
.user-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: .875rem;
}

@media (max-width: 640px) {
    .user-grid { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 480px) {
    .user-grid { grid-template-columns: 1fr; }
}

.user-chip {
    display: flex;
    align-items: center;
    gap: .875rem;
    padding: .875rem 1rem;
    border: 1.5px solid var(--border);
    border-radius: 12px;
    cursor: pointer;
    background: var(--surface);
    transition: border-color .15s, background .15s, box-shadow .15s;
    user-select: none;
    -webkit-user-select: none;
}
.user-chip:hover {
    border-color: var(--border-2);
    background: #faf9f7;
}
.user-chip.selected {
    border-color: var(--accent);
    background: var(--accent-bg);
    box-shadow: 0 0 0 3px var(--accent-dim);
}
.user-chip.hidden { display: none; }

.user-avatar {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    background: linear-gradient(135deg, #e0e7ff, #c7d2fe);
    color: #4338ca;
    font-size: 1.1rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    letter-spacing: .02em;
    transition: background .15s, color .15s;
}
.user-chip.selected .user-avatar {
    background: linear-gradient(135deg, #3b82f6, #1d4ed8);
    color: #fff;
}

.user-chip-meta { min-width: 0; flex: 1; }
.user-chip-name {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-1);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    transition: color .15s;
    margin-bottom: .15rem;
}
.user-chip.selected .user-chip-name { color: var(--accent); }

.user-chip-uname {
    font-size: .85rem;
    color: var(--text-3);
    font-family: var(--mono);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.user-chip-tick {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    border: 1.5px solid var(--border-2);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    color: transparent;
    transition: all .15s;
}
.user-chip.selected .user-chip-tick {
    background: var(--accent);
    border-color: var(--accent);
    color: #fff;
}
.user-chip-tick svg { width: 12px; height: 12px; }

.hidden-inputs { display: none; }

/* ── Legend ────────────────────────────────────────────────────── */
.legend-row {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: .6rem;
    font-size: 1rem;
    color: var(--text-2);
    font-weight: 500;
}

.ldot {
    width: 12px; height: 12px;
    border-radius: 50%;
    flex-shrink: 0;
}
.ldot.green { background: var(--green); }
.ldot.gray  { background: var(--border-2); }
.ldot.red   { background: var(--red); }

/* ── Permission groups ─────────────────────────────────────────── */
.perm-groups {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(500px, 1fr));
    gap: 0;
}

@media (max-width: 768px) {
    .perm-groups { grid-template-columns: 1fr; }
}

/* group block */
.perm-group {
    padding: 0;
}

.perm-group-label {
    font-size: .9rem;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: var(--text-3);
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: .75rem;
    margin-top: 1rem;
}

.perm-group-label::after {
    content: '';
    flex: 1;
    height: 1px;
    background: var(--border);
}

/* per-row */
.perm-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.5rem;
    padding: 1.25rem 0;
    border-bottom: 1px solid var(--border);
}
.perm-row:last-child { border-bottom: none; }

.perm-info { flex: 1; min-width: 0; }

.perm-desc {
    font-size: 1.1rem;
    font-weight: 500;
    color: var(--text-1);
    margin-bottom: .4rem;
    line-height: 1.4;
}

.perm-key {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    font-size: .85rem;
    font-family: var(--mono);
    color: var(--text-3);
}

/* ── 3-state toggle ────────────────────────────────────────────── */
.tri {
    display: inline-flex;
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 10px;
    overflow: hidden;
    flex-shrink: 0;
}

.tri input[type="radio"] { display: none; }

.tri label {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: .4rem;
    padding: .6rem 1rem;
    font-size: .95rem;
    font-family: var(--font);
    font-weight: 600;
    color: var(--text-3);
    cursor: pointer;
    border-right: 1px solid var(--border);
    transition: background .12s, color .12s;
    white-space: nowrap;
    line-height: 1;
}
.tri label:last-of-type { border-right: none; }
.tri label:hover { background: var(--border); color: var(--text-2); }
.tri label svg { width: 12px; height: 12px; }

.tri input:checked + .lbl-remove { background: var(--red-bg);   color: var(--red);   }
.tri input:checked + .lbl-keep   { background: #f0ede9;         color: var(--text-2);}
.tri input:checked + .lbl-grant  { background: var(--green-bg); color: var(--green); }

/* ── Perm group gutter on 2-col ────────────────────────────────── */
@media (min-width: 769px) {
    .perm-groups { column-gap: 4rem; }
    .perm-group { padding-right: 0; }
}

/* ── Sticky footer ─────────────────────────────────────────────── */
.bm-footer {
    position: fixed;
    bottom: 0; left: 0; right: 0;
    background: rgba(255,255,255,.93);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    border-top: 1px solid var(--border);
    padding: 1.25rem 2.5rem;
    z-index: 999;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.5rem;
}

.footer-meta {
    font-size: 1rem;
    color: var(--text-3);
    line-height: 1.5;
}
.footer-meta strong { color: var(--text-2); font-weight: 600; }

.btn-submit {
    display: inline-flex;
    align-items: center;
    gap: .6rem;
    padding: .875rem 1.75rem;
    background: var(--text-1);
    color: #fff;
    border: none;
    border-radius: 10px;
    font-size: 1.1rem;
    font-family: var(--font);
    font-weight: 600;
    cursor: pointer;
    letter-spacing: -.01em;
    transition: opacity .15s, transform .1s;
}
.btn-submit:hover { opacity: .85; }
.btn-submit:active { transform: scale(.97); }
.btn-submit svg { width: 16px; height: 16px; }

/* ── Responsive logic for Mobile ───────────────────────────────── */
@media (max-width: 640px) {
    .bm { padding: 2rem 1rem 3rem; }
    .bm-top { flex-direction: column; align-items: flex-start; }
    .btn-back { width: 100%; justify-content: center; margin-top: 1rem; }
    .user-toolbar { flex-direction: column; align-items: stretch; }
    .user-search { width: 100%; margin-bottom: .5rem; }
    .user-counter-pill { margin-left: 0; align-self: flex-start; }
    .perm-row { flex-direction: column; align-items: flex-start; gap: 1rem; }
    .tri { width: 100%; }
    .tri label { flex: 1; justify-content: center; }
    .bm-footer { 
        position: static;
        flex-direction: column; 
        padding: 2rem 0 0 0; 
        text-align: center; 
        background: transparent;
        border-top: none;
        backdrop-filter: none;
        -webkit-backdrop-filter: none;
        margin-top: 1.5rem;
    }
    .btn-submit { width: 100%; justify-content: center; padding: 1rem; }
}
</style>

<div class="bm">

    <!-- ── Header ──────────────────────────────────────────────── -->
    <div class="bm-top">
        <div class="bm-top-left">
            <h1>Edit Izin Massal</h1>
            <p>Terapkan atau cabut akses ke beberapa pengguna sekaligus.</p>
        </div>
        <a href="index.php" class="btn-back">
            <svg width="11" height="11" viewBox="0 0 12 12" fill="none"><path d="M7.5 2L3.5 6l4 4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Kembali
        </a>
    </div>

    <!-- ── Alerts ───────────────────────────────────────────────── -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="bm-alert success">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="#16a34a" stroke-width="1.5"/><path d="M5 8l2.5 2.5L11 5.5" stroke="#16a34a" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <span><?= $_SESSION['success_message'] ?></span>
            <button class="dismiss" onclick="this.closest('.bm-alert').remove()">
                <svg width="13" height="13" viewBox="0 0 12 12" fill="none"><path d="M2 2l8 8M10 2L2 10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
            </button>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="bm-alert error">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="#dc2626" stroke-width="1.5"/><path d="M8 5v3.5M8 10.5v.5" stroke="#dc2626" stroke-width="1.5" stroke-linecap="round"/></svg>
            <span><?= $_SESSION['error_message'] ?></span>
            <button class="dismiss" onclick="this.closest('.bm-alert').remove()">
                <svg width="13" height="13" viewBox="0 0 12 12" fill="none"><path d="M2 2l8 8M10 2L2 10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
            </button>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <form action="process_bulk.php" method="POST" id="bulk-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

        <!-- ── Step 1: Pilih User ──────────────────────────────── -->
        <div class="section-label">
            <div class="section-label-num">1</div>
            <span class="section-label-text">Pilih Pengguna <span class="section-label-sub">— klik chip untuk memilih</span></span>
        </div>

        <div class="user-toolbar">
            <input type="text" class="user-search" id="user-search" placeholder="Cari nama atau username…" autocomplete="off">
            <button type="button" class="btn-sm" id="select-all-btn">
                <svg width="12" height="12" viewBox="0 0 12 10" fill="none"><path d="M1 5l3 3 7-7" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                Pilih Semua
            </button>
            <button type="button" class="btn-sm danger" id="clear-all-btn">
                <svg width="11" height="11" viewBox="0 0 12 12" fill="none"><path d="M2 2l8 8M10 2L2 10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                Bersihkan
            </button>
            <div class="user-counter-pill" id="counter-pill">
                <svg width="11" height="11" viewBox="0 0 12 10" fill="none"><path d="M1 5l3 3 7-7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <span id="sel-count">0</span> dipilih
            </div>
        </div>

        <div class="user-grid" id="user-grid">
            <?php foreach($users as $u):
                $initials = '';
                foreach (explode(' ', trim($u['nama_lengkap'])) as $w)
                    $initials .= strtoupper(mb_substr($w, 0, 1));
                $initials = mb_substr($initials, 0, 2);
            ?>
            <div class="user-chip"
                 data-id="<?= $u['id'] ?>"
                 data-name="<?= htmlspecialchars(strtolower($u['nama_lengkap'])) ?>"
                 data-uname="<?= htmlspecialchars(strtolower($u['username'])) ?>"
                 onclick="toggleUser(this)">
                <div class="user-avatar"><?= $initials ?></div>
                <div class="user-chip-meta">
                    <div class="user-chip-name"><?= htmlspecialchars($u['nama_lengkap']) ?></div>
                    <div class="user-chip-uname">@<?= htmlspecialchars($u['username']) ?></div>
                </div>
                <div class="user-chip-tick">
                    <svg width="9" height="9" viewBox="0 0 10 8" fill="none"><path d="M1 4l3 3 5-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="hidden-inputs" id="hidden-inputs"></div>

        <hr class="section-divider">

        <!-- ── Step 2: Atur Izin ─────────────────────────────── -->
        <div class="section-label" style="margin-bottom:1rem">
            <div class="section-label-num">2</div>
            <span class="section-label-text">Atur Perizinan <span class="section-label-sub">— pilih tindakan per izin</span></span>
        </div>

        <div class="legend-row">
            <div class="legend-item"><div class="ldot green"></div> Berikan izin</div>
            <div class="legend-item"><div class="ldot gray"></div> Biarkan (tidak berubah)</div>
            <div class="legend-item"><div class="ldot red"></div> Cabut izin</div>
        </div>

        <div class="perm-groups">
            <?php foreach ($permissions as $grup => $items): ?>
            <div class="perm-group">
                <div class="perm-group-label"><?= htmlspecialchars($grup) ?></div>
                <?php foreach ($items as $perm): ?>
                <div class="perm-row">
                    <div class="perm-info">
                        <div class="perm-desc"><?= htmlspecialchars($perm['deskripsi']) ?></div>
                        <div class="perm-key">
                            <svg width="9" height="9" viewBox="0 0 12 12" fill="none" style="opacity:.5"><path d="M8 1a3 3 0 1 1 0 6 3 3 0 0 1 0-6zM5.5 5.5L1 11" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                            <?= htmlspecialchars($perm['nama_izin']) ?>
                        </div>
                    </div>
                    <div class="tri" role="group">
                        <input type="radio" name="actions[<?= $perm['id'] ?>]" id="rem_<?= $perm['id'] ?>" value="remove" autocomplete="off">
                        <label class="lbl-remove" for="rem_<?= $perm['id'] ?>">
                            <svg width="9" height="9" viewBox="0 0 10 10" fill="none"><path d="M2 2l6 6M8 2L2 8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                            Cabut
                        </label>

                        <input type="radio" name="actions[<?= $perm['id'] ?>]" id="keep_<?= $perm['id'] ?>" value="no_change" autocomplete="off" checked>
                        <label class="lbl-keep" for="keep_<?= $perm['id'] ?>">
                            <svg width="9" height="9" viewBox="0 0 10 4" fill="none"><path d="M1 2h8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                            Biarkan
                        </label>

                        <input type="radio" name="actions[<?= $perm['id'] ?>]" id="add_<?= $perm['id'] ?>" value="add" autocomplete="off">
                        <label class="lbl-grant" for="add_<?= $perm['id'] ?>">
                            <svg width="9" height="9" viewBox="0 0 10 8" fill="none"><path d="M1 4l3 3 5-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            Berikan
                        </label>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- ── Sticky Footer ─────────────────────────────────── -->
        <div class="bm-footer">
            <div class="footer-meta">
                Menerapkan ke <strong id="footer-count">0 pengguna</strong>
            </div>
            <button type="submit" class="btn-submit">
                <svg width="13" height="13" viewBox="0 0 14 12" fill="none"><path d="M1.5 6.5l4 4 7-8" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                Terapkan Perubahan
            </button>
        </div>

    </form>
</div>

<script>
(function () {
    const selected = new Set();

    function sync() {
        const n = selected.size;
        document.getElementById('sel-count').textContent = n;
        document.getElementById('footer-count').textContent = n + ' pengguna';

        const wrap = document.getElementById('hidden-inputs');
        wrap.innerHTML = '';
        selected.forEach(id => {
            const inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = 'user_ids[]';
            inp.value = id;
            wrap.appendChild(inp);
        });
    }

    window.toggleUser = function (chip) {
        const id = chip.dataset.id;
        if (selected.has(id)) { selected.delete(id); chip.classList.remove('selected'); }
        else                  { selected.add(id);    chip.classList.add('selected'); }
        sync();
    };

    document.getElementById('select-all-btn').addEventListener('click', () => {
        document.querySelectorAll('.user-chip:not(.hidden)').forEach(c => {
            selected.add(c.dataset.id); c.classList.add('selected');
        });
        sync();
    });

    document.getElementById('clear-all-btn').addEventListener('click', () => {
        selected.clear();
        document.querySelectorAll('.user-chip').forEach(c => c.classList.remove('selected'));
        sync();
    });

    document.getElementById('user-search').addEventListener('input', function () {
        const q = this.value.toLowerCase().trim();
        document.querySelectorAll('.user-chip').forEach(c => {
            c.classList.toggle('hidden', q && !c.dataset.name.includes(q) && !c.dataset.uname.includes(q));
        });
    });

    document.getElementById('bulk-form').addEventListener('submit', function (e) {
        e.preventDefault();
        
        if (selected.size === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Belum ada pengguna dipilih',
                text: 'Silakan pilih minimal satu pengguna untuk menerapkan perubahan.',
                confirmButtonColor: '#1a1917'
            });
            return;
        }
        const hasChange = [...document.querySelectorAll('input[type="radio"]:checked')]
            .some(r => r.value !== 'no_change');
        if (!hasChange) {
            Swal.fire({
                icon: 'info',
                title: 'Tidak ada tindakan',
                text: 'Semua izin masih diatur ke "Biarkan". Anda harus menentukan minimal satu tindakan (Berikan/Cabut).',
                confirmButtonColor: '#1a1917'
            });
            return;
        }
        
        Swal.fire({
            title: 'Terapkan Perubahan?',
            text: `Anda akan menerapkan pengaturan izin ini ke ${selected.size} pengguna terpilih.`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#1a1917',
            cancelButtonColor: '#a89f96',
            confirmButtonText: 'Ya, Terapkan!',
            cancelButtonText: 'Batal',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                this.submit();
            }
        });
    });

    sync();
})();
</script>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>