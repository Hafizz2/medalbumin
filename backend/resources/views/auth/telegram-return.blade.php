<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Returning to MedAlbumin…</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-page flex items-center justify-center p-6">
<main class="card card-pad max-w-sm w-full flex flex-col items-center text-center gap-3">
    <img src="{{ asset('images/medalbumin-mark.png') }}" alt="MedAlbumin" class="w-12 h-14 object-contain">
    <h1 class="font-display text-xl font-semibold">Returning to the app…</h1>
    <a href="{{ $url }}" class="btn btn-primary w-full">Open MedAlbumin</a>
</main>
<script>window.location.replace(@json($url));</script>
</body>
</html>
