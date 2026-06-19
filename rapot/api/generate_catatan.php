<?php
/**
 * rapot/api/generate_catatan.php
 * ─────────────────────────────────────────────────────────────────
 * Fungsi pembuat catatan rapor otomatis untuk Tahunan & Bulanan.
 * Menggunakan pendekatan rule-based untuk merangkai paragraf evaluatif
 * dengan bahasa yang lebih natural, nyambung, sopan, dan tidak berlebihan.
 * ─────────────────────────────────────────────────────────────────
 */

require_once __DIR__ . '/../../bootstrap/init.php';
require_once __DIR__ . '/../config/helper.php';

// =========================================================================
// 1. LIBRARY FUNGSI (Untuk Rapor Tahunan & Dukungan Bulanan)
// =========================================================================
if (!function_exists('generate_catatan_tahunan')) {

    function _label_mutu(string $aspek): string {
        return match($aspek) {
            'Ibadah'       => 'ibadah',
            'Akhlaq'       => 'akhlak dan adab',
            'Kedisiplinan' => 'kedisiplinan',
            'Kebersihan'   => 'kebersihan',
            default        => strtolower($aspek),
        };
    }

    function _avg_aspek(array $aspek): float {
        $vals = array_column($aspek['sub_mutu'] ?? [], 'nilai_final');
        if (empty($vals)) return 0;
        return array_sum($vals) / count($vals);
    }

    function _sub_terendah(array $aspek): ?array {
        $subs = $aspek['sub_mutu'] ?? [];
        if (empty($subs)) return null;
        usort($subs, fn($a, $b) => $a['nilai_final'] <=> $b['nilai_final']);
        return $subs[0];
    }

    function _sub_tertinggi(array $aspek): ?array {
        $subs = $aspek['sub_mutu'] ?? [];
        if (empty($subs)) return null;
        usort($subs, fn($a, $b) => $b['nilai_final'] <=> $a['nilai_final']);
        return $subs[0];
    }

    function _kalimat_per_mutu(array $aspek, float $avg): string {
        $label = _label_mutu($aspek['aspek']);
        $sub_rendah = _sub_terendah($aspek);
        $sub_nama_rendah = $sub_rendah ? strtolower($sub_rendah['nama']) : '';
        $nilai_rendah = $sub_rendah ? (float)$sub_rendah['nilai_final'] : 0;

        if ($avg >= 4.5) {
            return "Dalam hal {$label}, Ananda sudah menunjukkan kebiasaan yang sangat baik.";
        } elseif ($avg >= 3.5) {
            if ($nilai_rendah < 4 && $sub_nama_rendah) {
                return "Dalam hal {$label}, Ananda sudah cukup baik, meskipun kedisiplinannya pada poin {$sub_nama_rendah} masih bisa ditingkatkan lagi.";
            }
            return "Dalam hal {$label}, Ananda sudah menunjukkan perkembangan yang positif.";
        } elseif ($avg >= 2.5) {
            if ($sub_nama_rendah) {
                return "Untuk {$label}, Ananda masih membutuhkan bimbingan, khususnya dalam hal {$sub_nama_rendah}.";
            }
            return "Untuk {$label}, Ananda masih perlu membiasakan diri mengikuti rutinitas yang ada.";
        } else {
            return "Terkait {$label}, Ananda masih sangat membutuhkan perhatian dan bimbingan lebih lanjut.";
        }
    }

    function _kalimat_penutup(float $avg_global, int $total_pelanggaran, int $total_reward): string {
        $kalimat_reward = '';
        if ($total_reward > 0) {
            $kalimat_reward = " Kami juga sangat mengapresiasi pencapaian {$total_reward} poin reward yang Ananda dapatkan.";
        }
        $kalimat_pelanggaran = '';
        if ($total_pelanggaran > 20) {
            $kalimat_pelanggaran = " Namun, catatan pelanggaran sebesar {$total_pelanggaran} poin perlu menjadi bahan evaluasi serius agar Ananda bisa lebih disiplin ke depannya.";
        } elseif ($total_pelanggaran > 0) {
            $kalimat_pelanggaran = " Terdapat juga catatan pelanggaran sebesar {$total_pelanggaran} poin yang perlu kita jadikan bahan evaluasi bersama.";
        }

        if ($avg_global >= 4.5) {
            return "Secara keseluruhan, perkembangan Ananda selama setahun ini sangat baik.{$kalimat_reward}{$kalimat_pelanggaran} Semoga Ananda bisa terus mempertahankan kebiasaan baiknya.";
        } elseif ($avg_global >= 3.5) {
            return "Secara keseluruhan, Ananda telah mengikuti proses belajar dan pembinaan dengan baik.{$kalimat_reward}{$kalimat_pelanggaran} Harapan kami, Ananda bisa terus berkembang menjadi lebih baik lagi.";
        } elseif ($avg_global >= 2.5) {
            return "Secara umum, Ananda masih memerlukan adaptasi dan motivasi lebih.{$kalimat_reward}{$kalimat_pelanggaran} Kami memohon kerja sama Bapak/Ibu untuk ikut memotivasi Ananda dari rumah.";
        } else {
            return "Perkembangan Ananda tahun ini masih banyak yang perlu dievaluasi.{$kalimat_reward}{$kalimat_pelanggaran} Diperlukan kerja sama yang erat antara pembina dan orang tua demi kebaikan Ananda.";
        }
    }

    function generate_catatan_tahunan(
        array  $nilai_aspek,
        int    $total_pelanggaran = 0,
        int    $total_reward      = 0,
        string $nama_santri       = ''
    ): string {
        if (empty($nilai_aspek)) return 'Catatan belum tersedia.';

        $kalimat = [];
        $sapaan = $nama_santri ? "Ananda {$nama_santri}" : "Ananda";
        $kalimat[] = "Alhamdulillah, {$sapaan} telah menyelesaikan masa pendidikannya selama satu tahun ini.";

        $semua_avg = [];
        foreach ($nilai_aspek as $aspek) {
            $avg = _avg_aspek($aspek);
            $semua_avg[] = $avg;
            $kalimat[] = _kalimat_per_mutu($aspek, $avg);
        }

        $avg_global = !empty($semua_avg) ? array_sum($semua_avg) / count($semua_avg) : 0;
        $kalimat[] = _kalimat_penutup($avg_global, $total_pelanggaran, $total_reward);

        return implode(' ', $kalimat);
    }

    function generate_catatan_per_aspek(array $aspek): string {
        $avg = _avg_aspek($aspek);
        $label = _label_mutu($aspek['aspek']);
        $sub_rendah = _sub_terendah($aspek);
        $sub_tinggi = _sub_tertinggi($aspek);

        $nama_rendah = $sub_rendah ? strtolower($sub_rendah['nama']) : '';
        $nama_tinggi = $sub_tinggi ? strtolower($sub_tinggi['nama']) : '';
        $val_rendah = $sub_rendah ? (float)$sub_rendah['nilai_final'] : 0;
        $val_tinggi = $sub_tinggi ? (float)$sub_tinggi['nilai_final'] : 0;

        if ($avg >= 4.5) {
            return "Perkembangan {$label} Ananda sangat memuaskan, terutama kebiasaannya dalam hal {$nama_tinggi} yang patut dipertahankan.";
        } elseif ($avg >= 3.5) {
            if ($val_rendah < $val_tinggi && $nama_rendah) {
                return "Secara umum sudah baik, hanya saja Ananda perlu lebih disiplin lagi dalam hal {$nama_rendah}.";
            }
            return "Ananda sudah menunjukkan usaha yang bagus dalam menjaga {$label}-nya.";
        } elseif ($avg >= 2.5) {
            if ($nama_rendah) {
                return "Masih perlu evaluasi dan bimbingan, khususnya terkait kebiasaan {$nama_rendah} yang perlu diperbaiki.";
            }
            return "Ananda masih perlu dimotivasi untuk lebih mematuhi aturan dan arahan pembina.";
        } else {
            return "Sangat membutuhkan bimbingan intensif dan pengawasan lebih dari para pembina.";
        }
    }
}

