<?php

namespace App\Services;

use App\Models\User;

class AccountService
{
    /**
     * Recalculate a user's balance based on transactions.
     */
    public static function recalculate(User $user): void
    {
        $charges = $user->transactions()
            ->where('type', 'charge')
            ->sum('amount');

        $payments = $user->transactions()
            ->where('type', 'payment')
            ->where('status', 'paid')
            ->sum('amount');

        $balance = $charges - $payments;

        // ✅ Update or create account
        $account = $user->account ?? $user->account()->create(['balance' => 0]);
        $account->update(['balance' => $balance]);

        // ✅ Update linked student record
        if ($user->student) {
            $user->student->update(['total_balance' => $balance]);

            // 🎓 Auto-promotion if balance is fully cleared
            if ($balance <= 0) {
                self::promoteStudent($user);
            }
        }
    }

    /**
     * Promote student to next year level when balance = 0
     */
    protected static function promoteStudent(User $user): void
    {
        $student = $user->student;

        $yearLevels = [
            '1st Year',
            '2nd Year',
            '3rd Year',
            '4th Year',
        ];

        $currentIndex = array_search($student->year_level, $yearLevels);

        if ($currentIndex !== false && $currentIndex < count($yearLevels) - 1) {
            // ✅ Promote to next year
            $student->update([
                'year_level' => $yearLevels[$currentIndex + 1],
            ]);
        } elseif ($currentIndex === count($yearLevels) - 1) {
            // ✅ Graduate if last year is completed
            $student->update([
                'status' => 'graduated',
            ]);
        }
    }
}