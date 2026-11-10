<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: DejaVu Sans, sans-serif; color: #062A5E; font-size: 12px; }
    h1 { font-size: 22px; margin: 0 0 4px; }
    .muted { color: #56657D; }
    .box { border: 1px solid #E0E6EE; border-radius: 10px; padding: 12px 14px; margin-top: 14px; }
    table { width: 100%; border-collapse: collapse; margin-top: 6px; }
    td, th { padding: 7px 6px; border-bottom: 1px solid #EBEFF5; text-align: left; }
    .big { font-size: 34px; font-weight: bold; color: #017F8E; }
    .likely { color: #017F8E; } .possible { color: #7A5A12; } .reach { color: #B4441A; }
</style>
</head>
<body>
    <h1>MedAlbumin · My residency choice list</h1>
    <div class="muted">{{ $user->name }} · generated {{ now()->format('j M Y') }}</div>

    <div class="box">
        <span class="big">{{ $overall_chance }}%</span>
        <div>Chance of matching on this list · matching score {{ number_format($score, 1) }}</div>
    </div>

    @foreach ($groups as $g)
        <div class="box">
            <strong>{{ $g['order'] }}. {{ $g['specialty']['name'] }}</strong>
            <span class="muted"> — {{ $g['has_safe_option'] ? 'has a safe option' : 'add a safer option' }}</span>
            <table>
                <tr><th>#</th><th>University</th><th>Chance</th></tr>
                @foreach ($g['items'] as $i)
                    <tr><td>{{ $i['rank'] }}</td><td>{{ $i['name'] }}</td><td class="{{ $i['tier'] }}">{{ $i['chance'] }}% · {{ ucfirst($i['tier']) }}</td></tr>
                @endforeach
            </table>
        </div>
    @endforeach

    <p class="muted" style="margin-top: 18px">{{ $disclaimer }}</p>
</body>
</html>
