<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Services\AccountService;

class Payment extends Model
{
    const STATUS_COMPLETED = 'completed';
    const STATUS_PENDING = 'pending';
    const STATUS_FAILED = 'failed';

    protected $fillable = [
        'student_id', 'amount', 'description', 
        'payment_method', 'reference_number', 'status', 'paid_at'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    
    protected static function booted()
    {
        static::saved(function ($payment) {
            // make sure AccountService exists and namespace is correct
            AccountService::recalculate($payment->user);
        });
    }
}