<div class="flex flex-col gap-[22px]">
    <header class="flex flex-wrap items-center justify-between gap-3.5">
        <div class="flex flex-col gap-1">
            <span class="eyebrow">Add a language as a draft, translate it, then make it live. Anything not translated falls back to English.</span>
            <h1 class="h-title">Languages</h1>
        </div>
        @if ($canManage)<button type="button" class="btn btn-primary" wire:click="startAdd"><x-icon name="plus" size="16" stroke="2.4"/>Add language</button>@endif
    </header>

    <section class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Language</th><th>Status</th><th>App text</th><th>Questions</th><th>Notes</th><th>Decks</th><th>Videos</th><th>Students</th><th></th></tr></thead>
                <tbody>
                @foreach ($rows as $r)
                    @php
                        $l = $r['lang'];
                        $uiPct = $uiTotal ? round(($l->is_default ? $uiTotal : $r['ui']) / $uiTotal * 100) : 0;
                        $qPct = $totals['questions'] ? round($r['questions'] / $totals['questions'] * 100) : 0;
                    @endphp
                    <tr wire:key="lang-{{ $l->code }}">
                        <td>
                            <span class="flex items-center gap-2">
                                @if ($canManage && ! $l->is_default)
                                    <span class="flex flex-col -my-1">
                                        <button type="button" class="text-muted hover:text-navy leading-none" wire:click="move('{{ $l->code }}', -1)" aria-label="Move up">▲</button>
                                        <button type="button" class="text-muted hover:text-navy leading-none" wire:click="move('{{ $l->code }}', 1)" aria-label="Move down">▼</button>
                                    </span>
                                @endif
                                <span class="flex flex-col"><span class="font-semibold" dir="{{ $l->direction }}">{{ $l->native_name }}</span><span class="text-xs text-muted">{{ $l->name }} · <span class="mono">{{ $l->code }}</span>{{ $l->direction === 'rtl' ? ' · right-to-left' : '' }}</span></span>
                            </span>
                        </td>
                        <td>
                            @if ($l->is_default)<span class="badge badge-navy">Default</span>
                            @elseif ($l->is_active)<span class="badge badge-ok">Live</span>
                            @else<span class="badge badge-warn">Draft</span>@endif
                        </td>
                        <td><span class="flex items-center gap-2"><span class="bar w-16"><span style="width: {{ $uiPct }}%"></span></span><span class="mono text-xs">{{ $uiPct }}%</span></span></td>
                        <td><span class="mono text-xs">{{ number_format($r['questions']) }} · {{ $qPct }}%</span>@if ($r['drafts'])<span class="block text-[11px] text-muted">{{ $r['drafts'] }} drafts</span>@endif</td>
                        <td class="mono text-xs">{{ $r['notes'] }}/{{ $totals['notes'] }}</td>
                        <td class="mono text-xs">{{ $r['decks'] }}/{{ $totals['decks'] }}</td>
                        <td class="mono text-xs">{{ $r['videos'] }}/{{ $totals['videos'] }}</td>
                        <td class="mono text-xs">{{ number_format($r['students']) }}</td>
                        <td class="text-right whitespace-nowrap">
                            <a href="{{ route('admin.languages.strings', $l->code) }}" wire:navigate class="btn btn-light btn-sm">{{ $l->is_default ? 'App text' : 'Translate app' }}</a>
                            @if (! $l->is_default)
                                <a href="{{ route('admin.questions', ['missing' => $l->code]) }}" wire:navigate class="btn btn-light btn-sm">Questions</a>
                                @if ($canManage)
                                    @if ($l->is_active)
                                        <button type="button" class="btn btn-light btn-sm" wire:click="ask('{{ $l->code }}', 'deactivate')">Move to draft</button>
                                    @else
                                        <button type="button" class="btn btn-teal btn-sm" wire:click="ask('{{ $l->code }}', 'activate')">Make live</button>
                                        <button type="button" class="btn btn-danger btn-sm" wire:click="ask('{{ $l->code }}', 'delete')" aria-label="Remove"><x-icon name="trash" size="14"/></button>
                                    @endif
                                @endif
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="grid gap-3.5 md:grid-cols-3 text-sm">
        <div class="card card-pad flex flex-col gap-1"><strong>1 · Add as draft</strong><span class="text-slate">Pick from the official language list. Students do not see drafts.</span></div>
        <div class="card card-pad flex flex-col gap-1"><strong>2 · Translate</strong><span class="text-slate">Translate the app text here, and questions, notes, cards and videos from their own editors (each has language tabs).</span></div>
        <div class="card card-pad flex flex-col gap-1"><strong>3 · Make live</strong><span class="text-slate">A super admin turns it on. Students can then choose it; untranslated items show in English.</span></div>
    </section>

    @if ($adding)
        <div class="fixed inset-0 z-50 bg-navy/40 flex items-center justify-center p-4">
            <form wire:submit="add" class="card card-pad w-full max-w-md flex flex-col gap-3">
                <div class="flex items-center justify-between"><h2 class="h-section">Add language</h2><button type="button" wire:click="$set('adding', null)"><x-icon name="x"/></button></div>
                <label class="label">Language
                    <select class="field" wire:model.live="adding.code">
                        <option value="">Choose…</option>
                        @foreach ($available as $code => [$name, $native])<option value="{{ $code }}">{{ $name }} — {{ $native }} ({{ $code }})</option>@endforeach
                    </select>
                    @error('adding.code')<span class="error">{{ $message }}</span>@enderror
                </label>
                <label class="label">Name shown to students<input class="field" wire:model="adding.native_name" dir="{{ $adding['direction'] }}">@error('adding.native_name')<span class="error">{{ $message }}</span>@enderror</label>
                <label class="label">Writing direction
                    <select class="field" wire:model="adding.direction"><option value="ltr">Left to right</option><option value="rtl">Right to left</option></select>
                </label>
                <p class="text-xs text-muted">It starts as a draft. Nothing changes for students until you make it live.</p>
                <div class="flex justify-end gap-2"><button type="button" class="btn btn-light" wire:click="$set('adding', null)">Cancel</button><button class="btn btn-primary">Add draft language</button></div>
            </form>
        </div>
    @endif

    @if ($pending)
        @php $row = $rows->first(fn ($r) => $r['lang']->code === $pending->code); $uiPct = $uiTotal ? round($row['ui'] / $uiTotal * 100) : 0; @endphp
        <div class="fixed inset-0 z-50 bg-navy/40 flex items-center justify-center p-4">
            <div class="card card-pad w-full max-w-md flex flex-col gap-3">
                <h2 class="h-section">
                    {{ ['activate' => 'Make '.$pending->native_name.' live?', 'deactivate' => 'Move '.$pending->native_name.' to draft?', 'delete' => 'Remove '.$pending->native_name.'?'][$confirmAction] }}
                </h2>
                @if ($confirmAction === 'activate')
                    <p class="text-sm text-slate">Students will be able to choose it in the app.</p>
                    <ul class="text-sm flex flex-col gap-1">
                        <li class="{{ $uiPct < 100 ? 'text-warn' : 'text-teal' }}">App text translated: {{ $uiPct }}%{{ $uiPct < 100 ? ' — missing text shows in English' : '' }}</li>
                        <li>Questions translated: {{ number_format($row['questions']) }} of {{ number_format($totals['questions']) }}</li>
                    </ul>
                @elseif ($confirmAction === 'deactivate')
                    <p class="text-sm text-slate">{{ number_format($row['students']) }} students use it. They will see English until it is live again. Their setting is kept.</p>
                @else
                    <p class="text-sm text-slate">Its app-text translations are deleted and students using it switch to English. Translated questions and notes stay stored. Type <strong class="mono">{{ $pending->code }}</strong> to confirm.</p>
                    <input class="field mono" wire:model="confirmText">
                    @error('confirmText')<span class="error">{{ $message }}</span>@enderror
                @endif
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn btn-light" wire:click="$set('confirming', null)">Cancel</button>
                    <button type="button" class="btn {{ $confirmAction === 'delete' ? 'btn-danger' : 'btn-primary' }}" wire:click="confirm">{{ ['activate' => 'Make live', 'deactivate' => 'Move to draft', 'delete' => 'Remove'][$confirmAction] }}</button>
                </div>
            </div>
        </div>
    @endif
</div>
