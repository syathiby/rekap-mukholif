<?php
ob_start(); // Mulai output buffering paling awal, sebelum output apapun

session_start();
include '../db.php';
require_once __DIR__ . '/../header.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && !empty($_POST['ids'])) {
    // Validasi dan sanitasi ID
    $ids = array_filter($_POST['ids'], 'is_numeric');
    $ids = array_map('intval', $ids);
    $ids = implode(',', $ids);

    if (!empty($ids)) {
        // Hitung jumlah santri yang akan dihapus
        $count_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM santri WHERE id IN ($ids)");
        $count_result = mysqli_fetch_assoc($count_query);
        $total_deleted = $count_result['total'];

        // Eksekusi penghapusan
        $delete_result = mysqli_query($conn, "DELETE FROM santri WHERE id IN ($ids)");

        // Set session untuk feedback
        if ($delete_result) {
            $_SESSION['bulk_delete_result'] = [
                'success' => true,
                'count' => $total_deleted,
                'message' => "Berhasil menghapus $total_deleted santri."
            ];
        } else {
            $_SESSION['bulk_delete_result'] = [
                'success' => false,
                'message' => 'Gagal menghapus data santri. Error: ' . mysqli_error($conn)
            ];
        }
    } else {
        $_SESSION['bulk_delete_result'] = [
            'success' => false,
            'message' => 'Tidak ada ID santri yang valid untuk dihapus.'
        ];
    }
} else {
    $_SESSION['bulk_delete_result'] = [
        'success' => false,
        'message' => 'Tidak ada santri yang dipilih untuk dihapus.'
    ];
}

// Redirect setelah proses selesai
header("Location: index.php");
exit;

// Jangan ada kode apapun di bawah sini
