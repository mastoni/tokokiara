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
        Schema::table('stores', function (Blueprint $table) {
            $table->string('email')->nullable()->after('contact_number');
            $table->string('logo_url')->nullable()->after('email');
            $table->decimal('tax_rate', 5, 3)->default(0)->after('current_sale_number');
            $table->string('currency_code', 3)->default('USD')->after('tax_rate');
            $table->boolean('is_active')->default(true)->after('currency_code');
            $table->json('settings')->nullable()->after('is_active');
            $table->unsignedBigInteger('updated_by')->nullable()->after('created_by');

            // Foreign key
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');

            // Indexes
            $table->index('is_active');
            $table->index('currency_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
            $table->dropIndex(['currency_code']);

            $table->dropForeign(['updated_by']);

            $table->dropColumn([
                'email',
                'logo_url',
                'tax_rate',
                'currency_code',
                'is_active',
                'settings',
                'updated_by'
            ]);
        });
    }
};