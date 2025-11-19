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
        Schema::create('payment_gateway_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->onDelete('cascade');
            $table->enum('gateway', ['gcash', 'paypal', 'stripe']);
            $table->string('gateway_transaction_id')->nullable();
            $table->json('gateway_response_data')->nullable();
            $table->decimal('gateway_fee_amount', 8, 2)->default(0);
            $table->string('gateway_status')->default('initiated');
            $table->timestamp('gateway_processed_at')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index(['gateway', 'gateway_transaction_id']);
            $table->index('gateway_status');
            $table->index('gateway_processed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_gateway_details');
    }
};