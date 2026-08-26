-- ====================================================================
-- ASUTHTRACK - FULL DATABASE MIGRATION & RBAC v2.0 UPGRADE
-- Tanggal: 26 Agustus 2026
-- Database: rekap_mukholif
-- Engine: InnoDB | Charset: utf8mb4
-- ====================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+07:00";

-- ────────────────────────────────────────────────────────────────────
-- 1. TABEL: permissions (Master Tiket Hak Akses)
-- ────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama_izin` varchar(100) COLLATE utf8mb4_general_ci NOT NULL COMMENT 'Kode unik teknis izin',
  `deskripsi` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Penjelasan fungsi izin',
  `grup` varchar(50) COLLATE utf8mb4_general_ci DEFAULT 'Lainnya' COMMENT 'Pengelompokan modul/kategori',
  PRIMARY KEY (`id`),
  UNIQUE KEY `nama_izin` (`nama_izin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- SEED DATA PERMISSIONS (47 Master Permissions Terstandarisasi)
INSERT INTO `permissions` (`id`, `nama_izin`, `deskripsi`, `grup`) VALUES
(1, 'pelanggaran_view', 'Melihat daftar riwayat pelanggaran santri', 'Pelanggaran'),
(2, 'pelanggaran_add', 'Mencatat & menambahkan data pelanggaran baru', 'Pelanggaran'),
(3, 'pelanggaran_edit', 'Mengubah/memperbarui data pelanggaran santri', 'Pelanggaran'),
(4, 'pelanggaran_delete', 'Menghapus data catatan pelanggaran santri', 'Pelanggaran'),
(6, 'reward_view', 'Melihat daftar catatan reward/prestasi santri', 'Reward'),
(7, 'reward_add', 'Menambahkan reward/prestasi baru untuk santri', 'Reward'),
(8, 'reward_edit', 'Mengubah data reward santri', 'Reward'),
(9, 'reward_delete', 'Menghapus data reward santri', 'Reward'),
(11, 'santri_view', 'Melihat data santri (nama, kelas, kamar, poin)', 'Santri'),
(13, 'santri_detail', 'Melihat profil lengkap dan rekap performa santri', 'Santri'),
(17, 'santri_manage', 'Menambah, mengubah, dan menghapus data santri', 'Santri'),
(19, 'master_pelanggaran', 'Mengelola master jenis pelanggaran & tarif poin', 'Master Data'),
(20, 'master_reward', 'Mengelola master kategori reward & poin bonus', 'Master Data'),
(21, 'arsip_view', 'Melihat data arsip periode lalu', 'Arsip'),
(22, 'export_view', 'Mengakses halaman export data laporan', 'Export'),
(23, 'export_pelanggaran', 'Mengekspor laporan pelanggaran ke Excel/PDF', 'Export'),
(24, 'export_reward', 'Mengekspor laporan reward santri ke Excel', 'Export'),
(25, 'export_santri', 'Mengekspor data master santri ke Excel', 'Export'),
(26, 'pengaturan_view', 'Mengakses halaman pengaturan aplikasi', 'Pengaturan'),
(27, 'user_manage', 'Menambah, mengedit, & mereset user / musyrif', 'Pengaturan'),
(28, 'periode_manage', 'Mengganti periode aktif & semester kepengasuhan', 'Pengaturan'),
(29, 'reset_poin', 'Melakukan reset poin santri per semester/tahun', 'Pengaturan'),
(30, 'backup_restore', 'Melakukan backup & restore database aplikasi', 'Pengaturan'),
(32, 'izin_manage', 'Mengatur hak akses role & izin khusus pengguna', 'Pengaturan'),
(33, 'bahasa_view', 'Melihat riwayat pencatatan bahasa santri', 'Bahasa'),
(34, 'bahasa_add', 'Mencatat santri yang melanggar bahasa harian', 'Bahasa'),
(35, 'bahasa_manage', 'Mengelola dan mengedit data pencatatan bahasa', 'Bahasa'),
(36, 'arsip_bahasa', 'Melihat arsip data pencatatan bahasa', 'Arsip'),
(37, 'eksekusi_manage', 'Mengelola eksekusi kebersihan kamar', 'Eksekusi'),
(38, 'rapot_view', 'Melihat catatan rapot santri', 'Rapot'),
(39, 'rapot_input', 'Menginput catatan rapot santri', 'Rapot'),
(40, 'rapot_update', 'Mengubah catatan rapot santri', 'Rapot'),
(41, 'rapot_detail', 'Melihat detail penilaian rapot santri', 'Rapot'),
(42, 'rapot_cetak', 'Mencetak dokumen rapot santri ke PDF', 'Rapot'),
(43, 'rapot_tahunan_view', 'Melihat rekap data rapot tahunan', 'Rapot Tahunan'),
(44, 'rapot_tahunan_input', 'Menginput evaluasi rapot tahunan santri', 'Rapot Tahunan'),
(45, 'rapot_tahunan_update', 'Mengubah evaluasi rapot tahunan santri', 'Rapot Tahunan'),
(46, 'rapot_tahunan_detail', 'Melihat detail evaluasi tahunan santri', 'Rapot Tahunan'),
(47, 'rapot_tahunan_cetak', 'Mencetak rapot tahunan santri', 'Rapot Tahunan'),
(51, 'arsip_export', 'Ekspor data arsip ke Excel', 'Arsip'),
(52, 'pengumuman_manage', 'Mengelola dan menyiarkan pengumuman sistem', 'Pengaturan'),
(53, 'log_view', 'Melihat log audit aktivitas sistem', 'Pengaturan'),
(54, 'kebersihan_view', 'Melihat data rekap pelanggaran kebersihan', 'Kebersihan'),
(55, 'kebersihan_input', 'Mencatat data pelanggaran kebersihan kamar', 'Kebersihan')
ON DUPLICATE KEY UPDATE `deskripsi` = VALUES(`deskripsi`), `grup` = VALUES(`grup`);

