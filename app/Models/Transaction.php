<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Services\AccountService;

class Transaction extends Model
{
    protected $fillable = [
        'user_id', 'account_id', 'fee_id', 'reference', 
        'payment_channel', 'kind', 'type', 'amount', 'status', 
        'paid_at', 'meta'
    ];

    protected $casts = [
        'meta' => 'array',
        'paid_at' => 'datetime',
        'amount' => 'decimal:2',
    ];

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

    protected static function booted()
    {
        static::saved(function ($transaction) {
            AccountService::recalculate($transaction->user);
        });
    }

    public function download()
    {
        $transactions = \App\Models\Transaction::with('fee')->get();

        // Use a PDF generator like DomPDF
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.transactions', [
            'transactions' => $transactions
        ]);

        return $pdf->download('transactions.pdf');
    }

    /**
     * Get transaction type label
     */
    public function getKindLabelAttribute(): string
    {
        return $this->kind === 'charge' ? 'Charge' : 'Payment';
    }

    /**
     * Get status badge class
     */
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

    /**
     * Get formatted amount with sign
     */
    public function getFormattedAmountAttribute(): string
    {
        $sign = $this->kind === 'charge' ? '+' : '-';
        return $sign . number_format($this->amount, 2);
    }

    /**
     * Scope: By term (year and semester)
     */
    public function scopeByTerm($query, string $year, string $semester)
    {
        return $query->where('year', $year)
                    ->where('semester', $semester);
    }

    /**
     * Scope: Charges only
     */
    public function scopeCharges($query)
    {
        return $query->where('kind', 'charge');
    }

    /**
     * Scope: Payments only
     */
    public function scopePayments($query)
    {
        return $query->where('kind', 'payment');
    }

    /**
     * Scope: Pending only
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Check if transaction is a charge
     */
    public function isCharge(): bool
    {
        return $this->kind === 'charge';
    }

    /**
     * Check if transaction is a payment
     */
    public function isPayment(): bool
    {
        return $this->kind === 'payment';
    }

    /**
     * Check if transaction is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}