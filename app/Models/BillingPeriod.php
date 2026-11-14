<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingPeriod extends Model
{
    protected $fillable = ['student_term_id', 'period_no', 'label', 'amount_due', 'amount_paid', 'due_date'];
    protected $casts = ['amount_due' => 'decimal:2', 'amount_paid' => 'decimal:2', 'due_date' => 'date'];

    public function studentTerm() { return $this->belongsTo(StudentTerm::class); }

    public function getBalanceAttribute(): float
    {
        return round((float) $this->amount_due - (float) $this->amount_paid, 2);
    }
}