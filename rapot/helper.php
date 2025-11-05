<?php
// File: rekap-mukholif/rapot/helper.php

if (!function_exists('generatePenilaianDropdown')) {
    /**
     * FUNGSI DROPDOWN (CREATE.PHP)
     * Bahasa disederhanakan biar tetap sopan tapi natural.
     */
    function generatePenilaianDropdown($key) {
        $options = '';
        switch ($key) {
            case 'puasa_sunnah':
            case 'sholat_duha':
            case 'sholat_malam':
                $options = '
                    <option value="">-- Pilih Penilaian --</option>
                    <option value="5">Rajin dan semangat</option>
                    <option value="4">Cukup rutin, kadang terlewat</option>
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
            case 'lisan':
                $options = '
                    <option value="">-- Pilih Penilaian --</option>
                    <option value="5">Sopan dalam berbicara</option>
                    <option value="4">Umumnya baik, namun perlu ditingkatkan</option>
                    <option value="3">Perlu dibimbing dalam menjaga ucapan</option>
                    <option value="2">Kadang bicara kurang sopan</option>
                    <option value="1">Belum bisa menjaga ucapan dengan baik</option>
                ';
                break;
            case 'sikap':
            case 'kesopanan':
            case 'muamalah':
                $options = '
                    <option value="">-- Pilih Penilaian --</option>
                    <option value="5">Berperilaku baik dan berakhlak mulia</option>
                    <option value="4">Sopan dan menghargai orang lain</option>
                    <option value="3">Perlu dibiasakan sopan santun</option>
                    <option value="2">Masih perlu bimbingan dalam bersikap</option>
                    <option value="1">Sering bersikap kurang menghargai</option>
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
                    <option value="4">Umumnya rapi dan bersih, kadang kurang lengkap</option>
                    <option value="3">Cukup rapi namun masih perlu perbaikan</option>
                    <option value="2">Kurang memperhatikan kerapian dan kelengkapan</option>
                    <option value="1">Sering tidak rapi dan tidak sesuai ketentuan</option>
                ';
                break;
            case 'bahasa_arab':
                $options = '
                    <option value="">-- Pilih Penilaian --</option>
                    <option value="5">Aktif dan percaya diri pakai Bahasa Arab</option>
                    <option value="4">Berusaha berbicara walau belum lancar</option>
                    <option value="3">Paham tapi masih pasif</option>
                    <option value="2">Lebih sering pakai Bahasa Indonesia</option>
                    <option value="1">Belum berusaha berbicara Bahasa Arab</option>
                ';
                break;
            case 'mandi':
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
            case 'kerapihan_barang':
                $options = '
                    <option value="">-- Pilih Penilaian --</option>
                    <option value="5">Inisiatif, tanpa komando</option>
                    <option value="4">Melaksanakan dengan baik</option>
                    <option value="3">Melaksanakan tapi perlu diingatkan</option>
                    <option value="2">Kurang inisiatif atau belum tuntas</option>
                    <option value="1">Belum terlihat tanggung jawab</option>
                ';
                break;
            default:
                $options = '
                    <option value="">-- Pilih Penilaian --</option>
                    <option value="5">Sangat baik dan stabil</option>
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
            case 'puasa_sunnah':
            case 'sholat_duha':
            case 'sholat_malam':
                return [
                    5 => 'Rajin dan semangat',
                    4 => 'Cukup rutin, kadang terlewat',
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

            case 'lisan':
                return [
                    5 => 'Sopan dalam berbicara',
                    4 => 'Umumnya baik, namun perlu ditingkatkan',
                    3 => 'Perlu dibimbing dalam menjaga ucapan',
                    2 => 'Kadang bicara kurang sopan',
                    1 => 'Belum bisa menjaga ucapan dengan baik'
                ][$nilai] ?? '-';

            case 'sikap':
            case 'kesopanan':
            case 'muamalah':
                return [
                    5 => 'Berperilaku baik dan berakhlak mulia',
                    4 => 'Sopan dan menghargai orang lain',
                    3 => 'Perlu dibiasakan sopan santun',
                    2 => 'Masih perlu bimbingan dalam bersikap',
                    1 => 'Sering bersikap kurang menghargai'
                ][$nilai] ?? '-';

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
                    4 => 'Umumnya rapi dan bersih, kadang kurang lengkap',
                    3 => 'Cukup rapi namun masih perlu perbaikan',
                    2 => 'Kurang memperhatikan kerapian dan kelengkapan',
                    1 => 'Sering tidak rapi dan tidak sesuai ketentuan'
                ][$nilai] ?? '-';

            case 'bahasa_arab':
                return [
                    5 => 'Aktif dan percaya diri pakai Bahasa Arab',
                    4 => 'Berusaha berbicara walau belum lancar',
                    3 => 'Paham tapi masih pasif',
                    2 => 'Lebih sering pakai Bahasa Indonesia',
                    1 => 'Belum berusaha berbicara Bahasa Arab'
                ][$nilai] ?? '-';

            case 'mandi':
            case 'penampilan':
                return [
                    5 => 'Selalu bersih dan rapi',
                    4 => 'Umumnya bersih dan rapi',
                    3 => 'Kadang kurang rapi',
                    2 => 'Perlu dibiasakan menjaga kerapian',
                    1 => 'Kurang memperhatikan kebersihan diri'
                ][$nilai] ?? '-';

            case 'piket':
            case 'kerapihan_barang':
                return [
                    5 => 'Inisiatif, tanpa komando',
                    4 => 'Melaksanakan dengan baik',
                    3 => 'Melaksanakan tapi perlu diingatkan',
                    2 => 'Kurang inisiatif atau belum tuntas',
                    1 => 'Belum terlihat tanggung jawab'
                ][$nilai] ?? '-';

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