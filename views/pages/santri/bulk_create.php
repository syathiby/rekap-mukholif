<?php
$csrf_token = csrf_generate();
?>
<style>
    :root {
        --primary-color: #3498db;
        --secondary-color: #2c3e50;
    }
    .container-bulk-create {
        max-width: 800px;
    }
    .page-title-card {
        background: linear-gradient(135deg, var(--secondary-color), var(--primary-color));
        color: white;
        padding: 1.5rem;
        border-radius: 8px;
        margin-bottom: 2rem;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }
    .form-container {
        background-color: white;
        border-radius: 8px;
        padding: 2rem;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    }
    .example-box {
        background-color: #f8f9fa;
        border-left: 4px solid var(--primary-color);
        padding: 1rem;
        margin-bottom: 1.5rem;
        border-radius: 0 4px 4px 0;
    }
    .btn-primary {
        background-color: var(--primary-color);
        border-color: var(--primary-color);
    }

    textarea#list_santri {
        min-height: 550px; 
        font-family: monospace;
    }

    @media (max-width: 576px) {
        .page-title-card {
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        .page-title-card h2 {
            font-size: 1.25rem;
        }
        .form-container {
            padding: 1.5rem;
        }
        .form-actions .btn {
            padding: .375rem .75rem;
            font-size: 1rem;
        }
        textarea#list_santri {
            min-height: 400px;
        }
    }
</style>

<div class="container container-bulk-create mt-4 mb-5">
    <div class="page-title-card text-center">
        <h2 class="text-white"><i class="fas fa-users-cog me-2 text-white"></i> Bulk Input Santri</h2>
        <p class="mb-0 text-white">Tambahkan banyak santri sekaligus menggunakan format CSV sederhana</p>
    </div>

    <div class="form-container">
        <form method="post" action="<?= BASE_URL ?>/santri/bulk-create" id="bulkForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <div class="mb-4">
                <h5><i class="fas fa-info-circle text-primary me-2"></i>Petunjuk Format Data</h5>
                <div class="example-box">
                    <p>Gunakan format berikut untuk setiap santri (pisahkan dengan koma):</p>
                    <p class="mb-1"><strong>Nama,Kelas,Kamar</strong></p>
                    <p class="text-muted small mb-2">Contoh:</p>
                    <pre class="mb-0">Raffa,10,4
Fauzan,11,2
Luqman,12,3</pre>
                </div>
            </div>

            <div class="mb-4">
                <label for="list_santri" class="form-label fw-bold">
                    <i class="fas fa-paste me-2"></i>Data Santri
                </label>
                <textarea name="list_santri" id="list_santri" class="form-control mb-2" 
                    placeholder="Masukkan data santri disini, satu santri per baris..."></textarea>
                <div class="form-text">
                    <i class="fas fa-lightbulb me-1"></i> Anda bisa copy-paste dari Excel atau text editor lain.
                </div>
            </div>

            <div class="d-flex justify-content-between form-actions">
                <a href="<?= BASE_URL ?>/santri" class="btn btn-secondary btn-lg">
                    <i class="fas fa-arrow-left me-2"></i>Kembali
                </a>
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-cloud-upload-alt me-2"></i>Upload Data
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    document.getElementById('bulkForm').addEventListener('submit', function(e) {
        const textarea = document.getElementById('list_santri');
        if (textarea.value.trim() === '') {
            e.preventDefault();
            // Fallback alert jika function showAlert belum diload
            if (typeof showAlert === 'function') {
                showAlert('Silakan masukkan data santri terlebih dahulu!', 'warning');
            } else {
                alert('Silakan masukkan data santri terlebih dahulu!');
            }
            textarea.focus();
        }
    });
</script>
