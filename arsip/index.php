<?php
include '../db.php';
include '../header.php';

// Ambil semua arsip beserta jumlah datanya
$sql = "
SELECT a.*,
  (SELECT COUNT(*) FROM arsip_pelanggaran ap WHERE ap.arsip_id = a.id) AS total_pelanggaran,
  (SELECT COUNT(*) FROM arsip_pelanggaran_kebersihan apk WHERE apk.arsip_id = a.id) AS total_kebersihan
FROM arsip a
ORDER BY a.dibuat_pada DESC";
$res = mysqli_query($conn, $sql);
?>

<style>
    :root {
        --primary: #3498db;
        --primary-dark: #2980b9;
        --danger: #e74c3c;
        --danger-dark: #c0392b;
        --success: #2ecc71;
        --text: #333;
        --text-light: #7f8c8d;
        --bg-light: #f8f9fa;
        --border: #ecf0f1;
        --shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    
    /* ✅ Hanya atur box-sizing global, TAPI jangan reset margin & padding */
    * {
        box-sizing: border-box;
    }
    
    /* ✅ Isolasi semua style ke dalam .arsip-page agar tidak bentrok */
    .arsip-page {
        margin-top: 20px;
        padding: 0 15px;
    }
    
    .arsip-page .container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        color: var(--text);
    }
    
    .arsip-page h2 {
        color: #2c3e50;
        margin-bottom: 25px;
        padding-bottom: 10px;
        border-bottom: 2px solid var(--border);
        font-size: 28px;
        display: flex;
        align-items: center;
    }
    
    .arsip-page h2:before {
        content: "📦";
        margin-right: 10px;
    }
    
    .arsip-page .archive-form {
        background-color: var(--bg-light);
        padding: 20px;
        border-radius: 8px;
        box-shadow: var(--shadow);
        margin-bottom: 30px;
    }
    
    .arsip-page .archive-form label {
        display: block;
        margin-bottom: 6px;
        font-weight: 600;
        color: #2c3e50;
    }
    
    .arsip-page .archive-form input[type="text"],
    .arsip-page .archive-form input[type="date"] {
        padding: 10px 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
        transition: border-color 0.3s;
        width: 100%;
    }
    
    .arsip-page .archive-form input[type="text"]:focus,
    .arsip-page .archive-form input[type="date"]:focus {
        border-color: var(--primary);
        outline: none;
        box-shadow: 0 0 0 2px rgba(52,152,219,0.2);
    }
    
    .arsip-page .form-group {
        margin-bottom: 15px;
    }
    
    .arsip-page .date-inputs {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        margin-top: 8px;
    }
    
    .arsip-page .date-inputs > div {
        flex: 1;
        min-width: 150px;
    }
    
    .arsip-page button {
        background-color: var(--primary);
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 4px;
        cursor: pointer;
        font-weight: 600;
        transition: background-color 0.3s;
    }
    
    .arsip-page button:hover {
        background-color: var(--primary-dark);
    }
    
    .arsip-page .table-container {
        overflow-x: auto;
        margin-top: 20px;
        box-shadow: var(--shadow);
        border-radius: 8px;
        background-color: white;
        width: 100%;
    }
    
    .arsip-page table {
        width: 100%;
        border-collapse: collapse;
        min-width: 800px;
    }
    
    .arsip-page th, .arsip-page td {
        padding: 12px 15px;
        text-align: left;
        border-bottom: 1px solid var(--border);
    }
    
    .arsip-page th {
        background-color: var(--bg-light);
        font-weight: 600;
        color: #2c3e50;
        position: sticky;
        top: 0;
        white-space: nowrap;
    }
    
    .arsip-page tr:hover {
        background-color: #f5f5f5;
    }
    
    .arsip-page .action-form {
        display: inline;
        margin: 0;
    }
    
    .arsip-page .action-form button {
        background-color: var(--danger);
        padding: 5px 10px;
        font-size: 13px;
    }
    
    .arsip-page .action-form button:hover {
        background-color: var(--danger-dark);
    }
    
    .arsip-page a {
        color: var(--primary);
        text-decoration: none;
        transition: color 0.3s;
    }
    
    .arsip-page a:hover {
        color: var(--primary-dark);
        text-decoration: underline;
    }
    
    .arsip-page .no-data {
        text-align: center;
        padding: 30px;
        color: var(--text-light);
        font-style: italic;
    }

    /* Responsive Styles */
    @media (max-width: 768px) {
        .arsip-page .container { padding: 15px; }
        .arsip-page h2 { font-size: 24px; margin-bottom: 20px; }
        .arsip-page .archive-form { padding: 15px; }
        .arsip-page .date-inputs { flex-direction: column; gap: 10px; }
        .arsip-page .date-inputs > div { width: 100%; }
        .arsip-page th, .arsip-page td { padding: 10px; font-size: 14px; }
        .action-buttons { display: flex; flex-direction: column; gap: 5px; }
    }
    
    @media (max-width: 480px) {
        .arsip-page h2 { font-size: 20px; }
        .arsip-page .archive-form input[type="text"],
        .arsip-page .archive-form input[type="date"] { padding: 8px 10px; }
        .arsip-page button { padding: 8px 16px; width: 100%; }
        .arsip-page th, .arsip-page td { padding: 8px; font-size: 13px; }
        .arsip-page table { min-width: 700px; }
    }
</style>

<div class="arsip-page">
    <div class="container">
  <h2>Arsip Data</h2>

  <!-- Form Buat Arsip Baru -->
  <div class="archive-form">
    <form action="process.php" method="post">
      <input type="hidden" name="action" value="create">

      <div class="form-group">
        <label for="judul">Judul Arsip</label>
        <input type="text" name="judul" id="judul" required placeholder="Masukkan judul arsip">
      </div>

      <div class="form-group">
        <label>Periode Arsip</label>
        <div class="date-inputs">
          <div>
            <label for="tgl_mulai">Tanggal Mulai</label>
            <input type="date" name="tgl_mulai" id="tgl_mulai" required>
          </div>
          <div>
            <label for="tgl_selesai">Tanggal Selesai</label>
            <input type="date" name="tgl_selesai" id="tgl_selesai" required>
          </div>
        </div>
      </div>

      <button type="submit">Buat Arsip Baru</button>
    </form>
  </div>

  <!-- Daftar Arsip -->
  <div class="table-container">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Judul</th>
          <th>Periode</th>
          <th>Jumlah Pelanggaran</th>
          <th>Jumlah Kebersihan</th>
          <th>Dibuat Pada</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if(mysqli_num_rows($res) > 0): ?>
          <?php $no=1; while($row = mysqli_fetch_assoc($res)): ?>
          <tr>
            <td><?= $no++; ?></td>
            <td><?= htmlspecialchars($row['judul']); ?></td>
            <td><?= $row['tanggal_mulai']; ?> s/d <?= $row['tanggal_selesai']; ?></td>
            <td><?= $row['total_pelanggaran']; ?></td>
            <td><?= $row['total_kebersihan']; ?></td>
            <td><?= $row['dibuat_pada']; ?></td>
            <td>
              <a href="view.php?id=<?= $row['id']; ?>">Lihat</a> |
              <form class="action-form" action="process.php" method="post" onsubmit="return confirm('Yakin hapus arsip ini?');">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $row['id']; ?>">
                <button type="submit">Hapus</button>
              </form>
            </td>
          </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr>
            <td colspan="7" class="no-data">Belum ada data arsip</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include '../footer.php'; ?>