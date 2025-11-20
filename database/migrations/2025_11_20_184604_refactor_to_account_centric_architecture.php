<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * COMPREHENSIVE REFACTORING MIGRATION
     * 
     * This migration:
     * 1. Removes course/year_level from users (moves to students)
     * 2. Makes account_id the PRIMARY financial identifier
     * 3. Updates all financial tables to use account_id
     * 4. Ensures data integrity through proper foreign keys
     */
    public function up(): void
    {
        // ═══════════════════════════════════════════════════════
        // PART 1: FIX STUDENT DATA REDUNDANCY
        // ═══════════════════════════════════════════════════════
        
        // Ensure students table has all required columns
        Schema::table('students', function (Blueprint $table) {
            if (!Schema::hasColumn('students', 'course')) {
                $table->string('course')->nullable()->after('email');
            }
            if (!Schema::hasColumn('students', 'year_level')) {
                $table->string('year_level')->nullable()->after('course');
            }
        });

        // Migrate data from users to students
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

        // Make course/year_level NOT NULL
        Schema::table('students', function (Blueprint $table) {
            $table->string('course')->nullable(false)->change();
            $table->string('year_level')->nullable(false)->change();
        });

        // Remove redundant columns from users
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'course')) {
                try {
                    $table->dropIndex('users_course_index');
                } catch (\Exception $e) {}
                $table->dropColumn('course');
            }
            if (Schema::hasColumn('users', 'year_level')) {
                try {
                    $table->dropIndex('users_year_level_index');
                } catch (\Exception $e) {}
                $table->dropColumn('year_level');
            }
        });

        // ═══════════════════════════════════════════════════════
        // PART 2: ENSURE EVERY STUDENT HAS AN ACCOUNT
        // ═══════════════════════════════════════════════════════
        
        // Create accounts for students who don't have one
        DB::statement("
            INSERT INTO accounts (user_id, balance, created_at, updated_at)
            SELECT u.id, 0.00, NOW(), NOW()
            FROM users u
            LEFT JOIN accounts a ON u.id = a.user_id
            WHERE u.role = 'student' AND a.id IS NULL
        ");

        // ═══════════════════════════════════════════════════════
        // PART 3: ADD account_id TO ALL FINANCIAL TABLES
        // ═══════════════════════════════════════════════════════

        // 3.1: Add account_id to transactions
        Schema::table('transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('transactions', 'account_id')) {
                $table->foreignId('account_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('accounts')
                    ->onDelete('cascade');
                $table->index('account_id');
            }
        });

        // Populate account_id in transactions from user_id
        DB::statement("
            UPDATE transactions t
            INNER JOIN accounts a ON t.user_id = a.user_id
            SET t.account_id = a.id
            WHERE t.account_id IS NULL
        ");

        // 3.2: Add account_id to payments
        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'account_id')) {
                $table->foreignId('account_id')
                    ->nullable()
                    ->after('student_id')
                    ->constrained('accounts')
                    ->onDelete('cascade');
                $table->index('account_id');
            }
        });

        // Populate account_id in payments
        DB::statement("
            UPDATE payments p
            INNER JOIN students s ON p.student_id = s.id
            INNER JOIN accounts a ON s.user_id = a.user_id
            SET p.account_id = a.id
            WHERE p.account_id IS NULL
        ");

        // 3.3: Add account_id to student_fee_items
        Schema::table('student_fee_items', function (Blueprint $table) {
            if (!Schema::hasColumn('student_fee_items', 'account_id')) {
                $table->foreignId('account_id')
                    ->nullable()
                    ->after('student_id')
                    ->constrained('accounts')
                    ->onDelete('cascade');
                $table->index('account_id');
            }
        });

        // Populate account_id in student_fee_items
        DB::statement("
            UPDATE student_fee_items sfi
            INNER JOIN students s ON sfi.student_id = s.id
            INNER JOIN accounts a ON s.user_id = a.user_id
            SET sfi.account_id = a.id
            WHERE sfi.account_id IS NULL
        ");

        // ═══════════════════════════════════════════════════════
        // PART 4: ADD MISSING RELATIONSHIPS
        // ═══════════════════════════════════════════════════════

        // Add payment_id to transactions if missing
        Schema::table('transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('transactions', 'payment_id')) {
                $table->foreignId('payment_id')
                    ->nullable()
                    ->after('account_id')
                    ->constrained('payments')
                    ->onDelete('set null');
                $table->index('payment_id');
            }
        });

        // Add fee_item_id to payments if missing
        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'fee_item_id')) {
                $table->foreignId('fee_item_id')
                    ->nullable()
                    ->after('account_id')
                    ->constrained('student_fee_items')
                    ->onDelete('set null');
                $table->index('fee_item_id');
            }
        });

        // Ensure meta and receipt_number exist in payments
        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'meta')) {
                $table->json('meta')->nullable();
            }
            if (!Schema::hasColumn('payments', 'receipt_number')) {
                $table->string('receipt_number')->nullable()->unique();
            }
        });

        // ═══════════════════════════════════════════════════════
        // PART 5: MAKE account_id NOT NULL (Data Integrity)
        // ═══════════════════════════════════════════════════════

        // Verify no NULL account_ids remain
        $nullAccountIds = [
            'transactions' => DB::table('transactions')->whereNull('account_id')->count(),
            'payments' => DB::table('payments')->whereNull('account_id')->count(),
            'student_fee_items' => DB::table('student_fee_items')->whereNull('account_id')->count(),
        ];

        if (array_sum($nullAccountIds) > 0) {
            throw new \Exception(
                "Cannot make account_id NOT NULL. Found NULL values: " . 
                json_encode($nullAccountIds)
            );
        }

        // Make account_id NOT NULL
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('account_id')->nullable(false)->change();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('account_id')->nullable(false)->change();
        });

        Schema::table('student_fee_items', function (Blueprint $table) {
            $table->foreignId('account_id')->nullable(false)->change();
        });

        // ═══════════════════════════════════════════════════════
        // PART 6: ADD INDEXES FOR PERFORMANCE
        // ═══════════════════════════════════════════════════════

        Schema::table('students', function (Blueprint $table) {
            $table->index('course');
            $table->index('year_level');
            $table->index('status');
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->index('balance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore course/year_level to users
        Schema::table('users', function (Blueprint $table) {
            $table->string('course')->nullable();
            $table->string('year_level')->nullable();
        });

        DB::statement("
            UPDATE users u
            INNER JOIN students s ON u.id = s.user_id
            SET u.course = s.course, u.year_level = s.year_level
            WHERE u.role = 'student'
        ");

        // Remove account_id columns (make nullable first)
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('account_id')->nullable()->change();
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('account_id')->nullable()->change();
        });
        Schema::table('student_fee_items', function (Blueprint $table) {
            $table->foreignId('account_id')->nullable()->change();
        });

        // Drop account_id foreign keys and columns
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['account_id']);
            $table->dropColumn('account_id');
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['account_id']);
            $table->dropColumn('account_id');
        });
        Schema::table('student_fee_items', function (Blueprint $table) {
            $table->dropForeign(['account_id']);
            $table->dropColumn('account_id');
        });
    }
};