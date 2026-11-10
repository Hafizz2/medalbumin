<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Otp\LogChannel;
use App\Services\Otp\OtpChannel;
use App\Services\Otp\TelegramGatewayChannel;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OtpChannel::class, fn () => match (config('services.otp.driver')) {
            'telegram' => new TelegramGatewayChannel(
                (string) config('services.telegram_gateway.token'),
                config('services.telegram_gateway.sender_username'),
                config('services.telegram_gateway.reports') && str_starts_with((string) config('app.url'), 'https://') ? route('webhooks.telegram-gateway') : null,
            ),
            default => new LogChannel,
        });
    }

    public function boot(): void
    {
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
        // Every endpoint returns plain objects ({...} not {data: {...}}) for a predictable app client.
        JsonResource::withoutWrapping();

        // Short, stable names in polymorphic columns (user_notes.notable_type).
        Relation::morphMap([
            'note' => \App\Models\Note::class,
            'question' => \App\Models\Question::class,
            'video' => \App\Models\Video::class,
            'user' => User::class,
        ]);

        // Super admins can do everything.
        Gate::before(fn (User $user) => $user->hasRole('Super admin') ? true : null);

        RateLimiter::for('api', fn (Request $r) => Limit::perMinute(120)->by($r->user()?->id ?: $r->ip()));
        RateLimiter::for('otp', fn (Request $r) => [
            Limit::perHour(5)->by('otp-phone:'.preg_replace('/\D+/', '', (string) $r->input('phone'))),
            Limit::perHour(20)->by('otp-ip:'.$r->ip()),
        ]);
        RateLimiter::for('email', fn (Request $r) => [
            Limit::perHour(6)->by('email:'.mb_strtolower(trim((string) $r->input('email')))),
            Limit::perHour(20)->by('email-ip:'.$r->ip()),
        ]);
        RateLimiter::for('auth', fn (Request $r) => Limit::perMinute(10)->by($r->ip()));
        RateLimiter::for('posts', fn (Request $r) => Limit::perMinute(6)->by($r->user()?->id ?: $r->ip()));

        Scramble::configure()
            ->routes(fn ($route) => str_starts_with($route->uri, 'api/v1'))
            ->withDocumentTransformers(function (OpenApi $openApi) {
                $openApi->secure(SecurityScheme::http('bearer'));
            });

        Gate::define('viewApiDocs', fn (?User $user) => $this->app->isLocal() || ($user?->is_staff ?? false));
    }
}
