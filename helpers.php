<?php
// Pastikan session sudah aktif sebelum file ini dipanggil.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * =================================================================
 * FUNGSI PESAN KILAT (FLASH MESSAGE)
 * =================================================================
 */

/**
 * Menyimpan pesan flash ke dalam session.
 * Pesan ini hanya akan ditampilkan satu kali.
 *
 * @param string $type    Jenis pesan (e.g., 'success', 'danger', 'warning', 'info').
 * Ini akan menentukan warna notifikasinya.
 * @param string $message Isi pesan yang ingin ditampilkan.
 */
function set_flash_message($type, $message)
{
    $_SESSION['flash_message'] = [
        'type'    => $type,
        'message' => $message
    ];
}

/**
 * Menampilkan pesan flash jika ada, lalu menghapusnya dari session.
 * Fungsi ini harus dipanggil di halaman di mana notifikasi ingin ditampilkan
 * (biasanya di bawah header atau di atas konten utama).
 */
function display_flash_message()
{
    if (isset($_SESSION['flash_message'])) {
        $type    = $_SESSION['flash_message']['type'];
        $message = $_SESSION['flash_message']['message'];

        // Hapus pesan dari session agar tidak muncul lagi saat refresh.
        unset($_SESSION['flash_message']);

        // Siapkan ikon berdasarkan tipe notifikasi untuk tampilan lebih ciamik.
        $icon = '';
        switch ($type) {
            case 'success':
                $icon = '<i class="fas fa-check-circle me-2"></i>';
                break;
            case 'danger':
                $icon = '<i class="fas fa-times-circle me-2"></i>';
                break;
            case 'warning':
                $icon = '<i class="fas fa-exclamation-triangle me-2"></i>';
                break;
            case 'info':
                $icon = '<i class="fas fa-info-circle me-2"></i>';
                break;
        }

        // Tampilkan HTML notifikasi menggunakan komponen Alert dari Bootstrap 5.
        // alert-dismissible dan fade show memberikan efek animasi dan tombol close.
        echo "
        <div class='alert alert-{$type} alert-dismissible fade show' role='alert'>
            {$icon} {$message}
            <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
        </div>
        ";
    }
}

/**
 * =================================================================
 * FUNGSI BANTUAN LAINNYA (Bisa ditambahkan di sini)
 * =================================================================
 */

// Contoh fungsi helper lain di masa depan, misalnya untuk format tanggal:
/*
function format_tanggal_indonesia($date) {
    // Logika untuk mengubah format tanggal
    return $formatted_date;
}
*/

?>