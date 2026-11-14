@extends('layouts.app')
@section('content')
<div class="bg-white p-4 border">
  <h4 class="text-center">CLEARANCE &amp; EVALUATION SUMMARY</h4>
  <p class="text-center text-muted">Printed {{ now()->format('M d, Y h:i A') }}</p>
  <p><strong>{{ $student->full_name }}</strong> ({{ $student->student_no }}) · {{ $student->program->name }} · Year {{ $student->year_level }} · Status: {{ ucfirst($student->status) }}
    @if($holds) · <span class="text-danger fw-bold">ON CASHIER HOLD</span>@endif</p>
  @foreach($terms as $t)
    <h6 class="mt-3">{{ $t->schoolTerm->school_year }} - Sem {{ $t->schoolTerm->semester }} (Year {{ $t->year_level }})</h6>
    <table class="table table-sm table-bordered"><thead><tr><th>Period</th><th>Due</th><th>Paid</th><th>Balance</th><th>Exam clearance</th></tr></thead><tbody>
    @foreach($t->billingPeriods as $p)
      <tr><td>{{ $p->label }}</td><td>₱{{ number_format((float) $p->amount_due, 2) }}</td><td>₱{{ number_format((float) $p->amount_paid, 2) }}</td><td>₱{{ number_format($p->balance, 2) }}</td><td>@include('partials.badge', ['s' => $t->statuses[$p->period_no]])</td></tr>
    @endforeach
    </tbody></table>
    <div>Semester balance: <strong>₱{{ number_format($t->balance(), 2) }}</strong></div>
  @endforeach
</div>
<button class="btn btn-primary mt-3 no-print" onclick="window.print()">Print</button>
@endsection