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
        
        // Load student with fee items
        $user->load([
            'student.feeItems' => function ($query) {
                $query->with('fee')->orderBy('status')->orderBy('created_at');
            },
            'transactions' => function ($query) {
                $query->orderBy('created_at', 'desc');
            }
        ]);

        // Get current term
        $year = now()->year;
        $month = now()->month;
        $semester = ($month >= 6 && $month <= 10) ? '1st Sem' : (($month >= 11 || $month <= 3) ? '2nd Sem' : 'Summer');
        $schoolYear = $year . '-' . ($year + 1);

        // Get fee items for current term
        $feeItems = $user->student->feeItems()
            ->with('fee')
            ->where('school_year', $schoolYear)
            ->where('semester', $semester)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'fee_id' => $item->fee_id,
                    'name' => $item->fee->name,
                    'category' => $item->fee->category,
                    'original_amount' => $item->original_amount,
                    'amount_paid' => $item->amount_paid,
                    'balance' => $item->balance,
                    'status' => $item->status,
                    'payment_percentage' => $item->payment_percentage,
                ];
            });

        return Inertia::render('Student/AccountOverview', [
            'account' => $user->account,
            'transactions' => $user->transactions ?? [],
            'fees' => $feeItems,
            'currentTerm' => [
                'year' => $year,
                'semester' => $semester,
                'school_year' => $schoolYear,
            ],
        ]);
    }
}