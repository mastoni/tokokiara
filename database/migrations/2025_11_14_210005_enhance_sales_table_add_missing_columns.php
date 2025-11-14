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
        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('tax_amount', 10, 2)->default(0)->after('sale_time');
            $table->decimal('shipping_amount', 10, 2)->default(0)->after('tax_amount');
            $table->integer('loyalty_points_earned')->default(0)->after('shipping_amount');
            $table->integer('loyalty_points_redeemed')->default(0)->after('loyalty_points_earned');
            $table->text('staff_note')->nullable()->after('note');
            $table->text('customer_note')->nullable()->after('staff_note');
            $table->text('delivery_address')->nullable()->after('customer_note');
            $table->datetime('delivery_date')->nullable()->after('delivery_address');
            $table->unsignedBigInteger('updated_by')->nullable()->after('created_by');

            // Foreign key
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');

            // Indexes
            $table->index(['store_id', 'status']);
            $table->index(['contact_id', 'status']);
            $table->index(['sale_date', 'status']);
            $table->index('payment_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['store_id', 'status']);
            $table->dropIndex(['contact_id', 'status']);
            $table->dropIndex(['sale_date', 'status']);
            $table->dropIndex('payment_status');

            $table->dropForeign(['updated_by']);

            $table->dropColumn([
                'tax_amount',
                'shipping_amount',
                'loyalty_points_earned',
                'loyalty_points_redeemed',
                'staff_note',
                'customer_note',
                'delivery_address',
                'delivery_date',
                'updated_by'
            ]);
        });
    }
};