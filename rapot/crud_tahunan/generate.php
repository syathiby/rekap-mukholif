<?php
// rapot/crud_tahunan/generate.php
// Form konfirmasi sebelum generate rapor tahunan (Custom AI, otomatis dari data bulanan)

require_once __DIR__ . '/../../bootstrap/init.php';
require_once __DIR__ . '/../config/helper.php';

guard('rapot_create');

$kamar   = trim($_GET['kamar']   ?? '');
$periode = trim($_GET['periode'] ?? '');

if (empty($kamar) || empty($periode)) {
    set_flash_message('Parameter kamar atau periode tidak lengkap.', 'danger');
    header('Location: index.php');
    exit;
}

if (!preg_match('/^\d{4}\/\d{4}$/', $periode)) {
    set_flash_message('Format periode tidak valid.', 'danger');
    header('Location: index.php');
    exit;
}

$kamar_filter_musyrif = checkMusyrifKamarAccess();
if ($kamar_filter_musyrif !== null && (string)$kamar_filter_musyrif !== $kamar) {
    set_flash_message('Anda tidak memiliki akses ke kamar ini.', 'danger');
    header('Location: index.php');
    exit;
}

$page_title = "Generate Rapor Tahunan — Kamar $kamar";

// Ambil daftar santri
try {
    $stmt_santri = $conn->prepare("SELECT id, nama, kelas FROM santri WHERE kamar = ? ORDER BY nama ASC");
    $stmt_santri->bind_param('s', $kamar);
    $stmt_santri->execute();
    $santri_list = $stmt_santri->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_santri->close();
} catch (Exception $e) {
    set_flash_message('Gagal mengambil data santri: ' . $e->getMessage(), 'danger');
    header('Location: index.php');
    exit;
}

if (empty($santri_list)) {
    set_flash_message("Tidak ada santri di Kamar $kamar.", 'warning');
    header('Location: index.php');
    exit;
}

// Hitung santri yang punya rapot bulanan
[$tahun_awal] = explode('/', $periode);
$tahun_awal_int  = (int)$tahun_awal;
$tahun_akhir_int = $tahun_awal_int + 1;

$santri_dengan_data = 0;
$santri_info        = [];

// Perbaikan N+1 Query: Lakukan 1x Query Massal dengan GROUP BY
$santri_ids = array_column($santri_list, 'id');
if (!empty($santri_ids)) {
    $placeholders = implode(',', array_fill(0, count($santri_ids), '?'));
    $sql_cek = "
        SELECT santri_id, 
               COUNT(DISTINCT bulan) as total_bulan,
               SUM(CASE WHEN bulan = 'Juni' THEN 1 ELSE 0 END) as has_juni
        FROM rapot_kepengasuhan 
        WHERE santri_id IN ($placeholders) 
          AND (
              (tahun = ? AND bulan IN ('Juli','Agustus','September','Oktober','November','Desember')) 
              OR 
              (tahun = ? AND bulan IN ('Januari','Februari','Maret','April','Mei','Juni'))
          )
        GROUP BY santri_id
    ";
    $stmt_cek = $conn->prepare($sql_cek);
    
    // Bind dinamis
    $types = str_repeat('i', count($santri_ids)) . 'ii';
    $params = $santri_ids;
    $params[] = $tahun_awal_int;
    $params[] = $tahun_akhir_int;
    $stmt_cek->bind_param($types, ...$params);
    $stmt_cek->execute();
    $result_cek = $stmt_cek->get_result();
    
    $map_counts = [];
    $map_juni = [];
    while ($row = $result_cek->fetch_assoc()) {
        $map_counts[$row['santri_id']] = (int)$row['total_bulan'];
        $map_juni[$row['santri_id']] = (int)$row['has_juni'] > 0;
    }
    $stmt_cek->close();

    // Map status dari rapot_tahunan (jika sudah pernah di-generate)
    $sql_status = "
        SELECT santri_id, status 
        FROM rapot_tahunan 
        WHERE santri_id IN ($placeholders) AND periode = ?
    ";
    $stmt_status = $conn->prepare($sql_status);
    $types_status = str_repeat('i', count($santri_ids)) . 's';
    $params_status = $santri_ids;
    $params_status[] = $periode;
    $stmt_status->bind_param($types_status, ...$params_status);
    $stmt_status->execute();
    $result_status = $stmt_status->get_result();
    
    $map_status = [];
    while ($row = $result_status->fetch_assoc()) {
        $map_status[$row['santri_id']] = $row['status'];
    }
    $stmt_status->close();

    // Assign ke santri_info
    foreach ($santri_list as $s) {
        $sid_cek = (int)$s['id'];
        $jumlah = $map_counts[$sid_cek] ?? 0;
        $has_juni = $map_juni[$sid_cek] ?? false;
        $status = $map_status[$sid_cek] ?? '';
        
        $is_eligible = ($jumlah >= 10 && $has_juni);

        if ($is_eligible) $santri_dengan_data++;
        $santri_info[$sid_cek] = [
            'jumlah_bulan' => $jumlah,
            'has_juni'     => $has_juni,
            'status'       => $status,
            'is_eligible'  => $is_eligible
        ];
    }
}

