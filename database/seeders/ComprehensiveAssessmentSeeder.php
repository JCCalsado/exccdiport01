<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Subject;
use App\Models\StudentAssessment;
use App\Models\Transaction;
use App\Models\Payment;
use App\Models\Fee;
use App\Models\Account;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class ComprehensiveAssessmentSeeder extends Seeder
{
    public function run(): void
    {
        $schoolYear = '2025-2026';
        $semester = '1st Sem';

        $students = User::where('role', 'student')
            ->where('email', 'like', 'student%@ccdi.edu.ph')
            ->get();

        $this->command->info('Generating assessments for ' . $students->count() . ' students...');

        foreach ($students as $student) {

            DB::beginTransaction();

            try {

                // ---------------------------------------------------------------------
                // 1. Ensure required student fields exist
                // ---------------------------------------------------------------------
                if (!$student->year_level || !$student->course) {
                    $this->command->warn("Skipping {$student->name} — Missing year_level or course.");
                    DB::commit();
                    continue;
                }

                // ---------------------------------------------------------------------
                // 2. Ensure student has an account
                // ---------------------------------------------------------------------
                $account = Account::firstOrCreate(
                    ['user_id' => $student->id],
                    ['account_number' => Account::generateAccountNumber()]
                );

                if (!$account) {
                    $this->command->error("Cannot create account for {$student->name}");
                    DB::commit();
                    continue;
                }

                // ---------------------------------------------------------------------
                // 3. Fetch subjects
                // ---------------------------------------------------------------------
                $subjects = Subject::active()
                    ->where('course', $student->course)
                    ->where('year_level', $student->year_level)
                    ->where('semester', $semester)
                    ->get();

                if ($subjects->isEmpty()) {
                    $this->command->warn("No subjects found for {$student->name}");
                    DB::commit();
                    continue;
                }

                $tuitionFee = 0;
                $labFee = 0;
                $subjectData = [];

                foreach ($subjects as $subject) {

                    $subjectCost = $subject->units * $subject->price_per_unit;
                    $tuitionFee += $subjectCost;

                    if ($subject->has_lab) {
                        $labFee += $subject->lab_fee;
                    }

                    $subjectData[] = [
                        'id' => $subject->id,
                        'units' => $subject->units,
                        'amount' => $subjectCost + ($subject->has_lab ? $subject->lab_fee : 0),
                    ];
                }

                // ---------------------------------------------------------------------
                // 4. Fetch other fees
                // ---------------------------------------------------------------------
                $otherFees = Fee::active()
                    ->where('year_level', $student->year_level)
                    ->where('semester', $semester)
                    ->where('school_year', $schoolYear)
                    ->get();

                $otherFeesTotal = $otherFees->sum('amount');

                $feeBreakdown = $otherFees->map(function ($fee) {
                    return ['id' => $fee->id, 'amount' => $fee->amount];
                })->toArray();

                $totalAssessment = $tuitionFee + $labFee + $otherFeesTotal;

                // ---------------------------------------------------------------------
                // 5. Create assessment
                // ---------------------------------------------------------------------
                $assessment = StudentAssessment::create([
                    'account_id' => $account->id,
                    'user_id' => $student->id,
                    'assessment_number' => StudentAssessment::generateAssessmentNumber(),
                    'year_level' => $student->year_level,
                    'semester' => $semester,
                    'school_year' => $schoolYear,
                    'tuition_fee' => $tuitionFee + $labFee,
                    'other_fees' => $otherFeesTotal,
                    'total_assessment' => $totalAssessment,
                    'subjects' => $subjectData,
                    'fee_breakdown' => $feeBreakdown,
                    'status' => 'active',
                    'created_by' => 1
                ]);

                // ---------------------------------------------------------------------
                // 6. Create tuition transactions
                // ---------------------------------------------------------------------
                foreach ($subjects as $subject) {

                    $totalSubjectCost = ($subject->units * $subject->price_per_unit) +
                        ($subject->has_lab ? $subject->lab_fee : 0);

                    Transaction::create([
                        'account_id' => $account->id,
                        'user_id' => $student->id,
                        'reference' => 'SUBJ-' . strtoupper(Str::random(8)),
                        'kind' => 'charge',
                        'category' => 'Tuition',
                        'type' => 'charge',
                        'year' => '2025',
                        'semester' => $semester,
                        'amount' => $totalSubjectCost,
                        'status' => 'pending',
                        'meta' => [
                            'assessment_id' => $assessment->id,
                            'subject_id' => $subject->id,
                            'subject_code' => $subject->code,
                            'subject_name' => $subject->name,
                            'units' => $subject->units,
                            'has_lab' => $subject->has_lab
                        ]
                    ]);
                }

                // ---------------------------------------------------------------------
                // 7. Create other fee transactions
                // ---------------------------------------------------------------------
                foreach ($otherFees as $fee) {
                    Transaction::create([
                        'account_id' => $account->id,
                        'user_id' => $student->id,
                        'fee_id' => $fee->id,
                        'reference' => 'FEE-' . strtoupper(Str::random(8)),
                        'kind' => 'charge',
                        'category' => $fee->category ?? 'Other Fee',
                        'type' => 'charge',
                        'year' => '2025',
                        'semester' => $semester,
                        'amount' => $fee->amount,
                        'status' => 'pending',
                        'meta' => [
                            'assessment_id' => $assessment->id,
                            'fee_code' => $fee->code,
                            'fee_name' => $fee->name,
                        ],
                    ]);
                }

                // ---------------------------------------------------------------------
                // 8. Generate payments (if needed)
                // ---------------------------------------------------------------------
                $currentBalance = abs($account->balance ?? 0);

                if ($currentBalance < $totalAssessment) {

                    $amountPaid = $totalAssessment - $currentBalance;
                    $payments = rand(1, 3);
                    $installment = $amountPaid / $payments;

                    for ($i = 0; $i < $payments; $i++) {

                        $paymentAmount = ($i == $payments - 1)
                            ? $amountPaid - ($installment * $i)
                            : $installment;

                        $paymentDate = now()->subDays(rand(1, 60));

                        if ($student->student) {
                            Payment::create([
                                'account_id' => $account->id,
                                'student_id' => $student->student->id,
                                'amount' => $paymentAmount,
                                'payment_method' => ['cash', 'gcash', 'bank_transfer'][rand(0, 2)],
                                'reference_number' => 'PAY-' . strtoupper(Str::random(10)),
                                'description' => "Payment #" . ($i + 1),
                                'status' => Payment::STATUS_COMPLETED,
                                'paid_at' => $paymentDate,
                            ]);
                        }

                        Transaction::create([
                            'account_id' => $account->id,
                            'user_id' => $student->id,
                            'reference' => 'PAY-' . strtoupper(Str::random(8)),
                            'payment_channel' => ['cash', 'gcash', 'bank_transfer'][rand(0, 2)],
                            'kind' => 'payment',
                            'category' => 'Payment',
                            'type' => 'payment',
                            'year' => '2025',
                            'semester' => $semester,
                            'amount' => $paymentAmount,
                            'status' => 'paid',
                            'paid_at' => $paymentDate,
                            'meta' => [
                                'description' => "Payment #" . ($i + 1),
                            ],
                        ]);
                    }
                }

                // ---------------------------------------------------------------------
                // 9. Update account balance
                // ---------------------------------------------------------------------
                $account->update([
                    'balance' => $currentBalance * -1 // Ensure correct sign
                ]);

                DB::commit();

            } catch (\Throwable $e) {
                DB::rollBack();
                $this->command->error("Error processing {$student->name}: " . $e->getMessage());
            }
        }

        $this->command->info('✓ Assessments, transactions, and payment history generated successfully!');
    }
}