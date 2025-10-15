<?php
// Protokol Khusus Ruang Mesin
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../init.php';
guard('santri_delete');


$id = $_GET['id'];
mysqli_query($conn, "DELETE FROM santri WHERE id='$id'");
header("Location: index.php");
exit;
?>