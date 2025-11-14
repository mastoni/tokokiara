<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class InventoryReportExport implements FromView, WithColumnWidths, WithStyles, WithTitle
{
    protected $reportData;

    public function __construct(array $reportData)
    {
        $this->reportData = $reportData;
    }

    public function view(): View
    {
        return view('reports.excel.inventory', [
            'reportData' => $this->reportData
        ]);
    }

    public function columnWidths(): array
    {
        return [
            'A' => 25, // Product Name
            'B' => 12, // SKU
            'C' => 15, // Barcode
            'D' => 15, // Category
            'E' => 15, // Brand
            'F' => 10, // Quantity
            'G' => 12, // Cost Price
            'H' => 12, // Sale Price
            'I' => 12, // Cost Value
            'J' => 12, // Sale Value
            'K' => 15, // Potential Profit
            'L' => 12, // Profit Margin
            'M' => 12, // Stock Status
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 12]],
            'A1:M1' => [
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
        return 'Inventory Report';
    }
}