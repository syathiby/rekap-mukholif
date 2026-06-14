<?php
require 'bootstrap/init.php';
$res = mysqli_query($conn, 'SELECT * FROM jenis_pelanggaran WHERE bagian LIKE "%pengabdian%" OR nama_pelanggaran LIKE "%sholat%" OR nama_pelanggaran LIKE "%kbm%"');
while ($row = mysqli_fetch_assoc($res)) {
    print_r($row);
}
?>
