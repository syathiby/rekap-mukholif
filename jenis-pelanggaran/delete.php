<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
guard('jenis_pelanggaran_manage');
?>

<?php

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    
    $query = "DELETE FROM jenis_pelanggaran WHERE id = $id";
    mysqli_query($conn, $query);
}

header("Location: index.php");
exit;
?>