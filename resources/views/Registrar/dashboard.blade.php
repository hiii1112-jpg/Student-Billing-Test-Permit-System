@extends('layouts.app')
@section('content')
<h4>Registrar Dashboard</h4>
<p>Current term: <strong>{{ $term ? $term->school_year . ' - Sem ' . $term->semester : 'NOT SET (go to Semester)' }}</strong></p>
<div class="row g-3 mb-4">
  @foreach(['students' => 'Active Students', 'activated' => 'Activated Accounts', 'teachers' => 'Teachers', 'cashiers' => 'Cashiers'] as $k => $label)
  <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted">{{ $label }}</div><div class="fs-3">{{ $counts[$k] }}</div></div></div></div>
  @endforeach
</div>
<form method="POST" action="{{ route('registrar.maintenance') }}">@csrf
  <button class="btn {{ $maintenance ? 'btn-danger' : 'btn-outline-danger' }}">
    Maintenance mode: {{ $maintenance ? 'ON (click to turn off)' : 'OFF (click to turn on)' }}</button>
</form>
@endsection