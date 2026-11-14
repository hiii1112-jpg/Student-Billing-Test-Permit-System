@extends('layouts.app')
@section('content')
<div class="row justify-content-center"><div class="col-md-4">
  <div class="card"><div class="card-body">
    <h5>Change password</h5>
    <form method="POST" action="{{ route('password.update') }}">@csrf
      <input type="password" name="current_password" class="form-control mb-2" placeholder="Current password" required>
      <input type="password" name="password" class="form-control mb-2" placeholder="New password (min 8)" required>
      <input type="password" name="password_confirmation" class="form-control mb-3" placeholder="Confirm new password" required>
      <button class="btn btn-primary">Save</button>
    </form>
  </div></div>
</div></div>
@endsection