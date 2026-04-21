<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\WordDocumentController;
use App\Http\Controllers\AvidavitController;
use App\Http\Controllers\SkimController;
use App\Http\Controllers\AbgController;

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

    // ===== Avidavit Feature (Surat Keterangan) =====
    Route::get('/avidavit', [AvidavitController::class, 'index'])->name('avidavit.index');
    Route::post('/avidavit/upload', [AvidavitController::class, 'upload'])->name('avidavit.upload');
    Route::delete('/avidavit/documents/{document}', [AvidavitController::class, 'destroy'])->name('avidavit.destroy');
    Route::get('/avidavit/export', [AvidavitController::class, 'export'])->name('avidavit.export');

    // ===== SKIM Feature (Surat Keterangan Keimigrasian) =====
    Route::get('/skim', [SkimController::class, 'index'])->name('skim.index');
    Route::post('/skim/upload', [SkimController::class, 'upload'])->name('skim.upload');
    Route::delete('/skim/documents/{document}', [SkimController::class, 'destroy'])->name('skim.destroy');
    Route::get('/skim/export', [SkimController::class, 'export'])->name('skim.export');

    // ===== ABG Feature (Sertifikat Anak Berkewarganegaraan Ganda) =====
    Route::get('/abg', [AbgController::class, 'index'])->name('abg.index');
    Route::post('/abg/upload', [AbgController::class, 'upload'])->name('abg.upload');
    Route::delete('/abg/documents/{document}', [AbgController::class, 'destroy'])->name('abg.destroy');
    Route::get('/abg/export', [AbgController::class, 'export'])->name('abg.export');
});

// Temporary Route to Setup Database on Vercel
Route::get('/migrate-db', function () {
    try {
        // Run migrations and seeds
        // --force is required for production
        // migrate:fresh will recreate all tables
        Illuminate\Support\Facades\Artisan::call('migrate:fresh', [
            '--seed' => true,
            '--force' => true,
        ]);
        
        $output = Illuminate\Support\Facades\Artisan::output();
        
        return "<h1>Migration and Seeding Success!</h1><pre>$output</pre><br><a href='/login'>Go to Login</a>";
    } catch (\Exception $e) {
        return "<h1>Migration Failed</h1><pre>" . $e->getMessage() . "</pre>";
    }
});
