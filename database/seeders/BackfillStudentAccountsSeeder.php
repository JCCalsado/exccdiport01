<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Student;
use App\Models\Account;
use Illuminate\Support\Facades\DB;

class BackfillStudentAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $students = Student::with('user')->get();

        foreach ($students as $student) {

            DB::beginTransaction();

            try {

                // Ensure user relation exists
                if (!$student->user) {
                    $this->command->warn("Skipping Student ID {$student->id} — Missing related user.");
                    DB::commit();
                    continue;
                }

                // If account already exists, skip
                if ($student->account_id) {
                    DB::commit();
                    continue;
                }

                // Create or get existing account of the user
                $account = Account::firstOrCreate(
                    ['user_id' => $student->user_id],
                    ['account_number' => Account::generateAccountNumber()]
                );

                // Bind account to student record
                if (!$student->account_id) {
                    $student->update([
                        'account_id' => $account->id
                    ]);
                }

                $this->command->info(
                    "✔ Created/linked account for {$student->user->full_name}"
                );

                DB::commit();

            } catch (\Throwable $e) {

                DB::rollBack();
                $this->command->error("❌ Error for Student ID {$student->id}: " . $e->getMessage());
            }
        }

        $this->command->info("✓ Backfill complete!");
    }
}