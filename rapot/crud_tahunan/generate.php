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
        SELECT santri_id, COUNT(*) as total 
        FROM rapot_kepengasuhan 
        WHERE santri_id IN ($placeholders) AND (tahun = ? OR tahun = ?)
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
    while ($row = $result_cek->fetch_assoc()) {
        $map_counts[$row['santri_id']] = (int)$row['total'];
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
        $status = $map_status[$sid_cek] ?? '';
        
        if ($jumlah > 0) $santri_dengan_data++;
        $santri_info[$sid_cek] = [
            'jumlah_bulan' => $jumlah,
            'status' => $status
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
        display: flex; align-items: center; gap: 1rem;
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
    .s-nama { font-weight: 600; font-size: .9rem; color: var(--c-text); }
    .s-kelas { font-size: .78rem; color: var(--c-muted); }
    .s-badge {
        margin-left: auto; font-size: .7rem; font-weight: 600;
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
        .santri-row { padding: .75rem 1rem; }
        .s-avatar   { width: 36px; height: 36px; font-size: .8rem; }
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
            <div class="alert alert-warning mb-3" style="border-radius:.875rem;">
                <i class="fas fa-info-circle me-2"></i>
                Sudah ada <strong><?= $existing ?> rapor tahunan</strong> yang di-generate sebelumnya.<br>
                Secara default, <strong>hanya rapor DRAFT yang akan ditimpa ulang</strong> (rapor APPROVED/DOWNLOADED aman).<br>
                Jika Anda mencentang opsi di bawah, sistem akan <strong>memaksa menghapus dan menimpa ulang semua rapor</strong> tanpa menyebabkan data ganda.
            </div>
            <?php endif; ?>

            <?php if ($santri_dengan_data < count($santri_list)): ?>
            <div class="alert alert-info mb-3" style="border-radius:.875rem;">
                <i class="fas fa-info-circle me-2"></i>
                <strong><?= count($santri_list) - $santri_dengan_data ?> santri</strong> belum memiliki data rapot bulanan
                untuk periode <?= htmlspecialchars($periode) ?> — akan dilewati otomatis.
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
                        $has_data = ($santri_info[(int)$s['id']]['jumlah_bulan'] ?? 0) > 0;
                        $jml_bln  = $santri_info[(int)$s['id']]['jumlah_bulan'] ?? 0;
                        $status_rapor = $santri_info[(int)$s['id']]['status'] ?? '';
                    ?>
                    <div class="santri-row">
                        <div class="s-avatar"><?= strtoupper(substr($s['nama'], 0, 2)) ?></div>
                        <div>
                            <div class="s-nama"><?= htmlspecialchars($s['nama']) ?></div>
                            <div class="s-kelas"><i class="fas fa-graduation-cap me-1"></i>Kelas <?= htmlspecialchars($s['kelas'] ?? 'N/A') ?></div>
                        </div>
                        <span class="s-badge <?= $has_data ? 'badge-ok' : 'badge-no' ?>">
                            <?php if ($has_data): ?>
                                <?php if (in_array($status_rapor, ['APPROVED', 'EXPORTED'])): ?>
                                    <i class="fas fa-shield-alt me-1 text-primary"></i>Sudah <?= $status_rapor === 'EXPORTED' ? 'DOWNLOADED' : $status_rapor ?> (Aman)
                                <?php else: ?>
                                    <i class="fas fa-check me-1"></i><?= $jml_bln ?> bulan data
                                <?php endif; ?>
                            <?php else: ?>
                            <i class="fas fa-minus me-1"></i>Belum ada data
                            <?php endif; ?>
                        </span>
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
