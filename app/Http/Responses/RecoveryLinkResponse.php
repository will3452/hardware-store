<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;

class RecoveryLinkResponse implements FailedPasswordResetLinkRequestResponse, SuccessfulPasswordResetLinkRequestResponse
{
    public function toResponse($request): JsonResponse|RedirectResponse
    {
        $message = 'If this email has an eligible account, a password reset link has been sent. Check your inbox or contact your platform administrator.';

        return $request->wantsJson() ? response()->json(['message' => $message]) : back()->with('status', $message);
    }
}
