<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Services\ScormQuizService;
use Illuminate\Http\Request;

class ScormQuizController extends Controller
{
    public function create()
    {
        return view('scorm.quiz_create');
    }

    public function store(Request $request, ScormQuizService $generator)
    {
        // 1. Validasi Input
        $request->validate([
            'title' => 'required|string|max:255',
            'pdf_file' => 'required|mimes:pdf|max:30000',
            'passing_score' => 'required|integer|min:0|max:100',
            'quizzes' => 'required|array|min:1',
            'quizzes.*.question' => 'required|string',
            'quizzes.*.options' => 'required|array|min:2',
            'quizzes.*.correct' => 'required|integer',
        ]);

        // 2. Simpan File PDF
        $pdfPath = $request->file('pdf_file')->store('pdfs', 'public');

        // 3. Simpan Package
        $package = Package::create([
            'title' => $request->title,
            'pdf_file' => $pdfPath,
            'passing_score' => $request->passing_score,
            'has_quiz' => true,
        ]);

        // 4. Simpan Soal & Opsi (Menggunakan Relasi)
        foreach ($request->quizzes as $qData) {
            // Catatan: Pastikan relasi 'quizzes' ada di Model Package
            $quiz = $package->quizzes()->create([
                'question' => $qData['question'],
            ]);

            foreach ($qData['options'] as $optIndex => $optText) {
                // Catatan: Pastikan relasi 'options' ada di Model Quiz/Question
                $quiz->options()->create([
                    'option_text' => $optText,
                    'is_correct' => ((int)$qData['correct'] === (int)$optIndex),
                ]);
            }
        }

        // 5. Load Relasi
        $package->load('quizzes.options');

        // 6. Generate SCORM ZIP
        $zipPath = $generator->generateZip($package);

        // 7. Download
        return response()->download($zipPath);
    }
}