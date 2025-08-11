<?php
include '../db.php';
include '../header.php';

// Ambil semua santri yang tidak pernah ada di tabel pelanggaran
$query = mysqli_query($conn, "
    SELECT s.id, s.nama, s.kelas, s.kamar
    FROM santri s
    LEFT JOIN pelanggaran p ON s.id = p.santri_id
    WHERE p.santri_id IS NULL
    ORDER BY s.nama ASC
");
?>

<style>
/* Styling tabel hijau kalem + animasi fade-in (tanpa per-baris) */
.table-container {
    overflow-x: auto;
    margin-top: 20px;
    background: #ffffff;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    animation: fadeIn 0.8s ease-in-out;
}

table {
    border-collapse: collapse;
    width: 100%;
    min-width: 600px;
}

table th {
    background: #2e7d32;
    color: white;
    padding: 10px;
    text-align: left;
}

table td {
    padding: 8px 10px;
    border-bottom: 1px solid #ddd;
}

table tr:hover {
    background: #e8f5e9;
}

h2 {
    margin-top: 20px;
    color: #1b5e20;
    font-size: 22px;
    font-weight: bold;
    animation: fadeIn 0.8s ease-in-out;
}

/* Animasi */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>

<h2>📋 Daftar Santri Teladan</h2>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Nama Santri</th>
                <th>Kelas</th>
                <th>Kamar</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $no = 1;
            while ($row = mysqli_fetch_assoc($query)) {
                echo "<tr>
                        <td>{$no}</td>
                        <td>{$row['nama']}</td>
                        <td>{$row['kelas']}</td>
                        <td>{$row['kamar']}</td>
                      </tr>";
                $no++;
            }
            ?>
        </tbody>
    </table>
</div>

<?php include '../footer.php'; ?>