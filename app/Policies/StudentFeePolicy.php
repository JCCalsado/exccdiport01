<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class StudentFeePolicy
{
    use HandlesAuthorization;

    /**
     * Determine if the user can view any student fees
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role->value, ['admin', 'accounting']);
    }

    /**
     * Determine if the user can view student fee details
     */
    public function view(User $user, User $student): bool
    {
        // Admin and accounting can view any student
        if (in_array($user->role->value, ['admin', 'accounting'])) {
            return true;
        }

        // Students can only view their own fees
        return $user->id === $student->id;
    }

    /**
     * Determine if the user can create student fees
     */
    public function create(User $user): bool
    {
        return in_array($user->role->value, ['admin', 'accounting']);
    }

    /**
     * Determine if the user can update student fees
     */
    public function update(User $user, User $student): bool
    {
        return in_array($user->role->value, ['admin', 'accounting']);
    }

    /**
     * Determine if the user can record payments
     */
    public function recordPayment(User $user): bool
    {
        return in_array($user->role->value, ['admin', 'accounting']);
    }
}