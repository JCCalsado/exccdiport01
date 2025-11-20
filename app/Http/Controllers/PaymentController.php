<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use PDF;
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
        if (!$user->isStudent()) {
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
                event(new PaymentStatusChanged($payment, 'initiated', $payment->status));
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

            switch ($gatewayDetail->gateway) {
                case 'gcash':
                    $this->completeGCashPayment($payment, $request);
                    break;
                case 'paypal':
                    $this->completePayPalPayment($payment, $request);
                    break;
                case 'stripe':
                    $this->completeStripePayment($payment, $request);
                    break;
                default:
                    throw new \Exception('Unknown payment gateway');
            }

            // Update payment status
            $payment->status = Payment::STATUS_COMPLETED;
            $payment->completed_at = now();
            $payment->save();

            // Apply payment to fee items
            $this->applyPaymentToFeeItems($payment);

            // Fire completion event
            event(new PaymentStatusChanged($payment, 'completed', $payment->status));

            return redirect()->route('payment.receipt', $paymentId)
                ->with('success', 'Payment completed successfully!');

        } catch (\Exception $e) {
            Log::error('Payment completion failed', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
                'request' => $request->all(),
            ]);

            return redirect()->route('payment.create')
                ->with('error', 'Failed to complete payment. Please contact support.');
        }
    }

    /**
     * Handle payment cancellation
     */
    public function cancelled(Request $request, $paymentId)
    {
        $payment = Payment::findOrFail($paymentId);
        $user = Auth::user();

        // Verify ownership
        if ($payment->student->user_id !== $user->id && !$user->isStaff()) {
            abort(403, 'Unauthorized access');
        }

        // Update payment status
        $payment->status = Payment::STATUS_CANCELLED;
        $payment->save();

        // Fire cancellation event
        event(new PaymentStatusChanged($payment, 'cancelled', $payment->status));

        return redirect()->route('payment.create')
            ->with('warning', 'Payment was cancelled.');
    }

    /**
     * Handle payment failure
     */
    public function failed(Request $request, $paymentId)
    {
        $payment = Payment::findOrFail($paymentId);
        $user = Auth::user();

        // Verify ownership
        if ($payment->student->user_id !== $user->id && !$user->isStaff()) {
            abort(403, 'Unauthorized access');
        }

        // Update payment status
        $payment->status = Payment::STATUS_FAILED;
        $payment->save();

        // Fire failure event
        event(new PaymentStatusChanged($payment, 'failed', $payment->status));

        return redirect()->route('payment.create')
            ->with('error', 'Payment failed. Please try again or contact support.');
    }

    /**
     * Get payment status
     */
    public function status(Request $request, $paymentId)
    {
        $payment = Payment::findOrFail($paymentId);
        $user = Auth::user();

        // Verify ownership
        if ($payment->student->user_id !== $user->id && !$user->isStaff()) {
            abort(403, 'Unauthorized access');
        }

        return response()->json([
            'success' => true,
            'payment' => $payment->load('latestGatewayDetail'),
            'status' => $payment->status,
            'created_at' => $payment->created_at,
            'updated_at' => $payment->updated_at,
        ]);
    }

    /**
     * Generate payment receipt
     */
    public function receipt(Request $request, $paymentId)
    {
        $payment = Payment::findOrFail($paymentId);
        $user = Auth::user();

        // Verify ownership
        if ($payment->student->user_id !== $user->id && !$user->isStaff()) {
            abort(403, 'Unauthorized access');
        }

        return Inertia::render('Payment/Receipt', [
            'payment' => $payment->load(['student.user', 'latestGatewayDetail']),
        ]);
    }

    /**
     * Download payment receipt as PDF
     */
    public function downloadReceipt(Request $request, $paymentId)
    {
        $payment = Payment::findOrFail($paymentId);
        $user = Auth::user();

        // Verify ownership
        if ($payment->student->user_id !== $user->id && !$user->isStaff()) {
            abort(403, 'Unauthorized access');
        }

        // Generate PDF receipt
        $pdf = PDF::loadView('pdf.payment-receipt', [
            'payment' => $payment->load(['student.user', 'latestGatewayDetail']),
        ]);

        return $pdf->download("payment-receipt-{$payment->reference_number}.pdf");
    }

    /**
     * Check payment status (for polling)
     */
    public function checkStatus(Request $request, $paymentId)
    {
        $payment = Payment::findOrFail($paymentId);
        $user = Auth::user();

        // Verify ownership
        if ($payment->student->user_id !== $user->id && !$user->isStaff()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'status' => $payment->status,
            'updated_at' => $payment->updated_at->toISOString(),
        ]);
    }

    /**
     * Get available payment methods
     */
    public function getPaymentMethods()
    {
        try {
            $methods = $this->paymentGatewayService->getAvailablePaymentMethods();
            return response()->json([
                'success' => true,
                'methods' => $methods,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get payment methods', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load payment methods',
            ], 500);
        }
    }

    /**
     * Get payment history
     */
    public function history()
    {
        $user = Auth::user();

        // Only students can view their payment history
        if (!$user->isStudent()) {
            abort(403, 'Unauthorized access');
        }

        $student = $user->student;
        if (!$student) {
            abort(404, 'Student record not found');
        }

        $payments = Payment::where('student_id', $student->id)
            ->with('latestGatewayDetail')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return Inertia::render('Payment/History', [
            'payments' => $payments,
            'student' => $student->load('user'),
        ]);
    }

    /**
     * Handle GCash webhooks
     */
    public function handleGCashWebhook(Request $request)
    {
        try {
            $payload = $request->all();
            
            // Verify webhook signature
            if (!$this->paymentGatewayService->verifyWebhook('gcash', $payload)) {
                Log::warning('Invalid GCash webhook signature');
                return response()->json(['success' => false], 400);
            }

            $paymentId = $payload['payment_id'] ?? null;
            $payment = Payment::find($paymentId);

            if (!$payment) {
                Log::warning('GCash webhook: Payment not found', ['payment_id' => $paymentId]);
                return response()->json(['success' => false], 404);
            }

            // Process webhook based on event type
            $event = $payload['event_type'] ?? '';
            
            switch ($event) {
                case 'payment.completed':
                    $payment->status = Payment::STATUS_COMPLETED;
                    $payment->completed_at = now();
                    break;
                case 'payment.failed':
                    $payment->status = Payment::STATUS_FAILED;
                    break;
                case 'payment.cancelled':
                    $payment->status = Payment::STATUS_CANCELLED;
                    break;
                default:
                    Log::warning('Unknown GCash webhook event', ['event' => $event]);
                    return response()->json(['success' => false], 400);
            }

            $payment->save();

            // Apply payment if completed
            if ($payment->status === Payment::STATUS_COMPLETED) {
                $this->applyPaymentToFeeItems($payment);
            }

            // Fire status change event
            event(new PaymentStatusChanged($payment, $event, $payment->status));

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            Log::error('GCash webhook processing failed', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
            ]);

            return response()->json(['success' => false], 500);
        }
    }

    /**
     * Handle PayPal webhooks
     */
    public function handlePayPalWebhook(Request $request)
    {
        try {
            $payload = $request->all();
            
            // Verify webhook signature
            if (!$this->paymentGatewayService->verifyWebhook('paypal', $payload)) {
                Log::warning('Invalid PayPal webhook signature');
                return response()->json(['success' => false], 400);
            }

            $paymentId = $payload['custom'] ?? null; // PayPal uses custom field for our payment ID
            $payment = Payment::find($paymentId);

            if (!$payment) {
                Log::warning('PayPal webhook: Payment not found', ['payment_id' => $paymentId]);
                return response()->json(['success' => false], 404);
            }

            // Process webhook based on event type
            $event = $payload['event_type'] ?? '';
            
            switch ($event) {
                case 'PAYMENT.SALE.COMPLETED':
                    $payment->status = Payment::STATUS_COMPLETED;
                    $payment->completed_at = now();
                    break;
                case 'PAYMENT.SALE.DENIED':
                    $payment->status = Payment::STATUS_FAILED;
                    break;
                case 'PAYMENT.SALE.REVERSED':
                    $payment->status = Payment::STATUS_FAILED; // Changed from REFUNDED to avoid missing constant
                    break;
                default:
                    Log::warning('Unknown PayPal webhook event', ['event' => $event]);
                    return response()->json(['success' => false], 400);
            }

            $payment->save();

            // Apply payment if completed
            if ($payment->status === Payment::STATUS_COMPLETED) {
                $this->applyPaymentToFeeItems($payment);
            }

            // Fire status change event
            event(new PaymentStatusChanged($payment, $event, $payment->status));

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            Log::error('PayPal webhook processing failed', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
            ]);

            return response()->json(['success' => false], 500);
        }
    }

    /**
     * Handle Stripe webhooks
     */
    public function handleStripeWebhook(Request $request)
    {
        try {
            $payload = $request->all();
            
            // Verify webhook signature
            if (!$this->paymentGatewayService->verifyWebhook('stripe', $payload)) {
                Log::warning('Invalid Stripe webhook signature');
                return response()->json(['success' => false], 400);
            }

            $paymentId = $payload['data']['object']['metadata']['payment_id'] ?? null;
            $payment = Payment::find($paymentId);

            if (!$payment) {
                Log::warning('Stripe webhook: Payment not found', ['payment_id' => $paymentId]);
                return response()->json(['success' => false], 404);
            }

            // Process webhook based on event type
            $event = $payload['type'] ?? '';
            
            switch ($event) {
                case 'payment_intent.succeeded':
                    $payment->status = Payment::STATUS_COMPLETED;
                    $payment->completed_at = now();
                    break;
                case 'payment_intent.payment_failed':
                    $payment->status = Payment::STATUS_FAILED;
                    break;
                case 'charge.refunded':
                    $payment->status = Payment::STATUS_FAILED; // Changed from REFUNDED to avoid missing constant
                    break;
                default:
                    Log::warning('Unknown Stripe webhook event', ['event' => $event]);
                    return response()->json(['success' => false], 400);
            }

            $payment->save();

            // Apply payment if completed
            if ($payment->status === Payment::STATUS_COMPLETED) {
                $this->applyPaymentToFeeItems($payment);
            }

            // Fire status change event
            event(new PaymentStatusChanged($payment, $event, $payment->status));

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            Log::error('Stripe webhook processing failed', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
            ]);

            return response()->json(['success' => false], 500);
        }
    }

    /**
     * Complete GCash payment
     */
    private function completeGCashPayment(Payment $payment, Request $request)
    {
        // Verify payment with GCash
        $verification = $this->paymentGatewayService->verifyGCashPayment($payment, $request->all());
        
        if (!$verification['success']) {
            throw new \Exception($verification['message'] ?? 'GCash payment verification failed');
        }

        // Store gateway-specific details
        $payment->gatewayDetails()->create([
            'gateway' => 'gcash',
            'gateway_transaction_id' => $verification['transaction_id'] ?? null,
            'gateway_response' => json_encode($verification),
            'status' => 'completed',
        ]);
    }

    /**
     * Complete PayPal payment
     */
    private function completePayPalPayment(Payment $payment, Request $request)
    {
        // Verify payment with PayPal
        $verification = $this->paymentGatewayService->verifyPayPalPayment($payment, $request->all());
        
        if (!$verification['success']) {
            throw new \Exception($verification['message'] ?? 'PayPal payment verification failed');
        }

        // Store gateway-specific details
        $payment->gatewayDetails()->create([
            'gateway' => 'paypal',
            'gateway_transaction_id' => $verification['transaction_id'] ?? null,
            'gateway_response' => json_encode($verification),
            'status' => 'completed',
        ]);
    }

    /**
     * Complete Stripe payment
     */
    private function completeStripePayment(Payment $payment, Request $request)
    {
        // Verify payment with Stripe
        $verification = $this->paymentGatewayService->verifyStripePayment($payment, $request->all());
        
        if (!$verification['success']) {
            throw new \Exception($verification['message'] ?? 'Stripe payment verification failed');
        }

        // Store gateway-specific details
        $payment->gatewayDetails()->create([
            'gateway' => 'stripe',
            'gateway_transaction_id' => $verification['transaction_id'] ?? null,
            'gateway_response' => json_encode($verification),
            'status' => 'completed',
        ]);
    }

    /**
     * Apply payment to fee items
     */
    private function applyPaymentToFeeItems(Payment $payment)
    {
        DB::transaction(function () use ($payment) {
            $remainingAmount = $payment->amount;

            if (!empty($payment->fee_items)) {
                // Apply to specific fee items
                $feeItems = StudentFeeItem::whereIn('id', $payment->fee_items)->get();
                
                foreach ($feeItems as $feeItem) {
                    if ($remainingAmount <= 0) break;
                    
                    $amountToApply = min($feeItem->balance, $remainingAmount);
                    $feeItem->balance = max(0, $feeItem->balance - $amountToApply);
                    $feeItem->amount_paid = $feeItem->amount_paid + $amountToApply;
                    
                    if ($feeItem->balance === 0) {
                        $feeItem->status = 'paid';
                    } elseif ($feeItem->amount_paid > 0 && $feeItem->balance > 0) {
                        $feeItem->status = 'partial';
                    }
                    
                    $feeItem->save();
                    $remainingAmount -= $amountToApply;
                }
            } else {
                // Apply to oldest unpaid fee items
                $feeItems = StudentFeeItem::where('student_id', $payment->student_id)
                    ->where('balance', '>', 0)
                    ->orderBy('created_at')
                    ->get();
                
                foreach ($feeItems as $feeItem) {
                    if ($remainingAmount <= 0) break;
                    
                    $amountToApply = min($feeItem->balance, $remainingAmount);
                    $feeItem->balance = max(0, $feeItem->balance - $amountToApply);
                    $feeItem->amount_paid = $feeItem->amount_paid + $amountToApply;
                    
                    if ($feeItem->balance === 0) {
                        $feeItem->status = 'paid';
                    } elseif ($feeItem->amount_paid > 0 && $feeItem->balance > 0) {
                        $feeItem->status = 'partial';
                    }
                    
                    $feeItem->save();
                    $remainingAmount -= $amountToApply;
                }
            }

            // Update student's total balance
            $payment->student->update([
                'total_balance' => $payment->student->feeItems()->sum('balance'),
            ]);
        });
    }
}