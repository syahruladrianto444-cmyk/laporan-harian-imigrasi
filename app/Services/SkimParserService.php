<?php

namespace App\Services;

use Smalot\PdfParser\Parser;
use PhpOffice\PhpWord\IOFactory;
use Illuminate\Support\Facades\Log;

class SkimParserService
{
    private DataTransformerService $transformer;

    public function __construct(DataTransformerService $transformer)
    {
        $this->transformer = $transformer;
    }

    /**
     * Extract data from a file (PDF or Word)
     */
    public function extractFromFile(string $filePath): array
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if ($extension === 'pdf') {
            return $this->extractFromPdf($filePath);
        }

        return $this->extractFromWord($filePath);
    }

    /**
     * Extract from PDF - tries multiple methods
     */
    private function extractFromPdf(string $filePath): array
    {
        $parser = new Parser();
        $pdf = $parser->parseFile($filePath);

        // Method 1: Standard getText()
        $text = $pdf->getText();
        Log::info('SKIM PDF getText()', ['file' => basename($filePath), 'length' => strlen($text), 'text' => $text]);

        // Method 2: If getText() is empty, try getDataTm() which reads text with positioning
        if (strlen(trim($text)) < 10) {
            Log::info('SKIM: getText() empty, trying getDataTm()...');
            $text = $this->extractTextUsingDataTm($pdf);
            Log::info('SKIM PDF getDataTm()', ['file' => basename($filePath), 'length' => strlen($text), 'text' => $text]);
        }

        // Method 3: If still empty, try extracting text from each page's getTextArray()
        if (strlen(trim($text)) < 10) {
            Log::info('SKIM: getDataTm() empty, trying getTextArray()...');
            $text = $this->extractTextUsingTextArray($pdf);
            Log::info('SKIM PDF getTextArray()', ['file' => basename($filePath), 'length' => strlen($text), 'text' => $text]);
        }

        // Method 4: If still empty, try reading PDF raw stream content
        if (strlen(trim($text)) < 10) {
            Log::info('SKIM: getTextArray() empty, trying raw stream extraction...');
            $text = $this->extractTextFromPdfStream($filePath);
            Log::info('SKIM PDF stream', ['file' => basename($filePath), 'length' => strlen($text), 'text' => $text]);
        }

        // Method 5: If still empty, try pdftotext command if available
        if (strlen(trim($text)) < 10) {
            Log::info('SKIM: raw stream empty, trying pdftotext command...');
            $text = $this->extractTextUsingPdftotext($filePath);
            Log::info('SKIM PDF pdftotext', ['file' => basename($filePath), 'length' => strlen($text), 'text' => $text]);
        }

        if (strlen(trim($text)) < 10) {
            Log::warning('SKIM: ALL text extraction methods failed', ['file' => basename($filePath)]);
        }

        // Try parsing
        $records = [];
        $record = $this->parseText($text);
        if ($record['nama'] || $record['no_register']) {
            $records[] = $record;
        }

        // If still empty, return empty record
        if (empty($records)) {
            $records[] = $record;
        }

        return $records;
    }

    /**
     * Extract text using getDataTm() — reads text with coordinate positioning
     * This often works when getText() fails due to custom font encoding
     */
    private function extractTextUsingDataTm($pdf): string
    {
        $allItems = [];

        try {
            $pages = $pdf->getPages();
            foreach ($pages as $page) {
                $dataTm = $page->getDataTm();
                if (!empty($dataTm)) {
                    foreach ($dataTm as $item) {
                        $coords = $item[0] ?? [];
                        $txt = $item[1] ?? '';
                        $x = floatval($coords[4] ?? 0);
                        $y = floatval($coords[5] ?? 0);
                        if (strlen(trim($txt)) > 0) {
                            $allItems[] = [
                                'x' => $x,
                                'y' => $y,
                                'text' => $txt,
                            ];
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning('SKIM getDataTm error', ['error' => $e->getMessage()]);
            return '';
        }

        if (empty($allItems)) {
            return '';
        }

        // Sort by Y (descending = top to bottom) then X (ascending = left to right)
        usort($allItems, function ($a, $b) {
            // Group by Y with tolerance of 5 units (same line)
            $yDiff = round($b['y'] / 5) - round($a['y'] / 5);
            if ($yDiff !== 0) return $yDiff > 0 ? 1 : -1;
            return $a['x'] <=> $b['x'];
        });

        // Reconstruct text, adding newlines when Y changes significantly
        $lines = [];
        $currentLine = '';
        $lastY = null;

        foreach ($allItems as $item) {
            if ($lastY !== null && abs($item['y'] - $lastY) > 3) {
                // New line
                if (trim($currentLine) !== '') {
                    $lines[] = trim($currentLine);
                }
                $currentLine = $item['text'];
            } else {
                // Same line - add space
                $currentLine .= ' ' . $item['text'];
            }
            $lastY = $item['y'];
        }
        if (trim($currentLine) !== '') {
            $lines[] = trim($currentLine);
        }

        return implode("\n", $lines);
    }

    /**
     * Extract text using getTextArray() per page
     */
    private function extractTextUsingTextArray($pdf): string
    {
        $result = '';

        try {
            $pages = $pdf->getPages();
            foreach ($pages as $page) {
                $textArray = $page->getTextArray();
                if (!empty($textArray)) {
                    $result .= implode('', $textArray) . "\n";
                }
            }
        } catch (\Exception $e) {
            Log::warning('SKIM getTextArray error', ['error' => $e->getMessage()]);
        }

        return $result;
    }

    /**
     * Try to extract text from raw PDF stream content
     * This bypasses the parser's font decoding and reads raw text strings
     */
    private function extractTextFromPdfStream(string $filePath): string
    {
        $content = file_get_contents($filePath);
        $text = '';

        // Extract text between BT and ET markers (text objects in PDF)
        if (preg_match_all('/BT\s*(.*?)\s*ET/s', $content, $matches)) {
            foreach ($matches[1] as $textBlock) {
                // Extract text from Tj and TJ operators
                if (preg_match_all('/\((.*?)\)\s*Tj/s', $textBlock, $tjMatches)) {
                    foreach ($tjMatches[1] as $txt) {
                        $text .= $this->decodePdfString($txt) . ' ';
                    }
                }
                // TJ operator (array of strings)
                if (preg_match_all('/\[(.*?)\]\s*TJ/s', $textBlock, $tjArrayMatches)) {
                    foreach ($tjArrayMatches[1] as $arr) {
                        if (preg_match_all('/\((.*?)\)/', $arr, $strMatches)) {
                            foreach ($strMatches[1] as $txt) {
                                $text .= $this->decodePdfString($txt);
                            }
                        }
                        $text .= ' ';
                    }
                }
            }
        }

        return trim($text);
    }

    /**
     * Decode a PDF string (handle octal escapes)
     */
    private function decodePdfString(string $str): string
    {
        // Handle PDF escape sequences
        $str = str_replace(['\\n', '\\r', '\\t', '\\(', '\\)', '\\\\'], ["\n", "\r", "\t", '(', ')', '\\'], $str);

        // Handle octal sequences like \001, \002
        $str = preg_replace_callback('/\\\\(\d{1,3})/', function ($m) {
            return chr(octdec($m[1]));
        }, $str);

        return $str;
    }

    /**
     * Try to use system pdftotext command if available
     */
    private function extractTextUsingPdftotext(string $filePath): string
    {
        $commands = [
            'pdftotext',
            'C:\\Program Files\\xpdf\\pdftotext',
            'C:\\Program Files (x86)\\xpdf\\pdftotext',
            'C:\\laragon\\bin\\pdftotext',
        ];

        foreach ($commands as $cmd) {
            try {
                $outputFile = tempnam(sys_get_temp_dir(), 'skim_') . '.txt';
                $escapedPath = escapeshellarg($filePath);
                $escapedOutput = escapeshellarg($outputFile);
                $result = exec("{$cmd} -layout {$escapedPath} {$escapedOutput} 2>&1", $output, $returnCode);

                if ($returnCode === 0 && file_exists($outputFile)) {
                    $text = file_get_contents($outputFile);
                    unlink($outputFile);
                    if (strlen(trim($text)) > 10) {
                        return $text;
                    }
                }

                if (file_exists($outputFile)) {
                    unlink($outputFile);
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        return '';
    }

    /**
     * Extract from Word
     */
    private function extractFromWord(string $filePath): array
    {
        $fullText = '';

        try {
            $phpWord = IOFactory::load($filePath, 'Word2007');
            $sections = $phpWord->getSections();

            foreach ($sections as $section) {
                $elements = $section->getElements();
                foreach ($elements as $element) {
                    $fullText .= $this->extractTextFromElement($element) . "\n";
                }
                $fullText .= "\n---PAGE_BREAK---\n";
            }
        } catch (\Exception $e) {
            Log::warning('SKIM PhpWord fallback', ['file' => basename($filePath), 'error' => $e->getMessage()]);
            $fullText = $this->extractTextFallback($filePath);
        }

        Log::info('SKIM Word Raw Text', ['file' => basename($filePath), 'text' => $fullText]);

        $pages = $this->splitIntoPages($fullText);
        $records = [];

        foreach ($pages as $pageText) {
            if (strlen(trim($pageText)) < 30) continue;
            $record = $this->parseText($pageText);
            if ($record['nama'] || $record['no_register']) {
                $records[] = $record;
            }
        }

        if (empty($records)) {
            $record = $this->parseText($fullText);
            if ($record['nama'] || $record['no_register']) {
                $records[] = $record;
            }
        }

        return $records;
    }

    /**
     * Normalize text to handle various PDF extraction quirks
     */
    private function normalizeText(string $text): string
    {
        // Replace various Unicode colons with standard colon
        $text = str_replace(['：', '∶', '꞉', "\xEF\xBC\x9A"], ':', $text);

        // Replace Unicode whitespace variants
        $text = preg_replace('/[\x{00A0}\x{2000}-\x{200B}\x{202F}\x{205F}\x{3000}\x{FEFF}]/u', ' ', $text);

        // Remove zero-width characters
        $text = preg_replace('/[\x{200C}\x{200D}\x{200E}\x{200F}]/u', '', $text);

        // Normalize line endings
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        return $text;
    }

    /**
     * Parse text into structured SKIM data
     */
    private function parseText(string $text): array
    {
        $data = [
            'nama' => null,
            'ttl' => null,
            'niora' => null,
            'status_sipil' => null,
            'kewarganegaraan' => null,
            'pekerjaan' => null,
            'nomor_paspor' => null,
            'jenis_keimigrasian' => null,
            'alamat' => null,
            'no_register' => null,
            'jenis_kelamin' => null,
        ];

        if (strlen(trim($text)) < 5) {
            return $data;
        }

        // Normalize the text first
        $text = $this->normalizeText($text);

        // Create different text representations for matching
        $cleanText = preg_replace('/[^\S\n]+/', ' ', $text);
        $flatText = preg_replace('/\s+/', ' ', $text);

        Log::debug('SKIM parseText cleanText', ['text' => $cleanText]);
        Log::debug('SKIM parseText flatText', ['text' => substr($flatText, 0, 2000)]);

        // === 1. NAMA ===
        $namaPatterns = [
            '/(?:^|\n)\s*Nama\s*:\s*(.+?)(?:\n|$)/i',
            '/Nama\s*:\s*([A-Z][A-Z\s\.\-\',]+?)(?:\s*Tempat|\s*Niora|\s*Status)/i',
            '/bahwa\s*:\s*Nama\s*:\s*(.+?)\s*Tempat/i',
            '/Nama\s*:\s*([A-Z][A-Z\s\.\-\',]{2,})/i',
        ];
        foreach ($namaPatterns as $pattern) {
            if (preg_match($pattern, $cleanText, $m) || preg_match($pattern, $flatText, $m)) {
                $val = trim($m[1]);
                $val = preg_replace('/\s*(Tempat|Niora|Status|TTL|Paspor|Kewarganegaraan).*$/i', '', $val);
                $val = trim($val);
                if (strlen($val) >= 2) {
                    $data['nama'] = strtoupper($val);
                    break;
                }
            }
        }

        // === 2. TTL ===
        $ttlPatterns = [
            '/Tempat\s*,?\s*Tanggal\s*Lahir\s*:\s*(.+?)(?:\n|$)/i',
            '/Tempat\s*,?\s*Tanggal\s*Lahir\s*:\s*(.+?)(?:\s*Niora|\s*Status|\s*NIORA)/i',
            '/TTL\s*:\s*(.+?)(?:\n|$)/i',
        ];
        foreach ($ttlPatterns as $pattern) {
            if (preg_match($pattern, $cleanText, $m) || preg_match($pattern, $flatText, $m)) {
                $val = trim($m[1]);
                $val = preg_replace('/\s*(Niora|Status|NIORA).*$/i', '', $val);
                if (strlen($val) >= 3) {
                    $data['ttl'] = strtoupper(trim($val));
                    break;
                }
            }
        }

        // === 3. NIORA ===
        if (preg_match('/Niora\s*:\s*([A-Z0-9]+)/i', $cleanText, $m) ||
            preg_match('/Niora\s*:\s*([A-Z0-9]+)/i', $flatText, $m)) {
            $data['niora'] = strtoupper(trim($m[1]));
        }

        // === 4. STATUS SIPIL ===
        $statusPatterns = [
            '/Status\s*Sipil\s*:\s*(.+?)(?:\n|$)/i',
            '/Status\s*Sipil\s*:\s*(\w+)/i',
        ];
        foreach ($statusPatterns as $pattern) {
            if (preg_match($pattern, $cleanText, $m) || preg_match($pattern, $flatText, $m)) {
                $val = trim($m[1]);
                $val = preg_replace('/\s*(Kewarganegaraan|Pekerjaan|Paspor).*$/i', '', $val);
                $data['status_sipil'] = strtoupper(trim($val));
                break;
            }
        }

        // === 5. KEWARGANEGARAAN ===
        $kwPatterns = [
            '/(?:^|\n)\s*Kewarganegaraan\s*:\s*([A-Za-z\s]+?)(?:\n|$)/im',
            '/Kewarganegaraan\s*:\s*([A-Z][A-Za-z\s]+?)(?:\s*Pekerjaan|\s*Paspor|\s*Dokumen)/i',
            '/Kewarganegaraan\s*:\s*(\w+)/i',
        ];
        foreach ($kwPatterns as $pattern) {
            if (preg_match($pattern, $cleanText, $m) || preg_match($pattern, $flatText, $m)) {
                $val = trim($m[1]);
                $val = preg_replace('/\s*(Pekerjaan|Paspor|Dokumen|Jenis|Status|Alamat).*$/i', '', $val);
                if (strlen($val) >= 2) {
                    $data['kewarganegaraan'] = strtoupper(trim($val));
                    break;
                }
            }
        }

        // === 6. PEKERJAAN ===
        $pekerjaanPatterns = [
            '/Pekerjaan\s*:\s*(.+?)(?:\n|$)/i',
            '/Pekerjaan\s*:\s*(.+?)(?:\s*Paspor|\s*Dokumen)/i',
        ];
        foreach ($pekerjaanPatterns as $pattern) {
            if (preg_match($pattern, $cleanText, $m) || preg_match($pattern, $flatText, $m)) {
                $val = trim($m[1]);
                $val = preg_replace('/\s*(Paspor|Dokumen|No\.?\s*Paspor).*$/i', '', $val);
                if (strlen($val) >= 1) {
                    $data['pekerjaan'] = strtoupper(trim($val));
                    break;
                }
            }
        }

        // === 7. NO. PASPOR ===
        $pasporPatterns = [
            '/(?:^|\n)\s*Paspor\s*:\s*([A-Z0-9][\w\-]+)/im',
            '/No\.?\s*Paspor\s*:\s*([A-Z0-9][\w\-]+)/i',
            '/Paspor\s*:\s*([A-Z0-9][\w\-]+)/i',
        ];
        foreach ($pasporPatterns as $pattern) {
            if (preg_match($pattern, $cleanText, $m) || preg_match($pattern, $flatText, $m)) {
                $data['nomor_paspor'] = strtoupper(trim($m[1]));
                break;
            }
        }

        // === 8. JENIS KEIMIGRASIAN ===
        $jenisPatterns = [
            '/Jenis\s*:\s*(IZIN\s+TINGGAL\s+\w+)/i',
            '/Jenis\s*:\s*((?!Kelamin)[A-Z][A-Z\s]+?)(?:\n|Nomor|Register|Kanim|Tanggal)/i',
            '/Jenis\s*:\s*((?!Kelamin).+?)(?:\n|$)/i',
        ];
        foreach ($jenisPatterns as $pattern) {
            if (preg_match($pattern, $cleanText, $m) || preg_match($pattern, $flatText, $m)) {
                $val = trim($m[1]);
                $val = preg_replace('/\s*(Nomor|Register|Kanim|Tanggal).*$/i', '', $val);
                if (strlen($val) >= 3 && !preg_match('/kelamin/i', $val)) {
                    $data['jenis_keimigrasian'] = strtoupper(trim($val));
                    break;
                }
            }
        }

        // === 9. NO. REGISTER ===
        $registerPatterns = [
            '/Nomor\s*Register\s*:\s*([A-Z0-9][\w\-\/\.]+)/i',
            '/No\.?\s*Register\s*:\s*([A-Z0-9][\w\-\/\.]+)/i',
            '/Register\s*:\s*([A-Z0-9][\w\-\/\.]+)/i',
        ];
        foreach ($registerPatterns as $pattern) {
            if (preg_match($pattern, $cleanText, $m) || preg_match($pattern, $flatText, $m)) {
                $data['no_register'] = strtoupper(trim($m[1]));
                break;
            }
        }

        if (!$data['no_register']) {
            if (preg_match('/Nomor\s*:\s*([A-Z0-9][\w\-\/\.]+)/i', $cleanText, $m) ||
                preg_match('/Nomor\s*:\s*([A-Z0-9][\w\-\/\.]+)/i', $flatText, $m)) {
                $data['no_register'] = strtoupper(trim($m[1]));
            }
        }

        // === 10. ALAMAT ===
        $alamatPatterns = [
            '/Alamat\s*(?:tempat\s*tinggal)?\s*:\s*(.*?)(?=\s*(?:sudah|Demikian|Berdasarkan|bertempat|\n\s*\n))/is',
            '/Alamat\s*(?:tempat\s*tinggal)?\s*:\s*(.*?)(?:\s*sudah|\s*Demikian|\s*Berdasarkan)/is',
            '/Alamat\s*(?:tempat\s*tinggal)?\s*:\s*(.+?)(?:\n|$)/i',
        ];
        foreach ($alamatPatterns as $pattern) {
            if (preg_match($pattern, $cleanText, $m) || preg_match($pattern, $flatText, $m)) {
                $alamat = trim($m[1]);
                $alamat = preg_replace('/\s*\n\s*/', ', ', $alamat);
                $alamat = preg_replace('/,\s*,/', ',', $alamat);
                $alamat = preg_replace('/\s{2,}/', ' ', $alamat);
                $alamat = trim($alamat, " ,\t\n\r");
                if (strlen($alamat) >= 5) {
                    $data['alamat'] = strtoupper($alamat);
                    break;
                }
            }
        }

        // === 11. JENIS KELAMIN ===
        if (preg_match('/Jenis\s*Kelamin\s*:\s*(LAKI[\s\-]*LAKI|PEREMPUAN|WANITA|PRIA|MALE|FEMALE|[LP])\b/i', $cleanText, $m) ||
            preg_match('/Jenis\s*Kelamin\s*:\s*(LAKI[\s\-]*LAKI|PEREMPUAN|WANITA|PRIA|MALE|FEMALE|[LP])\b/i', $flatText, $m)) {
            $data['jenis_kelamin'] = $this->transformer->normalizeGender($m[1]);
        }

        return $data;
    }

    /**
     * Recursively extract text from PhpWord element
     */
    private function extractTextFromElement($element): string
    {
        $text = '';

        if (method_exists($element, 'getElements')) {
            foreach ($element->getElements() as $childElement) {
                $text .= $this->extractTextFromElement($childElement);
            }
            $text .= "\n";
        } elseif (method_exists($element, 'getText')) {
            $textContent = $element->getText();
            if (is_string($textContent)) {
                $text .= $textContent;
            } elseif (is_object($textContent) && method_exists($textContent, 'getText')) {
                $text .= $textContent->getText();
            }
        }

        return $text;
    }

    /**
     * Split text into pages
     */
    private function splitIntoPages(string $text): array
    {
        $pages = preg_split('/---PAGE_BREAK---/', $text);
        $pages = array_filter($pages, fn($p) => strlen(trim($p)) > 50);

        if (count($pages) > 1) {
            return array_values($pages);
        }

        $parts = preg_split('/(SURAT\s*KETERANGAN\s*KEIMIGRASIAN)/i', $text, -1, PREG_SPLIT_DELIM_CAPTURE);

        if (count($parts) > 2) {
            $pages = [];
            for ($i = 1; $i < count($parts); $i += 2) {
                $pages[] = ($parts[$i] ?? '') . ($parts[$i + 1] ?? '');
            }
            return $pages;
        }

        return [$text];
    }

    /**
     * Fallback: extract text from docx zip
     */
    private function extractTextFallback(string $filePath): string
    {
        $text = '';
        $zip = new \ZipArchive();

        if ($zip->open($filePath) === true) {
            if (($xmlData = $zip->getFromName('word/document.xml')) !== false) {
                $xmlData = str_replace(['</w:p>', '</w:tr>', '<w:br/>'], "\n", $xmlData);
                $text = strip_tags($xmlData);
                $text = html_entity_decode($text);
            }
            $zip->close();
        }

        return $text;
    }
}
