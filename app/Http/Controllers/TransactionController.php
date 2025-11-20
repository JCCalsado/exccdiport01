<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Services\AccountService;
use App\Models\Transaction;
use App\Models\StudentFeeItem;
use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Admins & accounting see all, students see their own
        if ($user->isStaff()) {
            $transactions = Transaction::with('user')
                ->orderByDesc('year')
                ->orderBy('semester')
                ->get()
                ->groupBy(fn($txn) => "{$txn->year} {$txn->semester}");
        } else {
            $transactions = $user->transactions()
                ->with('user')
                ->orderByDesc('year')
                ->orderBy('semester')
                ->get()
                ->groupBy(fn($txn) => "{$txn->year} {$txn->semester}");
        }

        return Inertia::render('Transactions/Index', [
            'auth' => ['user' => $user],
            'transactionsByTerm' => $transactions,
            'account' => $user->account,
            'currentTerm' => $this->getCurrentTerm(),
        ]);
    }

    private function getCurrentTerm(): string
    {
        $year = now()->year;
        $month = now()->month;

        if ($month >= 6 && $month <= 10) {
            $semester = '1st Sem';
        } elseif ($month >= 11 || $month <= 3) {
            $semester = '2nd Sem';
        } else {
            $semester = 'Summer';
        }

        return "{$year} {$semester}";
    }

    public function create()
    {
        $users = \App\Models\User::select('id', 'name', 'email')->get();

        return Inertia::render('Transactions/Create', [
            'users' => $users,
        ]);
    }

    public function store(Request $request)
    {
        // Only staff can create transactions
        if (!$request->user()->isStaff()) {
            abort(403, 'Unauthorized action.');
        }

        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:0.01',
            'type' => 'required|in:charge,payment',
            'payment_channel' => 'nullable|string',
        ]);

        $transaction = Transaction::create([
            'user_id' => $data['user_id'],
            'reference' => 'SYS-' . Str::upper(Str::random(8)),
            'amount' => $data['amount'],
            'type' => $data['type'],
            'status' => $data['type'] === 'payment' ? 'paid' : 'pending',
            'payment_channel' => $data['payment_channel'] ?? null,
        ]);

        // Recalculate balance
        $this->recalculateAccount($transaction->user);

        return redirect()->route('transactions.index')
            ->with('success', 'Transaction created successfully!');
    }

    public function show(Transaction $transaction)
    {
        return Inertia::render('Transactions/Show', [
            'transaction' => $transaction->load('user'),
        ]);
    }

    public function download(Request $request)
    {
        $user = $request->user();
        $year = $request->input('year', now()->year);
        $semester = $request->input('semester', $this->getCurrentSemester($year));

        // Only staff can download all, students see their own
        if ($user->isStaff()) {
            $transactions = Transaction::where('year', $year)
                ->where('semester', $semester)
                ->with('user')
                ->get();
        } else {
            $transactions = $user->transactions()
                ->where('year', $year)
                ->where('semester', $semester)
                ->get();
        }

        $pdf = Pdf::loadView('pdf.transactions', [
            'transactions' => $transactions,
            'year' => $year,
            'semester' => $semester,
            'user' => $user,
        ]);

        return $pdf->download("transactions-{$year}-{$semester}.pdf");
    }

    private function getCurrentSemester($year)
    {
        $month = now()->month;

        if ($month >= 6 && $month <= 10) {
            return '1st Sem';
        } elseif ($month >= 11 || $month <= 3) {
            return '2nd Sem';
        } else {
            return 'Summer';
        }
    }

    public function payNow(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string',
            'fee_item_ids' => 'nullable|array',
            'fee_item_ids.*' => 'exists:student_fee_items,id',
            'description' => 'required|string',
        ]);

        $user = $request->user();

        if (!$user->student) {
            return back()->withErrors(['error' => 'Student profile not found.']);
        }

        DB::beginTransaction();
        try {
            // If specific fees selected
            if (!empty($validated['fee_item_ids'])) {
                if (!$user->student) {
                    return back()->withErrors(['error' => 'Student profile not found.']);
                }

                $feeItems = StudentFeeItem::whereIn('id', $validated['fee_item_ids'])
                    ->where('student_id', $user->student->id)
                    ->unpaid()
                    ->get();

                $totalBalance = $feeItems->sum('balance');

                if ($validated['amount'] > $totalBalance) {
                    return back()->withErrors(['error' => 'Payment amount exceeds outstanding balance.']);
                }

                $remainingAmount = $validated['amount'];

                foreach ($feeItems as $feeItem) {
                    if ($remainingAmount <= 0) break;

                    $amountToPay = min($feeItem->balance, $remainingAmount);

                    // Update fee item
                    $feeItem->balance = max(0, $feeItem->balance - $amountToPay);
                    $feeItem->amount_paid = $feeItem->amount_paid + $amountToPay;
                    
                    if ($feeItem->balance === 0) {
                        $feeItem->status = 'paid';
                    } elseif ($feeItem->amount_paid > 0 && $feeItem->balance > 0) {
                        $feeItem->status = 'partial';
                    }
                    
                    $feeItem->save();

                    // Create payment record
                    $payment = Payment::create([
                        'student_id' => $user->student->id,
                        'user_id' => $user->id,
                        'reference_number' => 'PAY-' . Str::upper(Str::random(8)),
                        'amount' => $amountToPay,
                        'payment_method' => $validated['payment_method'],
                        'status' => 'pending',
                        'description' => $validated['description'],
                    ]);

                    $remainingAmount -= $amountToPay;
                }
            } else {
                // General payment (apply to oldest unpaid fee items)
                if (!$user->student) {
                    return back()->withErrors(['error' => 'Student profile not found.']);
                }

                $feeItems = StudentFeeItem::where('student_id', $user->student->id)
                    ->unpaid()
                    ->orderBy('created_at')
                    ->get();

                $remainingAmount = $validated['amount'];

                foreach ($feeItems as $feeItem) {
                    if ($remainingAmount <= 0) break;

                    $amountToPay = min($feeItem->balance, $remainingAmount);

                    // Update fee item
                    $feeItem->balance = max(0, $feeItem->balance - $amountToPay);
                    $feeItem->amount_paid = $feeItem->amount_paid + $amountToPay;
                    
                    if ($feeItem->balance === 0) {
                        $feeItem->status = 'paid';
                    } elseif ($feeItem->amount_paid > 0 && $feeItem->balance > 0) {
                        $feeItem->status = 'partial';
                    }
                    
                    $feeItem->save();

                    // Create payment record
                    $payment = Payment::create([
                        'student_id' => $user->student->id,
                        'user_id' => $user->id,
                        'reference_number' => 'PAY-' . Str::upper(Str::random(8)),
                        'amount' => $amountToPay,
                        'payment_method' => $validated['payment_method'],
                        'status' => 'pending',
                        'description' => $validated['description'],
                    ]);

                    $remainingAmount -= $amountToPay;
                }
            }

            // Update student's total balance
            $user->student->update([
                'total_balance' => $user->student->feeItems()->sum('balance'),
            ]);

            DB::commit();

            return redirect()->route('transactions.index')
                ->with('success', 'Payment processed successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Payment processing failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'data' => $validated,
            ]);

            return back()->withErrors(['error' => 'Payment processing failed. Please try again.']);
        }
    }

    protected function recalculateAccount($user): void
    {
        $charges = $user->transactions()->where('type', 'charge')->sum('amount');
        $payments = $user->transactions()->where('type', 'payment')->where('status', 'paid')->sum('amount');
        $balance = $charges - $payments;

        $account = $user->account ?? $user->account()->create();
        $account->update(['balance' => $balance]);
    }
}