<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClassOffering extends Model
{
    protected $guarded = [];
    protected $casts = ['claimed_at' => 'datetime'];

    public function subject() { return $this->belongsTo(Subject::class); }
    public function block() { return $this->belongsTo(Block::class); }
    public function teacher() { return $this->belongsTo(User::class, 'teacher_id'); }
    public function schoolTerm() { return $this->belongsTo(SchoolTerm::class); }
    public function enrollments() { return $this->hasMany(ClassEnrollment::class); }
}