<?php
// arsip/export/generate_pdf_tahunan_arsip.php
// Generate PDF Rapor TAHUNAN dari Arsip

require_once __DIR__ . '/../../bootstrap/init.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../rapot/config/helper.php';

guard('arsip_view');

$arsip_id = (int)($_GET['arsip_id'] ?? 0);
$rapor_id = (int)($_GET['rapor_id'] ?? 0);
$kamar = $_GET['kamar'] ?? '';
$periode = $_GET['periode'] ?? '';

if (!$rapor_id && (!$arsip_id || !$kamar || !$periode)) {
    http_response_code(400);
    die('Error: Parameter tidak lengkap.');
}

$rapot_list = [];

try {
    if ($rapor_id) {
        // Ambil data rapor tahunan
        $stmt = $conn->prepare("
            SELECT rt.*, rt.santri_nama AS nama_santri, rt.kamar, rt.santri_kelas as kelas_santri, rt.approved_by_nama AS nama_musyrif
            FROM arsip_data_rapot_tahunan rt
            WHERE rt.id = ?
        ");
        $stmt->bind_param('i', $rapor_id);
        $stmt->execute();
        $rapot = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$rapot) {
            http_response_code(404);
            die('Error: Data rapor tahunan di arsip tidak ditemukan.');
        }

        if ($rapot['status'] !== 'APPROVED' && $rapot['status'] !== 'EXPORTED') {
            http_response_code(403);
            die('Error: Rapor tahunan harus di-approve terlebih dahulu sebelum bisa di-download.');
        }

        $rapot_list[] = $rapot;
        $periode = $rapot['periode'];
    } else {
        $stmt = $conn->prepare("
            SELECT rt.*, rt.santri_nama AS nama_santri, rt.kamar, rt.santri_kelas as kelas_santri, rt.approved_by_nama AS nama_musyrif
            FROM arsip_data_rapot_tahunan rt
            WHERE rt.arsip_id = ? AND rt.kamar = ? AND rt.periode = ? AND rt.status IN ('APPROVED', 'EXPORTED')
            ORDER BY rt.santri_nama ASC
        ");
        $stmt->bind_param('iss', $arsip_id, $kamar, $periode);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $rapot_list[] = $row;
        }
        $stmt->close();

        if (empty($rapot_list)) {
            http_response_code(404);
            die('Error: Tidak ada rapor tahunan yang di-approve untuk arsip kamar dan periode tersebut.');
        }
    }
} catch (Exception $e) {
    die('Error database: ' . $e->getMessage());
}

// ============================================================
// Generate PDF via mPDF
// ============================================================
try {
    $mpdf = new \Mpdf\Mpdf([
        'mode'          => 'utf-8',
        'format'        => 'A4',
        'margin_left'   => 8,
        'margin_right'  => 8,
        'margin_top'    => 5,
        'margin_bottom' => 3,
    ]);

    $logo_path = __DIR__ . '/../../assets/img/Kop Syathiby.jpg';
    if (!file_exists($logo_path)) $logo_path = ''; 

    foreach ($rapot_list as $index => $rapot) {
        $stmt_pel = $conn->prepare("
            SELECT jenis_pelanggaran_nama as nama_pelanggaran, COUNT(*) AS jumlah, SUM(poin) as total_poin
            FROM arsip_data_pelanggaran
            WHERE arsip_id = ? AND santri_id = ? AND tipe = 'Umum'
            GROUP BY jenis_pelanggaran_nama
            ORDER BY jumlah DESC
        ");
        $stmt_pel->bind_param('ii', $rapot['arsip_id'], $rapot['santri_id']);
        $stmt_pel->execute();
        $pelanggaran_rekap = $stmt_pel->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_pel->close();

        $stmt_rwd = $conn->prepare("
            SELECT nama_reward, COUNT(*) AS jumlah, SUM(poin_reward) as total_poin
            FROM arsip_data_reward
            WHERE arsip_id = ? AND santri_id = ?
            GROUP BY nama_reward
            ORDER BY jumlah DESC
        ");
        $stmt_rwd->bind_param('ii', $rapot['arsip_id'], $rapot['santri_id']);
        $stmt_rwd->execute();
        $reward_rekap = $stmt_rwd->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_rwd->close();

        // Siapkan variabel untuk template
        $santri = [
            'nama'  => $rapot['nama_santri']  ?? 'Santri Dihapus',
            'kamar' => $rapot['kamar'] ?? 'N/A',
            'kelas' => $rapot['kelas_santri'] ?? 'N/A',
        ];
        $periode         = $rapot['periode'];
        $narasi_global   = $rapot['narasi_ai']       ?? ''; 
        $nama_musyrif    = $rapot['nama_musyrif'] ?? 'Musyrif';

        $total_pelanggaran = 0;
        foreach ($pelanggaran_rekap as $p) {
            $total_pelanggaran += (int)$p['total_poin'];
        }

        $total_reward = 0;
        foreach ($reward_rekap as $r) {
            $total_reward += (int)$r['total_poin'];
        }

        $nilai_aspek = json_decode($rapot['nilai_snapshot'] ?? '[]', true) ?? [];
        $total_nilai = 0;
        foreach ($nilai_aspek as $aspek) {
            foreach ($aspek['sub_mutu'] ?? [] as $sub) {
                $total_nilai += (float)($sub['nilai_final'] ?? 0);
            }
        }

        ob_start();
        include __DIR__ . '/../../rapot/config/template_rapot_tahunan.php';
        $html = ob_get_clean();

        if ($index > 0) {
            $mpdf->AddPage();
        }
        $mpdf->WriteHTML($html);
    }

    $output_mode = $_GET['output'] ?? 'download';

    if ($rapor_id) {
        $nama_clean = preg_replace('/[^a-zA-Z0-9 ]/', '', $rapot_list[0]['nama_santri']);
        $nama_file  = "Arsip Rapor Tahunan {$nama_clean} - {$periode}.pdf";
    } else {
        $kamar_clean = preg_replace('/[^a-zA-Z0-9 ]/', '', $kamar);
        $periode_clean = str_replace('/', '_', $periode);
        $nama_file  = "Arsip Rapor Tahunan Kamar {$kamar_clean} - {$periode_clean}.pdf";
    }

    if ($output_mode === 'string') {
        $pdf_content = $mpdf->Output($nama_file, \Mpdf\Output\Destination::STRING_RETURN);
        header('Content-Type: application/pdf');
        echo $pdf_content;
    } else {
        $mpdf->Output($nama_file, \Mpdf\Output\Destination::DOWNLOAD);
    }

} catch (\Mpdf\MpdfException $e) {
    die('Error mPDF: ' . $e->getMessage());
} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}
exit;
