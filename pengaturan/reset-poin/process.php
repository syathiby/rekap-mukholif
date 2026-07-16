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

// (Fungsi resetPoinSantri sudah digantikan dengan query massal di bawah)


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
            WHERE DATE(p.tanggal) >= ?";
        
        $stmt_pelanggaran_snapshot = $conn->prepare($sql_pelanggaran_snapshot);
        $stmt_pelanggaran_snapshot->bind_param('is', $arsip_id, $tgl_mulai);
        $stmt_pelanggaran_snapshot->execute();
        $stmt_pelanggaran_snapshot->close();

        // Snapshot pelanggaran kebersihan
        $sql_kebersihan_snapshot = "
            INSERT INTO arsip_data_pelanggaran_kebersihan 
                (arsip_id, kamar, catatan, tanggal, dicatat_oleh_user_id, dicatat_oleh_nama)
            SELECT ?, pk.kamar, pk.catatan, pk.tanggal, pk.dicatat_oleh, u.nama_lengkap
            FROM pelanggaran_kebersihan pk
            LEFT JOIN users u ON pk.dicatat_oleh = u.id
            WHERE DATE(pk.tanggal) >= ?";
        
        $stmt_kebersihan_snapshot = $conn->prepare($sql_kebersihan_snapshot);
        $stmt_kebersihan_snapshot->bind_param('is', $arsip_id, $tgl_mulai);
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

        // Snapshot rapot tahunan
        $sql_rapot_tahunan_snapshot = "
            INSERT INTO arsip_data_rapot_tahunan (
                arsip_id, rapot_tahunan_id, santri_id, santri_nama, santri_kelas, periode, kamar, musyrif_nama,
                nilai_snapshot, narasi_ai, catatan_musyrif, status, is_fallback,
                generated_at, approved_at, approved_by_nama
            )
            SELECT
                ?, rt.id, rt.santri_id, s.nama, s.kelas, rt.periode, rt.kamar, u2.nama_lengkap,
                rt.nilai_snapshot, rt.narasi_ai, rt.catatan_musyrif, rt.status, rt.is_fallback,
                rt.generated_at, rt.approved_at, u.nama_lengkap
            FROM rapot_tahunan rt
            LEFT JOIN santri s ON rt.santri_id = s.id
            LEFT JOIN users u ON rt.approved_by = u.id
            LEFT JOIN users u2 ON rt.musyrif_id = u2.id
        ";
        $stmt_rapot_tahunan_snapshot = $conn->prepare($sql_rapot_tahunan_snapshot);
        $stmt_rapot_tahunan_snapshot->bind_param('i', $arsip_id);
        $stmt_rapot_tahunan_snapshot->execute();
        $stmt_rapot_tahunan_snapshot->close();

        // 2. RESET PELANGGARAN KEBERSIHAN & RAPOT
        $stmt_del_kebersihan = $conn->prepare("DELETE FROM pelanggaran_kebersihan WHERE DATE(tanggal) >= ?");
        $stmt_del_kebersihan->bind_param('s', $tgl_mulai);
        $stmt_del_kebersihan->execute();
        
        // Rapot tidak punya filter rentang tanggal, namun kita amankan dengan periode tahun berjalan
        // Karena sistem saat ini mengarsip seluruh tabel rapot, kita hapus semuanya juga.
        $conn->query("DELETE FROM rapot_kepengasuhan");
        $conn->query("DELETE FROM rapot_tahunan");

        // 3. LOG MASAL & UPDATE POIN AKTIF
        
        // a. Insert semua santri ke log_reset_poin
        $stmt_log_massal = $conn->prepare("
            INSERT INTO log_reset_poin (id_santri, tanggal_reset, total_poin_sebelum_reset, keterangan, di_reset_oleh)
            SELECT id, CURDATE(), poin_aktif, ?, ?
            FROM santri
        ");
        $stmt_log_massal->bind_param('si', $keterangan, $di_reset_oleh);
        $stmt_log_massal->execute();
        $processed_count = $conn->affected_rows; // Ambil jumlah santri yang direset

        // b. Hitung ulang Poin Aktif: LEAST(Total Permanen, Total Pelanggaran - Total Reward)
        // Ini memungkinkan surplus poin reward menjadi negatif (Skenario B)
        $sql_update_poin = "
            UPDATE santri s
            SET poin_aktif = LEAST(
                COALESCE((
                    SELECT SUM(jp.poin) 
                    FROM pelanggaran p 
                    JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id 
                    WHERE p.santri_id = s.id AND jp.kategori = 'Sangat Berat'
                ), 0),
                COALESCE((
                    SELECT SUM(jp.poin) 
                    FROM pelanggaran p 
                    JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id 
                    WHERE p.santri_id = s.id
                ), 0)
                - 
                COALESCE((
                    SELECT SUM(jr.poin_reward) 
                    FROM daftar_reward dr 
                    JOIN jenis_reward jr ON dr.jenis_reward_id = jr.id 
                    WHERE dr.santri_id = s.id
                ), 0)
            )
        ";
        $stmt_update_poin = $conn->prepare($sql_update_poin);
        $stmt_update_poin->execute();

        // 4. HAPUS DATA PELANGGARAN RINGAN-SEDANG & REWARD
        $stmt_del_pelanggaran = $conn->prepare("
            DELETE p FROM pelanggaran p
            JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id
            WHERE jp.kategori != 'Sangat Berat' AND DATE(p.tanggal) >= ?
        ");
        $stmt_del_pelanggaran->bind_param('s', $tgl_mulai);
        $stmt_del_pelanggaran->execute();

        $stmt_del_reward = $conn->prepare("DELETE FROM daftar_reward WHERE DATE(tanggal) >= ?");
        $stmt_del_reward->bind_param('s', $tgl_mulai);
        $stmt_del_reward->execute();

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