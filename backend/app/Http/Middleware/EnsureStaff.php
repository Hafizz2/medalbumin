<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin console guard: staff only, and two-step login must be set up
 * ("2-step login required for all admin accounts").
 */
class EnsureStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }
        if (! $user->is_staff) {
            // A student signed in on the website: send them to their own account page.
            return redirect()->route('account');
        }
        if ($user->isSuspended()) {
            auth()->logout();

            return redirect()->route('login');
        }

        $needs2fa = config('medalbumin.require_admin_2fa') && ! $user->two_factor_confirmed_at;
        if ($needs2fa && ! $request->routeIs('admin.security', 'admin.security.*', 'two-factor.*', 'password.confirm*', 'logout', 'livewire.*')) {
            return redirect()->route('admin.security')->with('status', 'Set up two-step login to continue.');
        }

        return $next($request);
    }
}
