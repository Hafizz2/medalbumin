@extends('admin.auth.layout')
@section('title', 'Reset password')
@section('content')
    <h1 class="font-display text-2xl font-semibold">Reset password</h1>
    <p class="text-sm text-muted">Enter your admin email and we will send you a reset link.</p>
    <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-3.5">
        @csrf
        <label class="label">Email<input type="email" name="email" value="{{ old('email') }}" required autofocus class="field"></label>
        @error('email')<span class="error">{{ $message }}</span>@enderror
        <button class="btn btn-primary w-full h-12">Send reset link</button>
        <a href="{{ route('login') }}" class="text-sm text-center">Back to sign in</a>
    </form>
@endsection
