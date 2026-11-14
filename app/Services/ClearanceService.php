<?php

namespace App\Services;

use App\Models\PromissoryNote;
use App\Models\StudentTerm;

class ClearanceService
{
    public const GREEN  = 'green';
    public const YELLOW = 'yellow';
    public const RED    = 'red';

    public function statusFor(StudentTerm $studentTerm, int $periodNo): string
    {
        $hasUnpaid = $studentTerm->billingPeriods()
            ->where('period_no', '<=', $periodNo)
            ->whereColumn('amount_paid', '<', 'amount_due')
            ->exists();

        if (! $hasUnpaid) {
            return self::GREEN;
        }

        $period = $studentTerm->billingPeriods()->where('period_no', $periodNo)->first();

        $hasPromissory = $period && PromissoryNote::query()
            ->where('student_term_id', $studentTerm->id)
            ->where('billing_period_id', $period->id)
            ->where('status', 'active')
            ->whereDate('due_date', '>=', today())
            ->exists();

        return $hasPromissory ? self::YELLOW : self::RED;
    }

    public function canTakeExam(StudentTerm $studentTerm, int $periodNo): bool
    {
        return $this->statusFor($studentTerm, $periodNo) !== self::RED;
    }
}