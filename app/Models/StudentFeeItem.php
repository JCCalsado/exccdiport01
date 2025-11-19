<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class StudentFeeItem extends Model
{
    protected $fillable = [
        'student_id', 'fee_id', 'original_amount', 'amount_paid', 'balance', 'status', 'due_date', 'reference'
    ];

    protected $casts = [
        'original_amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'balance' => 'decimal:2',
        'due_date' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function fee()
    {
        return $this->belongsTo(Fee::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'student_fee_item_id');
    }

    /**
     * Apply payment to this fee item.
     * Returns numeric amount actually applied.
     */
    public function applyPayment(float $amount): float
    {
        // use DB transaction for safety
        return DB::transaction(function () use ($amount) {
            $this->refresh(); // ensure fresh values
            $remaining = (float) $this->balance;

            $applied = min($amount, $remaining);
            $this->amount_paid = (float) $this->amount_paid + $applied;
            $this->balance = (float) $this->original_amount - (float) $this->amount_paid;

            if ($this->balance <= 0) {
                $this->balance = 0;
                $this->status = 'paid';
            } elseif ($this->amount_paid > 0) {
                $this->status = 'partial';
            }

            $this->save();

            return $applied;
        });
    }
}