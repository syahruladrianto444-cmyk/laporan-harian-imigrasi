<?php

namespace App\Services;

use PhpOffice\PhpWord\IOFactory;
use Illuminate\Support\Facades\Log;

class WordParserService
{
    private DataTransformerService $transformer;

    public function __construct(DataTransformerService $transformer)
    {
        $this->transformer = $transformer;
    }

    /**
     * Extract data from a Word (.docx) file.
     * Supports multi-page documents where each page = separate certificate.
     *
     * @return array[] Array of records (one per page/certificate)
     */
    public function extractFromFile(string $filePath): array
    {
        $fullText = '';

        try {
            $phpWord = IOFactory::load($filePath, 'Word2007');
            $sections = $phpWord->getSections();

            // Build full text from all sections
            foreach ($sections as $section) {
                $elements = $section->getElements();
                foreach ($elements as $element) {
                    $fullText .= $this->extractTextFromElement($element) . "\n";
                }
                $fullText .= "\n---PAGE_BREAK---\n";
            }
        } catch (\Exception $e) {
            Log::warning('PhpWord failed to load file, using fallback extraction', [
                'file' => basename($filePath),
                'error' => $e->getMessage()
            ]);
            $fullText = $this->extractTextFallback($filePath);
        }

        Log::info('Word Raw Text Extract', ['file' => basename($filePath), 'text' => $fullText]);

        // Split into pages. If sections don't produce page breaks,
        // try splitting by known certificate header patterns
        $pages = $this->splitIntoPages($fullText);

        $records = [];
        foreach ($pages as $index => $pageText) {
            if (trim($pageText) === '' || strlen(trim($pageText)) < 30) {
                continue;
            }
            $record = $this->parsePage($pageText);
            $record['page_number'] = $index + 1;
            $records[] = $record;
        }

        // If no records found, try parsing the whole text as one page
        if (empty($records)) {
            $record = $this->parsePage($fullText);
            $record['page_number'] = 1;
            if ($record['nama'] || $record['nomor_registrasi']) {
                $records[] = $record;
            }
        }

        return $records;
    }