// Cek apakah sudah ada rapor tahunan
try {
    $stmt_existing = $conn->prepare("SELECT COUNT(*) as total FROM rapot_tahunan WHERE kamar = ? AND periode = ?");
    $stmt_existing->bind_param('ss', $kamar, $periode);
    $stmt_existing->execute();
    $existing = (int)$stmt_existing->get_result()->fetch_assoc()['total'];
    $stmt_existing->close();
} catch (Exception $e) {
    $existing = 0;
}

require_once __DIR__ . '/../../layouts/header.php';
?>

<style>
    :root {
        --c-primary: #1d6fa4;
        --c-primary-light: #e8f4fd;
        --c-success: #1a7c4f;
        --c-success-light: #e6f4ee;
        --c-warning: #b45309;
        --c-warning-light: #fef3c7;
        --c-danger: #b91c1c;
        --c-danger-light: #fee2e2;
        --c-border: #e2e8f0;
        --c-muted: #64748b;
        --c-text: #0f172a;
        --c-bg-soft: #f8fafc;
    }

    .gen-wrap { max-width: 960px; margin: 0 auto; }

    .gen-card {
        background: #fff; border: 1px solid var(--c-border);
        border-radius: .875rem; overflow: hidden; margin-bottom: 1rem;
        box-shadow: 0 1px 4px rgba(0,0,0,.04);
    }
    .gen-card-hdr {
        background: var(--c-bg-soft); border-bottom: 1px solid var(--c-border);
        padding: .875rem 1.25rem; display: flex; align-items: center;
        gap: .625rem; font-weight: 700; font-size: .9rem; color: var(--c-text);
    }

    /* ─── Santri row ─── */
    .santri-row {
        display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;
        padding: .875rem 1.25rem; border-bottom: 1px solid #f1f5f9;
        transition: background .15s;
    }
    .santri-row:last-child { border-bottom: none; }
    .santri-row:hover { background: #f8faff; }

    .s-avatar {
        width: 40px; height: 40px; border-radius: 50%; flex-shrink: 0;
        background: #1d4e7a; color: #fff; font-weight: 700; font-size: .875rem;
        display: flex; align-items: center; justify-content: center;
        box-shadow: 0 2px 4px rgba(29,78,122,.15);
    }
    .s-info { flex: 1; min-width: 180px; }
    .s-nama { font-weight: 600; font-size: .9rem; color: var(--c-text); line-height: 1.2; margin-bottom: .15rem; }
    .s-kelas { font-size: .78rem; color: var(--c-muted); }
    
    .s-badge-wrap { 
        display: flex; gap: .35rem; align-items: center; flex-wrap: wrap; 
        justify-content: flex-end; margin-left: auto;
    }
    .s-badge {
        font-size: .7rem; font-weight: 600;
        padding: .2rem .6rem; border-radius: 9999px; white-space: nowrap;
    }
    .badge-ok  { background: var(--c-success-light); color: var(--c-success); }
    .badge-no  { background: #f1f5f9; color: #94a3b8; }

    /* ─── Step checklist ─── */
    .step-item {
        display: flex; gap: .875rem; align-items: flex-start;
        padding: .75rem 1rem; border-radius: .625rem; margin-bottom: .5rem;
        border: 1px solid transparent;
    }
    .step-item.ok      { background: var(--c-success-light); border-color: #bbf7d0; }
    .step-item.warn    { background: var(--c-warning-light);  border-color: #fde68a; }
    .step-item.bad     { background: var(--c-danger-light);   border-color: #fca5a5; }
    .step-dot {
        width: 28px; height: 28px; border-radius: 50%; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        font-size: .8rem; font-weight: 700;
    }
    .step-item.ok   .step-dot { background: var(--c-success); color: #fff; }
    .step-item.warn .step-dot { background: var(--c-warning); color: #fff; }
    .step-item.bad  .step-dot { background: var(--c-danger);  color: #fff; }
    .step-ttl { font-weight: 700; font-size: .875rem; color: var(--c-text); }
    .step-sub { font-size: .78rem; color: var(--c-muted); margin-top: .1rem; }

    /* ─── How it works ─── */
    .how-list {
        list-style: none; padding: 0; margin: 0;
        display: flex; flex-direction: column; gap: .5rem;
    }
    .how-list li {
        display: flex; gap: .75rem; align-items: flex-start;
        font-size: .83rem; color: #475569;
    }
    .how-num {
        width: 22px; height: 22px; border-radius: 50%; flex-shrink: 0;
        background: var(--c-primary-light); color: var(--c-primary);
        font-weight: 700; font-size: .72rem;
        display: flex; align-items: center; justify-content: center;
    }

    /* ─── Generate button ─── */
    .btn-gen {
        border-radius: .75rem; padding: .75rem 1.75rem;
        font-weight: 700; font-size: .95rem; letter-spacing: .015em;
        transition: all .2s;
    }
    .btn-gen:hover:not(:disabled) {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(22,163,74,.25);
    }

    @media (max-width: 576px) {
        .santri-row { padding: .75rem 1rem; gap: .75rem; }
        .s-avatar   { width: 36px; height: 36px; font-size: .8rem; }
        .s-badge-wrap { width: 100%; justify-content: flex-start; margin-left: 0; padding-left: calc(36px + .75rem); }
    }
</style>

<div class="container-fluid py-4 px-3 px-md-4">
<div class="gen-wrap">

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="../index.php" class="text-decoration-none">Rapot Kepengasuhan</a></li>
            <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Rapor Tahunan</a></li>
            <li class="breadcrumb-item active">Generate — Kamar <?= htmlspecialchars($kamar) ?></li>
        </ol>
    </nav>

    <!-- Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="fw-bolder mb-1" style="color:var(--c-text);">
                <i class="fas fa-file-alt me-2" style="color:var(--c-primary);"></i>Generate Rapor Tahunan
                <button type="button" class="btn btn-sm btn-link text-info p-0 ms-2" data-bs-toggle="modal" data-bs-target="#guideModal" title="Buku Panduan">
                    <i class="fas fa-info-circle fs-5"></i>
                </button>
            </h3>
            <p class="text-muted mb-0 small">
                Kamar <strong><?= htmlspecialchars($kamar) ?></strong>
                &mdash; Periode <strong><?= htmlspecialchars($periode) ?></strong>
            </p>
        </div>
        <a href="index.php" class="btn btn-light border fw-medium" style="border-radius:.75rem;">
            <i class="fas fa-arrow-left me-1"></i> Kembali
        </a>
    </div>

    <div class="row gx-4 gy-3">

        <!-- Kolom Kiri: Form + Daftar Santri -->
        <div class="col-lg-8">

            <?php if ($existing > 0): ?>
            <div class="d-flex align-items-center p-3 mb-3" style="background:#fefce8; border:1px solid #fef08a; border-radius:.75rem; color:#854d0e;">
                <i class="fas fa-info-circle fs-5 me-3" style="color:#eab308;"></i>
                <div class="small lh-sm">
                    <strong><?= $existing ?> rapor sebelumnya ditemukan.</strong> 
                    Secara default, hanya rapor DRAFT yang ditimpa ulang. Centang opsi di bawah jika ingin menimpa paksa semua rapor.
                </div>
            </div>
            <?php endif; ?>

            <?php if ($santri_dengan_data < count($santri_list)): ?>
            <div class="d-flex align-items-center p-3 mb-4" style="background:#fef2f2; border:1px solid #fecaca; border-radius:.75rem; color:#991b1b;">
                <i class="fas fa-exclamation-circle fs-5 me-3" style="color:#ef4444;"></i>
                <div class="small lh-sm">
                    <strong><?= count($santri_list) - $santri_dengan_data ?> santri dilewati otomatis.</strong>
                    Syarat rapot tahunan: minimal 10 bulan berbeda & wajib ada rapot bulan Juni. Lengkapi rapot bulanan terlebih dahulu.
                </div>
            </div>
            <?php endif; ?>

            <!-- Form -->
            <form action="process.php" method="POST" id="form-generate">
                <input type="hidden" name="csrf_token" value="<?= csrf_generate() ?>">
                <input type="hidden" name="kamar_id" value="<?= htmlspecialchars($kamar) ?>">
                <input type="hidden" name="periode"  value="<?= htmlspecialchars($periode) ?>">

                <!-- Daftar santri -->
                <div class="gen-card">
                    <div class="gen-card-hdr">
                        <i class="fas fa-users" style="color:var(--c-primary);"></i>
                        Daftar Santri
                        <span class="ms-auto badge rounded-pill" style="background:var(--c-success-light);color:var(--c-success);font-size:.75rem;">
                            <?= $santri_dengan_data ?>/<?= count($santri_list) ?> siap di-generate
                        </span>
                    </div>
                    <?php foreach ($santri_list as $s):
                        $info = $santri_info[(int)$s['id']] ?? ['jumlah_bulan' => 0, 'has_juni' => false, 'status' => '', 'is_eligible' => false];
                        $is_eligible = $info['is_eligible'];
                        $jml_bln  = $info['jumlah_bulan'];
                        $has_juni = $info['has_juni'];
                        $status_rapor = $info['status'];
                    ?>
                    <div class="santri-row">
                        <div class="s-avatar"><?= strtoupper(substr($s['nama'], 0, 2)) ?></div>
                        <div class="s-info">
                            <div class="s-nama"><?= htmlspecialchars($s['nama']) ?></div>
                            <div class="s-kelas"><i class="fas fa-graduation-cap me-1"></i>Kelas <?= htmlspecialchars($s['kelas'] ?? 'N/A') ?></div>
                        </div>
                        <div class="s-badge-wrap">
                            <?php if ($is_eligible): ?>
                                <span class="s-badge badge-ok">
                                    <?php if (in_array($status_rapor, ['APPROVED', 'EXPORTED'])): ?>
                                        <i class="fas fa-shield-alt me-1 text-primary"></i>Sudah <?= $status_rapor === 'EXPORTED' ? 'DOWNLOADED' : $status_rapor ?>
                                    <?php else: ?>
                                        <i class="fas fa-check me-1"></i>Siap (<?= $jml_bln ?> bln)
                                    <?php endif; ?>
                                </span>
                            <?php else: ?>
                                <?php if ($jml_bln < 10): ?>
                                    <span class="s-badge" style="background:#fee2e2; color:#b91c1c;"><i class="fas fa-times me-1"></i>Data <?= $jml_bln ?>/10 bln</span>
                                <?php endif; ?>
                                <?php if (!$has_juni): ?>
                                    <span class="s-badge" style="background:#fee2e2; color:#b91c1c;"><i class="fas fa-times me-1"></i>Tanpa Juni</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($existing > 0): ?>
                <div class="form-check mt-3 mb-2 px-4">
                    <input class="form-check-input" type="checkbox" name="overwrite_approved" id="overwrite_approved" value="1">
                    <label class="form-check-label fw-bold text-danger small" for="overwrite_approved">
                        Generate Ulang juga rapor yang sudah APPROVED / DOWNLOADED
                    </label>
                </div>
                <?php endif; ?>

                <!-- Tombol Submit -->
                <div class="d-flex gap-3 flex-wrap mt-3 justify-content-center">
                    <button type="submit" id="btn-generate" class="btn btn-success btn-gen"
                            <?= $santri_dengan_data === 0 ? 'disabled' : '' ?>>
                        <i class="fas fa-magic me-2"></i>
                        <?= $existing > 0 ? 'Generate Ulang Semua' : 'Generate Rapor Tahunan' ?>
                    </button>
                    <a href="index.php" class="btn btn-light border fw-medium px-4 d-flex align-items-center" style="border-radius:.75rem;">Batal</a>
                </div>
            </form>
        </div>

        <!-- Kolom Kanan: Info -->
        <div class="col-lg-4">

            <!-- Pra-cek -->
            <div class="gen-card mb-3">
                <div class="gen-card-hdr">
                    <i class="fas fa-clipboard-check" style="color:var(--c-primary);"></i>
                    Pra-Cek Sistem
                </div>
                <div class="p-3">
                    <div class="step-item <?= count($santri_list) > 0 ? 'ok' : 'bad' ?>">
                        <div class="step-dot"><i class="fas <?= count($santri_list) > 0 ? 'fa-check' : 'fa-times' ?>"></i></div>
                        <div>
                            <div class="step-ttl">Data Santri</div>
                            <div class="step-sub"><?= count($santri_list) ?> santri di Kamar <?= htmlspecialchars($kamar) ?></div>
                        </div>
                    </div>
                    <div class="step-item <?= $santri_dengan_data === count($santri_list) ? 'ok' : ($santri_dengan_data > 0 ? 'warn' : 'bad') ?>">
                        <div class="step-dot"><i class="fas <?= $santri_dengan_data > 0 ? 'fa-check' : 'fa-times' ?>"></i></div>
                        <div>
                            <div class="step-ttl">Rapot Bulanan</div>
                            <div class="step-sub"><?= $santri_dengan_data ?>/<?= count($santri_list) ?> santri punya data</div>
                        </div>
                    </div>
                    <div class="step-item ok">
                        <div class="step-dot"><i class="fas fa-check"></i></div>
                        <div>
                            <div class="step-ttl">Custom AI</div>
                            <div class="step-sub">Catatan dihasilkan otomatis — cepat & hemat</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cara kerja -->
            <div class="gen-card">
                <div class="gen-card-hdr">
                    <i class="fas fa-cogs" style="color:var(--c-primary);"></i>
                    Alur Pemrosesan
                </div>
                <div class="p-3">
                    <ul class="how-list">
                        <li><span class="how-num">1</span>Menghimpun keseluruhan data rapor bulanan setiap santri.</li>
                        <li><span class="how-num">2</span>Menghitung rata-rata komulatif per sub-mutu secara presisi.</li>
                        <li><span class="how-num">3</span>Kalkulasi nilai otomatis berdasarkan akumulasi pelanggaran & prestasi.</li>
                        <li><span class="how-num">4</span>Menyusun narasi evaluasi rapor via algoritma cerdas (Custom AI).</li>
                        <li><span class="how-num">5</span>Menyimpan hasil draft ke dalam basis data sistem.</li>
                        <li><span class="how-num">6</span>Proses verifikasi dan validasi akhir oleh para musyrif.</li>
                    </ul>
                </div>
            </div>

            <!-- Estimasi waktu -->
            <div class="alert alert-secondary small border-0 mt-2" style="border-radius:.75rem;font-size:.8rem;">
                <i class="fas fa-bolt me-1 text-warning"></i>
                Estimasi Proses: <strong>&plusmn; 2 Detik</strong> — Pemrosesan rapot berjalan di sisi server dengan Custom AI tanpa API pihak ketiga.
            </div>

        </div>

    </div>
</div>
</div>

<script>
document.getElementById('form-generate')?.addEventListener('submit', function() {
    const btn = document.getElementById('btn-generate');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Memproses...';
    btn.classList.replace('btn-success', 'btn-secondary');
});
</script>

<!-- Modal Panduan -->
<div class="modal fade" id="guideModal" tabindex="-1" aria-labelledby="guideModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 1rem;">
      <div class="modal-header border-bottom-0 pb-0 mt-2 mx-2">
        <h5 class="modal-title fw-bolder text-dark" id="guideModalLabel">
            <i class="fas fa-spinner fa-spin text-primary me-2"></i>Memuat Panduan...
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body pt-3 px-4 pb-4 text-muted" id="guideModalBody">
         <div class="text-center py-4">
             <div class="spinner-border text-primary" role="status">
                 <span class="visually-hidden">Loading...</span>
             </div>
         </div>
      </div>
      <div class="modal-footer border-top-0 pt-0 pb-4 px-4">
        <button type="button" class="btn btn-primary w-100 fw-medium shadow-sm" style="border-radius: 0.75rem;" data-bs-dismiss="modal">Saya Mengerti</button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const guideModal = document.getElementById('guideModal');
    let guideLoaded = false;
    
    if (guideModal) {
        guideModal.addEventListener('show.bs.modal', function () {
            if (guideLoaded) return;
            
            fetch('../api/guide_tahunan.php')
                .then(response => response.json())
                .then(res => {
                    if(res.status === 'success') {
                        document.getElementById('guideModalLabel').innerHTML = res.data.title;
                        document.getElementById('guideModalBody').innerHTML = res.data.content;
                        guideLoaded = true;
                    } else {
                        document.getElementById('guideModalLabel').innerHTML = '<i class="fas fa-exclamation-triangle text-danger me-2"></i>Gagal Memuat';
                        document.getElementById('guideModalBody').innerHTML = '<div class="alert alert-danger">Gagal memuat panduan: ' + (res.message || 'Error tidak diketahui') + '</div>';
                    }
                })
                .catch(err => {
                    document.getElementById('guideModalLabel').innerHTML = '<i class="fas fa-exclamation-triangle text-danger me-2"></i>Koneksi Error';
                    document.getElementById('guideModalBody').innerHTML = '<div class="alert alert-danger">Terjadi kesalahan saat menghubungi server.</div>';
                });
        });
    }
});
</script>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
