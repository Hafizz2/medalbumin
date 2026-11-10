@extends('admin.auth.layout')
@section('title', 'Confirm password')
@section('content')
    <h1 class="font-display text-2xl font-semibold">Confirm your password</h1>
    <form method="POST" action="{{ route('password.confirm') }}" class="flex flex-col gap-3.5">
        @csrf
        <label class="label">Password<input type="password" name="password" required autofocus autocomplete="current-password" class="field"></label>
        @error('password')<span class="error">{{ $message }}</span>@enderror
        <button class="btn btn-primary w-full h-12">Confirm</button>
    </form>
@endsection
