<?php
// helpers.php — Fungsi serbaguna: flash message, format data, dll.
// SESSION dijamin sudah aktif oleh bootstrap/init.php sebelum file ini dipanggil.

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
function set_flash_message($message, $type = 'success')
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
    $has_message = false;
    $type = '';
    $message = '';

    if (isset($_SESSION['flash_message'])) {
        $type    = $_SESSION['flash_message']['type'];
        $message = $_SESSION['flash_message']['message'];
        unset($_SESSION['flash_message']);
        $has_message = true;
    } elseif (isset($_SESSION['message'])) {
        // Tangkap juga format $_SESSION['message'] yang lama
        $type    = $_SESSION['message']['type'];
        $message = isset($_SESSION['message']['text']) ? $_SESSION['message']['text'] : (isset($_SESSION['message']['message']) ? $_SESSION['message']['message'] : '');
        unset($_SESSION['message']);
        if (!empty($message)) {
            $has_message = true;
        }
    }

    if ($has_message) {
        $swal_icon = $type;
        if ($type === 'danger') {
            $swal_icon = 'error';
        }

        // Tentukan judul berdasarkan ikon (EYD yang baik)
        $title = 'Informasi';
        if ($swal_icon === 'success') {
            $title = 'Berhasil!';
        } elseif ($swal_icon === 'error') {
            $title = 'Oops...';
        } elseif ($swal_icon === 'warning') {
            $title = 'Peringatan!';
        }
        
        $message_js = addslashes($message);
        $title_js = addslashes($title);

        echo "
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof Swal !== 'undefined') {
                const Toast = Swal.mixin({
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 4000,
                    timerProgressBar: true,
                    didOpen: (toast) => {
                        toast.onmouseenter = Swal.stopTimer;
                        toast.onmouseleave = Swal.resumeTimer;
                    }
                });
                Toast.fire({
                    icon: '{$swal_icon}',
                    title: '{$title_js}',
                    text: '{$message_js}'
                });
            }
        });
        </script>
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