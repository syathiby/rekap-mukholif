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

    function _kalimat_per_mutu(array $aspek, float $avg, int $index): string {
        $label = _label_mutu($aspek['aspek']);
        $sub_rendah = _sub_terendah($aspek);
        $sub_nama_rendah = $sub_rendah ? strtolower($sub_rendah['nama']) : '';
        $nilai_rendah = $sub_rendah ? (float)$sub_rendah['nilai_final'] : 0;
        
        $sub_tinggi = _sub_tertinggi($aspek);
        $sub_nama_tinggi = $sub_tinggi ? strtolower($sub_tinggi['nama']) : '';

        // Variasi pembuka (agar tidak selalu "Dalam hal...")
        $pembuka = [
            "Terkait aspek {$label},",
            "Dalam hal {$label},",
            "Mengenai perkembangan {$label},",
            "Untuk aspek {$label},"
        ];
        // Pilih pembuka berdasarkan index agar bervariasi antar mutu
        $awalan = $pembuka[$index % count($pembuka)];
        
        // Transisi kalimat jika bukan poin pertama
        $transisi = "";
        if ($index > 0) {
            $transisi_list = ["Selain itu, ", "Di samping itu, ", "Sementara itu, ", "Adapun "];
            $transisi = $transisi_list[$index % count($transisi_list)];
            $awalan = strtolower($awalan);
        }

        if ($avg >= 4.5) {
            $kalimat = [
                "Ananda telah membiasakan diri dengan sangat konsisten dan membanggakan.",
                "Ananda menunjukkan komitmen yang patut diapresiasi.",
                "Ananda mampu mempertahankan rutinitas yang amat positif sepanjang tahun ini.",
                "kami melihat perkembangan yang sangat menjanjikan dari diri Ananda."
            ];
            $isi = $kalimat[$index % count($kalimat)];
            return "{$transisi}{$awalan} {$isi}";
        } elseif ($avg >= 3.5) {
            if ($nilai_rendah < 4 && $sub_nama_rendah) {
                $template_baik_tapi_kurang = [
                    "{$transisi}{$awalan} perkembangan Ananda sudah terbilang baik, meskipun pada bagian {$sub_nama_rendah} masih memerlukan pembiasaan yang lebih konsisten.",
                    "{$transisi}{$awalan} ada banyak kemajuan yang menggembirakan, namun Ananda masih perlu sedikit lebih fokus dalam hal {$sub_nama_rendah}.",
                    "{$transisi}{$awalan} Ananda secara umum telah menunjukkan kesungguhan yang baik, hanya saja kebiasaan {$sub_nama_rendah} perlu lebih diperhatikan.",
                    "{$transisi}{$awalan} perkembangannya cukup menggembirakan, kendati demikian pendampingan pada aspek {$sub_nama_rendah} masih tetap diperlukan."
                ];
                return $template_baik_tapi_kurang[$index % count($template_baik_tapi_kurang)];
            }
            $kalimat = [
                "Ananda terus menunjukkan perkembangan yang positif dan layak dipertahankan.",
                "Ananda sudah menunjukkan progres yang baik dan perlu terus dijaga konsistensinya.",
                "Ananda secara perlahan mulai terbiasa dengan rutinitas dan aturan yang ada."
            ];
            $isi = $kalimat[$index % count($kalimat)];
            return "{$transisi}{$awalan} {$isi}";
        } elseif ($avg >= 2.5) {
            if ($sub_nama_rendah) {
                $template_kurang = [
                    "{$transisi}{$awalan} Ananda masih memerlukan pendampingan yang konsisten, khususnya dalam hal {$sub_nama_rendah}.",
                    "{$transisi}{$awalan} kesadaran Ananda perlu terus ditumbuhkan, terutama dalam urusan {$sub_nama_rendah}.",
                    "{$transisi}{$awalan} perhatian lebih diperlukan agar kebiasaan {$sub_nama_rendah} bisa semakin baik ke depannya."
                ];
                return $template_kurang[$index % count($template_kurang)];
            }
            return "{$transisi}{$awalan} Ananda masih perlu lebih banyak dukungan dan motivasi untuk mengikuti rutinitas secara mandiri.";
        } else {
            return "{$transisi}pada aspek {$label} ini, Ananda masih memerlukan perhatian dan bimbingan yang lebih intensif dari berbagai pihak agar dapat berkembang lebih baik.";
        }
    }

    function _kalimat_penutup(float $avg_global, int $total_pelanggaran, int $total_reward): string {
        $kalimat_reward = '';
        if ($total_reward > 0) {
            $kalimat_reward = " Kami juga turut bersyukur atas capaian positif Ananda yang berhasil meraih {$total_reward} poin reward, sebuah bukti nyata potensi yang dimilikinya.";
        }
        $kalimat_pelanggaran = '';
        if ($total_pelanggaran > 20) {
            $kalimat_pelanggaran = " Meski demikian, catatan pelanggaran sejumlah {$total_pelanggaran} poin perlu menjadi bahan evaluasi bersama agar ke depannya Ananda dapat lebih disiplin.";
        } elseif ($total_pelanggaran > 0) {
            $kalimat_pelanggaran = " Tentu saja, adanya catatan pelanggaran sebanyak {$total_pelanggaran} poin tetap perlu menjadi perhatian kita bersama agar tidak terulang kembali.";
        }

        if ($avg_global >= 4.5) {
            return "Secara umum, perjalanan Ananda di pesantren selama setahun ini sungguh membanggakan dan patut disyukuri bersama.{$kalimat_reward}{$kalimat_pelanggaran} Harapan besar kami, semoga Ananda senantiasa istiqamah merawat kebiasaan-kebiasaan mulia ini dan menjadi teladan yang baik bagi teman-temannya.";
        } elseif ($avg_global >= 3.5) {
            return "Secara keseluruhan, Ananda berhasil melewati masa pembinaan tahun ini dengan catatan yang cukup menggembirakan.{$kalimat_reward}{$kalimat_pelanggaran} Besar harapan kami agar Ananda terus semangat dalam memperbaiki diri dan meraih capaian yang lebih baik di tahun ajaran mendatang.";
        } elseif ($avg_global >= 2.5) {
            return "Secara garis besar, proses pembinaan Ananda masih menemui beberapa tantangan yang perlu disikapi dengan bijak.{$kalimat_reward}{$kalimat_pelanggaran} Untuk itu, dukungan dan kerja sama yang aktif antara pihak asrama dan Bapak/Ibu di rumah sangatlah kami harapkan demi kemajuan Ananda ke depannya.";
        } else {
            return "Evaluasi menyeluruh selama setahun ini menunjukkan bahwa Ananda masih memerlukan bimbingan dan pendampingan yang lebih intensif dalam proses pembiasaan akhlak dan kedisiplinan.{$kalimat_reward}{$kalimat_pelanggaran} Kami mengajak Bapak/Ibu untuk bersama-sama merumuskan langkah terbaik agar Ananda dapat berkembang lebih optimal di masa mendatang.";
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
        $kalimat[] = "Alhamdulillah, {$sapaan} telah menyelesaikan satu tahun masa pendidikan di pesantren ini.";

        $semua_avg = [];
        $index = 0;
        foreach ($nilai_aspek as $aspek) {
            $avg = _avg_aspek($aspek);
            $semua_avg[] = $avg;
            $kalimat[] = _kalimat_per_mutu($aspek, $avg, $index);
            $index++;
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
            return "Pencapaian Ananda pada aspek {$label} selama setahun ini sungguh memuaskan dan layak mendapat apresiasi. Secara khusus, komitmen dan kesungguhan Ananda dalam hal {$nama_tinggi} sangat menonjol dan membanggakan. Kami berharap kebiasaan positif ini bisa terus dijaga dan menjadi inspirasi bagi teman-teman yang lain.";
        } elseif ($avg >= 3.5) {
            $tambahan = "";
            if ($val_rendah < $val_tinggi && $nama_rendah) {
                $tambahan = " Hanya saja, Ananda masih sesekali kurang konsisten, terutama dalam hal {$nama_rendah}.";
            }
            return "Perkembangan Ananda pada aspek {$label} secara umum sudah berjalan dengan baik dan menggembirakan.{$tambahan} Ke depannya, Ananda perlu terus dimotivasi untuk meningkatkan konsistensinya di semua bagian secara merata.";
        } elseif ($avg >= 2.5) {
            $tambahan = "";
            if ($nama_rendah) {
                $tambahan = " Kami mendapati bahwa Ananda masih kurang memperhatikan urusan {$nama_rendah}, yang cukup berpengaruh pada penilaian di aspek ini.";
            }
            return "Perkembangan {$label} Ananda masih memerlukan perhatian ekstra dari berbagai pihak.{$tambahan} Mohon bantuan Bapak/Ibu untuk turut memotivasi Ananda agar lebih bersungguh-sungguh mengikuti setiap arahan pembina.";
        } else {
            return "Penilaian pada aspek {$label} menunjukkan bahwa Ananda masih memerlukan bimbingan yang lebih intensif. Ananda tampaknya belum sepenuhnya memahami pentingnya kebiasaan di area ini, khususnya dalam hal {$nama_rendah}. Evaluasi personal dan pendampingan yang berkelanjutan sangat diperlukan agar Ananda dapat berkembang lebih baik ke depannya.";
        }
    }
}


// =========================================================================
// 2. ENDPOINT API (Untuk Rapor Bulanan)
// Menerima $_POST dari form bulanan dan mencetak catatan
// =========================================================================
if (basename($_SERVER['SCRIPT_FILENAME']) === 'generate_catatan.php' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    
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