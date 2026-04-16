<?php

namespace App\Exports;

use App\Models\ParsedDocument;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Carbon\Carbon;

class ParsedDocumentsExport implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize
{
    public function collection()
    {
        $documents = ParsedDocument::orderBy('created_at', 'asc')->get();

        return $documents->map(function ($doc, $index) {
            // Format TTL: TEMPAT / DD-MM-YYYY
            $ttl = null;
            if ($doc->tempat_lahir || $doc->tanggal_lahir) {
                $placePart = $doc->tempat_lahir ?? '';
                $datePart = $doc->tanggal_lahir ? $doc->tanggal_lahir->format('d-m-Y') : '';
                $ttl = $placePart . ($placePart && $datePart ? ' / ' : '') . $datePart;
            }

            // Format tanggal permohonan: d-M-y (e.g., 17-Mar-26)
            $tanggalPermohonan = $doc->tanggal_terbit
                ? $doc->tanggal_terbit->format('d-M-y')
                : null;

            // Format masa berlaku paspor: d/m/Y
            $masaBerlakuPaspor = $doc->tanggal_expired_paspor
                ? $doc->tanggal_expired_paspor->format('d/m/Y')
                : null;

            // Format masa berlaku ITAS: d/m/Y
            $masaBerlakuItas = $doc->tanggal_expired_itas
                ? $doc->tanggal_expired_itas->format('d/m/Y')
                : null;

            // Determine which document type column to fill
            $noItas  = $doc->tipe_dokumen === 'ITAS'    ? $doc->nomor_dokumen : null;
            $noItk   = $doc->tipe_dokumen === 'ITK'     ? $doc->nomor_dokumen : null;
            $noImk   = $doc->tipe_dokumen === 'IMK'     ? $doc->nomor_dokumen : null;
            $noTsp   = $doc->tipe_dokumen === 'TSP-EPO' ? $doc->nomor_dokumen : null;
            $noItap  = $doc->tipe_dokumen === 'ITAP'    ? $doc->nomor_dokumen : null;

            return [
                $index + 1,                                             // 1. No
                $tanggalPermohonan,                                     // 2. Tanggal Permohonan
                $doc->nama,                                             // 3. Name
                $ttl,                                                   // 4. TTL
                $doc->nomor_paspor,                                     // 5. No. Paspor
                $masaBerlakuPaspor,                                     // 6. Masa Berlaku Paspor
                $doc->kebangsaan,                                       // 7. Kebangsaan
                $doc->penjamin,                                         // 8. SPONSOR
                $doc->alamat,                                           // 9. Alamat
                $noItas,                                                // 10. No. ITAS
                $noItk,                                                 // 11. NO. ITK
                $noImk,                                                 // 12. NO. IMK
                $noTsp,                                                 // 13. NO. TSP / EPO
                $noItap,                                                // 14. NO. ITAP
                $masaBerlakuItas,                                       // 15. Masa Berlaku
                $doc->jenis_kelamin,                                    // 16. Jenis Kelamin
            ];
        });
    }

    public function headings(): array
    {
        return [
            'No',
            'Tanggal Permohonan',
            'Name',
            'TTL',
            'No. Paspor',
            'Masa Berlaku Paspor',
            'Kebangsaan',
            'SPONSOR',
            'Alamat',
            'No. ITAS',
            'NO. ITK',
            'NO. IMK',
            'NO. TSP / EPO',
            'NO. ITAP',
            'Masa Berlaku',
            'Jenis Kelamin',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $sheet->getHighestRow();
        $lastCol = 'P'; // 16 columns = A to P

        // Header style
        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 10,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0B3C8C'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
            ],
        ]);

        // Data rows style
        if ($lastRow > 1) {
            $sheet->getStyle("A2:{$lastCol}{$lastRow}")->applyFromArray([
                'font' => ['size' => 9],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_TOP,
                    'wrapText' => true,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'CCCCCC'],
                    ],
                ],
            ]);

            // Alternate row colors
            for ($row = 2; $row <= $lastRow; $row++) {
                if ($row % 2 === 0) {
                    $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'EEF3FB'],
                        ],
                    ]);
                }
            }
        }

        // Set row height for header
        $sheet->getRowDimension(1)->setRowHeight(30);

        return [];
    }
}
