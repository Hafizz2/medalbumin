@extends('site.layout')
@section('title', 'Privacy policy · MedAlbumin')
@section('description', 'How MedAlbumin collects, uses and protects your data.')
@section('content')
<article class="max-w-3xl mx-auto px-4 pt-14 prose-note text-[15px]">
    <h1 class="font-display text-4xl font-semibold tracking-[-0.03em] mb-2">Privacy policy</h1>
    <p class="text-muted">Last updated {{ now()->format('j F Y') }}</p>

    <h2>Who we are</h2>
    <p>MedAlbumin (“we”) provides an exam-preparation app and website for medical students. Contact us at <a href="mailto:{{ config('services.support.email') }}">{{ config('services.support.email') }}</a>.</p>

    <h2>What we collect</h2>
    <ul>
        <li><strong>Account details</strong>: phone number (to sign in by SMS code), or your Google / Apple account ID and email if you choose those; your name, university, program, study year and target exam if you add them.</li>
        <li><strong>Study activity</strong>: answers, scores, time spent, bookmarks, flashcard reviews, notes you write, video progress and discussion posts.</li>
        <li><strong>Device details</strong>: a device identifier, device name, platform and app version (to keep your account to two devices), and a push-notification token if you allow notifications.</li>
        <li><strong>Payments</strong>: plan, amount, payment method and reference. Card and wallet details are handled by the payment provider (Chapa, Telebirr, CBE Birr), not stored by us. If you pay by bank transfer, the receipt photo you upload.</li>
        <li><strong>Student ID</strong>: only if you upload it for a student discount.</li>
    </ul>

    <h2>How we use it</h2>
    <ul>
        <li>To run your account, sync your progress across your devices and show your statistics.</li>
        <li>To build your study plan, flashcard schedule and weak-area suggestions.</li>
        <li>To rank grand mocks. Leaderboards show your first name, last initial and university.</li>
        <li>To process payments and activate your plan.</li>
        <li>To send notifications you can turn off at any time in the app or your phone settings.</li>
        <li>To improve questions (for example, finding questions most students find confusing). This uses combined statistics.</li>
    </ul>
    <p>We do not sell your personal data and we do not show third-party advertising.</p>

    <h2>Who we share it with</h2>
    <p>Only with services needed to run MedAlbumin: our hosting provider, the SMS provider that sends sign-in codes, payment providers, and Expo / Apple / Google to deliver push notifications. They may only use the data to provide their service to us.</p>

    <h2>How long we keep it</h2>
    <p>While your account is open. When you delete your account we erase your profile, notes, progress, devices and uploaded images. Payment records are kept without your name or phone number for accounting and tax law.</p>

    <h2>Your choices</h2>
    <ul>
        <li>Edit your profile in the app at any time.</li>
        <li>Delete your account in the app (Profile → Delete account) or on <a href="{{ route('legal.delete-account') }}">this page</a>.</li>
        <li>Ask for a copy of your data by emailing us.</li>
    </ul>

    <h2>Security</h2>
    <p>Data is sent over encrypted connections (HTTPS). Receipts and student IDs are stored privately and only reviewed by authorised staff, who must use 2-step login.</p>

    <h2>Children</h2>
    <p>MedAlbumin is for university students and is not directed at children under 13.</p>

    <h2>Changes</h2>
    <p>We will post any changes on this page and tell you in the app if they are significant.</p>
</article>
@endsection
