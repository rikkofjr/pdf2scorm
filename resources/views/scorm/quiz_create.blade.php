<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Convert PDF + Quiz to SCORM</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f4f6f9; }
        .card { background: white; padding: 25px; border-radius: 8px; max-width: 650px; margin: auto; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 15px; }
        label { font-weight: bold; display: block; margin-bottom: 5px; }
        input[type="text"], input[type="number"], input[type="file"], select, textarea { width: 100%; padding: 8px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; }
        .btn { background: #007bff; color: white; border: none; padding: 10px 15px; border-radius: 4px; cursor: pointer; width: 100%; font-size: 16px; font-weight: bold; }
        .btn:hover { background: #0056b3; }
        .btn-secondary { background: #6c757d; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 13px; margin-top: 5px; }
        .btn-danger { background: #dc3545; color: white; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer; font-size: 12px; }
        a { text-decoration: none; color: #6c757d; font-size: 14px; }

        /* Styling Item Soal Dinamis */
        .quiz-card-item { background: #f8fafc; border: 1px solid #e2e8f0; padding: 15px; border-radius: 6px; margin-bottom: 15px; position: relative; }
        .quiz-card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; border-bottom: 1px solid #e2e8f0; padding-bottom: 5px; }
        .option-row { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
        .option-row input[type="radio"] { width: auto; cursor: pointer; }
        .option-row input[type="text"] { flex: 1; }
    </style>
</head>
<body>
<div class="card">
    <a href="{{ route('dashboard') }}">&larr; Kembali ke Dashboard</a>
    <h2>Convert PDF + Quiz to SCORM</h2>
    @if ($errors->any())
        <div style="color: red; background: #fee; padding: 10px; margin-bottom: 10px;">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (session('error'))
        <div style="color: red; background: #fee; padding: 10px; margin-bottom: 10px;">
            {{ session('error') }}
        </div>
    @endif
    <form action="{{ route('scorm.quiz.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="form-group">
            <label>Judul Modul:</label>
            <input type="text" name="title" required placeholder="Misal: Modul & Evaluasi K3">
        </div>

        <div class="form-group">
            <label>Upload File PDF:</label>
            <input type="file" name="pdf_file" accept="application/pdf" required>
        </div>

        <div class="form-group">
            <label>Passing Grade / KKM (0 - 100):</label>
            <input type="number" name="passing_score" value="80" min="0" max="100" required>
        </div>

        <hr style="margin: 20px 0; border: 0; border-top: 1px solid #eee;">
        
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <h3 style="margin: 0;">Pertanyaan Kuis</h3>
            <button type="button" class="btn-secondary" onclick="addQuizBlock()">+ Tambah Soal Baru</button>
        </div>

        <!-- Container Tempat Soal-soal Digenereate oleh JS -->
        <div id="quiz-wrapper"></div>

        <button type="submit" class="btn" style="margin-top: 15px;">Generate & Download ZIP</button>
    </form>
</div>

<script>
let quizCount = 0;

// Fungsi untuk menambah blok Soal baru
function addQuizBlock() {
    const wrapper = document.getElementById('quiz-wrapper');
    const qIndex = quizCount;

    const quizHtml = `
        <div class="quiz-card-item" id="quiz-block-${qIndex}">
            <div class="quiz-card-header">
                <strong>Soal #${qIndex + 1}</strong>
                ${qIndex > 0 ? `<button type="button" class="btn-danger" onclick="removeQuizBlock(${qIndex})">Hapus Soal</button>` : ''}
            </div>

            <div class="form-group">
                <label>Pertanyaan:</label>
                <textarea name="quizzes[${qIndex}][question]" rows="2" placeholder="Masukkan pertanyaan..." required></textarea>
            </div>

            <div class="form-group">
                <label>Pilihan Jawaban (Pilih radio button untuk Kunci Jawaban):</label>
                <div id="options-container-${qIndex}">
                    <!-- Default Minimal 2 Opsi -->
                    <div class="option-row">
                        <input type="radio" name="quizzes[${qIndex}][correct]" value="0" title="Pilih sebagai kunci jawaban" required checked>
                        <input type="text" name="quizzes[${qIndex}][options][0]" placeholder="Opsi 1" required>
                    </div>
                    <div class="option-row">
                        <input type="radio" name="quizzes[${qIndex}][correct]" value="1" title="Pilih sebagai kunci jawaban" required>
                        <input type="text" name="quizzes[${qIndex}][options][1]" placeholder="Opsi 2" required>
                    </div>
                </div>
                
                <button type="button" class="btn-secondary" style="background:#28a745;" onclick="addOptionRow(${qIndex})">+ Tambah Opsi</button>
            </div>
        </div>
    `;

    wrapper.insertAdjacentHTML('beforeend', quizHtml);
    quizCount++;
}

// Fungsi untuk menambah baris Opsi Pilihan pada Soal tertentu
function addOptionRow(qIndex) {
    const container = document.getElementById(`options-container-${qIndex}`);
    const currentOptionsCount = container.querySelectorAll('.option-row').length;

    const optionHtml = `
        <div class="option-row">
            <input type="radio" name="quizzes[${qIndex}][correct]" value="${currentOptionsCount}" title="Pilih sebagai kunci jawaban" required>
            <input type="text" name="quizzes[${qIndex}][options][${currentOptionsCount}]" placeholder="Opsi ${currentOptionsCount + 1}" required>
            <button type="button" class="btn-danger" onclick="this.parentElement.remove()">✕</button>
        </div>
    `;

    container.insertAdjacentHTML('beforeend', optionHtml);
}

// Fungsi untuk menghapus 1 blok soal
function removeQuizBlock(qIndex) {
    const block = document.getElementById(`quiz-block-${qIndex}`);
    if (block) {
        block.remove();
    }
}

// Otomatis buat 1 soal pertama saat halaman pertama kali di-load
document.addEventListener('DOMContentLoaded', function() {
    addQuizBlock();
});
</script>
</body>
</html>