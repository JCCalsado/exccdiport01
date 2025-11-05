<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Fee;

class FeeSeeder extends Seeder
{
    public function run(): void
    {
        $schoolYear = '2025-2026';
        $yearLevels = ['1st Year', '2nd Year', '3rd Year', '4th Year'];
        $semesters = ['1st Sem', '2nd Sem'];

        $feeTemplates = [
            ['name' => 'Tuition Fee', 'category' => 'Tuition', 'amount' => 5000.00],
            ['name' => 'Laboratory Fee', 'category' => 'Laboratory', 'amount' => 2000.00],
            ['name' => 'Library Fee', 'category' => 'Library', 'amount' => 500.00],
            ['name' => 'Athletic Fee', 'category' => 'Athletic', 'amount' => 300.00],
            ['name' => 'Miscellaneous Fee', 'category' => 'Miscellaneous', 'amount' => 1200.00],
            ['name' => 'Registration Fee', 'category' => 'Miscellaneous', 'amount' => 200.00],
        ];

        foreach ($yearLevels as $yearLevel) {
            foreach ($semesters as $semester) {
                foreach ($feeTemplates as $template) {
                    $code = Fee::generateCode(
                        $template['category'],
                        $schoolYear,
                        $semester
                    );

                    // Use updateOrCreate to avoid duplicates
                    Fee::updateOrCreate(
                        [
                            'code' => $code,
                        ],
                        [
                            'name' => $template['name'],
                            'category' => $template['category'],
                            'amount' => $template['amount'],
                            'year_level' => $yearLevel,
                            'semester' => $semester,
                            'school_year' => $schoolYear,
                            'description' => "Standard {$template['name']} for {$yearLevel} - {$semester}",
                            'is_active' => true,
                        ]
                    );
                }
            }
        }

        $this->command->info('Fees seeded successfully!');
    }
}