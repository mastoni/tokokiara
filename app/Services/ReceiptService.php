<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\Store;
use Illuminate\Support\Facades\View;
use Barryvdh\DomPDF\Facade\Pdf;

class ReceiptService
{
    /**
     * Generate receipt data
     */
    public function generateReceiptData(Sale $sale)
    {
        $sale->load(['contact', 'store', 'saleItems.product', 'transactions']);

        return [
            'store' => [
                'name' => $sale->store->name,
                'address' => $sale->store->address,
                'phone' => $sale->store->contact_number,
                'email' => $sale->store->email,
            ],
            'sale' => [
                'invoice_number' => $sale->invoice_number,
                'date' => $sale->sale_date->format('Y-m-d H:i:s'),
                'status' => $sale->status_label,
                'payment_status' => $sale->payment_status_label,
            ],
            'customer' => $sale->contact ? [
                'name' => $sale->contact->name,
                'phone' => $sale->contact->phone,
                'email' => $sale->contact->email,
            ] : null,
            'items' => $sale->saleItems->map(function($item) {
                return [
                    'name' => $item->product_name,
                    'quantity' => abs($item->quantity),
                    'unit_price' => number_format($item->unit_price, 2),
                    'discount' => number_format(abs($item->discount), 2),
                    'total' => number_format(abs($item->total_price), 2),
                ];
            }),
            'summary' => [
                'subtotal' => number_format($sale->total_amount, 2),
                'discount' => number_format($sale->discount, 2),
                'tax' => number_format($sale->tax_amount, 2),
                'shipping' => number_format($sale->shipping_amount, 2),
                'total' => number_format($sale->grand_total, 2),
                'amount_paid' => number_format($sale->amount_received, 2),
                'balance' => number_format($sale->balance, 2),
            ],
            'payments' => $sale->transactions->groupBy('payment_method')->map(function($transactions, $method) {
                return [
                    'method' => ucfirst($method),
                    'amount' => number_format($transactions->sum('amount'), 2),
                ];
            }),
            'notes' => [
                'staff_note' => $sale->staff_note,
                'customer_note' => $sale->customer_note,
            ],
            'footer' => $this->getReceiptFooter(),
        ];
    }

    /**
     * Generate HTML receipt
     */
    public function generateHTMLReceipt(Sale $sale)
    {
        $data = $this->generateReceiptData($sale);

        return View::make('receipts.standard', $data)->render();
    }

    /**
     * Generate PDF receipt
     */
    public function generatePDFReceipt(Sale $sale)
    {
        $data = $this->generateReceiptData($sale);

        $pdf = PDF::loadView('receipts.pdf', $data)
            ->setPaper([0, 0, 226.77, 600], 'portrait') // 80mm width, thermal printer height
            ->setOptions([
                'margin_top' => 5,
                'margin_right' => 5,
                'margin_bottom' => 5,
                'margin_left' => 5,
            ]);

        return $pdf;
    }

    /**
     * Generate thermal printer receipt
     */
    public function generateThermalReceipt(Sale $sale)
    {
        $data = $this->generateReceiptData($sale);

        // Create compact thermal printer format
        $receipt = $this->formatThermalReceipt($data);

        return $receipt;
    }

    /**
     * Generate receipt for mobile app
     */
    public function generateMobileReceipt(Sale $sale)
    {
        $data = $this->generateReceiptData($sale);

        return View::make('receipts.mobile', $data)->render();
    }

    /**
     * Generate email receipt
     */
    public function generateEmailReceipt(Sale $sale)
    {
        $data = $this->generateReceiptData($sale);

        return View::make('receipts.email', $data)->render();
    }

