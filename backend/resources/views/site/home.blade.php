@extends('site.layout')

@push('head')
<script type="application/ld+json">
{!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'MobileApplication',
    'name' => 'MedAlbumin',
    'operatingSystem' => 'Android, iOS',
    'applicationCategory' => 'EducationalApplication',
    'description' => 'Past exam papers, national mock exams, flashcards and notes for medical students in Ethiopia.',
    'inLanguage' => ['en', 'am', 'om', 'so', 'ar'],
    'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'ETB'],
    'publisher' => ['@type' => 'Organization', 'name' => 'MedAlbumin', 'logo' => asset('images/medalbumin-mark.png'), 'url' => url('/')],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
@endpush

@section('content')
@php
    $fmt = fn ($n) => $n >= 1000 ? number_format(floor($n / 100) * 100).'+' : number_format($n);
    $play = config('medalbumin.play_store_url');
    $ios = config('medalbumin.app_store_url');
@endphp

{{-- Hero --}}
<section class="relative bg-navy overflow-hidden">
    <svg aria-hidden="true" class="absolute inset-x-0 top-[55%] w-full" height="180" viewBox="0 0 1200 180" preserveAspectRatio="none">
        <path d="M0 110 H380 L420 70 L460 150 L505 10 L550 170 L590 110 H1200" fill="none" stroke="#1B4A8A" stroke-width="2"/>
        <path d="M0 110 H380 L420 70 L460 150 L505 10 L550 170 L590 110 H700" fill="none" stroke="#14BCC4" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
        <circle cx="700" cy="110" r="6" fill="#14BCC4"/><circle cx="700" cy="110" r="16" fill="none" stroke="#14BCC4" stroke-opacity=".35"/>
    </svg>
    <div class="relative max-w-6xl mx-auto px-4 pt-16 pb-24 md:pt-24 md:pb-32 grid gap-10 md:grid-cols-[1.2fr_1fr] items-center">
        <div class="flex flex-col gap-6">
            <span class="self-start text-xs font-semibold px-3 py-1.5 rounded-full bg-navy-2 text-[#A3E4E8]">Exit exam · Qualification · Residency</span>
            <h1 class="font-display text-[clamp(38px,6vw,64px)] leading-[1.04] font-semibold tracking-[-0.03em] text-page">Pass your medical exams with confidence.</h1>
            <p class="text-lg text-haze max-w-xl">Past papers, mock exams, notes and flashcards — in one calm, focused place. In English, አማርኛ, Afaan Oromoo, Soomaali and العربية.</p>
            <div id="download" class="flex flex-wrap gap-3">
                <a href="{{ $play ?: '#download' }}" class="btn h-14 px-5 bg-white text-navy hover:bg-page hover:text-navy rounded-2xl" @if (! $play) aria-disabled="true" @endif>
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M3.6 2.3c-.3.3-.4.7-.4 1.2v17c0 .5.1.9.4 1.2l9.5-9.7zM14.5 13.4l2.8 2.8-11 6.3c-.4.2-.9.3-1.3.2zM14.5 10.6 4.9 1c.4-.1.9 0 1.3.2l11 6.3zM18.6 8.3l2.7 1.6c.9.5.9 1.8 0 2.3l-2.7 1.5-3-3z"/></svg>
                    <span class="flex flex-col items-start leading-tight"><span class="text-[10px] font-medium">{{ $play ? 'Get it on' : 'Coming soon to' }}</span><span>Google Play</span></span>
                </a>
                <a href="{{ $ios ?: '#download' }}" class="btn h-14 px-5 bg-white text-navy hover:bg-page hover:text-navy rounded-2xl" @if (! $ios) aria-disabled="true" @endif>
                    <svg width="20" height="22" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M16.4 12.6c0-2.6 2.1-3.8 2.2-3.9-1.2-1.8-3.1-2-3.7-2-1.6-.2-3.1.9-3.9.9-.8 0-2-.9-3.3-.9-1.7 0-3.3 1-4.2 2.5-1.8 3.1-.5 7.7 1.3 10.2.9 1.2 1.9 2.6 3.2 2.6 1.3-.1 1.8-.8 3.3-.8s2 .8 3.3.8c1.4 0 2.3-1.3 3.1-2.5 1-1.4 1.4-2.8 1.4-2.9-.1 0-2.7-1-2.7-4zM13.9 5c.7-.8 1.2-2 1-3.1-1 0-2.2.7-2.9 1.5-.6.7-1.2 1.9-1 3 1.1.1 2.2-.6 2.9-1.4z"/></svg>
                    <span class="flex flex-col items-start leading-tight"><span class="text-[10px] font-medium">{{ $ios ? 'Download on the' : 'Coming soon to the' }}</span><span>App Store</span></span>
                </a>
            </div>
        </div>
        <div class="hidden md:flex justify-center">
            <div class="w-[290px] rounded-[44px] bg-page p-5 shadow-[0_30px_80px_rgba(0,0,0,0.35)] flex flex-col gap-3 border-[6px] border-[#0D3672]">
                <div class="flex items-center gap-2"><img src="{{ asset('images/medalbumin-mark.png') }}" class="w-7 h-8 object-contain" alt=""><span class="font-display font-bold"><span>Med</span><span class="text-teal">Albumin</span></span></div>
                <div class="rounded-3xl bg-navy p-4 text-white flex items-center gap-4">
                    <svg width="64" height="64" viewBox="0 0 92 92" aria-hidden="true"><circle cx="46" cy="46" r="38" fill="none" stroke="#1B4A8A" stroke-width="9"/><circle cx="46" cy="46" r="38" fill="none" stroke="#14BCC4" stroke-width="9" stroke-linecap="round" stroke-dasharray="170 238.8" transform="rotate(-90 46 46)"/></svg>
                    <span class="flex flex-col"><span class="text-xs text-haze-3">Exam readiness</span><span class="font-display text-lg font-semibold text-accent">On track ↗</span></span>
                </div>
                @foreach ([['Practice', 'Smart quiz', '#E4ECFB', '#2550A8'], ['Past papers', 'Real exam style', '#DDF3F5', '#017F8E'], ['Grand mock', 'National rank', '#F0E9FB', '#6B3FB8']] as [$t, $s, $bg, $ink])
                    <div class="rounded-2xl bg-white border border-line px-3 py-2.5 flex items-center gap-3"><span class="w-8 h-8 rounded-[10px]" style="background: {{ $bg }}"></span><span class="flex flex-col"><span class="text-sm font-semibold">{{ $t }}</span><span class="text-[11px] text-muted">{{ $s }}</span></span></div>
                @endforeach
            </div>
        </div>
    </div>
</section>

{{-- Live numbers from the question bank --}}
<section class="max-w-6xl mx-auto px-4 -mt-10 relative">
    <div class="card grid grid-cols-2 md:grid-cols-4 divide-x divide-divider">
        @foreach ([[$questions, 'exam questions with explanations'], [$papers, 'past exam papers'], [$notes + $videos, 'notes & video lessons'], [count($departments), 'departments covered']] as [$n, $label])
            <div class="px-5 py-6 flex flex-col gap-1"><span class="font-display text-3xl md:text-4xl font-bold tracking-[-0.03em]">{{ $fmt($n) }}</span><span class="text-sm text-muted">{{ $label }}</span></div>
        @endforeach
    </div>
</section>

{{-- Features --}}
<section id="features" class="max-w-6xl mx-auto px-4 pt-20 flex flex-col gap-8">
    <div class="flex flex-col gap-2 max-w-2xl">
        <h2 class="font-display text-[clamp(28px,4vw,40px)] font-semibold tracking-[-0.03em] leading-tight">Everything you need for exam day</h2>
        <p class="text-slate">Built with Ethiopian medical students and faculty for the national exit, qualification and residency exams.</p>
    </div>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ([
            ['note', 'Past exam papers', 'Real exit and qualification papers'.($years['a'] ?? null ? ', '.$years['a'].'–'.$years['b'] : '').', with full explanations in English and Amharic.', '#DDF3F5', '#017F8E'],
            ['question', 'Smart quiz builder', 'Pick subjects, size and mode. Practise unused questions, your mistakes or bookmarks.', '#E4ECFB', '#2550A8'],
            ['timer', 'National grand mocks', 'Timed 200-question mocks on Saturdays with a national leaderboard and detailed report.', '#F0E9FB', '#6B3FB8'],
            ['cards', 'Flashcards that adapt', 'Spaced repetition shows each card right before you would forget it.', '#E6EBF2', '#062A5E'],
            ['pin', 'Residency match predictor', 'Enter your score and see your chances per university from past placement results.', '#062A5E', '#14BCC4'],
            ['chart', 'Progress & study plan', 'Readiness score, weak-area detection and a daily plan that adapts to you.', '#FFF3D6', '#7A5A12'],
            ['video', 'Video lessons', 'Short lessons by subcategory, compressed for slow connections.', '#E4ECFB', '#2550A8'],
            ['chat', 'Discussion under every question', 'Ask a doubt; faculty post verified answers.', '#DDF3F5', '#017F8E'],
            ['globe', 'Five languages', 'English, አማርኛ, Afaan Oromoo, Soomaali and العربية.', '#FFE8D6', '#B4441A'],
        ] as [$icon, $title, $text, $bg, $ink])
            <article class="card card-pad flex flex-col gap-3">
                <span class="w-11 h-11 rounded-xl flex items-center justify-center" style="background: {{ $bg }}; color: {{ $ink }}"><x-icon :name="$icon" size="20"/></span>
                <h3 class="font-display text-lg font-semibold">{{ $title }}</h3>
                <p class="text-sm text-slate leading-relaxed">{{ $text }}</p>
            </article>
        @endforeach
    </div>
</section>

{{-- Subjects --}}
@if ($departments)
<section id="subjects" class="max-w-6xl mx-auto px-4 pt-20 flex flex-col gap-8">
    <h2 class="font-display text-[clamp(28px,4vw,40px)] font-semibold tracking-[-0.03em]">Every department, organised the way you study</h2>
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($departments as $d)
            <div class="card px-4 py-4 flex items-center gap-3.5">
                <span class="w-12 h-12 rounded-2xl flex items-center justify-center shrink-0" style="background: {{ $d['bg'] }}; color: {{ $d['ink'] }}"><x-icon :name="$d['icon']" size="22"/></span>
                <span class="flex flex-col"><span class="font-semibold">{{ $d['name'] }}</span><span class="text-sm text-muted">{{ $d['questions'] ? number_format($d['questions']).' questions' : 'Coming soon' }}</span></span>
            </div>
        @endforeach
    </div>
</section>
@endif

{{-- Pricing --}}
<section id="pricing" class="max-w-6xl mx-auto px-4 pt-20 flex flex-col gap-8">
    <div class="flex flex-col gap-2 max-w-2xl">
        <h2 class="font-display text-[clamp(28px,4vw,40px)] font-semibold tracking-[-0.03em]">Start free. Unlock everything when you are ready.</h2>
        <p class="text-slate">Free questions every day. Subscribe from <a href="{{ route('account') }}">your account</a> with Telebirr, CBE Birr, card or a bank transfer — the app unlocks automatically.</p>
    </div>
    @if ($plans)
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($plans as $i => $p)
                <a href="{{ route('pricing') }}" class="rounded-[22px] p-5 flex flex-col gap-2 no-underline {{ $p['badge'] ? 'bg-navy text-page hover:text-page' : 'bg-white border border-line text-navy hover:text-navy hover:border-teal' }}">
                    <span class="flex items-center justify-between"><span class="font-semibold">{{ $p['name'] }}</span>@if ($p['badge'])<span class="text-[10px] font-bold px-2 py-1 rounded-full bg-accent text-navy">{{ $p['badge'] }}</span>@endif</span>
                    <span class="font-display text-3xl font-bold">ETB {{ number_format($p['price']) }}</span>
                    <span class="text-sm {{ $p['badge'] ? 'text-haze' : 'text-muted' }}">{{ $p['note'] }}</span>
                </a>
            @endforeach
        </div>
    @endif
    <a href="{{ route('pricing') }}" class="btn btn-primary self-start">See plans &amp; what’s included</a>
</section>

{{-- FAQ --}}
<section id="faq" class="max-w-3xl mx-auto px-4 pt-20 flex flex-col gap-4">
    <h2 class="font-display text-[clamp(28px,4vw,40px)] font-semibold tracking-[-0.03em]">Questions</h2>
    @foreach ([
        ['Is MedAlbumin free?', 'Yes. You can practise free questions without paying, and even browse as a guest. A subscription unlocks every past paper, mock and lesson.'],
        ['Which exams does it cover?', 'The national medical exit (licensing) exam, pre-internship qualification and block exams, and residency entrance preparation.'],
        ['Can I study offline?', 'Notes and papers you open are kept on your phone, so a weak connection does not stop your revision.'],
        ['How many devices can I use?', 'One account works on up to two phones. You can sign out an old phone from the app at any time.'],
        ['Are the answers checked?', 'Questions and notes are written and reviewed by the MedAlbumin medical team. Students can report any question, and faculty answer doubts.'],
    ] as [$q, $a])
        <details class="card px-5 py-4 group">
            <summary class="font-semibold cursor-pointer list-none flex justify-between items-center gap-3">{{ $q }}<x-icon name="chevron-down" size="18" class="transition-transform group-open:rotate-180 shrink-0"/></summary>
            <p class="text-sm text-slate mt-3 leading-relaxed">{{ $a }}</p>
        </details>
    @endforeach
</section>
@endsection
