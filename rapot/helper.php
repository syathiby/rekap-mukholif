<?php
// File: rekap-mukholif/rapot/helper.php

if (!function_exists('generatePenilaianDropdown')) {
    function generatePenilaianDropdown($key) {
        $options = '';
        switch ($key) {
            case 'puasa_sunnah':
            case 'sholat_duha':
            case 'sholat_malam':
                $options = '
                    <option value="">-- Pilih Penilaian --</option>
                    <option value="5">Konsisten melaksanakan</option>
                    <option value="4">Cukup rutin</option>
                    <option value="3">Kadang melaksanakan</option>
                    <option value="2">Jarang melaksanakan</option>
                    <option value="1">Belum terbiasa melaksanakan</option>
                ';
                break;
            case 'sedekah':
                $options = '
                    <option value="">-- Pilih Penilaian --</option>
                    <option value="5">Dermawan dan suka membantu</option>
                    <option value="4">Sering berbagi dengan teman</option>
                    <option value="3">Kadang berbagi</option>
                    <option value="2">Kurang punya inisiatif untuk berbagi</option>
                    <option value="1">Belum terlihat semangat berbagi</option>
                ';
                break;
            case 'sunnah_tidur':
            case 'ibadah_lainnya':
                $options = '
                    <option value="">-- Pilih Penilaian --</option>
                    <option value="5">Konsisten mengamalkan</option>
                    <option value="4">Cukup rutin</option>
                    <option value="3">Kadang mengamalkan</option>
                    <option value="2">Jarang melaksanakan sunnah</option>
                    <option value="1">Belum tampak kesungguhan menjalankan sunnah</option>
                ';
                break;
            case 'lisan':
                $options = '
                    <option value="">-- Pilih Penilaian --</option>
                    <option value="5">Sopan dalam berbicara</option>
                    <option value="4">Umumnya baik</option>
                    <option value="3">Perlu dibimbing dalam menjaga ucapan</option>
                    <option value="2">Kadang bicara kurang sopan</option>
                    <option value="1">Belum bisa menjaga ucapan dengan baik</option>
                ';
                break;
            case 'sikap':
                $options = '
                    <option value="">-- Pilih Penilaian --</option>
                    <option value="5">Berperilaku baik terhadap sesama</option>
                    <option value="4">Menunjukkan sikap yang baik</option>
                    <option value="3">Perlu dibiasakan sopan santun</option>
                    <option value="2">Masih perlu bimbingan dalam bersikap</option>
                    <option value="1">Sering bersikap kurang menghargai</option>
                ';
                break;
            case 'kesopanan':
                $options = '
                    <option value="">-- Pilih Penilaian --</option>
                    <option value="5">Selalu berbicara dan bertingkah dengan adab yang baik</option>
                    <option value="4">Pada umumnya, sopan dalam berbicara dan bersikap</option>
                    <option value="3">Kadang kurang memperhatikan kesopanan</option>
                    <option value="2">Sering berbicara atau bersikap tanpa adab</option>
                    <option value="1">Perlu pembinaan serius dalam menjaga sopan santun</option>
                ';
                break;
            case 'muamalah':
                $options = '
                    <option value="">-- Pilih Penilaian --</option>
                    <option value="5">Berinteraksi dengan baik terhadap sesama</option>
                    <option value="4">Umumnya baik dalam hubungan sosial</option>
                    <option value="3">Kadang kurang memahami adab bermuamalah</option>
                    <option value="2">Sering bersikap egois atau kurang menghargai orang lain</option>
                    <option value="1">Perlu dibina dalam hal etika dan tanggung jawab sosial</option>
                ';
                break;
            case 'tidur':
            case 'keterlambatan':
                $options = '
                    <option value="">-- Pilih Penilaian --</option>
                    <option value="5">Selalu tepat waktu</option>
                    <option value="4">Hampir selalu tepat waktu</option>
                    <option value="3">Kadang terlambat</option>
                    <option value="2">Sering terlambat</option>
                    <option value="1">Perlu dibiasakan disiplin waktu</option>
                ';
                break;
            case 'seragam':
                $options = '
                    <option value="">-- Pilih Penilaian --</option>
                    <option value="5">Selalu berpakaian rapi, bersih, dan sesuai aturan</option>
                    <option value="4">Umumnya rapi dan bersih</option>
                    <option value="3">Cukup rapi namun masih perlu perbaikan</option>
                    <option value="2">Kurang memperhatikan kerapian dan kelengkapan</option>
                    <option value="1">Sering tidak rapi dan tidak sesuai ketentuan</option>
                ';
                break;
            case 'makan':
                $options = '
                    <option value="">-- Pilih Penilaian --</option>
                    <option value="5">Makan dengan adab dan waktu yang tepat</option>
                    <option value="4">Cukup disiplin saat makan</option>
                    <option value="3">Kadang tidak tepat waktu</option>
                    <option value="2">Sering makan terlambat atau tergesa</option>
                    <option value="1">Kurang memperhatikan adab makan</option>
                ';
                break;
            case 'mengikuti_arahan':
            case 'arahan':
                $options = '
                    <option value="">-- Pilih Penilaian --</option>
                    <option value="5">Taat dan patuh terhadap arahan</option>
                    <option value="4">Umumnya patuh</option>
                    <option value="3">Kadang kurang teliti dalam menjalankan arahan</option>
                    <option value="2">Sering lalai terhadap instruksi</option>
                    <option value="1">Kurang menunjukkan sikap taat</option>
                ';
                break;
            case 'bahasa_arab':
                $options = '
                    <option value="">-- Pilih Penilaian --</option>
                    <option value="5">Aktif dan percaya diri dalam Berbahasa Arab</option>
                    <option value="4">Berusaha berbicara walau belum lancar</option>
                    <option value="3">Paham tapi masih pasif</option>
                    <option value="2">Lebih sering pakai Bahasa Indonesia</option>
                    <option value="1">Belum berusaha berbicara Bahasa Arab</option>
                ';
                break;
            case 'mandi':
                $options = '
                    <option value="">-- Pilih Penilaian --</option>
                    <option value="5">Selalu menjaga kebersihan diri setiap hari</option>
                    <option value="4">Cukup rutin menjaga kebersihan diri</option>
                    <option value="3">Kadang kurang memperhatikan waktu mandi</option>
                    <option value="2">Sering menunda atau jarang mandi</option>
                    <option value="1">Kurang memperhatikan kebersihan pribadi</option>
                ';
                break;
            case 'penampilan':
                $options = '
                    <option value="">-- Pilih Penilaian --</option>
                    <option value="5">Selalu bersih dan rapi</option>
                    <option value="4">Umumnya bersih dan rapi</option>
                    <option value="3">Kadang kurang rapi</option>
                    <option value="2">Perlu dibiasakan menjaga kerapian</option>
                    <option value="1">Kurang memperhatikan kebersihan diri</option>
                ';
                break;
            case 'piket':
                $options = '
                    <option value="">-- Pilih Penilaian --</option>
                    <option value="5">Piket dengan bersih dan tanpa komando</option>
                    <option value="4">Melaksanakan piket dengan baik setelah diarahkan</option>
                    <option value="3">Melaksanakan piket namun perlu diingatkan</option>
                    <option value="2">Kurang peduli terhadap kebersihan bersama</option>
                    <option value="1">Tidak melaksanakan tugas piket dengan tanggung jawab</option>
                ';
                break;
            case 'kerapihan_barang':
                $options = '
                    <option value="">-- Pilih Penilaian --</option>
                    <option value="5">Barang pribadi selalu tertata rapi dan bersih</option>
                    <option value="4">Umumnya rapi</option>
                    <option value="3">Perlu diingatkan untuk menjaga kerapihan barang</option>
                    <option value="2">Sering terlihat berantakan atau tidak teratur</option>
                    <option value="1">Kurang tanggung jawab dalam menjaga barang pribadi</option>
                ';
                break;
            default:
                $options = '
                    <option value="">-- Pilih Penilaian --</option>
                    <option value="5">Sangat baik</option>
                    <option value="4">Baik, tapi perlu ditingkatkan</option>
                    <option value="3">Cukup</option>
                    <option value="2">Perlu diperhatikan lagi</option>
                    <option value="1">Perlu bimbingan lebih lanjut</option>
                ';
                break;
        }
        return $options;
    }
}