// =========================================================================
// 2. ENDPOINT API (Untuk Rapor Bulanan)
// Menerima $_POST dari form bulanan dan mencetak catatan
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (!isset($_POST['lisan']) && !isset($_POST['sholat_duha'])) {
        exit;
    }

    guard('catatan_otomatis');

    $santri_id = (int)($_POST['santri_id'] ?? 0);
    $bulan     = $_POST['bulan'] ?? '';
    $tahun     = (int)($_POST['tahun'] ?? 0);

    $total_poin_pelanggaran = 0;
    $total_poin_reward = 0;
    
    if ($santri_id && $bulan && $tahun) {
        $sql_pel = "SELECT SUM(jp.poin) as poin FROM pelanggaran p JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id WHERE p.santri_id = ? AND MONTH(p.tanggal) = FIND_IN_SET(?, 'Januari,Februari,Maret,April,Mei,Juni,Juli,Agustus,September,Oktober,November,Desember') AND YEAR(p.tanggal) = ? AND jp.poin > 0";
        $stmt_pel = $conn->prepare($sql_pel);
        if ($stmt_pel) {
            $stmt_pel->bind_param("isi", $santri_id, $bulan, $tahun);
            $stmt_pel->execute();
            $res = $stmt_pel->get_result();
            if ($res) $total_poin_pelanggaran = (int)($res->fetch_assoc()['poin'] ?? 0);
            $stmt_pel->close();
        }

        $sql_rew = "SELECT SUM(jr.poin_reward) as poin FROM daftar_reward dr JOIN jenis_reward jr ON dr.jenis_reward_id = jr.id WHERE dr.santri_id = ? AND MONTH(dr.tanggal) = FIND_IN_SET(?, 'Januari,Februari,Maret,April,Mei,Juni,Juli,Agustus,September,Oktober,November,Desember') AND YEAR(dr.tanggal) = ? AND jr.poin_reward > 0";
        $stmt_rew = $conn->prepare($sql_rew);
        if ($stmt_rew) {
            $stmt_rew->bind_param("isi", $santri_id, $bulan, $tahun);
            $stmt_rew->execute();
            $res = $stmt_rew->get_result();
            if ($res) $total_poin_reward = (int)($res->fetch_assoc()['poin'] ?? 0);
            $stmt_rew->close();
        }
    }

    $nama_penilaian = [
        'puasa_sunnah' => 'puasa sunnah', 'sholat_duha' => 'sholat dhuha', 'sholat_malam' => 'sholat malam',
        'sedekah' => 'sedekah/berbagi', 'sunnah_tidur' => 'adab tidur', 'ibadah_lainnya' => 'ibadah lainnya',
        'lisan' => 'menjaga lisan', 'sikap' => 'tingkah laku', 'kesopanan' => 'kesopanan', 'muamalah' => 'muamalah',
        'tidur' => 'disiplin waktu tidur', 'keterlambatan' => 'kedisiplinan waktu', 'seragam' => 'kerapihan seragam',
        'makan' => 'adab makan', 'arahan' => 'kepatuhan', 'bahasa_arab' => 'berbahasa Arab',
        'mandi' => 'kebersihan diri', 'penampilan' => 'penampilan', 'piket' => 'tanggung jawab piket', 'kerapihan_barang' => 'kerapihan barang'
    ];

    $nilai = $_POST;
    $poin_terbaik = [];
    $poin_terburuk = [];
    $total_skor = 0;
    $count_skor = 0;

    foreach ($nama_penilaian as $key => $label) {
        if (!isset($nilai[$key])) continue;
        $skor = (int)$nilai[$key];
        $total_skor += $skor;
        $count_skor++;
        
        if ($skor >= 4) {
            $poin_terbaik[$key] = $skor;
        } elseif ($skor <= 2 && $skor > 0) {
            $poin_terburuk[$key] = $skor;
        }
    }

    arsort($poin_terbaik);
    asort($poin_terburuk);

    $list_terbaik = array_slice(array_keys($poin_terbaik), 0, 2);
    $list_terbaik_label = array_map(fn($k) => strtolower($nama_penilaian[$k]), $list_terbaik);
    
    $list_terburuk = array_slice(array_keys($poin_terburuk), 0, 2);
    $list_terburuk_label = array_map(fn($k) => strtolower($nama_penilaian[$k]), $list_terburuk);

    $avg = $count_skor > 0 ? $total_skor / $count_skor : 0;

    // Merangkai kalimat secara mengalir (natural flow)
    $catatan = "Alhamdulillah, perkembangan Ananda pada bulan ini secara umum terbilang ";
    if ($avg >= 4) {
        $catatan .= "sangat baik.";
    } elseif ($avg >= 3) {
        $catatan .= "cukup baik.";
    } else {
        $catatan .= "kurang maksimal dan perlu dievaluasi.";
    }

    if (!empty($list_terbaik)) {
        $catatan .= " Ada beberapa hal positif yang patut dipertahankan, terutama dalam hal " . implode(" dan ", $list_terbaik_label) . ".";
    }

    if (!empty($list_terburuk)) {
        $catatan .= " Namun demikian, Ananda masih perlu lebih memperhatikan masalah " . implode(" serta ", $list_terburuk_label) . " ke depannya.";
    }

    if ($total_poin_pelanggaran > 0) {
        $catatan .= " Selain itu, tercatat adanya pelanggaran sebesar {$total_poin_pelanggaran} poin yang perlu menjadi bahan evaluasi kita bersama.";
    }
    
    if ($total_poin_reward > 0) {
        $catatan .= " Di sisi lain, kami sangat mengapresiasi perolehan {$total_poin_reward} poin reward yang didapatkan Ananda bulan ini.";
    }

    $catatan .= " Kami memohon kerja sama Bapak/Ibu untuk terus memotivasi Ananda agar senantiasa istiqomah dalam kebaikan.";

    echo trim($catatan);
    exit;
}