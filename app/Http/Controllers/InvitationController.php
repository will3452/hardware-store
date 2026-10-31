<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Models\User;
use App\Services\Invitations;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class InvitationController extends Controller
{
    public function show(string $token, Invitations $invitations): View
    {
        $invite = $invitations->find($token);

        return view('invitations.show', ['invite' => $invite, 'token' => $token, 'store' => Store::findOrFail($invite->store_id), 'owner' => User::findOrFail($invite->user_id)]);
    }

    public function accept(Request $request, string $token, Invitations $invitations): RedirectResponse
    {
        $invite = $invitations->find($token);
        $data = $request->validate(['password' => [$invite->new_identity ? 'required' : 'nullable', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()]]);
        $user = $invitations->accept($token, $request->user(), $data['password'] ?? null);
        if (! $request->user()) {
            // Use the normal login and MFA flow after activation.
            return redirect()->route('login')->with('status', 'Your store is active. Sign in with your new password.');
        }
        abort_unless(Auth::id() === $user->id, 403);

        return redirect()->route('store.index')->with('status', 'Your store is active.');
    }
}
