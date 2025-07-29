<?php 
include '../../db.php';
include '../../header.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pencatatan Pelanggaran Telat Sholat</title>
    <link rel="stylesheet" href="../../santri/style.css">
    <link rel="stylesheet" href="style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.css">
</head>

<style>
    body {
        background-color: #f8f9fa;
    }

    .container {
        padding-left: 1rem;
        padding-right: 1rem;
    }

    @media (max-width: 576px) {
        h2.text-center {
            font-size: 1.2rem;
        }

        .btn {
            font-size: 0.85rem;
            padding: 0.4rem 0.75rem;
        }

        .table th, .table td {
            font-size: 0.8rem;
            padding: 0.5rem;
        }
    }

    .card {
        border-radius: 10px;
    }

    .card-header h5 {
        font-size: 1rem;
        margin: 0;
    }

    #tambahSantri {
        width: 100%;
    }

    @media (min-width: 576px) {
        #tambahSantri {
            width: auto;
        }
    }
</style>

<body>
    <div class="container mt-4 px-3 px-sm-4">
        <h2 class="text-center mb-4">Pencatatan Pelanggaran Telat Sholat</h2>
        
        <div class="card shadow">
            <div class="card-header bg-warning text-white">
                <h5 class="mb-0">Form Pelanggaran</h5>
            </div>
            <div class="card-body">
                <form id="pelanggaranForm" action="process.php" method="POST">
                    <div class="row mb-3">
                        <div class="col-md-8">
                            <label for="santriSearch" class="form-label">Cari Santri:</label>
                            <input type="text" id="santriSearch" class="form-control" placeholder="Ketik nama santri...">
                        </div>
                        <div class="col-md-4 d-flex align-items-end mt-3 mt-md-0">
                            <button type="button" id="tambahSantri" class="btn btn-primary w-100 w-md-auto">Tambah ke Daftar</button>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-bordered" id="daftarSantri">
                            <thead class="table-light">
                                <tr>
                                    <th>Nama Santri</th>
                                    <th>Kelas</th>
                                    <th>Kamar</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Daftar santri yang melanggar akan muncul di sini -->
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="mt-3">
                        <button type="submit" class="btn btn-success">Simpan Pelanggaran</button>
                        <button type="button" id="resetForm" class="btn btn-danger">Reset Form</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="script.js"></script>
</body>
</html>

<?php include '../../footer.php'; ?>