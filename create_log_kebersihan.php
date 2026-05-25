<?php
require_once __DIR__ . '/bootstrap/init.php';

$sql = "
CREATE TABLE IF NOT EXISTS log_history_kebersihan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kamar VARCHAR(50) NOT NULL,
    tanggal_pelanggaran DATETIME NOT NULL,
    catatan TEXT,
    dicatat_oleh INT,
    dihapus_pada TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    dihapus_oleh INT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if ($conn->query($sql) === TRUE) {
    echo "Table log_history_kebersihan created successfully";
} else {
    echo "Error creating table: " . $conn->error;
}
