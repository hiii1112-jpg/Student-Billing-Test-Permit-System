<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class Notify
{
    public static function to(int $userId, string $message): void
    {
        DB::table('user_notifications')->insert([
            'user_id'    => $userId,
            'message'    => $message,
            'is_read'    => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}