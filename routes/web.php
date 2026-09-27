<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ScormPdfController;
use App\Http\Controllers\ScormQuizController;

// Halaman Utama Menu
Route::get('/', function () {
    return view('dashboard');
})->name('dashboard');

// Menu 1: PDF Only
Route::prefix('scorm-pdf')->group(function () {
    Route::get('/create', [ScormPdfController::class, 'create'])->name('scorm.pdf.create');
    Route::post('/store', [ScormPdfController::class, 'store'])->name('scorm.pdf.store');
});

// Menu 2: PDF + Quiz
Route::prefix('scorm-quiz')->group(function () {
    Route::get('/create', [ScormQuizController::class, 'create'])->name('scorm.quiz.create');
    Route::post('/store', [ScormQuizController::class, 'store'])->name('scorm.quiz.store');
});