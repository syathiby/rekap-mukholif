<!-- KONTEN HALAMAN SELESAI DI SINI -->
</main> <!-- Penutup .main-content -->

<!-- Toast Notifications -->
<div class="toast-container position-fixed bottom-0 end-0 p-3">
    <?php if ($success_message): ?>
    <div class="toast show" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header bg-success text-white">
            <strong class="me-auto">Sukses</strong>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body"><?= htmlspecialchars($success_message) ?></div>
    </div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
    <div class="toast show" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header bg-danger text-white">
            <strong class="me-auto">Error</strong>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body"><?= htmlspecialchars($error_message) ?></div>
    </div>
    <?php endif; ?>
</div>

<!-- JavaScript Bootstrap (WAJIB ADA DI AKHIR) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Script untuk jam live (Jika ada) -->
<script>
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
</script>

<!-- ⛔️ SEMUA SCRIPT NOTIFIKASI DIHAPUS ⛔️ -->

</body>
</html>