<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformAdministrator
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user && $user->hasPlatformPermission('platform.portal.access'), 403);

        if (! $user->two_factor_secret || ! $user->two_factor_confirmed_at) {
            return redirect()->route('security.edit')->with('status', 'platform-mfa-required');
        }

        $proof = $request->session()->get('platform.mfa', []);

        if (($proof['user_id'] ?? null) !== (string) $user->getKey()
            || ! hash_equals(hash('sha256', $user->two_factor_secret), $proof['fingerprint'] ?? '')) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('status', 'Complete two-factor authentication to open the Admin Portal.');
        }

        return $next($request);
    }
}
