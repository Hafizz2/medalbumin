@extends('site.layout')
@section('title', 'Sign in · MedAlbumin')
@section('content')
<section class="max-w-md mx-auto px-4 pt-14 flex flex-col gap-5">
    <div class="flex flex-col gap-1">
        <h1 class="font-display text-4xl font-semibold tracking-[-0.03em]">Sign in</h1>
        <p class="text-slate">Use the same Telegram or phone number as in the MedAlbumin app.</p>
    </div>

    @if (session('status'))<div class="card px-4 py-3 bg-teal-wash text-teal-ink text-sm">{{ session('status') }}</div>@endif
    @error('telegram')<div class="card px-4 py-3 bg-danger-bg text-warn text-sm">{{ $message }}</div>@enderror

    @if ($telegramReady)
        <a href="{{ route('auth.telegram.redirect', ['intent' => 'web']) }}" class="btn h-14 rounded-2xl text-base text-white hover:text-white" style="background:#229ED9">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M21.9 4.3 18.7 19.4c-.2 1.1-.9 1.3-1.8.8l-4.9-3.6-2.4 2.3c-.3.3-.5.5-1 .5l.3-5 9.2-8.3c.4-.4-.1-.6-.6-.2L6.1 13l-4.9-1.5c-1.1-.3-1.1-1.1.2-1.6L20.5 2.5c.9-.3 1.7.2 1.4 1.8z"/></svg>
            Continue with Telegram
        </a>
        <div class="flex items-center gap-3 text-xs text-muted"><span class="flex-1 h-px bg-line"></span>or get a code in Telegram<span class="flex-1 h-px bg-line"></span></div>
    @endif

    <form method="POST" action="{{ route('account.login.code') }}" class="card card-pad flex flex-col gap-3">
        @csrf
        <label class="label">Phone number (the one your Telegram uses)
            <span class="flex gap-2"><span class="field !w-auto flex items-center">+251</span><input name="phone" value="{{ old('phone', $phone ? substr($phone, 4) : '') }}" class="field" inputmode="tel" placeholder="91 234 5678" required autocomplete="tel-national"></span>
        </label>
        @error('phone')<span class="error">{{ $message }}</span>@enderror
        <button class="btn btn-light">Send code to my Telegram</button>
    </form>

    @if ($phone)
        <form method="POST" action="{{ route('account.login.verify') }}" class="card card-pad flex flex-col gap-3">
            @csrf
            <label class="label">6-digit code from Telegram<input name="code" inputmode="numeric" maxlength="6" class="field mono text-lg tracking-[0.3em]" required autofocus autocomplete="one-time-code"></label>
            @error('code')<span class="error">{{ $message }}</span>@enderror
            <button class="btn btn-primary">Sign in</button>
        </form>
    @endif

    <p class="text-xs text-muted">By continuing you agree to the <a href="{{ route('legal.terms') }}">Terms</a> and <a href="{{ route('legal.privacy') }}">Privacy policy</a>.</p>
</section>
@endsection
