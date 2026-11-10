<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'MedAlbumin — Past papers, mock exams & notes for Ethiopian medical students')</title>
    <meta name="description" content="@yield('description', 'Pass your medical exit, qualification and residency exams with MedAlbumin: past exam papers with explanations, national grand mocks, flashcards, official notes, video lessons and a residency match predictor. In English, Amharic, Afaan Oromoo, Somali and Arabic.')">
    <link rel="canonical" href="{{ url()->current() }}">
    <meta name="theme-color" content="#062A5E">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="MedAlbumin">
    <meta property="og:title" content="@yield('title', 'MedAlbumin — Connect Knowledge. Master Medicine.')">
    <meta property="og:description" content="@yield('description', 'Past papers, mock exams, notes and flashcards for medical students — in one calm, focused place.')">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ asset('images/medalbumin-mark.png') }}">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="icon" type="image/png" href="{{ asset('images/medalbumin-mark.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/medalbumin-mark.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;600;700&family=Geist:wght@400;500;600&family=Geist+Mono:wght@500&family=Noto+Sans+Ethiopic:wght@500;600&display=swap">
    @vite(['resources/css/app.css'])
    @stack('head')
</head>
<body class="bg-page text-navy">
<header class="sticky top-0 z-30 bg-page/90 backdrop-blur border-b border-line">
    <div class="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between gap-4">
        <a href="{{ route('home') }}" class="flex items-center gap-2.5 no-underline">
            <span class="w-10 h-10 rounded-xl bg-white border border-line flex items-center justify-center"><img src="{{ asset('images/medalbumin-mark.png') }}" alt="" class="w-7 h-8 object-contain"></span>
            <span class="font-display text-xl font-bold"><span class="text-navy">Med</span><span class="text-teal">Albumin</span></span>
        </a>
        <nav class="hidden md:flex items-center gap-6 text-sm font-medium">
            <a href="{{ route('home') }}#features" class="text-slate hover:text-navy no-underline">Features</a>
            <a href="{{ route('home') }}#subjects" class="text-slate hover:text-navy no-underline">Subjects</a>
            <a href="{{ route('pricing') }}" class="no-underline {{ request()->routeIs('pricing') ? 'text-navy font-semibold' : 'text-slate hover:text-navy' }}">Pricing</a>
            <a href="{{ route('home') }}#faq" class="text-slate hover:text-navy no-underline">FAQ</a>
        </nav>
        <span class="flex items-center gap-2">
            <a href="{{ route('account') }}" class="btn btn-light btn-sm">{{ auth()->check() && ! auth()->user()->is_staff ? 'My account' : 'Sign in' }}</a>
            <a href="{{ route('home') }}#download" class="btn btn-primary btn-sm hidden sm:inline-flex">Get the app</a>
        </span>
    </div>
</header>

@yield('content')

<footer class="bg-navy text-haze-3 mt-20">
    <div class="max-w-6xl mx-auto px-4 py-12 grid gap-8 md:grid-cols-[2fr_1fr_1fr]">
        <div class="flex flex-col gap-3">
            <span class="font-display text-2xl font-bold"><span class="text-white">Med</span><span class="text-accent">Albumin</span></span>
            <span class="text-sm text-haze">Connect Knowledge. Master Medicine.</span>
            <span class="text-xs text-haze-2 max-w-sm">For exam study only — not a substitute for clinical judgement or national treatment guidelines.</span>
        </div>
        <div class="flex flex-col gap-2 text-sm">
            <span class="font-semibold text-white">Legal</span>
            <a href="{{ route('pricing') }}" class="text-haze hover:text-white no-underline">Pricing</a>
            <a href="{{ route('legal.privacy') }}" class="text-haze hover:text-white no-underline">Privacy policy</a>
            <a href="{{ route('legal.terms') }}" class="text-haze hover:text-white no-underline">Terms of use</a>
            <a href="{{ route('legal.delete-account') }}" class="text-haze hover:text-white no-underline">Delete my account</a>
        </div>
        <div class="flex flex-col gap-2 text-sm">
            <span class="font-semibold text-white">Help</span>
            <a href="{{ config('services.support.telegram') }}" class="text-haze hover:text-white no-underline" rel="noopener">Telegram support</a>
            <a href="mailto:{{ config('services.support.email') }}" class="text-haze hover:text-white no-underline">{{ config('services.support.email') }}</a>
        </div>
    </div>
    <div class="border-t border-navy-4 py-5 text-center text-xs text-haze-2">© {{ date('Y') }} MedAlbumin. All rights reserved.</div>
</footer>
</body>
</html>
