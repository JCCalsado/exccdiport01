<?php
// database/migrations/2025_01_XX_add_payment_id_to_transactions.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Add payment_id to link transactions to payment records
            if (!Schema::hasColumn('transactions', 'payment_id')) {
                $table->foreignId('payment_id')
                      ->nullable()
                      ->after('fee_id')
                      ->constrained('payments')
                      ->onDelete('set null');
            }
            
            // Ensure we have year and semester columns for proper grouping
            if (!Schema::hasColumn('transactions', 'year')) {
                $table->string('year')->nullable()->after('kind');
            }
            if (!Schema::hasColumn('transactions', 'semester')) {
                $table->string('semester')->nullable()->after('year');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['payment_id']);
            $table->dropColumn('payment_id');
        });
    }
};