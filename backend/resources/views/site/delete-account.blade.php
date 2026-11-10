@extends('site.layout')
@section('title', 'Delete your MedAlbumin account')
@section('description', 'Permanently delete your MedAlbumin account and personal data.')
@section('content')
<section class="max-w-lg mx-auto px-4 pt-14 flex flex-col gap-5">
    <h1 class="font-display text-4xl font-semibold tracking-[-0.03em]">Delete your account</h1>

    @if ($done)
        <div class="card card-pad bg-teal-wash border-teal-soft">Your account and personal data have been deleted. Thank you for studying with MedAlbumin.</div>
    @else
        <p class="text-slate">You can also do this in the app: <strong>Profile → Delete account</strong>. Deleting removes your profile, progress, notes, devices and uploaded images. Payment records are kept without your personal details for accounting. This cannot be undone.</p>

        @if (session('status'))<div class="card px-4 py-3 bg-teal-wash text-teal-ink text-sm">{{ session('status') }}</div>@endif

        <form method="POST" action="{{ route('legal.delete-account.code') }}" class="card card-pad flex flex-col gap-3">
            @csrf
            <label class="label">1. Phone number on your account
                <span class="flex gap-2"><span class="field !w-auto flex items-center">+251</span><input name="phone" value="{{ old('phone', $phone ? substr($phone, 4) : '') }}" class="field" inputmode="tel" placeholder="91 234 5678" required></span>
            </label>
            @error('phone')<span class="error">{{ $message }}</span>@enderror
            <button class="btn btn-light">Send code by SMS</button>
        </form>

        @if ($phone)
            <form method="POST" action="{{ route('legal.delete-account.confirm') }}" class="card card-pad flex flex-col gap-3">
                @csrf
                <label class="label">2. 6-digit code<input name="code" inputmode="numeric" maxlength="6" class="field mono text-lg tracking-[0.3em]" required autocomplete="one-time-code"></label>
                @error('code')<span class="error">{{ $message }}</span>@enderror
                <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="confirm" value="1" class="accent-teal w-4 h-4 mt-0.5"> I understand my account and data will be permanently deleted.</label>
                @error('confirm')<span class="error">{{ $message }}</span>@enderror
                <button class="btn btn-danger">Delete my account</button>
            </form>
        @endif
        <p class="text-sm text-muted">Signed up with Google or Apple and no phone number? Email <a href="mailto:{{ config('services.support.email') }}">{{ config('services.support.email') }}</a> from that address, or delete from the app.</p>
    @endif
</section>
@endsection
