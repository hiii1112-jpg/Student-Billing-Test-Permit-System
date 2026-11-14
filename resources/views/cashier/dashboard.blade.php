@extends('layouts.app')
@section('autorefresh', '20')
@section('content')
<h4>Cashier Dashboard <small class="text-muted">{{ auth()->user()->isHead() ? 'Head Cashier' : 'Student Assistant' }}</small></h4>
<form class="row g-2 mb-4" method="GET" action="{{ route('cashier.search') }}">
  <div class="col-md-4"><input name="q" class="form-control" placeholder="Student No." required></div>
  <div class="col"><button class="btn btn-primary">Search student / balance inquiry</button></div>
</form>

<h5>Online payments (GCash / Maya / Bank) <small class="text-muted">auto-refreshes every 20s</small></h5>
<table class="table table-sm bg-white">
  <thead><tr><th>Student</th><th>Amount</th><th>Channel</th><th>Reference</th><th>Proof</th><th>Status</th><th></th></tr></thead><tbody>
  @forelse($pending as $p)
  <tr><td><a href="{{ route('cashier.ledger', $p->studentTerm->student) }}">{{ $p->studentTerm->student->full_name }}</a></td>
    <td>₱{{ number_format((float) $p->amount, 2) }}</td><td>{{ strtoupper($p->channel) }}</td><td>{{ $p->reference_no }}</td>
    <td>@if($p->proof_path)<a target="_blank" href="{{ asset('storage/' . $p->proof_path) }}">view</a>@endif</td>
    <td>@include('partials.badge', ['s' => $p->status]) {{ $p->flag_reason }}</td>
    <td>
      @if(auth()->user()->isHead())
      <div class="d-flex gap-1">
        <form method="POST" action="{{ route('cashier.verify', $p) }}">@csrf<button class="btn btn-sm btn-success">Verify</button></form>
        <form method="POST" action="{{ route('cashier.flag', $p) }}" class="d-flex gap-1">@csrf<input name="reason" class="form-control form-control-sm" placeholder="Error reason" required><button class="btn btn-sm btn-warning">Flag</button></form>
      </div>
      @else<span class="text-muted small">Head cashier verifies</span>@endif
    </td></tr>
  @empty
  <tr><td colspan="7" class="text-center text-muted">No pending online payments.</td></tr>
  @endforelse
  </tbody>
</table>

<h5 class="mt-4">Recent payments</h5>
<table class="table table-sm bg-white"><thead><tr><th>When</th><th>Student</th><th>Amount</th><th>Channel</th><th>Status</th><th>OR No.</th></tr></thead><tbody>
@foreach($recent as $p)
<tr><td>{{ $p->created_at->format('M d h:i A') }}</td><td>{{ $p->studentTerm->student->full_name }}</td><td>₱{{ number_format((float) $p->amount, 2) }}</td><td>{{ strtoupper($p->channel) }}</td>
  <td>@include('partials.badge', ['s' => $p->status])</td><td>{{ $p->receipt_no }}</td></tr>
@endforeach
</tbody></table>
@endsection