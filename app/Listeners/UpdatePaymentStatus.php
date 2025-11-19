<?php

namespace App\Listeners;

use App\Events\PaymentStatusChanged;
use App\Services\AccountService;
use Illuminate\Support\Facades\Log;
use App\Models\StudentFeeItem;
use App\Models\Transaction;

class UpdatePaymentStatus
{
    /**
     * Handle the event.
     */
    public function handle(PaymentStatusChanged $event): void
    {
        Log::info('Processing payment status change', [
            'payment_id' => $event->payment->id,
            'old_status' => $event->oldStatus,
            'new_status' => $event->newStatus,
        ]);

        $payment = $event->payment;
        $student = $payment->student;

        if (!$student) {
            Log::error('Payment status change: Student not found', [
                'payment_id' => $payment->id,
                'student_id' => $payment->student_id,
            ]);
            return;
        }

        // Handle different status changes
        switch ($event->newStatus) {
            case 'completed':
                $this->handlePaymentCompleted($payment, $student);
                break;

            case 'failed':
                $this->handlePaymentFailed($payment, $student);
                break;

            case 'cancelled':
                $this->handlePaymentCancelled($payment, $student);
                break;

            case 'pending':
                $this->handlePaymentPending($payment, $student);
                break;
        }

        // Update student's overall balance
        $this->updateStudentBalance($student);

        Log::info('Payment status change processed successfully', [
            'payment_id' => $payment->id,
            'new_status' => $event->newStatus,
        ]);
    }

    /**
     * Handle payment completion
     */
    private function handlePaymentCompleted($payment, $student): void
    {
        // Set payment completion timestamp if not already set
        if (!$payment->paid_at) {
            $payment->update(['paid_at' => now()]);
        }

        // Generate receipt number if not exists
        if (!$payment->receipt_number) {
            $payment->update(['receipt_number' => $this->generateReceiptNumber()]);
        }

        // Update fee item balance if payment is linked to a fee item
        if ($payment->feeItem) {
            $feeItem = $payment->feeItem;

            // Recalculate total paid for this fee item
            $totalPaid = $payment->where('fee_item_id', $feeItem->id)
                ->where('status', 'completed')
                ->sum('amount');

            // Update fee item paid amount
            $feeItem->update([
                'amount_paid' => $totalPaid,
                'balance' => max(0, $feeItem->amount - $totalPaid),
            ]);

            // Update fee item status if fully paid
            if ($feeItem->balance <= 0) {
                $feeItem->update(['status' => 'paid']);
            }
        }

        // Create transaction record for completed payment
        $this->createPaymentTransaction($payment, $student);

        // Check for automatic student promotion
        $this->checkStudentPromotion($student);
    }

    /**
     * Handle payment failure
     */
    private function handlePaymentFailed($payment, $student): void
    {
        // Log payment failure details
        Log::warning('Payment failed', [
            'payment_id' => $payment->id,
            'student_id' => $student->id,
            'amount' => $payment->amount,
            'payment_method' => $payment->payment_method,
            'reason' => $payment->metadata['failure_reason'] ?? 'Unknown',
        ]);

        // Update fee item status if applicable
        if ($payment->feeItem) {
            $feeItem = $payment->feeItem;

            // Check if all payments for this fee item have failed
            $hasActivePayments = $feeItem->payments()
                ->whereIn('status', ['pending', 'completed'])
                ->exists();

            if (!$hasActivePayments) {
                $feeItem->update(['status' => 'unpaid']);
            }
        }
    }

    /**
     * Handle payment cancellation
     */
    private function handlePaymentCancelled($payment, $student): void
    {
        // Log payment cancellation
        Log::info('Payment cancelled', [
            'payment_id' => $payment->id,
            'student_id' => $student->id,
            'amount' => $payment->amount,
            'payment_method' => $payment->payment_method,
        ]);

        // Similar to failed payment, update fee item status if needed
        if ($payment->feeItem) {
            $feeItem = $payment->feeItem;

            $hasActivePayments = $feeItem->payments()
                ->whereIn('status', ['pending', 'completed'])
                ->exists();

            if (!$hasActivePayments) {
                $feeItem->update(['status' => 'unpaid']);
            }
        }
    }

    /**
     * Handle payment pending status
     */
    private function handlePaymentPending($payment, $student): void
    {
        // Update fee item status to processing if there's a pending payment
        if ($payment->feeItem) {
            $feeItem = $payment->feeItem;

            if ($feeItem->status === 'unpaid') {
                $feeItem->update(['status' => 'processing']);
            }
        }
    }

    /**
     * Create transaction record for payment
     */
    private function createPaymentTransaction($payment, $student): void
    {
        // Check if transaction already exists for this payment
        $existingTransaction = Transaction::where('payment_id', $payment->id)->first();

        if ($existingTransaction) {
            Log::warning('Transaction already exists for payment', [
                'payment_id' => $payment->id,
                'transaction_id' => $existingTransaction->id,
            ]);
            return;
        }

        // Create payment transaction
        Transaction::create([
            'student_id' => $student->id,
            'payment_id' => $payment->id,
            'type' => 'payment',
            'description' => $payment->description,
            'amount' => $payment->amount,
            'balance' => 0, // Payments reduce balance to 0
            'status' => 'completed',
            'transaction_date' => $payment->paid_at ?? now(),
            'meta' => [
                'payment_reference' => $payment->reference_number,
                'payment_method' => $payment->payment_method,
                'gateway' => $payment->latestGatewayDetail?->gateway,
                'gateway_transaction_id' => $payment->latestGatewayDetail?->gateway_transaction_id,
            ],
        ]);

        Log::info('Payment transaction created', [
            'payment_id' => $payment->id,
            'amount' => $payment->amount,
        ]);
    }

    /**
     * Update student's overall account balance
     */
    private function updateStudentBalance($student): void
    {
        try {
            AccountService::recalculate($student->user);
        } catch (\Exception $e) {
            Log::error('Failed to recalculate student balance', [
                'student_id' => $student->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Check if student is eligible for promotion
     */
    private function checkStudentPromotion($student): void
    {
        try {
            AccountService::checkAndPromoteStudent($student);
        } catch (\Exception $e) {
            Log::error('Failed to check student promotion', [
                'student_id' => $student->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Generate unique receipt number
     */
    private function generateReceiptNumber(): string
    {
        do {
            $receiptNumber = 'RCP' . date('Ymd') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        } while (\App\Models\Payment::where('receipt_number', $receiptNumber)->exists());

        return $receiptNumber;
    }
}