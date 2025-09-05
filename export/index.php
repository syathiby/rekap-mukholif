<?php 
require_once __DIR__ . '/../header.php';
guard('export_laporan'); 
?>

<?php

// Ambil daftar kamar unik dari database
$kamar_list = [];
$sql_kamar = "SELECT DISTINCT kamar FROM santri WHERE kamar IS NOT NULL AND kamar != '' ORDER BY CAST(kamar AS UNSIGNED) ASC";
$result_kamar = $conn->query($sql_kamar);
if ($result_kamar && $result_kamar->num_rows > 0) {
    while ($row = $result_kamar->fetch_assoc()) {
        $kamar_list[] = $row['kamar'];
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export Laporan Pelanggaran</title>
    <!-- Pakai CDN Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Google Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        /* Style custom buat input tanggal biar ikonnya bisa di-klik */
        input[type="date"]::-webkit-calendar-picker-indicator {
            background: transparent;
            bottom: 0;
            color: transparent;
            cursor: pointer;
            height: auto;
            left: 0;
            position: absolute;
            right: 0;
            top: 0;
            width: auto;
        }
        /* Style custom buat panah dropdown */
        select {
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 1.2em;
            padding-right: 2.5rem; /* Kasih ruang buat panah */
        }
        
        /* ===== CSS FIX UNTUK PLACEHOLDER TANGGAL (REVISI FINAL) ===== */
        input[type="date"] {
            position: relative;
            color: transparent; 
        }
        input[type="date"]:valid,
        input[type="date"]:focus {
            color: #1f2937;
        }
        input[type="date"]::before {
            content: attr(placeholder);
            position: absolute;
            top: 50%;
            left: 2.75rem;
            transform: translateY(-50%);
            color: #9ca3af;
            pointer-events: none;
        }
        input[type="date"]:valid::before,
        input[type="date"]:focus::before {
            display: none;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-indigo-100 min-h-screen">

    <main class="p-4 sm:p-8">
        <div class="max-w-4xl mx-auto bg-white/90 backdrop-blur-sm p-6 sm:p-10 rounded-3xl shadow-2xl shadow-indigo-500/10 border border-gray-200">
            
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between mb-8 pb-4 border-b border-gray-200">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Export Laporan Lengkap</h1>
                    <p class="text-gray-600 mt-2">Pilih rentang tanggal & kamar untuk mengunduh laporan lengkap (3-in-1).</p>
                </div>
                <div class="text-indigo-500 mt-4 sm:mt-0 p-3 bg-indigo-100 rounded-full">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
            </div>

            <form action="process-export.php" method="POST" class="space-y-8">

                <!-- Filter Rentang Tanggal -->
                <div>
                    <label class="block text-base font-semibold text-gray-800 mb-3">Rentang Tanggal</label>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                               <svg class="w-5 h-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                            </div>
                            <input type="date" name="tanggal_mulai" placeholder="Tanggal Mulai" required class="pl-10 block w-full px-3 py-2.5 bg-gray-50 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                        </div>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                               <svg class="w-5 h-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                            </div>
                            <input type="date" name="tanggal_selesai" placeholder="Tanggal Selesai" required class="pl-10 block w-full px-3 py-2.5 bg-gray-50 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                        </div>
                    </div>
                </div>

                <!-- Filter Per Kamar -->
                <div>
                    <label for="kamar" class="block text-base font-semibold text-gray-800 mb-3">Filter Kamar</label>
                    <select id="kamar" name="kamar" class="block w-full px-3 py-2.5 bg-gray-50 border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm rounded-lg">
                        <option value="semua">Semua Kamar</option>
                        <?php foreach ($kamar_list as $kamar_item) : ?>
                            <option value="<?php echo htmlspecialchars($kamar_item); ?>"><?php echo htmlspecialchars($kamar_item); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Tombol Export -->
                <div class="pt-6 flex justify-end">
                    <button type="submit" name="export" class="inline-flex items-center justify-center px-8 py-3.5 border border-transparent text-base font-medium rounded-xl shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all duration-300 transform hover:scale-105">
                        <svg class="w-5 h-5 mr-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                        Unduh Laporan Lengkap
                    </button>
                </div>
            </form>
        </div>
    </main>

</body>
</html>

<?php require_once __DIR__ . '/../footer.php'; ?>