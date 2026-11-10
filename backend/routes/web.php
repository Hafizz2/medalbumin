<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AdminFileController;
use App\Http\Controllers\TelegramAuthController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\SiteController;
use App\Livewire\Admin;
use Illuminate\Support\Facades\Route;

// Public website (SEO landing page + legal pages required by the app stores)
Route::get('/', [SiteController::class, 'home'])->name('home');
Route::get('/pricing', [SiteController::class, 'pricing'])->name('pricing');
Route::get('/privacy', [SiteController::class, 'privacy'])->name('legal.privacy');
Route::get('/terms', [SiteController::class, 'terms'])->name('legal.terms');
Route::get('/delete-account', [SiteController::class, 'deleteAccount'])->name('legal.delete-account');
Route::post('/delete-account/code', [SiteController::class, 'deleteAccountCode'])->middleware('throttle:otp')->name('legal.delete-account.code');
Route::post('/delete-account', [SiteController::class, 'deleteAccountConfirm'])->middleware('throttle:auth')->name('legal.delete-account.confirm');
Route::get('/sitemap.xml', [SiteController::class, 'sitemap'])->name('sitemap');

// "Continue with Telegram" (website and mobile app)
Route::get('/auth/telegram/redirect', [TelegramAuthController::class, 'redirect'])->middleware('throttle:auth')->name('auth.telegram.redirect');
Route::get('/auth/telegram/callback', [TelegramAuthController::class, 'callback'])->name('auth.telegram.callback');
Route::post('/webhooks/telegram-gateway', [TelegramAuthController::class, 'gatewayReport'])->name('webhooks.telegram-gateway');

// Student account: subscriptions are bought on the website, not inside the apps.
Route::middleware('guest')->group(function () {
    Route::get('/account/login', [AccountController::class, 'login'])->name('account.login');
    Route::post('/account/login/code', [AccountController::class, 'sendCode'])->middleware('throttle:otp')->name('account.login.code');
    Route::post('/account/login/verify', [AccountController::class, 'verifyCode'])->middleware('throttle:auth')->name('account.login.verify');
});
Route::middleware('auth')->group(function () {
    Route::get('/account', [AccountController::class, 'show'])->name('account');
    Route::get('/account/plans/{plan}', [AccountController::class, 'checkout'])->name('account.checkout');
    Route::post('/account/plans/{plan}', [AccountController::class, 'pay'])->middleware('throttle:posts')->name('account.pay');
    Route::get('/account/payments/{payment}/returned', [AccountController::class, 'paymentReturned'])->name('account.payment-returned');
    Route::post('/account/logout', [AccountController::class, 'logout'])->name('account.logout');
});

// Payments
Route::post('/webhooks/chapa', [PaymentWebhookController::class, 'chapa'])->name('webhooks.chapa');
Route::get('/payments/return', [PaymentWebhookController::class, 'return'])->name('payments.return');

// Admin console (sign-in routes come from Fortify under /admin)
Route::prefix('admin')->name('admin.')->middleware(['auth', 'staff'])->group(function () {
    Route::get('/', Admin\Dashboard::class)->name('dashboard');
    Route::get('/questions', Admin\Questions::class)->name('questions');
    Route::get('/exams', Admin\Exams::class)->name('exams');
    Route::get('/videos', Admin\Videos::class)->name('videos');
    Route::get('/notes', Admin\Notes::class)->name('notes');
    Route::get('/library', Admin\Library::class)->name('library');
    Route::get('/match', Admin\MatchData::class)->name('match');
    Route::get('/users', Admin\Users::class)->name('users');
    Route::get('/payments', Admin\Payments::class)->name('payments');
    Route::get('/reports', Admin\Reports::class)->name('reports');
    Route::get('/push', Admin\Push::class)->name('push');
    Route::get('/community', Admin\Community::class)->name('community');
    Route::get('/analytics', Admin\Analytics::class)->name('analytics');
    Route::get('/languages', Admin\Languages::class)->name('languages');
    Route::get('/languages/{code}', Admin\LanguageStrings::class)->where('code', '[a-z]{2,3}')->name('languages.strings');
    Route::get('/settings', Admin\Settings::class)->name('settings');
    Route::get('/security', Admin\Security::class)->name('security');

    // Private uploads, streamed only to staff with the right permission
    Route::get('/payments/{payment}/receipt', [AdminFileController::class, 'receipt'])->name('payments.receipt');
    Route::get('/verifications/{verification}/image', [AdminFileController::class, 'verification'])->name('verifications.image');
});
