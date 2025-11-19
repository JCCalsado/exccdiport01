<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentGatewayDetail extends Model
{
    protected $fillable = [
        'payment_id',
        'gateway',
        'gateway_transaction_id',
        'gateway_response_data',
        'gateway_fee_amount',
        'gateway_status',
        'gateway_processed_at',
    ];

    protected $casts = [
        'gateway_response_data' => 'array',
        'gateway_fee_amount' => 'decimal:2',
        'gateway_processed_at' => 'datetime',
    ];

    /**
     * Get the payment that owns the gateway detail
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Get gateway display name
     */
    public function getGatewayDisplayNameAttribute(): string
    {
        return match ($this->gateway) {
            'gcash' => 'GCash',
            'paypal' => 'PayPal',
            'stripe' => 'Stripe',
            default => ucfirst($this->gateway),
        };
    }

    /**
     * Check if gateway transaction is completed
     */
    public function isCompleted(): bool
    {
        return in_array($this->gateway_status, ['SUCCESS', 'completed', 'succeeded']);
    }

    /**
     * Check if gateway transaction is pending
     */
    public function isPending(): bool
    {
        return in_array($this->gateway_status, ['PENDING', 'pending', 'processing', 'initiated']);
    }

    /**
     * Check if gateway transaction failed
     */
    public function isFailed(): bool
    {
        return in_array($this->gateway_status, ['FAILED', 'failed', 'cancelled', 'expired']);
    }

    /**
     * Get status badge class for UI
     */
    public function getStatusBadgeClassAttribute(): string
    {
        return match (true) {
            $this->isCompleted() => 'bg-green-100 text-green-800',
            $this->isPending() => 'bg-yellow-100 text-yellow-800',
            $this->isFailed() => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }
}