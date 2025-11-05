<?php
// File: rekap-mukholif/rapot/bulk_processor.php
// VERSI 2.0: Layout Responsif & Sentris

// Panggil 'Otak' aplikasi cuma buat panggil header
require_once __DIR__ . '/../init.php';
guard('rapot_cetak');

// Kita panggil header.php tapi GAK panggil footer.php
// biar bisa kita kontrol penuh halamannya
$page_title = "Prosesor Bulk Rapot";
$header_path = __DIR__ . '/../header.php';

// --- Magic buat ngambil CSS/JS dari header ---
ob_start();
require $header_path;
$header_content = ob_get_clean();

// Ekstrak cuma bagian <head> aja
preg_match('/<head>(.*?)<\/head>/s', $header_content, $head_matches);
$head_content = $head_matches[1] ?? '';
// --- Selesai Magic ---
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <?php echo $head_content; ?>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
    <style>
        /* Bikin body-nya abu-abu kayak di template admin */
        body {
            background-color: #f8f9fc;
        }
        /* Style buat page-wrapper rahasia */
        #render-wrapper {
            position: absolute; left: -9999px; top: 0;
            opacity: 0; height: 0; overflow: hidden;
        }
        .page-wrapper {
            width: 210mm; min-height: 297mm; background-color: white;
            padding: 7mm 10mm 4mm 10mm; box-sizing: border-box;
        }
    </style>
