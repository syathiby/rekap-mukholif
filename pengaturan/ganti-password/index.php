<?php
include '../../db.php';
include '../../header.php';
?>

<div class="container my-4">
    <div class="card shadow-sm">
        <div class="card-header text-dark" style="background-color: #1257bdbf;">
            <h4 class="mb-0">🔑 Ganti Password</h4>
        </div>
        <div class="card-body">
            <p class="text-muted">Masukkan password lama lalu password baru untuk memperbarui akses akun.</p>

            <form action="process.php" method="post" class="mt-3">
                <div class="mb-3">
                    <label for="old_password" class="form-label fw-bold">Password Lama</label>
                    <input type="password" id="old_password" name="old_password" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label for="new_password" class="form-label fw-bold">Password Baru</label>
                    <input type="password" id="new_password" name="new_password" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label for="confirm_password" class="form-label fw-bold">Konfirmasi Password Baru</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                </div>

                <!-- Checkbox buat lihat password -->
                <div class="form-check mb-3">
                    <input type="checkbox" class="form-check-input" id="showPassword">
                    <label for="showPassword" class="form-check-label">Lihat password</label>
                </div>

                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save me-1"></i> Simpan Password
                </button>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('showPassword').addEventListener('change', function() {
    let fields = ['old_password', 'new_password', 'confirm_password'];
    fields.forEach(id => {
        let input = document.getElementById(id);
        if (this.checked) {
            input.type = 'text';
        } else {
            input.type = 'password';
        }
    });
});
</script>

<?php include '../../footer.php'; ?>