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
        Schema::table('transactions', function (Blueprint $table) {
            // ensure a 'kind' column exists
            if (!Schema::hasColumn('transactions', 'kind')) {
                $table->enum('kind', ['charge','payment'])->default('charge')->after('payment_channel');
            }

            // change `type` from enum to string
            $table->string('type')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // revert -- note: changing back to enum may require caution
            $table->enum('type', ['charge','payment'])->default('charge')->change();
            if (Schema::hasColumn('transactions', 'kind')) {
                $table->dropColumn('kind');
            }
        });
    }
};