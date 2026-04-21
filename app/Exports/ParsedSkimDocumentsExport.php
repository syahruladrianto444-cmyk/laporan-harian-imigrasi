<?php

namespace App\Exports;

use App\Models\ParsedSkimDocument;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class ParsedSkimDocumentsExport implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize
{
    public function collection()
    {
        $documents = ParsedSkimDocument::orderBy('created_at', 'asc')->get();

        return $documents->map(function ($doc, $index) {
            return [
                $index + 1,
                $doc->nama ?? '-',
                $doc->ttl ?? '-',
                $doc->niora ?? '-',
                $doc->status_sipil ?? '-',
                $doc->kewarganegaraan ?? '-',
                $doc->pekerjaan ?? '-',
                $doc->nomor_paspor ?? '-',
                $doc->jenis_keimigrasian ?? '-',
                $doc->alamat ?? '-',
                $doc->no_register ?? '-',
                $doc->jenis_kelamin ?? '-',
            ];
        });
    }

    public function headings(): array
    {
        return [
            'No',
            'Nama',
            'TTL',
            'Niora',
            'Status Sipil',
            'Kewarganegaraan',
            'Pekerjaan',
            'No. Paspor',
            'Jenis Keimigrasian',
            'Alamat',
            'No. Register',
            'Jenis Kelamin',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $sheet->getHighestRow();
        $lastCol = 'L'; // 12 columns = A to L

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

        $sheet->getRowDimension(1)->setRowHeight(30);

        return [];
    }
}