    /**
     * Recursively extract text from a PHPWord element
     */
    private function extractTextFromElement($element): string
    {
        $text = '';

        // Avoid duplicate text: if it has children, only rely on children's text
        if (method_exists($element, 'getElements')) {
            foreach ($element->getElements() as $childElement) {
                $text .= $this->extractTextFromElement($childElement);
            }
            // Add newline for structural elements like Paragraph or TextRun
            // (PhpWord often uses TextRun for lines)
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
     * Split text into pages by section breaks or repeated headers
     */
    private function splitIntoPages(string $text): array
    {
        // First try splitting by page break markers
        $pages = preg_split('/---PAGE_BREAK---/', $text);

        // Filter out empty pages
        $pages = array_filter($pages, function ($p) {
            return strlen(trim($p)) > 50;
        });

        if (count($pages) > 1) {
            return array_values($pages);
        }

        // Fallback: split by certificate header pattern
        // "SERTIFIKAT" or "NOMOR :" appearing multiple times
        $parts = preg_split('/(SERTIFIKAT\s*\n)/i', $text, -1, PREG_SPLIT_DELIM_CAPTURE);

        if (count($parts) > 2) {
            $pages = [];
            for ($i = 1; $i < count($parts); $i += 2) {
                $pages[] = ($parts[$i] ?? '') . ($parts[$i + 1] ?? '');
            }
            return $pages;
        }

        // If nothing splits, return as single page
        return [$text];
    }

    /**
     * Parse a single page/certificate text into structured data
     *
     * Known Layout (from Sertifikat ABK Ganda sample):
     * -----------------------------------------------
     * KEMENTERIAN HUKUM DAN HAK ASASI MANUSIA
     * REPUBLIK INDONESIA
     * KANTOR WILAYAH JAWA TENGAH
     * KANTOR IMIGRASI KELAS I NON TPI PEMALANG
     *                                 J1U1XEQ44340   (passport number top-right)
     *
     * SERTIFIKAT
     * BUKTI PENDAFTARAN ANAK BERKEWARGANEGARAAN GANDA
     * NOMOR : 1G01LF0002-A
     *
     * Nama                    : YOUXUAN HAN
     * Tempat, Tanggal Lahir   : HEBEI, 08 AGUSTUS 2023
     * Kewarganegaraan Lain    : CHINA
     * Jenis Kelamin           : LAKI - LAKI
     * Nomor Paspor Asing      : (sometimes here too)
     * ...
     * Bukti pendaftaran ini berlaku sampai dengan tanggal 08 Agustus 2044.
     *
     *                           Pemalang, 22 Januari 2024
     * -----------------------------------------------
     */
    private function parsePage(string $text): array
    {
        $data = [
            'nama' => null,
            'jenis_kelamin' => null,
            'kebangsaan' => null,
            'alamat' => null,
            'kota_kabupaten' => null,
            'nomor_paspor' => null,
        ];

        $cleanText = preg_replace('/[^\S\n]+/', ' ', $text);

        // 2. NOMOR PASPOR: standalone alphanumeric code at top
        // Could be in header area or as "Nomor Paspor Asing :"
        $passportPatterns = [
            '/Nomor\s*Paspor\s*(?:Asing)?\s*[:：]\s*([A-Z0-9]{5,20})/i',
            '/Paspor\s*(?:Asing)?\s*[:：]\s*([A-Z0-9]{5,20})/i',
            // Standalone passport-like code in the first 500 chars (header area)
            '/\b([A-Z][A-Z0-9]{8,15})\b/',
        ];
        foreach ($passportPatterns as $pattern) {
            if (preg_match($pattern, $cleanText, $m)) {
                $candidate = trim($m[1]);
                // Avoid matching NOMOR registrasi or common words
                if ($candidate !== ($data['nomor_registrasi'] ?? '') &&
                    !preg_match('/^(KEMENTERIAN|REPUBLIK|KANTOR|SERTIFIKAT|BUKTI|PENDAFTARAN|NOMOR|WILAYAH|INDONESIA|PASPOR|PASSPORT)/i', $candidate)) {
                    $data['nomor_paspor'] = $candidate;
                    break;
                }
            }
        }

        // 3. NAMA: "Nama : YOUXUAN HAN"
        if (preg_match('/(?:^|\n)\s*Nama\s*[:：]\s*(.+?)(?:\n|$)/i', $cleanText, $m)) {
            $data['nama'] = strtoupper(trim($m[1]));
        }

        // 1. JENIS KELAMIN: "Jenis Kelamin : LAKI - LAKI"
        // Most robust: Look for "Kelamin" followed by any characters until we see a known gender keyword
        if (preg_match('/Kelamin.*?\b(LAKILAKI|LAKI|PEREMPUAN|MALE|FEMALE|WANITA|L|P)\b/i', $cleanText, $m)) {
            $data['jenis_kelamin'] = $this->transformer->normalizeGender($m[1]);
        }

        // 3. KEBANGSAAN: "Kewarganegaraan Lain : CHINA"
        if (preg_match('/Kewarganegaraan\s*(?:Lain)?\s*[:：]\s*([A-Za-z\s]+?)(?:\n|$)/i', $cleanText, $m)) {
            $val = trim($m[1]);
            // Remove trailing labels
            $val = preg_replace('/\s*(Jenis|Nomor|Nama|Tempat|Status).*$/i', '', $val);
            $data['kebangsaan'] = strtoupper(trim($val));
        }

        // 4. ALAMAT
        if (preg_match('/Alamat\s*[:：]\s*(.+?)(?:\n|$)/i', $cleanText, $m)) {
            $data['alamat'] = strtoupper(trim($m[1]));

            // 5. KOTA/KABUPATEN: Extract from the end of Alamat
            // Usually after the last comma or the last two words
            if (preg_match('/(?:,\s*|\s+)([A-Z]{3,15})$/i', $data['alamat'], $cityMatch)) {
                $kota = trim($cityMatch[1]);
                $data['kota_kabupaten'] = strtoupper($kota);
            }
        }

        // If Alamat is still null, set to default '-' as requested
        if (empty($data['alamat'])) {
            $data['alamat'] = '-';
            $data['kota_kabupaten'] = null; // Ensure Kota is empty if Alamat is empty/null
        }

        // Uppercase text fields
        foreach (['nama', 'kebangsaan', 'alamat', 'kota_kabupaten', 'nomor_paspor'] as $field) {
            if ($data[$field] && $data[$field] !== '-') {
                $data[$field] = strtoupper(trim($data[$field]));
            }
        }

        return $data;
    }

    /**
     * Fallback method: Extract text directly from document.xml inside docx zip
     * Useful when PhpWord crashes on malformed images/EMF.
     */
    private function extractTextFallback(string $filePath): string
    {
        $text = '';
        $zip = new \ZipArchive();

        if ($zip->open($filePath) === true) {
            // Document text is in word/document.xml
            if (($xmlData = $zip->getFromName('word/document.xml')) !== false) {
                // Replace paragraph/row ends with newlines to preserve structure
                $xmlData = str_replace(['</w:p>', '</w:tr>', '<w:br/>'], "\n", $xmlData);
                // Strip all XML tags
                $text = strip_tags($xmlData);
                // Decode entities
                $text = html_entity_decode($text);
            }
            $zip->close();
        }

        return $text;
    }
}
