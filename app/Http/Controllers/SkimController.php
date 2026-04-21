<?php

namespace App\Http\Controllers;

use App\Models\ParsedSkimDocument;
use App\Services\SkimParserService;
use App\Exports\ParsedSkimDocumentsExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class SkimController extends Controller
{
    private SkimParserService $parser;

    public function __construct(SkimParserService $parser)
    {
        $this->parser = $parser;
    }

    /**
     * Show the SKIM dashboard
     */
    public function index(Request $request)
    {
        $query = ParsedSkimDocument::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('no_register', 'like', "%{$search}%")
                  ->orWhere('nomor_paspor', 'like', "%{$search}%")
                  ->orWhere('kewarganegaraan', 'like', "%{$search}%")
                  ->orWhere('niora', 'like', "%{$search}%")
                  ->orWhere('jenis_keimigrasian', 'like', "%{$search}%")
                  ->orWhere('file_name', 'like', "%{$search}%");
            });
        }

        $documents = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();
        $totalCount = ParsedSkimDocument::count();

        return view('skim-dashboard', compact('documents', 'totalCount'));
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
                    ParsedSkimDocument::create($record);
                }

                Storage::disk('local')->delete($path);

                $recordCount = count($records);
                $results['success'][] = "{$fileName} ({$recordCount} record)";
            } catch (\Exception $e) {
                $results['failed'][] = [
                    'file' => $fileName,
                    'error' => $e->getMessage(),
                ];
                \Log::error("SKIM Parse Error [{$fileName}]: " . $e->getMessage());
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

        return redirect()->route('skim.index')->with([
            'success_count' => $successCount,
            'failed_count'  => $failedCount,
            'message'       => trim($message),
            'failed_details' => $results['failed'],
        ]);
    }

    /**
     * Delete a document
     */
    public function destroy(ParsedSkimDocument $document)
    {
        $document->delete();
        return redirect()->route('skim.index')->with('deleted', 'Data berhasil dihapus.');
    }

    /**
     * Export to Excel
     */
    public function export()
    {
        $filename = 'Laporan-SKIM-' . now()->format('d-m-Y_H-i-s') . '.xlsx';
        return Excel::download(new ParsedSkimDocumentsExport(), $filename);
    }
}
