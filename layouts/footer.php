</main>

<?php
// Deteksi path aktif untuk Bottom Nav
$project_path = defined('BASE_URL') ? parse_url(BASE_URL, PHP_URL_PATH) : '';
$req_path = str_replace($project_path, '', parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$req_path = '/' . ltrim($req_path, '/');

// Cek izin untuk menu bottom nav
$can_input_violation = false;
$violation_url = BASE_URL . '/pelanggaran';
if (function_exists('has_permission')) {
    $can_input_violation = has_permission([
        'pelanggaran_bahasa_input', 
        'pelanggaran_diniyyah_input', 
        'pelanggaran_kesantrian_input', 
        'pelanggaran_pengabdian_input', 
        'pelanggaran_tahfidz_input'
    ]);
    
    if ($can_input_violation) {
        $permissions_list = [
            'pelanggaran_bahasa_input' => '/pelanggaran/bahasa/create.php',
            'pelanggaran_diniyyah_input' => '/pelanggaran/diniyyah/create.php',
            'pelanggaran_kesantrian_input' => '/pelanggaran/kesantrian/create.php',
            'pelanggaran_pengabdian_input' => '/pelanggaran/pengabdian/create.php',
            'pelanggaran_tahfidz_input' => '/pelanggaran/tahfidz/create.php',
        ];
        
        // Admin has all permissions, but should go to the main landing page to select
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            $allowed_paths = [];
            foreach ($permissions_list as $perm => $path) {
                if (has_permission($perm)) {
                    $allowed_paths[] = BASE_URL . $path;
                }
            }
            if (count($allowed_paths) === 1) {
                $violation_url = $allowed_paths[0];
            }
        }
    }
}

$can_view_rekap = false;
if (function_exists('has_permission')) {
    $can_view_rekap = has_permission([
        'rekap_view_umum', 
        'rekap_view_per_kamar', 
        'rekap_view_santri', 
        'rekap_view_statistik', 
        'rekap_view_tahfidz'
    ]);
}
?>

<!-- --- Bottom Navigation Bar (Mobile Only) --- -->
<nav class="bottom-nav">
    <div class="bottom-nav-item">
        <a class="bottom-nav-link <?= ($req_path === '/' || strpos($req_path, '/index.php') !== false) ? 'active' : '' ?>" href="<?= BASE_URL ?>/index.php">
            <i class="fas fa-home"></i>
            <span>Beranda</span>
        </a>
    </div>
    <?php if ($can_input_violation): ?>
    <div class="bottom-nav-item">
        <a class="bottom-nav-link <?= strpos($req_path, '/pelanggaran') === 0 ? 'active' : '' ?>" href="<?= $violation_url ?>">
            <i class="fas fa-clipboard-list"></i>
            <span>Catat</span>
        </a>
    </div>
    <?php endif; ?>
    <?php if ($can_view_rekap): ?>
    <div class="bottom-nav-item">
        <a class="bottom-nav-link <?= strpos($req_path, '/rekap') === 0 ? 'active' : '' ?>" href="<?= BASE_URL ?>/rekap">
            <i class="fas fa-chart-bar"></i>
            <span>Rekap</span>
        </a>
    </div>
    <?php endif; ?>
    <div class="bottom-nav-item">
        <button class="bottom-nav-link" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas">
            <i class="fas fa-bars"></i>
            <span>Lainnya</span>
        </button>
    </div>
</nav>

<!-- --- Floating Action Button (Mobile Only) --- -->
<?php if ($can_input_violation): ?>
    <a href="<?= $violation_url ?>" class="fab-btn" title="Catat Pelanggaran">
        <i class="fas fa-plus"></i>
    </a>
