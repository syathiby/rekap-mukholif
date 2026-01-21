<?php
// File: rekap-mukholif/rapot/generate_catatan.php

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/helper.php';

guard('rapot_create');

// 1. Ambil data utama dari AJAX (opsional, hanya untuk poin pelanggaran & reward)
$santri_id = (int)($_POST['santri_id'] ?? 0);
$bulan = $_POST['bulan'] ?? '';
$tahun = (int)($_POST['tahun'] ?? 0);

// 2. Kalkulasi Total Poin Pelanggaran (jika data utama tersedia)
$total_poin_pelanggaran = 0;
if (!empty($santri_id) && !empty($bulan) && !empty($tahun)) {
    try {
        $sql_poin = "
            SELECT SUM(jp.poin) AS total_poin
            FROM pelanggaran p
            JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id
            WHERE p.santri_id = ? 
              AND MONTH(p.tanggal) = FIND_IN_SET(?, 'Januari,Februari,Maret,April,Mei,Juni,Juli,Agustus,September,Oktober,November,Desember')
              AND YEAR(p.tanggal) = ?
              AND jp.poin > 0
        ";
        
        $stmt_poin = $conn->prepare($sql_poin);
        $stmt_poin->bind_param("isi", $santri_id, $bulan, $tahun);
        $stmt_poin->execute();
        $hasil_poin = $stmt_poin->get_result()->fetch_assoc();
        
        if ($hasil_poin && $hasil_poin['total_poin'] > 0) {
            $total_poin_pelanggaran = (int)$hasil_poin['total_poin'];
        }
        $stmt_poin->close();
    } catch (Exception $e) {
        // Jika query gagal, abaikan dan biarkan poin = 0
    }
}

// 2b. Kalkulasi Total Poin Reward (jika data utama tersedia)
$total_poin_reward = 0;
if (!empty($santri_id) && !empty($bulan) && !empty($tahun)) {
    try {
        $sql_reward = "
            SELECT SUM(jr.poin_reward) AS total_reward
            FROM daftar_reward dr
            JOIN jenis_reward jr ON dr.jenis_reward_id = jr.id
            WHERE dr.santri_id = ? 
              AND MONTH(dr.tanggal) = FIND_IN_SET(?, 'Januari,Februari,Maret,April,Mei,Juni,Juli,Agustus,September,Oktober,November,Desember')
              AND YEAR(dr.tanggal) = ?
              AND jr.poin_reward > 0
        ";
        
        $stmt_reward = $conn->prepare($sql_reward);
        $stmt_reward->bind_param("isi", $santri_id, $bulan, $tahun);
        $stmt_reward->execute();
        $hasil_reward = $stmt_reward->get_result()->fetch_assoc();
        
        if ($hasil_reward && $hasil_reward['total_reward'] > 0) {
            $total_poin_reward = (int)$hasil_reward['total_reward'];
        }
        $stmt_reward->close();
    } catch (Exception $e) {
        // Jika query gagal, abaikan dan biarkan poin = 0
    }
}

// 3. Kamus Nama Penilaian
$nama_penilaian = [
    'puasa_sunnah' => 'Puasa Sunnah', 'sholat_duha' => 'Sholat Duha', 'sholat_malam' => 'Sholat Malam',
    'sedekah' => 'Sedekah & Berbagi', 'sunnah_tidur' => 'Sunnah sebelum tidur', 'ibadah_lainnya' => 'Ibadah lainnya',
    'lisan' => 'Lisan', 'sikap' => 'Sikap & Tingkah Laku', 'kesopanan' => 'Kesopanan', 'muamalah' => 'Muamalah',
    'tidur' => 'Tidur', 'keterlambatan' => 'Keterlambatan', 'seragam' => 'Seragam',
    'makan' => 'Makan', 'arahan' => 'Mengikuti Arahan', 'bahasa_arab' => 'Berbahasa Arab',
    'mandi' => 'Mandi', 'penampilan' => 'Penampilan', 'piket' => 'Piket', 'kerapihan_barang' => 'Kerapihan Barang'
];

// 4. Ambil data nilai dari AJAX
$nilai = $_POST;
$poin_terbaik = [];
$poin_terburuk = [];

// Pisahkan nilai tinggi (5) dan rendah (1/2)
foreach ($nilai as $key => $val) {
    if (!isset($nama_penilaian[$key])) continue;
    $skor = (int)$val;
    
    if ($skor == 5) {
        $poin_terbaik[] = $nama_penilaian[$key];
    } elseif ($skor <= 2 && $skor > 0) {
        $poin_terburuk[] = $nama_penilaian[$key];
    }
}

// 5. Buat kalimat catatan versi hangat
$catatan = "";

// Bagian 1: Aspek Baik
if (count($poin_terbaik) > 0) {
    $list_terbaik = array_slice($poin_terbaik, 0, 3);
    $catatan .= "Alhamdulillah, Ananda menunjukkan sikap yang baik, terutama dalam aspek " . implode(', ', $list_terbaik) . ". ";
    if (count($poin_terbaik) > 3) {
        $catatan .= "Masih banyak hal positif lainnya yang patut diapresiasi. ";
    }
} else {
    $catatan .= "Ananda telah berusaha dengan baik dalam menjalankan kegiatan hariannya. ";
}

// Bagian 2: Aspek Perlu Perbaikan
if (count($poin_terburuk) > 0) {
    $list_terburuk = array_slice($poin_terburuk, 0, 2);
    $catatan .= "Namun, perlu ditingkatkan lagi pada aspek " . implode(', ', $list_terburuk) . ". ";
} else {
    $catatan .= "Tidak ditemukan catatan yang perlu diperbaiki secara khusus pada bulan ini. ";
}

// Bagian 3: Komentar Poin Pelanggaran
if ($total_poin_pelanggaran > 0) {
    $catatan .= "Tercatat ada " . $total_poin_pelanggaran . " poin pelanggaran yang menjadi bahan evaluasi bersama. ";
} else {
    if (count($poin_terburuk) == 0) {
        $catatan .= "Ananda juga tercatat tanpa pelanggaran pada bulan ini. ";
    }
}

// Bagian 4: Komentar Poin Reward (BARU)
if ($total_poin_reward > 0) {
    $catatan .= "Ananda juga berhasil mengumpulkan " . $total_poin_reward . " poin reward atas prestasi dan kontribusi positifnya. ";
}

// Penutup
$catatan .= "Semoga Ananda terus tumbuh menjadi pribadi yang disiplin, berakhlak baik, dan membawa kebaikan di sekitarnya.";

// 6. Kirim balik catatannya ke JavaScript
echo $catatan;
?>