@extends('layouts.app')
@section('content')
@php $isHead = auth()->user()->isHead(); @endphp
<div class="d-flex justify-content-between">
  <h4>{{ $student->full_name }} <small class="text-muted">{{ $student->student_no }} · {{ $student->program->code }} · Year {{ $student->year_level }}</small></h4>
  <a href="{{ route('cashier.dashboard') }}" class="btn btn-outline-secondary btn-sm align-self-start">Back</a>
</div>
@foreach($holds as $h)
<div class="alert alert-danger d-flex justify-content-between">Registrar hold ({{ $h->reason }})
  @if($isHead)<form method="POST" action="{{ route('cashier.release', $h) }}">@csrf<button class="btn btn-sm btn-light">Release</button></form>@endif</div>
@endforeach

<ul class="nav nav-pills mb-3">
@foreach($terms as $t)<li class="nav-item"><a class="nav-link {{ $st && $st->id == $t->id ? 'active' : '' }}" href="{{ route('cashier.ledger', ['student' => $student, 'term' => $t->id]) }}">{{ $t->schoolTerm->school_year }} S{{ $t->schoolTerm->semester }}</a></li>@endforeach
</ul>

@if(! $st)
  <div class="alert alert-secondary">This student has not unlocked any semester yet.</div>
@else
<div class="row g-3">
  <div class="col-md-7"><div class="card mb-3"><div class="card-header">Billing — balance ₱{{ number_format($st->balance(), 2) }}</div><div class="card-body p-0">
    <table class="table table-sm mb-0"><thead><tr><th>Period</th><th>Due</th><th>Paid</th><th>Balance</th><th>Exam clearance</th></tr></thead><tbody>
    @foreach($periods as $p)<tr><td>{{ $p->label }}</td><td>₱{{ number_format((float) $p->amount_due, 2) }}</td><td>₱{{ number_format((float) $p->amount_paid, 2) }}</td><td>₱{{ number_format($p->balance, 2) }}</td><td>@include('partials.badge', ['s' => $clearance[$p->period_no]])</td></tr>@endforeach
    </tbody></table></div></div></div>

  <div class="col-md-5">
    <div class="card mb-3"><div class="card-header">Encode cash payment (auto-deduct / carry-over)</div><div class="card-body">
      <form method="POST" action="{{ route('cashier.cash', $st) }}" class="row g-2">@csrf
        <div class="col-6"><input name="amount" type="number" step="0.01" min="0.01" class="form-control" placeholder="Amount ₱" required></div>
        <div class="col-6"><select name="period_no" class="form-select"><option value="">Oldest unpaid first</option>@foreach($periods as $p)<option value="{{ $p->period_no }}">{{ $p->label }}</option>@endforeach</select></div>
        <div class="col-12"><button class="btn btn-success">Receive cash &amp; print OR</button></div>
      </form></div></div>

    @if($isHead)
    <div class="card mb-3"><div class="card-header">Approve promissory note</div><div class="card-body">
      <form method="POST" action="{{ route('cashier.promissory', $st) }}" class="row g-2">@csrf
        <div class="col-6"><select name="billing_period_id" class="form-select">@foreach($periods->filter(fn ($p) => $p->balance > 0) as $p)<option value="{{ $p->id }}">{{ $p->label }}</option>@endforeach</select></div>
        <div class="col-6"><input name="due_date" type="date" class="form-control" required></div>
        <div class="col-12"><input name="notes" class="form-control" placeholder="Notes (physical letter verified)"></div>
        <div class="col-12"><button class="btn btn-warning">Approve CLEARED VIA PROMISSORY</button></div>
      </form></div></div>

    <div class="card"><div class="card-header">Registrar hold</div><div class="card-body">
      <form method="POST" action="{{ route('cashier.hold', $student) }}" class="d-flex gap-2">@csrf
        <select name="reason" class="form-select"><option value="year_end">Year-end</option><option value="transfer">Transfer</option></select>
        <button class="btn btn-danger">Trigger hold</button></form></div></div>
    @endif
  </div>
</div>

<h5 class="mt-3">Payments</h5>
<table class="table table-sm bg-white"><thead><tr><th>When</th><th>Amount</th><th>Channel</th><th>Ref</th><th>Status</th><th>OR No.</th><th></th></tr></thead><tbody>
@forelse($payments as $p)
<tr><td>{{ $p->created_at->format('M d, Y h:i A') }}</td><td>₱{{ number_format((float) $p->amount, 2) }}</td><td>{{ strtoupper($p->channel) }}</td><td>{{ $p->reference_no }}</td>
  <td>@include('partials.badge', ['s' => $p->status]) {{ $p->void_reason ?? $p->flag_reason }}</td><td>{{ $p->receipt_no }}</td>
  <td class="d-flex gap-2">
    @if($p->status === 'verified')<a class="btn btn-sm btn-outline-primary" target="_blank" href="{{ route('cashier.receipt', $p) }}">Print OR</a>@endif
    @if($isHead && $p->status !== 'void')
      <form method="POST" action="{{ route('cashier.void', $p) }}" class="d-flex gap-1" onsubmit="return confirm('Void this payment?')">@csrf
        <input name="reason" class="form-control form-control-sm" placeholder="Void reason" required><button class="btn btn-sm btn-outline-danger">Void</button></form>
    @endif
  </td></tr>
@empty<tr><td colspan="7" class="text-muted">No payments.</td></tr>@endforelse
</tbody></table>

<h5>Promissory notes</h5>
<table class="table table-sm bg-white"><thead><tr><th>Period</th><th>Due date</th><th>Status</th><th>Notes</th></tr></thead><tbody>
@forelse($notes as $n)<tr><td>{{ $n->billingPeriod->label }}</td><td>{{ $n->due_date->format('M d, Y') }}</td><td>@include('partials.badge', ['s' => $n->status])</td><td>{{ $n->notes }}</td></tr>
@empty<tr><td colspan="4" class="text-muted">None.</td></tr>@endforelse
</tbody></table>
@endif
@endsection