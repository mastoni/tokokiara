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
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('store_id');
            $table->text('address')->nullable()->after('phone');
            $table->string('avatar_url')->nullable()->after('address');
            $table->datetime('last_login_at')->nullable()->after('avatar_url');
            $table->unsignedBigInteger('updated_by')->nullable()->after('created_by');

            // Foreign key
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');

            // Indexes
            $table->index('user_role');
            $table->index('is_active');
            $table->index('store_id');
            $table->index(['store_id', 'user_role']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('user_role');
            $table->dropIndex('is_active');
            $table->dropIndex('store_id');
            $table->dropIndex(['store_id', 'user_role']);

            $table->dropForeign(['updated_by']);

            $table->dropColumn([
                'phone',
                'address',
                'avatar_url',
                'last_login_at',
                'updated_by'
            ]);
        });
    }
};