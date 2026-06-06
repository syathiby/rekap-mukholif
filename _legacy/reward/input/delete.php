<?php
require_once __DIR__ . '/../../bootstrap/init.php';
// Gunakan guard history atau permission khusus delete
guard('reward_input'); 

// Validasi CSRF token dari GET parameter
if (!isset($_GET['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_GET['csrf_token'])) {
    http_response_code(403);
    require __DIR__ . '/../../bootstrap/csrf_expired.php';
    exit;
}

if (isset($_GET['id'])) {
    $id = (int) $_GET['id'];

    mysqli_begin_transaction($conn);

    try {
        // 1. Ambil data dulu sebelum dihapus (butuh ID santri & jumlah poin rewardnya)
        $query_get = "SELECT dr.santri_id, jr.poin_reward, s.nama AS nama_santri 
                      FROM daftar_reward dr 
                      JOIN jenis_reward jr ON dr.jenis_reward_id = jr.id 
                      JOIN santri s ON dr.santri_id = s.id
                      WHERE dr.id = $id FOR UPDATE";
        
        $result = mysqli_query($conn, $query_get);
        
        if (mysqli_num_rows($result) == 0) {
            throw new Exception("Data tidak ditemukan.");
        }

        $row = mysqli_fetch_assoc($result);
        $santri_id = $row['santri_id'];
        $poin_restore = $row['poin_reward'];
        $nama_santri = $row['nama_santri'];

        // 2. KEMBALIKAN Poin Santri (DITAMBAH)
        // Karena transaksinya dibatalkan, maka poin 'diskon' tadi kita cabut (balikin poin pelanggaran)
        $query_restore = "UPDATE santri SET poin_aktif = poin_aktif + $poin_restore WHERE id = $santri_id";
        if (!mysqli_query($conn, $query_restore)) throw new Exception("Gagal mengembalikan poin santri.");

        // 3. Hapus Data dari History
        $query_delete = "DELETE FROM daftar_reward WHERE id = $id";
        if (!mysqli_query($conn, $query_delete)) throw new Exception("Gagal menghapus data reward.");

        write_activity_log('DELETE', 'reward', "Membatalkan/menghapus reward santri '" . htmlspecialchars($nama_santri) . "' dan mengembalikan poinnya (+$poin_restore)", ['reward_id' => $id, 'santri_id' => $santri_id, 'nama_santri' => $nama_santri, 'poin_dikembalikan' => $poin_restore]);

        mysqli_commit($conn);
        $_SESSION['message'] = ['type' => 'success', 'text' => 'Data reward dihapus. Poin santri telah dikembalikan (bertambah).'];

    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Gagal hapus: ' . $e->getMessage()];
    }
}

header("Location: ../history/index.php");
exit;
?>