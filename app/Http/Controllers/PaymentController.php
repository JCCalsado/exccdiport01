<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use App\Services\PaymentGatewayService;
use App\Services\FraudDetectionService;
use App\Models\Payment;
use App\Models\Student;
use App\Models\StudentFeeItem;
use App\Events\PaymentStatusChanged;

class PaymentController extends Controller
{
    public function __construct(
        private PaymentGatewayService $paymentGatewayService,
        private FraudDetectionService $fraudDetectionService
    ) {
        $this->middleware('auth');
        $this->middleware('payment.security')->only(['initiate', 'process']);
        $this->middleware('throttle:5,1')->only(['initiate']); // 5 attempts per minute
    }

    /**
     * Display payment creation form
     */
    public function create(Request $request)
    {
        $user = Auth::user();

        // Only students can make payments
        if ($user->role !== 'student') {
            abort(403, 'Unauthorized access');
        }

        $student = $user->student;
        if (!$student) {
            abort(404, 'Student record not found');
        }

        // Get outstanding fee items
        $outstandingFees = StudentFeeItem::where('student_id', $student->id)
            ->where('balance', '>', 0)
            ->with(['fee', 'feeCategory'])
            ->orderBy('created_at')
            ->get();

        // Get available payment methods
        $paymentMethods = $this->paymentGatewayService->getAvailablePaymentMethods();

        // Get recent payment history
        $recentPayments = Payment::where('student_id', $student->id)
            ->with('latestGatewayDetail')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        return Inertia::render('Payment/Create', [
            'student' => $student->load('user'),
            'outstandingFees' => $outstandingFees,
            'paymentMethods' => $paymentMethods,
            'recentPayments' => $recentPayments,
            'defaultAmount' => $request->input('amount'),
            'selectedFees' => $request->input('fees', []),
        ]);
    }

