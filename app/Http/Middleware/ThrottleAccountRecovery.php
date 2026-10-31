<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ThrottleAccountRecovery
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('POST') && $request->is('forgot-password', 'reset-password')) {
            $key = 'account-recovery:'.$request->ip();
            abort_if(RateLimiter::tooManyAttempts($key, 10), 429, 'Too many attempts. Please try again in a minute.');
            RateLimiter::hit($key, 60);
            if ($request->is('forgot-password') && config('mail.default') === 'log') {
                return back()->with('status', 'Email delivery is not configured. Contact your platform administrator for account recovery.');
            }
        }

        return $next($request);
    }
}
