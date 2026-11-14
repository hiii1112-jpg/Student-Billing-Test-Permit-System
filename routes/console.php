<?php

use App\Models\PromissoryNote;
use App\Support\Notify;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('promissory:expire', function () {
    $notes = PromissoryNote::with('studentTerm.student')
        ->where('status', 'active')
        ->whereDate('due_date', '<', today())
        ->get();

    foreach ($notes as $n) {
        $n->update(['status' => 'expired']);
        Notify::to($n->studentTerm->student->user_id, 'Your promissory note expired. Settle your balance to restore exam clearance.');
    }

    $this->info($notes->count() . ' promissory note(s) expired.');
})->purpose('Expire overdue promissory notes');

Schedule::command('promissory:expire')->daily();
