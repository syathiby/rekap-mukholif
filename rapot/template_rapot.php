<?php
// File: rekap-mukholif/rapot/template_rapot.php
/**
 * Ini adalah file TEMPLATE HTML+CSS.
 * Versi FIX: Nambahin rincian poin minimalis.
 */
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Rapot <?php echo htmlspecialchars($santri['nama']); ?></title>
    <style>
        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 10pt; 
            color: #000;
        }
        
        .judul-rapot {
            text-align: center;
            font-weight: bold;
            font-size: 13pt; 
            margin: 15px 0 10px 0; 
            text-decoration: underline;
        }

        .tabel-identitas {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px; 
            font-size: 10pt; 
        }
        .tabel-identitas td {
            padding: 1px 5px; 
        }

        .tabel-nilai {
            width: 100%;
            border-collapse: collapse;
            font-size: 10pt; 
        }
        .tabel-nilai th, .tabel-nilai td {
            border: 1px solid black;
            padding: 3px 5px; 
            text-align: left; 
            vertical-align: top;
        }
        .tabel-nilai th {
            background-color: #f2f2f2;
            text-align: center;
            font-weight: bold;
            vertical-align: middle;
        }
        .tabel-nilai .mutu {
            font-weight: bold;
            vertical-align: middle;
            text-align: center;
        }
        .tabel-nilai .nilai-angka {
            text-align: center;
            vertical-align: middle;
        }
        .tabel-nilai td.penjelasan-cell {
            text-align: center; 
            vertical-align: middle; 
        }

        .jumlah-row {
            background-color: #FFC;
        }
        .jumlah-row td {
            font-weight: bold;
        }

        /* CSS BAR POIN */
        .poin-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1px;
        }
        .poin-table td {
            border: 1px solid #C00;
            padding: 3px 5px; 
            background-color: #e60000;
            color: white;
            font-weight: bold;
        }
        .poin-text {
            width: 85%;
        }
        .poin-nilai {
            width: 15%;
            text-align: center;
        }
        
        /* ==========================================================
                          CSS BARU BUAT RINCIAN POIN
           ========================================================== */
        .rincian-pelanggaran {
            font-size: 9pt; /* Bikin lebih kecil biar minimalis */
            color: #333;
            padding: 4px 6px;
            border: 1px solid #ddd; /* Kasih border tipis */
            border-top: none; /* Nempel sama bar merah di atasnya */
            background: #f9f9f9; /* Kasih background beda dikit */
            line-height: 1.4; /* Atur spasi baris */
        }
        /* ========================================================== */

        /* CSS FOOTER */
        .catatan {
            margin-top: 10px; 
            font-size: 10pt; 
        }
        .catatan p {
            margin: 0;
            padding-left: 10px;
        }

        .penutup {
            margin-top: 10px; 
            font-size: 10pt; 
        }
        .penutup p {
            margin: 0;
            text-align: left;
        }

        .ttd-container {
            float: right;
            width: 300px;
            margin-top: 10px; 
            text-align: center;
            font-size: 10pt; 
        }
        .ttd-container .nama-musyrif {
            margin-top: 50px; 
            font-weight: bold;
        }
    </style>
