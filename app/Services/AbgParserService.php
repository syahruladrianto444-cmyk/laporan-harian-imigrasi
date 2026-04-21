<?php

namespace App\Services;

use Smalot\PdfParser\Parser;
use PhpOffice\PhpWord\IOFactory;
use Illuminate\Support\Facades\Log;

class AbgParserService
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
     * Extract from PDF
     */
    private function extractFromPdf(string $filePath): array
    {
        $parser = new Parser();
        $pdf = $parser->parseFile($filePath);
        $text = $pdf->getText();

        Log::info('ABG PDF Raw Text', ['file' => basename($filePath), 'text' => $text]);

        // Try page-by-page first
        $pages = $pdf->getPages();
        $records = [];

        foreach ($pages as $page) {
            $pageText = $page->getText();
            if (strlen(trim($pageText)) < 30) continue;
            $record = $this->parseText($pageText);
            if ($record['nama'] || $record['no_register']) {
                $records[] = $record;
            }
        }

        // If page-by-page didn't work, try full text
        if (empty($records)) {
            $record = $this->parseText($text);
            if ($record['nama'] || $record['no_register']) {
                $records[] = $record;
            }
        }

        // If still empty, force return with whatever we can extract
        if (empty($records)) {
            $records[] = $this->parseText($text);
        }

        return $records;
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
            Log::warning('ABG PhpWord fallback', ['file' => basename($filePath), 'error' => $e->getMessage()]);
            $fullText = $this->extractTextFallback($filePath);
        }

        Log::info('ABG Word Raw Text', ['file' => basename($filePath), 'text' => $fullText]);

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
     * Parse text into structured ABG data
     *
     * Known Layout (Sertifikat Pendaftaran ABG):
     * -----------------------------------------------
     * KEMENTRIAN IMIGRASI DAN PERMASYARAKATAN REPUBLIK INDONESIA
     * KANTOR WILAYAH JAWA TENGAH
     * KANTOR IMIGRASI KELAS II NON TPI PEMALANG
     *
     * [photo]     ESHAAL DURRANI           <-- Name in white form above JENIS KELAMIN
     *             JENIS KELAMIN   : Wanita
     *             NOMOR REGISTER  : 2J11LF0431C
     *                                              ABG PASAL 4D
     *
     * SERTIFIKAT
     * PENDAFTARAN ANAK BERKEWARGANEGARAAN GANDA
     *
     * Tempat / Tanggal Lahir         : PEMALANG / 21-11-2025
     * Kewarganegaraan Lain           : PAKISTAN
     * Nomor Paspor Kebangsaan Asing  : -
     * Alamat Tempat Tinggal          : JL. BALIMBING BARAT, RT 04 RW 01...
     * Nama Ayah                      : MUHAMMAD ZALAN
     * Kewarganegaraan                : PAKISTAN
     * Nama Ibu                       : KURNIA MAHARANI ASH SHIDIQ
     * Kewarganegaraan                : INDONESIA
     * -----------------------------------------------
     *
     * PDF text extraction notes:
     * - The name "ESHAAL DURRANI" is in the header block (white form area above JENIS KELAMIN)
     * - smalot/pdfparser may output the name:
     *   a) As a standalone line before "JENIS KELAMIN"
     *   b) Concatenated with other text
     *   c) In the same line as header text
     * - The name does NOT have a "Nama :" label - it's standalone text
     */
    private function parseText(string $text): array
    {
        $data = [
            'nama' => null,
            'ttl' => null,
            'kewarganegaraan' => null,
            'nomor_paspor_asing' => null,
            'alamat' => null,
            'nama_ayah' => null,
            'kewarganegaraan_ayah' => null,
            'nama_ibu' => null,
            'kewarganegaraan_ibu' => null,
            'no_register' => null,
            'jenis_kelamin' => null,
        ];

        // Normalize the text first
        $text = $this->normalizeText($text);

        // Create different text representations for matching
        $cleanText = preg_replace('/[^\S\n]+/', ' ', $text);  // collapse horizontal whitespace per line
        $flatText = preg_replace('/\s+/', ' ', $text);        // everything on one line

        Log::debug('ABG cleanText', ['text' => $cleanText]);
        Log::debug('ABG flatText', ['text' => substr($flatText, 0, 2000)]);

        // === 1. NOMOR REGISTER: "NOMOR REGISTER : 2J11LF0431C" ===
        $registerPatterns = [
            '/NOMOR\s*REGISTER\s*:\s*([A-Z0-9][\w\-\/\.]+)/i',
            '/No\.?\s*Register\s*:\s*([A-Z0-9][\w\-\/\.]+)/i',
            '/REGISTER\s*:\s*([A-Z0-9][\w\-\/\.]+)/i',
        ];
        foreach ($registerPatterns as $pattern) {
            if (preg_match($pattern, $cleanText, $m) || preg_match($pattern, $flatText, $m)) {
                $data['no_register'] = strtoupper(trim($m[1]));
                break;
            }
        }

        // === 2. JENIS KELAMIN: "JENIS KELAMIN : Wanita" ===
        $genderPatterns = [
            '/JENIS\s*KELAMIN\s*:\s*(LAKI[\s\-]*LAKI|PEREMPUAN|WANITA|PRIA|MALE|FEMALE|[LP])\b/i',
            '/KELAMIN\s*:\s*(LAKI[\s\-]*LAKI|PEREMPUAN|WANITA|PRIA|MALE|FEMALE|[LP])\b/i',
            '/JENIS\s*KELAMIN\s*:\s*(\w+)/i',
        ];
        foreach ($genderPatterns as $pattern) {
            if (preg_match($pattern, $cleanText, $m) || preg_match($pattern, $flatText, $m)) {
                $data['jenis_kelamin'] = $this->transformer->normalizeGender($m[1]);
                break;
            }
        }

        // === 3. NAMA: Located in white form area ABOVE "JENIS KELAMIN" ===
        // The name is NOT labeled with "Nama :" - it's standalone text in the header
        // Strategy: find text between header keywords and "JENIS KELAMIN"
        $namePatterns = [
            // Pattern 1: Name on a line by itself before JENIS KELAMIN (multi-line)
            '/\n\s*([A-Z][A-Z\s\.\-\',]{2,}?)\s*\n\s*JENIS\s*KELAMIN/i',

            // Pattern 2: Name between ABG-related header and JENIS KELAMIN (flat)
            '/(?:PEMALANG|TPI\s*PEMALANG|NON\s*TPI\s*PEMALANG)\s+([A-Z][A-Z\s\.\-\',]{2,}?)\s+JENIS\s*KELAMIN/i',

            // Pattern 3: Name between KELAS and JENIS KELAMIN
            '/KELAS\s+(?:I{1,3}|[12])\s+(?:NON\s+)?(?:TPI\s+)?(?:PEMALANG\s+)?([A-Z][A-Z\s\.\-\',]{2,}?)\s+JENIS\s*KELAMIN/i',

            // Pattern 4: Name directly before "JENIS KELAMIN" in flat text (looser match)
            '/\b([A-Z][A-Z\s]{3,}?)\s+JENIS\s*KELAMIN/i',

            // Pattern 5: Name between "PENDAFTARAN" header and "JENIS KELAMIN"
            '/(?:PENDAFTARAN|SERTIFIKAT)\s+.*?([A-Z][A-Z\s\.\-\',]{2,}?)\s+JENIS\s*KELAMIN/is',

            // Pattern 6: Name in text just before JENIS or NOMOR REGISTER
            '/(?:IMIGRASI|PEMALANG)\s+([A-Z][A-Z\s\.\-\',]{2,}?)\s+(?:JENIS|NOMOR\s*REGISTER)/i',

            // Pattern 7: very loose - look for capitalized name-like text before JENIS KELAMIN
            // Extract from the text block between known header segments and JENIS KELAMIN
            '/([A-Z]{2,}(?:\s+[A-Z]{2,}){0,5})\s+JENIS\s*KELAMIN/i',
        ];

        foreach ($namePatterns as $pattern) {
            if (preg_match($pattern, $cleanText, $m) || preg_match($pattern, $flatText, $m)) {
                $name = trim($m[1]);
                // Clean noise: remove header text that may have been captured
                $noise = [
                    'KEMENTRIAN', 'KEMENTERIAN', 'IMIGRASI', 'PERMASYARAKATAN',
                    'REPUBLIK', 'INDONESIA', 'KANTOR', 'WILAYAH', 'JAWA', 'TENGAH',
                    'KELAS', 'NON', 'TPI', 'PEMALANG', 'SERTIFIKAT', 'PENDAFTARAN',
                    'ANAK', 'BERKEWARGANEGARAAN', 'GANDA', 'ABG', 'PASAL',
                    'HAK', 'ASASI', 'MANUSIA', 'HUKUM'
                ];
                // Check that the name is NOT one of the noise words
                $nameWords = preg_split('/\s+/', $name);
                $cleanWords = array_filter($nameWords, function($w) use ($noise) {
                    return !in_array(strtoupper($w), $noise) && strlen($w) >= 2;
                });

                if (count($cleanWords) >= 1) {
                    $name = implode(' ', $cleanWords);
                    // Verify it looks like a person name (not a government office name)
                    if (strlen($name) >= 2 && !preg_match('/KANTOR|WILAYAH|KELAS|IMIGRASI|SERTIFIKAT|PENDAFTARAN|KEMENTERIAN|KEMENTRIAN/i', $name)) {
                        $data['nama'] = strtoupper(trim($name));
                        break;
                    }
                }
            }
        }

        // Fallback: try explicit "Nama :" pattern
        if (!$data['nama']) {
            if (preg_match('/(?:^|\n)\s*Nama\s*:\s*(.+?)(?:\n|$)/im', $cleanText, $m) ||
                preg_match('/Nama\s*:\s*([A-Z][A-Z\s\.\-\',]{2,})/i', $flatText, $m)) {
                $val = trim($m[1]);
                $val = preg_replace('/\s*(Tempat|JENIS|KELAMIN|NOMOR|Ayah|Ibu).*$/i', '', $val);
                if (strlen($val) >= 2) {
                    $data['nama'] = strtoupper(trim($val));
                }
            }
        }

        // Fallback: extract name from "bahwa atas nama XXXXX merupakan"
        if (!$data['nama']) {
            if (preg_match('/(?:bahwa\s+)?atas\s+nama\s+([A-Z][A-Z\s\.\-\',]+?)\s+merupakan/i', $flatText, $m)) {
                $data['nama'] = strtoupper(trim($m[1]));
            }
        }

        // === 4. TTL: "Tempat / Tanggal Lahir : PEMALANG / 21-11-2025" ===
        $ttlPatterns = [
            '/Tempat\s*[\/,]\s*Tanggal\s*Lahir\s*:\s*(.+?)(?:\n|$)/i',
            '/Tempat\s*,?\s*Tanggal\s*Lahir\s*:\s*(.+?)(?:\n|$)/i',
            '/Tempat\s*[\/,]?\s*Tanggal\s*Lahir\s*:\s*(.+?)(?:\s*Kewarganegaraan)/i',
            '/TTL\s*:\s*(.+?)(?:\n|$)/i',
        ];
        foreach ($ttlPatterns as $pattern) {
            if (preg_match($pattern, $cleanText, $m) || preg_match($pattern, $flatText, $m)) {
                $val = trim($m[1]);
                $val = preg_replace('/\s*(Kewarganegaraan|Nomor\s*Paspor|Alamat).*$/i', '', $val);
                if (strlen($val) >= 3) {
                    $data['ttl'] = strtoupper(trim($val));
                    break;
                }
            }
        }

        // === 5. KEWARGANEGARAAN (Lain) ===
        // "Kewarganegaraan Lain : PAKISTAN" - the first Kewarganegaraan after TTL
        $kwPatterns = [
            '/Kewarganegaraan\s*Lain\s*:\s*([A-Za-z\s]+?)(?:\n|$)/i',
            '/Kewarganegaraan\s*Lain\s*:\s*(\w+)/i',
        ];
        foreach ($kwPatterns as $pattern) {
            if (preg_match($pattern, $cleanText, $m) || preg_match($pattern, $flatText, $m)) {
                $val = trim($m[1]);
                $val = preg_replace('/\s*(Nomor|Paspor|Alamat|Nama|Jenis).*$/i', '', $val);
                if (strlen($val) >= 2) {
                    $data['kewarganegaraan'] = strtoupper(trim($val));
                    break;
                }
            }
        }

        // Fallback: first "Kewarganegaraan" that is NOT "Lain" and NOT after Ayah/Ibu
        if (!$data['kewarganegaraan']) {
            if (preg_match('/(?:^|\n)\s*Kewarganegaraan\s*:\s*([A-Za-z\s]+?)(?:\n|$)/im', $cleanText, $m)) {
                $val = trim($m[1]);
                $val = preg_replace('/\s*(Nomor|Paspor|Alamat|Nama|Jenis).*$/i', '', $val);
                if (strlen($val) >= 2) {
                    $data['kewarganegaraan'] = strtoupper(trim($val));
                }
            }
        }

        // === 6. NOMOR PASPOR KEBANGSAAN ASING ===
        $pasporPatterns = [
            '/Nomor\s*Paspor\s*Kebangsaan\s*Asing\s*:\s*(.+?)(?:\n|$)/i',
            '/Paspor\s*Kebangsaan\s*Asing\s*:\s*(.+?)(?:\n|$)/i',
            '/No\.?\s*Paspor\s*(?:Kebangsaan\s*)?(?:Asing)?\s*:\s*(.+?)(?:\n|Alamat)/i',
        ];
        foreach ($pasporPatterns as $pattern) {
            if (preg_match($pattern, $cleanText, $m) || preg_match($pattern, $flatText, $m)) {
                $val = strtoupper(trim($m[1]));
                $val = preg_replace('/\s*(Alamat|Nama|Tempat).*$/i', '', $val);
                $val = trim($val);
                $data['nomor_paspor_asing'] = ($val === '-' || $val === '' || $val === 'TIDAK ADA') ? '-' : $val;
                break;
            }
        }

        // === 7. ALAMAT ===
        $alamatPatterns = [
            // Multi-line: capture until "Nama Ayah"
            '/Alamat\s*(?:Tempat\s*Tinggal)?\s*:\s*(.*?)(?=\s*(?:Nama\s*Ayah|Berdasarkan|\n\s*\n))/is',
            // Flat: Alamat ... : ... Nama Ayah
            '/Alamat\s*(?:Tempat\s*Tinggal)?\s*:\s*(.*?)(?:\s*Nama\s*Ayah)/is',
            // Simple
            '/Alamat\s*(?:Tempat\s*Tinggal)?\s*:\s*(.+?)(?:\n|$)/i',
        ];
        foreach ($alamatPatterns as $pattern) {
            if (preg_match($pattern, $cleanText, $m) || preg_match($pattern, $flatText, $m)) {
                $alamat = trim($m[1]);
                $alamat = preg_replace('/\s*\n\s*/', ', ', $alamat);
                $alamat = preg_replace('/,\s*,/', ',', $alamat);
                $alamat = preg_replace('/\s{2,}/', ' ', $alamat);
                $alamat = trim($alamat, " ,\t\n\r");
                if (strlen($alamat) >= 3) {
                    $data['alamat'] = strtoupper($alamat);
                    break;
                }
            }
        }

        // === 8. NAMA AYAH ===
        $namaAyahPatterns = [
            '/Nama\s*Ayah\s*:\s*(.+?)(?:\n|$)/i',
            '/Nama\s*Ayah\s*:\s*([A-Z][A-Z\s\.\-\',]+?)(?:\s*Kewarganegaraan)/i',
        ];
        foreach ($namaAyahPatterns as $pattern) {
            if (preg_match($pattern, $cleanText, $m) || preg_match($pattern, $flatText, $m)) {
                $val = trim($m[1]);
                $val = preg_replace('/\s*(Kewarganegaraan|Nama\s*Ibu).*$/i', '', $val);
                if (strlen($val) >= 2) {
                    $data['nama_ayah'] = strtoupper(trim($val));
                    break;
                }
            }
        }

        // === 9. KEWARGANEGARAAN AYAH ===
        // In the layout: after "Nama Ayah" there's "Kewarganegaraan : PAKISTAN"
        $kwAyahPatterns = [
            '/Nama\s*Ayah\s*:.*?\n\s*Kewarganegaraan\s*:\s*([A-Za-z\s]+?)(?:\n|$)/is',
            '/Nama\s*Ayah\s*:.*?Kewarganegaraan\s*:\s*([A-Za-z]+)/is',
        ];
        foreach ($kwAyahPatterns as $pattern) {
            if (preg_match($pattern, $cleanText, $m) || preg_match($pattern, $flatText, $m)) {
                $val = trim($m[1]);
                $val = preg_replace('/\s*(Nama|Ibu|Alamat|Nomor).*$/i', '', $val);
                if (strlen($val) >= 2) {
                    $data['kewarganegaraan_ayah'] = strtoupper(trim($val));
                    break;
                }
            }
        }

        // === 10. NAMA IBU ===
        $namaIbuPatterns = [
            '/Nama\s*Ibu\s*:\s*(.+?)(?:\n|$)/i',
            '/Nama\s*Ibu\s*:\s*([A-Z][A-Z\s\.\-\',]+?)(?:\s*Kewarganegaraan)/i',
        ];
        foreach ($namaIbuPatterns as $pattern) {
            if (preg_match($pattern, $cleanText, $m) || preg_match($pattern, $flatText, $m)) {
                $val = trim($m[1]);
                $val = preg_replace('/\s*(Kewarganegaraan|Berdasarkan).*$/i', '', $val);
                if (strlen($val) >= 2) {
                    $data['nama_ibu'] = strtoupper(trim($val));
                    break;
                }
            }
        }

        // === 11. KEWARGANEGARAAN IBU ===
        // In the layout: after "Nama Ibu" there's "Kewarganegaraan : INDONESIA"
        $kwIbuPatterns = [
            '/Nama\s*Ibu\s*:.*?\n\s*Kewarganegaraan\s*:\s*([A-Za-z\s]+?)(?:\n|$)/is',
            '/Nama\s*Ibu\s*:.*?Kewarganegaraan\s*:\s*([A-Za-z]+)/is',
        ];
        foreach ($kwIbuPatterns as $pattern) {
            if (preg_match($pattern, $cleanText, $m) || preg_match($pattern, $flatText, $m)) {
                $val = trim($m[1]);
                $val = preg_replace('/\s*(Berdasarkan|Nama|Alamat|Nomor|Pasal).*$/i', '', $val);
                if (strlen($val) >= 2) {
                    $data['kewarganegaraan_ibu'] = strtoupper(trim($val));
                    break;
                }
            }
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

        // Try splitting by "SERTIFIKAT" header
        $parts = preg_split('/(SERTIFIKAT\s*\n)/i', $text, -1, PREG_SPLIT_DELIM_CAPTURE);

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
