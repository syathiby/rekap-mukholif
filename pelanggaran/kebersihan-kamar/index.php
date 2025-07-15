<?php
include '../../db.php';
include '../../header.php';

// Ambil daftar kamar unik
$kamarQuery = mysqli_query($conn, "
  SELECT DISTINCT kamar
  FROM santri
  WHERE kamar IS NOT NULL AND kamar != ''
  ORDER BY
    REGEXP_REPLACE(kamar, '[0-9]', '') ASC,
    CAST(REGEXP_REPLACE(kamar, '[^0-9]', '') AS UNSIGNED) ASC
");
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Input Pelanggaran Kebersihan Massal</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .checkbox-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
      gap: 10px;
    }
    .checkbox-grid label {
      display: block;
      padding: 8px;
      border: 1px solid #ddd;
      border-radius: 5px;
      background-color: #f8f9fa;
      cursor: pointer;
      text-align: center;
      transition: all 0.2s ease-in-out;
    }
    .checkbox-grid input:checked + label {
      background-color: #ffc107;
      color: #fff;
      font-weight: bold;
      border-color: #ffca2c;
    }
    .form-title {
      margin-bottom: 20px;
    }
  </style>
</head>
<body>
<div class="container mt-5">
  <h2 class="text-center form-title">Catat Pelanggaran Kebersihan Kamar</h2>

  <?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
  <?php endif; ?>
  <?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
  <?php endif; ?>

  <form action="process.php" method="POST">
    <div class="mb-4">
      <label class="form-label">Pilih Kamar yang Melanggar:</label>
      <div class="checkbox-grid">
        <?php while($row = mysqli_fetch_assoc($kamarQuery)): ?>
          <div>
            <input type="checkbox" class="btn-check" name="kamar[]" id="kamar-<?= $row['kamar'] ?>" value="<?= htmlspecialchars($row['kamar']) ?>" autocomplete="off">
            <label class="btn btn-outline-warning w-100" for="kamar-<?= $row['kamar'] ?>"><?= htmlspecialchars($row['kamar']) ?></label>
          </div>
        <?php endwhile; ?>
      </div>
    </div>

    <div class="mb-3">
      <label class="form-label">Catatan (opsional):</label>
      <textarea name="catatan" class="form-control" rows="2"></textarea>
    </div>

    <div class="mb-3">
      <label class="form-label">Tanggal Pelanggaran:</label>
      <input type="datetime-local" name="tanggal" class="form-control" value="<?= date('Y-m-d\TH:i') ?>" required>
    </div>

    <button type="submit" class="btn btn-success">Catat Pelanggaran</button>
  </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php include '../../footer.php'; ?>
