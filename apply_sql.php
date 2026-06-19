<?php
require 'bootstrap/init.php';
$query = "
CREATE TABLE IF NOT EXISTS `arsip_data_rapot_tahunan` (
  `id` int NOT NULL AUTO_INCREMENT,
  `arsip_id` int NOT NULL COMMENT 'FK ke tabel arsip',
  `rapot_tahunan_id` int NOT NULL COMMENT 'ID asli dari rapot tahunan sebelum dihapus',
  `santri_id` int NOT NULL,
  `santri_nama` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `santri_kelas` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `periode` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kamar` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nilai_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `narasi_ai` text COLLATE utf8mb4_unicode_ci,
  `catatan_musyrif` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_fallback` tinyint(1) NOT NULL DEFAULT '0',
  `generated_at` datetime DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `approved_by_nama` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_arsip` (`arsip_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";
if ($conn->query($query)) {
    echo "Table arsip_data_rapot_tahunan created successfully.";
} else {
    echo "Error: " . $conn->error;
}
?>
