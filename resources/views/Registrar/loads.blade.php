@extends('layouts.app')
@section('content')
<h4>Teacher Load &amp; Loading Codes
  <small class="text-muted">{{ $term ? $term->school_year . ' Sem ' . $term->semester : 'Set the semester first' }}</small></h4>
<div class="card mb-3"><div class="card-body">
  <form method="POST" action="{{ route('registrar.loads.store') }}" class="row g-2">@csrf
    <div class="col-md-3"><select name="subject_id" class="form-select" required>@foreach($subjects as $s)<option value="{{ $s->id }}">{{ $s->code }} - {{ $s->title }}</option>@endforeach</select></div>
    <div class="col-md-2"><select name="block_id" class="form-select" required>@foreach($blocks as $b)<option value="{{ $b->id }}">Y{{ $b->year_level }} - {{ $b->name }}</option>@endforeach</select></div>
    <div class="col-md-3"><select name="teacher_id" class="form-select" required>@foreach($teachers as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach</select></div>
    <div class="col-md-2"><input name="schedule" class="form-control" placeholder="Schedule"></div>
    <div class="col-md-2"><button class="btn btn-primary w-100">Assign &amp; generate code</button></div>
  </form>
  <div class="small text-muted mt-2">All active students in the block are put on the roster automatically.</div>
</div></div>
<table class="table table-sm table-striped bg-white">
  <thead><tr><th>Subject</th><th>Block</th><th>Teacher</th><th>Students</th><th>Loading code</th><th>Claimed</th></tr></thead><tbody>
  @foreach($offerings as $o)
  <tr><td>{{ $o->subject->code }}</td><td>Y{{ $o->block->year_level }} {{ $o->block->name }}</td><td>{{ $o->teacher->name }}</td><td>{{ $o->enrollments_count }}</td><td><code>{{ $o->loading_code }}</code></td><td>{{ $o->claimed_at ? 'Yes' : 'No' }}</td></tr>
  @endforeach
  </tbody>
</table>
@endsection