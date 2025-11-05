<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Student;
use App\Models\Transaction;

class PayablesSeeder extends Seeder
{
    public function run(): void
    {
        $fees = [
            ['name' => 'Registration Fee', 'amount' => 4700.00, 'category' => 'Prelim'],
            ['name' => 'Tuition Fee', 'amount' => 1092.00, 'category' => 'Prelim'],
            ['name' => 'Lab Fee', 'amount' => 2256.00, 'category' => 'Midterm'],
            ['name' => 'Misc. Fee', 'amount' => 500.00, 'category' => 'Other Fees'],
        ];

        $students = User::where('role', 'student')->get();

        // ✅ Seed current + past 2 semesters
        $year = now()->year;
        $semesters = [
            [$year, '1st Sem'],
            [$year, '2nd Sem'],
            [$year - 1, '1st Sem'],
            [$year - 1, '2nd Sem'],
        ];

        foreach ($students as $student) {
            foreach ($semesters as [$y, $s]) {
                $totalPayables = 0;

                foreach ($fees as $fee) {
                    $transaction = Transaction::updateOrCreate(
                        [
                            'user_id' => $student->id,
                            'reference' => 'FEE-' . strtoupper(str_replace(' ', '_', $fee['name'])) . "-{$student->id}-{$y}-{$s}",
                        ],
                        [
                            'kind' => 'charge',
                            'type' => $fee['category'], // Prelim, Midterm, etc.
                            'year' => $y,
                            'semester' => $s,
                            'amount' => $fee['amount'],
                            'status' => 'pending',
                            'meta' => ['description' => $fee['name']],
                        ]
                    );

                    $totalPayables += $transaction->amount;
                }

                // Update balances
                $studentRecord = Student::where('user_id', $student->id)->first();
                if ($studentRecord) {
                    $studentRecord->update([
                        'total_balance' => $totalPayables,
                    ]);
                }

                if ($student->account) {
                    $student->account->update([
                        'balance' => -$totalPayables,
                    ]);
                } else {
                    $student->account()->firstOrCreate([], [
                        'balance' => -$totalPayables,
                    ]);
                }
            }
        }
    }
}