<?php
// =================================================================
// PROTOKOL UNTUK "WAHANA HIBRIDA" (Logika + Tampilan)
// =================================================================

// BAGIAN 1: PERSIAPAN & LOGIKA RUANG MESIN
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../auth.php';
guard('pengaturan_ganti_password'); // Tiketnya udah bener!

// Ambil SEMUA user, TERMASUK ADMIN, untuk ditampilkan di dropdown
$users = [];
// ✅ INI DIA BAGIAN YANG DIUBAH: klausa "WHERE role != 'admin'" dihapus
$result = $conn->query("SELECT id, username, nama_lengkap FROM users ORDER BY nama_lengkap ASC");
if ($result) {
    $users = $result->fetch_all(MYSQLI_ASSOC);
}

// Logika proses form HANYA berjalan jika ada POST request
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $user_id_to_change = $_POST['user_id'] ?? null;
        $new_password      = trim($_POST['new_password'] ?? '');
        $confirm_password  = trim($_POST['confirm_password'] ?? '');

        // Validasi Modern
        if (empty($user_id_to_change) || empty($new_password) || empty($confirm_password)) {
            throw new Exception("Semua field wajib diisi.");
        }
        if ($new_password !== $confirm_password) {
            throw new Exception("Password baru dan konfirmasi tidak sama!");
        }
        if (strlen($new_password) < 4) {
            throw new Exception("Password baru minimal 4 karakter.");
        }

        // Hash password baru
        $new_hash = hash('sha256', $new_password);

        // Update ke DB
        $stmt_update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt_update->bind_param("si", $new_hash, $user_id_to_change);
        
        if (!$stmt_update->execute()) {
            throw new Exception("Gagal memperbarui password di database.");
        }
        $stmt_update->close();

        $_SESSION['success_message'] = "✅ Password untuk user berhasil direset!";

    } catch (Exception $e) {
        $_SESSION['error_message'] = "❌ Gagal: " . $e->getMessage();
    }

    // Redirect balik ke halaman ini juga untuk nampilin notif
    header("Location: index.php");
    exit;
}

// BAGIAN 2: PERSIAPAN TAMPILAN WAHANA
require_once __DIR__ . '/../../header.php';
?>

<!-- ======================================================================= -->
<!-- BAGIAN TAMPILAN (HTML) DIMULAI DARI SINI -->
<!-- ======================================================================= -->

<style>
    /* Biar nggak biru mulu, kita kasih sentuhan ungu yang elegan & berkuasa */
    .card-header-purple {
        background: linear-gradient(135deg, #6f42c1, #4a148c);
    }
    .btn-purple {
        background-color: #6f42c1;
        border-color: #6f42c1;
        color: white;
    }
    .btn-purple:hover {
        background-color: #5a379b;
        border-color: #5a379b;
        color: white;
    }
</style>

<div class="container my-4">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card shadow-sm">
                <div class="card-header card-header-purple text-white">
                    <h4 class="mb-0"><i class="fas fa-user-lock me-2"></i>Reset Password User</h4>
                </div>
                <div class="card-body p-4">
                    <p class="text-muted">Pilih user dan masukkan password baru untuk mereset akses akun mereka.</p>

                    <form action="index.php" method="post" class="mt-4">
                        <!-- KOLOM USER BARU! -->
                        <div class="mb-3">
                            <label for="user_id" class="form-label fw-bold">Pilih User</label>
                            <select name="user_id" id="user_id" class="form-select" required>
                                <option value="" disabled selected>-- Pilih Akun yang Akan Direset --</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?= $user['id'] ?>">
                                        <?= htmlspecialchars($user['nama_lengkap']) ?> (<?= htmlspecialchars($user['username']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <hr class="my-4">

                        <div class="mb-3">
                            <label for="new_password" class="form-label fw-bold">Password Baru</label>
                            <input type="password" id="new_password" name="new_password" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label for="confirm_password" class="form-label fw-bold">Konfirmasi Password Baru</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                        </div>

                        <div class="form-check mb-4">
                            <input type="checkbox" class="form-check-input" id="showPassword">
                            <label for="showPassword" class="form-check-label">Lihat password</label>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-purple">
                                <i class="fas fa-save me-2"></i>Reset & Simpan Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('showPassword').addEventListener('change', function() {
    // Cuma 2 field sekarang
    const fields = ['new_password', 'confirm_password'];
    const type = this.checked ? 'text' : 'password';
    fields.forEach(id => {
        const input = document.getElementById(id);
        if (input) {
            input.type = type;
        }
    });
});
</script>

<?php 
// Panggil footer utama dari Markas Komando
require_once __DIR__ . '/../../footer.php'; 
?>