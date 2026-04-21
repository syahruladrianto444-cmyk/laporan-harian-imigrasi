<?php

namespace App\Http\Controllers;

use App\Models\ParsedAbgDocument;
use App\Services\AbgParserService;
use App\Exports\ParsedAbgDocumentsExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class AbgController extends Controller
{
    private AbgParserService $parser;

    public function __construct(AbgParserService $parser)
    {
        $this->parser = $parser;
    }

    /**
     * Show the ABG dashboard
     */
    public function index(Request $request)
    {
        $query = ParsedAbgDocument::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('no_register', 'like', "%{$search}%")
                  ->orWhere('nomor_paspor_asing', 'like', "%{$search}%")
                  ->orWhere('kewarganegaraan', 'like', "%{$search}%")
                  ->orWhere('nama_ayah', 'like', "%{$search}%")
                  ->orWhere('nama_ibu', 'like', "%{$search}%")
                  ->orWhere('file_name', 'like', "%{$search}%");
            });
        }

        $documents = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();
        $totalCount = ParsedAbgDocument::count();

        return view('abg-dashboard', compact('documents', 'totalCount'));
    }

    /**
     * Handle file upload (PDF or Word)
     */
    public function upload(Request $request)
    {
        $request->validate([
            'docs'   => 'required|array|min:1',
            'docs.*' => 'required|file|mimes:pdf,docx,doc|max:20480',
        ]);

        $results = ['success' => [], 'failed' => []];

        foreach ($request->file('docs') as $file) {
            $fileName = $file->getClientOriginalName();
            try {
                $path = $file->storeAs('temp_docs', $fileName, 'local');
                $fullPath = Storage::disk('local')->path($path);

                $records = $this->parser->extractFromFile($fullPath);

                foreach ($records as $record) {
                    $record['file_name'] = $fileName;
                    ParsedAbgDocument::create($record);
                }

                Storage::disk('local')->delete($path);

                $recordCount = count($records);
                $results['success'][] = "{$fileName} ({$recordCount} record)";
            } catch (\Exception $e) {
                $results['failed'][] = [
                    'file' => $fileName,
                    'error' => $e->getMessage(),
                ];
                \Log::error("ABG Parse Error [{$fileName}]: " . $e->getMessage());
            }
        }

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

        return redirect()->route('abg.index')->with([
            'success_count' => $successCount,
            'failed_count'  => $failedCount,
            'message'       => trim($message),
            'failed_details' => $results['failed'],
        ]);
    }

    /**
     * Delete a document
     */
    public function destroy(ParsedAbgDocument $document)
    {
        $document->delete();
        return redirect()->route('abg.index')->with('deleted', 'Data berhasil dihapus.');
    }

    /**
     * Export to Excel
     */
    public function export()
    {
        $filename = 'Laporan-ABG-' . now()->format('d-m-Y_H-i-s') . '.xlsx';
        return Excel::download(new ParsedAbgDocumentsExport(), $filename);
    }
}
