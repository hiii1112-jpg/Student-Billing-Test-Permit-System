<?php

namespace App\Http\Middleware;

use App\Support\Setting;
use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $user = $request->user();

        if (! $user || ! $user->is_active) {
            return $this->kick($request, 'Your account is inactive.');
        }

        $held = [$user->role];
        if ($user->isHead()) {
            $held[] = 'head';
        }
        if (! array_intersect($held, $roles)) {
            abort(403);
        }

        if ($user->role !== 'registrar' && Setting::get('maintenance') === '1') {
            return $this->kick($request, 'System is under maintenance. Please try again later.');
        }

        return $next($request);
    }

    private function kick(Request $request, string $message)
    {
        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors(['username' => $message]);
    }
}