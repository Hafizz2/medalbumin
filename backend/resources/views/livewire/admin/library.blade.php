<div class="flex flex-col gap-[22px]">
    <header class="flex flex-wrap items-center justify-between gap-3.5">
        <div class="flex flex-col gap-1">
            <span class="eyebrow">Past papers · official flashcards · quick-reference tools · universities</span>
            <h1 class="h-title">Papers, cards &amp; tools</h1>
        </div>
        <button type="button" class="btn btn-primary" wire:click="edit"><x-icon name="plus" size="16" stroke="2.4"/>Add</button>
    </header>

    <div class="seg w-fit flex-wrap" role="tablist">
        @foreach (['papers' => 'Past papers', 'decks' => 'Flashcards', 'labs' => 'Lab values', 'drugs' => 'Drugs', 'universities' => 'Universities'] as $k => $label)
            <button type="button" role="tab" class="seg-btn" aria-selected="{{ $tab === $k ? 'true' : 'false' }}" wire:click="$set('tab', '{{ $k }}')">{{ $label }}</button>
        @endforeach
    </div>

    @if (in_array($tab, ['labs', 'drugs'], true))
        <div class="px-4 py-3 rounded-2xl bg-warn-bg text-warn text-sm">Doses and ranges must be checked by the medical team against the national treatment guideline. Students see: “For exam study, not patient care.”</div>
    @endif

    <section class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                @if ($tab === 'papers')
                    <thead><tr><th>Paper</th><th>Tab</th><th>Year / label</th><th>Questions</th><th>Access</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($papers as $p)
                        <tr wire:key="pp-{{ $p->id }}">
                            <td class="font-semibold">{{ $p->name }}@if ($p->description)<span class="block text-xs text-muted font-normal">{{ $p->description }}</span>@endif</td>
                            <td>{{ \App\Models\Paper::CATEGORIES[$p->category] }}{{ $p->code ? ' · '.$p->code : '' }}</td>
                            <td class="mono">{{ $p->year }} {{ $p->ec_label ? '· '.$p->ec_label : '' }}</td>
                            <td><a href="{{ route('admin.questions', ['paper' => $p->id]) }}" wire:navigate class="mono">{{ $p->questions_count }}</a></td>
                            <td>{{ $p->is_premium ? 'Premium' : 'Free' }}</td>
                            <td><span class="badge {{ $p->status === 'published' ? 'badge-ok' : 'badge-muted' }}">{{ ucfirst($p->status) }}</span></td>
                            <td class="text-right whitespace-nowrap"><button type="button" class="btn btn-light btn-sm" wire:click="edit({{ $p->id }})">Edit</button> <button type="button" class="btn btn-danger btn-sm" wire:click="delete({{ $p->id }})" wire:confirm="Delete this paper?">Delete</button></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-10">No past papers yet. Add one, then attach questions to it in the Question bank.</td></tr>
                    @endforelse
                    </tbody>
                @elseif ($tab === 'decks')
                    <thead><tr><th>Deck</th><th>Linked to</th><th>Cards</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($decks as $d)
                        <tr wire:key="dk-{{ $d->id }}" class="{{ $deck === $d->id ? 'row-sel' : '' }}">
                            <td class="font-semibold">{{ $d->title }}</td>
                            <td>{{ $d->pathLabel() }}</td>
                            <td class="mono">{{ $d->cards_count }}</td>
                            <td><span class="badge {{ $d->status === 'published' ? 'badge-ok' : 'badge-muted' }}">{{ ucfirst($d->status) }}</span></td>
                            <td class="text-right whitespace-nowrap">
                                <button type="button" class="btn btn-teal btn-sm" wire:click="$set('deck', {{ $d->id }})">Cards</button>
                                <button type="button" class="btn btn-light btn-sm" wire:click="edit({{ $d->id }})">Edit</button>
                                <button type="button" class="btn btn-danger btn-sm" wire:click="delete({{ $d->id }})" wire:confirm="Delete this deck and its cards?">Delete</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-10">No official decks yet.</td></tr>
                    @endforelse
                    </tbody>
                @elseif ($tab === 'labs')
                    <thead><tr><th>Category</th><th>Test</th><th>Normal range</th><th>Unit</th><th>Shown</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($labs as $l)
                        <tr wire:key="lb-{{ $l->id }}"><td class="text-muted">{{ $l->category }}</td><td class="font-semibold">{{ $l->name }}</td><td class="mono">{{ $l->range }}</td><td>{{ $l->unit }}</td><td>{{ $l->is_active ? 'Yes' : 'No' }}</td>
                            <td class="text-right whitespace-nowrap"><button type="button" class="btn btn-light btn-sm" wire:click="edit({{ $l->id }})">Edit</button> <button type="button" class="btn btn-danger btn-sm" wire:click="delete({{ $l->id }})" wire:confirm="Delete?">Delete</button></td></tr>
                    @endforeach
                    </tbody>
                @elseif ($tab === 'drugs')
                    <thead><tr><th>Drug</th><th>Use</th><th>Dose</th><th>Watch</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($drugs as $d)
                        <tr wire:key="dr-{{ $d->id }}"><td class="font-semibold">{{ $d->name }}</td><td class="text-muted">{{ $d->category }}</td><td class="text-[13px] max-w-sm">{{ $d->dose }}</td><td class="text-[13px] max-w-sm text-slate">{{ $d->watch }}</td>
                            <td class="text-right whitespace-nowrap"><button type="button" class="btn btn-light btn-sm" wire:click="edit({{ $d->id }})">Edit</button> <button type="button" class="btn btn-danger btn-sm" wire:click="delete({{ $d->id }})" wire:confirm="Delete?">Delete</button></td></tr>
                    @endforeach
                    </tbody>
                @else
                    <thead><tr><th>University</th><th>Short</th><th>City</th><th>Students</th><th>Shown</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($universities as $u)
                        <tr wire:key="un-{{ $u->id }}"><td class="font-semibold">{{ $u->name }}</td><td>{{ $u->short_name }}</td><td>{{ $u->city }}</td><td class="mono">{{ number_format($u->users_count) }}</td><td>{{ $u->is_active ? 'Yes' : 'No' }}</td>
                            <td class="text-right whitespace-nowrap"><button type="button" class="btn btn-light btn-sm" wire:click="edit({{ $u->id }})">Edit</button> <button type="button" class="btn btn-danger btn-sm" wire:click="delete({{ $u->id }})" wire:confirm="Delete?">Delete</button></td></tr>
                    @endforeach
                    </tbody>
                @endif
            </table>
        </div>
    </section>

    @if ($tab === 'decks' && $deck)
        <section class="card overflow-hidden">
            <div class="flex items-center justify-between px-5 pt-4 pb-3">
                <h2 class="h-section">Cards</h2>
                <button type="button" class="btn btn-light btn-sm" wire:click="editCard">Add card</button>
            </div>
            @foreach ($cards as $c)
                <div class="px-5 py-3 border-t border-divider flex items-start justify-between gap-3" wire:key="cd-{{ $c->id }}">
                    <div class="text-sm"><span class="font-semibold">{{ $c->front }}</span>{{ $c->prompt ? ' — '.$c->prompt : '' }}<span class="block text-teal font-medium">{{ $c->back }}</span><span class="block text-xs text-muted">{{ $c->detail }}</span></div>
                    <div class="flex gap-1.5 shrink-0"><button type="button" class="btn btn-light btn-sm" wire:click="editCard({{ $c->id }})">Edit</button><button type="button" class="btn btn-danger btn-sm" wire:click="deleteCard({{ $c->id }})" wire:confirm="Delete card?">×</button></div>
                </div>
            @endforeach
        </section>
    @endif

    @if ($card)
        <div class="fixed inset-0 z-50 bg-navy/40 flex items-center justify-center p-4">
            <form wire:submit="saveCard" class="card card-pad w-full max-w-lg flex flex-col gap-3">
                <div class="flex items-center justify-between"><h2 class="h-section">{{ $card['id'] ? 'Edit card' : 'New card' }}</h2><button type="button" wire:click="$set('card', null)"><x-icon name="x"/></button></div>
                <x-lang-field model="card.front" label="Front (topic)" :values="$card['front'] ?? []" placeholder="Paracetamol overdose"/>
                <x-lang-field model="card.prompt" label="Question" :values="$card['prompt'] ?? []" placeholder="What is the antidote, and when is it most effective?"/>
                <x-lang-field model="card.back" label="Answer" :values="$card['back'] ?? []" placeholder="N-acetylcysteine (NAC)"/>
                <x-lang-field model="card.detail" label="Detail" :values="$card['detail'] ?? []" textarea/>
                <div class="flex justify-end gap-2"><button type="button" class="btn btn-light" wire:click="$set('card', null)">Close</button><button class="btn btn-primary">Save card</button></div>
            </form>
        </div>
    @endif

    @if ($form)
        <div class="fixed inset-0 z-50 bg-navy/40 flex items-center justify-center p-4">
            <form wire:submit="save" class="card card-pad w-full max-w-lg flex flex-col gap-3 max-h-[90vh] overflow-auto">
                <div class="flex items-center justify-between"><h2 class="h-section">{{ $form['id'] ? 'Edit' : 'Add' }}</h2><button type="button" wire:click="$set('form', null)"><x-icon name="x"/></button></div>
                @if ($tab === 'papers')
                    <div class="grid grid-cols-2 gap-3">
                        <label class="label">Tab<select class="field" wire:model.live="form.category"><option value="exit">Exit exam</option><option value="qualification">Qualification</option><option value="block">Block exams</option></select></label>
                        <label class="label">Status<select class="field" wire:model="form.status"><option value="draft">Draft</option><option value="published">Published</option></select></label>
                        <label class="label col-span-2">Name<input class="field" wire:model="form.name" placeholder="National Exit Exam">@error('form.name')<span class="error">{{ $message }}</span>@enderror</label>
                        <label class="label">Year (Gregorian)<input type="number" class="field" wire:model="form.year"></label>
                        <label class="label">Label<input class="field" wire:model="form.ec_label" placeholder="2017 EC / C-I"></label>
                        @if (($form['category'] ?? '') === 'block')
                            <label class="label">System code<input class="field" wire:model="form.code" placeholder="CVS">@error('form.code')<span class="error">{{ $message }}</span>@enderror</label>
                        @endif
                        <label class="label">Description<input class="field" wire:model="form.description" placeholder="All systems / Cardiovascular system"></label>
                        <label class="label">Time limit (min)<input type="number" class="field" wire:model="form.time_limit_minutes" placeholder="Auto"></label>
                        <label class="label">Order<input type="number" class="field" wire:model="form.sort"></label>
                    </div>
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="accent-teal w-4 h-4" wire:model="form.is_premium">Premium only</label>
                @elseif ($tab === 'decks')
                    <x-lang-field model="form.title" label="Title" :values="$form['title'] ?? []"/>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="label">Department<select class="field" wire:model.live="form.department_id"><option value="">All</option>@foreach ($departments as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach</select></label>
                        <label class="label">Subcategory<select class="field" wire:model="form.subcategory_id"><option value="">—</option>@foreach ($subcategories as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</select></label>
                        <label class="label">Status<select class="field" wire:model="form.status"><option value="published">Published</option><option value="draft">Draft</option></select></label>
                        <label class="label">Order<input type="number" class="field" wire:model="form.sort"></label>
                    </div>
                @elseif ($tab === 'labs')
                    <x-lang-field model="form.name" label="Test" :values="$form['name'] ?? []" placeholder="Sodium"/>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="label">Category<input class="field" wire:model="form.category">@error('form.category')<span class="error">{{ $message }}</span>@enderror</label>
                        <label class="label">Normal range<input class="field" wire:model="form.range">@error('form.range')<span class="error">{{ $message }}</span>@enderror</label>
                        <label class="label">Unit<input class="field" wire:model="form.unit"></label>
                        <label class="label">Order<input type="number" class="field" wire:model="form.sort"></label>
                    </div>
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="accent-teal w-4 h-4" wire:model="form.is_active">Shown in the app</label>
                @elseif ($tab === 'drugs')
                    <x-lang-field model="form.name" label="Drug" :values="$form['name'] ?? []"/>
                    <x-lang-field model="form.category" label="Use" :values="$form['category'] ?? []" placeholder="OB/GYN · eclampsia"/>
                    <x-lang-field model="form.dose" label="Dose" :values="$form['dose'] ?? []" textarea rows="3"/>
                    <x-lang-field model="form.watch" label="What to watch" :values="$form['watch'] ?? []" textarea/>
                    <div class="grid grid-cols-2 gap-3 items-end">
                        <label class="label">Order<input type="number" class="field" wire:model="form.sort"></label>
                        <label class="flex items-center gap-2 text-sm h-11"><input type="checkbox" class="accent-teal w-4 h-4" wire:model="form.is_active">Shown in the app</label>
                    </div>
                @else
                    <label class="label">Name<input class="field" wire:model="form.name">@error('form.name')<span class="error">{{ $message }}</span>@enderror</label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="label">Short name<input class="field" wire:model="form.short_name"></label>
                        <label class="label">City<input class="field" wire:model="form.city"></label>
                        <label class="label">Order<input type="number" class="field" wire:model="form.sort"></label>
                    </div>
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="accent-teal w-4 h-4" wire:model="form.is_active">Shown in sign-up list</label>
                @endif
                <div class="flex justify-end gap-2 pt-2"><button type="button" class="btn btn-light" wire:click="$set('form', null)">Cancel</button><button class="btn btn-primary">Save</button></div>
            </form>
        </div>
    @endif
</div>
