<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\SantriModel;

class SantriController extends Controller {
    private SantriModel $santriModel;

    public function __construct() {
        if (!isset($_SESSION['user_id'])) {
            $this->redirect('/login');
        }
        $this->santriModel = new SantriModel();
    }

    public function index(): void {
        // Guard middleware logic should ideally be here, checking permissions
        if (!\App\Helpers\AuthHelper::hasPermission('santri_view')) {
            $this->respond('errors/403');
            return;
        }

        if (isset($_GET['reset'])) {
            unset($_SESSION['filter_santri']);
            $this->redirect('/santri');
            return;
        }

        if (isset($_GET['nama']) || isset($_GET['kelas']) || isset($_GET['kamar'])) {
            $_SESSION['filter_santri'] = [
                'nama' => $_GET['nama'] ?? '',
                'kelas' => $_GET['kelas'] ?? '',
                'kamar' => $_GET['kamar'] ?? '',
            ];
        }

        $filters = $_SESSION['filter_santri'] ?? ['nama' => '', 'kelas' => '', 'kamar' => ''];

        $santri = $this->santriModel->getFiltered($filters);
        $total = $this->santriModel->countFiltered($filters);

        $data = [
            'santri' => $santri,
            'total' => $total,
            'filters' => $filters
        ];

        // If it's an HTMX request for the table only (from search form)
        if ($this->isHtmxRequest()) {
            $this->partial('pages/santri/_table', $data);
            return;
        }

        // Full page load
        $this->respond('pages/santri/index', $data);
    }

    public function create(): void {
        if (!\App\Helpers\AuthHelper::hasPermission('santri_create')) {
            $this->respond('errors/403');
            return;
        }
        $this->respond('pages/santri/form', ['santri' => []]);
    }

    public function store(): void {
        if (!\App\Helpers\AuthHelper::hasPermission('santri_create')) {
            $this->respond('errors/403');
            return;
        }

        $this->validateCsrfToken();

        try {
            $nama = trim($_POST['nama'] ?? '');
            $kelas = (int)trim($_POST['kelas'] ?? '0');
            $kamar = (int)trim($_POST['kamar'] ?? '0');

            if (empty($nama) || empty($kelas) || empty($kamar)) {
                throw new \Exception('Semua field harus diisi!');
            }

            $new_id = $this->santriModel->insert([
                'nama' => $nama,
                'kelas' => $kelas,
                'kamar' => $kamar
            ]);

            if ($new_id) {
                write_activity_log('CREATE', 'santri', 'Menambahkan santri baru: ' . $nama, [
                    'id' => $new_id,
                    'nama' => $nama,
                    'kelas' => $kelas,
                    'kamar' => $kamar
                ]);
                $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Santri ' . $nama . ' berhasil ditambahkan!'];
            } else {
                throw new \Exception('Gagal menambahkan santri');
            }
        } catch (\Exception $e) {
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => $e->getMessage()];
        }

