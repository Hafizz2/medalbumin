<div class="flex flex-col gap-[22px]">
    <header class="flex flex-wrap items-center justify-between gap-3.5">
        <div class="flex flex-col gap-1">
            <span class="eyebrow">{{ number_format($counts['questions']) }} questions · {{ $counts['papers'] }} past papers</span>
            <h1 class="h-title">Question bank</h1>
        </div>
        <div class="flex flex-wrap gap-2.5">
            <button type="button" class="btn btn-light" wire:click="$set('showImport', true)"><x-icon name="upload" size="16"/>Import Excel / CSV</button>
            <button type="button" class="btn btn-primary" wire:click="newQuestion"><x-icon name="plus" size="16" stroke="2.4"/>New question</button>
        </div>
    </header>

    {{-- Filters --}}
    <div class="flex flex-wrap gap-2">
        <label class="h-10 min-w-[220px] rounded-xl bg-white border border-line flex items-center gap-2 px-3">
            <x-icon name="search" size="15" class="text-muted"/>
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Code or text" class="flex-1 border-0 outline-0 bg-transparent text-sm">
        </label>
        <select wire:model.live="department" class="field !h-10 !w-auto">
            <option value="">All subjects</option>
            @foreach ($departments as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach
        </select>
        <select wire:model.live="paper" class="field !h-10 !w-auto">
            <option value="">All exams</option>
            @foreach ($papers as $p)<option value="{{ $p->id }}">{{ $p->label() }}</option>@endforeach
        </select>
        <select wire:model.live="year" class="field !h-10 !w-auto">
            <option value="">Any year</option>
            @foreach ($years as $y)<option>{{ $y }}</option>@endforeach
        </select>
        <select wire:model.live="status" class="field !h-10 !w-auto">
            <option value="">Any status</option>
            <option value="published">Published</option>
            <option value="in_review">In review</option>
            <option value="draft">Draft</option>
            <option value="reported">Reported</option>
        </select>
        <select wire:model.live="missing" class="field !h-10 !w-auto">
            <option value="">Any language</option>
            @foreach ($locales as $code => $name)
                @continue($code === 'en')
                <option value="{{ $code }}">Missing {{ $name }}</option>
            @endforeach
        </select>
        <button type="button" class="chip" aria-pressed="{{ $hasImage ? 'true' : 'false' }}" wire:click="$toggle('hasImage')"><x-icon name="image" size="15"/>Has image</button>
    </div>

    <div class="grid gap-3.5 grid-cols-1 xl:grid-cols-[minmax(0,5fr)_minmax(0,7fr)] items-start">
        {{-- List --}}
        <section class="card overflow-hidden">
            @forelse ($questions as $q)
                @php
                    $st = $q->open_reports_count ? ['Reported', 'badge-danger'] : match ($q->status) {
                        'published' => ['Published', 'badge-ok'], 'in_review' => ['In review', 'badge-warn'], default => ['Draft', 'badge-muted'],
                    };
                @endphp
                <button type="button" wire:click="select({{ $q->id }})" wire:key="q-{{ $q->id }}"
                        class="w-full text-left px-[18px] py-3.5 flex flex-col gap-1.5 border-b border-divider cursor-pointer {{ $selected === $q->id ? 'row-sel' : 'bg-white hover:bg-page' }}">
                    <span class="flex items-center justify-between gap-2"><span class="mono text-xs text-muted">{{ $q->code }}</span><span class="badge {{ $st[1] }}">{{ $st[0] }}</span></span>
                    <span class="text-sm font-medium line-clamp-2">{{ $q->getTranslation('stem', 'en') }}</span>
                    <span class="text-xs text-muted">{{ $q->subcategory?->name ?? $q->department?->name }}{{ $q->sourceLabel() ? ' · '.$q->sourceLabel() : '' }}{{ $q->image_path ? ' · Image' : '' }}</span>
                </button>
            @empty
                <div class="p-8 text-center text-sm text-muted">No questions match these filters.<br><button class="btn btn-light btn-sm mt-3" wire:click="newQuestion">Write the first one</button></div>
            @endforelse
            <div class="px-4 py-3">{{ $questions->links(data: ['scrollTo' => false]) }}</div>
        </section>

        {{-- Editor --}}
        @if ($selected || $creating)
            @php
                $state = fn ($l) => $l === 'en' ? 'ok' : (($translation_status[$l] ?? null) === 'published' ? 'ok' : (isset($translation_status[$l]) ? 'draft' : 'none'));
                $hasText = $locale === 'en' || isset($translation_status[$locale]);
                $dir = \App\Models\Language::isRtl($locale) ? 'rtl' : 'ltr';
            @endphp
            <section class="card card-pad flex flex-col gap-4" aria-label="Edit question">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="h-section">{{ $current ? 'Edit · '.$current->code : 'New question' }}</h2>
                    <div class="seg" role="tablist" aria-label="Content language">
                        @foreach ($locales as $code => $name)
                            <button type="button" role="tab" aria-selected="{{ $locale === $code ? 'true' : 'false' }}" wire:click="$set('locale', '{{ $code }}')" class="seg-btn flex items-center gap-1.5">
                                <span class="w-[7px] h-[7px] rounded-full {{ ['ok' => 'bg-teal', 'draft' => 'bg-danger-2', 'none' => 'bg-field-2'][$state($code)] }}"></span>{{ $name }}
                            </button>
                        @endforeach
                    </div>
                </div>

                @if ($hasText)
                    @if ($locale !== 'en')
                        <div class="flex items-center justify-between gap-3 px-3.5 py-2.5 rounded-2xl bg-page text-[13px]">
                            <span>{{ $locales[$locale] }} translation is <strong>{{ ($translation_status[$locale] ?? 'draft') === 'published' ? 'published' : 'a draft' }}</strong>. Students see English until it is published.</span>
                            @if (($translation_status[$locale] ?? null) === 'published')
                                <button type="button" class="btn btn-light btn-sm" wire:click="setTranslationStatus('draft')">Unpublish</button>
                            @else
                                <button type="button" class="btn btn-teal btn-sm" wire:click="setTranslationStatus('published')">Mark ready</button>
                            @endif
                        </div>
                    @endif

                    <label class="label">Question stem
                        <textarea dir="{{ $dir }}" rows="4" class="field" wire:model="stem.{{ $locale }}"></textarea>
                        @error('stem.en')<span class="error">{{ $message }}</span>@enderror
                    </label>

                    @if ($locale === 'en')
                        <div class="flex flex-col gap-2">
                            @if ($image)
                                <img src="{{ $image->temporaryUrl() }}" alt="" class="max-h-56 rounded-2xl border border-line object-contain bg-page">
                            @elseif ($current?->image_path && ! $removeImage)
                                <img src="{{ $current->imageUrl() }}" alt="" class="max-h-56 rounded-2xl border border-line object-contain bg-page">
                            @endif
                            <div class="flex flex-wrap items-center gap-2">
                                <label class="btn btn-light btn-sm cursor-pointer"><x-icon name="image" size="15"/>{{ $current?->image_path ? 'Replace image' : 'Add image · X-ray, ECG, slide' }}
                                    <input type="file" accept="image/*" class="sr-only" wire:model="image">
                                </label>
                                @if ($current?->image_path && ! $image)
                                    <button type="button" class="btn btn-light btn-sm" wire:click="$toggle('removeImage')">{{ $removeImage ? 'Keep image' : 'Remove image' }}</button>
                                @endif
                                <div wire:loading wire:target="image" class="text-xs text-muted">Uploading…</div>
                            </div>
                            @error('image')<span class="error">{{ $message }}</span>@enderror
                            @if ($image || ($current?->image_path && ! $removeImage))
                                <div class="grid sm:grid-cols-[1fr_160px] gap-2">
                                    <input class="field" placeholder="Caption, e.g. Lead II · 25 mm/s · 10 mm/mV" wire:model="image_caption">
                                    <select class="field" wire:model="image_type">
                                        <option value="">Image type</option>
                                        @foreach (['ecg' => 'ECG', 'xray' => 'X-ray', 'ct' => 'CT / MRI', 'histology' => 'Histology', 'photo' => 'Clinical photo', 'other' => 'Other'] as $k => $v)
                                            <option value="{{ $k }}">{{ $v }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                        </div>
                    @endif

                    <fieldset class="flex flex-col gap-2">
                        <legend class="text-[13px] font-semibold text-slate mb-2">Options · select the correct answer</legend>
                        @foreach (['A', 'B', 'C', 'D', 'E'] as $k)
                            <label class="flex items-center gap-3 min-h-[52px] px-3 rounded-2xl border-[1.5px] {{ $correct === $k ? 'border-teal bg-teal-soft' : 'border-line bg-white' }}">
                                <input type="radio" value="{{ $k }}" wire:model.live="correct" class="accent-teal w-4 h-4">
                                <span class="w-[30px] h-[30px] rounded-[10px] flex items-center justify-center font-semibold text-sm shrink-0 {{ $correct === $k ? 'bg-teal text-white' : 'bg-chip-2' }}">{{ $k }}</span>
                                <input dir="{{ $dir }}" class="flex-1 bg-transparent border-0 outline-0 text-sm" placeholder="{{ $k === 'E' || $k === 'D' ? 'Optional' : '' }}" wire:model="options.{{ $k }}.{{ $locale }}">
                                @if ($correct === $k)<span class="text-xs font-semibold text-teal">Correct</span>@endif
                            </label>
                        @endforeach
                        @error('options.A.en')<span class="error">{{ $message }}</span>@enderror
                        @error('options.B.en')<span class="error">{{ $message }}</span>@enderror
                        @error('correct')<span class="error">{{ $message }}</span>@enderror
                    </fieldset>

                    <label class="label">Explanation
                        <textarea dir="{{ $dir }}" rows="4" class="field" wire:model="explanation.{{ $locale }}"></textarea>
                    </label>
                @else
                    <div class="rounded-2xl bg-page px-5 py-8 flex flex-col items-center text-center gap-2">
                        <span class="font-display text-lg font-semibold">No {{ $locales[$locale] }} version yet</span>
                        <span class="text-sm text-muted max-w-md">Students using {{ $locales[$locale] }} will see the English version until a translation is published.</span>
                        <button type="button" class="btn btn-primary mt-2" wire:click="addTranslation">Add {{ $locales[$locale] }} translation</button>
                    </div>
                @endif

                <div class="grid gap-3 grid-cols-[repeat(auto-fit,minmax(170px,1fr))]">
                    <label class="label">Department
                        <select class="field" wire:model.live="department_id">
                            @foreach ($departments as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach
                        </select>
                    </label>
                    <label class="label">Subcategory
                        <select class="field" wire:model.live="subcategory_id">
                            <option value="">—</option>
                            @foreach ($subcategories as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                        </select>
                    </label>
                    <label class="label">Topic
                        <select class="field" wire:model="topic_id">
                            <option value="">—</option>
                            @foreach ($topics as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach
                        </select>
                    </label>
                    <label class="label">Exam / paper
                        <select class="field" wire:model="paper_id">
                            <option value="">—</option>
                            @foreach ($papers as $p)<option value="{{ $p->id }}">{{ $p->label() }}</option>@endforeach
                        </select>
                    </label>
                    <label class="label">Year
                        <input type="number" class="field" wire:model="exam_year" placeholder="e.g. 2024">
                    </label>
                    <label class="label">Difficulty
                        <select class="field" wire:model="difficulty">
                            <option value="easy">Easy</option><option value="moderate">Moderate</option><option value="hard">Hard</option>
                        </select>
                    </label>
                </div>
                <label class="flex items-center gap-2.5 text-sm font-medium">
                    <input type="checkbox" class="accent-teal w-4 h-4" wire:model="is_free"> Free question (available without a subscription)
                </label>

                <div class="flex flex-wrap items-center justify-between gap-3 pt-3 border-t border-divider">
                    <span class="text-xs text-muted">
                        @if ($current)
                            Last edited by {{ $current->editor?->shortName() ?? $current->author?->shortName() ?? '—' }} · {{ $current->updated_at->diffForHumans() }}
                            @if ($current->attempts_count) · {{ number_format($current->attempts_count) }} attempts, {{ $current->correctPct() }}% correct @endif
                        @endif
                    </span>
                    <div class="flex flex-wrap gap-2">
                        @if ($current)
                            <button type="button" class="btn btn-danger" wire:click="delete" wire:confirm="Delete {{ $current->code }}? Students will no longer see it.">Delete</button>
                        @endif
                        <button type="button" class="btn btn-light" wire:click="save('draft')" wire:loading.attr="disabled">Save draft</button>
                        @can('publish content')
                            <button type="button" class="btn btn-teal" wire:click="save('publish')" wire:loading.attr="disabled">Publish</button>
                        @else
                            <button type="button" class="btn btn-teal" wire:click="save('review')" wire:loading.attr="disabled">Send for review</button>
                        @endcan
                    </div>
                </div>
            </section>
        @else
            <section class="card card-pad text-center text-sm text-muted py-16">Select a question to edit, or create a new one.</section>
        @endif
    </div>

    {{-- Import dialog --}}
    @if ($showImport)
        <div class="fixed inset-0 z-50 bg-navy/40 flex items-center justify-center p-4" wire:keydown.escape="$set('showImport', false)">
            <div class="card card-pad w-full max-w-lg flex flex-col gap-4">
                <div class="flex items-center justify-between"><h2 class="h-section">Import questions</h2><button type="button" wire:click="$set('showImport', false)" aria-label="Close"><x-icon name="x"/></button></div>
                <p class="text-sm text-muted">Upload an Excel (.xlsx) or CSV file with one question per row. Required columns: <span class="mono text-xs">department, stem, option_a, option_b, correct</span>. Add <span class="mono text-xs">_am</span> columns for Amharic.</p>
                <button type="button" class="btn btn-light btn-sm self-start" wire:click="downloadTemplate"><x-icon name="download" size="15"/>Download template</button>
                <input type="file" accept=".csv,.xlsx" wire:model="importFile" class="field !h-auto py-2">
                @error('importFile')<span class="error">{{ $message }}</span>@enderror
                <label class="label">Import as
                    <select class="field" wire:model="importStatus">
                        <option value="draft">Drafts</option>
                        <option value="in_review">Waiting for review</option>
                        @can('publish content')<option value="published">Published</option>@endcan
                    </select>
                </label>
                @if ($importResult)
                    <div class="rounded-2xl bg-page p-3 text-sm flex flex-col gap-1 max-h-48 overflow-auto">
                        <strong>{{ $importResult['created'] }} imported, {{ count($importResult['errors']) }} rows skipped.</strong>
                        @foreach ($importResult['errors'] as $line => $msg)
                            <span class="text-xs text-danger">Row {{ $line }}: {{ $msg }}</span>
                        @endforeach
                    </div>
                @endif
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn btn-light" wire:click="$set('showImport', false)">Close</button>
                    <button type="button" class="btn btn-primary" wire:click="import" wire:loading.attr="disabled" wire:target="import,importFile">Import</button>
                </div>
            </div>
        </div>
    @endif
</div>
