<?php

namespace App\Http\Controllers;

use App\Models\BillingPeriod;
use App\Models\ClearanceHold;
use App\Models\Payment;
use App\Models\PromissoryNote;
use App\Models\Student;
use App\Models\StudentTerm;
use App\Services\ClearanceService;
use App\Services\PaymentService;
use App\Support\Audit;
use App\Support\Notify;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CashierController extends Controller
{
    public function dashboard()
    {
        return view('cashier.dashboard', [
            'pending' => Payment::with('studentTerm.student')->whereIn('status', ['pending', 'flagged'])
                ->where('channel', '!=', 'cash')->orderBy('id')->get(),
            'recent'  => Payment::with('studentTerm.student')->latest()->limit(10)->get(),
        ]);
    }

    public function search(Request $request)
    {
        $student = Student::where('student_no', trim((string) $request->q))->first();
        if (! $student) {
            return back()->withErrors(['q' => 'Student not found.']);
        }

        return redirect()->route('cashier.ledger', $student);
    }

    public function ledger(Request $request, Student $student)
    {
        $terms = $student->terms()->with('schoolTerm')->get()->sortByDesc('school_term_id')->values();
        $st = $request->term ? $terms->firstWhere('id', (int) $request->term) : $terms->first();
        $clear = app(ClearanceService::class);

        return view('cashier.ledger', [
            'student'   => $student->load('program'),
            'terms'     => $terms,
            'st'        => $st,
            'periods'   => $st ? $st->billingPeriods : collect(),
            'clearance' => $st ? collect(range(1, 4))->mapWithKeys(fn ($n) => [$n => $clear->statusFor($st, $n)]) : collect(),
            'payments'  => $st ? $st->payments()->latest()->get() : collect(),
            'notes'     => $st ? $st->promissoryNotes()->with('billingPeriod')->latest()->get() : collect(),
            'holds'     => ClearanceHold::where('student_id', $student->id)->whereNull('released_at')->get(),
        ]);
    }

    public function cashPay(Request $request, StudentTerm $st)
    {
        $d = $request->validate(['amount' => 'required|numeric|min:0.01', 'period_no' => 'nullable|integer|between:1,4']);

        $payment = app(PaymentService::class)->recordCash($st, (float) $d['amount'], $request->user(), $d['period_no'] ?? null);
        Notify::to($st->student->user_id, 'Cash payment of ₱' . number_format((float) $payment->amount, 2) . ' received. OR No. ' . $payment->receipt_no);
        Audit::log('payment.cash', $payment->receipt_no . ' / ' . $st->student->student_no);

        return redirect()->route('cashier.receipt', $payment);
    }

    public function verify(Request $request, Payment $payment)
    {
        $payment = app(PaymentService::class)->verify($payment, $request->user());
        Notify::to($payment->studentTerm->student->user_id, 'Your ' . strtoupper($payment->channel) . ' payment of ₱' . number_format((float) $payment->amount, 2) . ' was verified. OR No. ' . $payment->receipt_no);
        Audit::log('payment.verify', $payment->receipt_no);

        return back()->with('status', 'Payment verified. Clearance updated for the student and teachers.');
    }

    public function flag(Request $request, Payment $payment)
    {
        $d = $request->validate(['reason' => 'required|string|max:255']);
        app(PaymentService::class)->flag($payment, $d['reason']);
        Notify::to($payment->studentTerm->student->user_id, 'Your payment (ref ' . $payment->reference_no . ') was flagged: ' . $d['reason']);
        Audit::log('payment.flag', 'ref ' . $payment->reference_no);

        return back()->with('status', 'Payment flagged.');
    }

    public function void(Request $request, Payment $payment)
    {
        $d = $request->validate(['reason' => 'required|string|max:255']);
        app(PaymentService::class)->void($payment, $request->user(), $d['reason']);
        Notify::to($payment->studentTerm->student->user_id, 'A payment of ₱' . number_format((float) $payment->amount, 2) . ' was voided: ' . $d['reason']);
        Audit::log('payment.void', '#' . $payment->id . ' - ' . $d['reason']);

        return back()->with('status', 'Payment voided and balance restored.');
    }

    public function promissory(Request $request, StudentTerm $st)
    {
        $d = $request->validate([
            'billing_period_id' => ['required', Rule::exists('billing_periods', 'id')->where('student_term_id', $st->id)],
            'due_date'          => 'required|date|after:today',
            'notes'             => 'nullable|string|max:255',
        ]);

        $period = BillingPeriod::findOrFail($d['billing_period_id']);

        if (PromissoryNote::where('billing_period_id', $period->id)->where('status', 'active')->exists()) {
            return back()->withErrors(['billing_period_id' => 'An active promissory note already exists for this period.']);
        }
        if (app(ClearanceService::class)->statusFor($st, $period->period_no) === ClearanceService::GREEN) {
            return back()->withErrors(['billing_period_id' => 'This period is already fully paid.']);
        }

        PromissoryNote::create([
            'student_term_id'   => $st->id,
            'billing_period_id' => $period->id,
            'due_date'          => $d['due_date'],
            'approved_by'       => $request->user()->id,
            'notes'             => $d['notes'] ?? null,
        ]);

        Notify::to($st->student->user_id, "You are CLEARED VIA PROMISSORY for {$period->label} exam until {$d['due_date']}. Pay before the due date.");
        Audit::log('promissory.approve', $st->student->student_no . ' / ' . $period->label);

        return back()->with('status', 'Promissory approved. Student and teachers now see YELLOW.');
    }

    public function hold(Request $request, Student $student)
    {
        $d = $request->validate(['reason' => 'required|in:year_end,transfer']);
        ClearanceHold::create(['student_id' => $student->id, 'reason' => $d['reason'], 'placed_by' => $request->user()->id]);
        Audit::log('hold.place', $student->student_no . ' / ' . $d['reason']);

        return back()->with('status', 'Registrar hold placed.');
    }

    public function release(ClearanceHold $hold)
    {
        $hold->update(['released_at' => now()]);
        Audit::log('hold.release', '#' . $hold->id);

        return back()->with('status', 'Hold released.');
    }

    public function receipt(Payment $payment)
    {
        abort_unless($payment->status === 'verified', 404);
        return view('shared.receipt', ['payment' => $payment->load('studentTerm.student', 'studentTerm.schoolTerm', 'receiver')]);
    }

    public function report(Request $request)
    {
        $date = $request->date ?: today()->toDateString();
        $payments = Payment::with('studentTerm.student', 'receiver')
            ->where('status', 'verified')->whereDate('verified_at', $date)->get();

        return view('cashier.report', [
            'date'       => $date,
            'payments'   => $payments,
            'byChannel'  => $payments->groupBy('channel')->map(fn ($g) => round($g->sum(fn ($p) => (float) $p->amount), 2)),
            'voided'     => Payment::where('status', 'void')->whereDate('voided_at', $date)->get(),
            'cashOnHand' => $request->filled('cash_on_hand') ? (float) $request->cash_on_hand : null,
        ]);
    }
}