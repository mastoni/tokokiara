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
        Schema::table('contacts', function (Blueprint $table) {
            $table->string('city')->nullable()->after('whatsapp');
            $table->string('state')->nullable()->after('city');
            $table->string('country')->nullable()->after('state');
            $table->string('postal_code')->nullable()->after('country');
            $table->string('tax_number')->nullable()->after('postal_code');
            $table->string('company_name')->nullable()->after('tax_number');
            $table->decimal('credit_limit', 10, 2)->nullable()->after('balance');
            $table->string('payment_terms')->nullable()->after('credit_limit');
            $table->text('notes')->nullable()->after('payment_terms');
            $table->boolean('is_active')->default(true)->after('notes');
            $table->unsignedBigInteger('store_id')->nullable()->after('is_active');
            $table->unsignedBigInteger('updated_by')->nullable()->after('created_by');

            // Foreign keys
            $table->foreign('store_id')->references('id')->on('stores')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');

            // Indexes
            $table->index('store_id');
            $table->index('is_active');
            $table->index('type');
            $table->index(['store_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropIndex(['store_id']);
            $table->dropIndex(['is_active']);
            $table->dropIndex(['type']);
            $table->dropIndex(['store_id', 'type']);

            $table->dropForeign(['store_id']);
            $table->dropForeign(['updated_by']);

            $table->dropColumn([
                'city',
                'state',
                'country',
                'postal_code',
                'tax_number',
                'company_name',
                'credit_limit',
                'payment_terms',
                'notes',
                'is_active',
                'store_id',
                'updated_by'
            ]);
        });
    }
};