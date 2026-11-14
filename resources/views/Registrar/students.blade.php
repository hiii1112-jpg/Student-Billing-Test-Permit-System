@extends('layouts.app')
@section('content')
<h4>Students</h4>
<form class="row g-2 mb-3" method="GET">
  <div class="col-md-4"><input name="q" value="{{ request('q') }}" class="form-control" placeholder="Search student no. or name"></div>
  <div class="col"><button class="btn btn-secondary">Search</button></div>
</form>

<div class="row g-3 mb-3">
  <div class="col-md-7"><div class="card"><div class="card-header">Add student</div><div class="card-body">
    <form method="POST" action="{{ route('registrar.students.store') }}" class="row g-2">@csrf
      <div class="col-md-4"><input name="student_no" class="form-control" placeholder="Student No." required></div>
      <div class="col-md-4"><input name="first_name" class="form-control" placeholder="First name" required></div>
      <div class="col-md-4"><input name="last_name" class="form-control" placeholder="Last name" required></div>
      <div class="col-md-4"><select name="program_id" class="form-select" required>@foreach($programs as $p)<option value="{{ $p->id }}">{{ $p->code }}</option>@endforeach</select></div>
      <div class="col-md-3"><select name="year_level" class="form-select">@for($i = 1; $i <= 4; $i++)<option value="{{ $i }}">Year {{ $i }}</option>@endfor</select></div>
      <div class="col-md-5"><select name="block_id" class="form-select"><option value="">No block</option>@foreach($blocks as $b)<option value="{{ $b->id }}">Y{{ $b->year_level }} - {{ $b->name }}</option>@endforeach</select></div>
      <div class="col-12"><button class="btn btn-primary">Save &amp; generate COE code</button></div>
    </form>
  </div></div></div>
  <div class="col-md-5"><div class="card"><div class="card-header">Bulk upload (CSV)</div><div class="card-body">
    <form method="POST" action="{{ route('registrar.students.import') }}" enctype="multipart/form-data">@csrf
      <input type="file" name="csv" accept=".csv,.txt" class="form-control mb-2" required>
      <div class="small text-muted mb-2">Header row: <code>student_no,first_name,last_name,program_code,year_level,block</code></div>
      <button class="btn btn-success">Upload</button>
    </form>
  </div></div></div>
</div>

@if(session('import_errors'))
<div class="alert alert-warning"><strong>Skipped rows:</strong><ul class="mb-0">@foreach(session('import_errors') as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<table class="table table-sm table-striped bg-white">
  <thead><tr><th>Student No.</th><th>Name</th><th>Program</th><th>Year</th><th>Status</th><th>Activated</th><th></th></tr></thead>
  <tbody>
  @forelse($students as $s)
    <tr><td>{{ $s->student_no }}</td><td>{{ $s->full_name }}</td><td>{{ $s->program->code }}</td><td>{{ $s->year_level }}</td>
        <td>{{ ucfirst($s->status) }}</td><td>{{ $s->is_activated ? 'Yes' : 'No' }}</td>
        <td><a class="btn btn-sm btn-outline-primary" href="{{ route('registrar.students.show', $s) }}">Open</a></td></tr>
  @empty
    <tr><td colspan="7" class="text-center text-muted">No students yet.</td></tr>
  @endforelse
  </tbody>
</table>
{{ $students->links() }}
@endsection