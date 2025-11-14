<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Str;

class BarcodeService
{
    /**
     * Generate a barcode image for a product
     */
    public function generateBarcode($barcode, $format = 'code128', $width = 2, $height = 60, $options = [])
    {
        try {
            $generator = new \Picqer\Barcode\BarcodeGeneratorPNG();

            $barcodeImage = $generator->getBarcode($barcode, $format, $width, $height, $options);

            return base64_encode($barcodeImage);
        } catch (\Exception $e) {
            \Log::error('Barcode generation error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Generate barcode with product label
     */
    public function generateProductLabel($product, $template = 'standard')
    {
        try {
            $barcode = $this->generateBarcode($product->barcode);

            if (!$barcode) {
                return null;
            }

            // Create label image
            $labelWidth = 300;
            $labelHeight = 200;
            $image = Image::canvas($labelWidth, $labelHeight, '#ffffff');

            // Add product name
            $productName = Str::limit($product->name, 30);
            $image->text($productName, $labelWidth / 2, 30, function($font) {
                $font->file(1);
                $font->size(14);
                $font->color('#000000');
                $font->align('center');
            });

            // Add barcode
            $barcodeData = base64_decode($barcode);
            $barcodeImg = Image::make($barcodeData);
            $barcodeImg->resize($labelWidth - 40, 80, function($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });

            $image->insert($barcodeImg, 'top', 20, 50);

            // Add price
            if ($product->price) {
                $priceText = '$' . number_format($product->price, 2);
                $image->text($priceText, $labelWidth / 2, 150, function($font) {
                    $font->file(1);
                    $font->size(16);
                    $font->color('#000000');
                    $font->align('center');
                    $font->weight('bold');
                });
            }

            // Add SKU if available
            if ($product->sku) {
                $image->text($product->sku, $labelWidth / 2, 175, function($font) {
                    $font->file(1);
                    $font->size(12);
                    $font->color('#666666');
                    $font->align('center');
                });
            }

            return $image->encode('data-url');
        } catch (\Exception $e) {
            \Log::error('Product label generation error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Generate multiple barcodes for printing
     */
    public function generateMultipleBarcodes($products, $template = 'standard', $labelsPerRow = 3)
    {
        $labels = [];
        $labelWidth = 300;
        $labelHeight = 200;
        $spacing = 10;

        foreach ($products as $product) {
            $label = $this->generateProductLabel($product, $template);
            if ($label) {
                $labels[] = $label;
            }
        }

        if (empty($labels)) {
            return null;
        }

        // Create sheet with multiple labels
        $labelsPerRow = max(1, min($labelsPerRow, count($labels)));
        $rows = ceil(count($labels) / $labelsPerRow);

        $sheetWidth = ($labelWidth * $labelsPerRow) + ($spacing * ($labelsPerRow - 1));
        $sheetHeight = ($labelHeight * $rows) + ($spacing * ($rows - 1));

        $sheet = Image::canvas($sheetWidth, $sheetHeight, '#ffffff');

        foreach ($labels as $index => $labelData) {
            $row = floor($index / $labelsPerRow);
            $col = $index % $labelsPerRow;

            $x = $col * ($labelWidth + $spacing);
            $y = $row * ($labelHeight + $spacing);

            $labelImage = Image::make($labelData);
            $sheet->insert($labelImage, 'top-left', $x, $y);
        }

        return $sheet->encode('data-url');
    }

    /**
     * Generate unique barcode number
     */
    public function generateUniqueBarcodeNumber($prefix = 'PROD')
    {
        do {
            $number = $prefix . str_pad(mt_rand(1, 999999999), 9, '0', STR_PAD_LEFT);
        } while (\App\Models\Product::where('barcode', $number)->exists());

        return $number;
    }

    /**
     * Validate barcode format
     */
    public function validateBarcode($barcode, $format = 'code128')
    {
        $generator = new \Picqer\Barcode\BarcodeGeneratorPNG();

        try {
            $generator->getBarcode($barcode, $format);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get barcode information
     */
    public function getBarcodeInfo($barcode)
    {
        $generator = new \Picqer\Barcode\BarcodeGeneratorPNG();

        return [
            'code' => $barcode,
            'type' => 'code128',
            'checksum' => $this->calculateChecksum($barcode),
            'length' => strlen($barcode),
        ];
    }

    /**
     * Calculate barcode checksum
     */
    private function calculateChecksum($barcode)
    {
        // Simple checksum calculation for demonstration
        $sum = 0;
        for ($i = 0; $i < strlen($barcode); $i++) {
            $sum += ord($barcode[$i]) * ($i + 1);
        }
        return $sum % 100;
    }

    /**
     * Generate QR code for product
     */
    public function generateQRCode($product)
    {
        try {
            $qrData = [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'price' => $product->price,
            ];

            $qrText = json_encode($qrData);

            // This would require a QR code library like bacon/bacon-qr-code
            // For now, return placeholder
            return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
        } catch (\Exception $e) {
            \Log::error('QR code generation error: ' . $e->getMessage());
            return null;
        }
    }
}