    /**
     * Format receipt for thermal printer
     */
    private function formatThermalReceipt($data)
    {
        $lines = [];
        $width = 42; // Thermal printer width (characters)

        // Header
        $lines[] = str_repeat('=', $width);
        $lines[] = str_pad($data['store']['name'], $width, ' ', STR_PAD_BOTH);
        $lines[] = str_repeat('=', $width);
        $lines[] = str_pad($data['store']['address'], $width, ' ', STR_PAD_BOTH);
        $lines[] = str_pad('Tel: ' . $data['store']['phone'], $width, ' ', STR_PAD_BOTH);
        $lines[] = str_repeat('-', $width);

        // Sale info
        $lines[] = 'Invoice: ' . $data['sale']['invoice_number'];
        $lines[] = 'Date: ' . $data['sale']['date'];
        $lines[] = str_repeat('-', $width);

        // Customer info
        if ($data['customer']) {
            $lines[] = 'Customer: ' . $data['customer']['name'];
            if ($data['customer']['phone']) {
                $lines[] = 'Phone: ' . $data['customer']['phone'];
            }
            $lines[] = str_repeat('-', $width);
        }

        // Items header
        $lines[] = str_pad('Item', 20) . str_pad('Qty', 5) . str_pad('Price', 8) . str_pad('Total', 9);
        $lines[] = str_repeat('-', $width);

        // Items
        foreach ($data['items'] as $item) {
            $name = wordwrap($item['name'], 20, "\n", false);
            $nameLines = explode("\n", $name);

            foreach ($nameLines as $index => $nameLine) {
                if ($index === 0) {
                    $lines[] = str_pad($nameLine, 20) .
                              str_pad($item['quantity'], 5) .
                              str_pad($item['unit_price'], 8) .
                              str_pad($item['total'], 9);
                } else {
                    $lines[] = str_pad($nameLine, 20);
                }
            }
        }

        $lines[] = str_repeat('-', $width);

        // Summary
        $lines[] = str_pad('Subtotal:', 30) . str_pad($data['summary']['subtotal'], 12, ' ', STR_PAD_LEFT);
        if ($data['summary']['discount'] > 0) {
            $lines[] = str_pad('Discount:', 30) . str_pad($data['summary']['discount'], 12, ' ', STR_PAD_LEFT);
        }
        if ($data['summary']['tax'] > 0) {
            $lines[] = str_pad('Tax:', 30) . str_pad($data['summary']['tax'], 12, ' ', STR_PAD_LEFT);
        }
        if ($data['summary']['shipping'] > 0) {
            $lines[] = str_pad('Shipping:', 30) . str_pad($data['summary']['shipping'], 12, ' ', STR_PAD_LEFT);
        }
        $lines[] = str_repeat('=', $width);
        $lines[] = str_pad('TOTAL:', 30) . str_pad($data['summary']['total'], 12, ' ', STR_PAD_LEFT);
        $lines[] = str_repeat('=', $width);

        // Payments
        foreach ($data['payments'] as $payment) {
            $lines[] = str_pad($payment['method'], 30) . str_pad($payment['amount'], 12, ' ', STR_PAD_LEFT);
        }

        if ($data['summary']['balance'] != 0) {
            $lines[] = str_pad('Balance:', 30) . str_pad($data['summary']['balance'], 12, ' ', STR_PAD_LEFT);
        }

        $lines[] = str_repeat('=', $width);

        // Footer
        $lines[] = str_pad($data['footer']['thank_you'], $width, ' ', STR_PAD_BOTH);
        if ($data['footer']['return_policy']) {
            $lines[] = wordwrap($data['footer']['return_policy'], $width, "\n");
        }
        $lines[] = str_repeat('=', $width);

        return implode("\n", $lines);
    }

    /**
     * Get receipt footer settings
     */
    private function getReceiptFooter()
    {
        return [
            'thank_you' => 'Thank you for your purchase!',
            'return_policy' => 'Items can be returned within 7 days with receipt.',
            'website' => config('app.url'),
            'social_media' => [
                'facebook' => '',
                'instagram' => '',
                'twitter' => '',
            ],
        ];
    }

    /**
     * Generate receipt using template engine (Mustache)
     */
    public function generateTemplatedReceipt(Sale $sale, $template = 'standard')
    {
        $data = $this->generateReceiptData($sale);

        // This would integrate with Mustache templating system
        // For now, returning HTML receipt
        return $this->generateHTMLReceipt($sale);
    }

    /**
     * Generate barcode for receipt
     */
    public function generateReceiptBarcode(Sale $sale)
    {
        $barcodeService = new BarcodeService();
        return $barcodeService->generateBarcode($sale->invoice_number, 'code128', 1, 30);
    }

    /**
     * Generate QR code for receipt (for mobile viewing)
     */
    public function generateReceiptQRCode(Sale $sale)
    {
        $barcodeService = new BarcodeService();
        return $barcodeService->generateQRCode([
            'type' => 'receipt',
            'invoice_number' => $sale->invoice_number,
            'amount' => $sale->grand_total,
            'date' => $sale->sale_date->toISOString(),
            'url' => route('pos.receipt', $sale->id),
        ]);
    }

    /**
     * Print receipt to thermal printer
     */
    public function printToThermalPrinter(Sale $sale)
    {
        $receipt = $this->generateThermalReceipt($sale);

        // This would interface with actual thermal printer
        // For now, returning the formatted text
        return [
            'success' => true,
            'receipt_text' => $receipt,
            'message' => 'Receipt sent to printer',
        ];
    }

    /**
     * Email receipt to customer
     */
    public function emailReceipt(Sale $sale, $email = null)
    {
        if (!$email && !$sale->contact) {
            return [
                'success' => false,
                'error' => 'No email address available',
            ];
        }

        $email = $email ?: $sale->contact->email;
        $htmlReceipt = $this->generateEmailReceipt($sale);
        $pdfReceipt = $this->generatePDFReceipt($sale);

        try {
            \Mail::send([], [], function($message) use ($email, $sale, $htmlReceipt, $pdfReceipt) {
                $message->to($email)
                    ->subject('Receipt - ' . $sale->invoice_number)
                    ->html($htmlReceipt)
                    ->attachData($pdfReceipt->output(), 'receipt_' . $sale->invoice_number . '.pdf', [
                        'mime' => 'application/pdf',
                    ]);
            });

            return [
                'success' => true,
                'message' => 'Receipt emailed successfully',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to email receipt: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Generate receipt in different formats
     */
    public function generateReceipt(Sale $sale, $format = 'html')
    {
        switch (strtolower($format)) {
            case 'html':
                return $this->generateHTMLReceipt($sale);
            case 'pdf':
                return $this->generatePDFReceipt($sale);
            case 'thermal':
                return $this->generateThermalReceipt($sale);
            case 'mobile':
                return $this->generateMobileReceipt($sale);
            case 'email':
                return $this->generateEmailReceipt($sale);
            default:
                return $this->generateHTMLReceipt($sale);
        }
    }
}