<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UpdatePaymentStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     *
     * @var int
     */
    public $maxExceptions = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private Payment $payment,
        private string $newStatus,
        private ?string $reason = null
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $oldStatus = $this->payment->status;
            
            // Update payment status
            $this->payment->status = $this->newStatus;
            
            if ($this->newStatus === 'completed') {
                $this->payment->completed_at = now();
            }
            
            $this->payment->save();
            
            // Apply payment to fee items if completed
            if ($this->newStatus === 'completed') {
                $this->applyPaymentToFeeItems($this->payment);
                
                // Check if student should be promoted
                $this->checkAndPromoteStudent($this->payment->student);
            }
            
            Log::info('Payment status updated successfully', [
                'payment_id' => $this->payment->id,
                'old_status' => $oldStatus,
                'new_status' => $this->newStatus,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to update payment status', [
                'payment_id' => $this->payment->id,
                'new_status' => $this->newStatus,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw $e; // Re-throw to fail the job
        }
    }
    
    /**
     * Apply payment to fee items
     */
    private function applyPaymentToFeeItems(Payment $payment): void
    {
        $remainingAmount = $payment->amount;
        
        if (!empty($payment->fee_items)) {
            // Apply to specific fee items
            $feeItems = \App\Models\StudentFeeItem::whereIn('id', $payment->fee_items)->get();
            
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
            $feeItems = \App\Models\StudentFeeItem::where('student_id', $payment->student_id)
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
    }
    
    /**
     * Check if student should be promoted based on payments
     */
    private function checkAndPromoteStudent(Student $student): void
    {
        // Check if all fee items are paid
        $unpaidFees = $student->feeItems()->where('balance', '>', 0)->count();
        
        if ($unpaidFees === 0) {
            // All fees paid - check promotion criteria
            $currentYearLevel = $student->year_level;
            
            // Define promotion logic based on year levels
            $promotionMap = [
                '1st Year' => '2nd Year',
                '2nd Year' => '3rd Year',
                '3rd Year' => '4th Year',
                '4th Year' => 'Graduated',
            ];
            
            if (isset($promotionMap[$currentYearLevel])) {
                $student->update([
                    'year_level' => $promotionMap[$currentYearLevel],
                    'status' => $promotionMap[$currentYearLevel] === 'Graduated' ? 'graduated' : 'enrolled',
                ]);
                
                Log::info('Student promoted', [
                    'student_id' => $student->id,
                    'from' => $currentYearLevel,
                    'to' => $promotionMap[$currentYearLevel],
                ]);
            }
        }
    }
}