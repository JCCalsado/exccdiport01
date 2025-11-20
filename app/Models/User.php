<?php
// app/Models/User.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Enums\UserRoleEnum;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    // Status constants
    const STATUS_ACTIVE = 'active';
    const STATUS_GRADUATED = 'graduated';
    const STATUS_DROPPED = 'dropped';

    protected $fillable = [
        'last_name',
        'first_name',
        'middle_initial',
        'email',
        'password',
        'birthday',
        'address',
        'phone',
        'student_id',      // Keep for quick lookup
        'profile_picture',
        'faculty',         // For staff only
        'status',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = ['name', 'full_name'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRoleEnum::class,
            'birthday' => 'date',
        ];
    }

    // =================== RELATIONSHIPS ===================
    
    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    public function account(): HasOne
    {
        return $this->hasOne(Account::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    // =================== ACCESSORS ===================
    
    /**
     * Get full name (Last, First MI.)
     */
    public function getNameAttribute(): string
    {
        $mi = $this->middle_initial ? ' ' . strtoupper($this->middle_initial) . '.' : '';
        return "{$this->last_name}, {$this->first_name}{$mi}";
    }

    /**
     * Get full name alternative format (First Last)
     */
    public function getFullNameAttribute(): string
    {
        $mi = $this->middle_initial ? "{$this->middle_initial}. " : '';
        return "{$this->first_name} {$mi}{$this->last_name}";
    }

    /**
     * Dynamic accessor for course (from student relationship)
     */
    public function getCourseAttribute(): ?string
    {
        return $this->student?->course;
    }

    /**
     * Dynamic accessor for year_level (from student relationship)
     */
    public function getYearLevelAttribute(): ?string
    {
        return $this->student?->year_level;
    }

    // =================== ROLE CHECKERS ===================
    
    public function isAdmin(): bool
    {
        return $this->role === UserRoleEnum::ADMIN;
    }

    public function isAccounting(): bool
    {
        return $this->role === UserRoleEnum::ACCOUNTING;
    }

    public function isStudent(): bool
    {
        return $this->role === UserRoleEnum::STUDENT;
    }

    public function isStaff(): bool
    {
        return $this->isAdmin() || $this->isAccounting();
    }

    /**
     * Get role label
     */
    public function getRoleLabelAttribute(): string
    {
        return $this->role->label() ?? 'Unknown';
    }

    // =================== SCOPES ===================
    
    public function scopeStudents($query)
    {
        return $query->where('role', UserRoleEnum::STUDENT);
    }

    public function scopeStaff($query)
    {
        return $query->whereIn('role', [UserRoleEnum::ADMIN, UserRoleEnum::ACCOUNTING]);
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }
}