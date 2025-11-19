<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Student extends Model
{
    protected $fillable = [
        'user_id', 'student_id', 'last_name', 'first_name', 'middle_initial',
        'email', 'course', 'year_level', 'birthday',
        'phone', 'address', 'total_balance', 'status',
    ];

    protected $casts = [
        'birthday' => 'date',
        'total_balance' => 'decimal:2',
    ];

    // Status constants
    const STATUS_ENROLLED = 'enrolled';
    const STATUS_GRADUATED = 'graduated';
    const STATUS_INACTIVE = 'inactive';

    /**
     * Get the user that owns the student record
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all payments for this student
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function feeItems()
    {
        return $this->hasMany(StudentFeeItem::class);
    }

    /**
     * Get all transactions for this student through the user relationship
     * FIXED: Proper relationship definition
     */
    public function transactions(): HasManyThrough
    {
        return $this->hasManyThrough(
            Transaction::class,
            User::class,
            'id',         // Foreign key on users table
            'user_id',    // Foreign key on transactions table
            'user_id',    // Local key on students table
            'id'          // Local key on users table
        );
    }

    /**
     * Get the account associated with this student
     */
    public function account(): HasOne
    {
        return $this->hasOne(Account::class, 'user_id', 'user_id');
    }

    /**
     * Calculate remaining balance (total_balance - total paid)
     */
    public function getRemainingBalanceAttribute(): float
    {
        $totalPaid = $this->payments()
            ->where('status', Payment::STATUS_COMPLETED)
            ->sum('amount');
        
        return max(0, $this->total_balance - $totalPaid);
    }

    public function recalculateBalance(): float
    {
        $balance = $this->feeItems()->sum('balance');
        $this->total_balance = $balance;
        $this->save();

        return (float) $balance;
    }

    /**
     * Get full name attribute
     */
    public function getFullNameAttribute(): string
    {
        $mi = $this->middle_initial ? ' ' . strtoupper($this->middle_initial) . '.' : '';
        return "{$this->last_name}, {$this->first_name}{$mi}";
    }

    /**
     * Scope: Active students only
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ENROLLED);
    }

    /**
     * Scope: By course
     */
    public function scopeByCourse($query, string $course)
    {
        return $query->where('course', $course);
    }

    /**
     * Scope: By year level
     */
    public function scopeByYearLevel($query, string $yearLevel)
    {
        return $query->where('year_level', $yearLevel);
    }

    /**
     * Check if student has outstanding balance
     */
    public function hasOutstandingBalance(): bool
    {
        return $this->remaining_balance > 0;
    }

    /**
     * Get available payment methods
     */
    public static function getPaymentMethods(): array
    {
        return [
            'cash' => 'Cash',
            'gcash' => 'GCash',
            'bank_transfer' => 'Bank Transfer',
            'credit_card' => 'Credit Card',
            'debit_card' => 'Debit Card',
        ];
    }
}