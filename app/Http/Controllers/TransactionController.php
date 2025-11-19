<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Transaction;
use App\Models\Fee;
use App\Models\User;
use App\Models\StudentFeeItem;
use App\Models\Payment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Services\AccountService;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Admins & accounting see all, students see their own
        if (in_array($user->role->value, ['admin', 'accounting']) || $user->isAdmin()) {
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
        $users = User::select('id', 'name', 'email')->get();

        return Inertia::render('Transactions/Create', [
            'users' => $users,
        ]);
    }

    public function store(Request $request)
    {
        // Only staff can create transactions
        if (!($request->user()->isStaff() || in_array($request->user()->role->value, ['admin', 'accounting']))) {
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

    // This shouldn't be allowed
    // public function destroy(Transaction $transaction)
    // {
    //     $this->authorize('delete', $transaction);

    //     $transaction->delete();

    //     // Recalculate after deletion
    //     $this->recalculateAccount($transaction->user);

    //     return redirect()->route('transactions.index')
    //         ->with('success', 'Transaction deleted.');
    // }

    // protected function recalculateAccount($user): void
    // {
    //     $charges = $user->transactions()->where('type', 'charge')->sum('amount');
    //     $payments = $user->transactions()->where('type', 'payment')->where('status', 'paid')->sum('amount');
    //     $balance = $charges - $payments;

    //     $account = $user->account ?? $user->account()->create();
    //     $account->update(['balance' => $balance]);
    // }

    // public function payNow(Request $request)
    // {
    //     $user = $request->user();

    //     $data = $request->validate([
    //         'amount' => 'required|numeric|min:0.01',
    //         'payment_method' => 'required|string',
    //         'reference_number' => 'nullable|string',
    //         'paid_at' => 'required|date',
    //         'description' => 'required|string',
    //     ]);

    //     $tx = Transaction::create([
    //         'user_id' => $user->id,
    //         'reference' => 'PAY-' . Str::upper(Str::random(8)),
    //         'type' => 'payment',
    //         'amount' => $data['amount'],
    //         'status' => 'paid',
    //         'payment_channel' => $data['payment_method'],
    //         'paid_at' => $data['paid_at'],
    //         'meta' => [
    //             'reference_number' => $data['reference_number'] ?? null,
    //             'description' => $data['description'],
    //         ],
    //     ]);

    //     // update account balance
    //     $this->recalculateAccount($user);

    //     // ✅ Only check promotion if user has a student profile
    //     if ($user->role === 'student' && $user->student) {
    //         $this->checkAndPromoteStudent($user->student);
    //     }

    //     return redirect()->route('student.account')
    //         ->with('success', 'Payment recorded successfully.');
    // }

    // protected function checkAndPromoteStudent($student)
    // {
    //     if (!$student) {
    //         return; // no student profile, nothing to do
    //     }

    //     $user = $student->user;
    //     if (!$user) {
    //         return; // student not linked to user
    //     }

    //     AccountService::recalculate($user);

    //     $account = $user->account;

    //     if ($account && $account->balance <= 0) {
    //         $this->promoteYearLevel($student);
    //         $this->assignNextPayables($student);
    //     }
    // }

    // protected function promoteYearLevel($student)
    // {
    //     $levels = ['1st Year', '2nd Year', '3rd Year', '4th Year'];
    //     $currentIndex = array_search($student->year_level, $levels);

    //     if ($currentIndex !== false && $currentIndex < count($levels) - 1) {
    //         $student->year_level = $levels[$currentIndex + 1];
    //         $student->save();
    //     }
    // }

    // protected function assignNextPayables($student)
    // {
    //     // find fees for the new year/semester
    //     $fees = Fee::where('year_level', $student->year_level)
    //         ->where('semester', '1st Sem') // or detect dynamically
    //         ->get();

    //     foreach ($fees as $fee) {
    //         $student->transactions()->create([
    //             'reference' => 'FEE-' . strtoupper($fee->name) . '-' . $student->id,
    //             'type' => 'charge',
    //             'amount' => $fee->amount,
    //             'status' => 'pending',
    //             'meta' => ['description' => $fee->name],
    //         ]);
    //     }
    // }

    public function payNow(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'fee_item_ids' => 'nullable|array', // Allow selecting specific fees
            'fee_item_ids.*' => 'exists:student_fee_items,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string',
            'reference_number' => 'nullable|string',
            'paid_at' => 'required|date',
            'description' => 'required|string',
        ]);

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
                    return back()->withErrors(['amount' => 'Amount exceeds total balance of selected fees.']);
                }

                // Distribute payment across selected fees
                $remainingAmount = $validated['amount'];

                foreach ($feeItems as $feeItem) {
                    if ($remainingAmount <= 0) break;

                    $amountToPay = min($remainingAmount, $feeItem->balance);

                    // Create payment record
                    $payment = Payment::create([
                        'student_id' => $user->student->id,
                        'fee_item_id' => $feeItem->id,
                        'amount' => $amountToPay,
                        'payment_method' => $validated['payment_method'],
                        'reference_number' => $validated['reference_number'] ?? 'PAY-' . strtoupper(Str::random(10)),
                        'description' => $validated['description'],
                        'status' => Payment::STATUS_COMPLETED,
                        'paid_at' => $validated['paid_at'],
                    ]);

                    // Create transaction record
                    Transaction::create([
                        'user_id' => $user->id,
                        'reference' => $payment->reference_number,
                        'payment_channel' => $validated['payment_method'],
                        'kind' => 'payment',
                        'type' => 'Payment',
                        'amount' => $amountToPay,
                        'status' => 'paid',
                        'paid_at' => $validated['paid_at'],
                        'meta' => [
                            'payment_id' => $payment->id,
                            'fee_item_id' => $feeItem->id,
                            'fee_name' => $feeItem->fee->name,
                            'description' => $validated['description'],
                        ],
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

                    $amountToPay = min($remainingAmount, $feeItem->balance);

                    Payment::create([
                        'student_id' => $user->student->id,
                        'fee_item_id' => $feeItem->id,
                        'amount' => $amountToPay,
                        'payment_method' => $validated['payment_method'],
                        'reference_number' => $validated['reference_number'] ?? 'PAY-' . strtoupper(Str::random(10)),
                        'description' => $validated['description'],
                        'status' => Payment::STATUS_COMPLETED,
                        'paid_at' => $validated['paid_at'],
                    ]);

                    Transaction::create([
                        'user_id' => $user->id,
                        'reference' => 'PAY-' . strtoupper(Str::random(8)),
                        'payment_channel' => $validated['payment_method'],
                        'kind' => 'payment',
                        'type' => 'Payment',
                        'amount' => $amountToPay,
                        'status' => 'paid',
                        'paid_at' => $validated['paid_at'],
                        'meta' => [
                            'fee_item_id' => $feeItem->id,
                            'fee_name' => $feeItem->fee->name,
                            'description' => $validated['description'],
                        ],
                    ]);

                    $remainingAmount -= $amountToPay;
                }
            }

            // Recalculate balance
            AccountService::recalculate($user);

            DB::commit();

            return redirect()->route('student.account')
                ->with('success', 'Payment recorded successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Payment failed: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Payment failed. Please try again.']);
        }
    }

    protected function recalculateAccount($user): void
    {
        $charges = $user->transactions()->where('kind', 'charge')->sum('amount');
        $payments = $user->transactions()->where('kind', 'payment')->where('status', 'paid')->sum('amount');
        $balance = $charges - $payments;

        $account = $user->account ?? $user->account()->create();
        $account->update(['balance' => $balance]);
    }

    protected function checkAndPromoteStudent($student)
    {
        if (!$student) {
            return;
        }

        $user = $student->user;
        if (!$user) {
            return;
        }

        $account = $user->account;

        if ($account && $account->balance <= 0) {
            $this->promoteYearLevel($student);
            $this->assignNextPayables($student);
        }
    }

    protected function promoteYearLevel($student)
    {
        $levels = ['1st Year', '2nd Year', '3rd Year', '4th Year'];
        $currentIndex = array_search($student->year_level, $levels);

        if ($currentIndex !== false && $currentIndex < count($levels) - 1) {
            $student->year_level = $levels[$currentIndex + 1];
            $student->save();
        }
    }

    protected function assignNextPayables($student)
    {
        // find fees for the new year/semester
        $fees = \App\Models\Fee::where('year_level', $student->year_level)
            ->where('semester', '1st Sem')
            ->get();

        foreach ($fees as $fee) {
            $student->user->transactions()->create([
                'reference' => 'FEE-' . strtoupper($fee->name) . '-' . $student->id,
                'kind' => 'charge',
                'type' => $fee->name,
                'amount' => $fee->amount,
                'status' => 'pending',
                'meta' => ['description' => $fee->name],
            ]);
        }
    }
    public function download()
    {
        $transactions = Transaction::with('fee')->orderBy('created_at', 'desc')->get();

        $pdf = Pdf::loadView('pdf.transactions', [
            'transactions' => $transactions
        ]);

        return $pdf->download('transactions.pdf');
    }
}
