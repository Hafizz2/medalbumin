<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment · MedAlbumin</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-page flex items-center justify-center p-6">
@php $ok = $payment?->status === 'approved'; @endphp
<main class="card card-pad max-w-sm w-full flex flex-col items-center text-center gap-3">
    <span class="w-14 h-14 rounded-full flex items-center justify-center {{ $ok ? 'bg-teal-soft text-teal' : 'bg-warn-bg text-warn' }}"><x-icon :name="$ok ? 'check' : 'timer'" size="28" stroke="2.4"/></span>
    <h1 class="font-display text-2xl font-semibold">{{ $ok ? 'Payment received' : ($payment ? 'Checking your payment…' : 'Payment not found') }}</h1>
    <p class="text-sm text-slate">{{ $ok ? 'Your plan is active. Open the MedAlbumin app to keep studying.' : 'Your plan activates as soon as the payment is confirmed.' }}</p>
    <a href="{{ route('account') }}" class="btn btn-primary w-full">Go to my account</a>
</main>
</body>
</html>
