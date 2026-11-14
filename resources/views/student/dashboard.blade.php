@extends('layouts.app')
@section('content')
<div class="row">
  <div class="col-md-3">
    <div class="card mb-3"><div class="card-header">Profile</div><div class="card-body small">
      <div><strong>{{ $student->full_name }}</strong></div><div>{{ $student->student_no }}</div>
      <div>{{ $student->program->name }}</div><div>Year {{ $student->year_level }}</div>
    </div></div>
    <div class="card mb-3"><div class="card-header">Year &amp; Semester</div><div class="list-group list-group-flush">
      @foreach($terms->groupBy('year_level') as $yl => $group)
        <div class="list-group-item bg-light fw-bold">Year {{ $yl }}</div>
        @foreach($group as $t)
          <a class="list-group-item list-group-item-action {{ $st && $st->id == $t->id ? 'active' : '' }}" href="{{ route('student.dashboard', ['term' => $t->id]) }}">{{ $t->schoolTerm->school_year }} – Sem {{ $t->schoolTerm->semester }}</a>
        @endforeach
      @endforeach
    </div></div>
    <div class="card"><div class="card-header">Unlock next semester</div><div class="card-body">
      <form method="POST" action="{{ route('student.unlock') }}">@csrf
        <input name="code" class="form-control mb-2" placeholder="New COE code" required><button class="btn btn-primary w-100">Unlock</button></form>
    </div></div>
  </div>

  <div class="col-md-9">
  @if(! $st)
    <div class="alert alert-secondary">No semester unlocked yet.</div>
  @else
    @if($readOnly)<div class="alert alert-secondary">Past semester — read-only history.</div>@endif

    <div class="card mb-3"><div class="card-header">Tuition — balance ₱{{ number_format($st->balance(), 2) }}</div><div class="card-body p-0">
      <table class="table table-sm mb-0"><thead><tr><th>Period</th><th>Due</th><th>Paid</th><th>Balance</th><th>Exam clearance</th><th></th></tr></thead><tbody>
      @foreach($periods as $p)
      <tr><td>{{ $p->label }}</td><td>₱{{ number_format((float) $p->amount_due, 2) }}</td><td>₱{{ number_format((float) $p->amount_paid, 2) }}</td><td>₱{{ number_format($p->balance, 2) }}</td>
        <td>@include('partials.badge', ['s' => $clearance[$p->period_no]])</td>
        <td>@if(! $readOnly && $clearance[$p->period_no] !== 'red')<a class="btn btn-sm btn-outline-success" target="_blank" href="{{ route('student.permit', $p->period_no) }}">Exam permit</a>@endif</td></tr>
      @endforeach
      </tbody></table>
    </div></div>

    @foreach($notes->where('status', 'active') as $n)
      <div class="alert alert-warning">Promissory note for <strong>{{ $n->billingPeriod->label }}</strong> is due on <strong>{{ $n->due_date->format('M d, Y') }}</strong>.</div>
    @endforeach

    @if(! $readOnly && $st->balance() > 0)
    <div class="card mb-3"><div class="card-header">Pay online (verified by the cashier)</div><div class="card-body">
      <form method="POST" action="{{ route('student.pay', $st) }}" enctype="multipart/form-data" class="row g-2">@csrf
        <div class="col-md-4"><select name="mode" class="form-select"><option value="full">Pay all semester balance (₱{{ number_format($st->balance(), 2) }})</option><option value="period">Pay a single period (full or partial)</option></select></div>
        <div class="col-md-3"><select name="period_no" class="form-select"><option value="">Period (if single)</option>@foreach($periods as $p)<option value="{{ $p->period_no }}">{{ $p->label }} (₱{{ number_format($p->balance, 2) }})</option>@endforeach</select></div>
        <div class="col-md-2"><input name="amount" type="number" step="0.01" min="0.01" class="form-control" placeholder="Amount ₱"></div>
        <div class="col-md-3"><select name="channel" class="form-select"><option value="gcash">GCash</option><option value="maya">Maya</option><option value="bank">Bank</option></select></div>
        <div class="col-md-5"><input name="reference_no" class="form-control" placeholder="Reference no." required></div>
        <div class="col-md-5"><input type="file" name="proof" accept="image/*" class="form-control"></div>
        <div class="col-md-2"><button class="btn btn-success w-100">Submit</button></div>
      </form>
    </div></div>
    @endif

    <div class="card mb-3"><div class="card-header">Payment history</div><div class="card-body p-0">
      <table class="table table-sm mb-0"><thead><tr><th>Date</th><th>Amount</th><th>Channel</th><th>Ref</th><th>Status</th><th></th></tr></thead><tbody>
      @forelse($payments as $p)
      <tr><td>{{ $p->created_at->format('M d, Y h:i A') }}</td><td>₱{{ number_format((float) $p->amount, 2) }}</td><td>{{ strtoupper($p->channel) }}</td><td>{{ $p->reference_no }}</td>
        <td>@include('partials.badge', ['s' => $p->status]) {{ $p->flag_reason }}</td>
        <td>@if($p->status === 'verified')<a class="btn btn-sm btn-outline-primary" target="_blank" href="{{ route('student.receipt', $p) }}">E-Receipt</a>@endif</td></tr>
      @empty<tr><td colspan="6" class="text-muted">No payments yet.</td></tr>@endforelse
      </tbody></table>
    </div></div>

    <div class="card mb-3"><div class="card-header">Subjects &amp; exam status</div><div class="card-body p-0">
      <table class="table table-sm mb-0"><thead><tr><th>Subject</th><th>Teacher</th>@foreach($examPeriods as $ep)<th>{{ $ep->name }}</th>@endforeach</tr></thead><tbody>
      @forelse($enrollments as $e)
      <tr><td>{{ $e->classOffering->subject->code }} - {{ $e->classOffering->subject->title }}<div class="small text-muted">{{ $e->classOffering->schedule }}</div></td><td>{{ $e->classOffering->teacher->name }}</td>
        @foreach($examPeriods as $ep)<td>@include('partials.badge', ['s' => optional($records->get($e->id . '-' . $ep->id))->status ?? 'pending'])</td>@endforeach</tr>
      @empty<tr><td colspan="{{ 2 + $examPeriods->count() }}" class="text-muted">No subjects loaded yet.</td></tr>@endforelse
      </tbody></table>
    </div></div>
  @endif
  </div>
</div>
@endsection