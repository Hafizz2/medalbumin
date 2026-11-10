<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: DejaVu Sans, sans-serif; color: #062A5E; font-size: 12px; }
    h1 { font-size: 22px; margin: 0; } h2 { font-size: 15px; margin: 20px 0 6px; }
    .muted { color: #56657D; }
    .grid td { border: 1px solid #E0E6EE; padding: 10px; width: 25%; vertical-align: top; }
    .num { font-size: 20px; font-weight: bold; }
    table { width: 100%; border-collapse: collapse; }
    .list td, .list th { padding: 6px; border-bottom: 1px solid #EBEFF5; text-align: left; }
</style>
</head>
<body>
    <h1>MedAlbumin monthly report</h1>
    <div class="muted">{{ $from->format('F Y') }} · generated {{ now()->format('j M Y H:i') }}</div>

    <h2>Overview</h2>
    <table class="grid"><tr>
        <td><div class="muted">New students</div><div class="num">{{ number_format($newStudents) }}</div></td>
        <td><div class="muted">Active students</div><div class="num">{{ number_format($activeStudents) }}</div></td>
        <td><div class="muted">Questions answered</div><div class="num">{{ number_format($answered) }}</div></td>
        <td><div class="muted">Revenue</div><div class="num">ETB {{ number_format($revenue) }}</div><div class="muted">{{ $paymentsCount }} payments</div></td>
    </tr></table>

    <h2>Revenue by payment method</h2>
    <table class="list">
        <tr><th>Method</th><th>Payments</th><th>Amount (ETB)</th></tr>
        @forelse ($byGateway as $g)
            <tr><td>{{ \App\Models\Payment::GATEWAYS[$g->gateway] ?? $g->gateway }}</td><td>{{ $g->n }}</td><td>{{ number_format($g->s, 2) }}</td></tr>
        @empty
            <tr><td colspan="3" class="muted">No payments this month.</td></tr>
        @endforelse
    </table>

    <h2>Engagement (today)</h2>
    <table class="list">
        @foreach ($kpis as [$l, $v, $s])<tr><td>{{ $l }}</td><td><strong>{{ $v }}</strong></td><td class="muted">{{ $s }}</td></tr>@endforeach
    </table>

    <h2>Funnel (all time)</h2>
    <table class="list">
        @foreach ($funnel as [$l, $v, $p])<tr><td>{{ $l }}</td><td>{{ number_format($v) }}</td><td>{{ $p }}%</td></tr>@endforeach
    </table>

    <h2>Questions to review</h2>
    <table class="list">
        <tr><th>ID</th><th>Question</th><th>Correct</th><th>Flag</th></tr>
        @forelse ($flagged as $q)
            <tr><td>{{ $q->code }}</td><td>{{ \Illuminate\Support\Str::limit($q->getTranslation('stem', 'en'), 90) }}</td><td>{{ round($q->correctPct()) }}%</td><td>{{ str_replace('_', ' ', $q->quality_flag) }}</td></tr>
        @empty
            <tr><td colspan="4" class="muted">None flagged.</td></tr>
        @endforelse
    </table>
</body>
</html>
