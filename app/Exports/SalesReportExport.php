<?php

namespace App\Exports;

use App\Models\Sale;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SalesReportExport implements FromView, WithColumnWidths, WithStyles, WithTitle
{
    protected $reportData;

    public function __construct(array $reportData)
    {
        $this->reportData = $reportData;
    }

    public function view(): View
    {
        return view('reports.excel.sales', [
            'reportData' => $this->reportData
        ]);
    }

    public function columnWidths(): array
    {
        return [
            'A' => 15, // Invoice #
            'B' => 12, // Date
            'C' => 20, // Customer
            'D' => 15, // Store
            'E' => 12, // Amount
            'F' => 12, // Profit
            'G' => 10, // Status
            'H' => 15, // Payment Status
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 12]],
            'A1:H1' => [
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
        return 'Sales Report';
    }
}