<div class="flex flex-col gap-[22px]">
    <header class="flex flex-wrap items-center justify-between gap-3.5">
        <div class="flex flex-col gap-1">
            <a href="{{ route('admin.languages') }}" wire:navigate class="text-[13px]">← Languages</a>
            <h1 class="h-title">{{ $isSource ? 'App text (English source)' : 'Translate app · '.$lang->native_name }}</h1>
            <span class="eyebrow">{{ number_format($done) }} of {{ number_format($total) }} texts {{ $isSource ? 'defined' : 'translated' }}{{ $isSource ? '' : ' · '.($total ? round($done / $total * 100) : 0).'%' }}</span>
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="button" class="btn btn-light" wire:click="export"><x-icon name="download" size="16"/>Export JSON</button>
        </div>
    </header>

    <section class="card card-pad flex flex-wrap items-end gap-3">
        <label class="label flex-1 min-w-[220px]">{{ $isSource ? 'Upload the app’s English text file (en.json)' : 'Import a translated JSON file' }}
            <input type="file" accept=".json,application/json" class="field !h-auto py-2" wire:model="file">
            @error('file')<span class="error">{{ $message }}</span>@enderror
        </label>
        @if ($isSource)
            <label class="flex items-center gap-2 text-sm h-11"><input type="checkbox" class="accent-teal w-4 h-4" wire:model="prune">Remove texts missing from the file</label>
        @endif
        <button type="button" class="btn btn-primary" wire:click="import" wire:loading.attr="disabled" wire:target="import,file"><x-icon name="upload" size="16"/>Import</button>
        @if ($importResult)
            <div class="w-full text-sm"><strong>{{ $importResult['saved'] }} saved{{ $importResult['removed'] ? ', '.$importResult['removed'].' removed' : '' }}, {{ count($importResult['skipped']) }} skipped.</strong>
                @foreach (array_slice($importResult['skipped'], 0, 30, true) as $k => $why)<span class="block text-xs text-danger"><span class="mono">{{ $k }}</span>: {{ $why }}</span>@endforeach
            </div>
        @endif
    </section>

    <div class="flex flex-wrap items-center gap-2">
        <label class="h-10 min-w-[260px] rounded-xl bg-white border border-line flex items-center gap-2 px-3">
            <x-icon name="search" size="15" class="text-muted"/>
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search key or English text" class="flex-1 border-0 outline-0 bg-transparent text-sm">
        </label>
        @unless ($isSource)
            <button type="button" class="chip" aria-pressed="{{ $missingOnly ? 'true' : 'false' }}" wire:click="$toggle('missingOnly')">Missing only</button>
        @endunless
    </div>

    <section class="card overflow-hidden">
        @forelse ($rows as $r)
            @php $placeholders = \App\Services\UiTranslationService::placeholders($r->value); @endphp
            <div class="grid gap-3 px-5 py-3.5 border-b border-divider {{ $isSource ? '' : 'md:grid-cols-2' }}" wire:key="s-{{ $r->key }}" x-data="{ saved: false }" @saved-row.window="if ($event.detail.key === @js($r->key)) { saved = true; setTimeout(() => saved = false, 1500) }">
                <div class="flex flex-col gap-1 min-w-0">
                    <span class="mono text-[11px] text-muted break-all">{{ $r->key }}</span>
                    <span class="text-sm">{{ $r->value }}</span>
                    @if ($r->context)<span class="text-xs text-muted">{{ $r->context }}</span>@endif
                    @if ($placeholders)<span class="text-[11px] text-sky-ink">Keep: @foreach ($placeholders as $p)<span class="mono">{{ '{'.$p.'}' }}</span> @endforeach</span>@endif
                </div>
                @unless ($isSource)
                    <div class="flex flex-col gap-1">
                        <div class="flex gap-2 items-start">
                            <textarea rows="{{ mb_strlen($r->value) > 60 ? 3 : 1 }}" dir="{{ $lang->direction }}" class="field flex-1" wire:model="values.{{ $r->key }}" placeholder="{{ $lang->native_name }}"></textarea>
                            <button type="button" class="btn btn-light btn-sm !h-11" wire:click="saveRow(@js($r->key))">
                                <span x-show="!saved">Save</span><span x-show="saved" x-cloak class="text-teal">✓</span>
                            </button>
                        </div>
                        @error('values.'.$r->key)<span class="error">{{ $message }}</span>@enderror
                    </div>
                @endunless
            </div>
        @empty
            <p class="px-5 py-12 text-center text-sm text-muted">
                @if ($total === 0)
                    No app text yet. Upload the English file from the mobile app project (<span class="mono">mobile/src/i18n/en.json</span>) on the English page.
                @else
                    Nothing matches.
                @endif
            </p>
        @endforelse
        <div class="px-4 py-3">{{ $rows->links(data: ['scrollTo' => false]) }}</div>
    </section>
</div>
