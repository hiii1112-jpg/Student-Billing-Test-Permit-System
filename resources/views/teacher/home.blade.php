@extends('layouts.app')
@section('content')
<h4>My Classes</h4>
<div class="row g-3 mb-3">
  <div class="col-md-4"><div class="card"><div class="card-body">
    <form method="POST" action="{{ route('teacher.claim') }}" class="d-flex gap-2">@csrf
      <input name="loading_code" class="form-control" placeholder="Subject loading code" required><button class="btn btn-primary">Add</button></form>
  </div></div></div>
  <div class="col-md-8"><form method="GET" class="row g-2">
    <div class="col-md-5"><select name="term" class="form-select"><option value="">All school years / semesters</option>@foreach($terms as $t)<option value="{{ $t->id }}" @selected(request('term') == $t->id)>{{ $t->school_year }} Sem {{ $t->semester }}</option>@endforeach</select></div>
    <div class="col-md-3"><select name="year_level" class="form-select"><option value="">All year levels</option>@for($i = 1; $i <= 4; $i++)<option value="{{ $i }}" @selected(request('year_level') == $i)>Year {{ $i }}</option>@endfor</select></div>
    <div class="col"><button class="btn btn-secondary">Filter</button></div>
  </form></div>
</div>

@php [$now, $archive] = $offerings->partition(fn ($o) => $current && $o->school_term_id == $current->id); @endphp
@foreach(['Current semester' => $now, 'Archived (past semesters)' => $archive] as $title => $list)
<h5 class="mt-3">{{ $title }}</h5>
<table class="table table-sm bg-white"><thead><tr><th>Subject</th><th>Block</th><th>Term</th><th>Students</th><th></th></tr></thead><tbody>
@forelse($list as $o)
<tr><td>{{ $o->subject->code }} - {{ $o->subject->title }}</td><td>Y{{ $o->block->year_level }} {{ $o->block->name }}</td><td>{{ $o->schoolTerm->school_year }} S{{ $o->schoolTerm->semester }}</td><td>{{ $o->enrollments_count }}</td>
  <td><a class="btn btn-sm btn-primary" href="{{ route('teacher.class', $o) }}">Open class</a></td></tr>
@empty<tr><td colspan="5" class="text-muted">None.</td></tr>@endforelse
</tbody></table>
@endforeach
@endsection