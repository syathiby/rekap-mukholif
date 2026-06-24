<?php
// File: rekap-mukholif/rapot/crud_bulanan/bulk_processor.php
// VERSI 2.0: Layout Responsif & Sentris
// (dipindah dari rapot/crud/bulk_processor.php — fetch path diupdate)

require_once __DIR__ . '/../../bootstrap/init.php';
guard('rapot_cetak');

$page_title  = "Prosesor Bulk Rapot";
$header_path = __DIR__ . '/../../layouts/header.php';

ob_start();
require $header_path;
$header_content = ob_get_clean();

preg_match('/<head>(.*?)<\/head>/s', $header_content, $head_matches);
$head_content = $head_matches[1] ?? '';
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
        body { background-color: #f8f9fc; }
        #render-wrapper { position: absolute; left: -9999px; top: 0; opacity: 0; height: 0; overflow: hidden; }
        .page-wrapper { width: 210mm; min-height: 297mm; background-color: white; padding: 7mm 10mm 4mm 10mm; box-sizing: border-box; }
    </style>
</head>
<body class="bg-light">
    <div class="container" style="padding-top: 5vh;">
        <div class="row justify-content-center">
            <div class="col-12 col-md-10 col-lg-8">
                <div class="card shadow mb-4">
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
                        <ul class="list-group" id="log-list" style="max-height: 300px; overflow-y: auto;"></ul>
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
    document.addEventListener('DOMContentLoaded', async function () {
        const statusTitle   = document.getElementById('status-title');
        const statusText    = document.getElementById('status-text');
        const progressBar   = document.getElementById('progress-bar');
        const logList       = document.getElementById('log-list');
        const downloadSection = document.getElementById('download-section');
        const downloadBtn   = document.getElementById('download-btn');
        const renderTarget  = document.getElementById('render-target');

        const params = new URLSearchParams(window.location.search);
        const type   = params.get('type');
        const list   = JSON.parse(sessionStorage.getItem('bulkProcessList'));

        if (!list || list.length === 0) {
            statusTitle.innerHTML = '<i class="fas fa-times"></i> Error';
            statusText.innerText  = 'Tidak ada data rapot ditemukan di session.';
            return;
        }

        const total = list.length;
        statusTitle.innerHTML = `<i class="fas fa-cogs"></i> Memproses ${total} Rapot (${type.toUpperCase()})...`;
        statusText.innerText  = 'Mohon jangan tutup tab ini.';
        addLog('info', `Ditemukan ${total} rapot. Memulai proses...`);

        const zip = new JSZip();
        let successCount = 0;

        function addLog(type, text) {
            const item = document.createElement('li');
            let icon = 'fas fa-info-circle', color = 'text-primary';
            if (type === 'success') { icon = 'fas fa-check-circle'; color = 'text-success'; }
            if (type === 'error')   { icon = 'fas fa-times-circle'; color = 'text-danger'; }
            item.className = `list-group-item list-group-item-light ${color}`;
            item.innerHTML = `<i class="${icon} fa-fw"></i> ${text}`;
            logList.prepend(item);
        }

        function updateProgress(current) {
            const pct = Math.round((current / total) * 100);
            progressBar.style.width = pct + '%';
            progressBar.innerText   = pct + '%';
            progressBar.setAttribute('aria-valuenow', pct);
        }

        async function startProcessing() {
            for (let i = 0; i < list.length; i++) {
                const item     = list[i];
                const filename = `${item.filename}.${type}`;
                statusText.innerText = `Memproses file ${i + 1} dari ${total}: ${filename}`;
                addLog('info', `Memulai ${filename}...`);

                try {
                    let blob;
                    if (type === 'pdf') {
                        // Cek jenis rapor (tahunan atau bulanan)
                        const jenisParams = new URLSearchParams(window.location.search);
                        const jenis = jenisParams.get('jenis');
                        let pdfUrl = `../../rapot/export/generate_pdf.php?id=${item.id}&output=string`;
                        if (jenis === 'tahunan') {
                            pdfUrl = `../../rapot/export/generate_pdf_tahunan.php?id=${item.id}&output=string`;
                        }
                        
                        const response = await fetch(pdfUrl);
                        if (!response.ok) throw new Error(`Server error ${response.status}`);
                        blob = await response.blob();
                    } else {
                        const htmlResponse = await fetch(`../../rapot/export/generate_png.php?id=${item.id}&mode=html`);
                        if (!htmlResponse.ok) throw new Error(`Server error ${htmlResponse.status}`);
                        const html = await htmlResponse.text();
                        renderTarget.innerHTML = html;
                        const canvas = await html2canvas(renderTarget, {
                            useCORS: true, width: renderTarget.scrollWidth,
                            height: renderTarget.scrollHeight, windowWidth: 1000
                        });
                        blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/png', 0.9));
                        renderTarget.innerHTML = '';
                    }
                    zip.file(filename, blob);
                    addLog('success', `Berhasil memproses ${filename}.`);
                    successCount++;
                } catch (error) {
                    addLog('error', `Gagal memproses ${filename}. Error: ${error.message}`);
                }

                updateProgress(i + 1);
            }
            finishProcessing();
        }

        function finishProcessing() {
            statusTitle.innerHTML = '<i class="fas fa-check-circle"></i> Proses Selesai!';
            statusText.innerText  = `Berhasil memproses ${successCount} dari ${total} file.`;
            downloadSection.style.display = 'block';
            if (successCount === 0) {
                addLog('error', 'Tidak ada file yang berhasil diproses.');
                return;
            }
            zip.generateAsync({ type: 'blob' }).then(function (content) {
                const link = document.createElement('a');
                link.href     = URL.createObjectURL(content);
                const zipFilename = params.get('zipName') ? decodeURIComponent(params.get('zipName')) + '.zip' : `Rapot Bulk ${type.toUpperCase()}.zip`;
                link.download = zipFilename;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                downloadBtn.onclick = () => link.click();
                setTimeout(() => window.close(), 3000);
            });
        }

        startProcessing();
    });
    </script>
</body>
</html>
