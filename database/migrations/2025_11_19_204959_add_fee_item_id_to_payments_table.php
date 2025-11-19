<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('fee_item_id')
                  ->nullable()
                  ->after('student_id')
                  ->constrained('student_fee_items')
                  ->onDelete('cascade');
            
            $table->index('fee_item_id');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['fee_item_id']);
            $table->dropColumn('fee_item_id');
        });
    }
};