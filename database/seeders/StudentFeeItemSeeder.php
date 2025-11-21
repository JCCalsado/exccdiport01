<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Student;
use App\Models\Fee;
use App\Models\StudentFeeItem;

class StudentFeeItemSeeder extends Seeder
{
    public function run(): void
    {
        $schoolYear = '2025-2026';
        $semester = '1st Sem';

        // Get all active students
        $students = Student::where('status', 'enrolled')->get();

        $this->command->info("Creating fee items for {$students->count()} students...");

        foreach ($students as $student) {

            // 🔥 Ensure student has an account_id
            if (!$student->account_id) {
                $this->command->warn("⚠️ Student {$student->id} has no account_id. Skipping...");
                continue;
            }

            // Get fees for this student's year level
            $fees = Fee::active()
                ->where('year_level', $student->year_level)
                ->where('semester', $semester)
                ->where('school_year', $schoolYear)
                ->get();

            foreach ($fees as $fee) {

                // Check if already assigned
                $exists = StudentFeeItem::where('student_id', $student->id)
                    ->where('fee_id', $fee->id)
                    ->where('school_year', $schoolYear)
                    ->where('semester', $semester)
                    ->exists();

                if (!$exists) {
                    StudentFeeItem::create([
                        'student_id' => $student->id,
                        'account_id' => $student->account_id,     // 👈 REQUIRED FIX
                        'fee_id' => $fee->id,
                        'school_year' => $schoolYear,
                        'semester' => $semester,
                        'original_amount' => $fee->amount,
                        'amount_paid' => 0,
                        'balance' => $fee->amount,
                        'status' => 'pending',
                        'assigned_by' => 1, // Admin user ID
                    ]);
                }
            }

            $this->command->info("✓ Assigned fees to {$student->first_name} {$student->last_name}");
        }

        $this->command->info('✅ Student fee items created successfully!');
    }
}