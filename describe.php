<?php
require_once __DIR__ . '/bootstrap/init.php';
$tables = ['log_history', 'pelanggaran_kebersihan'];
$schema = [];
foreach ($tables as $t) {
    $res = $conn->query("DESCRIBE $t");
    $cols = [];
    while ($row = $res->fetch_assoc()) {
        $cols[] = $row;
    }
    $schema[$t] = $cols;
}
echo json_encode($schema, JSON_PRETTY_PRINT);
