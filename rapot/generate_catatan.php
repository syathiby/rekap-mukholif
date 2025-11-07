<?php
// File: rekap-mukholif/rapot/generate_catatan.php

// Panggil 'Otak' aplikasi & helper
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/helper.php';

// Cek SATPAM (pastikan yang akses ini user yang login)
guard('rapot_create'); // Pake izin yang sama kayak create

// 1. Ambil data utama dari AJAX
$santri_id = (int)($_POST['santri_id'] ?? 0);
$bulan = $_POST['bulan'] ?? '';
$tahun = (int)($_POST['tahun'] ?? 0);

// Validasi data utama
if (empty($santri_id) || empty($bulan) || empty($tahun)) {
    die('Error: Data santri, bulan, atau tahun tidak lengkap.');
}

// 2. Kalkulasi Total Poin Pelanggaran
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
    // Kalau query gagal, biarkan poinnya 0.
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

// Penutup
$catatan .= "Semoga Ananda terus tumbuh menjadi pribadi yang disiplin, berakhlak baik, dan membawa kebaikan di sekitarnya.";

// 6. Kirim balik catatannya ke JavaScript
echo $catatan;
?>