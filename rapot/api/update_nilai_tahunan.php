<?php
// rapot/api/update_nilai_tahunan.php
require_once __DIR__ . '/../../bootstrap/init.php';
require_once __DIR__ . '/../config/helper.php';
require_once __DIR__ . '/generate_catatan.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

if (!has_permission('rapot_create')) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

$rapor_id         = (int)($_POST['rapor_id'] ?? 0);
$idx_aspek        = $_POST['idx_aspek'] ?? '';
$idx_sub          = $_POST['idx_sub'] ?? '';
$new_score        = (float)($_POST['new_score'] ?? 0);
$regenerate_notes = ($_POST['regenerate_notes'] ?? 'false') === 'true';

if (!$rapor_id || $idx_aspek === '' || $idx_sub === '' || $new_score <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Parameter tidak lengkap.']);
    exit;
}

$idx_aspek = (int)$idx_aspek;
$idx_sub   = (int)$idx_sub;

try {
    // Ambil data rapor
    $stmt = $conn->prepare("
        SELECT rt.*, s.nama AS nama_santri, s.kelas 
        FROM rapot_tahunan rt
        JOIN santri s ON rt.santri_id = s.id
        WHERE rt.id = ? AND rt.status = 'DRAFT'
    ");
    $stmt->bind_param('i', $rapor_id);
    $stmt->execute();
    $rapot = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$rapot) {
        echo json_encode(['status' => 'error', 'message' => 'Rapor tidak ditemukan atau sudah APPROVED.']);
        exit;
    }

    $nilai_snapshot = json_decode($rapot['nilai_snapshot'] ?? '[]', true);
    if (!isset($nilai_snapshot[$idx_aspek]['sub_mutu'][$idx_sub])) {
        echo json_encode(['status' => 'error', 'message' => 'Indeks aspek atau sub-mutu tidak valid.']);
        exit;
    }

    // Update nilai
    $nilai_snapshot[$idx_aspek]['sub_mutu'][$idx_sub]['nilai_final'] = $new_score;
    // Tambahkan flag bahwa nilai ini sudah diedit manual
    $nilai_snapshot[$idx_aspek]['sub_mutu'][$idx_sub]['diubah_manual'] = true;
    
    // Ambil penjelasan baru berdasarkan nilai yg dibulatkan
    $rounded_score = (int)round($new_score);
    $sub_mutu_data = $nilai_snapshot[$idx_aspek]['sub_mutu'][$idx_sub];
    $field_key = $sub_mutu_data['field'] ?? strtolower(str_replace([' ', '&', '/'], ['_', '', ''], $sub_mutu_data['nama']));
    $penjelasan_baru = getDeskripsiPenilaian($field_key, $rounded_score);

    $catatan_global = $rapot['narasi_ai'] ?? '';

    // Hitung poin untuk regen notes
    if ($regenerate_notes && has_permission('catatan_otomatis')) {
        $tahun_awal = (int)explode('/', $rapot['periode'])[0];
        $t2 = $tahun_awal + 1;
        $santri_id = $rapot['santri_id'];

        $stmt_pel = $conn->prepare("SELECT SUM(jp.poin) as total FROM pelanggaran p JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id WHERE p.santri_id = ? AND (YEAR(p.tanggal) = ? OR YEAR(p.tanggal) = ?)");
        $stmt_pel->bind_param('iii', $santri_id, $tahun_awal, $t2);
        $stmt_pel->execute();
        $total_pel = (int)$stmt_pel->get_result()->fetch_assoc()['total'];
        $stmt_pel->close();

        $stmt_rew = $conn->prepare("SELECT SUM(jr.poin_reward) as total FROM daftar_reward rwd JOIN jenis_reward jr ON rwd.jenis_reward_id = jr.id WHERE rwd.santri_id = ? AND (YEAR(rwd.tanggal) = ? OR YEAR(rwd.tanggal) = ?)");
        $stmt_rew->bind_param('iii', $santri_id, $tahun_awal, $t2);
        $stmt_rew->execute();
        $total_rew = (int)$stmt_rew->get_result()->fetch_assoc()['total'];
        $stmt_rew->close();

        // Regenerate catatan per aspek
        $nilai_snapshot[$idx_aspek]['catatan'] = generate_catatan_per_aspek($nilai_snapshot[$idx_aspek]);
        
        // Regenerate catatan global
        $catatan_global = generate_catatan_tahunan($nilai_snapshot, $total_pel, $total_rew, (string)($rapot['nama_santri'] ?? ''));
    }

    $new_snapshot_json = json_encode($nilai_snapshot, JSON_UNESCAPED_UNICODE);
    
    $stmt_upd = $conn->prepare("UPDATE rapot_tahunan SET nilai_snapshot = ?, narasi_ai = ? WHERE id = ?");
    $stmt_upd->bind_param('ssi', $new_snapshot_json, $catatan_global, $rapor_id);
    
    if ($stmt_upd->execute()) {
        
        // Hitung ulang total nilai untuk dikembalikan ke FE
        $total_nilai = 0;
        foreach ($nilai_snapshot as $aspek) {
            foreach ($aspek['sub_mutu'] ?? [] as $sub) {
                $total_nilai += (float)($sub['nilai_final'] ?? 0);
            }
        }

        echo json_encode([
            'status' => 'success',
            'message' => 'Nilai berhasil diperbarui.',
            'data' => [
                'new_score' => $new_score,
                'rounded_score' => $rounded_score,
                'penjelasan' => $penjelasan_baru,
                'total_nilai' => $total_nilai,
                'catatan_aspek' => $regenerate_notes ? $nilai_snapshot[$idx_aspek]['catatan'] : null,
                'catatan_global' => $regenerate_notes ? $catatan_global : null
            ]
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan ke database.']);
    }
    $stmt_upd->close();

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
}
