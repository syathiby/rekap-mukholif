<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../auth.php';

// Hanya user dengan tiket 'user_manage' yang boleh eksekusi file ini
guard('user_manage');

// Pastikan request datang dari form dengan metode POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Jika diakses langsung, tendang ke halaman sebelumnya atau dashboard
    header("Location: /index.php");
    exit;
}

// 1. Ambil & bersihkan data dari form
// trim() untuk hapus spasi di awal/akhir
$nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
$username     = trim($_POST['username'] ?? '');
$password     = $_POST['password'] ?? '';
// strtolower() untuk membuat semua role jadi huruf kecil, biar konsisten (misal: Admin, admin, ADMIN jadi 'admin')
$role         = trim(strtolower($_POST['role'] ?? '')); 

// 2. Validasi data: Pastikan semua field penting diisi
if (empty($nama_lengkap) || empty($username) || empty($password) || empty($role)) {
    $_SESSION['error_message'] = "❌ Semua field wajib diisi.";
    header("Location: create.php");
    exit;
}

// 3. Cek apakah username sudah ada yang pakai (PENTING!)
// Menggunakan prepared statement untuk keamanan
$stmt_check = $conn->prepare("SELECT id FROM users WHERE username = ?");
$stmt_check->bind_param("s", $username);
$stmt_check->execute();
$stmt_check->store_result();

if ($stmt_check->num_rows > 0) {
    // Jika username sudah ada, kasih pesan error
    $_SESSION['error_message'] = "❌ Username '".htmlspecialchars($username)."' sudah digunakan. Silakan coba yang lain.";
    $stmt_check->close();
    header("Location: create.php");
    exit;
}
$stmt_check->close();

// 4. Hash password dengan metode modern dan aman (WAJIB!)
// password_hash() otomatis membuat salt yang kuat
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// 5. Simpan ke database
// Semua data sudah bersih dan aman, siap untuk dimasukkan
$stmt_insert = $conn->prepare("INSERT INTO users (nama_lengkap, username, password, role) VALUES (?, ?, ?, ?)");
$stmt_insert->bind_param("ssss", $nama_lengkap, $username, $hashedPassword, $role); 

if($stmt_insert->execute()){
    // Jika berhasil
    $_SESSION['success_message'] = "✅ User '".htmlspecialchars($username)."' dengan role '".htmlspecialchars($role)."' berhasil dibuat!";
} else {
    // Jika gagal
    $_SESSION['error_message'] = "❌ Terjadi kesalahan saat menyimpan data ke database.";
    // Untuk debugging (bisa dihapus saat sudah production): error_log($conn->error);
}
$stmt_insert->close();
$conn->close();

// 6. Kembalikan ke halaman form
header("Location: create.php");
exit;
?>