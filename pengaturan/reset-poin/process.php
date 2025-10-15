<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../init.php';
guard('reset_poin_manage');

// Fungsi pembantu untuk me-reset poin satu santri
// Sekarang juga akan menghapus riwayat pelanggaran non-permanen
function resetPoinSantri($conn, $id_santri, $keterangan, $di_reset_oleh) {
    // 1. Ambil poin santri saat ini
    $stmt_get_santri = $conn->prepare("SELECT poin_aktif FROM santri WHERE id = ?");
    $stmt_get_santri->bind_param("i", $id_santri);
    $stmt_get_santri->execute();
    $poin_sebelum_reset = $stmt_get_santri->get_result()->fetch_assoc()['poin_aktif'];
    $stmt_get_santri->close();

    if ($poin_sebelum_reset <= 0) {
        return true; // Anggap berhasil jika poin sudah 0, tidak perlu proses lebih lanjut
    }
    
    // 2. Hitung total poin permanen (Sangat Berat) santri
    $stmt_get_permanent = $conn->prepare("SELECT SUM(jp.poin) AS total_permanen FROM pelanggaran p JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id WHERE p.santri_id = ? AND jp.kategori = 'Sangat Berat'");
    $stmt_get_permanent->bind_param("i", $id_santri);
    $stmt_get_permanent->execute();
    $result_permanent = $stmt_get_permanent->get_result();
    $poin_baru = $result_permanent->fetch_assoc()['total_permanen'] ?? 0;
    $stmt_get_permanent->close();

    // 3. (LANGKAH BARU) Hapus semua riwayat pelanggaran non-permanen untuk santri ini
    $stmt_delete = $conn->prepare("
        DELETE p FROM pelanggaran p
        JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id
        WHERE p.santri_id = ? AND jp.kategori != 'Sangat Berat'
    ");
    $stmt_delete->bind_param("i", $id_santri);
    $stmt_delete->execute();
    $stmt_delete->close();

    // 4. Catat ke log reset
    $stmt_log = $conn->prepare("INSERT INTO log_reset_poin (id_santri, tanggal_reset, total_poin_sebelum_reset, keterangan, di_reset_oleh) VALUES (?, CURDATE(), ?, ?, ?)");
    $stmt_log->bind_param("iisi", $id_santri, $poin_sebelum_reset, $keterangan, $di_reset_oleh);
    $stmt_log->execute();
    $stmt_log->close();
    
    // 5. Update poin_aktif santri ke nilai poin permanen
    $stmt_update = $conn->prepare("UPDATE santri SET poin_aktif = ? WHERE id = ?");
    $stmt_update->bind_param("ii", $poin_baru, $id_santri);
    $stmt_update->execute();
    $stmt_update->close();

    return true; // Kembalikan true jika semua proses berhasil
}


// --- BAGIAN UTAMA: MENENTUKAN AKSI (Tidak ada perubahan di sini) ---

$di_reset_oleh = $_SESSION['user_id'];

// SKENARIO 1: RESET SATU SANTRI
if (isset($_POST['reset_satu_santri'])) {
    $id_santri = $_POST['santri_id'];
    $keterangan = trim($_POST['keterangan_satu']);

    if (empty($id_santri) || empty($keterangan)) {
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Santri dan keterangan harus diisi.'];
        header("Location: index.php");
        exit();
    }

    mysqli_begin_transaction($conn);
    try {
        resetPoinSantri($conn, $id_santri, $keterangan, $di_reset_oleh);
        mysqli_commit($conn);
        $_SESSION['message'] = ['type' => 'success', 'text' => 'RESET BERHASIL! Poin dan riwayat pelanggaran non-permanen santri telah direset.'];
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'RESET GAGAL! Terjadi kesalahan: ' . $e->getMessage()];
    }

    header("Location: index.php");
    exit();
}

// SKENARIO 2: RESET SEMUA SANTRI
elseif (isset($_POST['reset_semua_poin'])) {
    $keterangan = trim($_POST['keterangan_semua']);

    if (empty($keterangan)) {
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Keterangan reset massal tidak boleh kosong.'];
        header("Location: index.php");
        exit();
    }

    mysqli_begin_transaction($conn);
    try {
        // Ambil semua santri yang punya poin
        $result_santri = mysqli_query($conn, "SELECT id FROM santri WHERE poin_aktif > 0");
        $santri_list = mysqli_fetch_all($result_santri, MYSQLI_ASSOC);
        
        $processed_count = 0;
        foreach ($santri_list as $santri) {
            resetPoinSantri($conn, $santri['id'], $keterangan, $di_reset_oleh);
            $processed_count++;
        }

        mysqli_commit($conn);
        $_SESSION['message'] = ['type' => 'success', 'text' => "RESET MASSAL BERHASIL! Sebanyak $processed_count data poin santri dan riwayat pelanggaran non-permanennya telah direset."];

    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'RESET MASSAL GAGAL! Terjadi kesalahan: ' . $e->getMessage()];
    }

    header("Location: index.php");
    exit();

}

// Jika tidak ada aksi yang cocok, kembalikan ke halaman index
else {
    header("Location: index.php");
    exit();
}