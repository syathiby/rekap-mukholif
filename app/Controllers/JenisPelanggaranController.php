<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\JenisPelanggaranModel;
use App\Helpers\AuthHelper;

class JenisPelanggaranController extends Controller {
    private JenisPelanggaranModel $jpModel;

    public function __construct() {
        if (!isset($_SESSION['user_id'])) {
            $this->redirect('/login');
        }
        $this->jpModel = new JenisPelanggaranModel();
    }

    public function index(): void {
        AuthHelper::requirePermission('jenis_pelanggaran_view');

        $bagianFilter = $_GET['bagian'] ?? '';
        $kategoriFilter = $_GET['kategori'] ?? '';
        $search = $_GET['q'] ?? '';

        // Query params builder
        $where = [];
        $params = [];

        if (!empty($bagianFilter)) {
            $where[] = "LOWER(bagian) = LOWER(?)";
            $params[] = $bagianFilter;
        }

        if (!empty($kategoriFilter)) {
            $where[] = "LOWER(kategori) = LOWER(?)";
            $params[] = $kategoriFilter;
        }

        if (!empty($search)) {
            $where[] = "(nama_pelanggaran LIKE ? OR kategori LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        $query = "SELECT * FROM jenis_pelanggaran";
        if (!empty($where)) {
            $query .= " WHERE " . implode(' AND ', $where);
        }
        $query .= " ORDER BY bagian ASC, nama_pelanggaran ASC";

        // Execute query
        $db = \App\Core\Database::getInstance()->getConnection();
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $jenisPelanggaran = $stmt->fetchAll();

        $daftarBagian = $this->jpModel->getDaftarBagian();

        $data = [
            'jenis_pelanggaran' => $jenisPelanggaran,
            'daftar_bagian' => $daftarBagian,
            'bagian_filter' => $bagianFilter,
            'kategori_filter' => $kategoriFilter,
            'search' => $search,
            'total_data' => count($jenisPelanggaran)
        ];

        $this->respond('pages/jenis-pelanggaran/index', $data);
    }

    public function create(): void {
        AuthHelper::requirePermission('jenis_pelanggaran_create');
        
        $data = [
            'title' => 'Tambah Jenis Pelanggaran',
            'action' => '/jenis-pelanggaran/store',
            'jp' => null,
            'daftar_bagian' => $this->jpModel->getDaftarBagian()
        ];
        
        $this->respond('pages/jenis-pelanggaran/form', $data);
    }

    public function store(): void {
        AuthHelper::requirePermission('jenis_pelanggaran_create');

        $nama_pelanggaran = $_POST['nama_pelanggaran'] ?? '';
        $poin = (int)($_POST['poin'] ?? 0);
        $kategori = $_POST['kategori'] ?? 'Ringan';
        $bagian = $_POST['bagian'] ?? 'Kesantrian';

        if (empty($nama_pelanggaran)) {
            $_SESSION['flash_error'] = 'Nama Pelanggaran wajib diisi.';
            $this->redirect('/jenis-pelanggaran/create');
            return;
        }

        $this->jpModel->create([
            'nama_pelanggaran' => $nama_pelanggaran,
            'poin' => $poin,
            'kategori' => $kategori,
            'bagian' => $bagian
        ]);

        $_SESSION['flash_success'] = 'Jenis Pelanggaran berhasil ditambahkan.';
        $this->redirect('/jenis-pelanggaran');
    }

    public function edit(int $id): void {
        AuthHelper::requirePermission('jenis_pelanggaran_edit');

        $jp = $this->jpModel->findById($id);
        if (!$jp) {
            $_SESSION['flash_error'] = 'Data tidak ditemukan.';
            $this->redirect('/jenis-pelanggaran');
            return;
        }

        $data = [
            'title' => 'Edit Jenis Pelanggaran',
            'action' => "/jenis-pelanggaran/update/$id",
            'jp' => $jp,
            'daftar_bagian' => $this->jpModel->getDaftarBagian()
        ];

        $this->respond('pages/jenis-pelanggaran/form', $data);
    }

    public function update(int $id): void {
        AuthHelper::requirePermission('jenis_pelanggaran_edit');

        $nama_pelanggaran = $_POST['nama_pelanggaran'] ?? '';
        $poin = (int)($_POST['poin'] ?? 0);
        $kategori = $_POST['kategori'] ?? 'Ringan';
        $bagian = $_POST['bagian'] ?? 'Kesantrian';

        if (empty($nama_pelanggaran)) {
            $_SESSION['flash_error'] = 'Nama Pelanggaran wajib diisi.';
            $this->redirect("/jenis-pelanggaran/edit/$id");
            return;
        }

        $this->jpModel->update($id, [
            'nama_pelanggaran' => $nama_pelanggaran,
            'poin' => $poin,
            'kategori' => $kategori,
            'bagian' => $bagian
        ]);

        $_SESSION['flash_success'] = 'Jenis Pelanggaran berhasil diperbarui.';
        $this->redirect('/jenis-pelanggaran');
    }

    public function delete(int $id): void {
        AuthHelper::requirePermission('jenis_pelanggaran_delete');

        try {
            $this->jpModel->delete($id);
            $_SESSION['flash_success'] = 'Jenis Pelanggaran berhasil dihapus.';
        } catch (\Exception $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }

        $this->redirect('/jenis-pelanggaran');
    }

    // --- BULK OPERATIONS ---
    
    public function bulkEdit(): void {
        AuthHelper::requirePermission('jenis_pelanggaran_edit');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['ids'])) {
            $_SESSION['flash_error'] = 'Tidak ada data yang dipilih.';
            $this->redirect('/jenis-pelanggaran');
            return;
        }

