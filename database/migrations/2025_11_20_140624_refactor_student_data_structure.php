<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This migration consolidates student data into the students table
     * and removes redundant fields from users table.
     */
    public function up(): void
    {
        // STEP 1: Ensure students table has all required columns
        Schema::table('students', function (Blueprint $table) {
            // Add course if it doesn't exist
            if (!Schema::hasColumn('students', 'course')) {
                $table->string('course')->nullable()->after('email');
            }
            
            // Add year_level if it doesn't exist
            if (!Schema::hasColumn('students', 'year_level')) {
                $table->string('year_level')->nullable()->after('course');
            }
            
            // Ensure status column exists with correct enum values
            if (!Schema::hasColumn('students', 'status')) {
                $table->enum('status', ['enrolled', 'graduated', 'inactive'])
                    ->default('enrolled')
                    ->after('year_level');
            }
        });

        // STEP 2: Migrate data from users to students (CRITICAL - Don't lose data!)
        DB::statement("
            UPDATE students s
            INNER JOIN users u ON s.user_id = u.id
            SET 
                s.course = COALESCE(s.course, u.course),
                s.year_level = COALESCE(s.year_level, u.year_level),
                s.status = CASE
                    WHEN u.status = 'active' THEN 'enrolled'
                    WHEN u.status = 'graduated' THEN 'graduated'
                    WHEN u.status = 'dropped' THEN 'inactive'
                    ELSE s.status
                END
            WHERE u.role = 'student'
        ");

        // STEP 3: Verify data migration (log any students without course/year_level)
        $missingData = DB::select("
            SELECT s.id, s.student_id, u.email
            FROM students s
            INNER JOIN users u ON s.user_id = u.id
            WHERE s.course IS NULL OR s.year_level IS NULL
        ");
        
        if (!empty($missingData)) {
            foreach ($missingData as $student) {
                \Log::warning("Student missing academic data after migration", [
                    'student_id' => $student->id,
                    'student_number' => $student->student_id,
                    'email' => $student->email
                ]);
            }
        }

        // STEP 4: Make students.course and students.year_level NOT NULL
        Schema::table('students', function (Blueprint $table) {
            $table->string('course')->nullable(false)->change();
            $table->string('year_level')->nullable(false)->change();
        });

        // STEP 5: Remove redundant columns from users table
        Schema::table('users', function (Blueprint $table) {
            // Drop indexes first if they exist
            try {
                $table->dropIndex('users_course_index');
            } catch (\Exception $e) {
                // Index might not exist, ignore
            }
            
            try {
                $table->dropIndex('users_year_level_index');
            } catch (\Exception $e) {
                // Index might not exist, ignore
            }
            
            // Now drop the columns
            if (Schema::hasColumn('users', 'course')) {
                $table->dropColumn('course');
            }
            
            if (Schema::hasColumn('users', 'year_level')) {
                $table->dropColumn('year_level');
            }
        });

        // STEP 6: Add indexes to students table for performance
        Schema::table('students', function (Blueprint $table) {
            $table->index('course');
            $table->index('year_level');
            $table->index('status');
            $table->index(['course', 'year_level']); // Composite index for common queries
        });

        // STEP 7: Ensure payment_id exists in transactions table
        Schema::table('transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('transactions', 'payment_id')) {
                $table->foreignId('payment_id')
                    ->nullable()
                    ->after('fee_id')
                    ->constrained('payments')
                    ->onDelete('set null');
                
                $table->index('payment_id');
            }
        });

        // STEP 8: Ensure year and semester columns exist in transactions
        Schema::table('transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('transactions', 'year')) {
                $table->string('year')->nullable()->after('kind');
            }
            
            if (!Schema::hasColumn('transactions', 'semester')) {
                $table->string('semester')->nullable()->after('year');
            }
        });

        // STEP 9: Add fee_item_id to payments if missing
        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'fee_item_id')) {
                $table->foreignId('fee_item_id')
                    ->nullable()
                    ->after('student_id')
                    ->constrained('student_fee_items')
                    ->onDelete('cascade');
                
                $table->index('fee_item_id');
            }
            
            // Ensure meta column exists
            if (!Schema::hasColumn('payments', 'meta')) {
                $table->json('meta')->nullable();
            }
            
            // Ensure receipt_number exists
            if (!Schema::hasColumn('payments', 'receipt_number')) {
                $table->string('receipt_number')->nullable()->unique();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // STEP 1: Add columns back to users table
        Schema::table('users', function (Blueprint $table) {
            $table->string('course')->nullable()->after('phone');
            $table->string('year_level')->nullable()->after('course');
            
            // Add indexes back
            $table->index('course');
            $table->index('year_level');
        });

        // STEP 2: Restore data from students to users
        DB::statement("
            UPDATE users u
            INNER JOIN students s ON u.id = s.user_id
            SET 
                u.course = s.course,
                u.year_level = s.year_level
            WHERE u.role = 'student'
        ");

        // STEP 3: Drop indexes from students table
        Schema::table('students', function (Blueprint $table) {
            try {
                $table->dropIndex('students_course_index');
                $table->dropIndex('students_year_level_index');
                $table->dropIndex('students_status_index');
                $table->dropIndex(['students_course_year_level_index']);
            } catch (\Exception $e) {
                // Indexes might not exist, ignore
            }
        });

        // STEP 4: Drop payment_id from transactions
        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'payment_id')) {
                $table->dropForeign(['payment_id']);
                $table->dropColumn('payment_id');
            }
        });

        // STEP 5: Drop fee_item_id from payments
        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'fee_item_id')) {
                $table->dropForeign(['fee_item_id']);
                $table->dropColumn('fee_item_id');
            }
        });
    }
};