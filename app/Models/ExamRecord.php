<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamRecord extends Model
{
    protected $guarded = [];
    protected $casts = ['marked_at' => 'datetime'];

    public function markedBy() { return $this->belongsTo(User::class, 'marked_by'); }
}