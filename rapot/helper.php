<?php
// File: rekap-mukholif/rapot/helper.php

if (!function_exists('generatePenilaianDropdown')) {
    /**
     * FUNGSI DROPDOWN (CREATE.PHP)
     * Teksnya diubah jadi TANPA TANDA KURUNG.
     */
    function generatePenilaianDropdown($key) {
        $options = '';
        switch ($key) {
            case 'puasa_sunnah':
            case 'sholat_duha':
            case 'sholat_malam':
                $options = '
                    <option value="">-- Pilih Penilaian --</option>
                    <option value="5">Rutin & Penuh</option>
                    <option value="4">Sering, kadang terlewat</option>
                    <option value="3">Melaksanakan, tapi jarang</option>
                    <option value="2">Hampir tidak pernah</option>
                    <option value="1">Tidak pernah</option>
                ';
                break;
            case 'sedekah':
                $options = '
                    <option value="">-- Pilih Penilaian --</option>
                    <option value="5">Tulus dalam berbagi</option>
                    <option value="4">Suka berbagi</option>
                    <option value="3">Kadang berbagi</option>
                    <option value="2">Jarang mau berbagi</option>
                    <option value="1">Pelit / tidak peduli</option>
                ';
                break;
            case 'lisan':
                $options = '
                    <option value="">-- Pilih Penilaian --</option>
                    <option value="5">Selalu berkata baik & sopan</option>
                    <option value="4">Umumnya baik</option>
                    <option value="3">Perlu banyak diingatkan</option>
                    <option value="2">Sering berkata kotor/tidak sopan</option>
                    <option value="1">Sangat tidak terjaga</option>
                ';
                break;
            case 'sikap':
            case 'kesopanan':
            case 'muamalah':
                $options = '
                    <option value="">-- Pilih Penilaian --</option>
                    <option value="5">Menjadi teladan bagi teman</option>
                    <option value="4">Sopan & menghargai</option>
                    <option value="3">Standar, kadang kurang sopan</option>
                    <option value="2">Perlu ditegur/diingatkan</option>
                    <option value="1">Sering bermasalah dgn teman/asatidz</option>
                ';
                break;
            case 'tidur':
            case 'keterlambatan':
                $options = '
                    <option value="">-- Pilih Penilaian --</option>
                    <option value="5">Selalu tepat waktu</option>
                    <option value="4">Hampir selalu tepat waktu</option>
                    <option value="3">Beberapa kali terlambat/menunda</option>
                    <option value="2">Sering terlambat</option>
                    <option value="1">Sangat sering terlambat</option>
                ';
                break;
            case 'seragam':
                $options = '
                    <option value="">-- Pilih Penilaian --</option>
                    <option value="5">Sangat rapi & lengkap</option>
                    <option value="4">Rapi, kadang ada atribut kurang</option>
                    <option value="3">Kurang rapi / tidak lengkap</option>
                    <option value="2">Sering melanggar aturan</option>
                    <option value="1">Tidak peduli aturan</option>
                ';
                break;
            case 'bahasa_arab':
                $options = '
                    <option value="">-- Pilih Penilaian --</option>
                    <option value="5">Aktif berbahasa Arab</option>
                    <option value="4">Berusaha, walau masih campur</option>
                    <option value="3">Paham, tapi pasif / malu</option>
                    <option value="2">Sering pakai Bahasa Indonesia</option>
                    <option value="1">Tidak mau berusaha</option>
                ';
                break;
            case 'mandi':
            case 'penampilan':
                $options = '
                    <option value="">-- Pilih Penilaian --</option>
                    <option value="5">Sangat bersih & rapi</option>
                    <option value="4">Bersih & rapi</option>
                    <option value="3">Kadang terlihat kotor/kusut</option>
                    <option value="2">Harus disuruh untuk rapi/bersih</option>
                    <option value="1">Tidak peduli kebersihan</option>
                ';
                break;
            case 'piket':
            case 'kerapihan_barang':
                $options = '
                    <option value="">-- Pilih Penilaian --</option>
                    <option value="5">Sangat rapi & tuntas</option>
                    <option value="4">Rapi & bertanggung jawab</option>
                    <option value="3">Melaksanakan, tapi harus diingatkan</option>
                    <option value="2">Malas-malasan / tidak tuntas</option>
                    <option value="1">Tidak mau melaksanakan</option>
                ';
                break;
            default:
                $options = '
                    <option value="">-- Pilih Penilaian --</option>
                    <option value="5">Sangat konsisten</option>
                    <option value="4">Perlu ditingkatkan</option>
                    <option value="3">Cukup</option>
                    <option value="2">Kurang</option>
                    <option value="1">Sangat kurang</option>
                ';
                break;
        }
        return $options;
    }
}

