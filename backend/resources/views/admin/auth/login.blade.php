@extends('admin.auth.layout')
@section('title', 'Sign in')
@section('content')
    <h1 class="font-display text-2xl font-semibold">Sign in</h1>
    <form method="POST" action="{{ route('login') }}" class="flex flex-col gap-3.5">
        @csrf
        <label class="label">Email
            <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="field">
        </label>
        <label class="label">Password
            <input type="password" name="password" required autocomplete="current-password" class="field">
        </label>
        @error('email')<span class="error">{{ $message }}</span>@enderror
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="remember" class="accent-teal w-4 h-4"> Keep me signed in</label>
        <button class="btn btn-primary w-full h-12">Sign in</button>
        <a href="{{ route('password.request') }}" class="text-sm text-center">Forgot password?</a>
    </form>
@endsection
