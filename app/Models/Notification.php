<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'message',
        'start_date',
        'end_date',
        'target_role', // student | accounting | admin | all
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
    ];
}