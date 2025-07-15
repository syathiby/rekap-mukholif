<?php 
include '../db.php'; 
require_once __DIR__ . '/../header.php'; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Santri - Pesantren</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2c3e50;
            --accent-color: #e74c3c;
        }
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .container {
            max-width: 1200px;
        }
        .header {
            background: linear-gradient(135deg, var(--secondary-color), var(--primary-color));
            color: white;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        .table-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        .table thead {
            background-color: var(--secondary-color);
            color: white;
        }
        .table th {
            font-weight: 500;
        }
        .btn-action {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
        .checkbox-cell {
            width: 40px;
        }
        .action-cell {
            width: 120px;
        }
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        .btn-danger {
            background-color: var(--accent-color);
            border-color: var(--accent-color);
        }
        .btn-success {
            background-color: #27ae60;
            border-color: #27ae60;
        }
        .btn-warning {
            background-color: #f39c12;
            border-color: #f39c12;
        }
        .pagination .page-item.active .page-link {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        .pagination .page-link {
            color: var(--secondary-color);
        }
    </style>
</head>
<body>
<div class="container mt-4 mb-5">
    <div class="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="mb-0"><i class="fas fa-users me-2"></i>Data Santri</h2>
            <div>
                <span class="badge bg-light text-dark fs-6">
                    <i class="fas fa-database me-1"></i>
                    <?php 
                    $search = isset($_GET['search']) ? $_GET['search'] : '';
                    $count_query = "SELECT COUNT(*) as total FROM santri";
                    
                    if (!empty($search)) {
                        $count_query .= " WHERE nama LIKE '%$search%' OR kelas LIKE '%$search%' OR kamar LIKE '%$search%'";
                    }
                    
                    $count = mysqli_query($conn, $count_query);
                    $total = mysqli_fetch_assoc($count)['total'];
                    echo $total . " Santri";
                    ?>
                </span>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between mb-3">
        <div class="btn-group">
            <a href="create.php" class="btn btn-success me-2">
                <i class="fas fa-user-plus me-1"></i> Tambah Santri
            </a>
            <a href="bulk-create.php" class="btn btn-primary me-2">
                <i class="fas fa-file-import me-1"></i> Bulk Input
            </a>
            <a href="bulk-edit.php" class="btn btn-warning me-2">
                <i class="fas fa-pen-to-square me-1"></i> Bulk Edit
            </a>
            <button type="button" class="btn btn-danger" onclick="confirmBulkDelete()">
                <i class="fas fa-user-minus me-1"></i> Hapus Terpilih
            </button>
        </div>
        <div>
            <form class="d-flex" method="GET" action="">
                <input class="form-control me-2" type="search" name="search" placeholder="Cari santri..." 
                       aria-label="Search" value="<?= htmlspecialchars($search) ?>">
                <button class="btn btn-outline-secondary" type="submit"><i class="fas fa-search"></i></button>
                <?php if (!empty($search)): ?>
                    <a href="?" class="btn btn-outline-danger ms-2"><i class="fas fa-times"></i></a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="table-container">
        <form id="form-bulk-delete" method="post" action="bulk-delete.php">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th class="checkbox-cell"><input type="checkbox" id="select-all"></th>
                        <th>Nama Santri</th>
                        <th>Kelas</th>
                        <th>Kamar</th>
                        <th class="action-cell">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $query = "SELECT * FROM santri";
                    
                    if (!empty($search)) {
                        $query .= " WHERE nama LIKE '%$search%' OR kelas LIKE '%$search%' OR kamar LIKE '%$search%'";
                    }
                    
                    $query .= " ORDER BY nama ASC";
                    
                    $result = mysqli_query($conn, $query);
                    
                    if (mysqli_num_rows($result) > 0) {
                        while ($row = mysqli_fetch_assoc($result)) {
                    ?>
                    <tr>
                        <td><input type="checkbox" name="ids[]" value="<?= $row['id'] ?>"></td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="avatar me-2">
                                    <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center" 
                                         style="width: 36px; height: 36px; background-color: #<?= substr(md5($row['id']), 0, 6) ?>">
                                        <?= strtoupper(substr($row['nama'], 0, 1)) ?>
                                    </div>
                                </div>
                                <div>
                                    <strong><?= htmlspecialchars($row['nama']) ?></strong>
                                    <div class="text-muted small">ID: <?= $row['id'] ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-info text-dark">
                                <?= htmlspecialchars($row['kelas']) ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark">
                                <i class="fas fa-door-open me-1"></i>
                                <?= htmlspecialchars($row['kamar']) ?>
                            </span>
                        </td>
                        <td>
                            <div class="d-flex">
                                <a href="edit.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-warning me-2 btn-action" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="delete.php?id=<?= $row['id'] ?>" onclick="return confirmDelete()" class="btn btn-sm btn-danger btn-action" title="Hapus">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php 
                        }
                    } else {
                        echo '<tr><td colspan="5" class="text-center py-4">Tidak ada data santri ditemukan</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </form>
    </div>

    <!-- Pagination would go here when implemented -->
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Select all checkbox functionality
    document.getElementById("select-all").onclick = function() {
        const checkboxes = document.querySelectorAll('input[name="ids[]"]');
        for (let checkbox of checkboxes) {
            checkbox.checked = this.checked;
        }
    }

    // Confirm before bulk delete
    function confirmBulkDelete() {
        const checkedBoxes = document.querySelectorAll('input[name="ids[]"]:checked');
        if (checkedBoxes.length === 0) {
            alert('Silakan pilih santri yang akan dihapus terlebih dahulu.');
            return false;
        }
        
        if (confirm(`Anda yakin ingin menghapus ${checkedBoxes.length} santri terpilih?`)) {
            document.getElementById('form-bulk-delete').submit();
        }
    }

    // Confirm before single delete
    function confirmDelete() {
        return confirm('Apakah Anda yakin ingin menghapus santri ini?');
    }
</script>
</body>
</html>

<?php require_once __DIR__ . '/../footer.php'; ?>