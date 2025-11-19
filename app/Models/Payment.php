<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Services\AccountService;

class Payment extends Model
{
    // Status constants
    const STATUS_COMPLETED = 'completed';
    const STATUS_PENDING = 'pending';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';

    // Payment method constants
    const METHOD_CASH = 'cash';
    const METHOD_GCASH = 'gcash';
    const METHOD_BANK_TRANSFER = 'bank_transfer';
    const METHOD_CREDIT_CARD = 'credit_card';
    const METHOD_DEBIT_CARD = 'debit_card';

    protected $fillable = [
        'description', 'payment_method', 'reference_number', 
        'student_id','fee_id', 'fee_item_id', 'student_fee_item_id','amount','method','reference','status','paid_at','receipt_number','meta'
    ];

    protected $casts = [
        'meta' => 'array',
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    /**
     * Get the student that owns the payment
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function fee()
    {
        return $this->belongsTo(Fee::class);
    }

    public function studentFeeItem()
    {
        return $this->belongsTo(StudentFeeItem::class, 'student_fee_item_id');
    }

    public function feeItem(): BelongsTo
    {
        return $this->belongsTo(StudentFeeItem::class);
    }

    /**
     * Boot method to handle model events
     */
    protected static function booted()
    {
        static::saved(function ($payment) {
            if ($payment->fee_item_id && $payment->status === Payment::STATUS_COMPLETED) {
                $feeItem = $payment->feeItem;
                if ($feeItem) {
                    // Recalculate total paid for this fee item
                    $totalPaid = Payment::where('fee_item_id', $payment->fee_item_id)
                        ->where('status', Payment::STATUS_COMPLETED)
                        ->sum('amount');
                    
                    $feeItem->amount_paid = $totalPaid;
                    $feeItem->save(); // This auto-updates balance and status
                }
            }

            // Also recalculate student's overall balance
            if ($payment->student && $payment->student->user) {
                AccountService::recalculate($payment->student->user);
            }
        });
    }

    /**
     * Get all available payment methods
     */
    public static function getMethods(): array
    {
        return [
            self::METHOD_CASH => 'Cash',
            self::METHOD_GCASH => 'GCash',
            self::METHOD_BANK_TRANSFER => 'Bank Transfer',
            self::METHOD_CREDIT_CARD => 'Credit Card',
            self::METHOD_DEBIT_CARD => 'Debit Card',
        ];
    }

    /**
     * Get all available statuses
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_PENDING => 'Pending',
            self::STATUS_FAILED => 'Failed',
            self::STATUS_CANCELLED => 'Cancelled',
        ];
    }

    /**
     * Get payment method label
     */
    public function getPaymentMethodLabelAttribute(): string
    {
        return self::getMethods()[$this->payment_method] ?? $this->payment_method;
    }

    /**
     * Get status label
     */
    public function getStatusLabelAttribute(): string
    {
        return self::getStatuses()[$this->status] ?? $this->status;
    }

    /**
     * Scope: Completed payments only
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope: Pending payments
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope: By date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('paid_at', [$startDate, $endDate]);
    }

    /**
     * Check if payment is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Mark payment as completed
     */
    public function markAsCompleted(): bool
    {
        return $this->update([
            'status' => self::STATUS_COMPLETED,
            'paid_at' => now(),
        ]);
    }
}