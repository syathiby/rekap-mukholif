<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\PelanggaranModel;
use App\Models\SantriModel;
use App\Helpers\AuthHelper;
use App\Helpers\FormatHelper;

class PelanggaranController extends Controller
{
    private const VALID_BAGIAN = ['bahasa', 'diniyyah', 'kesantrian', 'tahfidz', 'pengabdian'];

    private function validateBagian(string $bagian): void
    {
        if (!in_array(strtolower($bagian), self::VALID_BAGIAN)) {
            $this->abort(404);
        }
    }

    public function index()
    {
        // Require at least one of the view permissions or admin
        // Actually, let's just let them view the dashboard, the links inside will be guarded
        $this->respond('pages/pelanggaran/index', [
            'title' => 'Input Pelanggaran'
        ]);
    }

    public function rekap(string $bagian)
    {
        $this->validateBagian($bagian);
        $bagianLower = strtolower($bagian);
        AuthHelper::requirePermission("rekap_view_{$bagianLower}");

        $awal = $_GET['awal'] ?? date('Y-m-01');
        $akhir = $_GET['akhir'] ?? date('Y-m-t');

        // Instead of group by, let's get raw list or summarized.
        // Wait, the PRD says "tabel rekap per bagian". 
        // We can just fetch all violations for that "bagian" in the date range.
        $pelanggaran = PelanggaranModel::getByBagian($bagian, $awal, $akhir);

        $this->respond('pages/pelanggaran/rekap_tabel', [
            'title' => 'Rekap Pelanggaran ' . ucfirst($bagian),
            'bagian' => ucfirst($bagian),
            'pelanggaran' => $pelanggaran,
            'awal' => $awal,
            'akhir' => $akhir
        ]);
    }

    public function create(string $bagian)
    {
        $this->validateBagian($bagian);
        $bagianLower = strtolower($bagian);
        AuthHelper::requirePermission("pelanggaran_{$bagianLower}_input");

        // Need to get jenis_pelanggaran for this bagian
        $conn = \App\Core\Database::getInstance()->getConnection();
        $stmt = $conn->prepare("SELECT id, nama_pelanggaran, poin, kategori FROM jenis_pelanggaran WHERE LOWER(bagian) = :bagian ORDER BY poin ASC");
        $stmt->execute(['bagian' => $bagianLower]);
        $jenisPelanggaran = $stmt->fetchAll();

        $this->respond('pages/pelanggaran/form_input_' . $bagianLower, [
            'title' => 'Input Pelanggaran ' . ucfirst($bagian),
            'bagian' => ucfirst($bagian),
            'jenisPelanggaran' => $jenisPelanggaran
        ]);
    }

    public function store(string $bagian)
    {
        $this->validateCsrfToken();
        $this->validateBagian($bagian);
        $bagianLower = strtolower($bagian);
        AuthHelper::requirePermission("pelanggaran_{$bagianLower}_input");

        $jenisPelanggaranIdRaw = $_POST['jenis_pelanggaran_id'] ?? '';
        $tanggal = $_POST['tanggal'] ?? date('Y-m-d H:i:s');
        $santriIds = $_POST['santri_ids'] ?? [];
        $userId = $_SESSION['user_id'];

        if (empty($jenisPelanggaranIdRaw) || empty($tanggal) || empty($santriIds) || !is_array($santriIds)) {
            $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'Data tidak lengkap. Pilih jenis pelanggaran, tanggal, dan minimal satu santri.'];
            $this->redirect('/pelanggaran/' . $bagianLower . '/create');
        }

        $isClear = ($jenisPelanggaranIdRaw === 'clear');
        $jenisPelanggaranId = $isClear ? 0 : (int)$jenisPelanggaranIdRaw;

        try {
            if ($bagianLower === 'bahasa') {
                PelanggaranModel::createBahasa($santriIds, $jenisPelanggaranId, $tanggal, $userId, $isClear);
            } else {
                if ($isClear) {
                    throw new \Exception("Opsi bersihkan hanya berlaku untuk pelanggaran bahasa.");
                }
                PelanggaranModel::createBulk($santriIds, $jenisPelanggaranId, $tanggal, $userId);
            }
            $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Pelanggaran berhasil dicatat!'];
        } catch (\Exception $e) {
            $_SESSION['flash_message'] = ['type' => 'danger', 'message' => $e->getMessage()];
        }

