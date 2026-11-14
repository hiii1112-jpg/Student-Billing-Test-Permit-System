<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolTerm extends Model
{
    protected $fillable = ['school_year', 'semester', 'is_current'];
    protected $casts = ['is_current' => 'boolean'];

    public static function current(): ?self
    {
        return static::where('is_current', true)->first();
    }
}