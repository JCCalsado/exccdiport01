<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * ACCOUNT-CENTRIC ARCHITECTURE REFACTORING
     * 
     * This migration ONLY handles account_id additions
     * because course/year_level cleanup was already done in:
     * 2025_11_20_140624_refactor_student_data_structure.php
     */
    public function up(): void
    {
        // ═══════════════════════════════════════════════════════
        // PART 1: ENSURE EVERY STUDENT HAS AN ACCOUNT
        // ═══════════════════════════════════════════════════════
        
        // Create accounts for students who don't have one
        DB::statement("
            INSERT INTO accounts (user_id, balance, created_at, updated_at)
            SELECT u.id, 0.00, NOW(), NOW()
            FROM users u
            LEFT JOIN accounts a ON u.id = a.user_id
            WHERE u.role = 'student' AND a.id IS NULL
        ");

        // Create accounts for admin/accounting users too (optional but good practice)
        DB::statement("
            INSERT INTO accounts (user_id, balance, created_at, updated_at)
            SELECT u.id, 0.00, NOW(), NOW()
            FROM users u
            LEFT JOIN accounts a ON u.id = a.user_id
            WHERE u.role IN ('admin', 'accounting') AND a.id IS NULL
        ");

        // ═══════════════════════════════════════════════════════
        // PART 2: FIX account_id IN transactions
        // ═══════════════════════════════════════════════════════

        // account_id already exists (added by 2025_09_06_041549_add_account_id_to_transactions_table)
        // Just populate NULL values
        if (Schema::hasColumn('transactions', 'account_id')) {
            DB::statement("
                UPDATE transactions t
                INNER JOIN accounts a ON t.user_id = a.user_id
                SET t.account_id = a.id
                WHERE t.account_id IS NULL
            ");
            
            // Make NOT NULL if no NULLs remain
            $nullCount = DB::table('transactions')->whereNull('account_id')->count();
            if ($nullCount === 0) {
                DB::statement("
                    ALTER TABLE transactions 
                    MODIFY account_id BIGINT UNSIGNED NOT NULL
                ");
            } else {
                \Log::warning("Cannot make transactions.account_id NOT NULL. Found {$nullCount} NULL values.");
            }
        }

        // Add payment_id if missing
        if (!Schema::hasColumn('transactions', 'payment_id')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->foreignId('payment_id')
                    ->nullable()
                    ->after('account_id')
                    ->constrained('payments')
                    ->onDelete('set null');
            });
        }

        // ═══════════════════════════════════════════════════════
        // PART 3: ADD account_id TO payments
        // ═══════════════════════════════════════════════════════

        if (!Schema::hasColumn('payments', 'account_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->foreignId('account_id')
                    ->nullable()
                    ->after('student_id')
                    ->constrained('accounts')
                    ->onDelete('cascade');
            });
        }

        // Populate account_id in payments from student_id
        DB::statement("
            UPDATE payments p
            INNER JOIN students s ON p.student_id = s.id
            INNER JOIN accounts a ON s.user_id = a.user_id
            SET p.account_id = a.id
            WHERE p.account_id IS NULL
        ");

        // Make NOT NULL if no NULLs remain
        $nullCount = DB::table('payments')->whereNull('account_id')->count();
        if ($nullCount === 0) {
            DB::statement("
                ALTER TABLE payments 
                MODIFY account_id BIGINT UNSIGNED NOT NULL
            ");
        } else {
            \Log::warning("Cannot make payments.account_id NOT NULL. Found {$nullCount} NULL values.");
        }

        // Add meta/receipt_number if missing
        if (!Schema::hasColumn('payments', 'meta')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->json('meta')->nullable();
            });
        }
        
        if (!Schema::hasColumn('payments', 'receipt_number')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('receipt_number')->nullable()->unique();
            });
        }

        // ═══════════════════════════════════════════════════════
        // PART 4: ADD account_id TO student_fee_items
        // ═══════════════════════════════════════════════════════

        if (!Schema::hasColumn('student_fee_items', 'account_id')) {
            Schema::table('student_fee_items', function (Blueprint $table) {
                $table->foreignId('account_id')
                    ->nullable()
                    ->after('student_id')
                    ->constrained('accounts')
                    ->onDelete('cascade');
            });
        }

        // Populate account_id in student_fee_items
        DB::statement("
            UPDATE student_fee_items sfi
            INNER JOIN students s ON sfi.student_id = s.id
            INNER JOIN accounts a ON s.user_id = a.user_id
            SET sfi.account_id = a.id
            WHERE sfi.account_id IS NULL
        ");

        // Make NOT NULL if no NULLs remain
        $nullCount = DB::table('student_fee_items')->whereNull('account_id')->count();
        if ($nullCount === 0) {
            DB::statement("
                ALTER TABLE student_fee_items 
                MODIFY account_id BIGINT UNSIGNED NOT NULL
            ");
        } else {
            \Log::warning("Cannot make student_fee_items.account_id NOT NULL. Found {$nullCount} NULL values.");
        }

        // ═══════════════════════════════════════════════════════
        // PART 5: ADD INDEXES FOR PERFORMANCE
        // ═══════════════════════════════════════════════════════

        // Add indexes to students table (if not exist)
        $this->addIndexIfNotExists('students', 'course', 'students_course_index');
        $this->addIndexIfNotExists('students', 'year_level', 'students_year_level_index');
        $this->addIndexIfNotExists('students', 'status', 'students_status_index');

        // Add index to accounts
        $this->addIndexIfNotExists('accounts', 'balance', 'accounts_balance_index');
        
        // Add composite indexes for common queries
        $this->addIndexIfNotExists('transactions', 'account_id', 'transactions_account_id_index');
        $this->addIndexIfNotExists('payments', 'account_id', 'payments_account_id_index');
        $this->addIndexIfNotExists('student_fee_items', 'account_id', 'student_fee_items_account_id_index');
    }

    /**
     * Helper: Add index if it doesn't exist
     */
    private function addIndexIfNotExists(string $table, string $column, string $indexName): void
    {
        $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
        
        if (empty($indexes)) {
            Schema::table($table, function (Blueprint $tableBlueprint) use ($column) {
                $tableBlueprint->index($column);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Make columns nullable before dropping
        if (Schema::hasColumn('transactions', 'account_id')) {
            DB::statement("ALTER TABLE transactions MODIFY account_id BIGINT UNSIGNED NULL");
        }
        
        if (Schema::hasColumn('payments', 'account_id')) {
            DB::statement("ALTER TABLE payments MODIFY account_id BIGINT UNSIGNED NULL");
        }
        
        if (Schema::hasColumn('student_fee_items', 'account_id')) {
            DB::statement("ALTER TABLE student_fee_items MODIFY account_id BIGINT UNSIGNED NULL");
        }

        // Drop account_id columns
        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'payment_id')) {
                $table->dropForeign(['payment_id']);
                $table->dropColumn('payment_id');
            }
        });

        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'account_id')) {
                $table->dropForeign(['account_id']);
                $table->dropColumn('account_id');
            }
        });

        Schema::table('student_fee_items', function (Blueprint $table) {
            if (Schema::hasColumn('student_fee_items', 'account_id')) {
                $table->dropForeign(['account_id']);
                $table->dropColumn('account_id');
            }
        });
    }
};