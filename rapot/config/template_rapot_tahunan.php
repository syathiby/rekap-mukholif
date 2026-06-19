<?php
// File: rekap-mukholif/rapot/config/template_rapot_tahunan.php
// Template PDF Rapor Tahunan (2 Halaman)
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Rapor Tahunan <?php echo htmlspecialchars($santri['nama']); ?></title>
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

        .catatan-mutu td {
            font-style: italic;
            font-size: 9pt;
            background-color: #f9f9f9;
        }

        /* POIN SECTION */
        .rincian-pelanggaran,
        .rincian-reward {
            font-size: 9pt;
            color: #333;
            padding: 4px 6px;
            border: 1px solid #ddd;
            border-top: none;
            background: #f9f9f9;
            line-height: 1.4;
        }

        /* CSS FOOTER */
        .catatan {
            margin-top: 10px; 
            font-size: 10pt; 
        }
        .catatan p {
            margin: 0;
            padding-left: 10px;
            text-align: justify;
        }

        .ttd-container {
            float: right;
            width: 300px;
            margin-top: 30px; 
            text-align: center;
            font-size: 10pt; 
        }
        .ttd-container .nama-musyrif {
            margin-top: 60px; 
            font-weight: bold;
            text-decoration: underline;
        }
        
        /* HALAMAN 2 STYLES */
        .tabel-rekap {
            width: 100%;
            border-collapse: collapse;
            font-size: 10pt;
            margin-bottom: 15px;
        }
        .tabel-rekap th, .tabel-rekap td {
            border: 1px solid black;
            padding: 5px;
        }
        .tabel-rekap th {
            background-color: #f2f2f2;
            text-align: center;
            font-weight: bold;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
    </style>
</head>
<body>
    
    <!-- HALAMAN 1: PENILAIAN KARAKTER -->
    <img src="<?php echo $logo_path; ?>" style="width: 100%; height: 60px;" alt="Kop Surat">

    <div class="judul-rapot">RAPORT KEPENGASUHAN SANTRI (TAHUNAN)</div>

    <table class="tabel-identitas">
        <tr>
            <td width="10%">Periode:</td>
            <td width="55%"><?php echo htmlspecialchars($periode); ?></td>
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
            <?php foreach ($nilai_aspek as $aspek): 
                $subs = $aspek['sub_mutu'] ?? [];
                $row_count = count($subs) + 1; // +1 untuk baris catatan per mutu
            ?>
                <?php foreach ($subs as $i => $sub): 
                    $field = $sub['field'];
                    $nf = (float)($sub['nilai_final'] ?? 0);
                    $nfr = (int)round($nf);
                    $ket = getDeskripsiPenilaian($field, $nfr);
                ?>
                <tr>
                    <?php if ($i === 0): ?>
                        <td rowspan="<?php echo $row_count; ?>" class="mutu"><?php echo htmlspecialchars($aspek['aspek']); ?></td>
                    <?php endif; ?>
                    <td><?php echo htmlspecialchars($sub['nama']); ?></td>
                    <td class="penjelasan-cell"><?php echo htmlspecialchars($ket); ?></td>
                    <td class="nilai-angka">
                        <?php echo number_format($nf, $nf == floor($nf) ? 0 : 1); ?>
                        <?php if (!empty($sub['ada_koreksi'])) echo '*'; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <tr class="catatan-mutu">
                    <td colspan="3">
                        <b>Catatan:</b> <?php echo htmlspecialchars($aspek['catatan'] ?? generate_catatan_per_aspek($aspek)); ?>
                    </td>
                </tr>
            <?php endforeach; ?>

            <tr class="jumlah-row">
                <td colspan="3" style="text-align: right;">Jumlah</td>
                <td class="nilai-angka">
                    <?php echo number_format($total_nilai, $total_nilai == floor($total_nilai) ? 0 : 1); ?>
                </td>
            </tr>

        </tbody>
    </table>
    <div style="font-size: 8pt; color: #555; margin-top: 2px;">
        * Nilai telah disesuaikan secara otomatis berdasarkan rekapitulasi poin pelanggaran dan reward santri selama 1 tahun.
    </div>

    <!-- POIN PELANGGARAN & REWARD BAR KECIL (PERSIS SEPERTI BULANAN) -->
    <table width="100%" style="margin-top: 10px; font-size: 9pt; line-height: 1.3; border-collapse: collapse;">
        <tr>
            <td width="50%" style="padding: 6px 8px; background: #fdecec; border-left: 3px solid #e60000; vertical-align: top;">
                <div style="color: #c00; font-weight: bold; margin-bottom: 2px;">Poin Pelanggaran</div>
                <div style="font-weight: bold; color: #000;">
                    <?php echo ($total_pelanggaran > 0) ? $total_pelanggaran : '–'; ?>
                </div>
            </td>
            <td width="50%" style="padding: 6px 8px; background: #ecf8f0; border-left: 3px solid #009900; vertical-align: top;">
                <div style="color: #090; font-weight: bold; margin-bottom: 2px;">Poin Reward</div>
                <div style="font-weight: bold; color: #000;">
                    <?php echo ($total_reward > 0) ? $total_reward : '–'; ?>
                </div>
            </td>
        </tr>
    </table>



    <!-- PAGE BREAK -->
    <pagebreak />

    <!-- HALAMAN 2: REKAP PELANGGARAN & REWARD -->
    <img src="<?php echo $logo_path; ?>" style="width: 100%; height: 60px;" alt="Kop Surat">
    <div class="judul-rapot" style="margin-bottom: 20px;">REKAPITULASI PELANGGARAN & REWARD</div>
    
    <table class="tabel-identitas" style="margin-bottom: 20px;">
        <tr>
            <td width="10%">Periode:</td>
            <td width="40%"><?php echo htmlspecialchars($periode); ?></td>
            <td width="10%">Nama:</td>
            <td width="40%"><b><?php echo htmlspecialchars($santri['nama']); ?></b></td>
        </tr>
    </table>

    <div style="font-weight: bold; margin-bottom: 5px;">A. Rincian Pelanggaran</div>
    <table class="tabel-rekap">
        <thead>
            <tr>
                <th width="10%">No</th>
                <th width="50%">Jenis Pelanggaran</th>
                <th width="20%">Jumlah (x)</th>
                <th width="20%">Total Poin</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($pelanggaran_rekap)): ?>
                <?php $no = 1; foreach ($pelanggaran_rekap as $p): ?>
                <tr>
                    <td class="text-center"><?php echo $no++; ?></td>
                    <td><?php echo htmlspecialchars($p['nama_pelanggaran']); ?></td>
                    <td class="text-center"><?php echo (int)$p['jumlah']; ?></td>
                    <td class="text-center"><?php echo (int)$p['total_poin']; ?></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4" class="text-center">Alhamdulillah, tidak ada catatan pelanggaran.</td>
                </tr>
            <?php endif; ?>
            <tr class="jumlah-row">
                <td colspan="3" class="text-right">Total Poin Pelanggaran Setahun</td>
                <td class="text-center"><?php echo $total_pelanggaran; ?></td>
            </tr>
        </tbody>
    </table>

    <div style="font-weight: bold; margin-top: 20px; margin-bottom: 5px;">B. Rincian Reward / Prestasi</div>
    <table class="tabel-rekap">
        <thead>
            <tr>
                <th width="10%">No</th>
                <th width="50%">Jenis Reward</th>
                <th width="20%">Jumlah (x)</th>
                <th width="20%">Total Poin</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($reward_rekap)): ?>
                <?php $no = 1; foreach ($reward_rekap as $r): ?>
                <tr>
                    <td class="text-center"><?php echo $no++; ?></td>
                    <td><?php echo htmlspecialchars($r['nama_reward']); ?></td>
                    <td class="text-center"><?php echo (int)$r['jumlah']; ?></td>
                    <td class="text-center"><?php echo (int)$r['total_poin']; ?></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4" class="text-center">Belum ada catatan reward.</td>
                </tr>
            <?php endif; ?>
            <tr class="jumlah-row">
                <td colspan="3" class="text-right">Total Poin Reward Setahun</td>
                <td class="text-center"><?php echo $total_reward; ?></td>
            </tr>
        </tbody>
    </table>

    <div class="catatan">
        <b>Catatan Keseluruhan:</b>
        <p><?php echo nl2br(htmlspecialchars($narasi_global)); ?></p>
    </div>

    <div style="margin-top: 20px; font-size: 10pt; text-align: justify;">
        <p>Demikian laporan kepengasuhan ini disampaikan. Kami memohon dukungan Bapak/Ibu untuk senantiasa memberikan motivasi agar Ananda istiqomah dalam kebaikan.</p>
    </div>

    <div class="ttd-container">
        Cileungsi, <?php echo date('d F Y'); ?> <br>
        Musyrif Kamar <?php echo htmlspecialchars($santri['kamar']); ?>
        
        <div class="nama-musyrif"><?php echo htmlspecialchars($nama_musyrif); ?></div>
    </div>

</body>
</html>