        $this->redirect('/santri');
    }

    public function edit(int $id): void {
        if (!\App\Helpers\AuthHelper::hasPermission('santri_edit')) {
            $this->respond('errors/403');
            return;
        }

        $santri = $this->santriModel->findById($id);
        if (!$santri) {
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Santri tidak ditemukan!'];
            $this->redirect('/santri');
            return;
        }

        $this->respond('pages/santri/form', ['santri' => $santri]);
    }

    public function update(int $id): void {
        if (!\App\Helpers\AuthHelper::hasPermission('santri_edit')) {
            $this->respond('errors/403');
            return;
        }

        $this->validateCsrfToken();

        try {
            $nama = trim($_POST['nama'] ?? '');
            $kelas = (int)trim($_POST['kelas'] ?? '0');
            $kamar = (int)trim($_POST['kamar'] ?? '0');

            if (empty($nama) || empty($kelas) || empty($kamar)) {
                throw new \Exception('Semua field harus diisi!');
            }

            $old_data = $this->santriModel->findById($id);
            if (!$old_data) {
                throw new \Exception('Santri tidak ditemukan!');
            }

            if ($this->santriModel->update($id, [
                'nama' => $nama,
                'kelas' => $kelas,
                'kamar' => $kamar
            ])) {
                write_activity_log('UPDATE', 'santri', 'Mengubah data santri: ' . $nama, [
                    'id' => $id,
                    'old' => $old_data,
                    'new' => ['nama' => $nama, 'kelas' => $kelas, 'kamar' => $kamar]
                ]);
                $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Data santri ' . $nama . ' berhasil diperbarui!'];
            } else {
                throw new \Exception('Gagal memperbarui data santri');
            }
            $this->redirect('/santri');
            return;
        } catch (\Exception $e) {
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => $e->getMessage()];
            $this->redirect('/santri/' . $id . '/edit');
        }
    }

    public function destroy(int $id): void {
        if (!\App\Helpers\AuthHelper::hasPermission('santri_delete')) {
            $this->respond('errors/403');
            return;
        }

        $this->validateCsrfToken();

        $old_data = $this->santriModel->findById($id);
        if ($old_data) {
            if ($this->santriModel->delete($id)) {
                write_activity_log('DELETE', 'santri', 'Menghapus data santri: ' . $old_data['nama'], [
                    'id' => $id,
                    'nama' => $old_data['nama']
                ]);
                $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Data santri berhasil dihapus!'];
            } else {
                $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Gagal menghapus santri!'];
            }
        }
        $this->redirect('/santri');
    }

    public function bulkCreate(): void {
        if (!\App\Helpers\AuthHelper::hasPermission('santri_create')) {
            $this->respond('errors/403');
            return;
        }
        $this->respond('pages/santri/bulk_create', []);
    }

    public function bulkStore(): void {
        if (!\App\Helpers\AuthHelper::hasPermission('santri_create')) {
            $this->respond('errors/403');
            return;
        }

        $this->validateCsrfToken();

        $santri_list = explode("\n", $_POST['list_santri'] ?? '');
        $success_count = 0;
        $error_count = 0;
        $errors = [];

        foreach ($santri_list as $index => $line) {
            $line_number = $index + 1;
            if (empty(trim($line))) continue;

            $data = array_map('trim', explode(',', $line));
            if (count($data) === 3) {
                list($nama, $kelas_raw, $kamar_raw) = $data;
                
                if (empty($nama) || empty($kelas_raw) || empty($kamar_raw)) {
                    $errors[] = "Baris $line_number: Data tidak lengkap";
                    $error_count++;
                    continue;
                }

                $kelas = (int)$kelas_raw;
                $kamar = (int)$kamar_raw;

                if ($this->santriModel->insert(['nama' => $nama, 'kelas' => $kelas, 'kamar' => $kamar])) {
                    $success_count++;
                } else {
                    $errors[] = "Baris $line_number ($nama): Gagal menyimpan";
                    $error_count++;
                }
            } else {
                $errors[] = "Baris $line_number: Format tidak valid";
                $error_count++;
            }
        }

        $_SESSION['bulk_upload_result'] = [
            'success' => $success_count,
            'error'   => $error_count,
            'errors'  => $errors
        ];

        if ($success_count > 0) {
            write_activity_log('CREATE', 'santri', "Bulk import $success_count santri baru sekaligus (error: $error_count)");
        }

        $this->redirect('/santri');
    }

    public function bulkEdit(): void {
        if (!\App\Helpers\AuthHelper::hasPermission('santri_edit')) {
            $this->respond('errors/403');
            return;
        }

        // Jika ada ids dari POST (dipilih dari index), tampilkan hanya yang dipilih
        $ids = $_POST['ids'] ?? [];
        $ids = array_filter(array_map('intval', (array)$ids));

        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $db = \App\Core\Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT * FROM santri WHERE id IN ($placeholders) ORDER BY CAST(kamar AS UNSIGNED) ASC, nama ASC");
            $stmt->execute(array_values($ids));
            $santri = $stmt->fetchAll();
        } else {
            // GET tanpa ids: tampilkan semua santri (user memilih di halaman bulk edit)
            $santri = $this->santriModel->getFiltered([]);
        }

        $this->respond('pages/santri/bulk_edit', ['santri' => $santri, 'selected_ids' => $ids]);
    }

    public function bulkUpdate(): void {
        if (!\App\Helpers\AuthHelper::hasPermission('santri_edit')) {
            $this->respond('errors/403');
            return;
        }

        $this->validateCsrfToken();

        $ids = $_POST['santri_ids'] ?? [];
        $kelasBaru_input = trim($_POST['kelas_baru'] ?? '');
        $kamarBaru_input = trim($_POST['kamar_baru'] ?? '');

        if (!empty($ids) && (!empty($kelasBaru_input) || !empty($kamarBaru_input))) {
            $updated_count = 0;
            $dataToUpdate = [];
            if (!empty($kelasBaru_input)) $dataToUpdate['kelas'] = (int)$kelasBaru_input;
            if (!empty($kamarBaru_input)) $dataToUpdate['kamar'] = (int)$kamarBaru_input;

            foreach ($ids as $id) {
                $old_data = $this->santriModel->findById((int)$id);
                if ($old_data) {
                    $newData = array_merge($old_data, $dataToUpdate);
                    if ($this->santriModel->update((int)$id, $newData)) {
                        $updated_count++;
                    }
                }
            }

            if ($updated_count > 0) {
                $perubahan = [];
                if (!empty($kelasBaru_input)) $perubahan[] = 'Kelas → ' . $kelasBaru_input;
                if (!empty($kamarBaru_input)) $perubahan[] = 'Kamar → ' . $kamarBaru_input;
                write_activity_log('UPDATE', 'santri', "Bulk edit $updated_count santri (" . implode(', ', $perubahan) . ")", [
                    'jumlah_santri' => $updated_count,
                    'perubahan'     => $perubahan
                ]);
                $_SESSION['flash_message'] = ['type' => 'success', 'message' => "Berhasil update data untuk $updated_count santri!"];
            } else {
                $_SESSION['flash_message'] = ['type' => 'error', 'message' => "Terjadi kesalahan, tidak ada data santri yang diperbarui."];
            }
        } else {
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => "Pilih santri dan isi minimal salah satu kolom (kelas atau kamar)!"];
        }

        $this->redirect('/santri/bulk-edit');
    }

    public function bulkDestroy(): void {
        if (!\App\Helpers\AuthHelper::hasPermission('santri_delete')) {
            $this->respond('errors/403');
            return;
        }

        $this->validateCsrfToken();

        $ids = $_POST['ids'] ?? [];
        $deleted_count = 0;

        foreach ($ids as $id) {
            $old_data = $this->santriModel->findById((int)$id);
            if ($old_data && $this->santriModel->delete((int)$id)) {
                $deleted_count++;
            }
        }

        if ($deleted_count > 0) {
            write_activity_log('DELETE', 'santri', "Bulk delete $deleted_count santri");
            $_SESSION['flash_message'] = ['type' => 'success', 'message' => "Berhasil menghapus $deleted_count santri!"];
        } else {
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => "Gagal menghapus santri atau tidak ada santri yang dipilih."];
        }

        $this->redirect('/santri');
    }
}

