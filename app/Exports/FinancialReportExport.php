<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class FinancialReportExport implements FromView, WithColumnWidths, WithStyles, WithTitle
{
    protected $reportData;

    public function __construct(array $reportData)
    {
        $this->reportData = $reportData;
    }

    public function view(): View
    {
        return view('reports.excel.financial', [
            'reportData' => $this->reportData
        ]);
    }

    public function columnWidths(): array
    {
        return [
            'A' => 15, // Month
            'B' => 15, // Revenue
            'C' => 15, // Profit
            'D' => 12, // Sales Count
            'E' => 15, // Avg Sale Value
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 12]],
            'A1:E1' => [
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '007bff']
                ],
                'font' => ['color' => ['rgb' => 'FFFFFF']]
            ],
        ];
    }

    public function title(): string
    {
        return 'Financial Report';
    }
}