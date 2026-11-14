@extends('layouts.app')
@section('content')
<div class="bg-white border p-4 mx-auto" style="max-width:640px">
  <h4 class="text-center">EXAM PERMIT — {{ $period->label }}</h4>
  <p class="text-center text-muted">{{ $term->school_year }} · Semester {{ $term->semester }}</p>
  <table class="table table-sm table-borderless">
    <tr><td>Student</td><td><strong>{{ $student->full_name }}</strong> ({{ $student->student_no }})</td></tr>
    <tr><td>Program / Year</td><td>{{ $student->program->code }} / {{ $student->year_level }}</td></tr>
    <tr><td>Clearance</td><td>@include('partials.badge', ['s' => $status])</td></tr>
    @if($note)<tr><td>Valid until</td><td class="text-danger">{{ $note->due_date->format('M d, Y') }} (promissory)</td></tr>@endif
    <tr><td>Permit no.</td><td><code>PRM-{{ $term->id }}-{{ $periodNo }}-{{ $student->student_no }}</code></td></tr>
  </table>
  <strong>Subjects</strong>
  <ul>@foreach($enrollments as $e)<li>{{ $e->classOffering->subject->code }} - {{ $e->classOffering->subject->title }}</li>@endforeach</ul>
</div>
<div class="text-center mt-3 no-print"><button class="btn btn-primary" onclick="window.print()">Print / Save as PDF</button></div>
@endsection