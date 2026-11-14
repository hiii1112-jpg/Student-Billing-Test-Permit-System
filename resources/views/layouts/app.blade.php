<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title', 'Billing & Exam Clearance')</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>@media print { .no-print { display: none !important; } body { background: #fff !important; } }</style>
</head>
<body class="bg-light">
@auth
@php
    $u = auth()->user();
    $links = match ($u->role) {
        'registrar' => ['registrar.dashboard' => 'Dashboard', 'registrar.students' => 'Students', 'registrar.staff' => 'Staff', 'registrar.setup' => 'Setup', 'registrar.loads' => 'Teacher Loads', 'registrar.terms' => 'Semester', 'registrar.logs' => 'Logs'],
        'cashier'   => ['cashier.dashboard' => 'Dashboard'] + ($u->isHead() ? ['cashier.report' => 'Daily Report'] : []),
        'teacher'   => ['teacher.home' => 'My Classes'],
        default     => ['student.dashboard' => 'Dashboard'],
    };
@endphp
<nav class="navbar navbar-expand navbar-dark bg-primary no-print">
  <div class="container-fluid">
    <a class="navbar-brand" href="{{ route('home') }}">Billing &amp; Clearance</a>
    <ul class="navbar-nav me-auto">
      @foreach($links as $r => $label)
        <li class="nav-item"><a class="nav-link" href="{{ route($r) }}">{{ $label }}</a></li>
      @endforeach
    </ul>
    <div class="dropdown me-3">
      <button id="nbtn" class="btn btn-sm btn-light" data-bs-toggle="dropdown">🔔 <span id="nbadge" class="badge bg-danger" style="display:none">0</span></button>
      <ul id="nlist" class="dropdown-menu dropdown-menu-end" style="min-width:320px"></ul>
    </div>
    <span class="navbar-text me-3">{{ $u->name }} ({{ $u->role }}{{ $u->isHead() ? ' - head' : '' }})</span>
    <a class="btn btn-sm btn-outline-light me-2" href="{{ route('password') }}">Password</a>
    <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-sm btn-warning">Logout</button></form>
  </div>
</nav>
@endauth

<div class="container py-3">
  @if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
  @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
  @yield('content')
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@auth
<script>
async function loadNotes() {
  try {
    const r = await fetch('{{ route('notifications') }}', {headers: {'Accept': 'application/json'}});
    if (!r.ok) return;
    const d = await r.json();
    const b = document.getElementById('nbadge');
    b.textContent = d.unread; b.style.display = d.unread > 0 ? 'inline-block' : 'none';
    const l = document.getElementById('nlist'); l.innerHTML = '';
    if (!d.items.length) { const li = document.createElement('li'); li.className = 'dropdown-item text-muted'; li.textContent = 'No notifications'; l.appendChild(li); }
    d.items.forEach(i => { const li = document.createElement('li'); li.className = 'dropdown-item small text-wrap' + (i.is_read ? ' text-muted' : ' fw-bold'); li.textContent = i.message; l.appendChild(li); });
  } catch (e) {}
}
loadNotes(); setInterval(loadNotes, 10000);
document.getElementById('nbtn').addEventListener('click', () => {
  fetch('{{ route('notifications.read') }}', {method: 'POST', headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'}}).then(() => setTimeout(loadNotes, 2000));
});
</script>
@endauth
@hasSection('autorefresh')
<script>setTimeout(() => location.reload(), {{ (int) $__env->yieldContent('autorefresh') * 1000 }});</script>
@endif
</body>
</html>