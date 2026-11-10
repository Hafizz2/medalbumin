<?php

namespace App\Providers;

use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Fortify;

/**
 * Fortify handles admin-console sign-in (email + password + authenticator app).
 * Students sign in from the mobile app via the API instead.
 */
class FortifyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);

        // Only active staff accounts may sign in to the console.
        Fortify::authenticateUsing(function (Request $request) {
            $user = User::query()->where('email', Str::lower((string) $request->input('email')))->first();

            return $user && $user->is_staff && ! $user->isSuspended() && $user->password && Hash::check($request->input('password'), $user->password)
                ? $user
                : null;
        });

        Fortify::loginView(fn () => view('admin.auth.login'));
        Fortify::twoFactorChallengeView(fn () => view('admin.auth.two-factor-challenge'));
        Fortify::requestPasswordResetLinkView(fn () => view('admin.auth.forgot-password'));
        Fortify::resetPasswordView(fn (Request $request) => view('admin.auth.reset-password', ['request' => $request]));
        Fortify::confirmPasswordView(fn () => view('admin.auth.confirm-password'));

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', fn (Request $request) => Limit::perMinute(5)->by($request->session()->get('login.id')));
    }
}
