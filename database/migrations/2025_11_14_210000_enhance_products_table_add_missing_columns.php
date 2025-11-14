<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->nullable()->after('meta_data');
            $table->decimal('cost', 10, 2)->nullable()->after('price');
            $table->decimal('tax_rate', 5, 3)->default(0)->after('cost');
            $table->decimal('reorder_point', 10, 2)->default(0)->after('alert_quantity');
            $table->decimal('max_stock', 10, 2)->nullable()->after('reorder_point');

            // Indexes for performance
            $table->index('barcode');
            $table->index('sku');
            $table->index('category_id');
            $table->index('brand_id');
            $table->index(['is_active', 'is_stock_managed']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['barcode']);
            $table->dropIndex(['sku']);
            $table->dropIndex(['category_id']);
            $table->dropIndex(['brand_id']);
            $table->dropIndex(['is_active', 'is_stock_managed']);

            $table->dropColumn([
                'price',
                'cost',
                'tax_rate',
                'reorder_point',
                'max_stock'
            ]);
        });
    }
};