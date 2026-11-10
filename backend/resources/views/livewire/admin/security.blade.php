<div class="flex flex-col gap-[22px] max-w-3xl">
    <header class="flex flex-col gap-1">
        <span class="eyebrow">{{ $user->email }}</span>
        <h1 class="h-title">Your account &amp; security</h1>
    </header>

    <section class="card card-pad flex flex-col gap-4">
        <div class="flex items-center justify-between gap-3">
            <h2 class="h-section">2-step login</h2>
            <span class="badge {{ $enabled ? 'badge-ok' : 'badge-warn' }}">{{ $enabled ? 'On' : (config('medalbumin.require_admin_2fa') ? 'Required — not set up' : 'Off (optional)') }}</span>
        </div>
        <p class="text-sm text-slate">Use an authenticator app (Google Authenticator, Microsoft Authenticator, Authy) to get a 6-digit code each time you sign in.</p>

        @if (! $enabled && ! $pending)
            <div class="flex flex-wrap items-end gap-2">
                <label class="label flex-1 min-w-[220px]">Confirm your password<input type="password" class="field" wire:model="confirmPassword" autocomplete="current-password"></label>
                <button type="button" class="btn btn-primary" wire:click="enable">Set up 2-step login</button>
            </div>
            @error('confirmPassword')<span class="error">{{ $message }}</span>@enderror
        @elseif ($pending)
            <div class="grid gap-5 sm:grid-cols-[200px_1fr] items-start">
                <div class="rounded-2xl border border-line p-3 bg-white [&_svg]:w-full [&_svg]:h-auto">{!! $user->twoFactorQrCodeSvg() !!}</div>
                <div class="flex flex-col gap-3 text-sm">
                    <span>1. Scan this QR code with your authenticator app.</span>
                    <span class="text-muted">Can’t scan? Enter this key: <span class="mono text-navy break-all">{{ decrypt($user->two_factor_secret) }}</span></span>
                    <span>2. Type the 6-digit code it shows:</span>
                    <div class="flex gap-2">
                        <input class="field mono !w-40 text-lg tracking-[0.3em]" inputmode="numeric" maxlength="6" wire:model="code" wire:keydown.enter="confirm" autocomplete="one-time-code">
                        <button type="button" class="btn btn-teal" wire:click="confirm">Turn on</button>
                    </div>
                    @error('code')<span class="error">{{ $message }}</span>@enderror
                </div>
            </div>
        @else
            @if ($showRecovery)
                <div class="rounded-2xl bg-page p-4 flex flex-col gap-2">
                    <strong class="text-sm">Recovery codes — save them somewhere safe. Each works once if you lose your phone.</strong>
                    <div class="grid grid-cols-2 gap-1 mono text-sm">@foreach ($user->recoveryCodes() as $c)<span>{{ $c }}</span>@endforeach</div>
                </div>
            @endif
            <div class="flex flex-wrap items-end gap-2">
                <label class="label flex-1 min-w-[220px]">Confirm your password<input type="password" class="field" wire:model="confirmPassword" autocomplete="current-password"></label>
                <button type="button" class="btn btn-light" wire:click="regenerate">New recovery codes</button>
                @unless (config('medalbumin.require_admin_2fa'))
                    <button type="button" class="btn btn-danger" wire:click="disable">Turn off</button>
                @endunless
            </div>
            @error('confirmPassword')<span class="error">{{ $message }}</span>@enderror
        @endif
    </section>

    <form wire:submit="changePassword" class="card card-pad flex flex-col gap-3">
        <h2 class="h-section">Change password</h2>
        <label class="label">Current password<input type="password" class="field" wire:model="current_password" autocomplete="current-password">@error('current_password')<span class="error">{{ $message }}</span>@enderror</label>
        <div class="grid sm:grid-cols-2 gap-3">
            <label class="label">New password<input type="password" class="field" wire:model="password" autocomplete="new-password">@error('password')<span class="error">{{ $message }}</span>@enderror</label>
            <label class="label">Repeat new password<input type="password" class="field" wire:model="password_confirmation" autocomplete="new-password"></label>
        </div>
        <span class="text-xs text-muted">At least 10 characters with upper and lower case letters and a number.</span>
        <button class="btn btn-primary self-end">Change password</button>
    </form>
</div>
