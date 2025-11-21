<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('transactions', function (Blueprint $table) {
            // add kind (charge/payment) — seeder currently uses 'kind'
            if (! Schema::hasColumn('transactions', 'kind')) {
                $table->string('kind')->default('charge')->after('reference');
            }

            // human-friendly category (Tuition, Library, Payment, etc.)
            if (! Schema::hasColumn('transactions', 'category')) {
                $table->string('category')->nullable()->after('kind');
            }

            // academic year and semester (used by seeders)
            if (! Schema::hasColumn('transactions', 'year')) {
                $table->string('year')->nullable()->after('category');
            }
            if (! Schema::hasColumn('transactions', 'semester')) {
                $table->string('semester')->nullable()->after('year');
            }
        });
    }

    public function down()
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'semester')) {
                $table->dropColumn('semester');
            }
            if (Schema::hasColumn('transactions', 'year')) {
                $table->dropColumn('year');
            }
            if (Schema::hasColumn('transactions', 'category')) {
                $table->dropColumn('category');
            }
            if (Schema::hasColumn('transactions', 'kind')) {
                $table->dropColumn('kind');
            }
        });
    }
};