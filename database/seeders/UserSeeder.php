<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Student;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // === Admin (idempotent) ===
        $admin = User::firstOrCreate(
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

        // === Accounting Staff (idempotent) ===
        $accounting = User::firstOrCreate(
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

        // === Students seed data ===
        $students = [
            [
                'user' => [
                    'last_name' => 'Dela Cruz',
                    'first_name' => 'Juan',
                    'middle_initial' => 'P',
                    'email' => 'student1@ccdi.edu.ph',
                ],
                'student' => [
                    'student_id' => '2025-0001',
                    'course' => 'BS Computer Science',
                    'year_level' => '1st Year',
                    'status' => 'enrolled',
                    'birthday' => '2005-06-15',
                    'phone' => '09171234567',
                    'address' => 'Sorsogon City',
                ],
            ],
            [
                'user' => [
                    'last_name' => 'Santos',
                    'first_name' => 'Maria',
                    'middle_initial' => 'L',
                    'email' => 'student2@ccdi.edu.ph',
                ],
                'student' => [
                    'student_id' => '2025-0002',
                    'course' => 'BS Information Technology',
                    'year_level' => '4th Year',
                    'status' => 'graduated',
                    'birthday' => '2002-03-10',
                    'phone' => '09181234567',
                    'address' => 'Legazpi City',
                ],
            ],
            [
                'user' => [
                    'last_name' => 'Ramirez',
                    'first_name' => 'Pedro',
                    'middle_initial' => 'C',
                    'email' => 'student3@ccdi.edu.ph',
                ],
                'student' => [
                    'student_id' => '2025-0003',
                    'course' => 'BS Accountancy',
                    'year_level' => '2nd Year',
                    'status' => 'inactive',
                    'birthday' => '2004-11-20',
                    'phone' => '09191234567',
                    'address' => 'Naga City',
                ],
            ],
        ];

        // Map student.status -> users.status constants
        $statusMap = [
            'enrolled' => User::STATUS_ACTIVE,
            'graduated' => User::STATUS_GRADUATED,
            'inactive' => User::STATUS_DROPPED,
        ];

        foreach ($students as $s) {
            // Create or fetch the user (idempotent)
            $studentUser = User::firstOrCreate(
                ['email' => $s['user']['email']],
                [
                    'last_name' => $s['user']['last_name'],
                    'first_name' => $s['user']['first_name'],
                    'middle_initial' => $s['user']['middle_initial'],
                    'password' => Hash::make('password'),
                    'role' => 'student',
                    'student_id' => $s['student']['student_id'],
                    'status' => $statusMap[$s['student']['status']] ?? User::STATUS_ACTIVE,
                    'course' => $s['student']['course'],
                    'year_level' => $s['student']['year_level'],
                    'birthday' => $s['student']['birthday'],
                    'phone' => $s['student']['phone'],
                    'address' => $s['student']['address'],
                ]
            );

            // Update only SAFE fields if they are missing
            $studentUser->fill([
                'last_name' => $studentUser->last_name ?: $s['user']['last_name'],
                'first_name' => $studentUser->first_name ?: $s['user']['first_name'],
                'middle_initial' => $studentUser->middle_initial ?: $s['user']['middle_initial'],
                'student_id' => $studentUser->student_id ?: $s['student']['student_id'],
                'course' => $studentUser->course ?: $s['student']['course'],
                'year_level' => $studentUser->year_level ?: $s['student']['year_level'],
                'status' => $studentUser->status ?: ($statusMap[$s['student']['status']] ?? User::STATUS_ACTIVE),
            ])->save();

            // Ensure student has an account (don't overwrite balance)
            $studentUser->account()->firstOrCreate([], [
                'balance' => -8048.00,
            ]);

            // Ensure Student profile record exists (don't overwrite existing updates)
            Student::firstOrCreate(
                ['user_id' => $studentUser->id],
                [
                    'student_id' => $s['student']['student_id'],
                    'last_name' => $s['user']['last_name'],
                    'first_name' => $s['user']['first_name'],
                    'middle_initial' => $s['user']['middle_initial'],
                    'email' => $studentUser->email,
                    'course' => $s['student']['course'],
                    'year_level' => $s['student']['year_level'],
                    'status' => $s['student']['status'],
                    'birthday' => $s['student']['birthday'] ?? null,
                    'phone' => $s['student']['phone'] ?? null,
                    'address' => $s['student']['address'] ?? null,
                    'total_balance' => 8048.00,
                ]
            );
        }
    }
}