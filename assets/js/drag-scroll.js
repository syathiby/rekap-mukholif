/**
 * AsuhTrack - Global Drag-to-Scroll & Smooth Horizontal Scroll
 * Fitur pintar untuk memudahkan navigasi horizontal pada tabel/kontainer yang melebar di Laptop & Desktop.
 * Fitur:
 * 1. Tahan klik kiri mouse & geser untuk scroll horizontal (Grab-to-Scroll).
 * 2. Fisika momentum / inersia yang halus saat mouse dilepas.
 * 3. Proteksi klik cerdas (mencegah tombol/link terpencet tanpa sengaja saat men-drag).
 * 4. Mendukung konversi roda scroll mouse vertikal ke horizontal pada area tabel.
 * 5. Kursor grab & grabbing otomatis pada area yang memiliki overflow horizontal.
 * 6. Kompatibel dengan konten dinamis (AJAX, Modal, Tab, Filter).
 */

(function () {
    'use strict';

    // Selektor umum kontainer horizontal yang sering digunakan di AsuhTrack
    const COMMON_SELECTORS = [
        '.table-responsive',
        '.table-responsive-custom',
        '.table-container',
        '.chart-scroll-outer',
        '.overflow-x-auto',
        '.overflow-auto',
        '.nav-tabs',
        '.nav-pills',
        '[data-drag-scroll]'
    ].join(', ');

    // State Tracking
    let activeContainer = null;
    let isMouseDown = false;
    let isDragging = false;
    let startX = 0;
    let startY = 0;
    let initialScrollLeft = 0;
    let lastX = 0;
    let lastTime = 0;
    let velocityX = 0;
    let momentumAnimId = null;
    let preventClickUntil = 0;

    /**
     * Mencari kontainer scrollable terdekat dari target elemen
     */
    function findScrollableContainer(target) {
        if (!target || target === document.body || target === document.documentElement) {
            return null;
        }

        // Jangan aktifkan drag jika pengguna mengklik elemen input, form, popup, dropdown, atau datepicker
        if (target.closest('input, textarea, select, option, [contenteditable="true"], .swal2-container, .flatpickr-calendar, .select2-dropdown, [data-no-drag]')) {
            return null;
        }

        // Cek dulu dari selektor umum
        const commonMatch = target.closest(COMMON_SELECTORS);
        if (commonMatch && isHorizontallyScrollable(commonMatch)) {
            return commonMatch;
        }

        // Jika tidak kena selektor umum, telusuri parent yang memiliki overflow horizontal
        let current = target;
        while (current && current !== document.body && current !== document.documentElement) {
            if (isHorizontallyScrollable(current)) {
                return current;
            }
            current = current.parentElement;
        }

        return null;
    }

    /**
     * Memeriksa apakah suatu elemen memiliki scroll horizontal aktif
     */
    function isHorizontallyScrollable(el) {
        if (!el || typeof el.getBoundingClientRect !== 'function') return false;
        // Berikan toleransi 2px untuk subpixel rendering
        if (el.scrollWidth <= el.clientWidth + 2) return false;

        // Jika memiliki atribut data-drag-scroll eksplisit
        if (el.hasAttribute('data-drag-scroll')) return true;

        const style = window.getComputedStyle(el);
        const overflowX = style.overflowX;
        // overflowX WAJIB 'auto' atau 'scroll' (bukan 'hidden' agar container layout pemotong tidak ikut tergeser)
        return overflowX === 'auto' || overflowX === 'scroll';
    }

    /**
     * Memperbarui kelas penanda visual (cursor grab) pada kontainer yang bisa di-scroll
     */
    function updateScrollableClass(el) {
        if (!el) return;
        if (isHorizontallyScrollable(el)) {
            if (!el.classList.contains('drag-scroll-active')) {
                el.classList.add('drag-scroll-active');
            }
        } else {
            el.classList.remove('drag-scroll-active');
        }
    }

    /**
     * Menghentikan animasi momentum yang sedang berjalan
     */
    function stopMomentum() {
        if (momentumAnimId) {
            cancelAnimationFrame(momentumAnimId);
            momentumAnimId = null;
        }
        velocityX = 0;
    }

    /**
     * Menjalankan animasi inersia / luncuran halus setelah mouse dilepas
     */
    function startMomentum(container) {
        if (!container || Math.abs(velocityX) < 0.15) {
            velocityX = 0;
            return;
        }

        stopMomentum();

        let currentVelocity = velocityX;
        const friction = 0.92; // Koefisien gesekan (makin dekat 1 makin licin)

        function step() {
            if (!container || Math.abs(currentVelocity) < 0.1) {
                stopMomentum();
                return;
            }

            container.scrollLeft -= currentVelocity;
            currentVelocity *= friction;

            // Batas scroll kiri dan kanan
            if (container.scrollLeft <= 0 || container.scrollLeft >= (container.scrollWidth - container.clientWidth)) {
                stopMomentum();
                return;
            }

            momentumAnimId = requestAnimationFrame(step);
        }

        momentumAnimId = requestAnimationFrame(step);
    }

    // ==========================================
    // EVENT LISTENERS
    // ==========================================

    /**
     * Mousedown Handler
     */
    function onMouseDown(e) {
        // Hanya tanggapi klik kiri (button 0)
        if (e.button !== 0) return;

        stopMomentum();

        const container = findScrollableContainer(e.target);
        if (!container) return;

        isMouseDown = true;
        isDragging = false;
        activeContainer = container;
        startX = e.pageX;
        startY = e.pageY;
        initialScrollLeft = container.scrollLeft;
        lastX = e.pageX;
        lastTime = performance.now();
        velocityX = 0;
    }

    /**
     * Mousemove Handler
     */
    function onMouseMove(e) {
        if (!isMouseDown || !activeContainer) return;

        const deltaX = e.pageX - startX;
        const deltaY = e.pageY - startY;

        // Cek apakah sudah melewati ambang batas gerakan (threshold 5px)
        if (!isDragging) {
            if (Math.abs(deltaX) > 5 && Math.abs(deltaX) >= Math.abs(deltaY)) {
                isDragging = true;
                document.body.classList.add('is-drag-scrolling');
                activeContainer.classList.add('is-dragging');
            } else if (Math.abs(deltaY) > 8 && Math.abs(deltaY) > Math.abs(deltaX)) {
                // Pengguna berniat scroll halaman secara vertikal, batalkan drag-scroll
                isMouseDown = false;
                return;
            }
        }

        if (isDragging) {
            // Cegah pemilihan teks browser & ghost image drag bawaan
            e.preventDefault();

            activeContainer.scrollLeft = initialScrollLeft - deltaX;

            // Hitung kecepatan untuk efek momentum
            const now = performance.now();
            const dt = now - lastTime;
            if (dt > 0) {
                const dx = e.pageX - lastX;
                // Exponential moving average untuk velocity yang mulus
                const instantV = (dx / dt) * 14;
                velocityX = velocityX * 0.4 + instantV * 0.6;
                lastX = e.pageX;
                lastTime = now;
            }
        }
    }

    /**
     * Mouseup Handler
     */
    function onMouseUp(e) {
        if (!isMouseDown) return;

        const targetContainer = activeContainer;

        if (isDragging) {
            document.body.classList.remove('is-drag-scrolling');
            if (targetContainer) {
                targetContainer.classList.remove('is-dragging');
            }

            // Kunci klik beberapa milidetik setelah drag untuk mencegah link/tombol terpencet
            preventClickUntil = Date.now() + 150;

            // Jika pengguna berhenti bergerak sebelum melepas mouse (> 80ms), nolkan velocity agar tidak terlontar
            if (performance.now() - lastTime > 80) {
                velocityX = 0;
            }

            // Jalankan inersia momentum jika kecepatan cukup tinggi
            if (targetContainer) {
                startMomentum(targetContainer);
            }
        }

        isMouseDown = false;
        isDragging = false;
        activeContainer = null;
    }

    /**
     * Click Capture: Membatalkan klik jika pengguna baru saja selesai drag-scroll
     */
    function onClickCapture(e) {
        if (Date.now() < preventClickUntil) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            preventClickUntil = 0;
            return false;
        }
    }

    /**
     * Mouseover: Update kursor grab secara dinamis saat mouse melintas
     */
    function onMouseOver(e) {
        if (isMouseDown) return;
        const container = findScrollableContainer(e.target);
        if (container) {
            updateScrollableClass(container);
        }
    }

    /**
     * Scan seluruh halaman dan tandai semua elemen scrollable
     */
    function scanAllScrollables() {
        const elements = document.querySelectorAll(COMMON_SELECTORS);
        elements.forEach(updateScrollableClass);
    }

    /**
     * Inisialisasi Utama
     */
    function init() {
        // Pasang event listener global dengan event delegation
        document.addEventListener('mousedown', onMouseDown, { passive: false });
        window.addEventListener('mousemove', onMouseMove, { passive: false });
        window.addEventListener('mouseup', onMouseUp, { passive: true });
        window.addEventListener('blur', onMouseUp, { passive: true });
        document.addEventListener('click', onClickCapture, { capture: true });
        document.addEventListener('mouseover', onMouseOver, { passive: true });

        // Scan awal setelah DOM siap
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', scanAllScrollables);
        } else {
            scanAllScrollables();
        }

        // Perbarui saat window resize
        let resizeTimeout;
        window.addEventListener('resize', function () {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(scanAllScrollables, 100);
        }, { passive: true });

        // MutationObserver untuk memantau konten yang dimuat secara dinamis (AJAX / filter / modal)
        if (window.MutationObserver) {
            const observer = new MutationObserver(function (mutations) {
                let shouldScan = false;
                for (let i = 0; i < mutations.length; i++) {
                    if (mutations[i].addedNodes && mutations[i].addedNodes.length > 0) {
                        shouldScan = true;
                        break;
                    }
                }
                if (shouldScan) {
                    scanAllScrollables();
                }
            });

            if (document.body) {
                observer.observe(document.body, { childList: true, subtree: true });
            } else {
                document.addEventListener('DOMContentLoaded', function () {
                    observer.observe(document.body, { childList: true, subtree: true });
                });
            }
        }
    }

    // Eksekusi inisialisasi
    init();
})();
