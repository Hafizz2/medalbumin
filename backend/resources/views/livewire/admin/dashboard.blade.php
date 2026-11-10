<div class="flex flex-col gap-[22px]">
    <header class="flex flex-wrap items-center justify-between gap-3.5">
        <div class="flex flex-col gap-1">
            <span class="eyebrow">{{ now()->format('l, j F') }}</span>
            @php $h = (int) now()->format('G'); @endphp
            <h1 class="h-title">Good {{ $h < 12 ? 'morning' : ($h < 17 ? 'afternoon' : 'evening') }}, {{ explode(' ', auth()->user()->name ?? 'there')[0] }}</h1>
        </div>
        <div class="flex flex-wrap items-center gap-2.5">
            <div class="relative" x-data="{ open: true }" @click.outside="open = false">
                <label class="h-11 min-w-[260px] rounded-[14px] bg-white border border-line flex items-center gap-2.5 px-3.5">
                    <x-icon name="search" size="16" class="text-muted"/>
                    <span class="sr-only">Search</span>
                    <input type="search" wire:model.live.debounce.300ms="search" @focus="open = true" placeholder="Search users, questions, receipts" class="flex-1 border-0 outline-0 bg-transparent text-sm">
                </label>
                @if ($results)
                    <div x-show="open" class="absolute right-0 left-0 top-12 z-20 card p-1.5 shadow-lg max-h-96 overflow-auto">
                        @foreach ($results as [$kind, $title, $sub, $url])
                            <a href="{{ $url }}" wire:navigate class="flex flex-col gap-0.5 px-3 py-2 rounded-xl hover:bg-page no-underline text-navy">
                                <span class="text-sm font-semibold"><span class="badge-muted badge mr-1.5">{{ $kind }}</span>{{ $title }}</span>
                                @if ($sub)<span class="text-xs text-muted truncate">{{ $sub }}</span>@endif
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
            @can('edit content')
                <a href="{{ route('admin.questions', ['new' => 1]) }}" wire:navigate class="btn btn-primary"><x-icon name="plus" size="16" stroke="2.4"/>New question</a>
            @endcan
        </div>
    </header>

    <section class="grid gap-3.5 grid-cols-[repeat(auto-fit,minmax(min(220px,100%),1fr))]">
        @foreach ($kpis as [$label, $value, $delta, $dark])
            <div class="rounded-[22px] px-5 py-[18px] flex flex-col gap-1.5 {{ $dark ? 'bg-navy text-page' : 'bg-white border border-line' }}">
                <span class="text-[13px] opacity-75">{{ $label }}</span>
                <span class="font-display text-[34px] font-bold tracking-[-0.03em] leading-none">{{ $value }}</span>
                <span class="text-xs font-semibold {{ $dark ? 'text-accent' : 'text-teal' }}">{{ $delta }}</span>
            </div>
        @endforeach
    </section>

    <section class="grid gap-3.5 grid-cols-[repeat(auto-fit,minmax(min(420px,100%),1fr))]">
        <div class="card card-pad flex flex-col gap-4">
            <div class="flex flex-wrap justify-between items-baseline gap-2">
                <h2 class="h-section">New subscriptions</h2>
                <span class="text-xs text-muted">Last 12 weeks</span>
            </div>
            @php $max = max(1, collect($weeks)->max('value')); @endphp
            <div class="h-[200px] flex items-end gap-2 border-b border-divider">
                @foreach ($weeks as $i => $w)
                    <div class="flex-1 flex flex-col items-center justify-end gap-1.5 h-full" title="Week of {{ $w['label'] }}">
                        <span class="mono text-[10px] text-muted">{{ $w['value'] }}</span>
                        <span class="w-full max-w-[34px] rounded-t-lg rounded-b-[3px] {{ $i >= 9 ? 'bg-teal' : 'bg-[#CFE3DD]' }}" style="height: {{ max(2, round($w['value'] / $max * 160)) }}px"></span>
                    </div>
                @endforeach
            </div>
            <div class="flex justify-between mono text-[11px] text-muted"><span>{{ $weeks[0]['label'] }}</span><span>{{ end($weeks)['label'] }}</span></div>
        </div>

        <div class="card card-pad flex flex-col gap-3">
            <h2 class="h-section">Needs your attention</h2>
            @foreach ($attention as [$title, $sub, $count, $url, $warm])
                <a href="{{ $url }}" wire:navigate class="min-h-[60px] px-3.5 rounded-2xl flex items-center justify-between no-underline text-navy {{ $warm && $count ? 'bg-warn-bg' : 'bg-page' }}">
                    <span class="flex flex-col gap-0.5"><span class="font-semibold text-sm">{{ $title }}</span><span class="text-xs text-muted">{{ $sub }}</span></span>
                    <span class="font-display text-2xl font-bold {{ $warm && $count ? 'text-warn' : '' }}">{{ $count }}</span>
                </a>
            @endforeach
        </div>
    </section>

    <section class="grid gap-3.5 grid-cols-[repeat(auto-fit,minmax(min(420px,100%),1fr))]">
        <div class="card card-pad flex flex-col gap-3.5">
            <div class="flex justify-between items-baseline"><h2 class="h-section">Content by language</h2><a href="{{ route('admin.languages') }}" wire:navigate class="text-[13px] font-semibold">Manage</a></div>
            @foreach ($languages as [$name, $n, $pct])
                <div class="grid grid-cols-[130px_1fr_90px] items-center gap-3">
                    <span class="text-sm font-medium">{{ $name }}</span>
                    <span class="bar"><span style="width: {{ $pct }}%"></span></span>
                    <span class="mono text-[13px] text-right text-slate">{{ number_format($n) }}</span>
                </div>
            @endforeach
        </div>
        <div class="card card-pad flex flex-col gap-1.5">
            <h2 class="h-section mb-1.5">Hardest questions this week</h2>
            @forelse ($hardest as $h)
                <a href="{{ route('admin.questions', ['selected' => $h['question']->id]) }}" wire:navigate class="flex items-center justify-between gap-3 min-h-12 border-t border-divider no-underline text-navy">
                    <span class="flex flex-col gap-0.5 min-w-0">
                        <span class="text-sm font-medium truncate">{{ strip_tags($h['question']->getTranslation('stem', 'en')) }}</span>
                        <span class="text-xs text-muted">{{ $h['question']->subcategory?->name ?? $h['question']->department?->name }}{{ $h['question']->sourceLabel() ? ' · '.$h['question']->sourceLabel() : '' }}</span>
                    </span>
                    <span class="mono text-[13px] text-danger shrink-0">{{ $h['pct'] }}% correct</span>
                </a>
            @empty
                <p class="text-sm text-muted border-t border-divider pt-3">Questions appear here once students have answered them at least 10 times this week.</p>
            @endforelse
        </div>
    </section>
</div>
