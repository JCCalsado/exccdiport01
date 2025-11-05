<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\StudentAssessment;

class StudentAssessmentSeeder extends Seeder
{
    public function run(): void
    {
        $students = User::where('role', 'student')->get();
        $schoolYear = '2025-2026';

        foreach ($students as $student) {
            StudentAssessment::create([
                'user_id' => $student->id,
                'assessment_number' => StudentAssessment::generateAssessmentNumber(),
                'year_level' => $student->year_level,
                'semester' => '1st Sem',
                'school_year' => $schoolYear,
                'tuition_fee' => 5000.00,
                'other_fees' => 3048.00,
                'total_assessment' => 8048.00,
                'subjects' => [],
                'fee_breakdown' => [
                    ['name' => 'Laboratory Fee', 'amount' => 2000.00],
                    ['name' => 'Library Fee', 'amount' => 500.00],
                    ['name' => 'Miscellaneous Fee', 'amount' => 548.00],
                ],
                'status' => 'active',
                'created_by' => 1,
            ]);
        }

        $this->command->info('Student assessments seeded successfully!');
    }
}