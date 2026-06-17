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



// SKENARIO 2: TUTUP BUKU AKHIR TAHUN
if (isset($_POST['tutup_buku_massal'])) {
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

        // Snapshot rapot kepengasuhan
        $sql_rapot_snapshot = "
            INSERT INTO arsip_data_rapot (
                arsip_id, santri_id, santri_nama, santri_kelas, santri_kamar,
                musyrif_id, musyrif_nama, bulan, tahun,
                puasa_sunnah, sholat_duha, sholat_malam, sedekah, sunnah_tidur, ibadah_lainnya,
                lisan, sikap, kesopanan, muamalah, tidur, keterlambatan, seragam, makan,
                arahan, bahasa_arab, mandi, penampilan, piket, kerapihan_barang,
                total_poin_pelanggaran_saat_itu, total_poin_reward_saat_itu, catatan_musyrif, dibuat_pada
            )
            SELECT
                ?, r.santri_id, s.nama, s.kelas, s.kamar,
                r.musyrif_id, u.nama_lengkap, r.bulan, r.tahun,
                r.puasa_sunnah, r.sholat_duha, r.sholat_malam, r.sedekah, r.sunnah_tidur, r.ibadah_lainnya,
                r.lisan, r.sikap, r.kesopanan, r.muamalah, r.tidur, r.keterlambatan, r.seragam, r.makan,
                r.arahan, r.bahasa_arab, r.mandi, r.penampilan, r.piket, r.kerapihan_barang,
                r.total_poin_pelanggaran_saat_itu, r.total_poin_reward_saat_itu, r.catatan_musyrif, r.dibuat_pada
            FROM rapot_kepengasuhan r
            LEFT JOIN santri s ON r.santri_id = s.id
            LEFT JOIN users u ON r.musyrif_id = u.id
        ";
        $stmt_rapot_snapshot = $conn->prepare($sql_rapot_snapshot);
        $stmt_rapot_snapshot->bind_param('i', $arsip_id);
        $stmt_rapot_snapshot->execute();
        $stmt_rapot_snapshot->close();

        // Snapshot reward santri ke arsip_data_reward
        $sql_reward_snapshot = "
            INSERT INTO arsip_data_reward
                (arsip_id, santri_id, santri_nama, santri_kelas, santri_kamar,
                 jenis_reward_id, nama_reward, poin_reward, tanggal, dicatat_oleh_nama)
            SELECT
                ?, dr.santri_id, s.nama, s.kelas, s.kamar,
                jr.id, jr.nama_reward, jr.poin_reward, dr.tanggal, u.nama_lengkap
            FROM daftar_reward dr
            JOIN santri s ON dr.santri_id = s.id
            JOIN jenis_reward jr ON dr.jenis_reward_id = jr.id
            LEFT JOIN users u ON dr.dicatat_oleh = u.id
        ";
        $stmt_reward_snapshot = $conn->prepare($sql_reward_snapshot);
        $stmt_reward_snapshot->bind_param('i', $arsip_id);
        $stmt_reward_snapshot->execute();
        $stmt_reward_snapshot->close();

        // 2. RESET PELANGGARAN KEBERSIHAN & RAPOT
        $conn->query("DELETE FROM pelanggaran_kebersihan");
        $conn->query("DELETE FROM rapot_kepengasuhan");

        // 3. RESET POIN SANTRI & PELANGGARAN UMUM RINGAN
        // Hitung surplus reward per santri sebelum daftar_reward dihapus
        $surplus_per_santri = [];
        $q_surplus = $conn->query("
            SELECT dr.santri_id,
                   COALESCE(SUM(jr.poin_reward), 0) AS total_reward,
                   (
                       SELECT COALESCE(SUM(jp.poin), 0)
                       FROM pelanggaran p
                       JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id
                       WHERE p.santri_id = dr.santri_id
                   ) AS total_pelanggaran
            FROM daftar_reward dr
            JOIN jenis_reward jr ON dr.jenis_reward_id = jr.id
            GROUP BY dr.santri_id
        ");
        if ($q_surplus) {
            while ($row_s = $q_surplus->fetch_assoc()) {
                $surplus = (int)$row_s['total_reward'] - (int)$row_s['total_pelanggaran'];
                if ($surplus > 0) {
                    $surplus_per_santri[(int)$row_s['santri_id']] = $surplus;
                }
            }
        }

        // Hapus daftar_reward (sudah di-snapshot ke arsip)
        $conn->query("DELETE FROM daftar_reward");

        $result_santri = mysqli_query($conn, "SELECT id FROM santri");
        $santri_list = mysqli_fetch_all($result_santri, MYSQLI_ASSOC);
        
        $processed_count = 0;
        foreach ($santri_list as $santri) {
            resetPoinSantri($conn, $santri['id'], $keterangan, $di_reset_oleh);
            // Terapkan surplus reward sebagai poin bonus awal tahun baru
            if (isset($surplus_per_santri[$santri['id']])) {
                $bonus = -$surplus_per_santri[$santri['id']]; // Negatif karena reward mengurangi poin
                $conn->query("UPDATE santri SET poin_aktif = poin_aktif + ($bonus) WHERE id = {$santri['id']}");
            }
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