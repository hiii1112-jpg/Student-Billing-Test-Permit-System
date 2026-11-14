@php
$map = [
  'green' => ['success', 'PAID'], 'yellow' => ['warning text-dark', 'PROMISSORY'], 'red' => ['danger', 'UNPAID'],
  'completed' => ['success', 'COMPLETED'], 'special_completed' => ['info text-dark', 'SPECIAL DONE'],
  'absent' => ['secondary', 'ABSENT'], 'missed' => ['danger', 'MISSED'], 'pending' => ['light text-dark border', 'PENDING'],
  'verified' => ['success', 'VERIFIED'], 'flagged' => ['warning text-dark', 'FLAGGED'], 'void' => ['dark', 'VOID'],
  'active' => ['warning text-dark', 'ACTIVE'], 'expired' => ['danger', 'EXPIRED'], 'honored' => ['success', 'HONORED'],
];
[$c, $t] = $map[$s] ?? ['secondary', strtoupper($s)];
@endphp
<span class="badge bg-{{ $c }}">{{ $t }}</span>