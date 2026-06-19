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
            font-size: 11pt; 
            color: #000;
        }
        
        .judul-rapot {
            text-align: center;
            font-weight: bold;
            font-size: 14pt; 
            margin: 10px 0 8px 0; 
            text-decoration: underline;
        }

        .tabel-identitas {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px; 
            font-size: 11pt; 
        }
        .tabel-identitas td {
            padding: 1px 4px; 
        }

        .tabel-nilai {
            width: 100%;
            border-collapse: collapse;
            font-size: 11pt; 
        }
        .tabel-nilai th, .tabel-nilai td {
            border: 1px solid black;
            padding: 2px 4px; 
            text-align: left; 
            vertical-align: middle;
        }
        .tabel-nilai th {
            background-color: #f2f2f2;
            text-align: center;
            font-weight: bold;
            vertical-align: middle;
        }
        .tabel-nilai .mutu {
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
            font-size: 10pt;
            vertical-align: top;
        }

        /* POIN SECTION */
        .rincian-pelanggaran,
        .rincian-reward {
            font-size: 8pt;
            color: #333;
            padding: 2px 4px;
            border: 1px solid #ddd;
            border-top: none;
            background: #f9f9f9;
            line-height: 1.4;
        }

        /* CSS FOOTER */
        .catatan {
            margin-top: 15px; 
            font-size: 11pt; 
        }
        .catatan p {
            margin: 0;
            padding-left: 10px;
            text-align: justify;
        }

        .ttd-container {
            float: right;
            width: 300px;
            margin-top: 25px; 
            text-align: center;
            font-size: 11pt; 
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
            font-size: 9pt;
            margin-bottom: 8px;
        }
        .tabel-rekap th, .tabel-rekap td {
            border: 1px solid #aaa;
            padding: 3px;
        }
        .tabel-rekap th {
            text-align: center;
            font-weight: bold;
            color: #fff;
        }
        .tabel-rekap-pelanggaran th {
            background-color: #e66767;
        }
        .tabel-rekap-pelanggaran .jumlah-row td {
            background-color: #fdecec;
            font-weight: bold;
            color: #c00;
        }
        .tabel-rekap-reward th {
            background-color: #48b461;
        }
        .tabel-rekap-reward .jumlah-row td {
            background-color: #ecf8f0;
            font-weight: bold;
            color: #090;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
    </style>
</head>
<body>
    
    <!-- HALAMAN 1: PENILAIAN KARAKTER -->
    <img src="<?php echo $logo_path; ?>" style="width: 100%; height: 50px;" alt="Kop Surat">

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
                <th rowspan="2" width="15%" style="background-color: #e2efda;">Mutu</th>
                <th rowspan="2" width="25%" style="background-color: #e2efda;">Sub mutu</th>
                <th colspan="2" width="60%" style="background-color: #e2efda;">Penilaian</th>
            </tr>
            <tr>
                <th width="45%" style="background-color: #e2efda;">Penjelasan</th>
                <th width="15%" style="background-color: #e2efda;">Poin</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($nilai_aspek as $aspek): 
                $subs = $aspek['sub_mutu'] ?? [];
                $row_count = count($subs); // rowspan hanya untuk baris sub mutu
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
                    </td>
                </tr>
                <?php endforeach; ?>
                <tr class="catatan-mutu">
                    <td style="color: #FF0000; vertical-align: middle; text-align: center; font-weight: bold; padding: 4px;">Evaluasi <?php echo htmlspecialchars($aspek['aspek']); ?></td>
                    <td colspan="3" style="text-align: justify; padding: 4px 6px; vertical-align: top;">
                        <?php echo htmlspecialchars($aspek['catatan'] ?? (has_permission('catatan_otomatis') ? generate_catatan_per_aspek($aspek) : '')); ?>
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


    <!-- PAGE BREAK -->
    <pagebreak />

    <!-- HALAMAN 2: REKAP PELANGGARAN & REWARD -->
    <img src="<?php echo $logo_path; ?>" style="width: 100%; height: 50px;" alt="Kop Surat">
    <div class="judul-rapot" style="margin-bottom: 10px;">REKAPITULASI PELANGGARAN & REWARD</div>
    
    <table class="tabel-identitas" style="margin-bottom: 10px;">
        <tr>
            <td width="10%">Periode:</td>
            <td width="40%"><?php echo htmlspecialchars($periode); ?></td>
            <td width="10%">Nama:</td>
            <td width="40%"><b><?php echo htmlspecialchars($santri['nama']); ?></b></td>
        </tr>
    </table>

    <div style="font-weight: bold; font-size: 12pt; color: #c00; margin-bottom: 5px; border-bottom: 2px solid #e66767; padding-bottom: 3px;">A. Rincian Pelanggaran</div>
    <table class="tabel-rekap tabel-rekap-pelanggaran">
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
                <td colspan="3" class="text-center">Total Poin Pelanggaran Setahun</td>
                <td class="text-center"><?php echo $total_pelanggaran; ?></td>
            </tr>
        </tbody>
    </table>

    <div style="font-weight: bold; font-size: 12pt; color: #090; margin-top: 20px; margin-bottom: 5px; border-bottom: 2px solid #48b461; padding-bottom: 3px;">B. Rincian Reward / Prestasi</div>
    <table class="tabel-rekap tabel-rekap-reward">
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
                <td colspan="3" class="text-center">Total Poin Reward Setahun</td>
                <td class="text-center"><?php echo $total_reward; ?></td>
            </tr>
        </tbody>
    </table>

    <div class="catatan">
        <b>Catatan Keseluruhan:</b>
        <p><?php echo nl2br(htmlspecialchars($narasi_global)); ?></p>
    </div>

    <div style="margin-top: 15px; font-size: 11pt; text-align: justify;">
        <p>Demikian laporan kepengasuhan ini disampaikan. Kami memohon dukungan Bapak/Ibu untuk senantiasa memberikan motivasi agar Ananda selalu istiqomah dalam kebaikan.</p>
    </div>

    <div class="ttd-container">
        Cileungsi, <?php 
            $bulan_indo = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
            echo date('d') . ' ' . $bulan_indo[(int)date('m')] . ' ' . date('Y'); 
        ?> <br>
        Musyrif Kamar <?php echo htmlspecialchars($santri['kamar']); ?>
        
        <div class="nama-musyrif"><?php echo htmlspecialchars($nama_musyrif); ?></div>
    </div>

</body>
</html>
