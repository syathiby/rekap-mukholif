<?php
/**
 * logout.php — Admin Session Termination & Zero-Trash Browser Cleanup
 * AsuhTrack Admin Panel
 *
 * Menghapus semua jejak sesi di sisi server DAN browser,
 * termasuk Service Worker caches, IndexedDB, cookies, dan storage.
 * Mendukung ?timeout=1 atau ?reason=timeout (auto-logout).
 */

require_once __DIR__ . '/bootstrap/init.php';

// Tangkap reason sebelum session destroyed
$logout_reason = (isset($_GET['timeout']) || ($_GET['reason'] ?? '') === 'timeout') ? 'timeout' : '';
$login_redirect = BASE_URL . '/login.php' . ($logout_reason === 'timeout' ? '?timeout=1' : '');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── 1. Hancurkan session server-side ──────────────────────────────────────
$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    // Set expired di masa lalu untuk semua kemungkinan path
    setcookie(session_name(), '', time() - 86400,
        $params["path"],   $params["domain"],
        $params["secure"], $params["httponly"]
    );
    // Hapus juga di root path, untuk menjamin
    setcookie(session_name(), '', time() - 86400, '/');
}

session_destroy();

// ── 2. Anti-Cache headers (agar halaman ini tidak di-cache) ───────────────
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Keluar — AsuhTrack</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Poppins', sans-serif;
            background: #0f172a;
            /* Gradien gelap dengan aksen hijau emerald yang elegan */
            background-image: 
                radial-gradient(circle at 10% 20%, rgba(22, 163, 74, 0.05) 0%, transparent 40%),
                radial-gradient(circle at 90% 80%, rgba(15, 23, 42, 0.2) 0%, transparent 40%);
            color: #f1f5f9;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        .logout-card {
            background: rgba(30, 41, 59, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-radius: 24px;
            padding: 40px 32px;
            max-width: 380px;
            width: 100%;
            text-align: center;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
        }
        .spinner-wrap {
            width: 64px; height: 64px;
            margin: 0 auto 24px;
            position: relative;
        }
        .spinner {
            width: 100%; height: 100%;
            border-radius: 50%;
            border: 3px solid rgba(22, 163, 74, 0.12);
            border-top-color: #22c55e;
            animation: spin 0.9s linear infinite;
        }
        .spinner-icon {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        h2 { font-size: 1.25rem; font-weight: 700; margin-bottom: 8px; color: #f8fafc; }
        p  { font-size: 0.85rem; color: #94a3b8; line-height: 1.6; }

        .step-list {
            list-style: none;
            margin: 24px 0 0;
            text-align: left;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .step-list li {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.8rem;
            color: #94a3b8;
            padding: 10px 14px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255, 255, 255, 0.03);
            transition: all 0.3s ease;
        }
        .step-list li.done {
            color: #22c55e;
            background: rgba(34, 197, 94, 0.06);
            border-color: rgba(34, 197, 94, 0.12);
        }
        .step-list li.done .step-dot { background: #22c55e; }
        .step-list li.active {
            color: #38bdf8;
            background: rgba(56, 189, 248, 0.06);
            border-color: rgba(56, 189, 248, 0.12);
        }
        .step-list li.active .step-dot { background: #38bdf8; animation: pulse 1s infinite; }
        .step-dot {
            width: 8px; height: 8px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.15);
            flex-shrink: 0;
            transition: background 0.3s;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50%       { opacity: 0.4; transform: scale(0.7); }
        }
    </style>
</head>
<body>
<div class="logout-card" id="logoutCard">
    <div class="spinner-wrap">
        <div class="spinner" id="mainSpinner"></div>
        <div class="spinner-icon">🔒</div>
    </div>
    <h2>Sedang Keluar…</h2>
    <p>Menghapus semua jejak sesi dan data browser</p>

    <ul class="step-list" id="stepList">
        <li id="step0" class="active"><span class="step-dot"></span>Menghapus data sesi</li>
        <li id="step1"><span class="step-dot"></span>Membersihkan penyimpanan lokal</li>
        <li id="step2"><span class="step-dot"></span>Menghapus cache Service Worker</li>
        <li id="step3"><span class="step-dot"></span>Menghapus cookies browser</li>
        <li id="step4"><span class="step-dot"></span>Membersihkan IndexedDB</li>
    </ul>
</div>

<script>
'use strict';

// ─── Helper: Tandai step ──────────────────────────────────────────────────
function markDone(id) {
    const el = document.getElementById(id);
    if (el) { el.className = 'done'; }
}
function markActive(id) {
    const el = document.getElementById(id);
    if (el) { el.className = 'active'; }
}

// ─── Fungsi utama: Zero-Trash Logout ─────────────────────────────────────
async function zeroTrashLogout() {

    // STEP 0: Session storage & local storage
    try {
        sessionStorage.clear();
        localStorage.clear();
    } catch(e) {}
    markDone('step0');
    markActive('step1');
    await sleep(150);

    // STEP 1: Semua storage (localStorage, sessionStorage)
    try {
        if (window.localStorage) {
            const lKeys = Object.keys(localStorage);
            lKeys.forEach(k => localStorage.removeItem(k));
        }
        if (window.sessionStorage) {
            const sKeys = Object.keys(sessionStorage);
            sKeys.forEach(k => sessionStorage.removeItem(k));
        }
    } catch(e) {}
    markDone('step1');
    markActive('step2');
    await sleep(150);

    // STEP 2: Cache API (Service Worker caches) — hapus SEMUA cache names
    try {
        if ('caches' in window) {
            const cacheNames = await caches.keys();
            await Promise.all(
                cacheNames.map(name => caches.delete(name))
            );
        }
    } catch(e) {}

    // Unregister semua service workers
    try {
        if ('serviceWorker' in navigator) {
            const registrations = await navigator.serviceWorker.getRegistrations();
            for (const reg of registrations) {
                if (reg.active) {
                    reg.active.postMessage({ type: 'CLEAR_ALL_CACHES' });
                }
                await reg.unregister();
            }
        }
    } catch(e) {}
    markDone('step2');
    markActive('step3');
    await sleep(150);

    // STEP 3: Cookies browser (semua cookies yang terlihat oleh JS)
    try {
        const cookies = document.cookie.split(';');
        for (const cookie of cookies) {
            const eqPos = cookie.indexOf('=');
            const name = eqPos > -1 ? cookie.substr(0, eqPos).trim() : cookie.trim();
            if (!name) continue;
            const expiry = 'expires=Thu, 01 Jan 1970 00:00:00 GMT';
            document.cookie = `${name}=; ${expiry}; path=/`;
            document.cookie = `${name}=; ${expiry}; path=/admin/`;
            document.cookie = `${name}=; ${expiry}; path=/; domain=${location.hostname}`;
        }
    } catch(e) {}
    markDone('step3');
    markActive('step4');
    await sleep(150);

    // STEP 4: IndexedDB — hapus semua database
    try {
        if (window.indexedDB && indexedDB.databases) {
            const dbs = await indexedDB.databases();
            await Promise.all(
                dbs.map(db => new Promise((resolve) => {
                    const req = indexedDB.deleteDatabase(db.name);
                    req.onsuccess = resolve;
                    req.onerror   = resolve; // lanjut meski error
                    req.onblocked = resolve;
                }))
            );
        }
    } catch(e) {}
    markDone('step4');
    await sleep(200);

    // ─── REDIRECT: Ganti history entry agar tombol Back tidak bisa kembali ───
    window.location.replace('<?php echo addslashes($login_redirect); ?>');
}

function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

// Mulai proses saat halaman siap
window.addEventListener('DOMContentLoaded', zeroTrashLogout);
</script>
</body>
</html>
<?php exit; ?>