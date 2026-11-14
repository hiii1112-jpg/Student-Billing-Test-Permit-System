<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class Setting
{
    public static function get(string $key, $default = null)
    {
        return DB::table('settings')->where('key', $key)->value('value') ?? $default;
    }

    public static function set(string $key, $value): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => $key],
            ['value' => $value, 'created_at' => now(), 'updated_at' => now()]
        );
    }
}