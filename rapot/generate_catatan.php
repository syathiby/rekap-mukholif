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
    // Kalau query gagal, biarkan poinnya 0. Tidak perlu hentikan eksekusi.
    // error_log('Gagal ambil poin di generate_catatan: ' . $e->getMessage());
}


// 3. Siapkan "kamus" untuk menerjemahkan 'key' menjadi 'Nama Penilaian'
$nama_penilaian = [
    'puasa_sunnah' => 'Puasa Sunnah', 'sholat_duha' => 'Sholat Duha', 'sholat_malam' => 'Sholat Malam',
    'sedekah' => 'Sedekah & Berbagi', 'sunnah_tidur' => 'Sunnah sebelum tidur', 'ibadah_lainnya' => 'Ibadah lainnya',
    'lisan' => 'Lisan', 'sikap' => 'Sikap & Tingkah Laku', 'kesopanan' => 'Kesopanan', 'muamalah' => 'Muamalah',
    'tidur' => 'Tidur', 'keterlambatan' => 'Keterlambatan', 'seragam' => 'Seragam',
    'makan' => 'Makan', 'arahan' => 'Mengikuti Arahan', 'bahasa_arab' => 'Berbahasa Arab',
    'mandi' => 'Mandi', 'penampilan' => 'Penampilan', 'piket' => 'Piket', 'kerapihan_barang' => 'Kerapihan Barang'
];

// 4. Ambil data nilai dari AJAX (jQuery)
$nilai = $_POST;
$poin_terbaik = [];
$poin_terburuk = [];

// Pisahkan mana nilai tinggi (5) dan nilai rendah (1 atau 2)
foreach ($nilai as $key => $val) {
    if (!isset($nama_penilaian[$key])) continue; // Lewati data selain penilaian
    
    $skor = (int)$val;
    
    if ($skor == 5) {
        $poin_terbaik[] = $nama_penilaian[$key];
    } elseif ($skor <= 2 && $skor > 0) {
        $poin_terburuk[] = $nama_penilaian[$key];
    }
}

// 5. Buat kalimat catatan
$catatan = "";

// --- Bagian 1: Komentar Penilaian Baik ---
if (count($poin_terbaik) > 0) {
    $list_terbaik = array_slice($poin_terbaik, 0, 3);
    $catatan .= "Ananda menunjukkan perkembangan yang baik, terutama dalam aspek " . implode(', ', $list_terbaik) . ". ";
    if (count($poin_terbaik) > 3) {
        $catatan .= "Dan juga beberapa aspek lainnya. ";
    }
} else {
    $catatan .= "Ananda sudah menunjukkan usaha yang cukup baik dalam mengikuti kegiatan sehari-hari. ";
}

// --- Bagian 2: Komentar Penilaian Kurang ---
if (count($poin_terburuk) > 0) {
    $list_terburuk = array_slice($poin_terburuk, 0, 2);
    $catatan .= "Namun masih perlu perhatian dan pembiasaan lebih baik pada aspek " . implode(', ', $list_terburuk) . ". ";
} else {
    if (count($poin_terbaik) > 15) {
        $catatan .= "Secara keseluruhan, Ananda sangat konsisten dan menunjukkan kedisiplinan yang baik. ";
    } else {
        $catatan .= "Tidak ditemukan hal yang perlu menjadi perhatian khusus pada bulan ini. ";
    }
}

// ==========================================================
//           BAGIAN 3: Komentar Soal Poin Pelanggaran
// ==========================================================
if ($total_poin_pelanggaran > 0) {
    $catatan .= "Tercatat ada " . $total_poin_pelanggaran . " poin pelanggaran pada bulan ini yang perlu menjadi bahan evaluasi dan pembinaan lebih lanjut. ";
} else {
    if (count($poin_terburuk) == 0) {
         $catatan .= "Ananda juga tercatat tanpa pelanggaran pada bulan ini. ";
    }
}

// Kalimat penutup
$catatan .= "Semoga Ananda dapat terus mempertahankan dan meningkatkan pencapaian dan juga karakternya.";

// 6. Kirim balik catatannya ke JavaScript
echo $catatan;
?>