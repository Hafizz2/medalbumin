<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Sign in') · MedAlbumin Admin</title>
    <link rel="icon" type="image/png" href="{{ asset('images/medalbumin-mark.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;600;700&family=Geist:wght@400;500;600&family=Geist+Mono:wght@500&display=swap">
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-navy flex items-center justify-center p-4 relative overflow-hidden">
    <svg aria-hidden="true" class="absolute inset-x-0 top-1/3 w-full" height="200" viewBox="0 0 1200 200" preserveAspectRatio="none">
        <path d="M0 120 H420 L460 70 L500 170 L545 20 L590 190 L630 120 H1200" fill="none" stroke="#1B4A8A" stroke-width="2"/>
        <path d="M0 120 H420 L460 70 L500 170 L545 20 L590 190 L630 120 H720" fill="none" stroke="#14BCC4" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
    <main class="relative w-full max-w-[420px] bg-page rounded-[28px] p-7 flex flex-col gap-5 shadow-2xl">
        <div class="flex items-center gap-2.5">
            <span class="w-12 h-12 rounded-[14px] bg-white border border-line flex items-center justify-center"><img src="{{ asset('images/medalbumin-mark.png') }}" alt="MedAlbumin logo" class="w-8 h-9 object-contain"></span>
            <span class="flex flex-col"><span class="font-display text-[22px] font-bold"><span class="text-navy">Med</span><span class="text-teal">Albumin</span></span><span class="text-xs text-muted">Admin console</span></span>
        </div>
        @if (session('status'))<div class="rounded-2xl bg-teal-wash text-teal-ink text-sm px-4 py-3">{{ session('status') }}</div>@endif
        @yield('content')
    </main>
</body>
</html>