</head>
<body class="bg-light">

    <div class="container" style="padding-top: 5vh;"> <div class="row justify-content-center"> <div class="col-12 col-md-10 col-lg-8"> <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary" id="status-title">
                            <i class="fas fa-spinner fa-spin"></i> Sedang Mempersiapkan...
                        </h6>
                    </div>
                    <div class="card-body">
                        <p id="status-text">Mengambil daftar rapot dari session...</p>
                        
                        <div class="progress mb-2">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                 id="progress-bar" role="progressbar" 
                                 style="width: 0%" aria-valuenow="0" 
                                 aria-valuemin="0" aria-valuemax="100">0%</div>
                        </div>
                        
                        <ul class="list-group" id="log-list" style="max-height: 300px; overflow-y: auto;">
                            </ul>
                        
                        <div id="download-section" class="text-center mt-3" style="display: none;">
                            <h3 class="text-success">SELESAI!</h3>
                            <p>File ZIP sedang diunduh. Tab ini akan ditutup otomatis.</p>
                            <button id="download-btn" class="btn btn-lg btn-success">
                                <i class="fas fa-download"></i> Unduh ZIP (Jika tidak otomatis)
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
    <div id="render-wrapper">
        <div class="page-wrapper" id="render-target"></div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', async function() {
        
        // --- Ambil elemen UI ---
        const statusTitle = document.getElementById('status-title');
        const statusText = document.getElementById('status-text');
        const progressBar = document.getElementById('progress-bar');
        const logList = document.getElementById('log-list');
        const downloadSection = document.getElementById('download-section');
        const downloadBtn = document.getElementById('download-btn');
        const renderTarget = document.getElementById('render-target');

        // --- Ambil data dari URL & Session ---
        const params = new URLSearchParams(window.location.search);
        const type = params.get('type'); // 'pdf' or 'png'
        const list = JSON.parse(sessionStorage.getItem('bulkProcessList'));
        
        if (!list || list.length === 0) {
            statusTitle.innerHTML = '<i class="fas fa-times"></i> Error';
            statusText.innerText = 'Tidak ada data rapot ditemukan di session. Silakan kembali dan pilih rapot.';
            return;
        }

        const total = list.length;
        statusTitle.innerHTML = `<i class="fas fa-cogs"></i> Memproses ${total} Rapot (${type.toUpperCase()})...`;
        statusText.innerText = "Mohon jangan tutup tab ini.";
        addLog('info', `Ditemukan ${total} rapot. Memulai proses...`);

        const zip = new JSZip();
        let successCount = 0;

        // --- Helper buat nambah log ---
        function addLog(type, text) {
            const item = document.createElement('li');
            let icon = 'fas fa-info-circle';
            let color = 'text-primary';
            if (type === 'success') { icon = 'fas fa-check-circle'; color = 'text-success'; }
            if (type === 'error') { icon = 'fas fa-times-circle'; color = 'text-danger'; }
            
            item.className = `list-group-item list-group-item-light ${color}`;
            item.innerHTML = `<i class="${icon} fa-fw"></i> ${text}`;
            logList.prepend(item); // Taruh di paling atas
        }
        
        // --- Helper update progress bar ---
        function updateProgress(current) {
            const percentage = Math.round((current / total) * 100);
            progressBar.style.width = percentage + '%';
            progressBar.innerText = percentage + '%';
            progressBar.setAttribute('aria-valuenow', percentage);
        }

        // --- Fungsi Ajaib: Loop Async ---
        async function startProcessing() {
            for (let i = 0; i < list.length; i++) {
                const item = list[i];
                const filename = `${item.filename}.${type}`;
                
                statusText.innerText = `Memproses file ${i + 1} dari ${total}: ${filename}`;
                addLog('info', `Memulai ${filename}...`);

                try {
                    let blob;
                    if (type === 'pdf') {
                        // --- Logika Fetch PDF ---
                        const response = await fetch(`generate_pdf.php?id=${item.id}&output=string`);
                        if (!response.ok) throw new Error(`Server error ${response.status}`);
                        blob = await response.blob();
                        
                    } else {
                        // --- Logika Fetch & Render PNG ---
                        // 1. Ambil HTML mentahnya
                        const htmlResponse = await fetch(`generate_png.php?id=${item.id}&mode=html`);
                        if (!htmlResponse.ok) throw new Error(`Server error ${htmlResponse.status}`);
                        const html = await htmlResponse.text();
                        
                        // 2. Masukin ke div rahasia
                        renderTarget.innerHTML = html;
                        
                        // 3. Screenshot pake html2canvas
                        const canvas = await html2canvas(renderTarget, {
                            useCORS: true, width: renderTarget.scrollWidth,
                            height: renderTarget.scrollHeight, windowWidth: 1000
                        });
                        
                        // 4. Ubah jadi Blob
                        blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/png', 0.9));
                        renderTarget.innerHTML = ''; // Kosongin lagi
                    }
                    
                    // Masukin file ke ZIP
                    zip.file(filename, blob);
                    addLog('success', `Berhasil memproses ${filename}.`);
                    successCount++;
                    
                } catch (error) {
                    console.error('Gagal memproses item:', item, error);
                    addLog('error', `Gagal memproses ${filename}. Error: ${error.message}`);
                }
                
                updateProgress(i + 1);
            }
            
            // --- Selesai Looping ---
            finishProcessing();
        }
        
        // --- Fungsi Selesai ---
        function finishProcessing() {
            statusTitle.innerHTML = '<i class="fas fa-check-circle"></i> Proses Selesai!';
            statusText.innerText = `Berhasil memproses ${successCount} dari ${total} file.`;
            downloadSection.style.display = 'block';

            if (successCount === 0) {
                addLog('error', 'Tidak ada file yang berhasil diproses. Download dibatalkan.');
                return;
            }

            // Bikin file ZIP
            zip.generateAsync({ type: "blob" })
               .then(function(content) {
                    // Bikin link download palsu
                    const link = document.createElement('a');
                    link.href = URL.createObjectURL(content);
                    link.download = `rapot_${type}_${Date.now()}.zip`;
                    document.body.appendChild(link);
                    
                    // Klik & Hapus
                    link.click();
                    document.body.removeChild(link);
                    
                    // Kasih tombol buat manual download
                    downloadBtn.onclick = () => link.click();
                    
                    // Tutup tab otomatis
                    setTimeout(() => window.close(), 3000);
               });
        }
        
        // Mulai proses!
        startProcessing();
        
    });
    </script>

    </body>
</html>