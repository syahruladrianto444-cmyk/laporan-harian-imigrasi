<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\WordDocumentController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Authentication Routes
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Protected Routes (require login)
Route::middleware('auth')->group(function () {

    // Root redirect to PDF dashboard
    Route::get('/', function () {
        return redirect()->route('pdf.index');
    })->name('dashboard');

    // ===== PDF Feature (Laporan Izin Tinggal) =====
    Route::get('/pdf', [DocumentController::class, 'index'])->name('pdf.index');
    Route::post('/pdf/upload', [DocumentController::class, 'upload'])->name('pdf.upload');
    Route::delete('/pdf/documents/{document}', [DocumentController::class, 'destroy'])->name('pdf.destroy');
    Route::get('/pdf/export', [DocumentController::class, 'export'])->name('pdf.export');
    Route::post('/pdf/debug', [DocumentController::class, 'debugPdf'])->name('pdf.debug');

    // ===== Word Feature (Laporan ABK Ganda) =====
    Route::get('/word', [WordDocumentController::class, 'index'])->name('word.index');
    Route::post('/word/upload', [WordDocumentController::class, 'upload'])->name('word.upload');
    Route::delete('/word/documents/{document}', [WordDocumentController::class, 'destroy'])->name('word.destroy');
    Route::get('/word/export', [WordDocumentController::class, 'export'])->name('word.export');
});