-- ────────────────────────────────────────────────────────────────────
-- 2. TABEL: roles (Master Jabatan/Peran)
-- ────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `roles` (
  `id` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `role_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- SEED DATA ROLES
INSERT INTO `roles` (`id`, `role_name`) VALUES
('admin', 'Developer'),
('admin_kesantrian', 'Admin Kesantrian'),
('divisi_bahasa', 'Divisi Bahasa'),
('divisi_diniyyah', 'Divisi Diniyyah'),
('divisi_tahfidz', 'Divisi Tahfidz'),
('musyrif', 'Musyrif'),
('pelihat', 'Pelihat'),
('pengelola', 'Pengelola')
ON DUPLICATE KEY UPDATE `role_name` = VALUES(`role_name`);

-- ────────────────────────────────────────────────────────────────────
-- 3. TABEL: role_permissions (Mapping Izin Default Bawaan Role)
-- ────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `role_permissions` (
  `role` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `permission_id` int(11) NOT NULL,
  PRIMARY KEY (`role`,`permission_id`),
  KEY `fk_role_permissions_id` (`permission_id`),
  CONSTRAINT `fk_role_permissions_id` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- SEED DEFAULT ROLE PERMISSIONS
INSERT IGNORE INTO `role_permissions` (`role`, `permission_id`) VALUES
-- Admin Kesantrian
('admin_kesantrian', 1), ('admin_kesantrian', 2), ('admin_kesantrian', 3), ('admin_kesantrian', 4),
('admin_kesantrian', 6), ('admin_kesantrian', 7), ('admin_kesantrian', 8), ('admin_kesantrian', 9),
('admin_kesantrian', 11), ('admin_kesantrian', 13), ('admin_kesantrian', 17), ('admin_kesantrian', 19),
('admin_kesantrian', 20), ('admin_kesantrian', 21), ('admin_kesantrian', 22), ('admin_kesantrian', 23),
('admin_kesantrian', 24), ('admin_kesantrian', 25), ('admin_kesantrian', 26), ('admin_kesantrian', 28),
('admin_kesantrian', 29), ('admin_kesantrian', 30), ('admin_kesantrian', 32), ('admin_kesantrian', 33),
('admin_kesantrian', 38), ('admin_kesantrian', 42), ('admin_kesantrian', 47), ('admin_kesantrian', 52),
('admin_kesantrian', 53),
-- Divisi Bahasa
('divisi_bahasa', 1), ('divisi_bahasa', 2), ('divisi_bahasa', 3), ('divisi_bahasa', 4),
('divisi_bahasa', 11), ('divisi_bahasa', 13), ('divisi_bahasa', 21), ('divisi_bahasa', 22),
('divisi_bahasa', 23), ('divisi_bahasa', 25), ('divisi_bahasa', 33), ('divisi_bahasa', 34),
('divisi_bahasa', 35), ('divisi_bahasa', 36),
-- Divisi Diniyyah
('divisi_diniyyah', 1), ('divisi_diniyyah', 13), ('divisi_diniyyah', 21), ('divisi_diniyyah', 22),
('divisi_diniyyah', 25), ('divisi_diniyyah', 27), ('divisi_diniyyah', 33), ('divisi_diniyyah', 36),
('divisi_diniyyah', 38), ('divisi_diniyyah', 42), ('divisi_diniyyah', 47), ('divisi_diniyyah', 55),
-- Divisi Tahfidz
('divisi_tahfidz', 1), ('divisi_tahfidz', 13), ('divisi_tahfidz', 21), ('divisi_tahfidz', 22),
('divisi_tahfidz', 25), ('divisi_tahfidz', 27), ('divisi_tahfidz', 33), ('divisi_tahfidz', 36),
('divisi_tahfidz', 38), ('divisi_tahfidz', 42), ('divisi_tahfidz', 47), ('divisi_tahfidz', 55),
-- Musyrif
('musyrif', 1), ('musyrif', 9), ('musyrif', 11), ('musyrif', 13), ('musyrif', 22),
('musyrif', 23), ('musyrif', 24), ('musyrif', 25), ('musyrif', 27), ('musyrif', 33),
('musyrif', 36), ('musyrif', 37), ('musyrif', 38), ('musyrif', 39), ('musyrif', 40),
('musyrif', 41), ('musyrif', 42), ('musyrif', 47), ('musyrif', 51), ('musyrif', 54),
('musyrif', 55),
-- Pelihat
('pelihat', 1), ('pelihat', 11), ('pelihat', 13), ('pelihat', 22), ('pelihat', 23),
('pelihat', 24), ('pelihat', 27), ('pelihat', 33), ('pelihat', 38), ('pelihat', 41),
('pelihat', 42), ('pelihat', 47), ('pelihat', 54), ('pelihat', 55),
-- Pengelola
('pengelola', 1), ('pengelola', 2), ('pengelola', 3), ('pengelola', 4), ('pengelola', 6),
('pengelola', 7), ('pengelola', 8), ('pengelola', 9), ('pengelola', 11), ('pengelola', 13),
('pengelola', 17), ('pengelola', 19), ('pengelola', 20), ('pengelola', 21), ('pengelola', 22),
('pengelola', 23), ('pengelola', 24), ('pengelola', 25), ('pengelola', 26), ('pengelola', 27),
('pengelola', 28), ('pengelola', 29), ('pengelola', 30), ('pengelola', 32), ('pengelola', 33),
('pengelola', 35), ('pengelola', 36), ('pengelola', 38), ('pengelola', 39), ('pengelola', 41),
('pengelola', 42), ('pengelola', 43), ('pengelola', 44), ('pengelola', 45), ('pengelola', 46),
('pengelola', 47), ('pengelola', 52), ('pengelola', 53), ('pengelola', 54), ('pengelola', 55);

-- ────────────────────────────────────────────────────────────────────
-- 4. TABEL: user_permissions (Pengecualian Izin Khusus: Allow & Deny)
-- ────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `user_permissions` (
  `user_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  `is_allowed` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 = Allow (Izin Tambahan), 0 = Deny (Izin Dicabut Khusus)',
  PRIMARY KEY (`user_id`,`permission_id`),
  KEY `idx_up_permission_id` (`permission_id`),
  KEY `idx_up_user_id` (`user_id`),
  KEY `idx_user_allowed` (`user_id`,`is_allowed`),
  CONSTRAINT `user_permissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- SMART MIGRATION: Hapus data duplikasi lama yang sama persis dengan role bawaan
DELETE up 
FROM `user_permissions` up
JOIN `users` u ON up.user_id = u.id
JOIN `role_permissions` rp ON rp.role = u.role AND rp.permission_id = up.permission_id
WHERE up.is_allowed = 1;

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;
