@extends('site.layout')
@section('title', 'Pricing · MedAlbumin — plans for medical exam preparation')
@section('description', 'Study free every day, or unlock every past paper, mock exam, note and video lesson with a MedAlbumin plan. Pay with Telebirr, CBE Birr, card or bank transfer.')

@if ($plans->isNotEmpty())
@push('head')
<script type="application/ld+json">
{!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'Product',
    'name' => 'MedAlbumin Premium',
    'description' => 'Full access to MedAlbumin past papers, mock exams, notes and video lessons.',
    'brand' => ['@type' => 'Brand', 'name' => 'MedAlbumin'],
    'offers' => $plans->map(fn ($p) => ['@type' => 'Offer', 'name' => $p->name, 'price' => (string) (float) $p->price, 'priceCurrency' => $p->currency, 'url' => route('pricing')])->values(),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
@endpush
@endif

@section('content')
{{-- Header --}}
<section class="bg-navy">
    <div class="max-w-6xl mx-auto px-4 pt-14 pb-28 flex flex-col items-center text-center gap-4">
        <span class="text-xs font-semibold px-3 py-1.5 rounded-full bg-navy-2 text-[#A3E4E8]">Pricing</span>
        <h1 class="font-display text-[clamp(34px,5vw,56px)] leading-[1.05] font-semibold tracking-[-0.03em] text-page max-w-3xl">Study free every day. Unlock everything when you’re ready.</h1>
        <p class="text-lg text-haze max-w-2xl">One plan unlocks every past paper, mock exam, note and video lesson on your phone. No automatic renewals — you choose when to extend.</p>
    </div>
</section>

{{-- Plans --}}
<section class="max-w-6xl mx-auto px-4 -mt-20 relative">
    @php $cols = [0 => 'lg:grid-cols-3', 1 => 'lg:grid-cols-3', 2 => 'lg:grid-cols-3', 3 => 'lg:grid-cols-4'][$plans->count()] ?? 'lg:grid-cols-5'; @endphp
    <div class="grid gap-4 sm:grid-cols-2 {{ $cols }}">
        {{-- Free --}}
        <article class="card p-6 flex flex-col gap-4 shadow-sm">
            <div class="flex flex-col gap-1">
                <span class="font-semibold">Free</span>
                <span class="font-display text-4xl font-bold tracking-[-0.03em]">ETB 0</span>
                <span class="text-sm text-muted">Forever</span>
            </div>
            <ul class="flex flex-col gap-2.5 text-sm flex-1">
                @foreach ([
                    $freeQuestions ? number_format($freeQuestions).' free questions with explanations' : 'Free questions with explanations',
                    'Flashcards with spaced repetition',
                    'Progress tracking and study plan',
                    'Residency match predictor',
                    'Try it as a guest — no sign-up',
                ] as $f)
                    <li class="flex gap-2.5"><x-icon name="check" size="18" stroke="2.4" class="text-teal shrink-0 mt-0.5"/><span>{{ $f }}</span></li>
                @endforeach
            </ul>
            <a href="{{ route('home') }}#download" class="btn btn-light w-full h-12">Get the app</a>
        </article>

        @forelse ($plans as $p)
            @php $featured = (bool) $p->badge; $perMonth = $p->monthlyPrice(); @endphp
            <article class="rounded-[22px] p-6 flex flex-col gap-4 relative {{ $featured ? 'bg-navy text-page shadow-[0_24px_60px_rgba(6,42,94,0.35)] lg:-translate-y-3' : 'bg-white border border-line shadow-sm' }}">
                @if ($featured)
                    <span class="absolute -top-3 left-6 text-[11px] font-bold px-3 py-1 rounded-full bg-accent text-navy">{{ $p->badge }}</span>
                @endif
                <div class="flex flex-col gap-1">
                    <span class="font-semibold">{{ $p->name }}</span>
                    <span class="font-display text-4xl font-bold tracking-[-0.03em]">ETB {{ number_format((float) $p->price) }}</span>
                    <span class="text-sm {{ $featured ? 'text-haze' : 'text-muted' }}">
                        {{ $p->note ?: $p->durationLabel() }}@if ($perMonth) · about ETB {{ number_format($perMonth) }} / month @endif
                    </span>
                </div>
                <ul class="flex flex-col gap-2.5 text-sm flex-1">
                    @foreach ($p->featureList() as $f)
                        <li class="flex gap-2.5"><x-icon name="check" size="18" stroke="2.4" class="{{ $featured ? 'text-accent' : 'text-teal' }} shrink-0 mt-0.5"/><span>{{ $f }}</span></li>
                    @endforeach
                </ul>
                <a href="{{ route('account.checkout', $p) }}" class="btn w-full h-12 {{ $featured ? 'bg-accent text-navy hover:bg-white hover:text-navy' : 'btn-primary' }}">Choose {{ $p->name }}</a>
            </article>
        @empty
            <article class="card p-6 flex flex-col justify-center gap-3 sm:col-span-1 lg:col-span-2 shadow-sm">
                <span class="font-display text-2xl font-semibold">Premium plans are coming soon</span>
                <p class="text-sm text-slate">We are finalising prices. Start free in the app today — your progress carries over when you upgrade.</p>
                <a href="{{ config('services.support.telegram') }}" class="btn btn-light self-start" rel="noopener">Ask us on Telegram</a>
            </article>
        @endforelse
    </div>
    <p class="text-center text-sm text-muted mt-5">Prices in Ethiopian Birr. Have a promo code or student discount? Apply it at checkout.</p>
</section>

{{-- How it works --}}
<section class="max-w-6xl mx-auto px-4 pt-20 flex flex-col gap-8">
    <h2 class="font-display text-[clamp(26px,3.5vw,36px)] font-semibold tracking-[-0.03em]">How subscribing works</h2>
    <div class="grid gap-4 md:grid-cols-3">
        @foreach ([
            ['1', 'Sign in on this website', 'Use “Continue with Telegram” or your phone number — the same account as in the app.'],
            ['2', 'Choose a plan and pay', $online ? 'Telebirr, CBE Birr, M-Pesa or card, or upload a bank transfer receipt.' : 'Pay by bank transfer and upload the receipt.'],
            ['3', 'The app unlocks', 'Online payments unlock instantly. Bank receipts are approved within a few hours, and you get a notification.'],
        ] as [$n, $t, $d])
            <div class="card card-pad flex flex-col gap-2">
                <span class="w-9 h-9 rounded-full bg-teal-soft text-teal flex items-center justify-center font-display font-bold">{{ $n }}</span>
                <h3 class="font-display text-lg font-semibold">{{ $t }}</h3>
                <p class="text-sm text-slate">{{ $d }}</p>
            </div>
        @endforeach
    </div>
    <div class="flex flex-wrap items-center gap-2 text-xs font-semibold">
        <span class="text-sm text-muted font-normal mr-1">We accept</span>
        @foreach ([['Telebirr', '#0E6FB8'], ['CBE Birr', '#6B2C91'], ['Chapa · cards', '#3F7F2A'], ['Bank transfer', '#475569']] as [$n, $c])
            <span class="px-3 py-1.5 rounded-full text-white" style="background: {{ $c }}">{{ $n }}</span>
        @endforeach
    </div>
</section>

{{-- Comparison --}}
<section class="max-w-4xl mx-auto px-4 pt-20 flex flex-col gap-6">
    <h2 class="font-display text-[clamp(26px,3.5vw,36px)] font-semibold tracking-[-0.03em]">Free vs Premium</h2>
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th class="w-1/2">What you get</th><th class="text-center">Free</th><th class="text-center">Premium</th></tr></thead>
                <tbody>
                @foreach ($compare as [$label, $free, $premium])
                    <tr>
                        <td class="font-medium">{{ $label }}</td>
                        @foreach ([$free, $premium] as $i => $v)
                            <td class="text-center {{ $i ? 'font-semibold text-teal' : 'text-slate' }}">
                                @if ($v === true)<x-icon name="check" size="18" stroke="2.4" class="inline text-teal"/>
                                @elseif ($v === false || $v === '0')<span class="text-field-2">—</span>
                                @else{{ $v }}@endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</section>

{{-- FAQ --}}
<section class="max-w-3xl mx-auto px-4 pt-20 flex flex-col gap-4">
    <h2 class="font-display text-[clamp(26px,3.5vw,36px)] font-semibold tracking-[-0.03em]">Pricing questions</h2>
    @foreach ([
        ['Does my plan renew automatically?', 'No. Plans never charge you again on their own. When your plan is ending, extend it from your account page whenever you like.'],
        ['What happens if I buy again before my plan ends?', 'Nothing is lost — the new plan starts the day the current one ends.'],
        ['Why can’t I pay inside the app?', 'Subscriptions are sold on this website so we can offer Telebirr, CBE Birr and bank transfer. Pay once here and the app on your phone unlocks automatically.'],
        ['How long does a bank receipt take?', 'Usually a few hours during the day. You get a notification in the app when it is approved. If something is wrong with the receipt we tell you why.'],
        ['Are there student or university discounts?', 'Yes — watch for promo codes from your university, the MedAlbumin Telegram channel, or invite a friend: when they subscribe, you both get a free month.'],
        ['Can I share my account?', 'One account works on up to 2 of your own phones. Sharing leads to suspension.'],
    ] as [$q, $a])
        <details class="card px-5 py-4 group">
            <summary class="font-semibold cursor-pointer list-none flex justify-between items-center gap-3">{{ $q }}<x-icon name="chevron-down" size="18" class="transition-transform group-open:rotate-180 shrink-0"/></summary>
            <p class="text-sm text-slate mt-3 leading-relaxed">{{ $a }}</p>
        </details>
    @endforeach
    <p class="text-sm text-muted">Still unsure? <a href="{{ config('services.support.telegram') }}" rel="noopener">Ask us on Telegram</a>.</p>
</section>
@endsection
