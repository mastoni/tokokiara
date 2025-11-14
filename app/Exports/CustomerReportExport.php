<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CustomerReportExport implements FromView, WithColumnWidths, WithStyles, WithTitle
{
    protected $reportData;

    public function __construct(array $reportData)
    {
        $this->reportData = $reportData;
    }

    public function view(): View
    {
        return view('reports.excel.customers', [
            'reportData' => $this->reportData
        ]);
    }

    public function columnWidths(): array
    {
        return [
            'A' => 20, // Name
            'B' => 25, // Email
            'C' => 15, // Phone
            'D' => 20, // Company
            'E' => 15, // Loyalty Points
            'F' => 12, // Balance
            'G' => 15, // Total Spent
            'H' => 12, // Total Orders
            'I' => 15, // Avg Order Value
            'J' => 15, // First Purchase
            'K' => 15, // Last Purchase
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 12]],
            'A1:K1' => [
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
        return 'Customer Report';
    }
}