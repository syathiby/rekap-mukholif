<?php 
// 1. Panggil 'Otak' aplikasi dulu
require_once __DIR__ . '/../init.php';

// 2. Jalankan 'SATPAM' buat ngejaga halaman
guard(['pelanggaran_bahasa_input', 'pelanggaran_diniyyah_input', 'pelanggaran_tahfidz_input', 'pelanggaran_kesantrian_input', 'pelanggaran_pengabdian_input']);   

// 3. Kalau lolos, baru panggil Tampilan
require_once __DIR__ . '/../header.php'; 
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Pilih Jenis Pelanggaran</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    body {
      background-color: #f8f9fa;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    .card-option {
      border: none;
      border-radius: 12px;
      transition: all 0.3s ease;
      background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
      height: 100%;
      display: flex;
      flex-direction: column;
    }
    .card-option:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 20px rgba(0,0,0,0.1);
    }
    .card-option .card-body {
        flex-grow: 1;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }
    .card-icon {
      font-size: 2.5rem;
      margin-bottom: 1rem;
      color: #fff;
      width: 70px;
      height: 70px;
      line-height: 70px;
      border-radius: 50%;
      margin: 0 auto 1.5rem;
      background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
    }
    .btn-block {
      width: 100%;
      border-radius: 8px;
      font-weight: 500;
      padding: 10px;
      margin-top: auto; /* Mendorong tombol ke bawah */
    }
    .card-title {
      font-weight: 600;
      color: #343a40;
      margin-bottom: 1rem;
    }
    .card-text {
      color: #6c757d;
      margin-bottom: 1.5rem;
      flex-grow: 1;
    }
    .header-title {
      color: #343a40;
      margin-bottom: 2rem;
      position: relative;
      padding-bottom: 10px;
    }
    .header-title:after {
      content: '';
      position: absolute;
      bottom: 0;
      left: 50%;
      transform: translateX(-50%);
      width: 80px;
      height: 3px;
      background: linear-gradient(to right, #6a11cb, #2575fc);
      border-radius: 3px;
    }
  </style>
</head>
<body>
<div class="container py-5">
  <h2 class="text-center header-title mb-5">Pilih Jenis Pencatatan</h2>
  <div class="row g-4 justify-content-center">
    
    <?php if (has_permission('pelanggaran_pengabdian_input')): ?>
    <div class="col-lg-3 col-md-6">
        <div class="card card-option shadow-sm">
            <div class="card-body text-center p-4">
                <div class="card-icon" style="background: linear-gradient(135deg, #f7971e 0%, #ffd200 100%);">
                    <i class="fas fa-hands-helping"></i>
                </div>
                <h5 class="card-title">Pengabdian</h5>
                <p class="card-text">Catat pelanggaran individu (telat) & kebersihan kamar.</p>
                <a href="pengabdian/create.php" class="btn btn-warning btn-block">
                    <i class="fas fa-sign-in-alt me-2"></i> Masuk
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (has_permission('pelanggaran_kesantrian_input')): ?>
    <div class="col-lg-3 col-md-6">
      <div class="card card-option shadow-sm">
        <div class="card-body text-center p-4">
          <div class="card-icon" style="background: linear-gradient(135deg, #89f7fe 0%, #66a6ff 100%);">
              <i class="fas fa-user-shield"></i>
          </div>
          <h5 class="card-title">Kesantrian</h5>
          <p class="card-text">Catat berbagai jenis pelanggaran umum Kesantrian.</p>
          <a href="kesantrian/create.php" class="btn btn-info btn-block">
              <i class="fas fa-sign-in-alt me-2"></i> Masuk
          </a>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <?php if (has_permission('pelanggaran_bahasa_input')): ?>
    <div class="col-lg-3 col-md-6">
        <div class="card card-option shadow-sm">
            <div class="card-body text-center p-4">
                <div class="card-icon" style="background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);">
                    <i class="fas fa-language"></i>
                </div>
                <h5 class="card-title">Pelanggaran Bahasa</h5>
                <p class="card-text">Catat pelanggaran berbahasa, seperti muhadatsah & kosa kata.</p>
                <a href="bahasa/create.php" class="btn btn-primary btn-block">
                    <i class="fas fa-sign-in-alt me-2"></i> Masuk
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (has_permission('pelanggaran_diniyyah_input')): ?>
    <div class="col-lg-3 col-md-6">
        <div class="card card-option shadow-sm">
            <div class="card-body text-center p-4">
                <div class="card-icon" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                    <i class="fas fa-book-quran"></i>
                </div>
                <h5 class="card-title">Pelanggaran Diniyyah</h5>
                <p class="card-text">Catat pelanggaran terkait kegiatan belajar mengajar.</p>
                <a href="diniyyah/create.php" class="btn btn-success btn-block">
                    <i class="fas fa-sign-in-alt me-2"></i> Masuk
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ======================================================= -->
    <!-- === INI DIA KARTU BARU BUAT TAHFIDZ === -->
    <!-- ======================================================= -->
    <?php if (has_permission('pelanggaran_tahfidz_input')): ?>
    <div class="col-lg-3 col-md-6">
        <div class="card card-option shadow-sm">
            <div class="card-body text-center p-4">
                <div class="card-icon" style="background: linear-gradient(135deg, #dd2476 0%, #ff512f 100%);">
                    <i class="fas fa-book-reader"></i>
                </div>
                <h5 class="card-title">Tahfidz</h5>
                <p class="card-text">Catat berbagai jenis pelanggaran Divisi Tahfidz.</p>
                <a href="tahfidz/create.php" class="btn btn-danger btn-block">
                    <i class="fas fa-sign-in-alt me-2"></i> Masuk
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <!-- ======================================================= -->
    <!-- === SELESAI === -->
    <!-- ======================================================= -->

  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php include '../footer.php'; ?>