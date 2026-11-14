<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class Audit
{
    public static function log(string $action, ?string $description = null): void
    {
        DB::table('activity_logs')->insert([
            'user_id'     => auth()->id(),
            'action'      => $action,
            'description' => $description,
            'ip_address'  => request()->ip(),
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }
}