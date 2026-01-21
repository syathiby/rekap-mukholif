<?php
// File: rekap-mukholif/rapot/process.php

// 1. Panggil 'Otak' aplikasi dulu
require_once __DIR__ . '/../init.php';

// 2. Jalankan 'SATPAM'
guard('rapot_create');

// 3. Cek apakah tombol submit ditekan
if (isset($_POST['simpan_rapot'])) {

    // 4. Ambil semua data dari POST
    $santri_id = (int)$_POST['santri_id'];
    $bulan = $_POST['bulan'];
    $tahun = (int)$_POST['tahun'];
    $catatan = $_POST['catatan_musyrif'];
    $musyrif_id = (int)$_SESSION['user_id']; 

    // Ambil semua nilai ibadah (biar rapi)
    $puasa_sunnah = (int)$_POST['puasa_sunnah'];
    $sholat_duha = (int)$_POST['sholat_duha'];
    $sholat_malam = (int)$_POST['sholat_malam'];
    $sedekah = (int)$_POST['sedekah'];
    $sunnah_tidur = (int)$_POST['sunnah_tidur'];
    $ibadah_lainnya = (int)$_POST['ibadah_lainnya'];
    $lisan = (int)$_POST['lisan'];
    $sikap = (int)$_POST['sikap'];
    $kesopanan = (int)$_POST['kesopanan'];
    $muamalah = (int)$_POST['muamalah'];
    $tidur = (int)$_POST['tidur'];
    $keterlambatan = (int)$_POST['keterlambatan'];
    $seragam = (int)$_POST['seragam'];
    $makan = (int)$_POST['makan'];
    $arahan = (int)$_POST['arahan'];
    $bahasa_arab = (int)$_POST['bahasa_arab'];
    $mandi = (int)$_POST['mandi'];
    $penampilan = (int)$_POST['penampilan'];
    $piket = (int)$_POST['piket'];
    $kerapihan_barang = (int)$_POST['kerapihan_barang'];

    
    try {
        // 5. Kalkulasi Total Poin Pelanggaran
        $total_poin_pelanggaran = 0;
        
        $sql_poin = "
            SELECT SUM(jp.poin) AS total_poin
            FROM pelanggaran p
            JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id
            WHERE p.santri_id = ? 
              AND MONTH(p.tanggal) = FIND_IN_SET(?, 'Januari,Februari,Maret,April,Mei,Juni,Juli,Agustus,September,Oktober,November,Desember')
              AND YEAR(p.tanggal) = ?
        ";
        
        $stmt_poin = $conn->prepare($sql_poin);
        $stmt_poin->bind_param("isi", $santri_id, $bulan, $tahun); 
        $stmt_poin->execute();
        
        $hasil_poin = $stmt_poin->get_result()->fetch_assoc();
        
        if ($hasil_poin && $hasil_poin['total_poin'] > 0) {
            $total_poin_pelanggaran = (int)$hasil_poin['total_poin'];
        }
        $stmt_poin->close();

        // 6. KALKULASI TOTAL POIN REWARD
        $total_poin_reward = 0;
        
        $sql_reward = "
            SELECT SUM(jr.poin_reward) AS total_poin_reward
            FROM daftar_reward rwd
            JOIN jenis_reward jr ON rwd.jenis_reward_id = jr.id
            WHERE rwd.santri_id = ? 
              AND MONTH(rwd.tanggal) = FIND_IN_SET(?, 'Januari,Februari,Maret,April,Mei,Juni,Juli,Agustus,September,Oktober,November,Desember')
              AND YEAR(rwd.tanggal) = ?
        ";
        
        $stmt_reward = $conn->prepare($sql_reward);
        $stmt_reward->bind_param("isi", $santri_id, $bulan, $tahun);
        $stmt_reward->execute();
        
        $hasil_reward = $stmt_reward->get_result()->fetch_assoc();
        
        if ($hasil_reward && $hasil_reward['total_poin_reward'] > 0) {
            $total_poin_reward = (int)$hasil_reward['total_poin_reward'];
        }
        $stmt_reward->close();

        // 8. Siapin query INSERT dengan placeholder yang benar
        $sql_insert = "
            INSERT INTO rapot_kepengasuhan (
                santri_id, musyrif_id, bulan, tahun, 
                puasa_sunnah, sholat_duha, sholat_malam, sedekah, sunnah_tidur, ibadah_lainnya, 
                lisan, sikap, kesopanan, muamalah, 
                tidur, keterlambatan, seragam, makan, arahan, bahasa_arab, 
                mandi, penampilan, piket, kerapihan_barang, 
                total_poin_pelanggaran_saat_itu, total_poin_reward_saat_itu, catatan_musyrif
            ) VALUES (
                ?, ?, ?, ?, 
                ?, ?, ?, ?, ?, ?, 
                ?, ?, ?, ?, 
                ?, ?, ?, ?, ?, ?, 
                ?, ?, ?, ?,
                ?, ?, ?
            )
        ";

        $stmt_insert = $conn->prepare($sql_insert);
        
        if (!$stmt_insert) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        // --- PERBAIKAN UTAMA (FIXED) ---
        // Penjelasan Format:
        // "iiss" -> santri_id(i), musyrif_id(i), bulan(s), tahun(s) [4 param]
        // 20 x "i" -> Variabel ibadah [20 param]
        // "ii" -> total_pelanggaran(i), total_reward(i) [2 param]
        // "s" -> catatan(s) [1 param]
        // Total = 27 Parameter.
        
        $types = "iiss" . str_repeat("i", 22) . "s"; 

        $success = $stmt_insert->bind_param(
            $types, 
            $santri_id, $musyrif_id, $bulan, $tahun, // 4 param
            $puasa_sunnah, $sholat_duha, $sholat_malam, $sedekah, $sunnah_tidur, $ibadah_lainnya, // 6 param
            $lisan, $sikap, $kesopanan, $muamalah, // 4 param
            $tidur, $keterlambatan, $seragam, $makan, $arahan, $bahasa_arab, // 6 param
            $mandi, $penampilan, $piket, $kerapihan_barang, // 4 param
            $total_poin_pelanggaran, $total_poin_reward, $catatan // 3 param
        );
        
        if (!$success) {
            throw new Exception("Bind param failed: " . $stmt_insert->error);
        }

        // 9. Eksekusi query
        $executed = $stmt_insert->execute();
        
        if (!$executed) {
            throw new Exception("Execute failed: " . $stmt_insert->error);
        }

        // 10. Kasih notif sukses & redirect
        set_flash_message('Rapot baru berhasil disimpan dengan poin reward!', 'success');
        
        header('Location: index.php');
        exit; 

    } catch (Throwable $e) { // Ganti Exception jadi Throwable biar nangkep semua error
        // Debug detail
        error_log("RAPOT PROCESS ERROR: " . $e->getMessage());
        error_log("Santri ID: $santri_id, Bulan: $bulan, Tahun: $tahun");
        error_log("Total Poin Pelanggaran: $total_poin_pelanggaran");
        error_log("Total Poin Reward: $total_poin_reward");
        
        set_flash_message('Error: ' . $e->getMessage(), 'danger');
        header('Location: create.php');
        exit;

    } finally {
        if (isset($stmt_poin) && $stmt_poin) $stmt_poin->close();
        if (isset($stmt_reward) && $stmt_reward) $stmt_reward->close();
        if (isset($stmt_insert) && $stmt_insert) $stmt_insert->close();
    }

} else {
    header('Location: create.php');
    exit;
}
?>