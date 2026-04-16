<?php

namespace App\Services;

use Smalot\PdfParser\Parser;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class PdfParserService
{
    private DataTransformerService $transformer;

    public function __construct(DataTransformerService $transformer)
    {
        $this->transformer = $transformer;
    }

    /**
     * Extract data from a PDF file
     */
    public function extractFromFile(string $filePath): array
    {
        $parser = new Parser();
        $pdf = $parser->parseFile($filePath);
        $text = $pdf->getText();

        // Log the raw text for debugging
        Log::info('PDF Raw Text Extract', ['file' => basename($filePath), 'text' => $text]);

        return $this->parseText($text);
    }

    /**
     * Parse extracted text into structured data
     *
     * Known PDF Layout (from ITAS sample):
     * -----------------------------------------------
     * MINISTRY OF IMMIGRATION AND CORRECTIONS...
     * DIRECTORATE GENERAL OF IMMIGRATION
     *                                        ITAS
     *                                    RESIDENCE PERMIT
     * [PHOTO]  TANG LIXIN
     *          PERMIT NUMBER    : C202C42LF000571-C
     *          STAY PERMIT EXPIRY : 17/03/2027
     *
     *          RESIDENCE PERMIT
     *          IZIN TINGGAL TERBATAS
     *
     * Place / Date of Birth    : HEBEI / 09-08-1991
     * Passport Number          : EP2163694
     * Passport Expiry          : 18-02-2035
     * Nationality              : CHINA
     * Gender                   : MALE
     * Address                  : KAVLING F-07, KEDAWUNG...
     *                            BATANG, KAB. BATANG, JAWA TENGAH
     * Occupation               : ELECTRICAL MANAGER
     * Guarantor                : PT ELECMETAL LONGTENG INDONESIA
     * Activity                 : EMPLOYMENT
     *
     * ...DISCLAIMER...
     *                          Kab. Batang, 17 March 2026
     *            KANTOR IMIGRASI KELAS II NON TPI PEMALANG
     * -----------------------------------------------
     */
    private function parseText(string $text): array
    {
        $data = [
            'nama' => null,
            'kebangsaan' => null,
            'jenis_kelamin' => null,
            'tempat_lahir' => null,
            'tanggal_lahir' => null,
            'nomor_paspor' => null,
            'tanggal_expired_paspor' => null,
            'tipe_dokumen' => null,
            'nomor_dokumen' => null,
            'tanggal_expired_itas' => null,
            'alamat' => null,
            'penjamin' => null,
            'tanggal_terbit' => null,
        ];

        // Normalize text: collapse multiple spaces but keep newlines
        $cleanText = preg_replace('/[^\S\n]+/', ' ', $text);
        // Also make a version with no newlines for multi-line matching
        $flatText = preg_replace('/\s+/', ' ', $text);

        // 1. DOCUMENT TYPE - ITAS/ITK/IMK/ITAP/TSP-EPO
        $data['tipe_dokumen'] = $this->extractDocumentType($cleanText);

        // 2. NAME - Located in the blue header block, right of photo
        $data['nama'] = $this->extractName($cleanText, $flatText);

        // 3. PERMIT NUMBER
        $data['nomor_dokumen'] = $this->extractField($cleanText, $flatText, [
            '/PERMIT\s*NUMBER\s*[:：]\s*([A-Z0-9][\w\-\/\.]+)/i',
            '/No\.?\s*(?:ITAS|ITK|IMK|ITAP|Izin)\s*[:：]\s*([A-Z0-9][\w\-\/\.]+)/i',
        ]);

        // 4. STAY PERMIT EXPIRY
        $expiryRaw = $this->extractField($cleanText, $flatText, [
            '/STAY\s*PERMIT\s*EXPIRY\s*[:：]\s*([\d\/\-\.]+)/i',
            '/(?:Berlaku|Valid)\s*(?:s\.?d\.?|Hingga|Until)\s*[:：]?\s*([\d\/\-\.]+)/i',
            '/(?:Masa\s*Berlaku|Expiry)\s*[:：]\s*([\d\/\-\.]+)/i',
        ]);
        $data['tanggal_expired_itas'] = $this->transformer->normalizeDate($expiryRaw);

        // 5. PLACE / DATE OF BIRTH
        $ttlRaw = $this->extractField($cleanText, $flatText, [
            '/Place\s*\/?\s*Date\s*of\s*Birth\s*[:：]\s*(.+?)(?:\n|$)/i',
            '/Tempat\s*\/?\s*(?:Tanggal|Tgl)\s*Lahir\s*[:：]\s*(.+?)(?:\n|$)/i',
            '/TTL\s*[:：]\s*(.+?)(?:\n|$)/i',
        ]);
        if ($ttlRaw) {
            $ttl = $this->transformer->parsePlaceAndDateOfBirth($ttlRaw);
            $data['tempat_lahir'] = $ttl['tempat_lahir'];
            $data['tanggal_lahir'] = $ttl['tanggal_lahir'];
        }

        // 6. PASSPORT NUMBER
        $data['nomor_paspor'] = $this->extractField($cleanText, $flatText, [
            '/Passport\s*Number\s*[:：]\s*([A-Z0-9]{5,20})/i',
            '/No(?:mor)?\.?\s*Paspor\s*[:：]\s*([A-Z0-9]{5,20})/i',
            '/Paspor\s*[:：]\s*([A-Z0-9]{5,20})/i',
        ]);

        // 7. PASSPORT EXPIRY
        $passExpRaw = $this->extractField($cleanText, $flatText, [
            '/Passport\s*Expiry\s*[:：]\s*([\d\-\/\.]+\s*\w*\s*\d*)/i',
            '/(?:Tanggal\s*)?(?:Habis\s*)?Berlaku\s*Paspor\s*[:：]\s*([\d\-\/\.]+)/i',
            '/Passport\s*Expired?\s*[:：]\s*([\d\-\/\.]+)/i',
        ]);
        $data['tanggal_expired_paspor'] = $this->transformer->normalizeDate($passExpRaw);

        // 8. NATIONALITY
        $data['kebangsaan'] = $this->extractField($cleanText, $flatText, [
            '/Nationality\s*[:：]\s*([A-Za-z\s]+?)(?:\n|$)/i',
            '/Kebangsaan\s*[:：]\s*([A-Za-z\s]+?)(?:\n|$)/i',
            '/Kewarganegaraan\s*[:：]\s*([A-Za-z\s]+?)(?:\n|$)/i',
        ]);
        // Clean up nationality - remove trailing label words
        if ($data['kebangsaan']) {
            $data['kebangsaan'] = preg_replace('/\s*(Gender|Sex|Jenis|Address|Alamat|Place|Tempat).*$/i', '', $data['kebangsaan']);
            $data['kebangsaan'] = trim($data['kebangsaan']);
        }

        // 9. GENDER
        $genderRaw = $this->extractField($cleanText, $flatText, [
            '/Gender\s*[:：]\s*(MALE|FEMALE|LAKI[\s\-]*LAKI|PEREMPUAN|[MF])\b/i',
            '/(?:Sex|Jenis\s*Kelamin)\s*[:：]\s*(MALE|FEMALE|LAKI[\s\-]*LAKI|PEREMPUAN|[LPMF])\b/i',
        ]);
        $data['jenis_kelamin'] = $this->transformer->normalizeGender($genderRaw);

        // 10. ADDRESS (multi-line: between "Address" and "Occupation")
        $data['alamat'] = $this->extractAddress($cleanText, $flatText);

        // 11. GUARANTOR / SPONSOR
        $data['penjamin'] = $this->extractField($cleanText, $flatText, [
            '/Guarantor\s*[:：]\s*(.+?)(?:\n|$)/i',
            '/Penjamin\s*[:：]\s*(.+?)(?:\n|$)/i',
            '/Sponsor\s*[:：]\s*(.+?)(?:\n|$)/i',
        ]);
        // Clean guarantor from trailing labels
        if ($data['penjamin']) {
            $data['penjamin'] = preg_replace('/\s*(Activity|Aktivitas|DISCLAIMER).*$/i', '', $data['penjamin']);
            $data['penjamin'] = trim($data['penjamin']);
        }

        // 12. APPLICATION DATE (bottom of PDF: "Kab. Batang, 17 March 2026")
        $data['tanggal_terbit'] = $this->extractApplicationDate($cleanText, $flatText);

        // Uppercase all text fields
        foreach (['nama', 'kebangsaan', 'tempat_lahir', 'nomor_paspor', 'nomor_dokumen', 'alamat', 'penjamin'] as $field) {
            $data[$field] = $this->transformer->toUpperCase($data[$field]);
        }

        return $data;
    }

    /**
     * Generic field extractor - tries patterns on both cleaned and flat text
     */
    private function extractField(string $cleanText, string $flatText, array $patterns): ?string
    {
        // Try on the cleaned text first (preserves newlines)
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $cleanText, $match)) {
                $val = trim($match[1]);
                if (strlen($val) >= 1) {
                    return $val;
                }
            }
        }

        // Try on flat text (no newlines) as fallback
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $flatText, $match)) {
                $val = trim($match[1]);
                if (strlen($val) >= 1) {
                    return $val;
                }
            }
        }

        return null;
    }

    /**
     * Extract document type (ITAS, ITK, IMK, TSP/EPO, ITAP)
     */
    private function extractDocumentType(string $text): ?string
    {
        // 1. Try to find explicit document titles in the first 1000 chars (header)
        // This avoids matching "Limited Stay Permit" inside an ITK disclaimer.
        $headerText = substr($text, 0, 1000);
        $headerPatterns = [
            'ITAS' => [
                '/\bITAS\b/',
                '/IZIN\s*TINGGAL\s*TERBATAS/i',
                '/LIMITED\s*STAY\s*PERMIT/i',
                '/RESIDENCE\s*PERMIT/i',
            ],
            'ITAP' => [
                '/\bITAP\b/',
                '/IZIN\s*TINGGAL\s*TETAP/i',
                '/PERMANENT\s*STAY\s*PERMIT/i',
            ],
            'ITK' => [
                '/\bITK\b/',
                '/IZIN\s*TINGGAL\s*KUNJUNGAN/i',
            ],
            'IMK' => [
                '/\bIMK\b/',
                '/IZIN\s*MASUK\s*KEMBALI/i',
                '/RE[\s\-]*ENTRY\s*PERMIT/i',
            ],
            'TSP-EPO' => [
                '/\bTSP[\s\/\-]*EPO\b/i',
                '/EXIT\s*PERMIT\s*ONLY/i',
            ],
        ];

        $priority = ['TSP-EPO', 'ITAP', 'IMK', 'ITK', 'ITAS'];
        foreach ($priority as $type) {
            foreach ($headerPatterns[$type] as $pattern) {
                if (preg_match($pattern, $headerText)) {
                    return $type;
                }
            }
        }

        // 2. Fallback: Identify by unique disclaimer paragraphs in the full text
        // Because the blue header image isn't always parsed into text.
        $disclaimerPatterns = [
            'ITK' => [
                '/Visit\s*Stay\s*Permit\s*originating\s*from/i',
                '/Extension\s*of\s*Stay\s*Permit\s*originating\s*from\s*Visit\s*Visa/i'
            ],
            'ITAS' => [
                '/converted\s*(?:it\s*)?into\s*permanent\s*stay\s*permit/i',
                '/maximum\s*stay\s*up\s*to\s*6\s*years/i'
            ],
        ];

        foreach (['ITK', 'ITAS'] as $type) {
            foreach ($disclaimerPatterns[$type] as $pattern) {
                if (preg_match($pattern, $text)) {
                    return $type;
                }
            }
        }

        return null;
    }

    /**
     * Extract applicant name from the blue header block
     *
     * In the PDF, the name appears in the styled header section,
     * typically as large text right of the photo. Patterns:
     * - Right before "PERMIT NUMBER"
     * - After document type header and before permit details
     * - As a standalone all-caps line in the header area
     */
    private function extractName(string $cleanText, string $flatText): ?string
    {
        $patterns = [
            // Pattern: Name line right before "PERMIT NUMBER"
            // "TANG LIXIN\nPERMIT NUMBER" or "TANG LIXIN PERMIT NUMBER"
            '/([A-Z][A-Z\s\.\-\',]{2,}?)\s*\n\s*PERMIT\s*NUMBER/i',

            // Flat text: name before PERMIT NUMBER
            '/(?:IMMIGRATION|IMIGRASI)\s+([A-Z][A-Z\s\.\-\',]{2,}?)\s+PERMIT\s*NUMBER/i',

            // After "RESIDENCE PERMIT" header line then name block
            '/(?:RESIDENCE\s*PERMIT|STAY\s*PERMIT).*?\n\s*([A-Z][A-Z\s\.\-\',]{2,}?)\s*\n\s*PERMIT/is',

            // Name as all-caps block followed by permit number on next line
            '/\n\s*([A-Z][A-Z\s]{2,}?)\s*\n.*?PERMIT\s*NUMBER/s',

            // Generic: after DIRECTORATE... find caps name before PERMIT
            '/DIRECTORATE.*?([A-Z][A-Z\s\.\-\',]{3,}?)\s*(?:\n\s*)?PERMIT\s*NUMBER/is',

            // Fallback: the line right before "PERMIT NUMBER" on flat text
            '/\b([A-Z][A-Z\s\.\-\',]{3,}?)\s+PERMIT\s*NUMBER/i',
        ];

        foreach ($patterns as $pattern) {
            // Try on clean text first, then flat text
            foreach ([$cleanText, $flatText] as $source) {
                if (preg_match($pattern, $source, $match)) {
                    $name = trim($match[1]);
                    // Remove noise: trailing numbers, document type words
                    $name = preg_replace('/\s*\d+\s*$/', '', $name);
                    $name = preg_replace('/\s*(ITAS|ITAP|ITK|IMK|TSP|EPO|RESIDENCE|STAY|PERMIT|INDEX|QR)\s*/i', '', $name);
                    $name = trim($name);

                    // Validate: name should be at least 2 chars, mostly letters
                    if (strlen($name) >= 2 && preg_match('/^[A-Z\s\.\-\',]+$/i', $name)) {
                        return $name;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Extract address (multi-line, between Address and Occupation labels)
     */
    private function extractAddress(string $cleanText, string $flatText): ?string
    {
        // Pattern 1: Between "Address" and "Occupation" (multi-line)
        $patterns = [
            // Cleaned text with newlines
            '/(?:Address|Alamat)\s*[:：]\s*(.*?)(?=\s*(?:Occupation|Pekerjaan|Jabatan))/is',
            // Flat text fallback
            '/(?:Address|Alamat)\s*[:：]\s*(.*?)(?=\s*(?:Occupation|Pekerjaan|Jabatan))/i',
            // If no Occupation label, try until Guarantor
            '/(?:Address|Alamat)\s*[:：]\s*(.*?)(?=\s*(?:Guarantor|Penjamin|Sponsor))/is',
        ];

        foreach ($patterns as $i => $pattern) {
            $source = ($i < 2) ? $cleanText : $flatText;
            if (preg_match($pattern, $source, $match)) {
                $address = trim($match[1]);
                // Normalize multiline: replace newlines with ", "
                $address = preg_replace('/\s*\n\s*/', ', ', $address);
                // Clean up multiple commas/spaces
                $address = preg_replace('/,\s*,/', ',', $address);
                $address = preg_replace('/\s{2,}/', ' ', $address);
                $address = trim($address, " ,\t\n\r");

                if (strlen($address) >= 5) {
                    return $address;
                }
            }
        }

        return null;
    }

    /**
     * Extract application date from bottom of document
     * Format: "Kab. Batang, 17 March 2026"
     */
    private function extractApplicationDate(string $cleanText, string $flatText): ?string
    {
        $monthNames = 'January|February|March|April|May|June|July|August|September|October|November|December|'
                     . 'Januari|Februari|Maret|April|Mei|Juni|Juli|Agustus|September|Oktober|November|Desember';

        $patterns = [
            // "City, DD Month YYYY" at bottom
            '/(?:[\w\.\s]+,\s*)?(\d{1,2}\s+(?:' . $monthNames . ')\s+\d{4})/i',
            // Near KANTOR IMIGRASI
            '/(\d{1,2}\s+(?:' . $monthNames . ')\s+\d{4}).*?KANTOR\s*IMIGRASI/is',
            // Generic date label
            '/(?:Tanggal|Date|Tgl)\s*(?:Permohonan|Terbit|Cetak)?\s*[:：]\s*(\d{1,2}[\s\/\-](?:' . $monthNames . '|\d{1,2})[\s\/\-]\d{4})/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $cleanText, $matches)) {
                // Take the LAST occurrence (bottom of document)
                $lastMatch = end($matches[1]);
                $date = $this->transformer->normalizeDate($lastMatch);
                if ($date) {
                    return $date;
                }
            }
        }

        // Fallback: try flat text
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $flatText, $matches)) {
                $lastMatch = end($matches[1]);
                $date = $this->transformer->normalizeDate($lastMatch);
                if ($date) {
                    return $date;
                }
            }
        }

        return null;
    }
}
