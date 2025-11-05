<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Student;
use Illuminate\Support\Facades\Hash;

class ResetDemoUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Example: php artisan demo:reset
     */
    protected $signature = 'demo:reset {--with-students : Reset demo students as well}';

    /**
     * The console command description.
     */
    protected $description = 'Reset demo admin, accounting, and optional demo students without overwriting real data.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Resetting demo users...');

        // Reset Admin with proper name
        $admin = User::updateOrCreate(
            ['email' => 'admin@ccdi.edu.ph'],
            [
                'last_name' => 'Rodriguez',
                'first_name' => 'Carlos',
                'middle_initial' => 'M',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'status' => User::STATUS_ACTIVE,
                'faculty' => 'Administration',
                'phone' => '09171234501',
                'address' => 'Sorsogon City',
                'birthday' => '1985-05-15',
            ]
        );
        $admin->account()->firstOrCreate([], ['balance' => 0]);
        $this->info('✓ Admin (Carlos M. Rodriguez) reset.');

        // Reset Accounting Staff with proper name
        $accounting = User::updateOrCreate(
            ['email' => 'accounting@ccdi.edu.ph'],
            [
                'last_name' => 'Garcia',
                'first_name' => 'Ana Marie',
                'middle_initial' => 'S',
                'password' => Hash::make('password'),
                'role' => 'accounting',
                'status' => User::STATUS_ACTIVE,
                'faculty' => 'Accounting Department',
                'phone' => '09181234502',
                'address' => 'Legazpi City',
                'birthday' => '1990-08-20',
            ]
        );
        $accounting->account()->firstOrCreate([], ['balance' => 0]);
        $this->info('✓ Accounting staff (Ana Marie S. Garcia) reset.');

        // Optionally reset seeded students
        if ($this->option('with-students')) {
            $this->resetDemoStudents();
        } else {
            $this->warn('Skipping demo students. Use --with-students to reset them too.');
        }

        $this->info('✅ Demo users reset complete.');
        return self::SUCCESS;
    }

    private function resetDemoStudents(): void
    {
        $students = [
            [
                'last_name' => 'Dela Cruz',
                'first_name' => 'Juan',
                'middle_initial' => 'P',
                'email' => 'student1@ccdi.edu.ph',
                'student_id' => '2025-0001',
                'course' => 'BS Computer Science',
                'year_level' => '1st Year',
                'status' => 'enrolled',
                'birthday' => '2005-06-15',
                'phone' => '09171234567',
                'address' => 'Sorsogon City',
            ],
            [
                'last_name' => 'Santos',
                'first_name' => 'Maria',
                'middle_initial' => 'L',
                'email' => 'student2@ccdi.edu.ph',
                'student_id' => '2025-0002',
                'course' => 'BS Information Technology',
                'year_level' => '4th Year',
                'status' => 'graduated',
                'birthday' => '2002-03-10',
                'phone' => '09181234567',
                'address' => 'Legazpi City',
            ],
            [
                'last_name' => 'Ramirez',
                'first_name' => 'Pedro',
                'middle_initial' => 'C',
                'email' => 'student3@ccdi.edu.ph',
                'student_id' => '2025-0003',
                'course' => 'BS Accountancy',
                'year_level' => '2nd Year',
                'status' => 'inactive',
                'birthday' => '2004-11-20',
                'phone' => '09191234567',
                'address' => 'Naga City',
            ],
        ];

        $statusMap = [
            'enrolled' => User::STATUS_ACTIVE,
            'graduated' => User::STATUS_GRADUATED,
            'inactive' => User::STATUS_DROPPED,
        ];

        foreach ($students as $s) {
            $user = User::updateOrCreate(
                ['email' => $s['email']],
                [
                    'last_name' => $s['last_name'],
                    'first_name' => $s['first_name'],
                    'middle_initial' => $s['middle_initial'],
                    'password' => Hash::make('password'),
                    'role' => 'student',
                    'student_id' => $s['student_id'],
                    'status' => $statusMap[$s['status']] ?? User::STATUS_ACTIVE,
                    'course' => $s['course'],
                    'year_level' => $s['year_level'],
                    'birthday' => $s['birthday'],
                    'phone' => $s['phone'],
                    'address' => $s['address'],
                ]
            );

            $user->account()->firstOrCreate([], ['balance' => -8048.00]);

            Student::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'student_id' => $s['student_id'],
                    'last_name' => $s['last_name'],
                    'first_name' => $s['first_name'],
                    'middle_initial' => $s['middle_initial'],
                    'email' => $s['email'],
                    'course' => $s['course'],
                    'year_level' => $s['year_level'],
                    'status' => $s['status'],
                    'birthday' => $s['birthday'],
                    'phone' => $s['phone'],
                    'address' => $s['address'],
                    'total_balance' => 8048.00,
                ]
            );

            $fullName = $user->name; // Uses the accessor
            $this->info("✓ Student {$fullName} reset.");
        }
    }
}