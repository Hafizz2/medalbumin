@extends('admin.auth.layout')
@section('title', 'New password')
@section('content')
    <h1 class="font-display text-2xl font-semibold">Choose a new password</h1>
    <form method="POST" action="{{ route('password.update') }}" class="flex flex-col gap-3.5">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <label class="label">Email<input type="email" name="email" value="{{ old('email', $request->email) }}" required class="field"></label>
        <label class="label">New password<input type="password" name="password" required autocomplete="new-password" class="field"></label>
        <label class="label">Repeat password<input type="password" name="password_confirmation" required autocomplete="new-password" class="field"></label>
        @error('email')<span class="error">{{ $message }}</span>@enderror
        @error('password')<span class="error">{{ $message }}</span>@enderror
        <button class="btn btn-primary w-full h-12">Save password</button>
    </form>
@endsection
