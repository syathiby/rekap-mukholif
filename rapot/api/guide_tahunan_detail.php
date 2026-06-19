<?php
// rapot/api/guide_tahunan_detail.php
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

$cache_file = $cache_dir . '/guide_tahunan_detail.json';
$cache_lifetime = 3600; // 1 jam

// Bersihkan cache lama
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

// Generate konten panduan untuk detail rapor
$guide_html = '
    <div class="mb-4 text-center">
        <div class="d-inline-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle mb-3" style="width:64px;height:64px;">
            <i class="fas fa-file-invoice fs-3"></i>
        </div>
        <p class="text-muted small mb-0 px-3">Halaman ini menampilkan rekam jejak lengkap (akumulasi 1 tahun) milik satu santri secara spesifik. Berikut adalah penjelasan fitur-fiturnya:</p>
    </div>

    <!-- Feature 1 -->
    <div class="d-flex gap-3 mb-4">
        <div class="flex-shrink-0">
            <div class="d-flex align-items-center justify-content-center bg-primary text-white rounded-3 shadow-sm" style="width: 40px; height: 40px;">
                <i class="fas fa-table"></i>
            </div>
        </div>
        <div>
            <h6 class="fw-bold mb-1 text-dark">Tabel Rekap Nilai Setahun</h6>
            <div class="small text-muted mb-2" style="line-height:1.6;">
                <span class="badge bg-light text-dark border me-1"><i class="fas fa-calculator text-primary me-1"></i>Rata-Rata</span>
                Angka pada tabel adalah hasil kalkulasi rata-rata otomatis dari seluruh rapor bulanan.
            </div>
            <div class="small text-muted mb-2" style="line-height:1.6;">
                <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle me-1"><i class="fas fa-edit me-1"></i>Edit Nilai</span>
                Klik tombol edit (<i class="fas fa-edit"></i>) di kolom paling kanan tabel jika Anda ingin mengubah rata-rata sistem menjadi angka manual Anda sendiri.
            </div>
            <div class="small text-muted" style="line-height:1.6;">
                <div class="fw-bold text-dark mt-3 mb-1"><i class="fas fa-info-circle text-primary me-1"></i>Penjelasan Tanda Bintang (✱)</div>
                Nilai yang memiliki bintang berarti angka tersebut telah bergeser dari rata-rata aslinya. Warna bintang menunjukkan penyebabnya:
                <ul class="mb-0 mt-1 ps-3" style="list-style-type: disc;">
                    <li><span class="text-warning fw-bold">Kuning/Oranye ✱</span> : Nilainya diubah manual oleh Musyrif.</li>
                    <li><span class="text-danger fw-bold">Merah ✱</span> : Nilainya dikurangi otomatis oleh sistem (karena riwayat pelanggaran).</li>
                    <li><span class="text-success fw-bold">Hijau ✱</span> : Nilainya ditambah otomatis oleh sistem (karena riwayat reward/prestasi).</li>
                    <li><span class="text-primary fw-bold">Biru ✱</span> : Nilainya mendapat penambahan sekaligus pengurangan otomatis dari sistem.</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Feature 2 -->
    <div class="d-flex gap-3 mb-4">
        <div class="flex-shrink-0">
            <div class="d-flex align-items-center justify-content-center bg-danger text-white rounded-3 shadow-sm" style="width: 40px; height: 40px;">
                <i class="fas fa-balance-scale"></i>
            </div>
        </div>
        <div>
            <h6 class="fw-bold mb-1 text-dark">Akumulasi Poin & Rincian</h6>
            <div class="small text-muted" style="line-height:1.6;">
                Sistem menampilkan total <span class="text-danger fw-semibold">Poin Pelanggaran</span> dan <span class="text-success fw-semibold">Poin Reward</span> selama setahun penuh. Anda juga dapat melihat riwayat lengkap di bawah kotak poin.
            </div>
        </div>
    </div>

    <!-- Feature 3 -->
    <div class="d-flex gap-3 mb-4">
        <div class="flex-shrink-0">
            <div class="d-flex align-items-center justify-content-center bg-info text-white rounded-3 shadow-sm" style="width: 40px; height: 40px;">
                <i class="fas fa-pen-nib"></i>
            </div>
        </div>
        <div>
            <h6 class="fw-bold mb-1 text-dark">Edit Catatan Pembinaan</h6>
            <div class="small text-muted" style="line-height:1.6;">
                Narasi evaluasi akhir tahun dibuat secara otomatis oleh sistem. Jika ingin mengubahnya, klik tombol <span class="badge bg-primary"><i class="fas fa-edit me-1"></i>Edit Catatan</span> dan simpan penyesuaian Anda.
            </div>
        </div>
    </div>

    <!-- Feature 4 -->
    <div class="d-flex gap-3 mb-4">
        <div class="flex-shrink-0">
            <div class="d-flex align-items-center justify-content-center bg-success text-white rounded-3 shadow-sm" style="width: 40px; height: 40px;">
                <i class="fas fa-check-double"></i>
            </div>
        </div>
        <div>
            <h6 class="fw-bold mb-1 text-dark">Approval Rapor</h6>
            <div class="small text-muted" style="line-height:1.6;">
                Rapor harus di-sahkan melalui tombol <span class="badge bg-success"><i class="fas fa-check me-1"></i>Approve Rapor</span> agar bisa diunduh (Download) sebagai file PDF dan dicetak.
            </div>
        </div>
    </div>

    <!-- Alert Keamanan -->
    <div class="bg-dark text-white rounded-4 p-3 shadow-sm d-flex gap-3 align-items-center mt-2 border-start border-5 border-danger">
        <div class="flex-shrink-0 ms-1">
            <i class="fas fa-lock fs-3 text-danger"></i>
        </div>
        <div class="small" style="line-height:1.5;">
            <strong class="d-block mb-1 text-white">Mode Terkunci (Read-Only)</strong>
            <span class="text-light opacity-75">Ketika Rapor berstatus <b>APPROVED</b> atau <b>DOWNLOADED</b>, seluruh tombol edit akan lenyap untuk menjaga keaslian data yang sudah disahkan.</span>
        </div>
    </div>
';

$response = [
    'status' => 'success',
    'data' => [
        'title' => '<i class="fas fa-book-open text-primary me-2"></i>Panduan Detail Rapor',
        'content' => trim($guide_html)
    ]
];

$json_response = json_encode($response);
file_put_contents($cache_file, $json_response);

echo $json_response;
exit;
