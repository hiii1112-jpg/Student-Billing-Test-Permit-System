<?php

namespace App\Http\Controllers;

use App\Models\ClassEnrollment;
use App\Models\CoeCode;
use App\Models\ExamPeriod;
use App\Models\ExamRecord;
use App\Models\Payment;
use App\Models\PromissoryNote;
use App\Models\SchoolTerm;
use App\Models\Student;
use App\Models\StudentTerm;
use App\Models\User;
use App\Services\BillingService;
use App\Services\ClearanceService;
use App\Services\PaymentService;
use App\Support\Audit;
use App\Support\Notify;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StudentController extends Controller
{
    private function me(): Student
    {
        return auth()->user()->student ?? abort(403);
    }

    public function dashboard(Request $request)
    {
        $student = $this->me();
        $terms = $student->terms()->with('schoolTerm')->get()->sortBy('school_term_id')->values();
        $current = SchoolTerm::current();

        $st = $request->term
            ? $terms->firstWhere('id', (int) $request->term)
            : ($terms->firstWhere('school_term_id', optional($current)->id) ?? $terms->last());

        $data = ['student' => $student->load('program'), 'terms' => $terms, 'st' => $st];

        if ($st) {
            $clear = app(ClearanceService::class);
            $enrollments = ClassEnrollment::with('classOffering.subject', 'classOffering.teacher')
                ->where('student_id', $student->id)
                ->whereHas('classOffering', fn ($q) => $q->where('school_term_id', $st->school_term_id))
                ->get();

            $data += [
                'readOnly'    => $st->school_term_id != optional($current)->id,
                'periods'     => $st->billingPeriods,
                'clearance'   => collect(range(1, 4))->mapWithKeys(fn ($n) => [$n => $clear->statusFor($st, $n)]),
                'payments'    => $st->payments()->latest()->get(),
                'notes'       => $st->promissoryNotes()->with('billingPeriod')->latest()->get(),
                'examPeriods' => ExamPeriod::where('school_term_id', $st->school_term_id)->orderBy('period_no')->get(),
                'enrollments' => $enrollments,
                'records'     => ExamRecord::whereIn('class_enrollment_id', $enrollments->pluck('id'))->get()
                    ->keyBy(fn ($r) => $r->class_enrollment_id . '-' . $r->exam_period_id),
            ];
        }

        return view('student.dashboard', $data);
    }

    public function unlock(Request $request)
    {
        $d = $request->validate(['code' => 'required|string']);
        $student = $this->me();

        $coe = CoeCode::where('student_id', $student->id)
            ->where('code', strtoupper(trim($d['code'])))->whereNull('used_at')->first();

        if (! $coe) {
            throw ValidationException::withMessages(['code' => 'Invalid or already used COE code.']);
        }

        $st = DB::transaction(function () use ($coe, $student) {
            $coe->update(['used_at' => now()]);
            return app(BillingService::class)->openTerm($student, $coe->schoolTerm, $student->year_level);
        });

        Audit::log('term.unlock', $coe->code);

        return redirect()->route('student.dashboard', ['term' => $st->id])->with('status', 'Semester unlocked.');
    }

    public function pay(Request $request, StudentTerm $st)
    {
        $student = $this->me();
        abort_unless($st->student_id == $student->id, 403);
        abort_unless($st->school_term_id == optional(SchoolTerm::current())->id, 403);

        $d = $request->validate([
            'mode'         => 'required|in:full,period',
            'period_no'    => 'required_if:mode,period|nullable|integer|between:1,4',
            'amount'       => 'required_if:mode,period|nullable|numeric|min:0.01',
            'channel'      => 'required|in:gcash,maya,bank',
            'reference_no' => 'required|string|max:60',
            'proof'        => 'nullable|image|max:2048',
        ]);

        if (Payment::where('channel', $d['channel'])->where('reference_no', $d['reference_no'])->exists()) {
            throw ValidationException::withMessages(['reference_no' => 'This reference number was already submitted.']);
        }

        $periodNo = null;
        if ($d['mode'] === 'full') {
            $amount = $st->balance();
        } else {
            $period = $st->billingPeriods()->where('period_no', $d['period_no'])->firstOrFail();
            $amount = (float) $d['amount'];
            if ($amount > $period->balance) {
                throw ValidationException::withMessages(['amount' => 'Amount is more than the balance of that period.']);
            }
            $periodNo = (int) $d['period_no'];
        }

        $path = $request->file('proof')?->store('proofs', 'public');

        app(PaymentService::class)->submitOnline($st, $amount, $d['channel'], $d['reference_no'], $path, $periodNo);

        User::where('role', 'cashier')->pluck('id')->each(fn ($id) => Notify::to(
            $id, $student->full_name . ' submitted a ' . strtoupper($d['channel']) . ' payment of ₱' . number_format($amount, 2) . ' (ref ' . $d['reference_no'] . ').'
        ));
        Audit::log('payment.submit', $d['channel'] . ' ref ' . $d['reference_no']);

        return back()->with('status', 'Payment submitted. Waiting for cashier verification.');
    }

    public function receipt(Payment $payment)
    {
        abort_unless($payment->status === 'verified' && $payment->studentTerm->student_id == $this->me()->id, 404);
        return view('shared.receipt', ['payment' => $payment->load('studentTerm.student', 'studentTerm.schoolTerm', 'receiver')]);
    }

    public function permit(int $periodNo)
    {
        $student = $this->me();
        $current = SchoolTerm::current() ?? abort(404);
        $st = $student->terms()->where('school_term_id', $current->id)->firstOrFail();
        abort_unless($periodNo >= 1 && $periodNo <= 4, 404);

        $status = app(ClearanceService::class)->statusFor($st, $periodNo);
        if ($status === ClearanceService::RED) {
            return redirect()->route('student.dashboard')->withErrors(['permit' => 'No exam permit: you are not cleared for this exam period.']);
        }

        $period = $st->billingPeriods()->where('period_no', $periodNo)->first();
        $note = $status === ClearanceService::YELLOW
            ? PromissoryNote::where('billing_period_id', $period->id)->where('status', 'active')->first()
            : null;

        return view('student.permit', [
            'student'     => $student->load('program'),
            'term'        => $current,
            'period'      => $period,
            'periodNo'    => $periodNo,
            'status'      => $status,
            'note'        => $note,
            'enrollments' => ClassEnrollment::with('classOffering.subject')->where('student_id', $student->id)
                ->whereHas('classOffering', fn ($q) => $q->where('school_term_id', $current->id))->get(),
        ]);
    }
}