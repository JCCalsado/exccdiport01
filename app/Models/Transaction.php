<?php
// app/Models/Transaction.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Services\AccountService;

/**
 * Transaction Model
 * 
 * Purpose: Records all financial movements (charges and payments)
 * Used for: Accounting, ledger, student balance calculation
 * 
 * Relationship with Payment:
 * - When a Payment is completed, a Transaction is created
 * - Transaction.reference should match Payment.reference_number
 */
class Transaction extends Model
{
    protected $fillable = [
        'user_id',
        'account_id',
        'fee_id',
        'payment_id',      // Links to Payment model if this is a payment transaction
        'reference',
        'payment_channel',
        'kind',            // 'charge' or 'payment'
        'type',            // Category: 'Tuition', 'Laboratory', etc.
        'year',
        'semester',
        'amount',
        'status',
        'paid_at',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'paid_at' => 'datetime',
        'amount' => 'decimal:2',
    ];

    // =================== RELATIONSHIPS ===================
    
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function fee(): BelongsTo
    {
        return $this->belongsTo(Fee::class);
    }

    /**
     * Link to the actual payment record (for payment transactions)
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    // =================== OBSERVERS ===================
    
    protected static function booted()
    {
        // Auto-recalculate account balance when transaction changes
        static::saved(function ($transaction) {
            AccountService::recalculate($transaction->user);
        });

        static::deleted(function ($transaction) {
            AccountService::recalculate($transaction->user);
        });
    }

    // =================== ACCESSORS ===================
    
    public function getKindLabelAttribute(): string
    {
        return $this->kind === 'charge' ? 'Charge' : 'Payment';
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match($this->status) {
            'paid' => 'bg-green-100 text-green-800',
            'pending' => 'bg-yellow-100 text-yellow-800',
            'failed' => 'bg-red-100 text-red-800',
            'cancelled' => 'bg-gray-100 text-gray-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    public function getFormattedAmountAttribute(): string
    {
        $sign = $this->kind === 'charge' ? '+' : '-';
        return $sign . number_format($this->amount, 2);
    }

    // =================== SCOPES ===================
    
    public function scopeByTerm($query, string $year, string $semester)
    {
        return $query->where('year', $year)->where('semester', $semester);
    }

    public function scopeCharges($query)
    {
        return $query->where('kind', 'charge');
    }

    public function scopePayments($query)
    {
        return $query->where('kind', 'payment');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    // =================== HELPERS ===================
    
    public function isCharge(): bool
    {
        return $this->kind === 'charge';
    }

    public function isPayment(): bool
    {
        return $this->kind === 'payment';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Create a transaction from a completed payment
     */
    public static function createFromPayment(Payment $payment): self
    {
        return self::create([
            'user_id' => $payment->student->user_id,
            'payment_id' => $payment->id,
            'reference' => $payment->reference_number,
            'payment_channel' => $payment->payment_method,
            'kind' => 'payment',
            'type' => 'Payment',
            'amount' => $payment->amount,
            'status' => 'paid',
            'paid_at' => $payment->paid_at ?? now(),
            'meta' => [
                'payment_id' => $payment->id,
                'gateway' => $payment->latestGatewayDetail?->gateway,
                'description' => $payment->description,
            ],
        ]);
    }
}