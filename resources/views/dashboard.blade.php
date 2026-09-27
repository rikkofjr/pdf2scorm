<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>SCORM Generator Center</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f6f9; padding: 50px; }
        .container { max-width: 800px; margin: auto; text-align: center; }
        .grid { display: flex; gap: 20px; margin-top: 30px; }
        .card { flex: 1; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .btn { display: inline-block; padding: 12px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; font-weight: bold; margin-top: 15px; }
        .btn-green { background: #28a745; }
    </style>
</head>
<body>
<div class="container">
    <h2>Layanan Konversi SCORM 1.2 Moodle</h2>
    <p>Silakan pilih jenis modul SCORM yang ingin Anda buat:</p>

    <div class="grid">
        <div class="card">
            <h3>1. Convert PDF to SCORM</h3>
            <p>Hanya mengonversi PDF statis. Status Moodle akan otomatis "Completed" setelah dibaca.</p>
            <a href="{{ route('scorm.pdf.create') }}" class="btn btn-green">Pilih Layanan PDF</a>
        </div>

        <div class="card">
            <h3>2. Convert PDF + Quiz</h3>
            <p>Mengonversi PDF + Kuis evaluasi. Nilai kuis otomatis tercatat di Gradebook Moodle.</p>
            <a href="{{ route('scorm.quiz.create') }}" class="btn">Pilih Layanan Kuis</a>
        </div>
    </div>
</div>
</body>
</html>