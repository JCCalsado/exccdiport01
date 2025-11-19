<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentFeeItem extends Model
{
    protected $fillable = [
        'student_id',
        'fee_id',
        'school_year',
        'semester',
        'original_amount',
        'amount_paid',
        'balance',
        'status',
        'notes',
        'due_date',
        'assigned_by',
    ];

    protected $casts = [
        'original_amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'balance' => 'decimal:2',
        'due_date' => 'datetime',
    ];

    /**
     * Boot method - auto-calculate balance when saving
     */
    protected static function booted()
    {
        static::saving(function ($item) {
            // Auto-calculate balance
            $item->balance = $item->original_amount - $item->amount_paid;
            
            // Auto-update status based on balance
            if ($item->balance <= 0) {
                $item->status = 'paid';
            } elseif ($item->amount_paid > 0) {
                $item->status = 'partial';
            } else {
                $item->status = 'pending';
            }
        });
    }

    // Relationships
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function fee(): BelongsTo
    {
        return $this->belongsTo(Fee::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'fee_item_id');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopePartial($query)
    {
        return $query->where('status', 'partial');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopeForTerm($query, $schoolYear, $semester)
    {
        return $query->where('school_year', $schoolYear)
                     ->where('semester', $semester);
    }

    public function scopeUnpaid($query)
    {
        return $query->whereIn('status', ['pending', 'partial']);
    }

    // Helper Methods
    public function recordPayment(float $amount): bool
    {
        if ($amount <= 0 || $amount > $this->balance) {
            return false;
        }

        $this->amount_paid += $amount;
        $this->save(); // This will auto-update balance and status

        return true;
    }

    public function getRemainingBalanceAttribute(): float
    {
        return $this->balance;
    }

    public function getPaymentPercentageAttribute(): float
    {
        if ($this->original_amount == 0) {
            return 0;
        }
        return ($this->amount_paid / $this->original_amount) * 100;
    }

    public function isFullyPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isOverdue(): bool
    {
        if (!$this->due_date) {
            return false;
        }
        return now()->isAfter($this->due_date) && !$this->isFullyPaid();
    }
}