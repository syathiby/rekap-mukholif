<?php
// Pastikan variabel sudah ada
$tipe = $tipe ?? 'daftar_hitam';
$arsip_nama = $arsip['judul'] ?? 'Arsip';
$arsip_tgl_mulai = date('d/m/Y', strtotime($arsip['tanggal_mulai'] ?? date('Y-m-d')));
$arsip_tgl_selesai = date('d/m/Y', strtotime($arsip['tanggal_selesai'] ?? date('Y-m-d')));
$filter_kelas = $filter_kelas ?? '';
$filter_kamar = $filter_kamar ?? '';

$filter_text = "arsip <strong>" . htmlspecialchars($arsip_nama) . " (" . $arsip_tgl_mulai . " s/d " . $arsip_tgl_selesai . ")</strong>";
if ($filter_kelas) $filter_text .= ", Kelas <strong>" . htmlspecialchars($filter_kelas) . "</strong>";
if ($filter_kamar) $filter_text .= ", Kamar <strong>" . htmlspecialchars($filter_kamar) . "</strong>";

if ($tipe === 'semua') {
    $banner_class = 'alert-info';
    $icon = 'fas fa-info-circle text-info';
    $title = 'Data Semua Santri (Arsip)';
    $desc = "Menampilkan arsip seluruh data santri pada periode $filter_text. <br><small>Daftar diurutkan berdasarkan Kamar, Kelas, dan Abjad Nama Santri.</small>";
} elseif ($tipe === 'daftar_hitam') {
    $banner_class = 'alert-danger';
    $icon = 'fas fa-exclamation-triangle';
    $title = 'Daftar Hitam Pelanggar (Arsip)';
    $desc = "Peringkat disusun murni berdasarkan <strong>Total Poin Pelanggaran</strong> terbanyak pada periode $filter_text. <br><small>Peringkat 1 (medali merah) adalah santri dengan riwayat pelanggaran paling berat.</small>";
} else {
    $formula_cur = $_GET['formula'] ?? 'semua_aspek';
    $sort_cur    = $_GET['sort_order'] ?? 'terbaik';
    
    if ($sort_cur === 'terbaik') {
        $banner_class = 'alert-success'; 
        $icon = 'fas fa-trophy text-success';
        $title = 'Peringkat Santri Terbaik (Arsip)';
        
        if ($formula_cur === 'semua_aspek') {
            $desc = "Menampilkan urutan santri berprestasi tertinggi pada periode $filter_text. <br><small>Skor Teladan dihitung dari: <strong>(Rata-rata Rapot × 20) + Poin Reward − Poin Pelanggaran</strong>.</small>";
        } else {
            $desc = "Menampilkan urutan santri dengan poin bersih tertinggi pada periode $filter_text. <br><small>Skor dihitung dari: <strong>Total Poin Reward − Total Poin Pelanggaran</strong> (tanpa mempertimbangkan rapot).</small>";
        }
    } else {
        $banner_class = 'alert-warning'; 
        $icon = 'fas fa-sort-amount-down text-warning';
        $title = 'Peringkat Santri Terbawah (Arsip)';
        
        if ($formula_cur === 'semua_aspek') {
            $desc = "Menampilkan urutan santri dengan performa terendah pada periode $filter_text. <br><small>Skor Teladan dihitung dari: <strong>(Rata-rata Rapot × 20) + Poin Reward − Poin Pelanggaran</strong>. Peringkat 1 adalah santri dengan skor paling minim.</small>";
        } else {
            $desc = "Menampilkan urutan santri dengan poin bersih paling minus (pelanggaran mendominasi) pada periode $filter_text. <br><small>Skor dihitung dari: <strong>Total Poin Reward − Total Poin Pelanggaran</strong>.</small>";
        }
    }
}
?>

<div class="alert <?= $banner_class ?> border-0 d-flex gap-3 align-items-start mb-4" style="border-radius:10px;">
    <i class="<?= $icon ?> mt-1 fs-4"></i>
    <div>
        <strong class="d-block mb-1 fs-6"><?= $title ?></strong> 
        <?= $desc ?>
    </div>
</div>
