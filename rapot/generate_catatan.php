<?php
// File: rekap-mukholif/rapot/generate_catatan.php

// Panggil 'Otak' aplikasi & helper
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/helper.php';

// Cek SATPAM (pastiin yg akses ini user yg login)
guard('rapot_create'); // Pake izin yang sama kayak create

// 1. Ambil data utama dari AJAX
$santri_id = (int)($_POST['santri_id'] ?? 0);
$bulan = $_POST['bulan'] ?? '';
$tahun = (int)($_POST['tahun'] ?? 0);

// Validasi data utama
if (empty($santri_id) || empty($bulan) || empty($tahun)) {
    die('Error: Data santri, bulan, atau tahun tidak lengkap.');
}

// 2. Kalkulasi Total Poin Pelanggaran (Logika dari process.php)
$total_poin_pelanggaran = 0;
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
    // Kalo query gagal, biarin poinnya 0. Gak usah 'die()'.
    // error_log('Gagal ambil poin di generate_catatan: ' . $e->getMessage());
}


// 3. Siapin "kamus" buat nerjemahin 'key' jadi 'Nama'
$nama_penilaian = [
    'puasa_sunnah' => 'Puasa Sunnah', 'sholat_duha' => 'Sholat Duha', 'sholat_malam' => 'Sholat Malam',
    'sedekah' => 'Sedekah & Berbagi', 'sunnah_tidur' => 'Sunnah sebelum tidur', 'ibadah_lainnya' => 'Ibadah lainnya',
    'lisan' => 'Lisan', 'sikap' => 'Sikap & tingkah laku', 'kesopanan' => 'Kesopanan', 'muamalah' => 'Muamalah',
    'tidur' => 'Tidur', 'keterlambatan' => 'Keterlambatan', 'seragam' => 'Seragam',
    'makan' => 'Makan', 'arahan' => 'Mengikuti arahan', 'bahasa_arab' => 'Berbahasa arab',
    'mandi' => 'Mandi', 'penampilan' => 'Penampilan', 'piket' => 'Piket', 'kerapihan_barang' => 'Kerapihan barang'
];

// 4. Ambil data nilai dari AJAX (jQuery)
$nilai = $_POST;
$poin_terbaik = [];
$poin_terburuk = [];

// Pisahin mana nilai bagus (5) dan nilai jelek (1 atau 2)
foreach ($nilai as $key => $val) {
    if (!isset($nama_penilaian[$key])) continue; // Lewatin 'santri_id', 'bulan', 'tahun'
    
    $skor = (int)$val;
    
    if ($skor == 5) {
        $poin_terbaik[] = $nama_penilaian[$key]; // Simpen namanya
    } elseif ($skor <= 2 && $skor > 0) {
        $poin_terburuk[] = $nama_penilaian[$key]; // Simpen namanya
    }
}

// 5. Bikin kalimat catatannya
$catatan = "";

// --- Bagian 1: Komentar soal Penilaian Baik (ibadah, akhlaq, dll) ---
if (count($poin_terbaik) > 0) {
    $list_terbaik = array_slice($poin_terbaik, 0, 3);
    $catatan .= "Ananda menunjukkan hasil yang sangat baik, terutama dalam hal " . implode(', ', $list_terbaik) . ". ";
    if (count($poin_terbaik) > 3) {
        $catatan .= "Dan beberapa aspek lainnya. ";
    }
} else {
    $catatan .= "Ananda sudah cukup baik dalam mengikuti kegiatan. ";
}

// --- Bagian 2: Komentar soal Penilaian Jelek ---
if (count($poin_terburuk) > 0) {
    $list_terburuk = array_slice($poin_terburuk, 0, 2);
    $catatan .= "Namun, masih perlu banyak peningkatan dan perhatian khusus pada aspek " . implode(', ', $list_terburuk) . ". ";
} else {
    // Kalo gak ada nilai jelek
    if (count($poin_terbaik) > 15) { // Anggep "sempurna"
        $catatan .= "Secara keseluruhan sudah sangat konsisten. ";
    } else {
        $catatan .= "Tidak ada catatan kepengasuhan yang menonjol untuk diperbaiki. ";
    }
}

// ==========================================================
//           PERUBAHAN LOGIKA DI SINI
// --- Bagian 3: Komentar soal Poin Pelanggaran ---
// ==========================================================
if ($total_poin_pelanggaran > 0) {
    $catatan .= "Dari sisi pelanggaran, Ananda memiliki " . $total_poin_pelanggaran . " poin di bulan ini yang perlu menjadi perhatian serius. ";
} else {
    // Kalo gak ada poin DAN gak ada nilai jelek
    if (count($poin_terburuk) == 0) {
         $catatan .= "Ananda juga tercatat bersih dari poin pelanggaran di bulan ini, Masya Allah. ";
    }
}
// ==========================================================

// Kalimat penutup
$catatan .= "Mohon agar terus dipertahankan dan ditingkatkan lagi.";

// 6. Kirim balik catatannya ke JavaScript
echo $catatan;
?>