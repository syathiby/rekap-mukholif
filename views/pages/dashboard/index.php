<?php
$can_view_pel_terkini = \App\Helpers\AuthHelper::hasPermission('rekap_view_statistik'); 
$can_view_santri_teladan = \App\Helpers\AuthHelper::hasPermission('rekap_santri_teladan');   
$can_view_top_pelanggar = \App\Helpers\AuthHelper::hasPermission('rekap_pelanggaran_umum');  
$can_view_santri = \App\Helpers\AuthHelper::hasPermission('santri_view');
$can_view_jp = \App\Helpers\AuthHelper::hasPermission('jenis_pelanggaran_view');
$can_view_chart = \App\Helpers\AuthHelper::hasPermission('rekap_view_statistik');
?>
<style>
.rank-badge-sm {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 14px;
    border-radius: 50%;
    margin-left: auto;
}
.rank-badge-sm.rank-1 { background: linear-gradient(135deg, #fbbf24, #d97706); color: white; box-shadow: 0 3px 8px rgba(217, 119, 6, 0.3); }
.rank-badge-sm.rank-2 { background: linear-gradient(135deg, #cbd5e1, #64748b); color: white; box-shadow: 0 3px 8px rgba(100, 116, 139, 0.3); }
.rank-badge-sm.rank-3 { background: linear-gradient(135deg, #fca5a5, #b91c1c); color: white; box-shadow: 0 3px 8px rgba(185, 28, 28, 0.3); }
.rank-badge-sm.rank-other { background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0; }

.mini-stats {
    display: flex;
    gap: 12px;
    margin-top: 4px;
}
.mini-stat {
    font-size: 11px;
    font-weight: 600;
    color: #64748b;
    display: flex;
    align-items: center;
    gap: 4px;
}
.mini-stat span {
    font-weight: 800;
}
</style>
<div class="dashboard-wrapper">
        
        <div class="row g-4 mb-4">
            <!-- Card 1: Total Santri -->
            <div class="col-xl-3 col-md-6">
                <div class="card h-100 shadow-sm border-0 rounded-4 overflow-hidden position-relative" style="background: linear-gradient(145deg, #ffffff, #f8fafc);">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h6 class="text-muted fw-semibold mb-1">Total Santri</h6>
                                <h3 class="fw-bold mb-0 text-dark" style="font-size: 2.2rem; letter-spacing:-0.5px;"><?= number_format($stats['santri'] ?? 0) ?></h3>
                            </div>
                            <div class="d-flex align-items-center justify-content-center rounded-3" style="width: 48px; height: 48px; background: rgba(16, 185, 129, 0.1); color: #10b981;">
                                <i class="fas fa-user-graduate fa-lg"></i>
                            </div>
                        </div>
                        <div class="text-muted small mt-2 d-flex justify-content-between align-items-center">
                            <span>Santri aktif terdaftar</span>
                            <?php if ($can_view_santri): ?>
                                <a href="santri/index.php" class="text-success text-decoration-none fw-semibold stretched-link" style="font-size:0.8rem;">Lihat <i class="fas fa-arrow-right ms-1"></i></a>
                            <?php else: ?>
                                <i class="fas fa-lock opacity-50"></i>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Card 2: Jenis Pelanggaran -->
            <div class="col-xl-3 col-md-6">
                <div class="card h-100 shadow-sm border-0 rounded-4 overflow-hidden position-relative" style="background: linear-gradient(145deg, #ffffff, #fffbeb);">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h6 class="text-muted fw-semibold mb-1">Kategori Aturan</h6>
                                <h3 class="fw-bold mb-0 text-dark" style="font-size: 2.2rem; letter-spacing:-0.5px;"><?= number_format($stats['jenis_pelanggaran'] ?? 0) ?></h3>
                            </div>
                            <div class="d-flex align-items-center justify-content-center rounded-3" style="width: 48px; height: 48px; background: rgba(245, 158, 11, 0.1); color: #f59e0b;">
                                <i class="fas fa-clipboard-list fa-lg"></i>
                            </div>
                        </div>
                        <div class="text-muted small mt-2 d-flex justify-content-between align-items-center">
                            <span>Macam-macam aturan</span>
                            <?php if ($can_view_jp): ?>
                                <a href="jenis-pelanggaran/index.php" class="text-warning text-decoration-none fw-semibold stretched-link" style="font-size:0.8rem;">Kelola <i class="fas fa-arrow-right ms-1"></i></a>
                            <?php else: ?>
                                <i class="fas fa-lock opacity-50"></i>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Card 3: Total Pelanggaran -->
            <div class="col-xl-3 col-md-6">
                <div class="card h-100 shadow-sm border-0 rounded-4 overflow-hidden position-relative" style="background: linear-gradient(145deg, #ffffff, #fef2f2);">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h6 class="text-muted fw-semibold mb-1">Pelanggaran Tercatat</h6>
                                <h3 class="fw-bold mb-0 text-dark" style="font-size: 2.2rem; letter-spacing:-0.5px;"><?= number_format($stats['total_pelanggaran'] ?? 0) ?></h3>
                            </div>
                            <div class="d-flex align-items-center justify-content-center rounded-3" style="width: 48px; height: 48px; background: rgba(239, 68, 68, 0.1); color: #ef4444;">
                                <i class="fas fa-exclamation-circle fa-lg"></i>
                            </div>
                        </div>
                        
                        <div class="text-muted small mt-2 d-flex justify-content-between align-items-end">
                            <div>
                                Total kasus tercatat
                                <?php if(!empty($frequent_violation)): ?>
                                    <br><span class="text-danger fw-medium d-inline-block mt-1" style="font-size: 0.75rem;"><i class="fas fa-fire me-1"></i>Sering: <?= htmlspecialchars($frequent_violation['nama_pelanggaran']) ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if ($can_view_chart): ?>
                                <a href="rekap/chart.php" class="text-danger text-decoration-none fw-semibold stretched-link" style="font-size:0.8rem;">Statistik <i class="fas fa-arrow-right ms-1"></i></a>
                            <?php else: ?>
                                <i class="fas fa-lock opacity-50 mb-1"></i>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Card 4: Santri Teladan -->
            <div class="col-xl-3 col-md-6">
                <div class="card h-100 shadow-sm border-0 rounded-4 overflow-hidden position-relative" style="background: linear-gradient(145deg, #ffffff, #f0fdf4);">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h6 class="text-muted fw-semibold mb-1">Santri Prestasi</h6>
                                <h3 class="fw-bold mb-0 text-dark" style="font-size: 2.2rem; letter-spacing:-0.5px;"><?= number_format($stats['santri_tanpa_pelanggaran'] ?? 0) ?></h3>
                            </div>
                            <div class="d-flex align-items-center justify-content-center rounded-3" style="width: 48px; height: 48px; background: rgba(59, 130, 246, 0.1); color: #3b82f6;">
                                <i class="fas fa-award fa-lg"></i>
                            </div>
                        </div>
                        <div class="text-muted small mt-2 d-flex justify-content-between align-items-center">
                            <span>Tanpa catatan kasus</span>
                            <?php if ($can_view_santri_teladan): ?>
                                <a href="rekap/santri_teladan.php" class="text-primary text-decoration-none fw-semibold stretched-link" style="font-size:0.8rem;">Daftar <i class="fas fa-arrow-right ms-1"></i></a>
                            <?php else: ?>
                                <i class="fas fa-lock opacity-50"></i>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-xl-8 col-lg-7">
                <!-- Pelanggaran Terkini -->
                <div class="card-premium h-100">
                    <div class="card-header-premium">
                        <h2 class="card-title"><i class="fas fa-history text-primary"></i> Pelanggaran Terkini</h2>
                        <?php if ($can_view_pel_terkini): ?>
                            <a href="rekap/tren_pelanggaran.php" class="btn btn-sm btn-light border">Lihat semua <i class="fas fa-chevron-right ms-1"></i></a>
                        <?php endif; ?>
                    </div>
                    
                    <div class="p-0">
                        <div class="table-responsive d-none d-md-block">
                            <table class="table-premium w-100 mb-0">
                                <thead>
                                    <tr>
                                        <th style="width: 5%;">No</th>
                                        <th style="width: 25%;">Nama Santri</th>
                                        <th style="width: 30%;">Pelanggaran</th>
                                        <th style="width: 20%;">Waktu</th>
                                        <th style="width: 20%;">Pencatat</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(count($recent_violations) > 0): ?>
                                        <?php $no = 1; foreach($recent_violations as $violation): $time_ago = \App\Helpers\FormatHelper::timeAgo($violation['tanggal']); ?>
                                            <tr>
                                                <td><?= $no++ ?></td>
                                                <td>
                                                    <div class="fw-medium text-dark"><?= htmlspecialchars($violation['nama']) ?></div>
                                                    <div class="text-muted small"><i class="fas fa-home"></i> Km. <?= htmlspecialchars($violation['kamar']) ?></div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-light text-dark border text-wrap text-start">
                                                        <?= htmlspecialchars($violation['nama_pelanggaran']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="text-dark small"><?= date('d M Y H:i', strtotime($violation['tanggal'])) ?></div>
                                                    <div class="text-muted text-xs"><?= $time_ago ?></div>
                                                </td>
                                                <td><span class="text-muted small"><i class="fas fa-user-edit"></i> <?= htmlspecialchars($violation['pencatat'] ?? 'N/A') ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="5" class="text-center py-5 text-muted"><i class="fas fa-check-circle fs-2 text-success mb-2 d-block"></i> Alhamdulillah, tidak ada pelanggaran baru-baru ini.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Mobile Cards -->
                        <div class="d-md-none p-3">
                            <?php if(count($recent_violations) > 0): ?>
                                                                <div class="mobile-violations-list">
                                    <?php foreach($recent_violations as $violation): 
                                        $time_ago = \App\Helpers\FormatHelper::timeAgo($violation['tanggal']);
                                        $initial = htmlspecialchars(substr($violation['nama'] ?? 'S', 0, 1));
                                        
                                        $avatar_bg = '#f1f5f9';
                                        $avatar_color = '#475569';
                                        $pel_nama = strtolower($violation['nama_pelanggaran'] ?? '');
                                        if (strpos($pel_nama, 'bahasa') !== false) { $avatar_bg = '#eef2ff'; $avatar_color = '#4f46e5'; }
                                        elseif (strpos($pel_nama, 'diniyyah') !== false) { $avatar_bg = '#ecfdf5'; $avatar_color = '#10b981'; }
                                        elseif (strpos($pel_nama, 'tahfidz') !== false) { $avatar_bg = '#fff1f2'; $avatar_color = '#f43f5e'; }
                                        elseif (strpos($pel_nama, 'kebersihan') !== false) { $avatar_bg = '#f0fdfa'; $avatar_color = '#0d9488'; }
                                    ?>
                                        <div class="mobile-violation-card mb-3 p-3 border rounded shadow-sm bg-white">
                                            <div class="mobile-violation-avatar rounded-circle d-flex align-items-center justify-content-center fw-bold" style="background-color: <?= $avatar_bg ?>; color: <?= $avatar_color ?>;">
                                                <?= $initial ?>
                                            </div>
                                            <div class="mobile-violation-content flex-grow-1">
                                                <div class="d-flex justify-content-between align-items-start mb-1">
                                                    <div class="fw-bold text-dark text-break pe-2"><?= htmlspecialchars($violation['nama'] ?? 'Penghuni Kamar') ?></div>
                                                    <div class="text-muted text-xs text-end flex-shrink-0"><?= $time_ago ?></div>
                                                </div>
                                                <div class="mobile-violation-title fw-medium text-danger mb-2">
                                                    <?= htmlspecialchars($violation['nama_pelanggaran'] ?? '') ?>
                                                </div>
                                                <div class="d-flex flex-wrap gap-3 text-muted small">
                                                    <span><i class="fas fa-home"></i> Km. <?= htmlspecialchars($violation['kamar'] ?? '') ?></span>
                                                    <span><i class="fas fa-user-edit"></i> <?= htmlspecialchars($violation['pencatat'] ?? 'N/A') ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4 text-muted border rounded bg-light">
                                    <i class="fas fa-check-circle text-success fs-1 mb-2"></i>
                                    <p class="mb-0">Alhamdulillah, tidak ada pelanggaran baru-baru ini.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4 col-lg-5">
                <!-- Sorotan Santri -->
                <div class="card-premium h-100">
                    <div class="card-header-premium">
                        <h2 class="card-title"><i class="fas fa-star text-warning"></i> Sorotan Santri</h2>
                    </div>
                    <div class="card-body p-3">
                        <ul class="nav nav-pills custom-student-tabs w-100 mb-3" id="studentTabs" role="tablist">
                            <li class="nav-item d-flex" role="presentation">
                                <button class="nav-link active w-100 d-flex flex-column flex-sm-row align-items-center justify-content-center gap-1 gap-sm-2 h-100 p-2" id="violators-tab" data-bs-toggle="tab" data-bs-target="#violators-panel" type="button" role="tab">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <span class="text-wrap" style="line-height: 1.2; font-size: 0.9em;">Top Pelanggar</span>
                                </button>
                            </li>
                            <li class="nav-item d-flex" role="presentation">
                                <button class="nav-link w-100 d-flex flex-column flex-sm-row align-items-center justify-content-center gap-1 gap-sm-2 h-100 p-2" id="teladan-tab" data-bs-toggle="tab" data-bs-target="#teladan-panel" type="button" role="tab">
                                    <i class="fas fa-medal"></i>
                                    <span class="text-wrap" style="line-height: 1.2; font-size: 0.9em;">Santri Teladan</span>
                                </button>
                            </li>
                            <li class="nav-item d-flex" role="presentation">
                                <button class="nav-link w-100 d-flex flex-column flex-sm-row align-items-center justify-content-center gap-1 gap-sm-2 h-100 p-2" id="histori-tab" data-bs-toggle="tab" data-bs-target="#histori-panel" type="button" role="tab" data-bs-toggle="tooltip" title="Total akumulasi poin seumur hidup (All-Time)">
                                    <i class="fas fa-history"></i>
                                    <span class="text-wrap" style="line-height: 1.2; font-size: 0.9em;">Top Histori</span>
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content" id="studentTabsContent">
                            <div class="tab-pane fade show active" id="violators-panel" role="tabpanel">
                                <?php if ($can_view_top_pelanggar): ?>
                                    <div class="text-end mb-3"><a href="rekap/pelanggaran_umum.php" class="text-primary text-decoration-none small fw-medium">Lihat semua <i class="fas fa-arrow-right"></i></a></div>
                                <?php endif; ?>
                                
                                <div class="student-list d-flex flex-column gap-3">
                                    <?php if(count($top_violators) > 0): ?>
                                        <?php foreach($top_violators as $violator): ?>
                                            <div class="student-item d-flex align-items-center p-3 border rounded bg-white shadow-sm">
                                                <div class="student-avatar rounded-circle text-white d-flex align-items-center justify-content-center me-3 fw-bold" style="background: linear-gradient(135deg, #ef4444, #b91c1c); box-shadow: 0 3px 8px rgba(239,68,68,0.2);">
                                                    <?= htmlspecialchars(substr($violator['nama'], 0, 1)) ?>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="fw-bold text-dark text-truncate" style="max-width: 180px;"><?= htmlspecialchars($violator['nama']) ?></div>
                                                    <div class="text-muted" style="font-size: 11px;">
                                                        <span class="me-2"><i class="fas fa-home opacity-75"></i> Km. <?= htmlspecialchars($violator['kamar']) ?></span>
                                                    </div>
                                                    <div class="mt-1">
                                                        <span class="badge bg-light text-secondary border px-2 py-1" style="font-size: 10px;">
                                                            Poin Bersih: <strong class="text-dark"><?= max(0, (int)$violator['poin_bersih_periode']) ?></strong>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="badge text-white rounded-pill px-3 py-2 fw-bold" style="background-color: #ef4444; font-size: 14px;">
                                                    <?= (int)$violator['total_poin'] ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>

                                        <div class="text-center text-muted py-4"><i class="fas fa-info-circle fs-2 mb-2"></i><p>Tidak ada data pelanggar</p></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="tab-pane fade" id="teladan-panel" role="tabpanel">
                                <?php if ($can_view_santri_teladan): ?>
                                    <div class="text-end mb-3"><a href="rekap/santri_teladan.php" class="text-primary text-decoration-none small fw-medium">Lihat semua <i class="fas fa-arrow-right"></i></a></div>
                                <?php endif; ?>
                                
                                <div class="student-list d-flex flex-column gap-3">
                                    <?php if(count($best_students) > 0): ?>
                                        <?php $no = 1; foreach($best_students as $student): 
                                            // Menentukan warna rank
                                            if ($no === 1) $rank_class = 'rank-1';
                                            elseif ($no === 2) $rank_class = 'rank-2';
                                            elseif ($no === 3) $rank_class = 'rank-3';
                                            else $rank_class = 'rank-other';
                                            
                                            $rapot = round((float)$student['avg_rapot'], 1);
                                            $str_rapot = ($rapot > 0) ? number_format($rapot, 1, '.', '') : '-';
                                        ?>
                                            <div class="student-item d-flex align-items-center p-3 border rounded bg-white shadow-sm position-relative">
                                                <div class="student-avatar rounded-circle text-white d-flex align-items-center justify-content-center me-3 fw-bold" style="background: linear-gradient(135deg, #10b981, #059669); box-shadow: 0 3px 8px rgba(16,185,129,0.2);">
                                                    <?= htmlspecialchars(substr($student['nama'], 0, 1)) ?>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="fw-bold text-dark text-truncate" style="max-width: 180px;"><?= htmlspecialchars($student['nama']) ?></div>
                                                    <div class="text-muted" style="font-size: 11px;">
                                                        <span class="me-2"><i class="fas fa-home opacity-75"></i> Km. <?= htmlspecialchars($student['kamar']) ?></span>
                                                        <span><i class="fas fa-graduation-cap opacity-75"></i> Kls <?= htmlspecialchars($student['kelas']) ?></span>
                                                    </div>
                                                    <div class="mini-stats">
                                                        <div class="mini-stat text-success"><i class="fas fa-plus-circle"></i> <span style="font-size: 12px;"> <?= (int)$student['total_reward'] ?></span></div>
                                                        <div class="mini-stat text-primary"><i class="fas fa-star"></i> <span style="font-size: 12px;"> <?= $str_rapot ?></span></div>
                                                    </div>
                                                </div>
                                                <div class="rank-badge-sm <?= $rank_class ?>"><?= $no ?></div>
                                            </div>
                                        <?php $no++; endforeach; ?>
                                    <?php else: ?>

                                        <div class="text-center text-muted py-4"><i class="fas fa-info-circle fs-2 mb-2"></i><p>Belum ada santri tanpa pelanggaran</p></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="tab-pane fade" id="histori-panel" role="tabpanel">
                                <div class="text-end mb-3">
                                    <span class="text-muted small"><i class="fas fa-info-circle me-1"></i> Data akumulasi seumur hidup</span>
                                </div>
                                <div class="student-list d-flex flex-column gap-3">
                                    <?php if(count($top_histori) > 0): ?>
                                        <?php foreach($top_histori as $histori): ?>
                                            <div class="student-item d-flex align-items-center p-3 border rounded bg-white shadow-sm">
                                                <div class="student-avatar rounded-circle text-white d-flex align-items-center justify-content-center me-3 fw-bold" style="background: linear-gradient(135deg, #64748b, #334155); box-shadow: 0 3px 8px rgba(100,116,139,0.2);">
                                                    <?= htmlspecialchars(substr($histori['nama'], 0, 1)) ?>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="fw-bold text-dark text-truncate" style="max-width: 180px;"><?= htmlspecialchars($histori['nama']) ?></div>
                                                    <div class="text-muted" style="font-size: 11px;">
                                                        <span class="me-2"><i class="fas fa-home opacity-75"></i> Km. <?= htmlspecialchars($histori['kamar']) ?></span>
                                                    </div>
                                                </div>
                                                <div class="badge text-white rounded-pill px-3 py-2 fw-bold" style="background-color: #475569; font-size: 14px;" data-bs-toggle="tooltip" title="Poin Histori (All-Time)">
                                                    <?= (int)$histori['poin_aktif'] ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="text-center text-muted py-4"><i class="fas fa-info-circle fs-2 mb-2"></i><p>Belum ada histori poin</p></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    

