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
    
    <h6 class="text-dark fw-bold mb-2"><i class="fas fa-magic text-primary me-2"></i>1. Generate & Generate Ulang</h6>
    <ul class="mb-4 small" style="padding-left: 1.25rem;">
      <li class="mb-1">Sistem akan secara otomatis menyusun narasi, menghitung poin pelanggaran/reward, serta merekap absensi kedisiplinan selama satu tahun ajaran penuh.</li>
      <li class="mb-1"><strong>Generate Ulang Massal:</strong> Secara standar, sistem <strong>hanya akan menimpa rapor yang berstatus DRAFT</strong>. Rapor yang sudah berstatus <em>APPROVED / DOWNLOADED</em> akan otomatis dilewati agar aman.</li>
      <li><strong>Opsi Centang (Bypass):</strong> Jika Anda mencentang opsi <em>"Generate Ulang juga rapor yang sudah APPROVED / DOWNLOADED"</em>, maka sistem akan <strong>memaksa menghapus rapor lama tersebut dan menggantinya dengan perhitungan baru (DRAFT)</strong>. Fitur ini aman dan <strong>tidak akan membuat data jadi ganda/dobel</strong>.</li>
    </ul>

    <div class="bg-light border-start border-4 border-warning rounded p-3 mb-4 small text-dark shadow-sm">
        <i class="fas fa-info-circle text-warning me-1"></i>
        <strong>Absen Data:</strong> Jika terdapat info "absen data", artinya ada santri yang <strong>tidak memiliki data Rapor Bulanan sama sekali</strong>, sehingga sistem otomatis melewatinya. <br><br>
        <i class="fas fa-ban text-danger me-1"></i>
        <strong>Generate Terkunci:</strong> Jika di dalam suatu kamar seluruh santrinya kosong data laporannya, maka tombol <strong>Generate tidak akan bisa ditekan sama sekali</strong>.
    </div>

    <h6 class="text-dark fw-bold mb-2"><i class="fas fa-tasks text-primary me-2"></i>2. Status Rapor & Download</h6>
    <ul class="mb-0 small" style="padding-left: 1.25rem;">
      <li class="mb-1"><strong>DRAFT:</strong> Rapor sudah dibuat otomatis, bisa di-generate ulang atau di-edit catatan/narasinya secara manual dengan mengklik tombol "Lihat".</li>
      <li class="mb-1"><strong>APPROVED:</strong> Rapor sudah dikunci/disetujui. Aman dari overwrite otomatis. Tombol "Download Approved" akan langsung muncul di halaman Daftar Santri.</li>
      <li><strong>Download PDF:</strong> Anda bisa mengunduh 1 file PDF panjang (semua santri gabung), atau dalam format ZIP (file PDF terpisah per santri).</li>
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
