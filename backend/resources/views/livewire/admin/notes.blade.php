<div class="flex flex-col gap-[22px]">
    <header class="flex flex-wrap items-center justify-between gap-3.5">
        <div class="flex flex-col gap-1">
            <span class="eyebrow">Department › Subcategory › Questions &amp; notes</span>
            <h1 class="h-title">Departments &amp; notes</h1>
        </div>
        <div class="flex flex-wrap gap-2.5">
            <button type="button" class="btn btn-light" wire:click="editDept">Add department</button>
            @if ($subcategory)
                <button type="button" class="btn btn-primary" wire:click="newNote"><x-icon name="plus" size="16" stroke="2.4"/>New official note</button>
            @endif
        </div>
    </header>

    <div class="grid gap-3.5 grid-cols-1 lg:grid-cols-[260px_minmax(0,1fr)] 2xl:grid-cols-[260px_320px_minmax(0,1fr)] items-start">
        {{-- Tree --}}
        <section class="card p-2" aria-label="Department tree">
            @foreach ($departments as $d)
                @php $isOpen = $open[$d->id] ?? false; @endphp
                <div wire:key="d-{{ $d->id }}">
                    <div class="flex items-center rounded-xl {{ $isOpen ? 'bg-page' : '' }}">
                        <button type="button" aria-expanded="{{ $isOpen ? 'true' : 'false' }}" wire:click="toggle({{ $d->id }})" class="flex-1 flex items-center gap-2 px-2.5 py-2.5 text-left cursor-pointer">
                            <x-icon name="chevron-right" size="14" class="transition-transform {{ $isOpen ? 'rotate-90' : '' }}"/>
                            <span class="flex-1 text-sm font-semibold {{ $d->is_active ? '' : 'text-muted line-through' }}">{{ $d->name }}</span>
                            <span class="mono text-[11px] text-muted">{{ number_format($d->questions_count) }}</span>
                        </button>
                        <button type="button" class="p-2 text-muted hover:text-navy" wire:click="editDept({{ $d->id }})" aria-label="Edit department"><x-icon name="edit" size="14"/></button>
                    </div>
                    @if ($isOpen)
                        <div class="flex flex-col pl-6 pr-1 py-1">
                            @foreach ($d->subcategories as $s)
                                <div class="flex items-center group">
                                    <button type="button" wire:click="pickSub({{ $s->id }})" class="flex-1 flex items-center justify-between gap-2 px-2.5 py-2 rounded-lg text-left text-[13px] cursor-pointer {{ $sub === $s->id ? 'bg-teal-soft text-teal-ink font-semibold' : 'hover:bg-page' }}">
                                        <span>{{ $s->name }}</span><span class="text-[11px] text-muted">{{ $s->notes_count }} notes</span>
                                    </button>
                                    <button type="button" class="p-1.5 text-muted opacity-0 group-hover:opacity-100" wire:click="editSub({{ $s->id }})" aria-label="Edit subcategory"><x-icon name="edit" size="13"/></button>
                                </div>
                            @endforeach
                            <button type="button" class="text-left text-[13px] font-semibold text-teal px-2.5 py-2 cursor-pointer" wire:click="editSub(null, {{ $d->id }})">+ Add subcategory</button>
                        </div>
                    @endif
                </div>
            @endforeach
        </section>

        {{-- Notes in subcategory --}}
        <section class="card overflow-hidden" aria-label="Notes in subcategory">
            @if ($subcategory)
                <div class="px-[18px] pt-4 pb-3">
                    <span class="text-xs text-muted">{{ $subcategory->department->name }} ›</span>
                    <h2 class="h-section">{{ $subcategory->name }}</h2>
                </div>
                @forelse ($notes as $n)
                    <button type="button" wire:click="openNote({{ $n->id }})" wire:key="n-{{ $n->id }}" class="w-full text-left px-[18px] py-3 flex flex-col gap-1 border-t border-divider cursor-pointer {{ $note === $n->id ? 'row-sel' : 'bg-white hover:bg-page' }}">
                        <span class="flex items-start justify-between gap-2"><span class="text-sm font-semibold">{{ $n->getTranslation('title', 'en') }}</span>
                            <span class="badge {{ ['published' => 'badge-ok', 'in_review' => 'badge-warn', 'draft' => 'badge-muted'][$n->status] }}">{{ ['published' => 'Published', 'in_review' => 'In review', 'draft' => 'Draft'][$n->status] }}</span></span>
                        <span class="text-xs text-muted">{{ $n->status === 'published' ? number_format($n->reads_count).' reads · ' : '' }}edited {{ $n->updated_at->diffForHumans() }}</span>
                    </button>
                @empty
                    <p class="px-[18px] py-10 text-sm text-muted border-t border-divider text-center">No notes yet.<br><button type="button" class="btn btn-light btn-sm mt-3" wire:click="newNote">Write the first note</button></p>
                @endforelse
            @else
                <p class="p-8 text-sm text-muted text-center">Pick a subcategory.</p>
            @endif
        </section>

        {{-- Editor --}}
        <section class="card card-pad flex flex-col gap-3.5 lg:col-span-2 2xl:col-span-1" aria-label="Note editor">
            @if ($subcategory && ($current || $creating))
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="h-section">{{ $current ? 'Edit note' : 'New note' }}</h2>
                    <div class="seg">
                        @foreach (\App\Models\Language::options() as $code => $label)
                            <button type="button" class="seg-btn flex items-center gap-1.5" aria-pressed="{{ $locale === $code ? 'true' : 'false' }}" wire:click="$set('locale', '{{ $code }}')">
                                <span class="w-[7px] h-[7px] rounded-full {{ filled($body[$code] ?? null) ? 'bg-teal' : 'bg-field-2' }}"></span>{{ $label }}
                            </button>
                        @endforeach
                    </div>
                </div>
                <label class="label">Title
                    <input class="field" wire:model="title.{{ $locale }}" placeholder="{{ $locale === 'en' ? 'e.g. Stroke: localising the lesion in 5 minutes' : 'Title in this language (optional — falls back to English)' }}" dir="{{ \App\Models\Language::isRtl($locale) ? 'rtl' : 'ltr' }}">
                    @error('title.en')<span class="error">{{ $message }}</span>@enderror
                </label>

                <div wire:key="editor-{{ $note ?? 'new' }}-{{ $locale }}" x-data="noteEditor($wire.entangle('body.{{ $locale }}'))" x-on:note-image.window="run('image', $event.detail.url)" class="rounded-2xl border border-field overflow-hidden">
                    <div role="toolbar" aria-label="Formatting" class="flex flex-wrap items-center gap-1 px-2 py-1.5 border-b border-divider bg-page">
                        @foreach ([['bold', 'B', 'font-bold'], ['italic', 'I', 'italic'], ['h2', 'H2', ''], ['h3', 'H3', ''], ['mark', 'A', 'bg-[#FFF1B8] px-1 rounded'], ['bullet', '•', ''], ['ordered', '1.', '']] as [$cmd, $label, $cls])
                            <button type="button" class="h-8 min-w-8 px-2 rounded-lg text-sm hover:bg-white" :class="active.{{ $cmd }} && 'bg-white shadow-sm'" @click="run('{{ $cmd }}')" aria-label="{{ $cmd }}"><span class="{{ $cls }}">{{ $label }}</span></button>
                        @endforeach
                        <span class="w-px h-5 bg-line mx-1"></span>
                        <button type="button" class="h-8 px-2 rounded-lg text-[13px] hover:bg-white" @click="run('table')">Table</button>
                        <template x-if="active.table">
                            <span class="flex gap-1">
                                <button type="button" class="h-8 px-2 rounded-lg text-xs hover:bg-white" @click="run('addRow')">+ row</button>
                                <button type="button" class="h-8 px-2 rounded-lg text-xs hover:bg-white" @click="run('addCol')">+ col</button>
                                <button type="button" class="h-8 px-2 rounded-lg text-xs hover:bg-white" @click="run('delRow')">− row</button>
                                <button type="button" class="h-8 px-2 rounded-lg text-xs text-stop hover:bg-white" @click="run('delTable')">× table</button>
                            </span>
                        </template>
                        <label class="h-8 px-2 rounded-lg text-[13px] hover:bg-white flex items-center cursor-pointer" title="Insert image"><x-icon name="image" size="15"/><input type="file" accept="image/*" class="sr-only" wire:model="editorImage"></label>
                        <span wire:loading wire:target="editorImage" class="text-xs text-muted">Uploading…</span>
                    </div>
                    <div wire:ignore x-ref="editor" dir="{{ \App\Models\Language::isRtl($locale) ? 'rtl' : 'ltr' }}" class="bg-white max-h-[60vh] overflow-y-auto"></div>
                </div>
                @error('body.en')<span class="error">{{ $message }}</span>@enderror

                <div class="grid gap-3 grid-cols-[repeat(auto-fit,minmax(170px,1fr))]">
                    <label class="label">Who can see it
                        <select class="field" wire:model="visibility"><option value="all">All students</option><option value="premium">Premium only</option></select>
                    </label>
                    <label class="label">Link to questions (topic)
                        <select class="field" wire:model.live="topic_id">
                            <option value="">Whole subcategory</option>
                            @foreach ($subcategory->topics as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach
                        </select>
                        @if ($topicQuestions !== null)<span class="text-xs text-muted font-normal">{{ $topicQuestions }} published questions</span>@endif
                    </label>
                    <label class="label">PDF attachment
                        <input type="file" accept="application/pdf" class="field !h-auto py-2" wire:model="attachment">
                        @if ($current?->attachment_path && ! $attachment)
                            <span class="text-xs font-normal"><a href="{{ Storage::disk('public')->url($current->attachment_path) }}" target="_blank">Current PDF</a> · <button type="button" class="text-stop" wire:click="$toggle('removeAttachment')">{{ $removeAttachment ? 'keep' : 'remove' }}</button></span>
                        @endif
                    </label>
                </div>

                @if ($current)
                    <div class="px-3.5 py-2.5 rounded-2xl bg-teal-wash text-[13px] text-teal-ink">
                        Students see this in their Notes tab and inside {{ $subcategory->name }}. {{ number_format($current->user_notes_count) }} students have added their own private notes on it.
                    </div>
                @endif

                <div class="flex flex-wrap justify-end gap-2 pt-3 border-t border-divider">
                    @if ($current)<button type="button" class="btn btn-danger mr-auto" wire:click="deleteNote" wire:confirm="Delete this note?">Delete</button>@endif
                    <button type="button" class="btn btn-light" wire:click="saveNote('draft')">Save draft</button>
                    @can('publish content')
                        <button type="button" class="btn btn-teal" wire:click="saveNote('publish')">Publish to students</button>
                    @else
                        <button type="button" class="btn btn-teal" wire:click="saveNote('review')">Send for review</button>
                    @endcan
                </div>
            @else
                <p class="text-sm text-muted text-center py-16">Select a note or create a new one.</p>
            @endif
        </section>
    </div>

    @if ($dept)
        <div class="fixed inset-0 z-50 bg-navy/40 flex items-center justify-center p-4">
            <form wire:submit="saveDept" class="card card-pad w-full max-w-md flex flex-col gap-3">
                <div class="flex items-center justify-between"><h2 class="h-section">{{ $dept['id'] ? 'Edit department' : 'New department' }}</h2><button type="button" wire:click="$set('dept', null)"><x-icon name="x"/></button></div>
                <x-lang-field model="dept.name" label="Name" :values="$dept['name'] ?? []"/>
                <div class="grid grid-cols-2 gap-3">
                    <label class="label">Short name<input class="field" wire:model="dept.short_name"></label>
                    <label class="label">Icon
                        <select class="field" wire:model="dept.icon">@foreach (['steth', 'blade', 'baby', 'female', 'globe', 'eye', 'heart', 'brain', 'kidney'] as $i)<option>{{ $i }}</option>@endforeach</select>
                    </label>
                    <label class="label">Tile colour<input type="color" class="field !p-1" wire:model="dept.color_bg"></label>
                    <label class="label">Icon colour<input type="color" class="field !p-1" wire:model="dept.color_ink"></label>
                </div>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="accent-teal w-4 h-4" wire:model="dept.is_active">Visible to students</label>
                <div class="flex justify-end gap-2"><button type="button" class="btn btn-light" wire:click="$set('dept', null)">Cancel</button><button class="btn btn-primary">Save</button></div>
            </form>
        </div>
    @endif

    @if ($subForm)
        <div class="fixed inset-0 z-50 bg-navy/40 flex items-center justify-center p-4">
            <form wire:submit="saveSub" class="card card-pad w-full max-w-md flex flex-col gap-3">
                <div class="flex items-center justify-between"><h2 class="h-section">{{ $subForm['id'] ? 'Edit subcategory' : 'New subcategory' }}</h2><button type="button" wire:click="$set('subForm', null)"><x-icon name="x"/></button></div>
                <x-lang-field model="subForm.name" label="Name" :values="$subForm['name'] ?? []"/>
                <label class="label">Topics — one per line<textarea rows="6" class="field" wire:model="subForm.topics" placeholder="Heart failure&#10;Arrhythmias"></textarea></label>
                <div class="flex justify-end gap-2">
                    @if ($subForm['id'])<button type="button" class="btn btn-danger mr-auto" wire:click="deleteSub" wire:confirm="Delete this subcategory?">Delete</button>@endif
                    <button type="button" class="btn btn-light" wire:click="$set('subForm', null)">Cancel</button><button class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    @endif
</div>
