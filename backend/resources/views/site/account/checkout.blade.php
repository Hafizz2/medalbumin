@extends('site.layout')
@section('title', 'Checkout · MedAlbumin')
@section('content')
<section class="max-w-2xl mx-auto px-4 pt-12 flex flex-col gap-5">
    <a href="{{ route('account') }}" class="text-sm">← Back to my account</a>
    <h1 class="font-display text-3xl font-semibold tracking-[-0.03em]">{{ $plan->name }} plan</h1>

    <div class="card card-pad flex flex-col gap-3">
        <div class="flex justify-between text-sm"><span>{{ $plan->name }} · {{ $plan->note ?: $plan->durationLabel() }}</span><span class="mono">ETB {{ number_format($quote['price'], 2) }}</span></div>
        @if ($quote['discount'] > 0)<div class="flex justify-between text-sm text-teal"><span>Promo {{ strtoupper($promo) }}</span><span class="mono">− ETB {{ number_format($quote['discount'], 2) }}</span></div>@endif
        @if ($quote['free_days'])<div class="text-sm text-teal">Promo {{ strtoupper($promo) }}: {{ $quote['free_days'] }} free days — no payment needed.</div>@endif
        <div class="flex justify-between font-semibold border-t border-divider pt-3"><span>Total</span><span class="mono text-lg">ETB {{ number_format($quote['free_days'] ? 0 : $quote['total'], 2) }}</span></div>
        <form method="GET" class="flex gap-2">
            <input name="promo" value="{{ $promo }}" class="field mono uppercase" placeholder="Promo code">
            <button class="btn btn-light">Apply</button>
        </form>
        @if ($promoError)<span class="error">{{ $promoError }}</span>@endif
    </div>

    @if ($quote['free_days'] || $quote['total'] <= 0)
        <form method="POST" action="{{ route('account.pay', $plan) }}">
            @csrf
            <input type="hidden" name="method" value="chapa"><input type="hidden" name="promo_code" value="{{ $promo }}">
            <button class="btn btn-teal w-full h-12">Activate plan</button>
        </form>
    @else
        @if ($online)
            <form method="POST" action="{{ route('account.pay', $plan) }}" class="card card-pad flex flex-col gap-3">
                @csrf
                <input type="hidden" name="method" value="chapa"><input type="hidden" name="promo_code" value="{{ $promo }}">
                <h2 class="h-section">Pay online</h2>
                <p class="text-sm text-slate">Telebirr, CBE Birr, M-Pesa or card through Chapa. Your plan activates as soon as payment is confirmed.</p>
                <div class="flex flex-wrap gap-2 text-xs font-semibold">
                    @foreach ([['Telebirr', '#0E6FB8'], ['CBE Birr', '#6B2C91'], ['Card', '#3F7F2A']] as [$n, $c])<span class="px-2.5 py-1 rounded-full text-white" style="background: {{ $c }}">{{ $n }}</span>@endforeach
                </div>
                @error('gateway')<span class="error">{{ $message }}</span>@enderror
                <button class="btn btn-teal h-12">Pay ETB {{ number_format($quote['total'], 2) }}</button>
            </form>
        @endif

        <form method="POST" action="{{ route('account.pay', $plan) }}" enctype="multipart/form-data" class="card card-pad flex flex-col gap-3">
            @csrf
            <input type="hidden" name="method" value="bank_transfer"><input type="hidden" name="promo_code" value="{{ $promo }}">
            <h2 class="h-section">Pay by bank transfer</h2>
            @if ($banks)
                <p class="text-sm text-slate">Transfer <strong class="mono">ETB {{ number_format($quote['total'], 2) }}</strong> to one of these accounts, then upload the receipt.</p>
                <div class="flex flex-col gap-2">
                    @foreach ($banks as $b)
                        <div class="rounded-2xl bg-page px-4 py-3 text-sm"><span class="font-semibold">{{ $b['bank'] }}</span> · {{ $b['account_name'] }}<span class="block mono text-base">{{ $b['account_number'] }}</span></div>
                    @endforeach
                </div>
                <div class="grid sm:grid-cols-2 gap-3">
                    <label class="label">Bank you paid from<input name="bank_name" class="field" value="{{ old('bank_name') }}" placeholder="CBE"></label>
                    <label class="label">Transaction reference<input name="reference" class="field mono" value="{{ old('reference') }}" placeholder="FT26286XK31"></label>
                </div>
                <label class="label">Receipt (photo or PDF)<input type="file" name="receipt" accept="image/*,application/pdf" class="field !h-auto py-2" required></label>
                @error('receipt')<span class="error">{{ $message }}</span>@enderror
                @error('reference')<span class="error">{{ $message }}</span>@enderror
                <button class="btn btn-primary h-12">Send receipt</button>
                <span class="text-xs text-muted">Our team usually approves receipts within a few hours. You get a notification in the app.</span>
            @else
                <p class="text-sm text-muted">Bank details will be shown here soon. Contact <a href="{{ config('services.support.telegram') }}">support on Telegram</a>.</p>
            @endif
        </form>
    @endif
</section>
@endsection
