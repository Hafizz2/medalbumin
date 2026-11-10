<div class="flex flex-col gap-[22px]">
    <header class="flex flex-wrap items-center justify-between gap-3.5">
        <div class="flex flex-col gap-1">
            <span class="eyebrow">Grand mocks, department tests, past papers, daily minis</span>
            <h1 class="h-title">Exam builder</h1>
        </div>
        <button type="button" class="btn btn-primary" wire:click="newExam"><x-icon name="plus" size="16" stroke="2.4"/>New exam</button>
    </header>

    <div class="grid gap-3.5 grid-cols-1 xl:grid-cols-[minmax(0,4fr)_minmax(0,7fr)] items-start">
        <section class="card overflow-hidden" aria-label="Exams">
            @forelse ($exams as $e)
                @php
                    $badge = ['scheduled' => 'badge-sky', 'live' => 'badge-warn', 'results_out' => 'badge-ok', 'published' => 'badge-ok', 'automatic' => 'badge-muted', 'draft' => 'badge-warn'][$e->status];
                    $label = ['scheduled' => 'Scheduled', 'live' => 'Live', 'results_out' => 'Results out', 'published' => 'Published', 'automatic' => 'Automatic', 'draft' => 'Draft'][$e->status];
                    $meta = match (true) {
                        $e->completed_count > 0 => number_format($e->completed_count).' took it · average '.round($e->avg_score).'%',
                        $e->type === 'daily' => $e->question_count.' Q every day, weak-topic mix',
                        $e->status === 'draft' => 'Not scheduled yet',
                        default => ($e->starts_at?->format('D j M · H:i') ?? '').' · '.number_format($e->registrations_count).' registered',
                    };
                @endphp
                <button type="button" wire:click="select({{ $e->id }})" wire:key="e-{{ $e->id }}" class="w-full text-left px-[18px] py-3.5 flex flex-col gap-1 border-b border-divider cursor-pointer {{ $selected === $e->id ? 'row-sel' : 'bg-white hover:bg-page' }}">
                    <span class="flex items-start justify-between gap-2"><span class="text-sm font-semibold">{{ $e->title }}</span><span class="badge {{ $badge }}">{{ $label }}</span></span>
                    <span class="text-xs text-muted">{{ $meta }}</span>
                </button>
            @empty
                <p class="px-5 py-12 text-center text-sm text-muted">No exams yet.</p>
            @endforelse
        </section>

        <section class="card card-pad flex flex-col gap-4" aria-label="Build exam">
            <div class="grid gap-3 grid-cols-[repeat(auto-fit,minmax(200px,1fr))]">
                <label class="label sm:col-span-2">Exam name<input class="field" wire:model="form.title">@error('form.title')<span class="error">{{ $message }}</span>@enderror</label>
                @if (in_array($form['type'], ['grand', 'department'], true))
                    <label class="label">Date &amp; time<input type="datetime-local" class="field" wire:model="form.starts_at">@error('form.starts_at')<span class="error">{{ $message }}</span>@enderror</label>
                @endif
                <label class="label">Duration (minutes)<input type="number" class="field" wire:model="form.duration_minutes"></label>
                <label class="label">Who can take it
                    <select class="field" wire:model.live="form.access"><option value="all">All students (free)</option><option value="premium">Premium only</option><option value="university">One university</option></select>
                </label>
                @if ($form['access'] === 'university')
                    <label class="label">University<select class="field" wire:model="form.university_id"><option value="">Choose…</option>@foreach ($universities as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select>@error('form.university_id')<span class="error">{{ $message }}</span>@enderror</label>
                @endif
            </div>

            <div class="flex flex-wrap gap-2" role="radiogroup" aria-label="Exam type">
                @foreach (\App\Models\Exam::TYPES as $k => $label)
                    <button type="button" role="radio" aria-checked="{{ $form['type'] === $k ? 'true' : 'false' }}" aria-pressed="{{ $form['type'] === $k ? 'true' : 'false' }}" class="chip" wire:click="$set('form.type', '{{ $k }}')">{{ $label }}</button>
                @endforeach
            </div>

            @if ($form['type'] === 'past_paper')
                <label class="label">Past paper
                    <select class="field" wire:model="form.paper_id"><option value="">Choose…</option>@foreach ($papers as $p)<option value="{{ $p->id }}">{{ $p->label() }} · {{ \App\Models\Paper::CATEGORIES[$p->category] }}</option>@endforeach</select>
                    @error('form.paper_id')<span class="error">{{ $message }}</span>@enderror
                </label>
            @elseif ($form['type'] === 'daily')
                <label class="label">Questions per day<input type="number" class="field" wire:model="form.question_count"></label>
                <p class="text-sm text-muted">Each student gets their own mix: half from their weakest subcategories, half unused questions.</p>
            @else
                <div class="flex flex-col gap-2.5">
                    <div class="flex justify-between items-baseline"><h2 class="h-section text-lg">Blueprint · questions per department</h2><span class="mono text-sm {{ $form['question_count'] && $total !== (int) $form['question_count'] ? 'text-danger' : 'text-teal' }}">{{ $total }}{{ $form['type'] === 'grand' ? ' / '.($form['question_count'] ?: 200) : '' }}</span></div>
                    @php $colors = ['#017F8E', '#0596A3', '#2550A8', '#6B3FB8', '#D4A72C', '#475569', '#B4441A']; @endphp
                    <div class="flex h-3 rounded-full overflow-hidden bg-divider">
                        @foreach ($departments as $i => $d)
                            @if (($blueprint[$d->id] ?? 0) > 0)<span style="width: {{ $total ? ($blueprint[$d->id] / $total * 100) : 0 }}%; background: {{ $colors[$i % 7] }}"></span>@endif
                        @endforeach
                    </div>
                    <div class="rounded-2xl border border-line overflow-hidden">
                        @foreach ($departments as $i => $d)
                            <div class="flex items-center gap-2.5 px-3 min-h-[50px] {{ $i ? 'border-t border-divider' : '' }}" wire:key="bp-{{ $d->id }}">
                                <span class="w-2.5 h-2.5 rounded-full" style="background: {{ $colors[$i % 7] }}"></span>
                                <span class="flex-1 text-sm font-medium">{{ $d->name }}</span>
                                <span class="text-xs text-muted">{{ number_format($available[$d->id] ?? 0) }} available</span>
                                <button type="button" class="w-8 h-8 rounded-lg border border-line" wire:click="step({{ $d->id }}, -5)" aria-label="Fewer">−</button>
                                <input type="number" class="mono w-14 text-center text-sm border-0 outline-0" wire:model.live.debounce.400ms="blueprint.{{ $d->id }}">
                                <button type="button" class="w-8 h-8 rounded-lg border border-line" wire:click="step({{ $d->id }}, 5)" aria-label="More">+</button>
                            </div>
                        @endforeach
                    </div>
                    @error('blueprint')<span class="error">{{ $message }}</span>@enderror
                    @if ($form['type'] === 'grand')
                        <label class="label max-w-[200px]">Target total<input type="number" class="field" wire:model.live="form.question_count"></label>
                    @endif
                </div>
            @endif

            <div class="flex flex-col rounded-2xl border border-line overflow-hidden">
                @foreach (['shuffle' => 'Shuffle question order', 'shuffle_options' => 'Shuffle answer options', 'allow_back' => 'Allow going back', 'show_answers' => 'Show answers after it ends', 'leaderboard' => 'National leaderboard'] as $k => $label)
                    <button type="button" role="switch" aria-checked="{{ ($rules[$k] ?? false) ? 'true' : 'false' }}" wire:click="toggleRule('{{ $k }}')" class="flex items-center justify-between px-4 min-h-[48px] text-sm text-left {{ $loop->first ? '' : 'border-t border-divider' }}">
                        <span>{{ $label }}</span><span class="switch" aria-checked="{{ ($rules[$k] ?? false) ? 'true' : 'false' }}"><span></span></span>
                    </button>
                @endforeach
            </div>

            @if ($current)
                <span class="text-[13px] text-muted">{{ number_format($current->registrations_count) }} registered · {{ $current->question_count }} questions picked · reminder push 1 day and 1 hour before</span>
            @endif
            @if ($form['type'] === 'grand' && (! $current || $current->status === 'draft'))
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="accent-teal w-4 h-4" wire:model="notify">Notify students when scheduled</label>
            @endif

            <div class="flex flex-wrap justify-end gap-2 pt-3 border-t border-divider">
                @if ($current)
                    <button type="button" class="btn btn-danger mr-auto" wire:click="delete" wire:confirm="Delete this exam?">Delete</button>
                    @if ($current->type === 'grand' && $current->status === 'scheduled' && $current->starts_at?->isPast())
                        <button type="button" class="btn btn-light" wire:click="publishResults">Publish results</button>
                    @endif
                @endif
                <button type="button" class="btn btn-light" wire:click="save('draft')">Save draft</button>
                <button type="button" class="btn btn-teal" wire:click="save('schedule')" wire:loading.attr="disabled">{{ in_array($form['type'], ['grand', 'department'], true) ? 'Schedule & notify students' : 'Publish' }}</button>
            </div>
        </section>
    </div>
</div>
