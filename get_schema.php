<?php
require 'bootstrap/init.php';
$result = $conn->query("SHOW CREATE TABLE rapot_tahunan");
if ($result) {
    $row = $result->fetch_assoc();
    echo $row['Create Table'] . "\n\n";
} else {
    echo "Table rapot_tahunan not found or error.\n";
}
?>
