<?php
require_once '../../init.php';
guard('jenis_reward_edit'); 

// Cek kalo gak ada data yang dipilih
if (!isset($_POST['ids']) || empty($_POST['ids'])) {
    header("Location: index.php");
    exit;
}

require_once '../../header.php';

$ids = implode(',', array_map('intval', $_POST['ids']));
$query = "SELECT * FROM jenis_reward WHERE id IN ($ids)";
$result = mysqli_query($conn, $query);
$total_data = mysqli_num_rows($result);

// Simpan data ke array untuk digunakan dua kali
$rewards_data = [];
while($row = mysqli_fetch_assoc($result)) {
    $rewards_data[] = $row;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Edit Reward - AsuhTrack</title>
    <style>
        /* Modern Minimalist Styles */
        :root {
            --primary: #4361ee;
            --primary-light: #eef2ff;
            --secondary: #6c757d;
            --success: #06d6a0;
            --light: #f8f9fa;
            --dark: #212529;
            --border: #e9ecef;
            --shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            --radius: 12px;
        }
        
        body {
            background-color: #fafbfe;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: #344767;
            min-height: 100vh;
        }
        
        /* Header Styles */
        .page-header {
            background: linear-gradient(135deg, #4361ee 0%, #3a56d4 100%);
            border-radius: var(--radius);
            padding: 1.5rem 1.5rem;
            margin-bottom: 2rem;
            color: white;
            box-shadow: var(--shadow);
        }
        
        .page-title {
            font-weight: 600;
            font-size: 1.5rem;
            margin-bottom: 0.25rem;
        }
        
        .page-subtitle {
            opacity: 0.9;
            font-size: 0.875rem;
            margin-bottom: 0;
        }
        
        .badge-count {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        /* Card Styles */
        .edit-card {
            background: white;
            border-radius: var(--radius);
            border: none;
            box-shadow: var(--shadow);
            margin-bottom: 1.5rem;
            overflow: hidden;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .edit-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
        }
        
        .card-header-minimal {
            background: var(--primary-light);
            border-bottom: 1px solid var(--border);
            padding: 1rem 1.5rem;
        }
        
        .card-header-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--primary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0;
        }
        
        .card-badge {
            background: white;
            color: var(--primary);
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.25rem 0.75rem;
            border-radius: 6px;
            border: 1px solid var(--primary);
        }
        
        /* Form Styles */
        .form-group-minimal {
            margin-bottom: 1.25rem;
        }
        
        .form-label-minimal {
            display: block;
            font-size: 0.85rem;
            font-weight: 500;
            color: #5e6e82;
            margin-bottom: 0.5rem;
        }
        
        .form-input-minimal {
            width: 100%;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            border: 1.5px solid #e0e0e0;
            border-radius: 10px;
            background: white;
            transition: all 0.2s ease;
            color: #344767;
        }
        
        .form-input-minimal:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
            background: white;
        }
        
        .form-input-minimal::placeholder {
            color: #adb5bd;
        }
        
        .input-group-minimal {
            display: flex;
            align-items: center;
        }
        
        .input-group-minimal input {
            flex: 1;
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
            border-right: none;
        }
        
        .input-group-suffix {
            padding: 0.75rem 1rem;
            background: var(--primary-light);
            border: 1.5px solid #e0e0e0;
            border-left: none;
            border-top-right-radius: 10px;
            border-bottom-right-radius: 10px;
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--primary);
        }
        
        /* Button Styles */
        .action-bar {
            position: sticky;
            bottom: 0;
            background: white;
            border-top: 1px solid var(--border);
            padding: 1.25rem;
            margin-top: 2rem;
            box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.05);
        }
        
        .btn-modern {
            padding: 0.875rem 1.75rem;
            font-size: 1rem;
            font-weight: 500;
            border-radius: 10px;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            text-decoration: none;
        }
        
        .btn-modern-secondary {
            background: white;
            color: var(--secondary);
            border: 1.5px solid #e0e0e0;
        }
        
        .btn-modern-secondary:hover {
            background: #f8f9fa;
            border-color: #adb5bd;
            text-decoration: none;
        }
        
        .btn-modern-primary {
            background: linear-gradient(135deg, #4361ee 0%, #3a56d4 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(67, 97, 238, 0.3);
        }
        
        .btn-modern-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(67, 97, 238, 0.4);
        }
        
        .btn-modern-primary:active {
            transform: translateY(0);
        }
        
        /* Footer Note */
        .action-note {
            text-align: center;
            margin-top: 1rem;
            font-size: 0.85rem;
            color: #6c757d;
            opacity: 0.8;
        }
        
        .action-note i {
            margin-right: 0.5rem;
        }
        
        /* Desktop Styles */
        @media (min-width: 768px) {
            .desktop-only {
                display: block;
            }
            
            .mobile-only {
                display: none !important;
            }
            
            .container-main {
                max-width: 1000px;
                margin: 0 auto;
                padding: 2rem;
            }
            
            .page-header {
                padding: 1.75rem 2rem;
            }
            
            .page-title {
                font-size: 1.75rem;
            }
            
            .desktop-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 1.5rem;
                margin-bottom: 2rem;
            }
            
            .desktop-table-container {
                background: white;
                border-radius: var(--radius);
                box-shadow: var(--shadow);
                overflow: hidden;
                margin-bottom: 2rem;
            }
            
            .table-header {
                background: var(--primary-light);
                padding: 1.25rem 1.5rem;
                border-bottom: 1px solid var(--border);
            }
            
            .table-header h3 {
                font-size: 1rem;
                font-weight: 600;
                color: var(--primary);
                text-transform: uppercase;
                letter-spacing: 0.5px;
                margin: 0;
            }
            
            .table-responsive {
                overflow-x: auto;
            }
            
            .table-minimal {
                width: 100%;
                border-collapse: collapse;
            }
            
            .table-minimal thead {
                background-color: #f8fafc;
            }
            
            .table-minimal th {
                padding: 1rem 1.25rem;
                font-size: 0.85rem;
                font-weight: 600;
                color: #5e6e82;
                text-align: left;
                border-bottom: 2px solid var(--border);
                white-space: nowrap;
            }
            
            .table-minimal td {
                padding: 1rem 1.25rem;
                border-bottom: 1px solid var(--border);
                vertical-align: middle;
            }
            
            .table-minimal tbody tr:hover {
                background-color: #f8fafc;
            }
            
            .table-minimal .id-badge {
                background: var(--primary-light);
                color: var(--primary);
                font-size: 0.75rem;
                font-weight: 600;
                padding: 0.25rem 0.75rem;
                border-radius: 6px;
                display: inline-block;
            }
            
            .btn-group-desktop {
                display: flex;
                gap: 1rem;
                justify-content: flex-end;
                padding: 1.5rem;
                background: white;
                border-top: 1px solid var(--border);
                margin-top: 1rem;
            }
        }
        
        /* Mobile Optimization */
        @media (max-width: 767px) {
            .desktop-only {
                display: none !important;
            }
            
            .mobile-only {
                display: block;
            }
            
            .container-main {
                padding: 1rem;
                padding-bottom: 80px; /* Untuk memberi ruang action bar */
            }
            
            .btn-modern {
                flex: 1;
            }
            
            .btn-group-modern {
                display: flex;
                gap: 0.75rem;
            }
            
            .action-bar {
                position: fixed;
                left: 0;
                right: 0;
                bottom: 0;
                z-index: 1000;
            }
        }
        
        /* Common Styles */
        .error-message {
            background: #fee;
            border: 1px solid #fcc;
            border-radius: 10px;
            padding: 1rem 1.25rem;
            margin: 1rem 0;
            color: #d32f2f;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: slideDown 0.3s ease;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Loading State */
        .btn-loading {
            position: relative;
            color: transparent !important;
        }
        
        .btn-loading::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Animation */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .fade-in {
            animation: fadeIn 0.3s ease forwards;
        }
    </style>
</head>
<body>
    <div class="container-main">
        <!-- Modern Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="page-title">
                        <i class="fas fa-edit me-2"></i>Edit Massal
                    </h1>
                    <p class="page-subtitle">Perbarui beberapa reward sekaligus</p>
                </div>
                <span class="badge-count">
                    <i class="fas fa-layer-group me-1"></i>
                    <?= $total_data ?> Data
                </span>
            </div>
        </div>
        
        <!-- Desktop View (Table Layout) -->
        <div class="desktop-only">
            <div class="desktop-table-container">
                <div class="table-header">
                    <h3><i class="fas fa-gift me-2"></i>Data Reward yang Akan Diedit</h3>
                </div>
                
                <form action="process.php" method="POST" id="bulkFormDesktop">
                    <div class="table-responsive">
                        <table class="table-minimal">
                            <thead>
                                <tr>
                                    <th style="width: 60px;">ID</th>
                                    <th style="width: 300px;">Nama Reward</th>
                                    <th style="width: 150px;">Poin Reward</th>
                                    <th>Deskripsi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($rewards_data as $counter => $row): ?>
                                <tr class="fade-in" style="animation-delay: <?= ($counter * 0.05) ?>s">
                                    <td>
                                        <span class="id-badge"><?= $row['id'] ?></span>
                                        <input type="hidden" name="ids[]" value="<?= $row['id'] ?>">
                                    </td>
                                    <td>
                                        <input type="text" 
                                               name="nama_reward[<?= $row['id'] ?>]" 
                                               class="form-input-minimal" 
                                               value="<?= htmlspecialchars($row['nama_reward']) ?>" 
                                               placeholder="Masukkan nama reward"
                                               required
                                               style="width: 100%;">
                                    </td>
                                    <td>
                                        <div class="input-group-minimal" style="width: 140px;">
                                            <input type="number" 
                                                   name="poin_reward[<?= $row['id'] ?>]" 
                                                   class="form-input-minimal" 
                                                   value="<?= $row['poin_reward'] ?>" 
                                                   min="1"
                                                   placeholder="0"
                                                   required>
                                            <span class="input-group-suffix">Poin</span>
                                        </div>
                                    </td>
                                    <td>
                                        <input type="text" 
                                               name="deskripsi[<?= $row['id'] ?>]" 
                                               class="form-input-minimal" 
                                               value="<?= htmlspecialchars($row['deskripsi']) ?>" 
                                               placeholder="Deskripsi singkat"
                                               style="width: 100%;">
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="btn-group-desktop">
                        <a href="index.php" class="btn-modern btn-modern-secondary">
                            <i class="fas fa-times me-1"></i>
                            Batal
                        </a>
                        <button type="submit" name="bulk_update" class="btn-modern btn-modern-primary" id="submitBtnDesktop">
                            <i class="fas fa-save me-1"></i>
                            Simpan Semua
                        </button>
                    </div>
                </form>
            </div>
            
            <div class="action-note">
                <i class="fas fa-info-circle"></i>
                Semua perubahan akan disimpan sekaligus. Pastikan semua data sudah benar sebelum menyimpan.
            </div>
        </div>
        
        <!-- Mobile View (Card Layout) -->
        <div class="mobile-only fade-in">
            <form action="process.php" method="POST" id="bulkFormMobile">
                <?php foreach($rewards_data as $counter => $row): ?>
                <div class="edit-card fade-in" style="animation-delay: <?= ($counter * 0.05) ?>s">
                    <div class="card-header-minimal d-flex justify-content-between align-items-center">
                        <h2 class="card-header-title">
                            <i class="fas fa-gift me-2"></i>Reward #<?= $counter + 1 ?>
                        </h2>
                        <span class="card-badge">ID: <?= $row['id'] ?></span>
                    </div>
                    
                    <div class="card-body p-3">
                        <input type="hidden" name="ids[]" value="<?= $row['id'] ?>">
                        
                        <!-- Nama Reward -->
                        <div class="form-group-minimal">
                            <label class="form-label-minimal">
                                <i class="fas fa-tag me-1"></i> Nama Reward
                            </label>
                            <input type="text" 
                                   name="nama_reward[<?= $row['id'] ?>]" 
                                   class="form-input-minimal" 
                                   value="<?= htmlspecialchars($row['nama_reward']) ?>" 
                                   placeholder="Contoh: Membuat karya ilmiah"
                                   required>
                        </div>
                        
                        <!-- Poin Reward -->
                        <div class="form-group-minimal">
                            <label class="form-label-minimal">
                                <i class="fas fa-star me-1"></i> Poin Reward
                            </label>
                            <div class="input-group-minimal">
                                <input type="number" 
                                       name="poin_reward[<?= $row['id'] ?>]" 
                                       class="form-input-minimal" 
                                       value="<?= $row['poin_reward'] ?>" 
                                       min="1"
                                       placeholder="0"
                                       required>
                                <span class="input-group-suffix">Poin</span>
                            </div>
                        </div>
                        
                        <!-- Deskripsi -->
                        <div class="form-group-minimal mb-0">
                            <label class="form-label-minimal">
                                <i class="fas fa-align-left me-1"></i> Deskripsi (Opsional)
                            </label>
                            <input type="text" 
                                   name="deskripsi[<?= $row['id'] ?>]" 
                                   class="form-input-minimal" 
                                   value="<?= htmlspecialchars($row['deskripsi']) ?>" 
                                   placeholder="Deskripsi singkat reward">
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <!-- Modern Action Bar -->
                <div class="action-bar">
                    <div class="btn-group-modern">
                        <a href="index.php" class="btn-modern btn-modern-secondary">
                            <i class="fas fa-times"></i>
                            Batal
                        </a>
                        <button type="submit" name="bulk_update" 
                                class="btn-modern btn-modern-primary" id="submitBtnMobile">
                            <i class="fas fa-save"></i>
                            Simpan Semua
                        </button>
                    </div>
                    <p class="action-note">
                        <i class="fas fa-info-circle"></i>
                        Semua perubahan akan disimpan sekaligus
                    </p>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Common validation function
            function validateForm(form) {
                let isValid = true;
                const numberInputs = form.querySelectorAll('input[type="number"]');
                const errorInputs = [];
                
                // Check each input
                numberInputs.forEach(input => {
                    if (input.value <= 0 || input.value === '') {
                        isValid = false;
                        errorInputs.push(input);
                        
                        // Add error styling
                        input.style.borderColor = '#e63946';
                        input.style.boxShadow = '0 0 0 3px rgba(230, 57, 70, 0.1)';
                        
                        // Scroll to first error
                        if (errorInputs.length === 1) {
                            if (window.innerWidth >= 768) {
                                input.closest('tr').scrollIntoView({
                                    behavior: 'smooth',
                                    block: 'center'
                                });
                            } else {
                                input.closest('.edit-card').scrollIntoView({
                                    behavior: 'smooth',
                                    block: 'center'
                                });
                            }
                        }
                    } else {
                        input.style.borderColor = '';
                        input.style.boxShadow = '';
                    }
                });
                
                return { isValid, errorInputs };
            }
            
            // Show error message
            function showError(form, message) {
                // Remove previous error if exists
                const oldError = form.querySelector('.error-message');
                if (oldError) oldError.remove();
                
                // Create error message
                const errorMsg = document.createElement('div');
                errorMsg.className = 'error-message';
                errorMsg.innerHTML = `
                    <i class="fas fa-exclamation-triangle"></i>
                    <div>${message}</div>
                `;
                
                // Insert error at the beginning of form
                form.insertBefore(errorMsg, form.firstChild);
                
                // Auto remove error after 5 seconds
                setTimeout(() => {
                    if (errorMsg.parentNode) {
                        errorMsg.remove();
                    }
                }, 5000);
            }
            
            // Clear errors on input
            function clearErrors(input) {
                input.style.borderColor = '';
                input.style.boxShadow = '';
                
                // Remove error message if exists
                const form = input.closest('form');
                const errorMsg = form.querySelector('.error-message');
                if (errorMsg) errorMsg.remove();
            }
            
            // Desktop form handling
            const desktopForm = document.getElementById('bulkFormDesktop');
            const desktopSubmitBtn = document.getElementById('submitBtnDesktop');
            
            if (desktopForm) {
                desktopForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const validation = validateForm(this);
                    
                    if (!validation.isValid) {
                        showError(this, 'Poin reward harus lebih besar dari 0!');
                        return false;
                    }
                    
                    // Show loading state
                    desktopSubmitBtn.classList.add('btn-loading');
                    desktopSubmitBtn.disabled = true;
                    
                    // Remove any existing error messages
                    const errorMsg = this.querySelector('.error-message');
                    if (errorMsg) errorMsg.remove();
                    
                    // Submit form after a brief delay to show loading state
                    setTimeout(() => {
                        this.submit();
                    }, 300);
                });
                
                // Clear error on input
                const desktopInputs = desktopForm.querySelectorAll('input');
                desktopInputs.forEach(input => {
                    input.addEventListener('input', function() {
                        clearErrors(this);
                    });
                });
            }
            
            // Mobile form handling
            const mobileForm = document.getElementById('bulkFormMobile');
            const mobileSubmitBtn = document.getElementById('submitBtnMobile');
            
            if (mobileForm) {
                mobileForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const validation = validateForm(this);
                    
                    if (!validation.isValid) {
                        showError(this, 'Poin reward harus lebih besar dari 0!');
                        return false;
                    }
                    
                    // Show loading state
                    mobileSubmitBtn.classList.add('btn-loading');
                    mobileSubmitBtn.disabled = true;
                    
                    // Remove any existing error messages
                    const errorMsg = this.querySelector('.error-message');
                    if (errorMsg) errorMsg.remove();
                    
                    // Submit form after a brief delay to show loading state
                    setTimeout(() => {
                        this.submit();
                    }, 300);
                });
                
                // Clear error on input
                const mobileInputs = mobileForm.querySelectorAll('input');
                mobileInputs.forEach(input => {
                    input.addEventListener('input', function() {
                        clearErrors(this);
                    });
                });
            }
            
            // Add subtle animation to cards on load
            const cards = document.querySelectorAll('.edit-card, .table-minimal tbody tr');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 50);
            });
        });
        
        // Handle back button
        window.addEventListener('pageshow', function(event) {
            if (event.persisted) {
                window.location.reload();
            }
        });
        
        // Handle resize for responsive behavior
        window.addEventListener('resize', function() {
            // Refresh page if crossing mobile/desktop boundary
            if ((window.innerWidth >= 768 && document.querySelector('.mobile-only').style.display !== 'none') ||
                (window.innerWidth < 768 && document.querySelector('.desktop-only').style.display !== 'none')) {
                window.location.reload();
            }
        });
    </script>
</body>
</html>

<?php require_once '../../footer.php'; ?>