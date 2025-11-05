<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentEnrollment extends Model
{
    protected $fillable = [
        'user_id',
        'subject_id',
        'school_year',
        'semester',
        'status',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    // Calculate cost for this enrollment
    public function getCostAttribute()
    {
        return $this->subject->total_cost ?? 0;
    }
}