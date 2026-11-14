@extends('layouts.app')
@section('content')
<div class="row justify-content-center"><div class="col-md-5">
  <div class="card shadow-sm"><div class="card-body">
    <h4 class="mb-3">Activate Account</h4>
    <p class="text-muted small">Use the 1st COE code given by the Registrar.</p>
    <form method="POST" action="{{ route('activate') }}">@csrf
      <div class="mb-2"><label class="form-label">Student No.</label><input name="student_no" value="{{ old('student_no') }}" class="form-control" required></div>
      <div class="mb-2"><label class="form-label">COE Code</label><input name="code" class="form-control" placeholder="COE-XXXXXXXX" required></div>
      <div class="mb-2"><label class="form-label">New password (min 8)</label><input type="password" name="password" class="form-control" required></div>
      <div class="mb-3"><label class="form-label">Confirm password</label><input type="password" name="password_confirmation" class="form-control" required></div>
      <button class="btn btn-success w-100">Activate</button>
    </form>
    <div class="text-center mt-3"><a href="{{ route('login') }}">Back to login</a></div>
  </div></div>
</div></div>
@endsection