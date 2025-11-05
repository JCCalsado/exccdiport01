<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Notification;
use App\Models\Transaction;

class StudentDashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Get account with transactions
        $account = $user->account()->with('transactions')->first();
        
        if (!$account) {
            $account = $user->account()->create(['balance' => 0]);
        }

        // Calculate stats
        $totalCharges = $user->transactions()->where('kind', 'charge')->sum('amount');
        $totalPayments = $user->transactions()
            ->where('kind', 'payment')
            ->where('status', 'paid')
            ->sum('amount');
        $remainingBalance = abs($account->balance);
        $pendingChargesCount = $user->transactions()
            ->where('kind', 'charge')
            ->where('status', 'pending')
            ->count();

        // Get notifications
        $notifications = Notification::where(function ($q) use ($user) {
            $q->where('target_role', $user->role->value)
              ->orWhere('target_role', 'all');
        })
        ->orderByDesc('start_date')
        ->take(5)
        ->get();

        // Get recent transactions
        $recentTransactions = $user->transactions()
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->map(function ($txn) {
                return [
                    'id' => $txn->id,
                    'reference' => $txn->reference,
                    'type' => $txn->type ?: 'General',
                    'amount' => $txn->amount,
                    'status' => $txn->status,
                    'created_at' => $txn->created_at,
                ];
            });

        return Inertia::render('Student/Dashboard', [
            'account' => $account,
            'notifications' => $notifications,
            'recentTransactions' => $recentTransactions,
            'stats' => [
                'total_fees' => (float) $totalCharges,
                'total_paid' => (float) $totalPayments,
                'remaining_balance' => (float) $remainingBalance,
                'pending_charges_count' => $pendingChargesCount,
            ],
        ]);
    }
}