        $ids = is_array($_POST['ids']) ? $_POST['ids'] : explode(',', $_POST['ids']);
        $ids = array_map('intval', $ids);
        
        if (empty($ids)) {
            $this->redirect('/jenis-pelanggaran');
            return;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $db = \App\Core\Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM jenis_pelanggaran WHERE id IN ($placeholders) ORDER BY bagian ASC, nama_pelanggaran ASC");
        $stmt->execute($ids);
        $selectedItems = $stmt->fetchAll();

        $data = [
            'selected_items' => $selectedItems,
            'ids_string' => implode(',', $ids),
            'daftar_bagian' => $this->jpModel->getDaftarBagian()
        ];

        $this->respond('pages/jenis-pelanggaran/bulk_edit', $data);
    }

    public function bulkUpdate(): void {
        AuthHelper::requirePermission('jenis_pelanggaran_edit');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['ids'])) {
            $this->redirect('/jenis-pelanggaran');
            return;
        }

        $ids = is_array($_POST['ids']) ? $_POST['ids'] : explode(',', $_POST['ids']);
        $points = $_POST['poin'] ?? [];
        $categories = $_POST['kategori'] ?? [];
        $bagianArr = $_POST['bagian'] ?? [];

        $db = \App\Core\Database::getInstance()->getConnection();
        $db->beginTransaction();

        try {
            foreach ($ids as $id) {
                $id = (int)$id;
                if ($id <= 0) continue;

                $updateData = [];
                if (isset($points[$id]) && $points[$id] !== '') {
                    $updateData['poin'] = (int)$points[$id];
                }
                if (!empty($categories[$id])) {
                    $updateData['kategori'] = $categories[$id];
                }
                if (!empty($bagianArr[$id])) {
                    $updateData['bagian'] = $bagianArr[$id];
                }

                if (!empty($updateData)) {
                    $this->jpModel->update($id, $updateData);
                }
            }
            $db->commit();
            $_SESSION['flash_success'] = 'Data jenis pelanggaran berhasil diperbarui secara massal.';
        } catch (\Exception $e) {
            $db->rollBack();
            $_SESSION['flash_error'] = 'Terjadi kesalahan saat update massal: ' . $e->getMessage();
        }

        $this->redirect('/jenis-pelanggaran');
    }

    public function bulkDelete(): void {
        AuthHelper::requirePermission('jenis_pelanggaran_delete');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['ids'])) {
            $this->redirect('/jenis-pelanggaran');
            return;
        }

        $ids = is_array($_POST['ids']) ? $_POST['ids'] : explode(',', $_POST['ids']);
        $ids = array_map('intval', $ids);
        
        $db = \App\Core\Database::getInstance()->getConnection();
        $db->beginTransaction();

        try {
            $deletedCount = 0;
            $skippedCount = 0;
            
            foreach ($ids as $id) {
                if ($id <= 0) continue;
                
                // Cek apakah dipakai
                if (!$this->jpModel->isUsed($id)) {
                    $this->jpModel->delete($id);
                    $deletedCount++;
                } else {
                    $skippedCount++;
                }
            }
            $db->commit();
            
            if ($skippedCount > 0) {
                $_SESSION['flash_warning'] = "$deletedCount dihapus. $skippedCount aturan dilewati karena sudah pernah dipakai santri.";
            } else {
                $_SESSION['flash_success'] = "$deletedCount aturan berhasil dihapus secara massal.";
            }
        } catch (\Exception $e) {
            $db->rollBack();
            $_SESSION['flash_error'] = 'Terjadi kesalahan: ' . $e->getMessage();
        }

        $this->redirect('/jenis-pelanggaran');
    }
}
