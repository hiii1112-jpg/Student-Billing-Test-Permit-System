@extends('layouts.app')
@if($editable)@section('autorefresh', '15')@endif
@section('content')
@php
  $paid = $rows->where('status', 'green')->count();
  $prom = $rows->where('status', 'yellow')->count();
  $unpaid = $rows->where('status', 'red')->count();
  $special = $rows->filter(fn ($r) => $r->record && in_array($r->record->status, ['missed', 'absent']));
@endphp
<div class="d-flex justify-content-between align-items-start">
  <h4>{{ $offering->subject->code }} – {{ $offering->subject->title }}
    <small class="text-muted">Y{{ $offering->block->year_level }} {{ $offering->block->name }} · {{ $offering->schoolTerm->school_year }} Sem {{ $offering->schoolTerm->semester }}</small></h4>
  <a href="{{ route('teacher.home') }}" class="btn btn-outline-secondary btn-sm">Back</a>
</div>
@unless($editable)<div class="alert alert-secondary">Archived semester — read-only history.</div>@endunless

<ul class="nav nav-pills mb-3">
@foreach($periods as $p)<li class="nav-item"><a class="nav-link {{ $p->id == $period->id ? 'active' : '' }}" href="{{ route('teacher.class', ['offering' => $offering, 'period' => $p->period_no]) }}">{{ $p->name }} exam</a></li>@endforeach
</ul>

<div class="row g-2 mb-3 text-center">
  <div class="col"><div class="card"><div class="card-body py-2">Total<div class="fs-4">{{ $rows->count() }}</div></div></div></div>
  <div class="col"><div class="card border-success"><div class="card-body py-2">GREEN (paid)<div class="fs-4 text-success">{{ $paid }}</div></div></div></div>
  <div class="col"><div class="card border-warning"><div class="card-body py-2">YELLOW (promissory)<div class="fs-4 text-warning">{{ $prom }}</div></div></div></div>
  <div class="col"><div class="card border-danger"><div class="card-body py-2">RED (unpaid)<div class="fs-4 text-danger">{{ $unpaid }}</div></div></div></div>
  <div class="col"><div class="card"><div class="card-body py-2">Completed<div class="fs-4">{{ $rows->filter(fn ($r) => $r->record && in_array($r->record->status, ['completed', 'special_completed']))->count() }}</div></div></div></div>
</div>

@if($editable)
<div class="row g-2 mb-3">
  <div class="col-md-5">
    <form method="POST" action="{{ route('teacher.bulk', $offering) }}" onsubmit="return confirm('BULK OK: mark all GREEN/YELLOW students as completed? RED and absent students are excluded.')">@csrf
      <input type="hidden" name="exam_period_id" value="{{ $period->id }}">
      <button class="btn btn-success">BULK OK — mark remaining PAID/PROMISSORY as completed</button></form>
  </div>
  <div class="col-md-5 ms-auto">
    <form method="POST" action="{{ route('teacher.add', $offering) }}" class="d-flex gap-2">@csrf
      <input name="student_no" class="form-control" placeholder="Add student by Student ID" required><button class="btn btn-outline-primary">Add</button></form>
  </div>
</div>
@endif

<table class="table table-sm bg-white">
  <thead><tr><th>Student No.</th><th>Name</th><th>Clearance</th><th>Exam status</th><th>Audit proof</th>@if($editable)<th></th>@endif</tr></thead><tbody>
  @foreach($rows as $r)
  <tr class="{{ $r->status === 'red' ? 'table-danger' : '' }}">
    <td>{{ $r->student->student_no }}</td><td>{{ $r->student->full_name }}</td>
    <td>@include('partials.badge', ['s' => $r->status]){{ $r->status === 'red' ? ' — step outside' : '' }}</td>
    <td>@include('partials.badge', ['s' => optional($r->record)->status ?? 'pending'])</td>
    <td class="small text-muted">@if($r->record && $r->record->marked_at){{ $r->record->markedBy->name ?? '' }} · {{ $r->record->marked_at->format('M d h:i A') }} · {{ strtoupper($r->record->clearance_status ?? '-') }}@endif</td>
    @if($editable)
    <td class="d-flex gap-1">
      <form method="POST" action="{{ route('teacher.absent', [$offering, $r->enrollment]) }}">@csrf<input type="hidden" name="exam_period_id" value="{{ $period->id }}">
        <button class="btn btn-sm btn-outline-secondary">{{ optional($r->record)->status === 'absent' ? 'Undo absent' : 'Mark absent' }}</button></form>
      <form method="POST" action="{{ route('teacher.remove', [$offering, $r->enrollment]) }}" onsubmit="return confirm('Remove from roster?')">@csrf @method('DELETE')
        <button class="btn btn-sm btn-outline-danger">Remove</button></form>
    </td>
    @endif
  </tr>
  @endforeach
  </tbody>
</table>

<h5 class="mt-4">Next-week special exam (missed / absent)</h5>
<table class="table table-sm bg-white"><thead><tr><th>Student</th><th>Exam status</th><th>Current clearance</th><th></th></tr></thead><tbody>
@forelse($special as $r)
<tr><td>{{ $r->student->full_name }}</td><td>@include('partials.badge', ['s' => $r->record->status])</td><td>@include('partials.badge', ['s' => $r->status])</td>
  <td>@if($editable)
    <form method="POST" action="{{ route('teacher.special', [$offering, $r->enrollment]) }}">@csrf<input type="hidden" name="exam_period_id" value="{{ $period->id }}">
      <button class="btn btn-sm btn-primary" {{ $r->status === 'red' ? 'disabled' : '' }}>Mark special exam completed</button></form>@endif</td></tr>
@empty<tr><td colspan="4" class="text-muted">No missed or absent students for this exam.</td></tr>@endforelse
</tbody></table>
@endsection