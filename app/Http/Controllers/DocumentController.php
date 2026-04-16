<?php

namespace App\Http\Controllers;

use App\Models\ParsedDocument;
use App\Services\PdfParserService;
use App\Exports\ParsedDocumentsExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class DocumentController extends Controller
{
    private PdfParserService $pdfParser;

    public function __construct(PdfParserService $pdfParser)
    {
        $this->pdfParser = $pdfParser;
    }

    /**
     * Show the dashboard with all parsed documents
     */
    public function index(Request $request)
    {
        $query = ParsedDocument::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('nomor_paspor', 'like', "%{$search}%")
                  ->orWhere('kebangsaan', 'like', "%{$search}%")
                  ->orWhere('tipe_dokumen', 'like', "%{$search}%")
                  ->orWhere('nomor_dokumen', 'like', "%{$search}%")
                  ->orWhere('penjamin', 'like', "%{$search}%")
                  ->orWhere('file_name', 'like', "%{$search}%");
            });
        }

        $documents = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();
        $totalCount = ParsedDocument::count();

        return view('dashboard', compact('documents', 'totalCount'));
    }

    /**
     * Handle single or multiple PDF uploads
     */
    public function upload(Request $request)
    {
        $request->validate([
            'pdfs'   => 'required|array|min:1',
            'pdfs.*' => 'required|file|mimes:pdf|max:20480',
        ]);

        $results = ['success' => [], 'failed' => []];

        foreach ($request->file('pdfs') as $file) {
            $fileName = $file->getClientOriginalName();
            try {
                // Store to temp
                $path = $file->storeAs('temp_pdfs', $fileName, 'local');
                $fullPath = Storage::disk('local')->path($path);

                // Extract data
                $data = $this->pdfParser->extractFromFile($fullPath);
                $data['file_name'] = $fileName;

                // Save to database
                ParsedDocument::create($data);

                // Cleanup temp file
                Storage::disk('local')->delete($path);

                $results['success'][] = $fileName;
            } catch (\Exception $e) {
                $results['failed'][] = [
                    'file' => $fileName,
                    'error' => $e->getMessage(),
                ];
                \Log::error("PDF Parse Error [{$fileName}]: " . $e->getMessage());
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

        return redirect()->route('pdf.index')->with([
            'success_count' => $successCount,
            'failed_count'  => $failedCount,
            'message'       => trim($message),
            'failed_details' => $results['failed'],
        ]);
    }

    /**
     * Delete a single document
     */
    public function destroy(ParsedDocument $document)
    {
        $document->delete();
        return redirect()->route('pdf.index')->with('deleted', 'Data berhasil dihapus.');
    }

    /**
     * Export all documents to Excel
     */
    public function export()
    {
        $filename = 'Laporan-Imigrasi-' . now()->format('d-m-Y_H-i-s') . '.xlsx';
        return Excel::download(new ParsedDocumentsExport(), $filename);
    }

    /**
     * Debug: show raw PDF text extraction + parsed result
     */
    public function debugPdf(Request $request)
    {
        $request->validate([
            'pdf' => 'required|file|mimes:pdf|max:20480',
        ]);

        $file = $request->file('pdf');
        $path = $file->storeAs('temp_pdfs', $file->getClientOriginalName(), 'local');
        $fullPath = Storage::disk('local')->path($path);

        // Get raw text
        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($fullPath);
        $rawText = $pdf->getText();

        // Get parsed data
        $data = $this->pdfParser->extractFromFile($fullPath);

        // Cleanup
        Storage::disk('local')->delete($path);

        return response()->json([
            'file_name' => $file->getClientOriginalName(),
            'raw_text' => $rawText,
            'parsed_data' => $data,
        ], 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