<?php endif; ?>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    // --- SPA DOMContentLoaded & Load Event Shim ---
    (function() {
        const originalAddEventListener = document.addEventListener;
        document.addEventListener = function(type, listener, options) {
            if (type === 'DOMContentLoaded' && document.readyState !== 'loading') {
                setTimeout(listener, 1);
            } else {
                originalAddEventListener.call(document, type, listener, options);
            }
        };

        const originalWindowAddEventListener = window.addEventListener;
        window.addEventListener = function(type, listener, options) {
            if ((type === 'DOMContentLoaded' || type === 'load') && document.readyState === 'complete') {
                setTimeout(listener, 1);
            } else {
                originalWindowAddEventListener.call(window, type, listener, options);
            }
        };
    })();

    function updateLiveTime() {
        const timeEl = document.getElementById('live-time');
        if (timeEl) {
            const now = new Date();
            timeEl.textContent = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        }
    }
    if (document.getElementById('live-time')) {
        setInterval(updateLiveTime, 1000);
        updateLiveTime();
    }

    // --- PJAX SPA ROUTER & LOADER ENGINE ---
    $(document).ready(function() {
        // Dynamically insert progress bar if not exists
        if (!$('#pjax-progress-bar').length) {
            $('body').append('<div id="pjax-progress-bar"></div>');
        }

        // Track loaded external script URLs to prevent duplicate loads
        const loadedScriptUrls = new Set();
        $('script[src]').each(function() {
            try {
                const absUrl = new URL(this.src, window.location.href).href;
                loadedScriptUrls.add(absUrl);
            } catch (e) {}
        });

        // Helper to check if script is a core library to prevent reloading it
        function isCoreLibrary(src) {
            if (!src) return false;
            src = src.toLowerCase();
            if (src.includes('jquery') && !src.includes('jquery-ui') && !src.includes('jquery.ui')) {
                return true;
            }
            if (src.includes('bootstrap') || src.includes('select2') || src.includes('chart.js')) {
                return true;
            }
            return false;
        }

        // Intercept clicks on links
        $(document).on('click', 'a', function(e) {
            const href = $(this).attr('href');
            if (!href) return;

            // Skip hash, javascript, external links, downloads, and target="_blank"
            if (href.startsWith('#') || 
                href.startsWith('javascript:') || 
                href.startsWith('mailto:') || 
                href.startsWith('tel:') || 
                $(this).attr('target') === '_blank' || 
                $(this).hasClass('no-pjax') || 
                $(this).closest('.no-pjax').length > 0) {
                return;
            }

            try {
                const url = new URL(this.href, window.location.origin);
                if (url.origin !== window.location.origin) return;

                const path = url.pathname;
                if (path.endsWith('.pdf') || path.endsWith('.zip') || path.endsWith('.xlsx') || path.indexOf('download.php') !== -1 || path.indexOf('logout.php') !== -1) {
                    return;
                }
            } catch (err) {
                return;
            }

            e.preventDefault();
            loadPage(this.href);
        });

        // Handle back/forward navigation
        window.addEventListener('popstate', function() {
            loadPage(window.location.href, false);
        });

        function loadPage(url, push = true) {
            const bar = $('#pjax-progress-bar');
            bar.addClass('loading').css('width', '10%');

            let width = 10;
            const progressInterval = setInterval(function() {
                if (width < 90) {
                    width += (90 - width) * 0.15;
                    bar.css('width', width + '%');
                }
            }, 150);

            // Fade out current main content slightly to hide swap and signal loading
            $('main.main-content').animate({ opacity: 0.3 }, 150);

            $.ajax({
                url: url,
                method: 'GET',
                success: function(response) {
                    clearInterval(progressInterval);
                    bar.css('width', '100%');

                    const parser = new DOMParser();
                    const doc = parser.parseFromString(response, 'text/html');

                    const newMain = doc.querySelector('main.main-content');
                    if (!newMain) {
                        window.location.href = url;
                        return;
                    }

                    // Update Title
                    const newTitle = doc.querySelector('title') ? doc.querySelector('title').innerText : '';
                    if (newTitle) {
                        document.title = newTitle;
                    }

                    // Swap Content (remains faded at 0.3 opacity)
                    $('main.main-content').html(newMain.innerHTML);

                    // Update active nav links
                    const newNav = doc.querySelector('.bottom-nav');
                    if (newNav) {
                        $('.bottom-nav').html(newNav.innerHTML);
                    }
                    const newSidebar = doc.querySelector('.sidebar');
                    if (newSidebar) {
                        $('.sidebar').html(newSidebar.innerHTML);
                    }
                    const newOffcanvasBody = doc.querySelector('#sidebarOffcanvas .offcanvas-body');
                    if (newOffcanvasBody) {
                        $('#sidebarOffcanvas .offcanvas-body').html(newOffcanvasBody.innerHTML);
                    }

                    // Update FAB button dynamically
                    const newFab = doc.querySelector('.fab-btn');
                    $('.fab-btn').remove();
                    if (newFab) {
                        $('body').append(newFab.outerHTML);
                    }

                    if (push) {
                        window.history.pushState(null, newTitle, url);
                    }

                    window.scrollTo(0, 0);

                    // Hide Offcanvas if open
                    const offcanvasEl = document.getElementById('sidebarOffcanvas');
                    if (offcanvasEl) {
                        const bsOffcanvas = bootstrap.Offcanvas.getInstance(offcanvasEl);
                        if (bsOffcanvas) {
                            bsOffcanvas.hide();
                        }
                    }

                    // Extract and execute script tags sequentially to prevent race conditions
                    const scripts = Array.from(doc.querySelectorAll('script'));
                    let scriptIndex = 0;
                    
                    function executeNextScript() {
                        if (scriptIndex >= scripts.length) {
                            // Reinitialize components after all scripts have loaded
                            if (document.getElementById('live-time')) {
                                updateLiveTime();
                            }
                            // Fade the content back in smoothly now that all elements/scripts are initialized
                            $('main.main-content').animate({ opacity: 1 }, 200);
                            return;
                        }
                        
                        const script = scripts[scriptIndex++];
                        const src = script.getAttribute('src');
                        
                        if (isCoreLibrary(src)) {
                            executeNextScript();
                            return;
                        }
                        
                        if (src) {
                            try {
                                const absUrl = new URL(src, window.location.href).href;
                                if (loadedScriptUrls.has(absUrl)) {
                                    executeNextScript();
                                    return;
                                }
                                loadedScriptUrls.add(absUrl);
                                
                                const newScript = document.createElement('script');
                                newScript.src = absUrl;
                                newScript.onload = function() {
                                    executeNextScript();
                                };
                                newScript.onerror = function() {
                                    executeNextScript();
                                };
                                document.body.appendChild(newScript);
                            } catch (e) {
                                executeNextScript();
                            }
                        } else {
                            // Skip the PJAX engine script itself to prevent infinite listener accumulation and lag
                            if (script.textContent.includes('PJAX SPA ROUTER') || script.textContent.includes('SPA DOMContentLoaded')) {
                                executeNextScript();
                                return;
                            }
                            
                            // Inline script
                            const newScript = document.createElement('script');
                            newScript.textContent = script.textContent;
                            document.body.appendChild(newScript);
                            newScript.remove();
                            executeNextScript();
                        }
                    }
                    
                    executeNextScript();
                },
                error: function() {
                    clearInterval(progressInterval);
                    bar.css('width', '100%');
                    window.location.href = url;
                },
                complete: function() {
                    setTimeout(function() {
                        bar.removeClass('loading').css('width', '0%');
                    }, 250);
                }
            });
        }
    });
</script>

</body>
</html>