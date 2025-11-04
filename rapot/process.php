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

    // Ambil semua nilai (biar rapi)
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
        // 5. Kalkulasi Total Poin Pelanggaran (Pake MySQLi)
        $total_poin_pelanggaran = 0;
        
        // ==========================================================
        //           PERBAIKAN SQL-NYA DI SINI
        // Kita ganti MONTHNAME() jadi MONTH() dan FIND_IN_SET()
        // ==========================================================
        $sql_poin = "
            SELECT SUM(jp.poin) AS total_poin
            FROM pelanggaran p
            JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id
            WHERE p.santri_id = ? 
              AND MONTH(p.tanggal) = FIND_IN_SET(?, 'Januari,Februari,Maret,April,Mei,Juni,Juli,Agustus,September,Oktober,November,Desember')
              AND YEAR(p.tanggal) = ?
        ";
        // ==========================================================
        
        $stmt_poin = $conn->prepare($sql_poin);
        // bind_param-nya udah bener (i, s, i), gak perlu diubah
        $stmt_poin->bind_param("isi", $santri_id, $bulan, $tahun); 
        $stmt_poin->execute();
        
        $hasil_poin = $stmt_poin->get_result()->fetch_assoc();
        
        if ($hasil_poin && $hasil_poin['total_poin'] > 0) {
            $total_poin_pelanggaran = (int)$hasil_poin['total_poin'];
        }
        $stmt_poin->close();


        // 6. Siapin query INSERT (Pake MySQLi)
        $sql_insert = "
            INSERT INTO rapot_kepengasuhan (
                santri_id, musyrif_id, bulan, tahun, 
                puasa_sunnah, sholat_duha, sholat_malam, sedekah, sunnah_tidur, ibadah_lainnya, 
                lisan, sikap, kesopanan, muamalah, 
                tidur, keterlambatan, seragam, makan, arahan, bahasa_arab, 
                mandi, penampilan, piket, kerapihan_barang, 
                total_poin_pelanggaran_saat_itu, catatan_musyrif
            ) VALUES (
                ?, ?, ?, ?, 
                ?, ?, ?, ?, ?, ?, 
                ?, ?, ?, ?, 
                ?, ?, ?, ?, ?, ?, 
                ?, ?, ?, ?,
                ?, ?
            )
        "; 

        $stmt_insert = $conn->prepare($sql_insert);
        
        $stmt_insert->bind_param("iissiiiiiiiiiiiiiiiiiiiiis", 
            $santri_id, $musyrif_id, $bulan, $tahun,
            $puasa_sunnah, $sholat_duha, $sholat_malam, $sedekah, $sunnah_tidur, $ibadah_lainnya,
            $lisan, $sikap, $kesopanan, $muamalah,
            $tidur, $keterlambatan, $seragam, $makan, $arahan, $bahasa_arab,
            $mandi, $penampilan, $piket, $kerapihan_barang,
            $total_poin_pelanggaran, $catatan
        );

        // 7. Eksekusi query
        $stmt_insert->execute();

        // 8. Kasih notif sukses & redirect
        set_flash_message('Rapot baru berhasil disimpan!', 'success');
        
        header('Location: index.php'); // Redirect ke halaman daftar rapot
        exit; 

    } catch (Exception $e) {
        // Kalo gagal
        set_flash_message('Error: ' . $e->getMessage(), 'danger');
        header('Location: create.php'); // Balikin ke form create
        exit;

    } finally {
        // Selalu tutup statement
        if (isset($stmt_insert)) {
            $stmt_insert->close();
        }
    }

} else {
    // Kalo diakses langsung tanpa submit
    header('Location: create.php');
    exit;
}
?>