@props(['title' => null])
@php
    $user = auth()->user();
    $pendingPayments = \App\Models\Payment::query()->where('status', 'pending')->where('gateway', 'bank_transfer')->count();
    $openReports = \App\Models\QuestionReport::query()->where('status', 'open')->count();
    $nav = [
        ['admin.dashboard', 'Dashboard', 'dashboard', null, null],
        ['admin.questions', 'Question bank', 'question', 'edit content', null],
        ['admin.exams', 'Exam builder', 'timer', 'edit content', null],
        ['admin.videos', 'Video library', 'video', 'edit content', null],
        ['admin.notes', 'Departments & notes', 'note', 'edit content', null],
        ['admin.library', 'Papers, cards & tools', 'library', 'edit content', null],
        ['admin.match', 'Match data', 'pin', 'edit content', null],
        ['admin.users', 'Users', 'users', 'manage users', null],
        ['admin.payments', 'Payments', 'card', 'approve payments', $pendingPayments],
        ['admin.reports', 'Reported', 'flag', 'edit content', $openReports],
        ['admin.push', 'Push notifications', 'bell', 'send notifications', null],
        ['admin.community', 'Community', 'chat', 'answer doubts', null],
        ['admin.analytics', 'Analytics', 'chart', 'view analytics', null],
        ['admin.languages', 'Languages', 'globe', 'edit content', null],
        ['admin.settings', 'Settings', 'settings', 'super', null],
    ];
    $role = $user?->getRoleNames()->first() ?? 'Staff';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ? $title.' · ' : '' }}MedAlbumin Admin</title>
    <link rel="icon" type="image/png" href="{{ asset('images/medalbumin-mark.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;600;700&family=Geist:wght@400;500;600&family=Geist+Mono:wght@500&family=Noto+Sans+Ethiopic:wght@400;500;600&family=Noto+Sans+Arabic:wght@400;500;600&display=swap">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen" x-data="{ nav: false }">
<div class="min-h-screen lg:flex">
    {{-- Mobile top bar --}}
    <div class="lg:hidden sticky top-0 z-30 flex items-center justify-between bg-navy px-4 h-14">
        <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2 no-underline">
            <span class="w-8 h-8 rounded-[10px] bg-white flex items-center justify-center"><img src="{{ asset('images/medalbumin-mark.png') }}" alt="" class="w-6 h-7 object-contain"></span>
            <span class="font-display font-bold text-lg"><span class="text-white">Med</span><span class="text-accent">Albumin</span></span>
        </a>
        <button type="button" class="text-white p-2" @click="nav = !nav" aria-label="Menu"><x-icon name="menu" size="22"/></button>
    </div>

    <nav aria-label="Admin" :class="nav ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
         class="fixed lg:sticky top-0 z-40 lg:z-auto h-screen w-[260px] shrink-0 bg-navy text-haze-3 px-4 py-6 flex flex-col gap-5 overflow-y-auto transition-transform">
        <div class="flex items-center gap-2.5 px-2">
            <span class="w-[38px] h-[38px] rounded-[11px] bg-white flex items-center justify-center shrink-0"><img src="{{ asset('images/medalbumin-mark.png') }}" alt="MedAlbumin logo" class="w-7 h-8 object-contain"></span>
            <span class="flex flex-col"><span class="font-display text-[19px] font-bold tracking-[-0.01em]"><span class="text-white">Med</span><span class="text-accent">Albumin</span></span><span class="text-[11px] text-haze-2">Admin console</span></span>
        </div>
        <div class="flex flex-col gap-0.5">
            @foreach ($nav as [$route, $label, $icon, $perm, $count])
                @continue($perm === 'super' ? ! $user?->hasRole('Super admin') : ($perm && ! $user?->can($perm)))
                @php $active = request()->routeIs($route.'*'); @endphp
                <a href="{{ route($route) }}" wire:navigate
                   class="min-h-[42px] px-3 rounded-xl flex items-center justify-between gap-3 no-underline text-sm {{ $active ? 'bg-navy-2 text-page font-semibold' : 'text-haze font-medium hover:bg-navy-3 hover:text-page' }}">
                    <span class="flex items-center gap-3"><x-icon :name="$icon"/>{{ $label }}</span>
                    @if ($count)
                        <span class="mono text-[11px] px-2 py-0.5 rounded-full bg-accent text-navy">{{ $count }}</span>
                    @endif
                </a>
            @endforeach
        </div>
        <div class="flex-1"></div>
        <div class="flex items-center gap-2.5 p-3 rounded-[14px] bg-navy-3">
            <span class="w-[34px] h-[34px] rounded-full bg-navy-5 text-page flex items-center justify-center text-[13px] font-semibold shrink-0">{{ $user?->initials() }}</span>
            <a href="{{ route('admin.security') }}" class="flex flex-col min-w-0 flex-1 no-underline" wire:navigate>
                <span class="text-[13px] font-semibold text-page truncate">{{ $user?->shortName() }}</span>
                <span class="text-[11px] text-haze-2">{{ $role }}</span>
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="text-haze hover:text-white p-1" title="Sign out" aria-label="Sign out"><x-icon name="logout" size="18"/></button>
            </form>
        </div>
    </nav>
    <div x-show="nav" x-cloak @click="nav = false" class="fixed inset-0 z-30 bg-navy/40 lg:hidden"></div>

    <main class="flex-1 min-w-0 px-4 sm:px-[clamp(16px,3vw,40px)] pt-7 pb-12 flex flex-col gap-[22px]">
        @if (session('status'))
            <div class="card px-4 py-3 text-sm font-medium bg-teal-wash border-teal-soft text-teal-ink">{{ session('status') }}</div>
        @endif
        {{ $slot }}
    </main>
</div>

{{-- Toasts: $this->dispatch('toast', message: '…') from any Livewire component --}}
<div x-data="{ items: [] }"
     @toast.window="items.push({ id: Date.now(), msg: $event.detail.message, type: $event.detail.type || 'ok' }); setTimeout(() => items.shift(), 3500)"
     class="fixed bottom-5 right-5 z-50 flex flex-col gap-2">
    <template x-for="t in items" :key="t.id">
        <div class="px-4 py-3 rounded-[14px] text-sm font-semibold shadow-lg"
             :class="t.type === 'error' ? 'bg-stop text-white' : 'bg-navy text-page'" x-text="t.msg"></div>
    </template>
</div>
@livewireScripts
</body>
</html>
