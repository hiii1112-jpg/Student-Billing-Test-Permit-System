<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClassEnrollment extends Model
{
    protected $guarded = [];

    public function classOffering() { return $this->belongsTo(ClassOffering::class); }
    public function student() { return $this->belongsTo(Student::class); }
}