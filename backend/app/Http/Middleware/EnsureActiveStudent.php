<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks suspended accounts and keeps "last active" and device "last seen" fresh
 * (written at most every 5 minutes to keep requests cheap).
 */
class EnsureActiveStudent
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user?->isSuspended()) {
            $token = $user->currentAccessToken();
            $token instanceof \Laravel\Sanctum\PersonalAccessToken && $token->delete();

            return response()->json([
                'message' => 'This account is suspended.'.($user->suspended_reason ? ' Reason: '.$user->suspended_reason : ''),
                'code' => 'ACCOUNT_SUSPENDED',
            ], 403);
        }

        if ($user && (! $user->last_active_at || $user->last_active_at->lt(now()->subMinutes(5)))) {
            $user->forceFill(['last_active_at' => now()])->saveQuietly();
            if ($deviceId = $user->currentDeviceId()) {
                $user->devices()->whereKey($deviceId)->update(['last_seen_at' => now()]);
            }
        }

        return $next($request);
    }
}
