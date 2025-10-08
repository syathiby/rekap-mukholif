<?php
require_once __DIR__ . '/../header.php';
guard('arsip_create');
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buat Arsip Baru</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* SEMUA CSS DI BAWAH INI DIAWALI DENGAN '#arsip-create-page'
          Ini akan "mengunci" style agar hanya berlaku di dalam halaman ini
          dan TIDAK AKAN merusak header utama.
        */
        #arsip-create-page {
            /* Style untuk body kita pindahkan ke wrapper ini */
            font-family: 'Poppins', sans-serif;
            color: #1e293b;
        }

        #arsip-create-page .container-fluid {
            padding: 1rem 2rem;
        }

        #arsip-create-page .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        #arsip-create-page .page-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        #arsip-create-page .page-title i {
            color: #4f46e5;
        }

        #arsip-create-page .card {
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.05);
        }

        #arsip-create-page .form-label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.75rem;
        }

        #arsip-create-page .form-control {
            width: 100%;
            padding: 0.85rem 1rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            transition: all 0.2s;
            font-size: 1rem;
        }

        #arsip-create-page .form-control:focus {
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px #e0e7ff;
            outline: none;
        }

        #arsip-create-page .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            border: 1px solid transparent;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        #arsip-create-page .btn-primary {
            background-color: transparent;
            border-color: #4f46e5;
            color: #4f46e5;
        }
        
        #arsip-create-page .btn-primary:hover {
            background-color: #4f46e5;
            color: white;
        }

        #arsip-create-page .btn-light-outline {
            background-color: transparent;
            border-color: #e2e8f0;
            color: #1e293b;
        }

        #arsip-create-page .btn-light-outline:hover {
            background-color: #f8fafc;
        }

        @media (max-width: 768px) {
            #arsip-create-page .container-fluid {
                padding: 1rem;
            }

            #arsip-create-page .page-title {
                font-size: 1.5rem;
            }

            #arsip-create-page .page-header .btn {
                font-size: 0.8rem;
                padding: 0.5rem 1rem;
            }
        }
    </style>
</head>
<body>

<div id="arsip-create-page">
    <div class="container-fluid mt-4">
        <div class="page-header">
            <h1 class="page-title"><i class="fas fa-plus-circle"></i>Buat Arsip Baru</h1>
            <a href="index.php" class="btn btn-light-outline"> &larr; Kembali</a>
        </div>

        <?php display_flash_message(); ?>

        <div class="card">
            <div class="card-body p-4">
                <form action="process.php" method="post" id="arsipForm">
                    <input type="hidden" name="action" value="create">
                    <div class="row g-4">
                        <div class="col-12">
                            <label for="judul" class="form-label">Judul Arsip</label>
                            <input type="text" class="form-control" name="judul" id="judul" required placeholder="Contoh: Rekap Semester Ganjil">
                        </div>
                        <div class="col-12">
                            <label for="tgl_mulai" class="form-label">Periode Mulai</label>
                            <input type="date" class="form-control" name="tgl_mulai" id="tgl_mulai" required>
                        </div>
                        <div class="col-12">
                            <label for="tgl_selesai" class="form-label">Periode Selesai</label>
                            <input type="date" class="form-control" name="tgl_selesai" id="tgl_selesai" required>
                        </div>
                        <div class="col-12 text-end mt-3">
                            <button type="submit" class="btn btn-primary w-100"><i class="fas fa-cogs me-2"></i>Proses & Buat Arsip</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../footer.php'; ?>
</body>
</html>