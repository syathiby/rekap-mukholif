<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../auth.php';
guard('pelanggaran_pengabdian_input');

// =======================================================
// === 🚀 FUNGSI BANTU UNTUK KIRIM NOTIFIKASI 🚀 ===
// =======================================================
/**
 * Mengirim notifikasi ke semua user yang memiliki izin DAN ke semua admin.
 * @param mysqli $conn Koneksi database
 * @param string $pesan Isi pesan notifikasi
 * @param string $link URL tujuan jika notifikasi diklik
 */
function kirim_notifikasi_pelanggaran($conn, $pesan, $link) {
    // ✅ PERUBAHAN DI SINI: Query di-upgrade untuk otomatis mencakup semua admin
    $query_penerima = "
        -- Ambil user yang punya izin spesifik
        SELECT up.user_id 
        FROM user_permissions up
        JOIN permissions p ON up.permission_id = p.id
        WHERE p.nama_izin = 'terima_notif_pelanggaran'
        
        UNION -- Gabungkan (tanpa duplikat) dengan...
        
        -- Ambil SEMUA user yang rolenya 'admin'
        SELECT id 
        FROM users 
        WHERE role = 'admin'
    ";
    $result_penerima = mysqli_query($conn, $query_penerima);
    
    if ($result_penerima && mysqli_num_rows($result_penerima) > 0) {
        $penerima_ids = [];
        while ($row = mysqli_fetch_assoc($result_penerima)) {
            $penerima_ids[] = $row['user_id'];
        }

        // 2. Masukkan notifikasi untuk setiap penerima
        if (!empty($penerima_ids)) {
            $query_insert_notif = "INSERT INTO notifikasi (user_id, pesan, link) VALUES (?, ?, ?)";
            $stmt_insert_notif = mysqli_prepare($conn, $query_insert_notif);
            
            foreach ($penerima_ids as $penerima_id) {
                mysqli_stmt_bind_param($stmt_insert_notif, "iss", $penerima_id, $pesan, $link);
                mysqli_stmt_execute($stmt_insert_notif);
            }
            mysqli_stmt_close($stmt_insert_notif);
        }
    }
}


