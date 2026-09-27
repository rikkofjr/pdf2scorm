<?php

namespace App\Services;

use App\Models\Package;
use Illuminate\Support\Facades\File;
use ZipArchive;

class ScormQuizService
{
    public function generateZip(Package $package): string
    {
        $packageDir = $this->prepareTempFolder($package);

        File::copy(storage_path("app/public/{$package->pdf_file}"), $packageDir . '/content/materi.pdf');
        
        if (File::exists(storage_path('app/scorm_template/js/scorm-api-wrapper.js'))) {
            File::copy(storage_path('app/scorm_template/js/scorm-api-wrapper.js'), $packageDir . '/js/scorm-api-wrapper.js');
        }
        if (File::exists(storage_path('app/scorm_template/js/pdf.min.js'))) {
            File::copy(storage_path('app/scorm_template/js/pdf.min.js'), $packageDir . '/js/pdf.min.js');
        }
        if (File::exists(storage_path('app/scorm_template/js/pdf.worker.min.js'))) {
            File::copy(storage_path('app/scorm_template/js/pdf.worker.min.js'), $packageDir . '/js/pdf.worker.min.js');
        }

        File::put($packageDir . '/imsmanifest.xml', $this->buildManifestXml($package));

        // Format data kuis dari relasi $package->quizzes
        $quizData = $package->quizzes->map(function ($questionItem) {
            $correctIndex = 0;
            $optionsArr = [];
            
            foreach ($questionItem->options->values() as $idx => $opt) {
                $optionsArr[] = $opt->option_text;
                if ($opt->is_correct) {
                    $correctIndex = $idx;
                }
            }

            return [
                'question' => $questionItem->question,
                'options'  => $optionsArr,
                'answer'   => $correctIndex
            ];
        })->toArray();

        File::put($packageDir . '/index.html', $this->buildQuizHtml($package->title, json_encode($quizData)));

        return $this->zipAndDeleteTemp($packageDir, $package->id);
    }

