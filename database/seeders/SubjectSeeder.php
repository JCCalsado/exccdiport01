<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Subject;

class SubjectSeeder extends Seeder
{
    public function run(): void
    {
        $subjects = [
            // 1st Year - 1st Sem - BS Computer Science
            [
                'code' => 'CS101',
                'name' => 'Introduction to Computing',
                'units' => 3,
                'price_per_unit' => 350.00,
                'year_level' => '1st Year',
                'semester' => '1st Sem',
                'course' => 'BS Computer Science',
                'has_lab' => true,
                'lab_fee' => 500.00,
            ],
            [
                'code' => 'MATH101',
                'name' => 'College Algebra',
                'units' => 3,
                'price_per_unit' => 350.00,
                'year_level' => '1st Year',
                'semester' => '1st Sem',
                'course' => 'BS Computer Science',
                'has_lab' => false,
                'lab_fee' => 0,
            ],
            [
                'code' => 'ENG101',
                'name' => 'Communication Skills',
                'units' => 3,
                'price_per_unit' => 350.00,
                'year_level' => '1st Year',
                'semester' => '1st Sem',
                'course' => 'BS Computer Science',
                'has_lab' => false,
                'lab_fee' => 0,
            ],
            [
                'code' => 'PE101',
                'name' => 'Physical Education 1',
                'units' => 2,
                'price_per_unit' => 350.00,
                'year_level' => '1st Year',
                'semester' => '1st Sem',
                'course' => 'BS Computer Science',
                'has_lab' => false,
                'lab_fee' => 0,
            ],
            [
                'code' => 'NSTP101',
                'name' => 'CWTS 1',
                'units' => 3,
                'price_per_unit' => 350.00,
                'year_level' => '1st Year',
                'semester' => '1st Sem',
                'course' => 'BS Computer Science',
                'has_lab' => false,
                'lab_fee' => 0,
            ],

            // 1st Year - 2nd Sem - BS Computer Science
            [
                'code' => 'CS102',
                'name' => 'Computer Programming 1',
                'units' => 3,
                'price_per_unit' => 350.00,
                'year_level' => '1st Year',
                'semester' => '2nd Sem',
                'course' => 'BS Computer Science',
                'has_lab' => true,
                'lab_fee' => 800.00,
            ],
            [
                'code' => 'MATH102',
                'name' => 'Trigonometry',
                'units' => 3,
                'price_per_unit' => 350.00,
                'year_level' => '1st Year',
                'semester' => '2nd Sem',
                'course' => 'BS Computer Science',
                'has_lab' => false,
                'lab_fee' => 0,
            ],
            [
                'code' => 'ENG102',
                'name' => 'Technical Writing',
                'units' => 3,
                'price_per_unit' => 350.00,
                'year_level' => '1st Year',
                'semester' => '2nd Sem',
                'course' => 'BS Computer Science',
                'has_lab' => false,
                'lab_fee' => 0,
            ],

            // 2nd Year - 1st Sem - BS Computer Science
            [
                'code' => 'CS201',
                'name' => 'Data Structures and Algorithms',
                'units' => 3,
                'price_per_unit' => 350.00,
                'year_level' => '2nd Year',
                'semester' => '1st Sem',
                'course' => 'BS Computer Science',
                'has_lab' => true,
                'lab_fee' => 800.00,
            ],
            [
                'code' => 'CS202',
                'name' => 'Object-Oriented Programming',
                'units' => 3,
                'price_per_unit' => 350.00,
                'year_level' => '2nd Year',
                'semester' => '1st Sem',
                'course' => 'BS Computer Science',
                'has_lab' => true,
                'lab_fee' => 800.00,
            ],

            // BS Information Technology subjects
            [
                'code' => 'IT101',
                'name' => 'Introduction to Information Technology',
                'units' => 3,
                'price_per_unit' => 350.00,
                'year_level' => '1st Year',
                'semester' => '1st Sem',
                'course' => 'BS Information Technology',
                'has_lab' => true,
                'lab_fee' => 500.00,
            ],
            [
                'code' => 'IT102',
                'name' => 'Web Development Fundamentals',
                'units' => 3,
                'price_per_unit' => 350.00,
                'year_level' => '1st Year',
                'semester' => '2nd Sem',
                'course' => 'BS Information Technology',
                'has_lab' => true,
                'lab_fee' => 800.00,
            ],
        ];

        foreach ($subjects as $subject) {
            // Use updateOrCreate to avoid duplicate entry errors
            Subject::updateOrCreate(
                ['code' => $subject['code']], // Match by code
                array_merge($subject, [
                    'description' => "Standard course for {$subject['course']}",
                    'is_active' => true,
                ])
            );
        }

        $this->command->info('Subjects seeded successfully!');
    }
}