<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Fee;

class StudentAccountController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        
        // Load account
        if (!$user->account) {
            $user->account()->create(['balance' => 0]);
        }
        
        // Load transactions directly through user relationship (NOT account)
        // This is the key fix - seeders use user_id, not account_id
        $user->load(['transactions' => function ($query) {
            $query->orderBy('created_at', 'desc');
        }]);

        // Get current term
        $year = now()->year;
        $month = now()->month;
        if ($month >= 6 && $month <= 10) {
            $semester = '1st Sem';
        } elseif ($month >= 11 || $month <= 3) {
            $semester = '2nd Sem';
        } else {
            $semester = 'Summer';
        }

        // Get fees for current term and student's year level
        $fees = Fee::active()
            ->where('year_level', $user->year_level)
            ->where('semester', $semester)
            ->where('school_year', $year . '-' . ($year + 1))
            ->select('name', 'amount', 'category')
            ->get();

        // If no fees found, use fallback
        if ($fees->isEmpty()) {
            $fees = collect([
                ['name' => 'Registration Fee', 'amount' => 200.0, 'category' => 'Miscellaneous'],
                ['name' => 'Tuition Fee', 'amount' => 5000.0, 'category' => 'Tuition'],
                ['name' => 'Lab Fee', 'amount' => 2000.0, 'category' => 'Laboratory'],
                ['name' => 'Library Fee', 'amount' => 500.0, 'category' => 'Library'],
                ['name' => 'Misc. Fee', 'amount' => 1200.0, 'category' => 'Miscellaneous'],
            ]);
        }

        return Inertia::render('Student/AccountOverview', [
            'account'      => $user->account,
            'transactions' => $user->transactions ?? [], // Use user->transactions, not account->transactions
            'fees'         => $fees->values(),
        ]);
    }
}