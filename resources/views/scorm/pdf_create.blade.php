<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Convert PDF to SCORM</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f4f6f9; }
        .card { background: white; padding: 25px; border-radius: 8px; max-width: 500px; margin: auto; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 15px; }
        label { font-weight: bold; display: block; margin-bottom: 5px; }
        input[type="text"], input[type="file"] { width: 100%; padding: 8px; box-sizing: border-box; }
        .btn { background: #28a745; color: white; border: none; padding: 10px 15px; border-radius: 4px; cursor: pointer; width: 100%; font-size: 16px; }
        a { text-decoration: none; color: #6c757d; font-size: 14px; }
    </style>
</head>
<body>
<div class="card">
    <a href="{{ route('dashboard') }}">&larr; Kembali ke Dashboard</a>
    <h2>Convert PDF to SCORM</h2>
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
    <form action="{{ route('scorm.pdf.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="form-group">
            <label>Judul Modul:</label>
            <input type="text" name="title" required placeholder="Misal: Panduan Operasional SOP">
        </div>

        <div class="form-group">
            <label>Upload File PDF:</label>
            <input type="file" name="pdf_file" accept="application/pdf" required>
        </div>

        <button type="submit" class="btn">Generate & Download ZIP</button>
    </form>
</div>
</body>
</html>