// Pastikan form disubmit dengan benar
if (isset($_POST['submit_pelanggaran'])) {

    $tipe_pelanggaran = $_POST['tipe_pelanggaran'] ?? '';
    $dicatat_oleh = $_SESSION['user_id'];

    // =======================================================
    // === CABANG LOGIKA 1: JIKA TIPE PELANGGARAN INDIVIDU ===
    // =======================================================
    if ($tipe_pelanggaran === 'individu') {

        $santri_ids = $_POST['santri_ids'] ?? [];
        $jenis_pelanggaran_id = (int)($_POST['jenis_pelanggaran_id'] ?? 0);
        $tanggal = $_POST['tanggal_individu'] ?? '';

        // Validasi Awal
        if (empty($santri_ids) || $jenis_pelanggaran_id === 0 || empty($tanggal)) {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Untuk pelanggaran individu, Anda harus memilih jenis pelanggaran, tanggal, dan minimal satu santri.'];
            header("Location: create.php");
            exit();
        }

        // Ambil info Jenis Pelanggaran (Nama & Poin)
        $query_jp = "SELECT nama_pelanggaran, poin FROM jenis_pelanggaran WHERE id = ?";
        $stmt_jp = mysqli_prepare($conn, $query_jp);
        mysqli_stmt_bind_param($stmt_jp, "i", $jenis_pelanggaran_id);
        mysqli_stmt_execute($stmt_jp);
        $result_jp = mysqli_stmt_get_result($stmt_jp);
        $data_pelanggaran = mysqli_fetch_assoc($result_jp);
        mysqli_stmt_close($stmt_jp);

        if (!$data_pelanggaran) {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Jenis pelanggaran tidak valid.'];
            header("Location: create.php");
            exit();
        }
        $poin_to_add = (int)$data_pelanggaran['poin'];
        $nama_pelanggaran = $data_pelanggaran['nama_pelanggaran'];

        // Siapkan Query
        $query_insert = "INSERT INTO pelanggaran (santri_id, jenis_pelanggaran_id, tanggal, dicatat_oleh) VALUES (?, ?, ?, ?)";
        $stmt_insert = mysqli_prepare($conn, $query_insert);
        $query_update = "UPDATE santri SET poin_aktif = poin_aktif + ? WHERE id = ?";
        $stmt_update = mysqli_prepare($conn, $query_update);

        // Mulai Transaksi
        mysqli_begin_transaction($conn);
        $success_count = 0;
        $error_count = 0;
        $error_message = '';
        $last_insert_id = null; // Simpan ID untuk link notifikasi

        foreach ($santri_ids as $santri_id) {
            $santri_id_int = (int)$santri_id;
            
            mysqli_stmt_bind_param($stmt_insert, "iisi", $santri_id_int, $jenis_pelanggaran_id, $tanggal, $dicatat_oleh);
            $exec_insert = mysqli_stmt_execute($stmt_insert);
            if ($exec_insert) {
                // Ambil ID hanya jika insert berhasil
                $last_insert_id = mysqli_insert_id($conn); 
            }
            
            $exec_update = true;
            if ($exec_insert && $poin_to_add > 0) {
                mysqli_stmt_bind_param($stmt_update, "ii", $poin_to_add, $santri_id_int);
                $exec_update = mysqli_stmt_execute($stmt_update);
            }

            if (!($exec_insert && $exec_update)) {
                $error_count++;
                $error_message = mysqli_error($conn);
                mysqli_rollback($conn);
                break;
            }
            $success_count++;
        }

        mysqli_stmt_close($stmt_insert);
        mysqli_stmt_close($stmt_update);

        // Finalisasi
        if ($error_count > 0) {
            $_SESSION['message'] = ['type' => 'danger', 'text' => "Terjadi kesalahan! Proses dibatalkan. Error: " . $error_message];
        } else {
            mysqli_commit($conn);
            $_SESSION['message'] = ['type' => 'success', 'text' => "Berhasil mencatat $success_count pelanggaran individu."];

            // ✨ PANGGIL FUNGSI NOTIFIKASI DENGAN LOGIKA BARU ✨
            $pesan_notif = "";
            $link_notif = ""; // Kosongkan dulu
            
            // ✅ LOGIKA BARU UNTUK LINK ✅
            if (count($santri_ids) == 1 && $last_insert_id) {
                // KASUS 1: HANYA 1 SANTRI, LINK SPESIFIK
                $query_santri = "SELECT nama FROM santri WHERE id = ?";
                $stmt_santri = mysqli_prepare($conn, $query_santri);
                mysqli_stmt_bind_param($stmt_santri, "i", $santri_ids[0]);
                mysqli_stmt_execute($stmt_santri);
                $santri_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_santri));
                $nama_santri = $santri_data['nama'] ?? 'Santri';
                mysqli_stmt_close($stmt_santri);
                
                $pesan_notif = "Pelanggaran '$nama_pelanggaran' oleh $nama_santri.";
                $link_notif = "/rekap/detail-pelanggaran.php?id=" . $last_insert_id;
            } else {
                // KASUS 2: BANYAK SANTRI, LINK UMUM
                $pesan_notif = count($santri_ids) . " santri melakukan pelanggaran '$nama_pelanggaran'.";
                $link_notif = "/rekap/umum.php"; // Arahkan ke halaman rekap umum
            }
            
            kirim_notifikasi_pelanggaran($conn, $pesan_notif, $link_notif);
        }

        header("Location: create.php");
        exit();

    // =======================================================
    // === CABANG LOGIKA 2: JIKA TIPE PELANGGARAN KAMAR ===
    // =======================================================
    } elseif ($tipe_pelanggaran === 'kamar') {

        $kamar_list = $_POST['kamar'] ?? [];
        $catatan_array = $_POST['catatan'] ?? []; 
        $tanggal = $_POST['tanggal_kamar'] ?? '';

        if (empty($kamar_list) || empty($tanggal)) {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Untuk pelanggaran kebersihan, Anda harus memilih tanggal dan minimal satu kamar.'];
            header("Location: create.php");
            exit();
        }

        $query = "INSERT INTO pelanggaran_kebersihan (kamar, tanggal, dicatat_oleh, catatan) VALUES (?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $query);

        $success_count = 0;
        foreach ($kamar_list as $kamar) {
            $catatan_spesifik = trim($catatan_array[$kamar] ?? '');
            mysqli_stmt_bind_param($stmt, "ssis", $kamar, $tanggal, $dicatat_oleh, $catatan_spesifik);
            if (mysqli_stmt_execute($stmt)) {
                $success_count++;
            }
        }
        mysqli_stmt_close($stmt);

        if ($success_count > 0) {
            $_SESSION['message'] = ['type' => 'success', 'text' => "Berhasil mencatat $success_count pelanggaran kebersihan kamar."];

            // ✨ PANGGIL FUNGSI NOTIFIKASI DI SINI ✨
            $kamar_str = implode(', ', $kamar_list);
            $pesan_notif = "Pelanggaran kebersihan baru untuk kamar: " . $kamar_str . ".";
            $link_notif = "/eksekusi/index.php"; // Arahkan ke halaman eksekusi
            kirim_notifikasi_pelanggaran($conn, $pesan_notif, $link_notif);
        }

        header("Location: create.php");
        exit();

    } else {
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Tipe pelanggaran tidak valid. Silakan coba lagi.'];
        header("Location: create.php");
        exit();
    }
}

// Jika file diakses langsung, tendang balik
header("Location: create.php");
exit();