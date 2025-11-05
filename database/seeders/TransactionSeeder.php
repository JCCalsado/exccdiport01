<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Str;

class TransactionSeeder extends Seeder
{
    public function run(): void
    {
        $students = User::where('role', 'student')->get();

        if ($students->isEmpty()) {
            $this->command->info('No students found — run UserSeeder first.');
            return;
        }

        $categories = [
            'Prelim' => [
                ['name' => 'Tuition Fee', 'amount' => 3000.00],
                ['name' => 'Lab Fee', 'amount' => 500.00],
            ],
            'Midterm' => [
                ['name' => 'Tuition Fee', 'amount' => 3000.00],
                ['name' => 'Midterm Exam Fee', 'amount' => 150.00],
            ],
            'Prefinal' => [
                ['name' => 'Tuition Fee', 'amount' => 3000.00],
                ['name' => 'Prefinal Exam Fee', 'amount' => 150.00],
            ],
            'Final' => [
                ['name' => 'Tuition Fee', 'amount' => 3000.00],
                ['name' => 'Final Exam Fee', 'amount' => 150.00],
            ],
            'Intramurals' => [
                ['name' => 'Shirt', 'amount' => 200.00],
                ['name' => 'Team Contribution', 'amount' => 150.00],
                ['name' => 'Gen Expense', 'amount' => 100.00],
                ['name' => 'Acquaintance', 'amount' => 50.00],
            ],
            'Other Fees' => [
                ['name' => 'Library Fee', 'amount' => 100.00],
                ['name' => 'Sports Contribution', 'amount' => 200.00],
            ],
        ];

        $year = now()->year;
        $semesters = [
            [$year, '1st Sem'],
            [$year, '2nd Sem'],
            [$year - 1, '1st Sem'],
            [$year - 1, '2nd Sem'],
        ];

        foreach ($students as $student) {
            foreach ($semesters as [$y, $s]) {
                foreach ($categories as $category => $items) {
                    $amount = array_sum(array_column($items, 'amount'));
                    $reference = 'TXN-' . strtoupper(Str::slug($category)) . "-{$student->id}-{$y}-{$s}";

                    Transaction::updateOrCreate(
                        [
                            'user_id' => $student->id,
                            'reference' => $reference,
                        ],
                        [
                            'payment_channel' => null,
                            'type' => $category,   // Prelim, Midterm, etc.
                            'kind' => 'charge',
                            'year' => $y,
                            'semester' => $s,
                            'amount' => $amount,
                            'status' => 'pending',
                            'meta' => [
                                'items' => $items,
                                'seeded' => true,
                            ],
                        ]
                    );
                }
            }
        }
    }
}