<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Student;
use App\Models\Account;

class BackfillStudentAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $students = Student::all();

        foreach ($students as $student) {
            if (!$student->account_id) {
                $account = Account::create([
                    'account_number' => Account::generateAccountNumber(),
                    'user_id' => $student->user_id,
                ]);

                $student->update([
                    'account_id' => $account->id
                ]);

                echo "Created account for {$student->user->full_name}\n";
            }
        }
    }
}
