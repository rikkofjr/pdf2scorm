<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Services\ScormPdfService;
use Illuminate\Http\Request;

class ScormPdfController extends Controller
{
    public function create()
    {
        return view('scorm.pdf_create');
    }

    public function store(Request $request, ScormPdfService $generator)
    {
        $request->validate([
            'title' => 'required|string',
            'pdf_file' => 'required|mimes:pdf|max:30000',
        ]);

        $pdfPath = $request->file('pdf_file')->store('pdfs', 'public');

        $package = Package::create([
            'title' => $request->title,
            'pdf_file' => $pdfPath,
            'has_quiz' => false,
        ]);

        // Menggunakan generator interaktif
        $zipPath = $generator->generateZip($package);
        
        return response()->download($zipPath);
    }
}