-- ====================================================================
-- SCRIPT UPDATE DATABASE: FITUR PANEL PENGELOLA & BROADCAST (v1.0)
-- ====================================================================

-- 1. Tambahkan kolom is_active di tabel users untuk fitur Suspend/Aktifkan Musyrif
-- Jika kolom is_active sudah ada sebelumnya, baris ini bisa dilewati atau akan error (aman untuk diabaikan).
ALTER TABLE `users` 
ADD COLUMN `is_active` TINYINT(1) DEFAULT 1 AFTER `password`;

-- 2. Buat tabel baru untuk menyimpan Riwayat Pengumuman Sistem (Broadcast)
CREATE TABLE IF NOT EXISTS `pengumuman_sistem` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `target_user_id` INT NULL DEFAULT NULL,
  `judul` VARCHAR(255) DEFAULT 'Pengumuman Sistem!',
  `pesan` TEXT NOT NULL,
  `status_aktif` TINYINT(1) DEFAULT 1,
  `created_by` INT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `target_user_id` (`target_user_id`),
  CONSTRAINT `pengumuman_sistem_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pengumuman_sistem_ibfk_2` FOREIGN KEY (`target_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
