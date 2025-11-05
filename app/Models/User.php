<?php

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
        'student_id',
        'profile_picture',
        'course',
        'year_level',
        'faculty', // Added for accounting/admin users
        'status',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRoleEnum::class,
            'birthday' => 'date',
        ];
    }

    // Relationships
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

    // Derived full name accessor
    public function getFullNameAttribute(): string
    {
        $mi = $this->middle_initial ? "{$this->middle_initial}." : '';
        return "{$this->last_name}, {$this->first_name} {$mi}";
    }

    public static function getValidationRules($userId = null)
    {
        return [
            'student_id' => 'nullable|string|unique:users,student_id,' . $userId,
            'address' => 'nullable|string|max:255',
            'course' => 'nullable|string|max:100',
            'year_level' => 'nullable|string|max:50',
            'faculty' => 'nullable|string|max:100',
            'status' => 'required|in:active,graduated,dropped',
            'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ];
    }

    public function getNameAttribute(): string
    {
        $mi = $this->middle_initial ? ' ' . strtoupper($this->middle_initial) . '.' : '';
        return "{$this->last_name}, {$this->first_name}{$mi}";
    }

    protected $appends = ['name'];
}