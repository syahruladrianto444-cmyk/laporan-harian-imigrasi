<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Str;

class DataTransformerService
{
    /**
     * Month mappings for Indonesian and English month names
     */
    private array $monthMap = [
        // Indonesian
        'januari' => '01', 'februari' => '02', 'maret' => '03', 'april' => '04',
        'mei' => '05', 'juni' => '06', 'juli' => '07', 'agustus' => '08',
        'september' => '09', 'oktober' => '10', 'november' => '11', 'desember' => '12',
        // English
        'january' => '01', 'february' => '02', 'march' => '03',
        'may' => '05', 'june' => '06', 'july' => '07', 'august' => '08',
        'october' => '10', 'december' => '12',
        // Short English
        'jan' => '01', 'feb' => '02', 'mar' => '03', 'apr' => '04',
        'jun' => '06', 'jul' => '07', 'aug' => '08', 'sep' => '09',
        'oct' => '10', 'nov' => '11', 'dec' => '12',
    ];

    /**
     * Normalize a date string from various Indonesian/English formats to Y-m-d
     */
    public function normalizeDate(?string $dateString): ?string
    {
        if (empty($dateString)) {
            return null;
        }

        $dateString = trim($dateString);

        // Try Carbon parse directly first for standard formats
        try {
            // Handle dd/mm/yyyy or dd-mm-yyyy
            if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $dateString, $m)) {
                return Carbon::createFromDate($m[3], $m[2], $m[1])->format('Y-m-d');
            }

            // Handle yyyy-mm-dd
            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $dateString, $m)) {
                return $dateString;
            }

            // Handle "dd Month yyyy" or "dd MonthName yyyy" (English/Indonesian)
            if (preg_match('/(\d{1,2})\s+(\w+)\s+(\d{4})/i', $dateString, $m)) {
                $day = str_pad($m[1], 2, '0', STR_PAD_LEFT);
                $monthName = strtolower($m[2]);
                $year = $m[3];

                if (isset($this->monthMap[$monthName])) {
                    $month = $this->monthMap[$monthName];
                    return "{$year}-{$month}-{$day}";
                }
            }

            // Handle "Month dd, yyyy"
            if (preg_match('/(\w+)\s+(\d{1,2}),?\s+(\d{4})/i', $dateString, $m)) {
                $monthName = strtolower($m[1]);
                $day = str_pad($m[2], 2, '0', STR_PAD_LEFT);
                $year = $m[3];

                if (isset($this->monthMap[$monthName])) {
                    $month = $this->monthMap[$monthName];
                    return "{$year}-{$month}-{$day}";
                }
            }

            // Fallback: try Carbon
            return Carbon::parse($dateString)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Parse place and date of birth from combined string
     * Supports separators: / , -
     * Example: "SEOUL / 15-03-1985" or "TOKYO, 1985-03-15"
     */
    public function parsePlaceAndDateOfBirth(?string $ttlString): array
    {
        if (empty($ttlString)) {
            return ['tempat_lahir' => null, 'tanggal_lahir' => null];
        }

        $ttlString = trim($ttlString);

        // Try splitting by / first, then comma
        $separators = ['/', ','];

        foreach ($separators as $sep) {
            if (str_contains($ttlString, $sep)) {
                $parts = explode($sep, $ttlString, 2);
                $place = strtoupper(trim($parts[0]));
                $dateStr = trim($parts[1] ?? '');
                $date = $this->normalizeDate($dateStr);

                if ($date) {
                    return [
                        'tempat_lahir' => $place,
                        'tanggal_lahir' => $date,
                    ];
                }
            }
        }

        // If no separator found, try to extract date from end
        if (preg_match('/^(.+?)\s+(\d{1,2}[\s\-\/]\w+[\s\-\/]\d{4})$/i', $ttlString, $m)) {
            return [
                'tempat_lahir' => strtoupper(trim($m[1])),
                'tanggal_lahir' => $this->normalizeDate($m[2]),
            ];
        }

        return ['tempat_lahir' => strtoupper($ttlString), 'tanggal_lahir' => null];
    }

    /**
     * Normalize gender from English to L/P
     */
    public function normalizeGender(?string $gender): ?string
    {
        if (empty($gender)) {
            return null;
        }

        // Remove non-word characters and whitespace to handle "LAKI - LAKI" etc.
        $gender = strtoupper(trim($gender));
        $clean = preg_replace('/[^A-Z]/', '', $gender);

        return match (true) {
            in_array($clean, ['MALE', 'LAKILAKI', 'LAKI', 'L', 'M']) => 'L',
            in_array($clean, ['FEMALE', 'PEREMPUAN', 'WANITA', 'P', 'F']) => 'P',
            default => null,
        };
    }

    /**
     * Normalize document type
     */
    public function normalizeDocumentType(?string $type): ?string
    {
        if (empty($type)) {
            return null;
        }

        $type = strtoupper(trim($type));

        // Normalize variations
        $type = str_replace(['/', '-', ' '], '', $type);

        return match (true) {
            str_contains($type, 'ITAS') => 'ITAS',
            str_contains($type, 'ITK') => 'ITK',
            str_contains($type, 'IMK') => 'IMK',
            str_contains($type, 'TSP') || str_contains($type, 'EPO') => 'TSP-EPO',
            str_contains($type, 'ITAP') => 'ITAP',
            default => $type,
        };
    }

    /**
     * Convert all string fields to uppercase
     */
    public function toUpperCase(?string $value): ?string
    {
        return $value ? strtoupper(trim($value)) : null;
    }
}
