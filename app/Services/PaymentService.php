<?php

namespace App\Services;

use App\Models\BillingPeriod;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\StudentTerm;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function submitOnline(
        StudentTerm $studentTerm, float $amount, string $channel,
        string $reference, ?string $proofPath = null, ?int $periodNo = null
    ): Payment {
        $this->assertWithinBalance($studentTerm, $amount);

        return Payment::create([
            'student_term_id'   => $studentTerm->id,
            'billing_period_id' => $this->periodId($studentTerm, $periodNo),
            'amount'            => $amount,
            'channel'           => $channel,
            'reference_no'      => $reference,
            'proof_path'        => $proofPath,
            'status'            => 'pending',
        ]);
    }

    public function recordCash(StudentTerm $studentTerm, float $amount, User $cashier, ?int $periodNo = null): Payment
    {
        $this->assertWithinBalance($studentTerm, $amount);

        return DB::transaction(function () use ($studentTerm, $amount, $cashier, $periodNo) {
            $payment = Payment::create([
                'student_term_id'   => $studentTerm->id,
                'billing_period_id' => $this->periodId($studentTerm, $periodNo),
                'amount'            => $amount,
                'channel'           => 'cash',
                'status'            => 'pending',
                'received_by'       => $cashier->id,
            ]);

            return $this->verify($payment, $cashier);
        });
    }

    public function verify(Payment $payment, User $cashier): Payment
    {
        return DB::transaction(function () use ($payment, $cashier) {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if (! in_array($payment->status, ['pending', 'flagged'], true)) {
                throw ValidationException::withMessages(['payment' => 'This payment can no longer be verified.']);
            }

            $periods = BillingPeriod::where('student_term_id', $payment->student_term_id)
                ->orderBy('period_no')->lockForUpdate()->get();

            $preferred = $payment->billingPeriod?->period_no;
            if ($preferred) {
                $periods = $periods
                    ->sortBy(fn ($p) => $p->period_no === $preferred ? 0 : $p->period_no)
                    ->values();
            }

            $remaining = round((float) $payment->amount, 2);

            foreach ($periods as $period) {
                if ($remaining <= 0) {
                    break;
                }
                $balance = round((float) $period->amount_due - (float) $period->amount_paid, 2);
                if ($balance <= 0) {
                    continue;
                }

                $apply = min($balance, $remaining);
                $period->amount_paid = round((float) $period->amount_paid + $apply, 2);
                $period->save();

                PaymentAllocation::create([
                    'payment_id'        => $payment->id,
                    'billing_period_id' => $period->id,
                    'amount'            => $apply,
                ]);

                $remaining = round($remaining - $apply, 2);
            }

            if ($remaining > 0) {
                throw ValidationException::withMessages(['amount' => 'Payment is more than the remaining balance.']);
            }

            $payment->update([
                'status'      => 'verified',
                'flag_reason' => null,
                'verified_at' => now(),
                'received_by' => $payment->received_by ?? $cashier->id,
                'receipt_no'  => sprintf('OR-%s-%06d', now()->format('Ymd'), $payment->id),
            ]);

            return $payment->fresh();
        });
    }

    public function flag(Payment $payment, string $reason): Payment
    {
        $payment->update(['status' => 'flagged', 'flag_reason' => $reason]);
        return $payment;
    }

    public function void(Payment $payment, User $by, string $reason): Payment
    {
        return DB::transaction(function () use ($payment, $by, $reason) {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($payment->status === 'void') {
                return $payment;
            }

            if ($payment->status === 'verified') {
                foreach ($payment->allocations as $allocation) {
                    $period = BillingPeriod::whereKey($allocation->billing_period_id)->lockForUpdate()->first();
                    $period->amount_paid = max(0, round((float) $period->amount_paid - (float) $allocation->amount, 2));
                    $period->save();
                }
                $payment->allocations()->delete();
            }

            $payment->update(['status' => 'void', 'voided_at' => now(), 'void_reason' => $reason]);

            return $payment;
        });
    }

    private function assertWithinBalance(StudentTerm $studentTerm, float $amount): void
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Amount must be greater than zero.']);
        }
        if ($amount > $studentTerm->balance()) {
            throw ValidationException::withMessages(['amount' => 'Amount is more than the remaining balance.']);
        }
    }

    private function periodId(StudentTerm $studentTerm, ?int $periodNo): ?int
    {
        return $periodNo
            ? $studentTerm->billingPeriods()->where('period_no', $periodNo)->value('id')
            : null;
    }
}