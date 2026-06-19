<?php
// rapot/crud_tahunan/koreksi_nilai.php
// Fungsi untuk menyesuaikan nilai secara cerdas (dinamis) berdasarkan Poin Pelanggaran dan Reward

/**
 * Mengambil rekap pelanggaran per santri dalam 1 periode tahunan
 */
function fetch_pelanggaran_tahunan($conn, $santri_id, $tahun_awal, $tahun_akhir) {
    $stmt = $conn->prepare("
        SELECT jp.nama_pelanggaran, SUM(jp.poin) as total_poin, COUNT(*) as jumlah
        FROM pelanggaran p
        JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id
        WHERE p.santri_id = ?
          AND (YEAR(p.tanggal) = ? OR YEAR(p.tanggal) = ?)
        GROUP BY jp.id, jp.nama_pelanggaran
    ");
    $stmt->bind_param('iii', $santri_id, $tahun_awal, $tahun_akhir);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $result;
}

/**
 * Mengambil rekap reward per santri dalam 1 periode tahunan
 */
function fetch_reward_tahunan($conn, $santri_id, $tahun_awal, $tahun_akhir) {
    $stmt = $conn->prepare("
        SELECT jr.nama_reward, SUM(jr.poin_reward) as total_poin, COUNT(*) as jumlah
        FROM daftar_reward rwd
        JOIN jenis_reward jr ON rwd.jenis_reward_id = jr.id
        WHERE rwd.santri_id = ?
          AND (YEAR(rwd.tanggal) = ? OR YEAR(rwd.tanggal) = ?)
        GROUP BY jr.id, jr.nama_reward
    ");
    $stmt->bind_param('iii', $santri_id, $tahun_awal, $tahun_akhir);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $result;
}

/**
 * Pemetaan cerdas berbasis keyword ke [Mutu, Sub Mutu] yang sangat spesifik
 */
function map_keyword_to_aspek($nama_kegiatan) {
    $nama = strtolower(trim($nama_kegiatan));
    
    // Pemetaan berurutan dari yang paling spesifik ke umum
    $mapping = [
        // Ibadah
        'puasa'     => ['Ibadah', 'Puasa Sunnah'],
        'dhuha'     => ['Ibadah', 'Sholat Duha'],
        'tahajud'   => ['Ibadah', 'Sholat Malam'],
        'malam'     => ['Ibadah', 'Sholat Malam'],
        'sedekah'   => ['Ibadah', 'Sedekah & Berbagi'],
        'infaq'     => ['Ibadah', 'Sedekah & Berbagi'],
        'berbagi'   => ['Ibadah', 'Sedekah & Berbagi'],
        'sunnah'    => ['Ibadah', 'Sunnah sebelum tidur'],
        
        // Ibadah Fallback / Lainnya
        'sholat'    => ['Ibadah', 'Ibadah lainnya'],
        'masjid'    => ['Ibadah', 'Ibadah lainnya'],
        'jamaah'    => ['Ibadah', 'Ibadah lainnya'],
        'tilawah'   => ['Ibadah', 'Ibadah lainnya'],
        'ngaji'     => ['Ibadah', 'Ibadah lainnya'],
        'quran'     => ['Ibadah', 'Ibadah lainnya'],
        'hafal'     => ['Ibadah', 'Ibadah lainnya'],
        'setor'     => ['Ibadah', 'Ibadah lainnya'],

        // Akhlaq
        'lisan'     => ['Akhlaq', 'Lisan'],
        'bohong'    => ['Akhlaq', 'Lisan'],
        'ejek'      => ['Akhlaq', 'Lisan'],
        'bully'     => ['Akhlaq', 'Lisan'],
        'sopan'     => ['Akhlaq', 'Kesopanan'],
        'adab'      => ['Akhlaq', 'Kesopanan'],
        'lawan jenis'=> ['Akhlaq', 'Kesopanan'],
        'muamalah'  => ['Akhlaq', 'Muamalah'],
        'curi'      => ['Akhlaq', 'Muamalah'],
        'kelahi'    => ['Akhlaq', 'Muamalah'],
        'jujur'     => ['Akhlaq', 'Muamalah'],

        // Akhlaq Sikap & Fallback
        'sikap'     => ['Akhlaq', 'Sikap & tingkah laku'],
        'kasar'     => ['Akhlaq', 'Sikap & tingkah laku'],
        'bangkang'  => ['Akhlaq', 'Sikap & tingkah laku'],
        'lawan'     => ['Akhlaq', 'Sikap & tingkah laku'],
        'rokok'     => ['Akhlaq', 'Sikap & tingkah laku'],
        'zina'      => ['Akhlaq', 'Sikap & tingkah laku'],
        'pacar'     => ['Akhlaq', 'Sikap & tingkah laku'],
        'bantu'     => ['Akhlaq', 'Sikap & tingkah laku'],
        'tolong'    => ['Akhlaq', 'Sikap & tingkah laku'],
        'inisiatif' => ['Akhlaq', 'Sikap & tingkah laku'],
        'juara'     => ['Akhlaq', 'Sikap & tingkah laku'],
        'prestasi'  => ['Akhlaq', 'Sikap & tingkah laku'],
        'lomba'     => ['Akhlaq', 'Sikap & tingkah laku'],

        // Kedisiplinan
        'telat'     => ['Kedisiplinan', 'Keterlambatan'],
        'terlambat' => ['Kedisiplinan', 'Keterlambatan'],
        'seragam'   => ['Kedisiplinan', 'Seragam'],
        'pakaian'   => ['Kedisiplinan', 'Seragam'],
        'makan'     => ['Kedisiplinan', 'Makan'],
        'arahan'    => ['Kedisiplinan', 'Mengikuti arahan'],
        'kabur'     => ['Kedisiplinan', 'Mengikuti arahan'],
        'keluar'    => ['Kedisiplinan', 'Mengikuti arahan'],
        'bolos'     => ['Kedisiplinan', 'Mengikuti arahan'],
        'hp'        => ['Kedisiplinan', 'Mengikuti arahan'],
        'gadget'    => ['Kedisiplinan', 'Mengikuti arahan'],
        'arab'      => ['Kedisiplinan', 'Berbahasa arab di kamar'],
        
        // Kedisiplinan Tidur & Fallback
        'tidur'     => ['Kedisiplinan', 'Tidur'],
        'begadang'  => ['Kedisiplinan', 'Tidur'],

        // Kebersihan
        'mandi'     => ['Kebersihan', 'Mandi'],
        'bau'       => ['Kebersihan', 'Mandi'],
        'penampilan'=> ['Kebersihan', 'Penampilan & berpakaian'],
        'piket'     => ['Kebersihan', 'Piket'],
        'bersih'    => ['Kebersihan', 'Piket'],
        'kotor'     => ['Kebersihan', 'Piket'],
        'sampah'    => ['Kebersihan', 'Piket'],
        'jemur'     => ['Kebersihan', 'Piket'],
        'rapi'      => ['Kebersihan', 'Kerapihan barang'],
        'barang'    => ['Kebersihan', 'Kerapihan barang'],
    ];

    foreach ($mapping as $keyword => $target) {
        if (strpos($nama, $keyword) !== false) {
            return $target;
        }
    }
    
    // Jika tidak ada keyword yang cocok, masukkan ke kategori umum "Akhlaq" -> "Sikap & tingkah laku"
    return ['Akhlaq', 'Sikap & tingkah laku']; 
}

/**
 * Fungsi utama untuk menerapkan penambahan dan pengurangan nilai secara otomatis.
 * Dieksekusi saat klik "Generate Ulang".
 */
function apply_koreksi_nilai($conn, $santri_id, $tahun_awal, $tahun_akhir, &$nilai_snapshot) {
    $pelanggaran = fetch_pelanggaran_tahunan($conn, $santri_id, $tahun_awal, $tahun_akhir);
    $rewards     = fetch_reward_tahunan($conn, $santri_id, $tahun_awal, $tahun_akhir);

    if (empty($pelanggaran) && empty($rewards)) {
        return; // Tidak ada aktivitas tambahan
    }

    // Array penampung perubahan nilai (bisa plus / minus)
    // Format: $adjustments["Aspek|SubMutu"] = ['skala' => 0, 'alasan_plus' => [], 'alasan_minus' => []]
    $adjustments = [];

    // --- 1. Kalkulasi Potongan dari Pelanggaran ---
    foreach ($pelanggaran as $p) {
        $nama = $p['nama_pelanggaran'];
        $poin = (int)$p['total_poin'];
        $jml  = (int)$p['jumlah'];
        
        // Aturan Konversi: Setiap 10 Poin Pelanggaran = -0.2 Skala Nilai (Berlaku kelipatan desimal, 1 Poin = -0.02)
        $potongan_skala = round($poin * 0.02, 2);
        
        if ($potongan_skala > 0) {
            [$target_aspek, $target_sub] = map_keyword_to_aspek($nama);
            $key = $target_aspek . '|' . $target_sub;
            
            if (!isset($adjustments[$key])) {
                $adjustments[$key] = ['skala' => 0, 'alasan_plus' => [], 'alasan_minus' => []];
            }
            
            $adjustments[$key]['skala'] -= $potongan_skala;
            $adjustments[$key]['alasan_minus'][] = "{$jml}x {$nama} (-{$poin} poin)";
        }
    }

    // --- 2. Kalkulasi Penambahan dari Reward ---
    foreach ($rewards as $r) {
        $nama = $r['nama_reward'];
        $poin = (int)$r['total_poin'];
        $jml  = (int)$r['jumlah'];
        
        // Aturan Konversi: Setiap 10 Poin Reward = +0.2 Skala Nilai (Berlaku kelipatan desimal, 1 Poin = +0.02)
        $tambahan_skala = round($poin * 0.02, 2);
        
        if ($tambahan_skala > 0) {
            [$target_aspek, $target_sub] = map_keyword_to_aspek($nama);
            $key = $target_aspek . '|' . $target_sub;
            
            if (!isset($adjustments[$key])) {
                $adjustments[$key] = ['skala' => 0, 'alasan_plus' => [], 'alasan_minus' => []];
            }
            
            $adjustments[$key]['skala'] += $tambahan_skala;
            $adjustments[$key]['alasan_plus'][] = "{$jml}x {$nama} (+{$poin} poin)";
        }
    }

    // --- 3. Terapkan Skala Perubahan ke Snapshot Rapot ---
    if (!empty($adjustments)) {
        foreach ($nilai_snapshot as &$aspek_data) {
            $nama_aspek = $aspek_data['aspek'];
            
            foreach ($aspek_data['sub_mutu'] as &$sub) {
                $nama_sub = $sub['nama'];
                
                // Cari key yang cocok di dictionary (case-insensitive)
                $matched_key = null;
                foreach ($adjustments as $k => $v) {
                    [$d_aspek, $d_sub] = explode('|', $k);
                    if (strcasecmp($d_aspek, $nama_aspek) === 0 && strcasecmp($d_sub, $nama_sub) === 0) {
                        $matched_key = $k;
                        break;
                    }
                }

                if ($matched_key) {
                    $adj = $adjustments[$matched_key];
                    $total_change = $adj['skala'];
                    
                    if ($total_change != 0) {
                        $nilai_awal = (float)$sub['nilai_final'];
                        $nilai_baru = $nilai_awal + $total_change;
                        
                        // Batas Nilai Rapor adalah 1.0 (Minimum) sampai 5.0 (Maksimum)
                        if ($nilai_baru < 1.0) $nilai_baru = 1.0;
                        if ($nilai_baru > 5.0) $nilai_baru = 5.0;
                        
                        // Cek apakah benar-benar ada perubahan nilai setelah dibulatkan ke 1 desimal
                        if (round($nilai_baru, 1) != round($nilai_awal, 1)) {
                            $sub['nilai_final'] = round($nilai_baru, 1);
                            $sub['ada_koreksi'] = true;
                            
                            $alasan = [];
                            if (!empty($adj['alasan_plus'])) {
                                $alasan[] = "Ditambah berkat: " . implode(", ", $adj['alasan_plus']);
                            }
                            if (!empty($adj['alasan_minus'])) {
                                $alasan[] = "Dikurangi akibat: " . implode(", ", $adj['alasan_minus']);
                            }
                            
                            $sub['alasan_koreksi'] = implode(" | ", $alasan);
                        }
                    }
                }
            }
        }
    }
}
