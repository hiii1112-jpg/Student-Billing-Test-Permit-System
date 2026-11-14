<?php

namespace App\Services;

use App\Models\SchoolTerm;
use App\Models\Student;
use App\Models\StudentTerm;
use Illuminate\Support\Facades\DB;

class BillingService
{
    public function openTerm(Student $student, SchoolTerm $term, int $yearLevel, float $tuition = 10000.00): StudentTerm
    {
        return DB::transaction(function () use ($student, $term, $yearLevel, $tuition) {
            $studentTerm = StudentTerm::firstOrCreate(
                ['student_id' => $student->id, 'school_term_id' => $term->id],
                ['year_level' => $yearLevel, 'tuition_total' => $tuition, 'unlocked_at' => now()]
            );

            if ($studentTerm->billingPeriods()->doesntExist()) {
                $labels = [1 => 'Prelim', 2 => 'Midterm', 3 => 'Pre-Final', 4 => 'Final'];
                $share = floor($tuition / 4 * 100) / 100;

                foreach ($labels as $no => $label) {
                    $studentTerm->billingPeriods()->create([
                        'period_no'   => $no,
                        'label'       => $label,
                        'amount_due'  => $no === 4 ? round($tuition - ($share * 3), 2) : $share,
                        'amount_paid' => 0,
                    ]);
                }
            }

            return $studentTerm;
        });
    }

    /** Retake / extra charge: added to the last period. */
    public function addCharge(StudentTerm $studentTerm, float $fee): void
    {
        DB::transaction(function () use ($studentTerm, $fee) {
            $last = $studentTerm->billingPeriods()->where('period_no', 4)->lockForUpdate()->firstOrFail();
            $last->amount_due = round((float) $last->amount_due + $fee, 2);
            $last->save();

            $studentTerm->tuition_total = round((float) $studentTerm->tuition_total + $fee, 2);
            $studentTerm->save();
        });
    }
}