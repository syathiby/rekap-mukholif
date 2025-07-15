<?php include '../header.php'; ?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Pilih Jenis Pelanggaran</title>
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
    }
    .card-option:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 20px rgba(0,0,0,0.1);
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
    }
    .card-title {
      font-weight: 600;
      color: #343a40;
      margin-bottom: 1rem;
    }
    .card-text {
      color: #6c757d;
      margin-bottom: 1.5rem;
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
  <h2 class="text-center header-title mb-5">Pilih Jenis Pelanggaran</h2>
  <div class="row g-4">
    
    <div class="col-md-4">
      <div class="card card-option shadow-sm">
        <div class="card-body text-center p-4">
          <div class="card-icon bg-primary">
            <i class="fas fa-clock"></i>
          </div>
          <h5 class="card-title">Telat Sholat</h5>
          <p class="card-text">Catat santri yang telat melaksanakan sholat wajib atau sunnah.</p>
          <a href="telat-sholat/index.php" class="btn btn-primary btn-block">
            <i class="fas fa-sign-in-alt me-2"></i> Masuk
          </a>
        </div>
      </div>
    </div>
    
    <div class="col-md-4">
      <div class="card card-option shadow-sm">
        <div class="card-body text-center p-4">
          <div class="card-icon bg-warning" style="background: linear-gradient(135deg, #f7971e 0%, #ffd200 100%);">
            <i class="fas fa-user-clock"></i>
          </div>
          <h5 class="card-title">Telat KBM</h5>
          <p class="card-text">Catat santri yang telat mengikuti Kegiatan Belajar Mengajar.</p>
          <a href="telat-kbm/index.php" class="btn btn-warning btn-block">
            <i class="fas fa-sign-in-alt me-2"></i> Masuk
          </a>
        </div>
      </div>
    </div>

    <div class="col-md-4">
      <div class="card card-option shadow-sm">
        <div class="card-body text-center p-4">
          <div class="card-icon bg-success" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
            <i class="fas fa-broom"></i>
          </div>
          <h5 class="card-title">Kebersihan Kamar</h5>
          <p class="card-text">Catat pelanggaran kebersihan berdasarkan kamar santri.</p>
          <a href="kebersihan-kamar/index.php" class="btn btn-success btn-block">
            <i class="fas fa-sign-in-alt me-2"></i> Masuk
          </a>
        </div>
      </div>
    </div>

  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php include '../footer.php'; ?>