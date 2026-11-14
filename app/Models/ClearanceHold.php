<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClearanceHold extends Model
{
    protected $guarded = [];
    protected $casts = ['released_at' => 'datetime'];
}