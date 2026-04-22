<?php

namespace App\Http\Controllers;

use App\Models\ParsedWordDocument;
use App\Services\WordParserService;
use App\Exports\ParsedWordDocumentsExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class WordDocumentController extends Controller
{
    private WordParserService $wordParser;

    public function __construct(WordParserService $wordParser)
    {
        $this->wordParser = $wordParser;
    }

    /**
     * Show the Word documents dashboard
     */
    public function index(Request $request)
    {
        $query = ParsedWordDocument::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('nomor_paspor', 'like', "%{$search}%")
                  ->orWhere('kebangsaan', 'like', "%{$search}%")
                  ->orWhere('nomor_registrasi', 'like', "%{$search}%")
                  ->orWhere('kota_kabupaten', 'like', "%{$search}%")
                  ->orWhere('file_name', 'like', "%{$search}%");
            });
        }

        $documents = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();
        $totalCount = ParsedWordDocument::count();

        return view('word-dashboard', compact('documents', 'totalCount'));
    }

    /**
     * Handle Word document upload(s)
     */
    public function upload(Request $request)
    {
        $request->validate([
            'docs'   => 'required|array|min:1',
            'docs.*' => 'required|file|mimes:docx,doc|max:20480',
        ]);

        $results = ['success' => [], 'failed' => []];

        foreach ($request->file('docs') as $file) {
            $fileName = $file->getClientOriginalName();
            try {
                // Store to temp
                $path = $file->storeAs('temp_docs', $fileName, 'tmp');
                $fullPath = Storage::disk('tmp')->path($path);

                // Extract data (returns array of records, one per page)
                $records = $this->wordParser->extractFromFile($fullPath);

                // Save each record to database
                foreach ($records as $record) {
                    $record['file_name'] = $fileName;
                    ParsedWordDocument::create($record);
                }

                // Cleanup temp file
                Storage::disk('tmp')->delete($path);

                $pageCount = count($records);
                $results['success'][] = "{$fileName} ({$pageCount} halaman)";
            } catch (\Exception $e) {
                $results['failed'][] = [
                    'file' => $fileName,
                    'error' => $e->getMessage(),
                ];
                \Log::error("Word Parse Error [{$fileName}]: " . $e->getMessage());
            }
        }

        // Build response message
        $successCount = count($results['success']);
        $failedCount  = count($results['failed']);

        $message = '';
        if ($successCount > 0) {
            $message .= "{$successCount} file berhasil diproses. ";
        }
        if ($failedCount > 0) {
            $failedNames = implode(', ', array_column($results['failed'], 'file'));
            $message .= "{$failedCount} file gagal: {$failedNames}.";
        }

        return redirect()->route('word.index')->with([
            'success_count' => $successCount,
            'failed_count'  => $failedCount,
            'message'       => trim($message),
            'failed_details' => $results['failed'],
        ]);
    }

    /**
     * Delete a single document
     */
    public function destroy(ParsedWordDocument $document)
    {
        $document->delete();
        return redirect()->route('word.index')->with('deleted', 'Data berhasil dihapus.');
    }

    /**
     * Export all Word documents to Excel
     */
    public function export()
    {
        $filename = 'Laporan-ABK-Ganda-' . now()->format('d-m-Y_H-i-s') . '.xlsx';
        return Excel::download(new ParsedWordDocumentsExport(), $filename);
    }
}
