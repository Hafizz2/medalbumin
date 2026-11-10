<div class="flex flex-col gap-[22px]">
    <header class="flex flex-wrap items-center justify-between gap-3.5">
        <div class="flex flex-col gap-1">
            <span class="eyebrow">How students use the app, and which questions need fixing</span>
            <h1 class="h-title">Analytics</h1>
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="button" class="btn btn-light" wire:click="exportUsers">Export users (CSV)</button>
            <button type="button" class="btn btn-light" wire:click="exportPayments">Export payments</button>
            <button type="button" class="btn btn-primary" wire:click="monthlyReport">Monthly report (PDF)</button>
        </div>
    </header>

    <section class="grid gap-3.5 grid-cols-[repeat(auto-fit,minmax(min(200px,100%),1fr))]">
        @foreach ($kpis as [$l, $v, $s])
            <div class="card px-5 py-4 flex flex-col gap-1"><span class="text-[13px] text-muted">{{ $l }}</span><span class="font-display text-[30px] font-bold tracking-[-0.03em]">{{ $v }}</span><span class="text-xs font-semibold text-teal">{{ $s }}</span></div>
        @endforeach
    </section>

    <section class="grid gap-3.5 grid-cols-[repeat(auto-fit,minmax(min(420px,100%),1fr))]">
        <div class="card card-pad flex flex-col gap-4">
            <div class="flex flex-wrap justify-between items-baseline gap-2"><h2 class="h-section">Daily active students</h2><span class="text-xs text-muted">Last 30 days · weekends darker</span></div>
            @php $max = max(1, collect($dau)->max('value')); $peak = collect($dau)->sortByDesc('value')->first(); @endphp
            <div class="h-[170px] flex items-end gap-[3px] border-b border-divider">
                @foreach ($dau as $d)
                    <span class="flex-1 rounded-t-[4px] {{ $d === $peak && $d['value'] ? 'bg-gold' : ($d['weekend'] ? 'bg-teal' : 'bg-[#9ADDE2]') }}" style="height: {{ max(2, round($d['value'] / $max * 150)) }}px" title="{{ $d['label'] }}: {{ $d['value'] }}"></span>
                @endforeach
            </div>
            <div class="flex justify-between mono text-[11px] text-muted"><span>{{ $dau[0]['label'] }}</span><span>Peak {{ number_format($peak['value']) }} · {{ $peak['label'] }}</span><span>{{ end($dau)['label'] }}</span></div>
        </div>
        <div class="card card-pad flex flex-col gap-3.5">
            <h2 class="h-section">From install to paying</h2>
            @foreach ($funnel as [$label, $value, $pct])
                <div class="flex flex-col gap-1.5">
                    <span class="flex justify-between text-sm"><span class="font-medium">{{ $label }}</span><span class="mono">{{ number_format($value) }} <span class="text-muted">· {{ $pct }}%</span></span></span>
                    <span class="bar !h-3"><span style="width: {{ min(100, $pct) }}%"></span></span>
                </div>
            @endforeach
            @php $drops = collect($funnel)->sliding(2)->map(fn ($p) => [$p->first()[0], $p->last()[0], $p->first()[2] - $p->last()[2]])->sortByDesc(2)->first(); @endphp
            @if ($drops && $drops[2] > 0)
                <span class="text-[13px] px-3.5 py-2.5 rounded-2xl bg-page">Biggest drop: {{ Str::lower($drops[0]) }} → {{ Str::lower($drops[1]) }}.</span>
            @endif
        </div>
    </section>

    <section class="card overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 px-5 pt-4 pb-3">
            <h2 class="h-section">Question quality</h2>
            <div class="flex items-center gap-2">
                <div class="seg">
                    @foreach (['all' => 'All', 'flag' => 'Needs review', 'amb' => 'Ambiguous only'] as $k => $label)
                        <button type="button" class="seg-btn" aria-pressed="{{ $quality === $k ? 'true' : 'false' }}" wire:click="$set('quality', '{{ $k }}')">{{ $label }}</button>
                    @endforeach
                </div>
                <button type="button" class="btn btn-light btn-sm" wire:click="recompute" title="Runs nightly; refresh now"><x-icon name="refresh" size="14"/></button>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>ID</th><th>Question</th><th>Attempts</th><th>Correct</th><th>Top-students correct</th><th>Avg time</th><th>Flag</th></tr></thead>
                <tbody>
                @forelse ($rows as $q)
                    @php $f = ['too_hard' => ['Too hard', 'badge-danger'], 'ambiguous' => ['Ambiguous', 'badge-plum'], 'too_easy' => ['Too easy', 'badge-sky'], 'good' => ['Good', 'badge-ok']][$q->quality_flag]; $t = $q->avgTimeSeconds(); @endphp
                    <tr wire:key="qq-{{ $q->id }}">
                        <td><a href="{{ route('admin.questions', ['selected' => $q->id]) }}" wire:navigate class="mono">{{ $q->code }}</a></td>
                        <td class="max-w-md"><span class="line-clamp-1">{{ $q->getTranslation('stem', 'en') }}</span></td>
                        <td class="mono">{{ number_format($q->attempts_count) }}</td>
                        <td class="mono">{{ round($q->correctPct()) }}%</td>
                        <td class="mono">{{ $q->top_correct_pct !== null ? round($q->top_correct_pct).'%' : '—' }}</td>
                        <td class="mono">{{ $t !== null ? sprintf('%d:%02d', intdiv($t, 60), $t % 60) : '—' }}</td>
                        <td><span class="badge {{ $f[1] }}">{{ $f[0] }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-10">Questions are rated once they have 20+ attempts. Ratings refresh every night.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
