@extends('layouts.app')
@section('content')
<h4>Semester Switch &amp; Block Progression</h4>
<div class="card mb-3"><div class="card-body">
  <form method="POST" action="{{ route('registrar.terms.store') }}" class="row g-2" onsubmit="return confirm('Switch to a new semester? Activated students get new COE codes, and students move up a year when the school year changes.')">@csrf
    <div class="col-md-3"><input name="school_year" class="form-control" placeholder="2026-2027" required></div>
    <div class="col-md-2"><select name="semester" class="form-select"><option value="1">1st Sem</option><option value="2">2nd Sem</option></select></div>
    <div class="col-md-3"><button class="btn btn-danger">Create &amp; switch</button></div>
  </form>
  <div class="small text-muted mt-2">Creates the 4 exam periods, makes this the current term, issues new COE codes, and promotes students (Year +1) when the school year changes. Old terms become read-only history.</div>
</div></div>
<table class="table table-sm bg-white"><thead><tr><th>School year</th><th>Semester</th><th>Current</th></tr></thead><tbody>
@foreach($terms as $t)<tr><td>{{ $t->school_year }}</td><td>{{ $t->semester }}</td><td>{{ $t->is_current ? 'Yes' : '' }}</td></tr>@endforeach
</tbody></table>
@endsection