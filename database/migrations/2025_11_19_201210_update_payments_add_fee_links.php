<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdatePaymentsAddFeeLinks extends Migration
{
    public function up()
    {
        Schema::table('payments', function (Blueprint $table) {
            // add nullable references so we can link payments to fees or to a specific student_fee_item
            if (!Schema::hasColumn('payments', 'fee_id')) {
                $table->unsignedBigInteger('fee_id')->nullable()->after('student_id')->index();
                $table->foreign('fee_id')->references('id')->on('fees')->onDelete('set null');
            }
            if (!Schema::hasColumn('payments', 'student_fee_item_id')) {
                $table->unsignedBigInteger('student_fee_item_id')->nullable()->after('fee_id')->index();
                $table->foreign('student_fee_item_id')->references('id')->on('student_fee_items')->onDelete('set null');
            }

            // optional receipt number + meta fields
            if (!Schema::hasColumn('payments', 'receipt_number')) {
                $table->string('receipt_number')->nullable();
            }
            if (!Schema::hasColumn('payments', 'meta')) {
                $table->json('meta')->nullable()->after('status');
            }
        });
    }

    public function down()
    {
        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'student_fee_item_id')) {
                $table->dropForeign(['student_fee_item_id']);
                $table->dropColumn('student_fee_item_id');
            }
            if (Schema::hasColumn('payments', 'fee_id')) {
                $table->dropForeign(['fee_id']);
                $table->dropColumn('fee_id');
            }
            if (Schema::hasColumn('payments', 'receipt_number')) {
                $table->dropColumn('receipt_number');
            }
            if (Schema::hasColumn('payments', 'meta')) {
                $table->dropColumn('meta');
            }
        });
    }
}