@extends('site.layout')
@section('title', 'Terms of use · MedAlbumin')
@section('description', 'The rules for using MedAlbumin.')
@section('content')
<article class="max-w-3xl mx-auto px-4 pt-14 prose-note text-[15px]">
    <h1 class="font-display text-4xl font-semibold tracking-[-0.03em] mb-2">Terms of use</h1>
    <p class="text-muted">Last updated {{ now()->format('j F Y') }}</p>

    <h2>1. Using MedAlbumin</h2>
    <p>MedAlbumin is a study tool for medical students. By creating an account you agree to these terms and our <a href="{{ route('legal.privacy') }}">privacy policy</a>.</p>

    <h2>2. For study, not patient care</h2>
    <p>Questions, explanations, notes, lab values, drug doses and calculators are for exam preparation. Always follow your national treatment guidelines and senior clinicians when treating patients. Match predictions are estimates from past data, not official results.</p>

    <h2>3. Your account</h2>
    <ul>
        <li>One account is for one person and can be used on up to two devices.</li>
        <li>Sharing or reselling access, or copying the question bank, leads to suspension without refund.</li>
        <li>Keep your sign-in codes private.</li>
    </ul>

    <h2>4. Plans and payments</h2>
    <ul>
        <li>Paid plans unlock premium content for the period shown when you buy. Plans do not renew automatically.</li>
        <li>Bank-transfer payments are activated after our team checks the receipt, usually within a few hours.</li>
        <li>If you were charged but your plan did not activate, contact support with your payment reference.</li>
    </ul>

    <h2>5. Community rules</h2>
    <p>Be respectful in discussions. No cheating offers, leaked exam material, spam or abuse. We may hide posts and suspend accounts that break these rules.</p>

    <h2>6. Content</h2>
    <p>All questions, notes, videos and designs belong to MedAlbumin or its contributors. Your own notes stay yours; your discussion posts may be shown to other students.</p>

    <h2>7. Ending your account</h2>
    <p>You can delete your account at any time from the app or <a href="{{ route('legal.delete-account') }}">this page</a>.</p>

    <h2>8. Liability</h2>
    <p>We work hard to keep content accurate but cannot guarantee exam results. To the extent allowed by law, our liability is limited to the amount you paid in the last 12 months.</p>

    <h2>9. Contact</h2>
    <p><a href="mailto:{{ config('services.support.email') }}">{{ config('services.support.email') }}</a> · <a href="{{ config('services.support.telegram') }}">Telegram</a></p>
</article>
@endsection
