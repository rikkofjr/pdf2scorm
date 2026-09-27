<?php

namespace App\Services;

use App\Models\Package;
use Illuminate\Support\Facades\File;
use ZipArchive;

class ScormPdfService
{
    public function generateZip(Package $package): string
    {
        $packageDir = $this->prepareTempFolder($package);

        File::copy(storage_path("app/public/{$package->pdf_file}"), $packageDir . '/content/materi.pdf');
        File::copy(storage_path('app/scorm_template/js/scorm-api-wrapper.js'), $packageDir . '/js/scorm-api-wrapper.js');
        File::copy(storage_path('app/scorm_template/js/pdf.min.js'), $packageDir . '/js/pdf.min.js');
        File::copy(storage_path('app/scorm_template/js/pdf.worker.min.js'), $packageDir . '/js/pdf.worker.min.js');

        File::put($packageDir . '/imsmanifest.xml', $this->buildManifestXml($package));
        File::put($packageDir . '/index.html', $this->buildInteractiveHtml($package->title));

        return $this->zipAndDeleteTemp($packageDir, $package->id);
    }

    private function buildInteractiveHtml($title): string
    {
        $titleEscaped = htmlspecialchars($title);

        return <<<HTML
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$titleEscaped}</title>
    <script src="js/scorm-api-wrapper.js"></script>
    <script src="js/pdf.min.js"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', sans-serif; display: flex; flex-direction: column; height: 100vh; background: #f4f6f9; overflow: hidden; }
        header { background: #1e293b; color: white; padding: 12px 20px; display: flex; justify-content: space-between; align-items: center; z-index: 10; }
        .header-actions { display: flex; align-items: center; gap: 12px; }
        .status-badge { padding: 6px 12px; border-radius: 20px; font-size: 13px; font-weight: bold; background: #f59e0b; color: #fff; }
        .status-badge.completed { background: #10b981; }
        .btn-header { background: #334155; color: white; border: 1px solid #475569; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 13px; display: flex; align-items: center; gap: 6px; }
        .btn-header:hover { background: #475569; }
        
        /* 1. Main Container Kunci Layout */
        .main-container { display: flex; flex: 1; overflow: hidden; position: relative; }
        .sidebar { width: 260px; background: #ffffff; border-right: 1px solid #e2e8f0; display: flex; flex-direction: column; transition: margin-left 0.3s ease, opacity 0.3s ease; height: 100%; }
        .sidebar.collapsed { margin-left: -260px; opacity: 0; pointer-events: none; }
        .sidebar-header { padding: 15px; font-weight: bold; background: #f8fafc; border-bottom: 1px solid #e2e8f0; font-size: 14px; }
        .catalog-list { list-style: none; overflow-y: auto; flex: 1; }
        .catalog-item { padding: 12px 15px; border-bottom: 1px solid #f1f5f9; cursor: pointer; font-size: 13px; display: flex; justify-content: space-between; align-items: center; }
        .catalog-item:hover { background: #f1f5f9; }
        .catalog-item.active { background: #e0f2fe; color: #0284c7; font-weight: bold; }
        .icon-check { color: #10b981; display: none; }
        .catalog-item.visited .icon-check { display: inline; }

        /* 2. Content Area Aktifkan Scroll Mandiri & Align Top */
        .content-area { flex: 1; display: flex; justify-content: center; align-items: flex-start; padding: 20px; background: #cbd5e1; overflow: auto; height: 100%; }
        .slide-frame { background: white; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); border-radius: 8px; display: inline-block; padding: 10px; margin: 0 auto; }
        canvas { display: block; max-width: 100%; height: auto; }

        footer { background: white; border-top: 1px solid #e2e8f0; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; z-index: 10; }
        .btn { background: #2563eb; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: 600; }
        .btn:disabled { background: #94a3b8; cursor: not-allowed; }
        .page-indicator { font-weight: 600; color: #334155; font-size: 14px; }

        .modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); display: none; justify-content: center; align-items: center; z-index: 999; }
        .modal-content { background: white; padding: 30px; border-radius: 12px; text-align: center; max-width: 400px; }
        .modal-content h2 { color: #10b981; margin-bottom: 10px; }
    </style>
</head>
<body>
    <header>
        <div class="header-actions">
            <button class="btn-header" onclick="toggleCatalog()">
                <span>☰</span> <span id="catalog-btn-text">Sembunyikan Katalog</span>
            </button>
            <div id="module-title">{$titleEscaped}</div>
        </div>
        <div class="header-actions">
            <button class="btn-header" onclick="toggleFullscreen()">
                <span id="fullscreen-icon">⛶</span> <span id="fullscreen-text">Layar Penuh</span>
            </button>
            <div id="status-badge" class="status-badge">In Progress</div>
        </div>
    </header>

    <div class="main-container">
        <div class="sidebar" id="sidebar-catalog">
            <div class="sidebar-header">Daftar Slide / Katalog</div>
            <ul class="catalog-list" id="catalog-list"></ul>
        </div>
        <div class="content-area" id="content-area">
            <div class="slide-frame">
                <canvas id="pdf-render"></canvas>
            </div>
        </div>
    </div>

    <footer>
        <button class="btn" id="btn-prev" onclick="changeSlide(-1)">&laquo; Prev</button>
        <div class="page-indicator" id="page-indicator">Memuat Slide...</div>
        <button class="btn" id="btn-next" onclick="changeSlide(1)">Next &raquo;</button>
    </footer>

    <div class="modal-overlay" id="completion-modal">
        <div class="modal-content">
            <h2>🎉 Selamat!</h2>
            <p>Anda telah membaca seluruh materi. Silahkan kembali ke halaman utama, dan lakukan aktifitas selanjutnya</p>
            <button class="btn" onclick="closeModal()">Tutup & Selesai</button>
        </div>
    </div>

    <script>
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'js/pdf.worker.min.js';

        var pdfDoc = null,
            pageNum = 1,
            pageRendering = false,
            pageNumPending = null,
            scale = 1.3, /* Menjaga ukuran tetap besar agar terbaca jelas */
            canvas = document.getElementById('pdf-render'),
            ctx = canvas.getContext('2d'),
            visitedSlides = new Set([1]),
            isCompleted = false;

        window.onload = function() {
            if (typeof pipwerks !== 'undefined' && pipwerks.SCORM) {
                pipwerks.SCORM.init();
            }
            
            pdfjsLib.getDocument('content/materi.pdf').promise.then(function(pdfDoc_) {
                pdfDoc = pdfDoc_;
                renderCatalog();
                renderPage(pageNum);
            });
        };

        function renderPage(num) {
            pageRendering = true;
            pdfDoc.getPage(num).then(function(page) {
                var viewport = page.getViewport({scale: scale});
                canvas.height = viewport.height;
                canvas.width = viewport.width;

                var renderContext = {
                    canvasContext: ctx,
                    viewport: viewport
                };
                var renderTask = page.render(renderContext);

                renderTask.promise.then(function() {
                    pageRendering = false;
                    if (pageNumPending !== null) {
                        renderPage(pageNumPending);
                        pageNumPending = null;
                    }
                });
            });

            document.getElementById('page-indicator').textContent = 'Slide ' + num + ' dari ' + pdfDoc.numPages;
            
            /* 3. Reset scroll ke bagian atas setiap kali pindah slide */
            var contentArea = document.getElementById('content-area');
            if (contentArea) contentArea.scrollTop = 0;

            updateUI();
        }

        function renderCatalog() {
            var listHtml = '';
            for (var i = 1; i <= pdfDoc.numPages; i++) {
                var isVisited = visitedSlides.has(i) ? 'visited' : '';
                var isActive = (i === pageNum) ? 'active' : '';
                listHtml += '<li class="catalog-item ' + isActive + ' ' + isVisited + '" id="cat-item-' + i + '" onclick="goToSlide(' + i + ')">' +
                            '<span>Slide ' + i + '</span>' +
                            '<span class="icon-check">✅</span>' +
                            '</li>';
            }
            document.getElementById('catalog-list').innerHTML = listHtml;
        }

        function changeSlide(direction) {
            var target = pageNum + direction;
            if (target >= 1 && target <= pdfDoc.numPages) {
                goToSlide(target);
            }
        }

        function goToSlide(num) {
            pageNum = num;
            visitedSlides.add(num);

            if (pageRendering) {
                pageNumPending = num;
            } else {
                renderPage(num);
            }
            checkCompletion();
        }

        function updateUI() {
            document.getElementById('btn-prev').disabled = (pageNum === 1);
            if (pageNum === pdfDoc.numPages) {
                document.getElementById('btn-next').innerText = 'Selesai ✓';
            } else {
                document.getElementById('btn-next').innerText = 'Next »';
            }

            for (var i = 1; i <= pdfDoc.numPages; i++) {
                var item = document.getElementById('cat-item-' + i);
                if (item) {
                    item.classList.remove('active');
                    if (visitedSlides.has(i)) item.classList.add('visited');
                    if (i === pageNum) item.classList.add('active');
                }
            }
        }

        function checkCompletion() {
            if (visitedSlides.size === pdfDoc.numPages && !isCompleted) {
                isCompleted = true;

                if (typeof pipwerks !== 'undefined' && pipwerks.SCORM) {
                    pipwerks.SCORM.set("cmi.core.lesson_status", "completed");
                    pipwerks.SCORM.save();
                }

                var badge = document.getElementById('status-badge');
                badge.innerText = 'Completed ✓';
                badge.classList.add('completed');

                document.getElementById('completion-modal').style.display = 'flex';
            }
        }

        function toggleCatalog() {
            var sidebar = document.getElementById('sidebar-catalog');
            var btnText = document.getElementById('catalog-btn-text');
            sidebar.classList.toggle('collapsed');
            btnText.innerText = sidebar.classList.contains('collapsed') ? 'Tampilkan Katalog' : 'Sembunyikan Katalog';
        }

        function toggleFullscreen() {
            var doc = window.document;
            var docEl = doc.documentElement;
            var request = docEl.requestFullscreen || docEl.webkitRequestFullScreen || docEl.mozRequestFullScreen || docEl.msRequestFullscreen;
            var cancel = doc.exitFullscreen || doc.webkitExitFullscreen || doc.mozCancelFullScreen || doc.msExitFullscreen;

            if (!doc.fullscreenElement && !doc.webkitFullscreenElement && !doc.mozFullScreenElement && !doc.msFullscreenElement) {
                if (request) request.call(docEl);
            } else {
                if (cancel) cancel.call(doc);
            }
        }

        function closeModal() {
            document.getElementById('completion-modal').style.display = 'none';
            if (typeof pipwerks !== 'undefined' && pipwerks.SCORM) {
                pipwerks.SCORM.quit();
            }
        }
    </script>
</body>
</html>
HTML;
    }

    private function prepareTempFolder($package): string
    {
        $packageDir = storage_path("app/public/temp_scorm_{$package->id}");
        if (File::exists($packageDir)) File::deleteDirectory($packageDir);
        File::makeDirectory($packageDir . '/js', 0755, true);
        File::makeDirectory($packageDir . '/content', 0755, true);
        return $packageDir;
    }

    private function zipAndDeleteTemp($packageDir, $id): string
    {
        $zipPath = storage_path("app/public/scorm_packages/package_{$id}.zip");
        File::makeDirectory(dirname($zipPath), 0755, true, true);

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($packageDir), \RecursiveIteratorIterator::LEAVES_ONLY);
            foreach ($files as $file) {
                if (!$file->isDir()) {
                    $filePath = $file->getRealPath();
                    $relativePath = substr($filePath, strlen($packageDir) + 1);
                    $zip->addFile($filePath, $relativePath);
                }
            }
            $zip->close();
        }
        File::deleteDirectory($packageDir);
        return $zipPath;
    }

    private function buildManifestXml($package): string
    {
        $title = htmlspecialchars($package->title);
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<manifest identifier="MANIFEST_{$package->id}" version="1.0"
          xmlns="http://www.imsproject.org/xsd/imscp_rootv1p1p2"
          xmlns:adlcp="http://www.adlnet.org/xsd/adlcp_rootv1p2">
  <metadata>
    <schema>ADL SCORM</schema>
    <schemaversion>1.2</schemaversion>
  </metadata>
  <organizations default="ORG_1">
    <organization identifier="ORG_1">
      <title>{$title}</title>
      <item identifier="ITEM_1" identifierref="RES_1">
        <title>{$title}</title>
      </item>
    </organization>
  </organizations>
  <resources>
    <resource identifier="RES_1" type="webcontent" adlcp:scormtype="sco" href="index.html">
      <file href="index.html"/>
      <file href="js/scorm-api-wrapper.js"/>
      <file href="js/pdf.min.js"/>
      <file href="js/pdf.worker.min.js"/>
      <file href="content/materi.pdf"/>
    </resource>
  </resources>
</manifest>
XML;
    }
}