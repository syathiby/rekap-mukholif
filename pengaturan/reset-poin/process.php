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
    $poin_sebelum_reset = $stmt_get_santri->get_result()->fetch_assoc()['poin_aktif'] ?? 0;
    $stmt_get_santri->close();
    
    // 2. Hitung total poin permanen (Sangat Berat) santri
    $stmt_get_permanent = $conn->prepare("SELECT SUM(jp.poin) AS total_permanen FROM pelanggaran p JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id WHERE p.santri_id = ? AND jp.kategori = 'Sangat Berat'");
    $stmt_get_permanent->bind_param("i", $id_santri);
    $stmt_get_permanent->execute();
    $total_permanen = $stmt_get_permanent->get_result()->fetch_assoc()['total_permanen'] ?? 0;
    $stmt_get_permanent->close();

    // 2b. Hitung total reward (Opsi B: Pertahankan Surplus)
    $stmt_get_reward = $conn->prepare("SELECT SUM(jr.poin_reward) AS total_reward FROM daftar_reward r JOIN jenis_reward jr ON r.jenis_reward_id = jr.id WHERE r.santri_id = ?");
    $stmt_get_reward->bind_param("i", $id_santri);
    $stmt_get_reward->execute();
    $total_reward = $stmt_get_reward->get_result()->fetch_assoc()['total_reward'] ?? 0;
    $stmt_get_reward->close();

    $poin_baru = $total_permanen - $total_reward;

    // 3. Hapus semua riwayat pelanggaran non-permanen untuk santri ini
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
    
    // 5. Update poin_aktif santri ke nilai poin yang dihitung ulang (Permanen - Reward)
    $stmt_update = $conn->prepare("UPDATE santri SET poin_aktif = ? WHERE id = ?");
    $stmt_update->bind_param("ii", $poin_baru, $id_santri);
    $stmt_update->execute();
    $stmt_update->close();

    return true; // Kembalikan true jika semua proses berhasil
}


// --- BAGIAN UTAMA: MENENTUKAN AKSI ---

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

// SKENARIO 2: TUTUP BUKU AKHIR TAHUN
elseif (isset($_POST['tutup_buku_massal'])) {
    $keterangan = trim($_POST['keterangan_semua']);
    $judul_arsip = trim($_POST['judul_arsip']);

    if (empty($keterangan) || empty($judul_arsip)) {
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Judul arsip dan keterangan tidak boleh kosong.'];
        header("Location: index.php");
        exit();
    }

    mysqli_begin_transaction($conn);
    try {
        // Ambil periode aktif saat ini dari konstanta
        $tgl_mulai = PERIODE_AKTIF;
        $tgl_selesai = date('Y-m-d'); // Hari ini

        // 1. BUAT ARSIP
        $stmt_arsip = $conn->prepare("INSERT INTO arsip (judul, tanggal_mulai, tanggal_selesai) VALUES (?, ?, ?)");
        $stmt_arsip->bind_param('sss', $judul_arsip, $tgl_mulai, $tgl_selesai);
        $stmt_arsip->execute();
        $arsip_id = $conn->insert_id;
        $stmt_arsip->close();

        // Snapshot santri
        $sql_santri_snapshot = "INSERT INTO arsip_data_santri (arsip_id, santri_id, santri_nama, santri_kelas, santri_kamar, total_poin_saat_arsip) SELECT ?, id, nama, kelas, kamar, poin_aktif FROM santri";
        $stmt_santri_snapshot = $conn->prepare($sql_santri_snapshot);
        $stmt_santri_snapshot->bind_param('i', $arsip_id);
        $stmt_santri_snapshot->execute();
        $stmt_santri_snapshot->close();

        // Snapshot pelanggaran umum
        $sql_pelanggaran_snapshot = "
            INSERT INTO arsip_data_pelanggaran 
                (arsip_id, santri_id, santri_nama, santri_kelas, santri_kamar, jenis_pelanggaran_id, jenis_pelanggaran_nama, bagian, poin, tanggal, tipe) 
            SELECT 
                ?, p.santri_id, s.nama, s.kelas, s.kamar, 
                p.jenis_pelanggaran_id, jp.nama_pelanggaran, jp.bagian, jp.poin, p.tanggal, 'Umum' AS tipe 
            FROM pelanggaran p 
            JOIN santri s ON p.santri_id = s.id 
            JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id 
            WHERE DATE(p.tanggal) BETWEEN ? AND ?";
        
        $stmt_pelanggaran_snapshot = $conn->prepare($sql_pelanggaran_snapshot);
        $stmt_pelanggaran_snapshot->bind_param('iss', $arsip_id, $tgl_mulai, $tgl_selesai);
        $stmt_pelanggaran_snapshot->execute();
        $stmt_pelanggaran_snapshot->close();

        // Snapshot pelanggaran kebersihan
        $sql_kebersihan_snapshot = "
            INSERT INTO arsip_data_pelanggaran_kebersihan 
                (arsip_id, kamar, catatan, tanggal, dicatat_oleh_user_id, dicatat_oleh_nama)
            SELECT ?, pk.kamar, pk.catatan, pk.tanggal, pk.dicatat_oleh, u.nama_lengkap
            FROM pelanggaran_kebersihan pk
            LEFT JOIN users u ON pk.dicatat_oleh = u.id
            WHERE DATE(pk.tanggal) BETWEEN ? AND ?";
        
        $stmt_kebersihan_snapshot = $conn->prepare($sql_kebersihan_snapshot);
        $stmt_kebersihan_snapshot->bind_param('iss', $arsip_id, $tgl_mulai, $tgl_selesai);
        $stmt_kebersihan_snapshot->execute();
        $stmt_kebersihan_snapshot->close();

        // 2. RESET PELANGGARAN KEBERSIHAN (Opsi A)
        $conn->query("DELETE FROM pelanggaran_kebersihan");

        // 3. RESET POIN SANTRI & PELANGGARAN UMUM RINGAN
        $result_santri = mysqli_query($conn, "SELECT id FROM santri");
        $santri_list = mysqli_fetch_all($result_santri, MYSQLI_ASSOC);
        
        $processed_count = 0;
        foreach ($santri_list as $santri) {
            resetPoinSantri($conn, $santri['id'], $keterangan, $di_reset_oleh);
            $processed_count++;
        }

        // 4. UPDATE PERIODE AKTIF KE HARI INI
        $hari_ini_baru = date('Y-m-d');
        $conn->query("UPDATE pengaturan SET nilai = '$hari_ini_baru' WHERE nama = 'periode_aktif'");

        mysqli_commit($conn);
        
        // Catat log
        write_activity_log('RESET_POIN', 'reset-poin', "Melakukan TUTUP BUKU AKHIR TAHUN. Data diarsipkan dan laci utama dibersihkan ($processed_count santri)", [
            'processed_count' => $processed_count,
            'keterangan' => $keterangan,
            'judul_arsip' => $judul_arsip,
            'arsip_id' => $arsip_id
        ]);

        $_SESSION['message'] = ['type' => 'success', 'text' => "TUTUP BUKU BERHASIL! Data telah diarsipkan ke gudang dan laci utama siap untuk tahun ajaran baru."];

    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'TUTUP BUKU GAGAL! Terjadi kesalahan: ' . $e->getMessage()];
    }

    header("Location: index.php");
    exit();

}

// Jika tidak ada aksi yang cocok, kembalikan ke halaman index
else {
    header("Location: index.php");
    exit();
}