        $this->redirect('/pelanggaran/' . $bagianLower . '/create');
    }

    public function detail(string $bagian, int $santriId)
    {
        $this->validateBagian($bagian);
        AuthHelper::requirePermission("rekap_view_" . strtolower($bagian));

        $santri = SantriModel::findById($santriId);
        if (!$santri) {
            $this->abort(404);
        }

        // Get all violations for this student, then filter by bagian in PHP (or just use getBySantri and filter)
        // Better to get specific bagian
        $conn = \App\Core\Database::getInstance()->getConnection();
        $sql = "SELECT p.*, jp.nama_pelanggaran, jp.poin, jp.bagian, jp.kategori
                FROM pelanggaran p
                JOIN jenis_pelanggaran jp ON jp.id = p.jenis_pelanggaran_id
                WHERE p.santri_id = :santri_id AND LOWER(jp.bagian) = :bagian
                ORDER BY p.tanggal DESC, p.id DESC";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            'santri_id' => $santriId,
            'bagian' => $bagianLower
        ]);
        $pelanggaran = $stmt->fetchAll();

        $this->respond('pages/pelanggaran/detail_santri', [
            'title' => 'Riwayat Pelanggaran ' . ucfirst($bagian),
            'bagian' => ucfirst($bagian),
            'santri' => $santri,
            'pelanggaran' => $pelanggaran
        ]);
    }

    public function delete(int $id)
    {
        $this->validateCsrfToken();
        // Permission check requires knowing which 'bagian' it is. We can just check admin or generic edit/delete.
        // For simplicity, let's allow it if they have ANY input permission or just check admin.
        // In legacy, delete is usually guarded by the specific input permission.
        
        $conn = \App\Core\Database::getInstance()->getConnection();
        $stmt = $conn->prepare("SELECT jp.bagian FROM pelanggaran p JOIN jenis_pelanggaran jp ON jp.id = p.jenis_pelanggaran_id WHERE p.id = :id");
        $stmt->execute(['id' => $id]);
        $data = $stmt->fetch();

        if (!$data) {
            $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'Data tidak ditemukan.'];
            $this->redirectBack();
        }

        $bagianLower = strtolower($data['bagian']);
        AuthHelper::requirePermission("pelanggaran_{$bagianLower}_input");

        if (PelanggaranModel::deleteById($id)) {
            $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Pelanggaran berhasil dihapus dan poin dikembalikan.'];
        } else {
            $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'Gagal menghapus pelanggaran.'];
        }
        
        $this->redirectBack();
    }

    public function searchSantri()
    {
        $q = $_GET['q'] ?? '';
        
        $conn = \App\Core\Database::getInstance()->getConnection();
        $sql = "SELECT id, nama, kamar, kelas FROM santri WHERE 1=1";
        $params = [];
        if ($q !== '') {
            $sql .= " AND nama LIKE :q1";
            $params['q1'] = "%$q%";
        }
        $sql .= " ORDER BY nama ASC LIMIT 50";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $santriList = $stmt->fetchAll();

        // Render partial HTML for HTMX
        foreach ($santriList as $s) {
            echo '<label class="list-group-item d-flex align-items-center gap-3 py-3 border-bottom hover-bg-light cursor-pointer">';
            echo '    <input class="form-check-input flex-shrink-0 santri-checkbox fs-5" type="checkbox" name="santri_ids[]" value="' . $s['id'] . '">';
            echo '    <div>';
            echo '        <div class="fw-bold text-dark mb-1">' . htmlspecialchars($s['nama']) . '</div>';
            echo '        <div class="small text-muted">';
            echo '            <span class="me-2"><i class="fas fa-bed me-1"></i>' . htmlspecialchars($s['kamar']) . '</span>';
            echo '            <span><i class="fas fa-graduation-cap me-1"></i>' . htmlspecialchars($s['kelas']) . '</span>';
            echo '        </div>';
            echo '    </div>';
            echo '</label>';
        }
        
        if (empty($santriList)) {
            echo '<div class="p-4 text-center text-muted">';
            echo '  <i class="fas fa-search fa-2x mb-2 text-light"></i>';
            echo '  <p class="mb-0">Tidak ada santri yang cocok.</p>';
            echo '</div>';
        }
        exit;
    }
}
