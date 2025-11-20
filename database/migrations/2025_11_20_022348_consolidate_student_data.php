<?php
// database/migrations/2025_01_XX_consolidate_student_data.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * STEP 1: Remove redundant columns from users table
     * Keep student_id in users for quick lookup, move rest to students table
     */
    public function up(): void
    {
        // First, ensure students table has all necessary columns
        Schema::table('students', function (Blueprint $table) {
            if (!Schema::hasColumn('students', 'course')) {
                $table->string('course')->after('email');
            }
            if (!Schema::hasColumn('students', 'year_level')) {
                $table->string('year_level')->after('course');
            }
            if (!Schema::hasColumn('students', 'status')) {
                $table->enum('status', ['enrolled', 'graduated', 'inactive'])->default('enrolled')->after('year_level');
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
                    ELSE 'inactive'
                END
            WHERE u.role = 'student'
        ");

        // Now remove redundant columns from users (keep student_id for quick reference)
        Schema::table('users', function (Blueprint $table) {
            // Keep student_id for lookup efficiency
            // Remove: course, year_level
            if (Schema::hasColumn('users', 'course')) {
                $table->dropColumn('course');
            }
            if (Schema::hasColumn('users', 'year_level')) {
                $table->dropColumn('year_level');
            }
            // Keep status as it applies to all user types
        });
    }

    /**
     * Rollback: Restore columns to users table
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('course')->nullable()->after('phone');
            $table->string('year_level')->nullable()->after('course');
        });

        // Restore data from students back to users
        DB::statement("
            UPDATE users u
            INNER JOIN students s ON u.id = s.user_id
            SET 
                u.course = s.course,
                u.year_level = s.year_level
            WHERE u.role = 'student'
        ");
    }
};