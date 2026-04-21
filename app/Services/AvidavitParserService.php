<?php

namespace App\Services;

use Smalot\PdfParser\Parser;
use PhpOffice\PhpWord\IOFactory;
use Illuminate\Support\Facades\Log;

class AvidavitParserService
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
     * Extract from PDF using smalot/pdfparser
     */
    private function extractFromPdf(string $filePath): array
    {
        $parser = new Parser();
        $pdf = $parser->parseFile($filePath);
        $text = $pdf->getText();

        Log::info('Avidavit PDF Raw Text', ['file' => basename($filePath), 'text' => $text]);

        return [$this->parseText($text)];
    }

    /**
     * Extract from Word using PhpWord
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
            Log::warning('Avidavit PhpWord fallback', ['file' => basename($filePath), 'error' => $e->getMessage()]);
            $fullText = $this->extractTextFallback($filePath);
        }

        Log::info('Avidavit Word Raw Text', ['file' => basename($filePath), 'text' => $fullText]);

        // Split into pages
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
     * Parse text into structured Avidavit data
     *
     * Known Layout (Surat Keterangan / Avidavit):
     * -----------------------------------------------
     * K E T E R A N G A N
     * No. : 1G11LF0001-W
     *
     * Nama            : Achmad Yuta Suwandi Yamamoto (L)
     * No. Paspor      : MZ2041170
     * Tempat, Tanggal Lahir : Kota Pekalongan, 09 April 2019
     * Nama Orang Tua  : Ayah : Tri Suwandi
     *                   Ibu  : Mayuko Yamamoto
     * Alamat          : Jl. Dr. Wahidin No. 81A RT/RW 001/005
     *                   Noyontaansari, Pekalongan Timur, Kota Pekalongan
     * adalah subyek Pasal 4 huruf c, huruf d, huruf h, huruf l dan Pasal 5
     * Undang-Undang No. 12 Tahun 2006 tentang Kewarganegaraan Republik Indonesia.
     * -----------------------------------------------
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

        $cleanText = preg_replace('/[^\S\n]+/', ' ', $text);
        $flatText = preg_replace('/\s+/', ' ', $text);

        // 1. NO. REGISTER: "No. : 1G11LF0001-W" or "No. : XXXX"
        if (preg_match('/No\.?\s*[:：]\s*([A-Z0-9][\w\-\/\.]+)/i', $cleanText, $m)) {
            $data['no_register'] = strtoupper(trim($m[1]));
        }

        // 2. NAMA + JENIS KELAMIN: "Nama : Achmad Yuta Suwandi Yamamoto (L)"
        if (preg_match('/(?:^|\n)\s*Nama\s*[:：]\s*(.+?)(?:\n|$)/i', $cleanText, $m)) {
            $namaRaw = trim($m[1]);
            // Extract gender from (L) or (P) suffix
            if (preg_match('/\(([LP])\)\s*$/i', $namaRaw, $gm)) {
                $data['jenis_kelamin'] = $this->transformer->normalizeGender($gm[1]);
                $namaRaw = preg_replace('/\s*\([LP]\)\s*$/i', '', $namaRaw);
            }
            $data['nama'] = strtoupper(trim($namaRaw));
        }

        // 3. NO. PASPOR KEBANGSAAN ASING: "No. Paspor : MZ2041170"
        $pasporPatterns = [
            '/No\.?\s*Paspor\s*(?:Kebangsaan\s*Asing)?\s*[:：]\s*([A-Z0-9][\w\-]+)/i',
            '/Paspor\s*[:：]\s*([A-Z0-9]{5,20})/i',
        ];
        foreach ($pasporPatterns as $pattern) {
            if (preg_match($pattern, $cleanText, $m)) {
                $data['nomor_paspor_asing'] = strtoupper(trim($m[1]));
                break;
            }
        }

        // 4. TTL: "Tempat, Tanggal Lahir : Kota Pekalongan, 09 April 2019"
        $ttlPatterns = [
            '/Tempat\s*,?\s*Tanggal\s*Lahir\s*[:：]\s*(.+?)(?:\n|$)/i',
            '/TTL\s*[:：]\s*(.+?)(?:\n|$)/i',
        ];
        foreach ($ttlPatterns as $pattern) {
            if (preg_match($pattern, $cleanText, $m)) {
                $data['ttl'] = strtoupper(trim($m[1]));
                break;
            }
        }

        // 5. NAMA AYAH: "Ayah : Tri Suwandi" or "Nama Ayah : ..."
        $ayahPatterns = [
            '/Nama\s*Ayah\s*[:：]\s*(.+?)(?:\n|$)/i',
            '/Ayah\s*[:：]\s*(.+?)(?:\n|$)/i',
        ];
        foreach ($ayahPatterns as $pattern) {
            if (preg_match($pattern, $cleanText, $m)) {
                $val = trim($m[1]);
                // Clean trailing labels
                $val = preg_replace('/\s*(Ibu|Alamat|Kewarganegaraan|No\.|Nama).*$/i', '', $val);
                $data['nama_ayah'] = strtoupper(trim($val));
                break;
            }
        }

        // 6. NAMA IBU: "Ibu : Mayuko Yamamoto" or "Nama Ibu : ..."
        $ibuPatterns = [
            '/Nama\s*Ibu\s*[:：]\s*(.+?)(?:\n|$)/i',
            '/Ibu\s*[:：]\s*(.+?)(?:\n|$)/i',
        ];
        foreach ($ibuPatterns as $pattern) {
            if (preg_match($pattern, $cleanText, $m)) {
                $val = trim($m[1]);
                $val = preg_replace('/\s*(Alamat|Kewarganegaraan|No\.|adalah|Nama).*$/i', '', $val);
                $data['nama_ibu'] = strtoupper(trim($val));
                break;
            }
        }

        // 7. ALAMAT: "Alamat : Jl. Dr. Wahidin..." (multi-line until "adalah subyek")
        if (preg_match('/Alamat\s*[:：]\s*(.*?)(?=\s*(?:adalah|Berdasarkan|Pemalang|Demikian|\n\s*\n))/is', $cleanText, $m)) {
            $alamat = trim($m[1]);
            $alamat = preg_replace('/\s*\n\s*/', ', ', $alamat);
            $alamat = preg_replace('/,\s*,/', ',', $alamat);
            $alamat = preg_replace('/\s{2,}/', ' ', $alamat);
            $data['alamat'] = strtoupper(trim($alamat, " ,\t\n\r"));
        }

        // 8. KEWARGANEGARAAN: Extract from "Pasal 4 huruf c, huruf d..." context
        // In Avidavit, citizenship is implied by the document itself (WNI by birth context)
        // Try to find explicit "Kewarganegaraan" field
        if (preg_match('/Kewarganegaraan\s*[:：]\s*([A-Za-z\s]+?)(?:\n|$)/i', $cleanText, $m)) {
            $data['kewarganegaraan'] = strtoupper(trim($m[1]));
        } else {
            // Default: document is about Kewarganegaraan RI
            $data['kewarganegaraan'] = 'INDONESIA';
        }

        // 9. KEWARGANEGARAAN AYAH - try to find after Nama Ayah
        // Look for "Kewarganegaraan" that appears after "Ayah" context
        if (preg_match('/Ayah\s*[:：].*?\n.*?Kewarganegaraan\s*[:：]\s*([A-Za-z\s]+?)(?:\n|$)/is', $cleanText, $m)) {
            $data['kewarganegaraan_ayah'] = strtoupper(trim($m[1]));
        }

        // 10. KEWARGANEGARAAN IBU - try to find after Nama Ibu
        if (preg_match('/Ibu\s*[:：].*?\n.*?Kewarganegaraan\s*[:：]\s*([A-Za-z\s]+?)(?:\n|$)/is', $cleanText, $m)) {
            $data['kewarganegaraan_ibu'] = strtoupper(trim($m[1]));
        }

        // Fallback gender detection from text
        if (!$data['jenis_kelamin']) {
            if (preg_match('/Jenis\s*Kelamin\s*[:：]\s*(LAKI[\s\-]*LAKI|PEREMPUAN|WANITA|PRIA|MALE|FEMALE|[LP])\b/i', $cleanText, $m)) {
                $data['jenis_kelamin'] = $this->transformer->normalizeGender($m[1]);
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

        // Try splitting by "K E T E R A N G A N" header
        $parts = preg_split('/(K\s*E\s*T\s*E\s*R\s*A\s*N\s*G\s*A\s*N)/i', $text, -1, PREG_SPLIT_DELIM_CAPTURE);

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
