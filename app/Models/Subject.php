<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
    protected $fillable = [
        'code',
        'name',
        'units',
        'price_per_unit',
        'year_level',
        'semester',
        'course',
        'description',
        'has_lab',
        'lab_fee',
        'is_active',
    ];

    protected $casts = [
        'units' => 'integer',
        'price_per_unit' => 'decimal:2',
        'lab_fee' => 'decimal:2',
        'has_lab' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class);
    }

    // Calculate total cost for this subject
    public function getTotalCostAttribute()
    {
        $tuition = $this->units * $this->price_per_unit;
        $lab = $this->has_lab ? $this->lab_fee : 0;
        return $tuition + $lab;
    }

    // Scope for active subjects
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Scope for specific term and course
    public function scopeForTerm($query, $yearLevel, $semester, $course)
    {
        return $query->where('year_level', $yearLevel)
                     ->where('semester', $semester)
                     ->where('course', $course);
    }
}