    /**
     * Initiate payment process
     */
    public function initiate(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1|max:100000',
            'payment_method' => 'required|in:gcash,paypal,stripe',
            'description' => 'required|string|max:255',
            'fee_items' => 'nullable|array',
            'fee_items.*' => 'exists:student_fee_items,id',
        ]);

        $user = Auth::user();
        $student = $user->student;

        if (!$student) {
            return response()->json([
                'success' => false,
                'message' => 'Student record not found'
            ], 404);
        }

        // Fraud detection check
        if ($this->fraudDetectionService->isSuspicious($request->all(), $student)) {
            Log::warning('Suspicious payment attempt detected', [
                'student_id' => $student->id,
                'amount' => $request->amount,
                'payment_method' => $request->payment_method,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Payment blocked due to security concerns. Please contact support.'
            ], 403);
        }

        try {
            $paymentData = array_merge($request->all(), [
                'student_id' => $student->id,
                'user_id' => $user->id,
            ]);

            // Add fee items if specified
            if ($request->filled('fee_items')) {
                $feeItems = StudentFeeItem::whereIn('id', $request->fee_items)
                    ->where('student_id', $student->id)
                    ->where('balance', '>', 0)
                    ->get();

                if ($feeItems->isEmpty()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No valid fee items found'
                    ], 400);
                }

                // Verify amount matches total fee balance
                $totalFeeBalance = $feeItems->sum('balance');
                if (abs($request->amount - $totalFeeBalance) > 0.01) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Payment amount does not match selected fee items total'
                    ], 400);
                }

                $paymentData['fee_items'] = $feeItems->pluck('id')->toArray();
                $paymentData['description'] = 'Payment for: ' . $feeItems->pluck('fee.name')->implode(', ');
            }

            // Calculate gateway fees
            $gatewayFees = $this->paymentGatewayService->calculateGatewayFees(
                $request->amount,
                $request->payment_method
            );

            $paymentData['gateway_fees'] = $gatewayFees;
            $paymentData['total_amount'] = $request->amount + $gatewayFees;

            // Initiate payment
            $result = $this->paymentGatewayService->initiatePayment($paymentData);

            // Fire payment initiated event
            if (isset($result['payment_id'])) {
                $payment = Payment::find($result['payment_id']);
                event(new PaymentStatusChanged($payment, 'initiated'));
            }

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Payment initiation failed', [
                'student_id' => $student->id,
                'amount' => $request->amount,
                'payment_method' => $request->payment_method,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Payment initiation failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process payment completion (redirect from gateway)
     */
    public function success(Request $request, $paymentId)
    {
        $payment = Payment::findOrFail($paymentId);
        $user = Auth::user();

        // Verify ownership
        if ($payment->student->user_id !== $user->id && !$user->isAdmin()) {
            abort(403, 'Unauthorized access');
        }

        // Check if payment is already processed
        if ($payment->status === Payment::STATUS_COMPLETED) {
            return redirect()->route('payment.receipt', $paymentId)
                ->with('success', 'Payment already completed successfully!');
        }

        // Process gateway-specific completion
        try {
            $gatewayDetail = $payment->latestGatewayDetail;
            if ($gatewayDetail) {
                $result = $this->paymentGatewayService->processWebhook(
                    $gatewayDetail->gateway,
                    ['payment_id' => $gatewayDetail->gateway_transaction_id]
                );

                if ($result['success'] && $result['status'] === Payment::STATUS_COMPLETED) {
                    event(new PaymentStatusChanged($payment, Payment::STATUS_COMPLETED));
                    return redirect()->route('payment.receipt', $paymentId)
                        ->with('success', 'Payment completed successfully!');
                }
            }
        } catch (\Exception $e) {
            Log::error('Payment processing failed', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);
        }

        return redirect()->route('payment.status', $paymentId)
            ->with('info', 'Payment is being processed. Please wait for confirmation.');
    }

    /**
     * Handle payment cancellation
     */
    public function cancelled(Request $request, $paymentId)
    {
        $payment = Payment::findOrFail($paymentId);
        $user = Auth::user();

        // Verify ownership
        if ($payment->student->user_id !== $user->id && !$user->isAdmin()) {
            abort(403, 'Unauthorized access');
        }

        $payment->update(['status' => Payment::STATUS_CANCELLED]);
        event(new PaymentStatusChanged($payment, Payment::STATUS_CANCELLED));

        return redirect()->route('payment.create')
            ->with('error', 'Payment was cancelled. You can try again.');
    }

    /**
     * Handle payment failure
     */
    public function failed(Request $request, $paymentId)
    {
        $payment = Payment::findOrFail($paymentId);
        $user = Auth::user();

        // Verify ownership
        if ($payment->student->user_id !== $user->id && !$user->isAdmin()) {
            abort(403, 'Unauthorized access');
        }

        $payment->update(['status' => Payment::STATUS_FAILED]);
        event(new PaymentStatusChanged($payment, Payment::STATUS_FAILED));

        return redirect()->route('payment.create')
            ->with('error', 'Payment failed. Please try again or contact support.');
    }

    /**
     * Display payment status page
     */
    public function status(Request $request, $paymentId)
    {
        $payment = Payment::findOrFail($paymentId);
        $user = Auth::user();

        // Verify ownership
        if ($payment->student->user_id !== $user->id && !$user->isAdmin()) {
            abort(403, 'Unauthorized access');
        }

        return Inertia::render('Payment/Status', [
            'payment' => $payment->load(['student', 'latestGatewayDetail']),
        ]);
    }

    /**
     * Display payment receipt
     */
    public function receipt(Request $request, $paymentId)
    {
        $payment = Payment::findOrFail($paymentId);
        $user = Auth::user();

        // Verify ownership
        if ($payment->student->user_id !== $user->id && !$user->isAdmin()) {
            abort(403, 'Unauthorized access');
        }

        if ($payment->status !== Payment::STATUS_COMPLETED) {
            return redirect()->route('payment.status', $paymentId)
                ->with('error', 'Payment receipt is only available for completed payments.');
        }

        return Inertia::render('Payment/Receipt', [
            'payment' => $payment->load([
                'student',
                'student.user',
                'latestGatewayDetail',
                'feeItem.fee',
                'feeItem.feeCategory'
            ]),
        ]);
    }

    /**
     * Download payment receipt PDF
     */
    public function downloadReceipt(Request $request, $paymentId)
    {
        $payment = Payment::findOrFail($paymentId);
        $user = Auth::user();

        // Verify ownership
        if ($payment->student->user_id !== $user->id && !$user->isAdmin()) {
            abort(403, 'Unauthorized access');
        }

        if ($payment->status !== Payment::STATUS_COMPLETED) {
            abort(404, 'Receipt not available for incomplete payments');
        }

        try {
            $pdf = \PDF::loadView('pdfs.payment-receipt', [
                'payment' => $payment->load([
                    'student',
                    'student.user',
                    'latestGatewayDetail',
                    'feeItem.fee',
                    'feeItem.feeCategory'
                ]),
            ]);

            return $pdf->download("payment-receipt-{$payment->reference_number}.pdf");
        } catch (\Exception $e) {
            Log::error('Receipt generation failed', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', 'Failed to generate receipt. Please try again.');
        }
    }

    /**
     * Check payment status (AJAX endpoint)
     */
    public function checkStatus(Request $request, $paymentId)
    {
        $payment = Payment::findOrFail($paymentId);
        $user = Auth::user();

        // Verify ownership
        if ($payment->student->user_id !== $user->id && !$user->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json([
            'status' => $payment->status,
            'paid_at' => $payment->paid_at,
            'gateway_status' => $payment->latestGatewayDetail?->gateway_status,
            'amount' => $payment->amount,
            'reference_number' => $payment->reference_number,
        ]);
    }

    /**
     * Get payment methods with fees (AJAX endpoint)
     */
    public function getPaymentMethods(Request $request)
    {
        $amount = $request->input('amount', 0);
        $paymentMethods = $this->paymentGatewayService->getAvailablePaymentMethods();

        // Calculate fees for each method
        foreach ($paymentMethods as $key => &$method) {
            if ($method['available']) {
                $fees = $this->paymentGatewayService->calculateGatewayFees($amount, $key);
                $method['fees'] = $fees;
                $method['total'] = $amount + $fees;
            }
        }

        return response()->json($paymentMethods);
    }

    /**
     * Display payment history for student
     */
    public function history(Request $request)
    {
        $user = Auth::user();

        if ($user->role !== 'student') {
            abort(403, 'Unauthorized access');
        }

        $student = $user->student;
        if (!$student) {
            abort(404, 'Student record not found');
        }

        $payments = Payment::where('student_id', $student->id)
            ->with(['latestGatewayDetail', 'feeItem.fee'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return Inertia::render('Payment/History', [
            'payments' => $payments,
            'stats' => [
                'total_paid' => Payment::where('student_id', $student->id)
                    ->where('status', Payment::STATUS_COMPLETED)
                    ->sum('amount'),
                'payment_count' => Payment::where('student_id', $student->id)
                    ->where('status', Payment::STATUS_COMPLETED)
                    ->count(),
                'pending_count' => Payment::where('student_id', $student->id)
                    ->where('status', Payment::STATUS_PENDING)
                    ->count(),
            ],
        ]);
    }
}