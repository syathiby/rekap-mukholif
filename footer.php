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

<!-- ======================================================= -->
<!-- === ✅ KUNCI YANG HILANG ADA DI SINI ✅ === -->
<!-- ======================================================= -->
<!-- Panggil jQuery DULU, baru yang lain -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Baru panggil Bootstrap -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Script untuk jam live -->
<script>
    function updateLiveTime() {
        const timeEl = document.getElementById('live-time');
        if (timeEl) {
            const now = new Date();
            timeEl.textContent = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        }
    }
    // Cek dulu apakah elemennya ada sebelum menjalankan interval
    if (document.getElementById('live-time')) {
        setInterval(updateLiveTime, 1000);
        updateLiveTime();
    }
</script>

<!-- Script Notifikasi (kode lu udah bagus, gw pake lagi) -->
<script>
$(document).ready(function() {

    function fetchNotifications() {
        $.ajax({
            url: 'get_notifications.php', 
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                // Update angka di lonceng
                if (response && response.count > 0) {
                    $('#notification-count').text(response.count).show();
                } else {
                    $('#notification-count').hide();
                }

                // Update isi dropdown
                var notifList = $('#notification-list');
                notifList.empty(); 

                if (response && response.notifications && response.notifications.length > 0) {
                    response.notifications.forEach(function(notif) {
                        const timeAgo = new Date(notif.created_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });

                        var notifItem = `
                            <li>
                                <a href="${notif.link || '#'}" class="dropdown-item d-flex align-items-start py-2">
                                    <i class="fas fa-exclamation-triangle text-warning me-3 mt-1"></i> 
                                    <div class="text-wrap">
                                        <p class="mb-0" style="font-size: 0.9em;">${notif.pesan}</p>
                                        <small class="text-muted">${timeAgo}</small>
                                    </div>
                                </a>
                            </li>
                        `;
                        notifList.append(notifItem);
                    });
                } else {
                    notifList.html('<li class="px-3 py-2 text-center text-muted">Tidak ada notifikasi baru</li>');
                }
            },
            error: function() {
                $('#notification-list').html('<li class="px-3 py-2 text-center text-danger">Gagal memuat notifikasi</li>');
            }
        });
    }

    // Panggil fungsi pertama kali
    fetchNotifications();

    // Panggil fungsi setiap 20 detik
    setInterval(fetchNotifications, 20000); 

    // Tandai sudah dibaca saat dropdown dibuka
    $('#notification-icon').on('click', function() {
        setTimeout(function() {
            var currentCount = parseInt($('#notification-count').text());
            if (currentCount > 0) {
                $.post('mark_notifications_read.php', function(response) {
                    // Cukup hilangkan angkanya saja, tidak perlu cek response
                    $('#notification-count').fadeOut('slow');
                });
            }
        }, 1500); // Tunda 1.5 detik
    });
});
</script>

</body>
</html>