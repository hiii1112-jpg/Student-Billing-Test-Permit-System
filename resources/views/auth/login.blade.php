@extends('layouts.app')
@section('content')
<div class="row justify-content-center"><div class="col-md-4">
  <div class="card shadow-sm"><div class="card-body">
    <h4 class="mb-3">Student Billing &amp; Exam Clearance</h4>
    <form method="POST" action="{{ route('login') }}">@csrf
      <div class="mb-3"><label class="form-label">Username / Student No.</label>
        <input name="username" value="{{ old('username') }}" class="form-control" required autofocus></div>
      <div class="mb-3"><label class="form-label">Password</label>
        <input type="password" name="password" class="form-control" required></div>
      <button class="btn btn-primary w-100">Login</button>
    </form>
    <div class="text-center mt-3"><a href="{{ route('activate') }}">Activate account (new student)</a></div>
  </div></div>
</div></div>
@endsection