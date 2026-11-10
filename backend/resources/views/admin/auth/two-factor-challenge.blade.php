@extends('admin.auth.layout')
@section('title', '2-step login')
@section('content')
    <h1 class="font-display text-2xl font-semibold">2-step login</h1>
    <form method="POST" action="{{ url('admin/two-factor-challenge') }}" class="flex flex-col gap-3.5">
        @csrf
        <label class="label" id="otp-field">Code from your authenticator app
            <input name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" class="field mono text-lg tracking-[0.3em]" autofocus>
        </label>
        <label class="label hidden" id="recovery-field">Recovery code
            <input name="recovery_code" autocomplete="off" class="field mono">
        </label>
        @error('code')<span class="error">{{ $message }}</span>@enderror
        @error('recovery_code')<span class="error">{{ $message }}</span>@enderror
        <button class="btn btn-primary w-full h-12">Continue</button>
        <button type="button" class="text-sm text-teal" id="toggle-recovery">Lost your phone? Use a recovery code</button>
    </form>
    <script>
        document.getElementById('toggle-recovery').addEventListener('click', function () {
            const otp = document.getElementById('otp-field'), rec = document.getElementById('recovery-field');
            const useRecovery = rec.classList.contains('hidden');
            otp.classList.toggle('hidden', useRecovery);
            rec.classList.toggle('hidden', !useRecovery);
            (useRecovery ? rec : otp).querySelector('input').focus();
            this.textContent = useRecovery ? 'Use authenticator code' : 'Lost your phone? Use a recovery code';
        });
    </script>
@endsection