</head>
<body>
    
    <img src="<?php echo $logo_path; ?>" style="width: 100%; height: 60px;" alt="Kop Surat">

    <div class="judul-rapot">RAPORT KEPENGASUHAN SANTRI</div>

    <table class="tabel-identitas">
        <tr>
            <td width="10%">Bulan:</td>
            <td width="55%"><?php echo htmlspecialchars($rapot['bulan']) . ' ' . htmlspecialchars($rapot['tahun']); ?></td>
            <td width="10%">Kamar:</td>
            <td width="25%"><?php echo htmlspecialchars($santri['kamar']); ?></td>
        </tr>
        <tr>
            <td>Nama:</td>
            <td colspan="3"><?php echo htmlspecialchars($santri['nama']); ?></td>
        </tr>
    </table>

    <table class="tabel-nilai">
        <thead>
            <tr>
                <th width="15%">Mutu</th>
                <th width="25%">Sub mutu</th>
                <th width="45%">Penjelasan</th>
                <th width="15%">Nilai</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td rowspan="6" class="mutu">Ibadah</td>
                <td>Puasa Sunnah</td>
                <td class="penjelasan-cell"><?php echo getDeskripsiPenilaian('puasa_sunnah', $rapot['puasa_sunnah']); ?></td>
                <td class="nilai-angka"><?php echo $rapot['puasa_sunnah']; ?></td>
            </tr>
            <tr>
                <td>Sholat Duha</td>
                <td class="penjelasan-cell"><?php echo getDeskripsiPenilaian('sholat_duha', $rapot['sholat_duha']); ?></td>
                <td class="nilai-angka"><?php echo $rapot['sholat_duha']; ?></td>
            </tr>
            <tr>
                <td>Sholat Malam</td>
                <td class="penjelasan-cell"><?php echo getDeskripsiPenilaian('sholat_malam', $rapot['sholat_malam']); ?></td>
                <td class="nilai-angka"><?php echo $rapot['sholat_malam']; ?></td>
            </tr>
            <tr>
                <td>Sedekah & Berbagi</td>
                <td class="penjelasan-cell"><?php echo getDeskripsiPenilaian('sedekah', $rapot['sedekah']); ?></td>
                <td class="nilai-angka"><?php echo $rapot['sedekah']; ?></td>
            </tr>
            <tr>
                <td>Sunnah sebelum tidur</td>
                <td class="penjelasan-cell"><?php echo getDeskripsiPenilaian('sunnah_tidur', $rapot['sunnah_tidur']); ?></td>
                <td class="nilai-angka"><?php echo $rapot['sunnah_tidur']; ?></td>
            </tr>
            <tr>
                <td>Ibadah lainnya</td>
                <td class="penjelasan-cell"><?php echo getDeskripsiPenilaian('ibadah_lainnya', $rapot['ibadah_lainnya']); ?></td>
                <td class="nilai-angka"><?php echo $rapot['ibadah_lainnya']; ?></td>
            </tr>
            
            <tr>
                <td rowspan="4" class="mutu">Akhlaq</td>
                <td>Lisan</td>
                <td class="penjelasan-cell"><?php echo getDeskripsiPenilaian('lisan', $rapot['lisan']); ?></td>
                <td class="nilai-angka"><?php echo $rapot['lisan']; ?></td>
            </tr>
            <tr>
                <td>Sikap & tingkah laku</td>
                <td class="penjelasan-cell"><?php echo getDeskripsiPenilaian('sikap', $rapot['sikap']); ?></td>
                <td class="nilai-angka"><?php echo $rapot['sikap']; ?></td>
            </tr>
            <tr>
                <td>Kesopanan</td>
                <td class="penjelasan-cell"><?php echo getDeskripsiPenilaian('kesopanan', $rapot['kesopanan']); ?></td>
                <td class="nilai-angka"><?php echo $rapot['kesopanan']; ?></td>
            </tr>
            <tr>
                <td>Muamalah</td>
                <td class="penjelasan-cell"><?php echo getDeskripsiPenilaian('muamalah', $rapot['muamalah']); ?></td>
                <td class="nilai-angka"><?php echo $rapot['muamalah']; ?></td>
            </tr>
            
            <tr>
                <td rowspan="6" class="mutu">Kedisiplinan</td>
                <td>Tidur</td>
                <td class="penjelasan-cell"><?php echo getDeskripsiPenilaian('tidur', $rapot['tidur']); ?></td>
                <td class="nilai-angka"><?php echo $rapot['tidur']; ?></td>
            </tr>
            <tr>
                <td>Keterlambatan</td>
                <td class="penjelasan-cell"><?php echo getDeskripsiPenilaian('keterlambatan', $rapot['keterlambatan']); ?></td>
                <td class="nilai-angka"><?php echo $rapot['keterlambatan']; ?></td>
            </tr>
            <tr>
                <td>Seragam</td>
                <td class="penjelasan-cell"><?php echo getDeskripsiPenilaian('seragam', $rapot['seragam']); ?></td>
                <td class="nilai-angka"><?php echo $rapot['seragam']; ?></td>
            </tr>
            <tr>
                <td>Makan</td>
                <td class="penjelasan-cell"><?php echo getDeskripsiPenilaian('makan', $rapot['makan']); ?></td>
                <td class="nilai-angka"><?php echo $rapot['makan']; ?></td>
            </tr>
            <tr>
                <td>Mengikuti arahan</td>
                <td class="penjelasan-cell"><?php echo getDeskripsiPenilaian('arahan', $rapot['arahan']); ?></td>
                <td class="nilai-angka"><?php echo $rapot['arahan']; ?></td>
            </tr>
            <tr>
                <td>Berbahasa arab di kamar</td>
                <td class="penjelasan-cell"><?php echo getDeskripsiPenilaian('bahasa_arab', $rapot['bahasa_arab']); ?></td>
                <td class="nilai-angka"><?php echo $rapot['bahasa_arab']; ?></td>
            </tr>

            <tr>
                <td rowspan="4" class="mutu">Kebersihan & Kerapihan</td>
                <td>Mandi</td>
                <td class="penjelasan-cell"><?php echo getDeskripsiPenilaian('mandi', $rapot['mandi']); ?></td>
                <td class="nilai-angka"><?php echo $rapot['mandi']; ?></td>
            </tr>
            <tr>
                <td>Penampilan & berpakaian</td>
                <td class="penjelasan-cell"><?php echo getDeskripsiPenilaian('penampilan', $rapot['penampilan']); ?></td>
                <td class="nilai-angka"><?php echo $rapot['penampilan']; ?></td>
            </tr>
            <tr>
                <td>Piket</td>
                <td class="penjelasan-cell"><?php echo getDeskripsiPenilaian('piket', $rapot['piket']); ?></td>
                <td class="nilai-angka"><?php echo $rapot['piket']; ?></td>
            </tr>
            <tr>
                <td>Kerapihan barang</td>
                <td class="penjelasan-cell"><?php echo getDeskripsiPenilaian('kerapihan_barang', $rapot['kerapihan_barang']); ?></td>
                <td class="nilai-angka"><?php echo $rapot['kerapihan_barang']; ?></td>
            </tr>

            <tr class="jumlah-row">
                <td colspan="3" style="text-align: right;">Jumlah</td>
                <td class="nilai-angka">
                    <?php
                    // Kalkulasi total nilai
                    $total_nilai = $rapot['puasa_sunnah'] + $rapot['sholat_duha'] + $rapot['sholat_malam'] + 
                                   $rapot['sedekah'] + $rapot['sunnah_tidur'] + $rapot['ibadah_lainnya'] + 
                                   $rapot['lisan'] + $rapot['sikap'] + $rapot['kesopanan'] + $rapot['muamalah'] + 
                                   $rapot['tidur'] + $rapot['keterlambatan'] + $rapot['seragam'] + $rapot['makan'] + 
                                   $rapot['arahan'] + $rapot['bahasa_arab'] + $rapot['mandi'] + $rapot['penampilan'] + 
                                   $rapot['piket'] + $rapot['kerapihan_barang'];
                    echo $total_nilai;
                    ?>
                </td>
            </tr>

        </tbody>
    </table>

    <table class="poin-table">
        <tr>
            <td class="poin-text">Total Poin Pelanggaran</td>
            <td class="poin-nilai">
                <?php 
                echo ($rapot['total_poin_pelanggaran_saat_itu'] > 0) ? $rapot['total_poin_pelanggaran_saat_itu'] : '-'; 
                ?>
            </td>
        </tr>
    </table>
    
    <?php if (!empty($pelanggaran_list)): ?>
        <div class="rincian-pelanggaran">
            <b>Rincian Poin:</b> 
            <?php
            $rincian_array = [];
            foreach ($pelanggaran_list as $pelanggaran) {
                // Kita bikin format: Nama (Poin)
                $rincian_array[] = htmlspecialchars($pelanggaran['nama_pelanggaran']) . ' (' . $pelanggaran['poin'] . ')';
            }
            // Kita gabung semua jadi satu baris, dipisah koma
            echo implode(', ', $rincian_array);
            ?>
        </div>
    <?php endif; ?>
    <div class="catatan">
        <b>Catatan:</b>
        <p><?php echo nl2br(htmlspecialchars($rapot['catatan_musyrif'])); ?></p>
    </div>
    
    <div class="penutup">
        <p>Demikian Laporan ini kami sampaikan kepada Bapak/ibu, semoga dengan adanya laporan ini Ananda bisa
        mempertahankan hal-hal baiknya dan menjadi lebih baik lagi dari sebelumnya, Kami berharap Bapak/ibu
        dapat memberikan Motivasi dan nasihat agar ananda senantiasa konsisten dalam kebaikan</p>
    </div>

    <div class="ttd-container">
        Cileungsi, <?php echo date('d F Y'); ?> <br>
        Musyrif Kamar <?php echo htmlspecialchars($santri['kamar']); ?>
        
        <div class="nama-musyrif"><?php echo htmlspecialchars($musyrif['nama_lengkap']); ?></div>
    </div>

</body>
</html>