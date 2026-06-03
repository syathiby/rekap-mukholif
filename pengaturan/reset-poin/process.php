<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../bootstrap/init.php';
guard('reset_poin_manage');

// Validasi CSRF token sebelum proses apapun
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
}

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

// SKENARIO 1: RESET BEBERAPA SANTRI (MULTI-SELECT)
if (isset($_POST['reset_satu_santri'])) {
    $id_santri_array = $_POST['santri_id']; // Sekarang ini berupa array
    $keterangan = trim($_POST['keterangan_satu']);

    if (empty($id_santri_array) || !is_array($id_santri_array) || empty($keterangan)) {
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Minimal satu santri dan keterangan harus diisi.'];
        header("Location: index.php");
        exit();
    }

    // Ambil nama santri untuk dicatat di log
    $santri_names = [];
    if (!empty($id_santri_array)) {
        $ids_str = implode(',', array_map('intval', $id_santri_array));
        $q_s = $conn->query("SELECT nama FROM santri WHERE id IN ($ids_str)");
        if ($q_s) {
            while ($s_row = $q_s->fetch_assoc()) {
                $santri_names[] = $s_row['nama'];
            }
        }
    }

    mysqli_begin_transaction($conn);
    try {
        $processed_count = 0;
        foreach ($id_santri_array as $id_santri) {
            // Pastikan ID valid
            if (!empty($id_santri)) {
                resetPoinSantri($conn, $id_santri, $keterangan, $di_reset_oleh);
                $processed_count++;
            }
        }
        
        mysqli_commit($conn);
        
        // Catat log reset poin
        write_activity_log('RESET_POIN', 'reset-poin', "Melakukan reset poin santri sebanyak " . count($santri_names) . " anak: " . implode(', ', $santri_names), [
            'santri_ids' => $id_santri_array,
            'santri_names' => $santri_names,
            'keterangan' => $keterangan
        ]);
        
        $_SESSION['message'] = ['type' => 'success', 'text' => "RESET BERHASIL! Sebanyak $processed_count santri telah direset poin and riwayat pelanggaran non-permanennya."];
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
        
        // Catat log reset poin massal
        write_activity_log('RESET_POIN', 'reset-poin', "Melakukan reset massal seluruh poin aktif santri ($processed_count santri)", [
            'processed_count' => $processed_count,
            'keterangan' => $keterangan
        ]);

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