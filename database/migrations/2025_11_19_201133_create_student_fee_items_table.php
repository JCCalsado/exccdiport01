<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_fee_items', function (Blueprint $table) {
            $table->id();
            
            // Foreign keys
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignId('fee_id')->constrained('fees')->onDelete('restrict');
            
            // Academic period
            $table->string('school_year'); // e.g., "2025-2026"
            $table->string('semester'); // e.g., "1st Sem", "2nd Sem"
            
            // Financial tracking
            $table->decimal('original_amount', 12, 2); // Original fee amount
            $table->decimal('amount_paid', 12, 2)->default(0); // Total paid so far
            $table->decimal('balance', 12, 2); // Remaining balance
            
            // Status
            $table->enum('status', ['pending', 'partial', 'paid', 'waived'])->default('pending');
            
            // Metadata
            $table->text('notes')->nullable(); // For special instructions
            $table->timestamp('due_date')->nullable(); // Optional deadline
            $table->foreignId('assigned_by')->nullable()->constrained('users')->onDelete('set null'); // Who assigned this fee
            
            $table->timestamps();
            
            // Prevent duplicate fee assignments
            $table->unique(['student_id', 'fee_id', 'school_year', 'semester'], 'unique_student_fee_assignment');
            
            // Indexes for faster queries
            $table->index(['student_id', 'status']);
            $table->index(['school_year', 'semester']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_fee_items');
    }
};