@extends('site.layout')
@section('title', 'My account · MedAlbumin')
@section('content')
<section class="max-w-4xl mx-auto px-4 pt-12 flex flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <span class="w-12 h-12 rounded-full bg-sky-bg text-sky flex items-center justify-center font-semibold">{{ $user->initials() }}</span>
            <div class="flex flex-col"><h1 class="font-display text-2xl font-semibold">{{ $user->name ?: 'Your account' }}</h1><span class="text-sm text-muted">{{ $user->phone ?? ($user->telegram_username ? '@'.$user->telegram_username : $user->email) }}</span></div>
        </div>
        <form method="POST" action="{{ route('account.logout') }}">@csrf<button class="btn btn-light btn-sm">Sign out</button></form>
    </div>

    @if (session('status'))<div class="card px-4 py-3 bg-teal-wash text-teal-ink text-sm">{{ session('status') }}</div>@endif

    <div class="rounded-[22px] p-6 flex flex-wrap items-center justify-between gap-4 {{ $until ? 'bg-navy text-page' : 'bg-white border border-line' }}">
        <div class="flex flex-col gap-1">
            <span class="text-sm {{ $until ? 'text-haze-3' : 'text-muted' }}">Your plan</span>
            <span class="font-display text-3xl font-bold">{{ $until ? 'Premium' : 'Free plan' }}</span>
            <span class="text-sm {{ $until ? 'text-accent' : 'text-muted' }}">{{ $until ? 'Active until '.$until->format('j F Y') : 'Free questions every day. Upgrade to unlock everything.' }}</span>
        </div>
        @if ($pending)<span class="badge badge-warn">Receipt waiting for approval</span>@endif
    </div>

    @if ($plans->isNotEmpty())
        <div class="flex flex-col gap-3">
            <h2 class="h-section">{{ $until ? 'Extend your plan' : 'Choose a plan' }}</h2>
            <p class="text-sm text-slate">Paying early never loses days — a new plan starts when your current one ends.</p>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($plans as $p)
                    <a href="{{ route('account.checkout', $p) }}" class="rounded-[22px] p-5 flex flex-col gap-2 no-underline {{ $p->badge ? 'bg-navy text-page hover:text-page' : 'bg-white border border-line text-navy hover:text-navy hover:border-teal' }}">
                        <span class="flex items-center justify-between"><span class="font-semibold">{{ $p->name }}</span>@if ($p->badge)<span class="text-[10px] font-bold px-2 py-1 rounded-full bg-accent text-navy">{{ $p->badge }}</span>@endif</span>
                        <span class="font-display text-2xl font-bold">ETB {{ number_format((float) $p->price) }}</span>
                        <span class="text-sm opacity-75">{{ $p->note ?: $p->durationLabel() }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    @if ($payments->isNotEmpty())
        <section class="card overflow-hidden">
            <h2 class="h-section px-5 pt-4 pb-3">Payments</h2>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Date</th><th>Plan</th><th>Method</th><th>Amount</th><th>Status</th></tr></thead>
                    <tbody>
                    @foreach ($payments as $p)
                        <tr>
                            <td>{{ $p->created_at->format('j M Y') }}</td>
                            <td>{{ $p->plan?->name }}</td>
                            <td>{{ $p->gatewayLabel() }}</td>
                            <td class="mono">ETB {{ number_format((float) $p->amount, 2) }}</td>
                            <td><span class="badge {{ ['approved' => 'badge-ok', 'pending' => 'badge-warn', 'rejected' => 'badge-stop'][$p->status] ?? 'badge-muted' }}">{{ ucfirst($p->status) }}</span>
                                @if ($p->reject_reason)<span class="block text-xs text-stop mt-1">{{ $p->reject_reason }}</span>@endif</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</section>
@endsection
