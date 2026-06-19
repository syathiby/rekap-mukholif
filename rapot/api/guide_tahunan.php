<?php
// rapot/api/guide_tahunan.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../bootstrap/init.php';

// Hanya user yang login yang bisa akses API ini
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$cache_dir = __DIR__ . '/../../cache/guide';
if (!is_dir($cache_dir)) {
    mkdir($cache_dir, 0755, true);
}

$cache_file = $cache_dir . '/guide_tahunan.json';
$cache_lifetime = 3600; // 1 jam

// Bersihkan cache lama agar tidak menumpuk
$files = glob($cache_dir . '/*.json');
$now = time();
if ($files) {
    foreach ($files as $file) {
        if (is_file($file)) {
            if ($now - filemtime($file) >= $cache_lifetime) {
                unlink($file);
            }
        }
    }
}

// Gunakan cache jika masih valid
if (file_exists($cache_file) && ($now - filemtime($cache_file) < $cache_lifetime)) {
    $cached_data = file_get_contents($cache_file);
    echo $cached_data;
    exit;
}

// Generate konten panduan jika cache tidak ada / kedaluwarsa
$guide_html = '
    <p class="mb-4 small">Halaman ini digunakan untuk me-<em>generate</em> dan mengelola Rapor Tahunan berdasarkan akumulasi data Rapor Bulanan.</p>
    
    <h6 class="text-dark fw-bold mb-2"><i class="fas fa-magic text-primary me-2"></i>1. Pembuatan Rapor</h6>
    <ul class="mb-4 small" style="padding-left: 1.25rem;">
      <li class="mb-1">Klik tombol <strong>Generate</strong> pada kamar yang diinginkan.</li>
      <li class="mb-1">Sistem akan secara otomatis menyusun narasi, menghitung poin pelanggaran dan reward, serta menghitung absensi kedisiplinan selama satu tahun ajaran penuh.</li>
      <li>Rapor yang baru di-generate akan berstatus <strong>Draft</strong>.</li>
    </ul>

    <div class="bg-light border-start border-4 border-warning rounded p-3 mb-4 small text-dark shadow-sm">
        <i class="fas fa-info-circle text-warning me-1"></i>
        <strong>Absen Data:</strong> Jika terdapat info "absen data", artinya ada santri yang <strong>tidak memiliki data Rapor Bulanan sama sekali</strong>, sehingga sistem otomatis melewatinya. <br><br>
        <i class="fas fa-ban text-danger me-1"></i>
        <strong>Generate Terkunci:</strong> Jika di dalam suatu kamar seluruh santrinya kosong data laporannya, maka kolom keterangan akan tertulis <em>"Data kosong"</em> dan tombol <strong>Generate tidak akan bisa ditekan sama sekali</strong>.
    </div>

    <h6 class="text-dark fw-bold mb-2"><i class="fas fa-tasks text-primary me-2"></i>2. Status Rapor</h6>
    <ul class="mb-0 small" style="padding-left: 1.25rem;">
      <li class="mb-1"><strong>Draft:</strong> Rapor sudah dibuat, namun masih bisa di-<em>generate</em> ulang atau diubah catatannya secara manual.</li>
      <li class="mb-1"><strong>Approved:</strong> Rapor sudah disetujui (dikunci) dan siap didownload sebagai file PDF.</li>
      <li>Data dengan status <em>Approved</em> aman dari proses Generate Ulang massal, kecuali Anda secara paksa menghapus semuanya.</li>
    </ul>
';

$response = [
    'status' => 'success',
    'data' => [
        'title' => '<i class="fas fa-book-open text-primary me-2"></i>Panduan Rapor Tahunan',
        'content' => trim($guide_html)
    ]
];

$json_response = json_encode($response);
file_put_contents($cache_file, $json_response);

echo $json_response;
exit;
