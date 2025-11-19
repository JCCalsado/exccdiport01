<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStudentFeeItemsTable extends Migration
{
    public function up()
    {
        Schema::create('student_fee_items', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('student_id')->index();
            $table->unsignedBigInteger('fee_id')->index();

            $table->decimal('original_amount', 15, 2)->default(0);
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->decimal('balance', 15, 2)->default(0);

            $table->string('status')->default('unpaid'); // unpaid, partial, paid, waived, cancelled
            $table->string('reference')->nullable(); // optional ref

            $table->timestamp('due_date')->nullable();

            $table->timestamps();

            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            $table->foreign('fee_id')->references('id')->on('fees')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('student_fee_items');
    }
}