<?php
// Protokol Khusus Ruang Mesin
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
// Tentukan tiket sesuai file
// Untuk delete.php & bulk-delete.php:
guard('santri_delete');


$id = $_GET['id'];
mysqli_query($conn, "DELETE FROM santri WHERE id='$id'");
header("Location: index.php");
exit;
?>