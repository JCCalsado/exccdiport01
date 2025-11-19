<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeeCategory extends Model
{
    protected $fillable = ['code', 'name', 'description', 'is_active'];

    public function fees()
    {
        return $this->hasMany(Fee::class);
    }
}