// ==========================================================
//           FUNGSI DESKRIPSI (BUAT PDF-NYA) KITA UBAH JUGA
// ==========================================================

if (!function_exists('getDeskripsiPenilaian')) {
    /**
     * Mengubah nilai angka (1-5) menjadi teks deskripsi SPESIFIK (TANPA TANDA KURUNG).
     * @param string $key Nama sub mutu (cth: 'puasa_sunnah')
     * @param int $nilai Nilai dari 1 sampai 5
     * @return string Deskripsi penilaian
     */
    function getDeskripsiPenilaian($key, $nilai) {
        
        switch ($key) {
            
            case 'puasa_sunnah':
            case 'sholat_duha':
            case 'sholat_malam':
                switch ($nilai) {
                    case 5: return 'Rutin & Penuh';
                    case 4: return 'Sering, kadang terlewat';
                    case 3: return 'Melaksanakan, tapi jarang';
                    case 2: return 'Hampir tidak pernah';
                    case 1: return 'Tidak pernah';
                    default: return '-';
                }
            
            case 'sedekah':
                switch ($nilai) {
                    case 5: return 'Tulus dalam berbagi';
                    case 4: return 'Suka berbagi';
                    case 3: return 'Kadang berbagi';
                    case 2: return 'Jarang mau berbagi';
                    case 1: return 'Pelit / tidak peduli';
                    default: return '-';
                }

            case 'lisan':
                switch ($nilai) {
                    case 5: return 'Selalu berkata baik & sopan';
                    case 4: return 'Umumnya baik';
                    case 3: return 'Perlu banyak diingatkan';
                    case 2: return 'Sering berkata kotor/tidak sopan';
                    case 1: return 'Sangat tidak terjaga';
                    default: return '-';
                }
            
            case 'sikap':
            case 'kesopanan':
            case 'muamalah':
                switch ($nilai) {
                    case 5: return 'Menjadi teladan bagi teman';
                    case 4: return 'Sopan & menghargai';
                    case 3: return 'Standar, kadang kurang sopan';
                    case 2: return 'Perlu ditegur/diingatkan';
                    case 1: return 'Sering bermasalah dgn teman/asatidz';
                    default: return '-';
                }

            case 'tidur':
            case 'keterlambatan':
                switch ($nilai) {
                    case 5: return 'Selalu tepat waktu';
                    case 4: return 'Hampir selalu tepat waktu';
                    case 3: return 'Beberapa kali terlambat/menunda';
                    case 2: return 'Sering terlambat';
                    case 1: return 'Sangat sering terlambat';
                    default: return '-';
                }

            case 'seragam':
                switch ($nilai) {
                    case 5: return 'Sangat rapi & lengkap';
                    case 4: return 'Rapi, kadang ada atribut kurang';
                    case 3: return 'Kurang rapi / tidak lengkap';
                    case 2: return 'Sering melanggar aturan';
                    case 1: return 'Tidak peduli aturan';
                    default: return '-';
                }

            case 'bahasa_arab':
                switch ($nilai) {
                    case 5: return 'Aktif berbahasa Arab';
                    case 4: return 'Berusaha, walau masih campur';
                    case 3: return 'Paham, tapi pasif / malu';
                    case 2: return 'Sering pakai Bahasa Indonesia';
                    case 1: return 'Tidak mau berusaha';
                    default: return '-';
                }
                
            case 'mandi':
            case 'penampilan':
                switch ($nilai) {
                    case 5: return 'Sangat bersih & rapi';
                    case 4: return 'Bersih & rapi';
                    case 3: return 'Kadang terlihat kotor/kusut';
                    case 2: return 'Harus disuruh untuk rapi/bersih';
                    case 1: return 'Tidak peduli kebersihan';
                    default: return '-';
                }
                
            case 'piket':
            case 'kerapihan_barang':
                switch ($nilai) {
                    case 5: return 'Sangat rapi & tuntas';
                    case 4: return 'Rapi & bertanggung jawab';
                    case 3: return 'Melaksanakan, tapi harus diingatkan';
                    case 2: return 'Malas-malasan / tidak tuntas';
                    case 1: return 'Tidak mau melaksanakan';
                    default: return '-';
                }

            // Fallback
            default:
                switch ($nilai) {
                    case 5: return 'Sangat konsisten';
                    case 4: return 'Perlu ditingkatkan';
                    case 3: return 'Cukup';
                    case 2: return 'Kurang';
                    case 1: return 'Sangat kurang';
                    default: return '-';
                }
        }
    }
}
?>