// ==========================================================
//           FUNGSI DESKRIPSI (BUAT PDF-NYA)
// ==========================================================

if (!function_exists('getDeskripsiPenilaian')) {
    function getDeskripsiPenilaian($key, $nilai) {
        switch ($key) {
            // ================== IBADAH ==================
            case 'puasa_sunnah':
            case 'sholat_duha':
            case 'sholat_malam':
                return [
                    5 => 'Konsisten melaksanakan',
                    4 => 'Cukup rutin',
                    3 => 'Kadang melaksanakan',
                    2 => 'Jarang melaksanakan',
                    1 => 'Belum terbiasa melaksanakan'
                ][$nilai] ?? '-';
            case 'sedekah':
                return [
                    5 => 'Dermawan dan suka membantu',
                    4 => 'Sering berbagi dengan teman',
                    3 => 'Kadang berbagi',
                    2 => 'Kurang punya inisiatif untuk berbagi',
                    1 => 'Belum terlihat semangat berbagi'
                ][$nilai] ?? '-';
            case 'sunnah_tidur':
            case 'ibadah_lainnya':
                return [
                    5 => 'Konsisten mengamalkan',
                    4 => 'Cukup rutin',
                    3 => 'Kadang mengamalkan',
                    2 => 'Jarang melaksanakan sunnah',
                    1 => 'Belum tampak kesungguhan menjalankan sunnah'
                ][$nilai] ?? '-';

            // ================== AKHLAK ==================
            case 'lisan':
                return [
                    5 => 'Sopan dalam berbicara',
                    4 => 'Umumnya baik',
                    3 => 'Perlu dibimbing dalam menjaga ucapan',
                    2 => 'Kadang bicara kurang sopan',
                    1 => 'Belum bisa menjaga ucapan dengan baik'
                ][$nilai] ?? '-';
            case 'sikap':
                return [
                    5 => 'Berperilaku baik terhadap sesama',
                    4 => 'Menunjukkan sikap baik',
                    3 => 'Perlu dibiasakan sopan santun',
                    2 => 'Masih perlu bimbingan dalam bersikap',
                    1 => 'Sering bersikap kurang menghargai'
                ][$nilai] ?? '-';
            case 'kesopanan':
                return [
                    5 => 'Selalu berbicara dan bertingkah dengan adab yang baik',
                    4 => 'Pada umumnya, sopan dalam berbicara dan bersikap',
                    3 => 'Kadang kurang memperhatikan kesopanan',
                    2 => 'Sering berbicara atau bersikap tanpa adab',
                    1 => 'Perlu pembinaan serius dalam menjaga sopan santun'
                ][$nilai] ?? '-';

            case 'muamalah':
                return [
                    5 => 'Berinteraksi dengan baik terhadap sesama',
                    4 => 'Umumnya baik dalam hubungan sosial',
                    3 => 'Kadang kurang memahami adab bermuamalah',
                    2 => 'Sering bersikap egois atau kurang menghargai orang lain',
                    1 => 'Perlu dibina dalam hal etika dan tanggung jawab sosial'
                ][$nilai] ?? '-';

            // ================== KEDISIPLINAN ==================
            case 'tidur':
            case 'keterlambatan':
                return [
                    5 => 'Selalu tepat waktu',
                    4 => 'Hampir selalu tepat waktu',
                    3 => 'Kadang terlambat',
                    2 => 'Sering terlambat',
                    1 => 'Perlu dibiasakan disiplin waktu'
                ][$nilai] ?? '-';
            case 'seragam':
                return [
                    5 => 'Selalu berpakaian rapi, bersih, dan sesuai aturan',
                    4 => 'Umumnya rapi dan bersih',
                    3 => 'Cukup rapi namun masih perlu perbaikan',
                    2 => 'Kurang memperhatikan kerapian dan kelengkapan',
                    1 => 'Sering tidak rapi dan tidak sesuai ketentuan'
                ][$nilai] ?? '-';
            case 'makan':
                return [
                    5 => 'Makan dengan adab dan waktu yang tepat',
                    4 => 'Cukup disiplin saat makan',
                    3 => 'Kadang tidak tepat waktu',
                    2 => 'Sering makan terlambat atau tergesa',
                    1 => 'Kurang memperhatikan adab makan'
                ][$nilai] ?? '-';
            case 'mengikuti_arahan':
            case 'arahan':
                return [
                    5 => 'Taat dan patuh terhadap arahan',
                    4 => 'Umumnya patuh',
                    3 => 'Kadang kurang teliti dalam menjalankan arahan',
                    2 => 'Sering lalai terhadap instruksi',
                    1 => 'Kurang menunjukkan sikap taat'
                ][$nilai] ?? '-';
            case 'bahasa_arab':
                return [
                    5 => 'Aktif dan percaya diri dalam Berbahasa Arab',
                    4 => 'Berusaha berbicara walau belum lancar',
                    3 => 'Paham tapi masih pasif',
                    2 => 'Lebih sering pakai Bahasa Indonesia',
                    1 => 'Belum berusaha berbicara Bahasa Arab'
                ][$nilai] ?? '-';

            // ================== KEBERSIHAN & KERAPIHAN ==================
            case 'mandi':
                return [
                    5 => 'Selalu menjaga kebersihan diri setiap hari',
                    4 => 'Cukup rutin menjaga kebersihan diri',
                    3 => 'Kadang kurang memperhatikan waktu mandi',
                    2 => 'Sering menunda atau jarang mandi',
                    1 => 'Kurang memperhatikan kebersihan pribadi'
                ][$nilai] ?? '-';
            case 'penampilan':
                return [
                    5 => 'Selalu bersih dan rapi',
                    4 => 'Umumnya bersih dan rapi',
                    3 => 'Kadang kurang rapi',
                    2 => 'Perlu dibiasakan menjaga kerapian',
                    1 => 'Kurang memperhatikan kebersihan diri'
                ][$nilai] ?? '-';
            case 'piket':
                return [
                    5 => 'Piket dengan bersih dan tanpa komando',
                    4 => 'Melaksanakan piket dengan baik setelah diarahkan',
                    3 => 'Melaksanakan piket namun perlu diingatkan',
                    2 => 'Kurang peduli terhadap kebersihan bersama',
                    1 => 'Tidak melaksanakan tugas piket dengan tanggung jawab'
                ][$nilai] ?? '-';

            case 'kerapihan_barang':
                return [
                    5 => 'Barang pribadi selalu tertata rapi dan bersih',
                    4 => 'Umumnya rapi',
                    3 => 'Perlu diingatkan untuk menjaga kerapihan barang',
                    2 => 'Sering terlihat berantakan atau tidak teratur',
                    1 => 'Kurang tanggung jawab dalam menjaga barang pribadi'
                ][$nilai] ?? '-';

            // ================== DEFAULT ==================
            default:
                return [
                    5 => 'Sangat baik',
                    4 => 'Baik, tapi perlu ditingkatkan',
                    3 => 'Cukup',
                    2 => 'Perlu diperhatikan lagi',
                    1 => 'Perlu bimbingan lebih lanjut'
                ][$nilai] ?? '-';
        }
    }
}
?>