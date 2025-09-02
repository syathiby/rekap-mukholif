<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../auth.php';
guard('user_manage');

// Cek apakah request datang dari form (metode POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Ambil & bersihkan data dari form
    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    $username     = trim($_POST['username'] ?? '');
    $password     = $_POST['password'] ?? '';
    $role         = $_POST['role'] ?? ''; // <-- AMBIL DATA ROLE

    // 2. Validasi data
    // Pastikan semua field diisi, termasuk role
    if (empty($nama_lengkap) || empty($username) || empty($password) || empty($role)) {
        $_SESSION['error_message'] = "❌ Semua field wajib diisi.";
        header("Location: create.php");
        exit;
    }

    // <-- VALIDASI KEAMANAN UNTUK ROLE -->
    // Pastikan role yang dikirim itu valid, jangan sampe ada yang iseng ngirim 'admin'
    $allowed_roles = ['user', 'pengabdian', 'kesantrian', 'bahasa', 'diniyyah'];
    if (!in_array($role, $allowed_roles)) {
        $_SESSION['error_message'] = "❌ Role yang dipilih tidak valid.";
        header("Location: create.php");
        exit;
    }

    // 3. Cek apakah username sudah ada yang pakai
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $_SESSION['error_message'] = "❌ Username '".htmlspecialchars($username)."' sudah digunakan. Coba yang lain.";
        $stmt->close();
        header("Location: create.php");
        exit;
    }
    $stmt->close();

    // 4. Hash password (WAJIB!)
    $hashedPassword = hash('sha256', $password);

    // 5. Simpan ke tabel 'users' dengan role yang sudah dipilih
    $stmt_insert = $conn->prepare("INSERT INTO users (nama_lengkap, username, password, role) VALUES (?, ?, ?, ?)");
    // Tipe data jadi "ssss" (string, string, string, string)
    $stmt_insert->bind_param("ssss", $nama_lengkap, $username, $hashedPassword, $role); 
    
    if($stmt_insert->execute()){
        $_SESSION['success_message'] = "✅ User '".htmlspecialchars($username)."' dengan role '".htmlspecialchars($role)."' berhasil dibuat!";
    } else {
        $_SESSION['error_message'] = "❌ Gagal membuat user: " . $conn->error;
    }
    $stmt_insert->close();

} else {
    // Jika file ini diakses langsung, tendang
    $_SESSION['error_message'] = "❌ Akses tidak sah.";
}

// Setelah selesai, kembalikan ke halaman formulir
header("Location: create.php");
exit;
?>