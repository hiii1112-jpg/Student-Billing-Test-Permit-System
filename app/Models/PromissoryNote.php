<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromissoryNote extends Model
{
    protected $fillable = ['student_term_id', 'billing_period_id', 'due_date', 'status', 'approved_by', 'notes'];
    protected $casts = ['due_date' => 'date'];

    public function studentTerm() { return $this->belongsTo(StudentTerm::class); }
    public function billingPeriod() { return $this->belongsTo(BillingPeriod::class); }
}