<?php
// FILE: pengaturan/history/delete_permanen.php — Handler Hapus Permanen Log Penghapusan
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../bootstrap/init.php';
guard('history_manage');
csrf_validate();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Ambil data filter buat redirect balik
    $start_date        = $_POST['start_date']        ?? date('Y-m-d');
    $end_date          = $_POST['end_date']          ?? date('Y-m-d');
    $bagian            = $_POST['bagian']            ?? '';
    $search            = $_POST['search']            ?? '';
    $kamar             = $_POST['kamar']             ?? '';
    $kelas             = $_POST['kelas']             ?? '';
    $jenis_pelanggaran = $_POST['jenis_pelanggaran'] ?? '';
    $page              = intval($_POST['page']        ?? 1);

    $redirect_url = "history_view.php?" . http_build_query([
        'start_date'        => $start_date,
        'end_date'          => $end_date,
        'bagian'            => $bagian,
        'search'            => $search,
        'kamar'             => $kamar,
        'kelas'             => $kelas,
        'jenis_pelanggaran' => $jenis_pelanggaran,
        'page'              => $page,
    ]);

    if ($action === 'bulk_delete') {
        // Hapus log yang usianya lebih dari 30 hari
        mysqli_begin_transaction($conn);
        try {
            $stmt1 = $conn->prepare("DELETE FROM log_history WHERE dihapus_pada < DATE_SUB(NOW(), INTERVAL 30 DAY)");
            $stmt1->execute();
            $deleted1 = $stmt1->affected_rows;

            $stmt2 = $conn->prepare("DELETE FROM log_history_kebersihan WHERE dihapus_pada < DATE_SUB(NOW(), INTERVAL 30 DAY)");
            $stmt2->execute();
            $deleted2 = $stmt2->affected_rows;

            mysqli_commit($conn);
            $total_deleted = $deleted1 + $deleted2;
            
            $_SESSION['flash_message'] = [
                'type' => 'success',
                'message' => "Berhasil membersihkan $total_deleted data riwayat lama (> 30 Hari)."
            ];
            
            // Catat log
            write_activity_log('DELETE', 'log_history', "Membersihkan $total_deleted data log penghapusan yang usianya > 30 hari secara massal.");

        } catch (Exception $e) {
            mysqli_rollback($conn);
            $_SESSION['flash_message'] = [
                'type' => 'error',
                'message' => "Gagal membersihkan data: " . $e->getMessage()
            ];
        }
        
        header("Location: $redirect_url");
        exit;
    } 
    else if ($action === 'delete_single') {
        $id = intval($_POST['id'] ?? 0);
        $tipe = $_POST['tipe'] ?? '';

        if ($id <= 0 || !in_array($tipe, ['individu', 'kebersihan'])) {
            $_SESSION['flash_message'] = [
                'type' => 'error',
                'message' => "Data tidak valid!"
            ];
            header("Location: $redirect_url");
            exit;
        }

        try {
            if ($tipe === 'individu') {
                $stmt = $conn->prepare("DELETE FROM log_history WHERE id = ?");
            } else {
                $stmt = $conn->prepare("DELETE FROM log_history_kebersihan WHERE id = ?");
            }
            
            $stmt->bind_param("i", $id);
            $stmt->execute();
            
            if ($stmt->affected_rows > 0) {
                $_SESSION['flash_message'] = [
                    'type' => 'success',
                    'message' => "Data log penghapusan berhasil dihapus permanen."
                ];
                write_activity_log('DELETE', 'log_history', "Menghapus permanen data log $tipe dengan ID $id.");
            } else {
                $_SESSION['flash_message'] = [
                    'type' => 'error',
                    'message' => "Data tidak ditemukan atau sudah terhapus."
                ];
            }

        } catch (Exception $e) {
            $_SESSION['flash_message'] = [
                'type' => 'error',
                'message' => "Gagal menghapus data: " . $e->getMessage()
            ];
        }
        
        header("Location: $redirect_url");
        exit;
    }
}

// Redirect back if not POST or no valid action
header('Location: history_view.php');
exit;
