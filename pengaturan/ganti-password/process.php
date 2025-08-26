<?php
include '../../db.php';
session_start();

// Pastikan user login
if (!isset($_SESSION['user_id'])) {
    die("<script>alert('❌ Anda belum login!');window.location='../../login.php';</script>");
}

$user_id = $_SESSION['user_id'];
$old_password     = trim($_POST['old_password'] ?? '');
$new_password     = trim($_POST['new_password'] ?? '');
$confirm_password = trim($_POST['confirm_password'] ?? '');

// Validasi
if ($new_password === '' || $confirm_password === '') {
    die("<script>alert('❌ Password baru/konfirmasi tidak boleh kosong.');history.back();</script>");
}
if ($new_password !== $confirm_password) {
    die("<script>alert('❌ Password baru dan konfirmasi tidak sama!');history.back();</script>");
}
if (strlen($new_password) < 4) {
    die("<script>alert('❌ Password baru minimal 4 karakter.');history.back();</script>");
}

// Ambil password user dari DB
$stmt = mysqli_prepare($conn, "SELECT password FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $db_password);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

if (!$db_password) {
    die("<script>alert('❌ User tidak ditemukan!');history.back();</script>");
}

// Cek password lama (karena pakai SHA256 di DB)
if (hash('sha256', $old_password) !== $db_password) {
    die("<script>alert('❌ Password lama salah!');history.back();</script>");
}

// Hash password baru dengan SHA256 biar konsisten
$new_hash = hash('sha256', $new_password);

// Update ke DB
$stmt = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ?");
mysqli_stmt_bind_param($stmt, "si", $new_hash, $user_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

echo "<script>alert('✅ Password berhasil diubah!');window.location='index.php';</script>";
exit;