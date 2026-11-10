<?php

namespace App\Http\Middleware;

use App\Models\Language;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Content (questions, notes…) is returned in the student's content language,
 * falling back to English where the language is not live or no translation
 * is published. The app may override it per request with X-Content-Locale.
 */
class SetContentLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $wanted = $request->header('X-Content-Locale') ?: $request->user()?->content_locale;
        app()->setLocale(Language::resolve($wanted));

        return $next($request);
    }
}
