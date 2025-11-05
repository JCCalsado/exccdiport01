<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentAssessment extends Model
{
    protected $fillable = [
        'user_id',
        'assessment_number',
        'year_level',
        'semester',
        'school_year',
        'tuition_fee',
        'other_fees',
        'total_assessment',
        'subjects',
        'fee_breakdown',
        'status',
        'created_by',
    ];

    protected $casts = [
        'tuition_fee' => 'decimal:2',
        'other_fees' => 'decimal:2',
        'total_assessment' => 'decimal:2',
        'subjects' => 'array',
        'fee_breakdown' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'user_id', 'user_id');
    }

    // Generate unique assessment number
    public static function generateAssessmentNumber(): string
    {
        $year = now()->year;
        $lastAssessment = self::where('assessment_number', 'like', "ASS-{$year}-%")
            ->orderBy('id', 'desc')
            ->first();

        if ($lastAssessment) {
            $lastNumber = intval(substr($lastAssessment->assessment_number, -4));
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }

        return "ASS-{$year}-{$newNumber}";
    }

    // Calculate total from breakdown
    public function calculateTotal(): void
    {
        $this->total_assessment = $this->tuition_fee + $this->other_fees;
        $this->save();
    }
}