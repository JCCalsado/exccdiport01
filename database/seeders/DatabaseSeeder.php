<?php
// database/seeders/DatabaseSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Complete database seeding with proper order and cleanup
     */
    public function run(): void
    {
        $this->command->info('🚀 Starting School Payment Portal Seeding...');
        $this->command->newLine();

        // STEP 1: Clean slate (optional - use with caution)
        if ($this->command->confirm('⚠️  Clear existing data?', false)) {
            $this->cleanDatabase();
        }

        // STEP 2: Seed Users & Students (SINGLE SOURCE OF TRUTH)
        $this->command->info('📚 Step 1: Creating Users & Students...');
        $this->call(ComprehensiveUserSeeder::class);
        $this->command->newLine();

        // STEP 3: Seed Subjects (OBE Curriculum)
        $this->command->info('📖 Step 2: Loading Subjects...');
        $this->call(EnhancedSubjectSeeder::class);
        $this->command->newLine();

        // STEP 4: Seed Fees
        $this->command->info('💰 Step 3: Setting up Fees...');
        $this->call(FeeSeeder::class);
        $this->command->newLine();

        // STEP 5: Create Student Fee Items (NEW)
        $this->command->info('📋 Step 4: Assigning Fees to Students...');
        $this->call(StudentFeeItemSeeder::class);
        $this->command->newLine();

        // STEP 6: Create Assessments & Transactions
        $this->command->info('📊 Step 5: Generating Assessments...');
        $this->call(ComprehensiveAssessmentSeeder::class);
        $this->command->newLine();

        // STEP 7: Seed Notifications
        $this->command->info('🔔 Step 6: Creating Notifications...');
        $this->call(NotificationSeeder::class);
        $this->command->newLine();

        $this->command->info('✅ Seeding completed successfully!');
        $this->displaySummary();
    }

    /**
     * Clean database (use with caution!)
     */
    private function cleanDatabase(): void
    {
        $this->command->warn('🗑️  Clearing existing data...');

        // Disable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Truncate in reverse dependency order
        $tables = [
            'payment_gateway_details',
            'audit_logs',
            'notification_logs',
            'notifications',
            'payments',
            'transactions',
            'student_assessments',
            'student_enrollments',
            'student_fee_items',
            'students',
            'accounts',
            'subjects',
            'fees',
            // Don't truncate users if you want to keep admin
            'users',
        ];

        foreach ($tables as $table) {
            DB::table($table)->truncate();
            $this->command->line("  ✓ Cleared {$table}");
        }

        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        
        $this->command->info('✓ Database cleaned');
        $this->command->newLine();
    }

    /**
     * Display seeding summary
     */
    private function displaySummary(): void
    {
        $this->command->newLine();
        $this->command->info('📊 SEEDING SUMMARY');
        $this->command->info('═══════════════════════════════════════════════════════');
        
        $userCount = \App\Models\User::count();
        $adminCount = \App\Models\User::where('role', 'admin')->count();
        $accountingCount = \App\Models\User::where('role', 'accounting')->count();
        $studentCount = \App\Models\User::where('role', 'student')->count();
        
        $activeStudents = \App\Models\Student::where('status', 'enrolled')->count();
        $graduatedStudents = \App\Models\Student::where('status', 'graduated')->count();
        $inactiveStudents = \App\Models\Student::where('status', 'inactive')->count();
        
        $subjectCount = \App\Models\Subject::count();
        $feeCount = \App\Models\Fee::count();
        $feeItemCount = \App\Models\StudentFeeItem::count();
        $assessmentCount = \App\Models\StudentAssessment::count();
        $transactionCount = \App\Models\Transaction::count();
        $paymentCount = \App\Models\Payment::count();
        
        $this->command->table(
            ['Category', 'Count'],
            [
                ['Total Users', $userCount],
                ['├─ Admins', $adminCount],
                ['├─ Accounting Staff', $accountingCount],
                ['└─ Students', $studentCount],
                ['', ''],
                ['Student Status', ''],
                ['├─ Active', $activeStudents],
                ['├─ Graduated', $graduatedStudents],
                ['└─ Inactive', $inactiveStudents],
                ['', ''],
                ['Academic Setup', ''],
                ['├─ Subjects', $subjectCount],
                ['├─ Fee Types', $feeCount],
                ['└─ Fee Assignments', $feeItemCount],
                ['', ''],
                ['Financial Records', ''],
                ['├─ Assessments', $assessmentCount],
                ['├─ Transactions', $transactionCount],
                ['└─ Payments', $paymentCount],
            ]
        );
        
        $this->command->newLine();
        $this->command->info('🔐 DEFAULT CREDENTIALS');
        $this->command->info('═══════════════════════════════════════════════════════');
        $this->command->table(
            ['Role', 'Email', 'Password'],
            [
                ['Admin', 'admin@ccdi.edu.ph', 'password'],
                ['Accounting', 'accounting@ccdi.edu.ph', 'password'],
                ['Student (example)', 'student1@ccdi.edu.ph', 'password'],
            ]
        );
        
        $this->command->newLine();
        $this->command->info('💡 NEXT STEPS');
        $this->command->info('═══════════════════════════════════════════════════════');
        $this->command->line('1. Run: php artisan migrate:fresh --seed (to reset everything)');
        $this->command->line('2. Visit: http://your-domain/login');
        $this->command->line('3. Test with: student1@ccdi.edu.ph / password');
        $this->command->newLine();
    }
}