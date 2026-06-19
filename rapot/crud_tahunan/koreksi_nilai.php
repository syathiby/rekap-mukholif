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
    
    // =======================================================================
    // PEMETAAN KEYWORD → [Mutu, Sub-Mutu]
    // Dibangun berdasarkan data NYATA dari tabel jenis_pelanggaran & jenis_reward.
    // ATURAN: FRASA PANJANG / SPESIFIK HARUS DI ATAS KATA TUNGGAL.
    // =======================================================================
    $mapping = [

        // ===================================================
        // IBADAH
        // ===================================================
        'puasa sunnah'              => ['Ibadah', 'Puasa Sunnah'],
        'puasa'                     => ['Ibadah', 'Puasa Sunnah'],
        'sholat malam'              => ['Ibadah', 'Sholat Malam'],
        'tahajud'                   => ['Ibadah', 'Sholat Malam'],
        'qiyamul'                   => ['Ibadah', 'Sholat Malam'],
        'dhuha'                     => ['Ibadah', 'Sholat Duha'],
        'duha'                      => ['Ibadah', 'Sholat Duha'],
        'sedekah'                   => ['Ibadah', 'Sedekah & Berbagi'],
        'infaq'                     => ['Ibadah', 'Sedekah & Berbagi'],
        'berbagi'                   => ['Ibadah', 'Sedekah & Berbagi'],
        'sunnah tidur'              => ['Ibadah', 'Sunnah sebelum tidur'],
        'dzikir'                    => ['Ibadah', 'Sunnah sebelum tidur'],  // "Tidak dzikir setelah sholat"
        'shaff'                     => ['Ibadah', 'Sunnah sebelum tidur'],  // Reward: "Berada di shaff 1 saat sholat shubuh"

        // Ibadah Lainnya (hafalan, muadzin, jamaah, tilawah, setor, dll)
        'mutqin'                    => ['Ibadah', 'Ibadah lainnya'],        // Reward: "Mutqin hafalan Al-Quran 30 Juz"
        'menyelesaikan hafalan'     => ['Ibadah', 'Ibadah lainnya'],        // Reward: "Menyelesaikan hafalan 30 juz"
        'hafalan melebihi'          => ['Ibadah', 'Ibadah lainnya'],        // Reward: "Hafalan melebihi target"
        'halaqoh terdisiplin'       => ['Ibadah', 'Ibadah lainnya'],        // Reward: "Halaqoh terdisipilin"
        'muadzin'                   => ['Ibadah', 'Ibadah lainnya'],        // Reward: "Muadzin Ma'had"
        'kelas terdisiplin'         => ['Ibadah', 'Ibadah lainnya'],        // Reward: "Kelas terdisiplin"
        'halaqoh'                   => ['Ibadah', 'Ibadah lainnya'],
        'sholat'                    => ['Ibadah', 'Ibadah lainnya'],
        'sunnah'                    => ['Ibadah', 'Ibadah lainnya'],        // "sunnah" umum → bukan sunnah tidur
        'masjid'                    => ['Ibadah', 'Ibadah lainnya'],
        'jamaah'                    => ['Ibadah', 'Ibadah lainnya'],
        'tilawah'                   => ['Ibadah', 'Ibadah lainnya'],
        'ngaji'                     => ['Ibadah', 'Ibadah lainnya'],
        'quran'                     => ['Ibadah', 'Ibadah lainnya'],
        'mushaf'                    => ['Ibadah', 'Ibadah lainnya'],        // "Tidak membawa mushaf/buku"
        'hafalan'                   => ['Ibadah', 'Ibadah lainnya'],
        'hafal'                     => ['Ibadah', 'Ibadah lainnya'],
        'setor'                     => ['Ibadah', 'Ibadah lainnya'],
        'tajwid'                    => ['Ibadah', 'Ibadah lainnya'],        // "Bacaan tidak sesuai kaidah tajwid"

        // ===================================================
        // AKHLAQ — FRASA LENGKAP SELALU DI ATAS KATA TUNGGAL
        // ===================================================

        // Kesopanan
        'berkomunikasi dengan lawan jenis' => ['Akhlaq', 'Kesopanan'],     // "Berkomunikasi dengan lawan jenis" (66 poin)
        'membicarakan lawan jenis'  => ['Akhlaq', 'Kesopanan'],            // "Membicarakan lawan jenis/penyanyi" (15 poin)
        'foto lawan jenis'          => ['Akhlaq', 'Kesopanan'],            // "Membawa/menyimpan foto lawan jenis" (30 poin)
        'gambar lawan jenis'        => ['Akhlaq', 'Kesopanan'],
        'lawan jenis'               => ['Akhlaq', 'Kesopanan'],            // ⚑ HARUS sebelum 'lawan'
        'tabarruj'                  => ['Akhlaq', 'Kesopanan'],            // "Tabarruj" berhias berlebihan (15 poin)
        'sopan'                     => ['Akhlaq', 'Kesopanan'],
        'adab'                      => ['Akhlaq', 'Kesopanan'],

        // Lisan
        'kata kotor'                => ['Akhlaq', 'Lisan'],                // "Mengeluarkan kata kotor/kasar" (16 poin)
        'bersumpah palsu'           => ['Akhlaq', 'Lisan'],                // "Bersumpah palsu" (60 poin)
        'gelar yang buruk'          => ['Akhlaq', 'Lisan'],                // "Mengejek atau memberi gelar yang buruk" (20 poin)
        'menyebarkan fitnah'        => ['Akhlaq', 'Lisan'],                // "Menyebarkan fitnah" (100 poin)
        'mencemarkan nama baik'     => ['Akhlaq', 'Lisan'],                // "Mencemarkan nama baik ma'had" (100 poin)
        'namimah'                   => ['Akhlaq', 'Lisan'],                // "Melakukan adu mba/namimah" (100 poin)
        'adu domba'                 => ['Akhlaq', 'Lisan'],
        'melecehkan'                => ['Akhlaq', 'Lisan'],                // "Melecehkan baik secara tertulis atau lisan" (100 poin)
        'berbohong'                 => ['Akhlaq', 'Lisan'],                // "Berbohong" (51 poin)
        'bersumpah'                 => ['Akhlaq', 'Lisan'],
        'sumpah'                    => ['Akhlaq', 'Lisan'],
        'fitnah'                    => ['Akhlaq', 'Lisan'],
        'bohong'                    => ['Akhlaq', 'Lisan'],
        'dusta'                     => ['Akhlaq', 'Lisan'],
        'ejek'                      => ['Akhlaq', 'Lisan'],
        'mengejek'                  => ['Akhlaq', 'Lisan'],
        'provokasi'                 => ['Akhlaq', 'Lisan'],                // "Melakukan provokasi/adu domba"
        'ancaman'                   => ['Akhlaq', 'Lisan'],                // "Melakukan ancaman dan penindasan" (100 poin)
        'penindasan'                => ['Akhlaq', 'Lisan'],
        'bully'                     => ['Akhlaq', 'Lisan'],                // "Melakukan ancaman dan penindasan/bully" (100 poin)
        'lisan'                     => ['Akhlaq', 'Lisan'],

        // Muamalah
        'mencuri di luar'           => ['Akhlaq', 'Muamalah'],             // "Mencuri di luar Ma'had" (300 poin)
        'mencuri kategori'          => ['Akhlaq', 'Muamalah'],             // "Mencuri kategori Berat/Ringan"
        'mengambil jatah'           => ['Akhlaq', 'Muamalah'],             // "Mengambil jatah makan/lauk orang lain" (22 poin)
        'menggunakan barang orang lain' => ['Akhlaq', 'Muamalah'],         // "Menggunakan barang orang lain tanpa izin" (10 poin)
        'perkelahian dengan terencana' => ['Akhlaq', 'Muamalah'],          // "Perkelahian dengan terencana" (100 poin)
        'perkelahian tidak terencana' => ['Akhlaq', 'Muamalah'],           // "Perkelahian tidak terencana (emosi spontan)" (30 poin)
        'perkelahian'               => ['Akhlaq', 'Muamalah'],
        'mencuri'                   => ['Akhlaq', 'Muamalah'],
        'curi'                      => ['Akhlaq', 'Muamalah'],
        'ghasab'                    => ['Akhlaq', 'Muamalah'],
        'kelahi'                    => ['Akhlaq', 'Muamalah'],
        'jujur'                     => ['Akhlaq', 'Muamalah'],
        'muamalah'                  => ['Akhlaq', 'Muamalah'],

        // Sikap & Tingkah Laku
        'tidak beradab'             => ['Akhlaq', 'Sikap & tingkah laku'], // "Tidak beradab/membangkang kepada ustadz" (50 poin)
        'membangkang'               => ['Akhlaq', 'Sikap & tingkah laku'],
        'menolak sanksi'            => ['Akhlaq', 'Sikap & tingkah laku'], // "Menolak sanksi dari ma'had" (43 poin)
        'menganiaya'                => ['Akhlaq', 'Sikap & tingkah laku'], // "Menganiaya ustadz atau pegawai" (300 poin)
        'menjadi provokator'        => ['Akhlaq', 'Sikap & tingkah laku'], // "Menjadi provokator atas Self Harm" (50 poin)
        'self harm'                 => ['Akhlaq', 'Sikap & tingkah laku'], // "Menyakiti diri sendiri" (50 poin)
        'melukai diri'              => ['Akhlaq', 'Sikap & tingkah laku'],
        'homoseksual'               => ['Akhlaq', 'Sikap & tingkah laku'], // "Melakukan homoseksual/LGBT" (300 poin)
        'lgbt'                      => ['Akhlaq', 'Sikap & tingkah laku'],
        'berzina'                   => ['Akhlaq', 'Sikap & tingkah laku'], // "Berzina" (300 poin)
        'zina'                      => ['Akhlaq', 'Sikap & tingkah laku'],
        'pencabulan'                => ['Akhlaq', 'Sikap & tingkah laku'], // "Pencabulan" (300 poin)
        'obat-obatan terlarang'     => ['Akhlaq', 'Sikap & tingkah laku'], // "Obat-obatan terlarang" (300 poin)
        'narkoba'                   => ['Akhlaq', 'Sikap & tingkah laku'],
        'obat-obatan'               => ['Akhlaq', 'Sikap & tingkah laku'],
        'rokok'                     => ['Akhlaq', 'Sikap & tingkah laku'], // "Membawa/mengkonsumsi rokok" (74 poin)
        'merokok'                   => ['Akhlaq', 'Sikap & tingkah laku'],
        'pacar'                     => ['Akhlaq', 'Sikap & tingkah laku'],
        'hubungan persahabatan yang tidak wajar' => ['Akhlaq', 'Sikap & tingkah laku'], // (30 poin)
        'mengintip orang mandi'     => ['Akhlaq', 'Sikap & tingkah laku'], // "Mengintip orang mandi" (75 poin) - lebih dekat ke akhlak
        'konten yang tidak baik'    => ['Akhlaq', 'Sikap & tingkah laku'], // "Membuat/Mengupload konten..." (40 poin)
        'konten'                    => ['Akhlaq', 'Sikap & tingkah laku'],
        'mendengarkan musik'        => ['Akhlaq', 'Sikap & tingkah laku'], // "Mendengarkan musik" (40 poin)
        'musik'                     => ['Akhlaq', 'Sikap & tingkah laku'],
        'nasyid'                    => ['Akhlaq', 'Sikap & tingkah laku'], // "Bernyanyi/Berdendang/Nasyid" (20 poin)
        'bernyanyi'                 => ['Akhlaq', 'Sikap & tingkah laku'],
        'menari'                    => ['Akhlaq', 'Sikap & tingkah laku'], // "Menari/memperagakan tarian" (10 poin)
        'video porno'               => ['Akhlaq', 'Sikap & tingkah laku'], // "Menyimpan/Menonton film/video porno" (66 poin)
        'film porno'                => ['Akhlaq', 'Sikap & tingkah laku'],
        'porno'                     => ['Akhlaq', 'Sikap & tingkah laku'],
        'merayakan hari ulang tahun'=> ['Akhlaq', 'Sikap & tingkah laku'], // "Merayakan hari ulang tahun" (40 poin)
        'ulang tahun'               => ['Akhlaq', 'Sikap & tingkah laku'],
        'valentine'                 => ['Akhlaq', 'Sikap & tingkah laku'],
        'monopoli'                  => ['Akhlaq', 'Sikap & tingkah laku'], // "Bermain monopoli/catur" (30 poin)
        'catur'                     => ['Akhlaq', 'Sikap & tingkah laku'],
        'menyontek'                 => ['Akhlaq', 'Sikap & tingkah laku'], // "Menyontek ketika ujian" (50 poin)
        'kegaduhan'                 => ['Akhlaq', 'Sikap & tingkah laku'], // "Membuat kegaduhan atau keributan" (8 poin)
        'keributan'                 => ['Akhlaq', 'Sikap & tingkah laku'],
        'juara'                     => ['Akhlaq', 'Sikap & tingkah laku'], // Reward: "Juara perlombaan"
        'lomba'                     => ['Akhlaq', 'Sikap & tingkah laku'],
        'prestasi'                  => ['Akhlaq', 'Sikap & tingkah laku'],
        'karya ilmiah'              => ['Akhlaq', 'Sikap & tingkah laku'], // Reward: "Membuat karya ilmiah"
        'pengurus osmis'            => ['Akhlaq', 'Sikap & tingkah laku'], // Reward: "Pengurus OSMIS"
        'osmis'                     => ['Akhlaq', 'Sikap & tingkah laku'],
        'mencemarkan'               => ['Akhlaq', 'Sikap & tingkah laku'], // "Mencemarkan nama baik"
        'bantu'                     => ['Akhlaq', 'Sikap & tingkah laku'],
        'tolong'                    => ['Akhlaq', 'Sikap & tingkah laku'],
        'inisiatif'                 => ['Akhlaq', 'Sikap & tingkah laku'],
        'kasar'                     => ['Akhlaq', 'Sikap & tingkah laku'],
        'bangkang'                  => ['Akhlaq', 'Sikap & tingkah laku'],
        'sikap'                     => ['Akhlaq', 'Sikap & tingkah laku'],
        'lawan'                     => ['Akhlaq', 'Sikap & tingkah laku'], // ⚑ Setelah 'lawan jenis'

        // ===================================================
        // KEDISIPLINAN — FRASA LENGKAP DI ATAS
        // ===================================================

        // Keterlambatan (DB: TELAT SHOLAT, TELAT KBM, Terlambat mengikuti salat berjama'ah, dll)
        'telat sholat'              => ['Kedisiplinan', 'Keterlambatan'],   // "TELAT SHOLAT" (DB id=1, 0 poin)
        'telat kbm'                 => ['Kedisiplinan', 'Keterlambatan'],   // "TELAT KBM" (DB id=2, 0 poin)
        'terlambat lebih dari'      => ['Kedisiplinan', 'Keterlambatan'],   // "Datang terlambat lebih dari 10 menit" (5 poin)
        'datang terlambat'          => ['Kedisiplinan', 'Keterlambatan'],
        'terlambat mengikuti'       => ['Kedisiplinan', 'Keterlambatan'],   // "Terlambat mengikuti salat berjama'ah" (5 poin)
        'terlambat masuk'           => ['Kedisiplinan', 'Keterlambatan'],   // "Terlambat masuk aula" (3 poin)
        'datang terlambat ke kelas' => ['Kedisiplinan', 'Keterlambatan'],   // "Datang terlambat ke kelas" (1 poin)
        'terlambat'                 => ['Kedisiplinan', 'Keterlambatan'],
        'telat'                     => ['Kedisiplinan', 'Keterlambatan'],

        // Seragam (DB: Memodifikasi seragam, Berpakaian tidak seragam pada jam KBM)
        'memodifikasi seragam'      => ['Kedisiplinan', 'Seragam'],         // "Memodifikasi seragam yang diberikan Ma'had" (10 poin)
        'berpakaian tidak seragam'  => ['Kedisiplinan', 'Seragam'],         // "Berpakaian tidak seragam pada jam KBM" (9 poin)
        'seragam'                   => ['Kedisiplinan', 'Seragam'],

        // Makan (DB: "Makan dan minum berdiri", "Tidak makan di ruang makan", "Memesan makan dari luar")
        'tidak makan di ruang makan'=> ['Kedisiplinan', 'Makan'],           // "Tidak makan di ruang makan" (3 poin)
        'makan dan minum berdiri'   => ['Kedisiplinan', 'Makan'],           // "Makan dan minum berdiri atau menggunakan tangan kiri" (4 poin)
        'makan dan minum'           => ['Kedisiplinan', 'Makan'],
        'memesan makan'             => ['Kedisiplinan', 'Makan'],           // "Memesan makan dari luar (delivery order)" (9 poin)
        'jatah makan'               => ['Kedisiplinan', 'Makan'],
        'makan'                     => ['Kedisiplinan', 'Makan'],

        // Mengikuti Arahan (DB: kabur, perizinan, HP, playstation, area terlarang, tidak di kamar)
        'keluar dari lingkungan pesantren' => ['Kedisiplinan', 'Mengikuti arahan'], // "Keluar dari lingkungan pesantren tanpa izin (kabur)" (56 poin)
        'melampaui batas waktu kembali' => ['Kedisiplinan', 'Mengikuti arahan'],    // "Melampaui batas waktu kembali dalam perizinan" (10 poin)
        'menyalahgunakan perizinan' => ['Kedisiplinan', 'Mengikuti arahan'],        // "Menyalahgunakan perizinan" (18 & 5 poin)
        'tidak mengindahkan arahan' => ['Kedisiplinan', 'Mengikuti arahan'],        // "Tidak mengindahkan arahan dan nasehat" (7 poin)
        'tidak berada di kamar'     => ['Kedisiplinan', 'Mengikuti arahan'],        // "Tidak berada di kamar pada jam istirahat" (7 poin)
        'mengadakan kegiatan'       => ['Kedisiplinan', 'Mengikuti arahan'],        // "Mengadakan kegiatan tanpa izin" (17 poin)
        'masuk ke area terlarang'   => ['Kedisiplinan', 'Mengikuti arahan'],        // "Masuk ke area terlarang" (20 poin)
        'masuk kamar orang lain'    => ['Kedisiplinan', 'Mengikuti arahan'],        // "Masuk kamar orang lain tanpa izin" (6 poin)
        'membolos 1 jam'            => ['Kedisiplinan', 'Mengikuti arahan'],        // "Membolos 1 jam dengan sengaja" (20 poin)
        'menyimpan dan memiliki hp' => ['Kedisiplinan', 'Mengikuti arahan'],        // "Menyimpan dan memiliki HP" (56 poin)
        'memakai hp asrama'         => ['Kedisiplinan', 'Mengikuti arahan'],        // "Memakai hp asrama di luar waktu" (7 poin)
        'permainan playstation'     => ['Kedisiplinan', 'Mengikuti arahan'],        // "Permainan playstation/game online" (56 poin)
        'tidak tertib'              => ['Kedisiplinan', 'Mengikuti arahan'],        // "Tidak tertib pada waktu halaqah dan kelas" (3 poin)
        'mengelabui'                => ['Kedisiplinan', 'Mengikuti arahan'],        // "Mengelabui OSMIS dan civitas mahad" (30 poin)
        'tanpa izin'                => ['Kedisiplinan', 'Mengikuti arahan'],
        'perizinan'                 => ['Kedisiplinan', 'Mengikuti arahan'],
        'membolos'                  => ['Kedisiplinan', 'Mengikuti arahan'],
        'bolos'                     => ['Kedisiplinan', 'Mengikuti arahan'],
        'meninggalkan'              => ['Kedisiplinan', 'Mengikuti arahan'],
        'kabur'                     => ['Kedisiplinan', 'Mengikuti arahan'],
        'keluar'                    => ['Kedisiplinan', 'Mengikuti arahan'],
        'arahan'                    => ['Kedisiplinan', 'Mengikuti arahan'],
        'hp asrama'                 => ['Kedisiplinan', 'Mengikuti arahan'],
        'hp'                        => ['Kedisiplinan', 'Mengikuti arahan'],
        'playstation'               => ['Kedisiplinan', 'Mengikuti arahan'],
        'game online'               => ['Kedisiplinan', 'Mengikuti arahan'],
        'game'                      => ['Kedisiplinan', 'Mengikuti arahan'],
        'gadget'                    => ['Kedisiplinan', 'Mengikuti arahan'],
        'elektronik'                => ['Kedisiplinan', 'Mengikuti arahan'],        // "Memakai peralatan elektronik selain HP" (30 poin)

        // Berbahasa Arab (DB: "Bahasa Level 1–10" dari divisi Bahasa)
        'bahasa level'              => ['Kedisiplinan', 'Berbahasa arab di kamar'], // "Bahasa Level 1-10" (3–30 poin)
        'berbahasa arab'            => ['Kedisiplinan', 'Berbahasa arab di kamar'],
        'berbahasa'                 => ['Kedisiplinan', 'Berbahasa arab di kamar'],
        'bahasa'                    => ['Kedisiplinan', 'Berbahasa arab di kamar'],
        'arab'                      => ['Kedisiplinan', 'Berbahasa arab di kamar'],

        // Tidur (DB: "Tidur satu selimut dan/atau satu kasur", "Tidak berada di kamar pada jam istirahat malam")
        'tidur satu selimut'        => ['Kedisiplinan', 'Tidur'],                   // "Tidur satu selimut" (35 poin)
        'jam istirahat malam'       => ['Kedisiplinan', 'Tidur'],                   // "Tidak berada di kamar pada jam istirahat"
        'istirahat malam'           => ['Kedisiplinan', 'Tidur'],
        'begadang'                  => ['Kedisiplinan', 'Tidur'],
        'tidur'                     => ['Kedisiplinan', 'Tidur'],
        'malam'                     => ['Kedisiplinan', 'Tidur'],                   // ⚑ 'malam' sendirian → tidur

        // ===================================================
        // KEBERSIHAN — FRASA SPESIFIK DI ATAS KATA TUNGGAL
        // ===================================================

        // Mandi
        'mandi'                     => ['Kebersihan', 'Mandi'],
        'bau'                       => ['Kebersihan', 'Mandi'],

        // Penampilan & Berpakaian (DB: "Potongan rambut menyerupai lawan jenis", "Berpakaian tidak islami", "pakaian yang tidak sesuai", "parfume/make up")
        'potongan rambut menyerupai'=> ['Kebersihan', 'Penampilan & berpakaian'],   // "Potongan rambut menyerupai lawan jenis" (40 poin)
        'mewarnai dan menyemir rambut' => ['Kebersihan', 'Penampilan & berpakaian'],
        'potongan rambut'           => ['Kebersihan', 'Penampilan & berpakaian'],
        'mencukur jenggot'          => ['Kebersihan', 'Penampilan & berpakaian'],   // "Mencukur jenggot" (40 poin)
        'berpakaian tidak islami'   => ['Kebersihan', 'Penampilan & berpakaian'],   // "Berpakaian tidak islami (isbal)" (40 poin)
        'pakaian yang tidak sesuai' => ['Kebersihan', 'Penampilan & berpakaian'],   // "Membawa dan/atau memakai pakaian yang tidak sesuai" (10 poin)
        'membuat pakaian seragam'   => ['Kebersihan', 'Penampilan & berpakaian'],   // "Membuat/memesan pakaian seragam tanpa izin ma'had" (20 poin)
        'parfume'                   => ['Kebersihan', 'Penampilan & berpakaian'],   // "Membawa/memakai parfume dan make up (akhwat)" (15 poin)
        'make up'                   => ['Kebersihan', 'Penampilan & berpakaian'],
        'alas kaki'                 => ['Kebersihan', 'Penampilan & berpakaian'],   // "Tidak memakai alas kaki di area lantai 1" (5 poin)
        'celana panjang'            => ['Kebersihan', 'Penampilan & berpakaian'],   // "Tidak memakai celana panjang ketika tidur" (5 poin)
        'jenggot'                   => ['Kebersihan', 'Penampilan & berpakaian'],
        'rambut'                    => ['Kebersihan', 'Penampilan & berpakaian'],
        'penampilan'                => ['Kebersihan', 'Penampilan & berpakaian'],
        'berpakaian'                => ['Kebersihan', 'Penampilan & berpakaian'],
        'pakaian'                   => ['Kebersihan', 'Penampilan & berpakaian'],

        // Piket (DB: "KEBERSIHAN KAMAR", "Tidak piket asrama", "Membuang sampah sembarangan", "Mencoret-coret")
        'kebersihan kamar'          => ['Kebersihan', 'Piket'],                     // "KEBERSIHAN KAMAR" (DB id=3, 0 poin)
        'kamar terbersih'           => ['Kebersihan', 'Piket'],                     // Reward: "Kamar terbersih"
        'tidak piket'               => ['Kebersihan', 'Piket'],                     // "Tidak piket asrama/mahad/halaqah" (5 poin)
        'membuang sampah'           => ['Kebersihan', 'Piket'],                     // "Membuang sampah sembarangan" (7 poin)
        'mencoret-coret'            => ['Kebersihan', 'Piket'],                     // "Mencoret-coret/merusak inventaris" (15 poin)
        'merusak inventaris'        => ['Kebersihan', 'Piket'],
        'piket'                     => ['Kebersihan', 'Piket'],
        'sampah'                    => ['Kebersihan', 'Piket'],
        'mencoret'                  => ['Kebersihan', 'Piket'],
        'merusak'                   => ['Kebersihan', 'Piket'],
        'bersih'                    => ['Kebersihan', 'Piket'],
        'kotor'                     => ['Kebersihan', 'Piket'],
        'jemur'                     => ['Kebersihan', 'Piket'],

        // Kerapihan Barang (DB: "Meletakkan barang-barang pribadi tidak pada tempatnya", "Menyimpan barang selain kebutuhan belajar di loker")
        'meletakkan barang'         => ['Kebersihan', 'Kerapihan barang'],          // "Meletakkan barang-barang pribadi tidak pada tempatnya" (5 poin)
        'menyimpan barang selain'   => ['Kebersihan', 'Kerapihan barang'],          // "Menyimpan barang selain kebutuhan di loker" (2 poin)
        'barang di loker'           => ['Kebersihan', 'Kerapihan barang'],
        'loker'                     => ['Kebersihan', 'Kerapihan barang'],
        'lemari'                    => ['Kebersihan', 'Kerapihan barang'],
        'rapi'                      => ['Kebersihan', 'Kerapihan barang'],
        'barang'                    => ['Kebersihan', 'Kerapihan barang'],

        // ===================================================
        // KEYWORD CADANGAN — Untuk Jangka Panjang
        // Ditambahkan tanpa mengganti yang sudah ada di atas.
        // Cocok untuk nama pelanggaran/reward yang belum spesifik tertangkap.
        // ===================================================

        // -- Ibadah tambahan --
        'istiqomah'         => ['Ibadah', 'Ibadah lainnya'],           // "Istiqomah puasa sunnah / sholat sunnah"
        'jum\'at'           => ['Ibadah', 'Ibadah lainnya'],           // Reward: "Datang di awal waktu saat Sholat Jum'at"
        'jumat'             => ['Ibadah', 'Ibadah lainnya'],
        'wudhu'             => ['Ibadah', 'Ibadah lainnya'],
        'ibadah'            => ['Ibadah', 'Ibadah lainnya'],           // Umum "ibadah"
        'doa'               => ['Ibadah', 'Sunnah sebelum tidur'],
        'zikir'             => ['Ibadah', 'Sunnah sebelum tidur'],     // variasi ejaan dzikir

        // -- Akhlaq / Kesopanan tambahan --
        'isbal'             => ['Kebersihan', 'Penampilan & berpakaian'], // "Berpakaian tidak islami (isbal)"
        'jilbab'            => ['Kebersihan', 'Penampilan & berpakaian'], // Penampilan akhwat
        'hijab'             => ['Kebersihan', 'Penampilan & berpakaian'],
        'fasik'             => ['Akhlaq', 'Sikap & tingkah laku'],     // "Membawa benda yang identik dengan orang fasik/kafir"
        'kafir'             => ['Akhlaq', 'Sikap & tingkah laku'],
        'syirik'            => ['Akhlaq', 'Sikap & tingkah laku'],
        'mubadzir'          => ['Kedisiplinan', 'Makan'],              // "Mubadzir pada makanan yang disediakan ma'had"
        'israf'             => ['Kedisiplinan', 'Makan'],

        // -- Kedisiplinan tambahan --
        'izin'              => ['Kedisiplinan', 'Mengikuti arahan'],   // Umum "tanpa izin" / "izin palsu"
        'berdagang'         => ['Kedisiplinan', 'Mengikuti arahan'],   // "Berdagang di area mahad"
        'dagang'            => ['Kedisiplinan', 'Mengikuti arahan'],
        'jendela'           => ['Kedisiplinan', 'Mengikuti arahan'],   // "Keluar masuk lewat jendela" (10 poin)
        'ventilasi'         => ['Kedisiplinan', 'Mengikuti arahan'],   // "Melihat-lihat ke luar jendela/ventilasi"
        'cat calling'       => ['Akhlaq', 'Kesopanan'],                // "Melihat-lihat dan cat calling"
        'sarpras'           => ['Kedisiplinan', 'Mengikuti arahan'],   // "Memakai peralatan sarpras tanpa izin"
        'inventaris'        => ['Kedisiplinan', 'Mengikuti arahan'],   // "Menyalahgunakan Handphone inventaris"
        'fasilitas'         => ['Kedisiplinan', 'Mengikuti arahan'],
        'medsos'            => ['Akhlaq', 'Sikap & tingkah laku'],     // "Membuat konten di medsos"
        'sosial media'      => ['Akhlaq', 'Sikap & tingkah laku'],
        'internet'          => ['Kedisiplinan', 'Mengikuti arahan'],
        'sim'               => ['Kedisiplinan', 'Mengikuti arahan'],   // "Mengganti SIM HP asrama" (10 poin)
        'motor'             => ['Kedisiplinan', 'Mengikuti arahan'],   // "Menyewa atau meminjam motor/mobil" (14 poin)
        'mobil'             => ['Kedisiplinan', 'Mengikuti arahan'],
        'laptop'            => ['Kedisiplinan', 'Mengikuti arahan'],   // "Menggunakan HP/Laptop/Tab milik tamu"

        // -- Kebersihan tambahan --
        'wc'                => ['Kebersihan', 'Mandi'],                // Kebersihan kamar mandi
        'kamar mandi'       => ['Kebersihan', 'Mandi'],
        'handuk'            => ['Kebersihan', 'Mandi'],
        'sepatu'            => ['Kebersihan', 'Penampilan & berpakaian'], // Alas kaki/sepatu
        'sandal'            => ['Kebersihan', 'Penampilan & berpakaian'],
        'semir'             => ['Kebersihan', 'Penampilan & berpakaian'], // "Menyemir rambut"
        'warna rambut'      => ['Kebersihan', 'Penampilan & berpakaian'],
        'tatanan'           => ['Kebersihan', 'Kerapihan barang'],     // "Tatanan kamar tidak rapi"
        'berantakan'        => ['Kebersihan', 'Kerapihan barang'],
        'rapih'             => ['Kebersihan', 'Kerapihan barang'],     // variasi ejaan
        'susun'             => ['Kebersihan', 'Kerapihan barang'],
        'tata'              => ['Kebersihan', 'Kerapihan barang'],

        // -- Keyword Bahasa Arab variasi --
        'bahasa inggris'    => ['Kedisiplinan', 'Berbahasa arab di kamar'], // jika pesantren pakai bahasa Inggris juga
        'english'           => ['Kedisiplinan', 'Berbahasa arab di kamar'],

        // ===================================================
        // KEYWORD KHUSUS REWARD — Presisi Sub-Mutu
        // ===================================================
        // Reward ID=4: "Juara 1 kelas dan halaqoh per semester"
        // → 'juara' saja sudah match ke Sikap & tingkah laku,
        //   tapi "juara kelas" / "juara halaqoh" lebih tepat ke Ibadah lainnya
        'juara kelas'           => ['Ibadah', 'Ibadah lainnya'],         // "Juara 1 kelas dan halaqoh per semester"
        'juara halaqoh'         => ['Ibadah', 'Ibadah lainnya'],
        'juara 1 kelas'         => ['Ibadah', 'Ibadah lainnya'],

        // Alias perlombaan & kompetisi (reward ID=1,6)
        'perlombaan di luar'    => ['Akhlaq', 'Sikap & tingkah laku'],  // "Juara perlombaan di luar Ma'had" (30 poin)
        'perlombaan di dalam'   => ['Akhlaq', 'Sikap & tingkah laku'],  // "Juara perlombaan di dalam Ma'had" (10 poin)
        'perlombaan'            => ['Akhlaq', 'Sikap & tingkah laku'],
        'kompetisi'             => ['Akhlaq', 'Sikap & tingkah laku'],
        'pidato'                => ['Akhlaq', 'Sikap & tingkah laku'],  // "MHQ/Lomba Pidato/MHM"
        'mhq'                   => ['Akhlaq', 'Sikap & tingkah laku'],  // Musabaqah Hifdzil Qur'an
        'mhm'                   => ['Akhlaq', 'Sikap & tingkah laku'],

        // Alias hafalan spesifik (reward ID=2,7,12)
        '30 juz'                => ['Ibadah', 'Ibadah lainnya'],        // "Mutqin/Menyelesaikan hafalan 30 juz"
        'juz'                   => ['Ibadah', 'Ibadah lainnya'],        // Umum hafalan juz
        'ujian hafalan'         => ['Ibadah', 'Ibadah lainnya'],        // "Ujian 30 juz"
        'target hafalan'        => ['Ibadah', 'Ibadah lainnya'],        // "Hafalan melebihi target"

        // Alias shubuh & awal waktu (reward ID=9,16)
        'sholat shubuh'         => ['Ibadah', 'Sunnah sebelum tidur'],  // "Berada di shaff 1 saat sholat shubuh"
        'shubuh'                => ['Ibadah', 'Sunnah sebelum tidur'],
        'subuh'                 => ['Ibadah', 'Sunnah sebelum tidur'],  // variasi ejaan
        'awal waktu'            => ['Ibadah', 'Ibadah lainnya'],        // "Datang di awal waktu saat Sholat Jum'at"

        // Alias reward OSMIS & karya (reward ID=3,5)
        'menulis buku'          => ['Akhlaq', 'Sikap & tingkah laku'],  // "Membuat karya ilmiah / menulis buku"
        'karya tulis'           => ['Akhlaq', 'Sikap & tingkah laku'],
        'pengurus'              => ['Akhlaq', 'Sikap & tingkah laku'],  // "Pengurus OSMIS"

        // Alias terdisipilin (DB punya typo "terdisipilin")
        'terdisipilin'          => ['Ibadah', 'Ibadah lainnya'],        // "Halaqoh terdisipilin" (typo di DB)
        'terdisiplin'           => ['Ibadah', 'Ibadah lainnya'],        // "Kelas terdisiplin"
    ];

    // Urutkan mapping berdasarkan panjang keyword (terpanjang ke terpendek)
    // Ini MENCEGAH bug di mana kata pendek seperti "zina" match duluan di dalam kata "periZINAn",
    // atau "adab" match duluan di dalam "tidak beradab".
    uksort($mapping, function($a, $b) {
        return strlen($b) - strlen($a);
    });

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
