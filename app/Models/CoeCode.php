<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoeCode extends Model
{
    protected $guarded = [];
    protected $casts = ['used_at' => 'datetime'];

    public function schoolTerm() { return $this->belongsTo(SchoolTerm::class); }
}