    private function buildQuizHtml($title, $quizJson): string
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
        body { font-family: 'Segoe UI', sans-serif; display: flex; flex-direction: column; height: 100vh; background: #f4f6f9; }
        header { background: #1e293b; color: white; padding: 12px 20px; display: flex; justify-content: space-between; align-items: center; }
        .header-actions { display: flex; align-items: center; gap: 12px; }
        .status-badge { padding: 6px 12px; border-radius: 20px; font-size: 13px; font-weight: bold; background: #f59e0b; color: #fff; }
        .status-badge.completed { background: #10b981; }
        .btn-header { background: #334155; color: white; border: 1px solid #475569; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 13px; display: flex; align-items: center; gap: 6px; }
        .btn-header:hover { background: #475569; }

        .main-container { display: flex; flex: 1; height:calc(100vh - 60px - 60px); overflow: hidden; position: relative; }
        .sidebar { width: 260px; background: #ffffff; border-right: 1px solid #e2e8f0; display: flex; flex-direction: column; transition: margin-left 0.3s ease, opacity 0.3s ease; }
        .sidebar.collapsed { margin-left: -260px; opacity: 0; pointer-events: none; }
        .sidebar-header { padding: 15px; font-weight: bold; background: #f8fafc; border-bottom: 1px solid #e2e8f0; font-size: 14px; }
        .catalog-list { list-style: none; overflow-y: auto; flex: 1; }
        .catalog-item { padding: 12px 15px; border-bottom: 1px solid #f1f5f9; cursor: pointer; font-size: 13px; display: flex; justify-content: space-between; align-items: center; }
        .catalog-item:hover { background: #f1f5f9; }
        .catalog-item.active { background: #e0f2fe; color: #0284c7; font-weight: bold; }
        .icon-check { color: #10b981; display: none; }
        .catalog-item.visited .icon-check { display: inline; }

        .content-area { flex: 1; display: flex; justify-content: center; align-items: flex-start; padding: 20px; background: #cbd5e1; overflow: auto; height: 100%; }
        .slide-frame { background: white; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); border-radius: 8px; display: inline-block; padding: 10px; margin: 0 auto; }
        canvas { display: block; max-width: 100%; height: auto; }

        .quiz-card { background: white; padding: 30px; border-radius: 12px; width: 100%; max-width: 600px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); display: none; }
        .quiz-question { font-size: 18px; font-weight: bold; margin-bottom: 20px; color: #1e293b; }
        .quiz-option { display: block; background: #f8fafc; border: 2px solid #e2e8f0; padding: 12px 16px; border-radius: 8px; margin-bottom: 10px; cursor: pointer; transition: all 0.2s; }
        .quiz-option:hover { background: #f1f5f9; border-color: #cbd5e1; }
        .quiz-option input { margin-right: 10px; }

        /* Styling Modal */
        .modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); display: flex; justify-content: center; align-items: center; z-index: 9999; backdrop-filter: blur(2px); }
        .modal-card { background: #ffffff; width: 90%; max-width: 400px; border-radius: 12px; padding: 24px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); text-align: center; animation: fadeIn 0.3s ease-in-out; }
        .modal-card h3 { margin: 0 0 12px 0; color: #333; }
        .modal-body { margin: 15px 0; font-size: 15px; color: #555; text-align: center; }
        .btn-modal-close { background-color: #007bff; color: white; border: none; padding: 10px 24px; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: bold; }

        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.9); }
            to { opacity: 1; transform: scale(1); }
        }

        footer { background: white; border-top: 1px solid #e2e8f0; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; }
        .btn { background: #2563eb; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: 600; }
        .btn:disabled { background: #94a3b8; cursor: not-allowed; }
        .page-indicator { font-weight: 600; color: #334155; font-size: 14px; }
    </style>
</head>
<body>
    <header>
        <div class="header-actions">
            <button class="btn-header" onclick="toggleCatalog()">
                <span>☰</span> <span id="catalog-btn-text">Sembunyikan Katalog</span>
            </button>
            <div>{$titleEscaped}</div>
        </div>
        <div class="header-actions">
            <button class="btn-header" onclick="toggleFullscreen()">
                <span id="fullscreen-icon">⛶</span> <span>Layar Penuh</span>
            </button>
            <div id="status-badge" class="status-badge">In Progress</div>
        </div>
    </header>

    <div class="main-container">
        <div class="sidebar" id="sidebar-catalog">
            <div class="sidebar-header">Navigasi Modul</div>
            <ul class="catalog-list" id="catalog-list"></ul>
        </div>
        <div class="content-area">
            <div class="slide-frame" id="pdf-container">
                <canvas id="pdf-render"></canvas>
            </div>
            
            <div class="quiz-card" id="quiz-container">
                <div class="quiz-question" id="quiz-question-text">Pertanyaan...</div>
                <div id="quiz-options-list"></div>
                <button class="btn" style="margin-top: 15px;" onclick="submitAnswer()">Jawab & Lanjut</button>
            </div>
        </div>
    </div>

    <footer>
        <button class="btn" id="btn-prev" onclick="changeSlide(-1)">&laquo; Prev</button>
        <div class="page-indicator" id="page-indicator">Memuat...</div>
        <button class="btn" id="btn-next" onclick="changeSlide(1)">Next &raquo;</button>
    </footer>

    <!-- Modal Jawaban Salah / Peringatan -->
    <div id="modal-wrong" class="modal-overlay" style="display: none;">
        <div class="modal-card">
            <h3 style="color: #dc3545;">Perhatian</h3>
            <div class="modal-body">
                <p id="modal-wrong-text">Silakan pilih jawaban yang tepat.</p>
            </div>
            <button onclick="closeWrongModal()" class="btn-modal-close" style="background-color: #dc3545;">Coba Lagi</button>
        </div>
    </div>

    <!-- Modal Complete -->
    <div id="modal-complete" class="modal-overlay" style="display: none;">
        <div class="modal-card">
            <h3 style="color: #10b981;">🎉 Modul Selesai!</h3>
            <div class="modal-body">
                <p>Selamat, Anda telah membaca materi dan menyelesaikan seluruh soal kuis dengan benar.</p>
            </div>
            <button onclick="closeCompleteModal()" class="btn-modal-close" style="background-color: #10b981;">Tutup & Selesai</button>
        </div>
    </div>

    <script>
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'js/pdf.worker.min.js';

        var quizzes = {$quizJson};
        var pdfDoc = null, pageNum = 1, totalPdfPages = 0;
        var canvas = document.getElementById('pdf-render'), ctx = canvas.getContext('2d');
        var currentQuizIndex = -1;
        var visitedSlides = new Set([1]);

        window.onload = function() {
            if (typeof pipwerks !== 'undefined' && pipwerks.SCORM) {
                pipwerks.SCORM.init();
            }
            
            pdfjsLib.getDocument('content/materi.pdf').promise.then(function(pdfDoc_) {
                pdfDoc = pdfDoc_;
                totalPdfPages = pdfDoc.numPages;
                renderCatalog();
                renderPage(1);
            });
        };

        function renderPage(num) {
            document.getElementById('pdf-container').style.display = 'inline-block';
            document.getElementById('quiz-container').style.display = 'none';

            pdfDoc.getPage(num).then(function(page) {
                // Menggunakan skala tetap agar tulisan tidak mengecil di layar HP/Mobile
                var viewport = page.getViewport({ scale: 1.3 }); 
                
                canvas.height = viewport.height;
                canvas.width = viewport.width;

                page.render({ canvasContext: ctx, viewport: viewport });
            });

            document.getElementById('page-indicator').textContent = 'Slide ' + num + ' dari ' + totalPdfPages;
            
            // Reset posisi scroll ke paling atas setiap kali pindah slide
            document.querySelector('.content-area').scrollTop = 0;
            
            updateUI();
        }

        function renderCatalog() {
            var html = '';
            for (var i = 1; i <= totalPdfPages; i++) {
                var isVisited = visitedSlides.has(i) ? 'visited' : '';
                html += '<li class="catalog-item ' + isVisited + '" id="cat-' + i + '" onclick="goToSlide(' + i + ')">' +
                    '<span>Slide ' + i + '</span>' +
                    '<span class="icon-check">✅</span>' +
                    '</li>';
            }
            if(quizzes.length > 0) {
                html += '<li class="catalog-item" id="cat-quiz" onclick="startQuiz()">' +
                    '<span>Kuis Evaluasi (' + quizzes.length + ' Soal)</span>' +
                    '<span class="icon-check">✅</span>' +
                    '</li>';
            }
            document.getElementById('catalog-list').innerHTML = html;
        }

        function changeSlide(dir) {
            if (currentQuizIndex === -1) {
                var target = pageNum + dir;
                if (target >= 1 && target <= totalPdfPages) {
                    goToSlide(target);
                } else if (target > totalPdfPages && quizzes.length > 0) {
                    startQuiz();
                }
            } else {
                var nextQuiz = currentQuizIndex + dir;
                if (nextQuiz >= 0 && nextQuiz < quizzes.length) {
                    renderQuizQuestion(nextQuiz);
                } else if (nextQuiz < 0) {
                    goToSlide(totalPdfPages);
                }
            }
        }

        function goToSlide(num) {
            currentQuizIndex = -1;
            pageNum = num;
            visitedSlides.add(num);
            renderPage(num);
        }

        function startQuiz() {
            currentQuizIndex = 0;
            renderQuizQuestion(0);
        }

        function renderQuizQuestion(index) {
            currentQuizIndex = index;
            document.getElementById('pdf-container').style.display = 'none';
            document.getElementById('quiz-container').style.display = 'block';

            var q = quizzes[index];
            document.getElementById('quiz-question-text').textContent = 'Soal ' + (index + 1) + ' dari ' + quizzes.length + ': ' + q.question;

            var optsHtml = '';
            q.options.forEach(function(optText, optIdx) {
                optsHtml += '<label class="quiz-option">' +
                    '<input type="radio" name="quiz_opt" value="' + optIdx + '"> ' +
                    optText +
                    '</label>';
            });

            document.getElementById('quiz-options-list').innerHTML = optsHtml;
            document.getElementById('page-indicator').textContent = 'Kuis Soal ' + (index + 1) + ' / ' + quizzes.length;
            updateUI();
        }

        function submitAnswer() {
            var selected = document.querySelector('input[name="quiz_opt"]:checked');
            if (!selected) {
                showWrongModal("Pilih salah satu jawaban terlebih dahulu!");
                return;
            }

            var userAnswer = parseInt(selected.value);
            var correctAnswer = quizzes[currentQuizIndex].answer;

            if (userAnswer === correctAnswer) {
                if (currentQuizIndex < quizzes.length - 1) {
                    currentQuizIndex++;
                    renderQuizQuestion(currentQuizIndex);
                } else {
                    finishModule();
                }
            } else {
                showWrongModal("Jawaban Anda belum tepat. Silakan pilih kembali jawaban yang benar.");
            }
        }

        function finishModule() {
            // Tandai Katalog Kuis Selesai
            var quizCat = document.getElementById('cat-quiz');
            if (quizCat) quizCat.classList.add('visited');

            if (typeof pipwerks !== 'undefined' && pipwerks.SCORM) {
                pipwerks.SCORM.set("cmi.core.lesson_status", "completed");
                pipwerks.SCORM.save();
            }

            var badge = document.getElementById('status-badge');
            if (badge) {
                badge.innerText = 'Completed ✓';
                badge.className = 'status-badge completed';
            }

            document.getElementById('modal-complete').style.display = 'flex';
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

        function updateUI() {
            document.getElementById('btn-prev').disabled = (pageNum === 1 && currentQuizIndex === -1);
            
            var items = document.querySelectorAll('.catalog-item');
            items.forEach(function(el) { el.classList.remove('active'); });

            if(currentQuizIndex === -1) {
                var activeCat = document.getElementById('cat-' + pageNum);
                if(activeCat) {
                    activeCat.classList.add('active');
                    activeCat.classList.add('visited');
                }
            } else {
                var quizCat = document.getElementById('cat-quiz');
                if(quizCat) quizCat.classList.add('active');
            }
        }

        function showWrongModal(message) {
            document.getElementById('modal-wrong-text').innerText = message;
            document.getElementById('modal-wrong').style.display = 'flex';
        }

        function closeWrongModal() {
            document.getElementById('modal-wrong').style.display = 'none';
        }

        function closeCompleteModal() {
            document.getElementById('modal-complete').style.display = 'none';
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