-- ====================================================================
-- SCRIPT UPDATE DATABASE: REFACTOR SISTEM IZIN AKSES (RBAC + SMART OVERRIDES)
-- Versi: v2.0 - AsuhTrack
-- Tanggal: 2026-08-26
-- ====================================================================

-- 1. Tambahkan kolom `is_allowed` pada tabel `user_permissions` jika belum ada
-- Keterangan: 1 = Allow (Izin Tambahan), 0 = Deny (Izin Dicabut Khusus)
ALTER TABLE `user_permissions` 
ADD COLUMN IF NOT EXISTS `is_allowed` TINYINT(1) NOT NULL DEFAULT 1 
COMMENT '1 = Allow (Izin Tambahan), 0 = Deny (Izin Dicabut Khusus)' 
AFTER `permission_id`;

-- 2. Tambahkan Index untuk mempercepat query izin gabungan
ALTER TABLE `user_permissions`
ADD INDEX IF NOT EXISTS `idx_user_allowed` (`user_id`, `is_allowed`);

-- 3. SMART CLEANUP MIGRATION:
-- Hapus semua baris data di `user_permissions` yang izinnya SAMA PERSIS dengan bawaan rolenya.
-- (Menghapus ratusan data duplikasi lama dan menyisakan HANYA izin yang berbeda/spesial).
DELETE up 
FROM `user_permissions` up
JOIN `users` u ON up.user_id = u.id
JOIN `role_permissions` rp ON rp.role = u.role AND rp.permission_id = up.permission_id;

-- 4. Verifikasi hasil migrasi
-- Menampilkan sisa override khusus yang benar-benar dimiliki user
SELECT 
    u.username, 
    u.role, 
    p.nama_izin, 
    IF(up.is_allowed = 1, 'IZIN TAMBAHAN (ALLOW)', 'DICABUT KHUSUS (DENY)') AS status_override
FROM `user_permissions` up
JOIN `users` u ON up.user_id = u.id
JOIN `permissions` p ON up.permission_id = p.id
ORDER BY u.username ASC, p.nama_izin ASC;
