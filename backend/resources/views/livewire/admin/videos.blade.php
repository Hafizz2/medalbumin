<div class="flex flex-col gap-[22px]">
    <header class="flex flex-wrap items-center justify-between gap-3.5">
        <div class="flex flex-col gap-1">
            <span class="eyebrow">Lessons linked to departments, subcategories and questions</span>
            <h1 class="h-title">Video library</h1>
        </div>
        <button type="button" class="btn btn-primary" wire:click="newVideo"><x-icon name="upload" size="16"/>Upload video</button>
    </header>

    <section class="grid gap-3.5 grid-cols-[repeat(auto-fit,minmax(min(200px,100%),1fr))]">
        @foreach ($kpis as [$label, $value, $sub])
            <div class="card px-5 py-4 flex flex-col gap-1"><span class="text-[13px] text-muted">{{ $label }}</span><span class="font-display text-[28px] font-bold tracking-[-0.03em]">{{ $value }}</span><span class="text-xs text-muted">{{ $sub }}</span></div>
        @endforeach
    </section>

    <div class="grid gap-3.5 grid-cols-1 2xl:grid-cols-[minmax(0,7fr)_minmax(0,5fr)] items-start">
        <section class="card overflow-hidden">
            <div class="flex flex-wrap gap-2 px-5 pt-4 pb-3">
                <button type="button" class="chip" aria-pressed="{{ ! $department ? 'true' : 'false' }}" wire:click="$set('department', null)">All</button>
                @foreach ($departments as $d)
                    <button type="button" class="chip" aria-pressed="{{ (string) $department === (string) $d->id ? 'true' : 'false' }}" wire:click="$set('department', '{{ $d->id }}')">{{ $d->short_name ?: $d->name }}</button>
                @endforeach
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Lesson</th><th>Linked to</th><th>Length</th><th>Views</th><th>Watched to end</th><th>Access</th></tr></thead>
                    <tbody>
                    @forelse ($videos as $v)
                        @php $pct = $v->completionPct(); @endphp
                        <tr wire:key="v-{{ $v->id }}" wire:click="edit({{ $v->id }})" class="cursor-pointer {{ $selected === $v->id ? 'row-sel' : 'hover:bg-page' }}">
                            <td><span class="flex items-center gap-2.5">
                                @if ($v->thumbnailUrl())<img src="{{ $v->thumbnailUrl() }}" alt="" class="w-14 h-9 rounded-lg object-cover shrink-0">@else<span class="w-14 h-9 rounded-lg bg-navy text-accent flex items-center justify-center shrink-0"><x-icon name="play" size="14"/></span>@endif
                                <span class="flex flex-col"><span class="font-semibold">{{ $v->title }}</span>@if ($v->status !== 'published')<span class="text-xs {{ $v->status === 'failed' ? 'text-stop' : 'text-muted' }}">{{ ucfirst($v->status) }}</span>@endif</span>
                            </span></td>
                            <td class="text-slate">{{ $v->breadcrumb() }}</td>
                            <td class="mono">{{ $v->durationLabel() }}</td>
                            <td class="mono">{{ number_format($v->views_count) }}</td>
                            <td><span class="flex items-center gap-2"><span class="bar w-16"><span style="width: {{ $pct }}%; {{ $pct < 50 ? 'background:#D9692F' : '' }}"></span></span><span class="mono text-xs">{{ $pct }}%</span></span></td>
                            <td><span class="badge {{ $v->access === 'free' ? 'badge-ok' : 'badge-navy' }}">{{ ucfirst($v->access) }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-10">No lessons yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3">{{ $videos->links(data: ['scrollTo' => false]) }}</div>
        </section>

        @if ($editing)
            <section class="card card-pad flex flex-col gap-3.5" aria-label="Upload">
                <h2 class="h-section">{{ $selected ? 'Edit lesson' : 'New lesson' }}</h2>
                <div class="seg w-fit">
                    @foreach (['upload' => 'Upload file', 'youtube' => 'YouTube', 'url' => 'Video link'] as $k => $label)
                        <button type="button" class="seg-btn" aria-pressed="{{ $form['source'] === $k ? 'true' : 'false' }}" wire:click="$set('form.source', '{{ $k }}')">{{ $label }}</button>
                    @endforeach
                </div>
                @if ($form['source'] === 'upload')
                    <div x-data="{ progress: 0, uploading: false }" x-on:livewire-upload-start="uploading = true" x-on:livewire-upload-finish="uploading = false" x-on:livewire-upload-progress="progress = $event.detail.progress" class="flex flex-col gap-2">
                        <input type="file" accept="video/*" class="field !h-auto py-2" wire:model="file">
                        <div x-show="uploading" class="flex flex-col gap-1"><span class="bar"><span :style="`width:${progress}%`"></span></span><span class="text-xs text-muted" x-text="progress + '%'"></span></div>
                        @if ($current?->status === 'processing')<span class="text-xs text-muted">Compressing… refresh in a few minutes.</span>@endif
                        <span class="text-xs text-muted">Compressed to 720p and 360p for slow connections automatically.</span>
                        @error('file')<span class="error">{{ $message }}</span>@enderror
                    </div>
                @else
                    <label class="label">{{ $form['source'] === 'youtube' ? 'YouTube link (unlisted works)' : 'Direct link to an .mp4 / .m3u8' }}<input class="field" wire:model="form.url" placeholder="https://">@error('form.url')<span class="error">{{ $message }}</span>@enderror</label>
                    <label class="label max-w-[160px]">Length (mm:ss)<input class="field" wire:model="form.duration" placeholder="14:20">@error('form.duration')<span class="error">{{ $message }}</span>@enderror</label>
                @endif

                <x-lang-field model="form.title" label="Title" :values="$form['title'] ?? []"/>
                <div class="grid grid-cols-2 gap-3">
                    <label class="label">Department<select class="field" wire:model.live="form.department_id">@foreach ($departments as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach</select></label>
                    <label class="label">Subcategory<select class="field" wire:model.live="form.subcategory_id"><option value="">—</option>@foreach ($subcategories as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</select></label>
                    <label class="label">Topic<select class="field" wire:model="form.topic_id"><option value="">—</option>@foreach ($topics as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach</select></label>
                    <label class="label">Lecturer<input class="field" wire:model="form.lecturer"></label>
                    <label class="label">Access<select class="field" wire:model="form.access"><option value="premium">Premium</option><option value="free">Free</option></select></label>
                    <label class="label">Thumbnail<input type="file" accept="image/*" class="field !h-auto py-2" wire:model="thumbnail"></label>
                </div>
                <x-lang-field model="form.description" label="Description" :values="$form['description'] ?? []" textarea/>
                <label class="label">Chapters — one per line, “mm:ss Title”<textarea rows="4" class="field mono text-[13px]" wire:model="form.chapters" placeholder="00:00 Definition and first 5 minutes&#10;03:40 Benzodiazepine first line"></textarea></label>
                <div class="grid grid-cols-2 gap-3">
                    @foreach (\App\Models\Language::cached() as $l)
                        <label class="label">Captions {{ $l->native_name }} (.vtt) @if ($current?->captions[$l->code] ?? null)<span class="text-teal font-normal">✓ uploaded</span>@endif<input type="file" accept=".vtt" class="field !h-auto py-2" wire:model="captionFiles.{{ $l->code }}"></label>
                    @endforeach
                </div>
                <label class="label">Linked questions (codes)<input class="field mono text-[13px]" wire:model="form.questions" placeholder="Q-00012, Q-00013"></label>
                <div class="flex flex-wrap justify-end gap-2 pt-3 border-t border-divider">
                    @if ($selected)<button type="button" class="btn btn-danger mr-auto" wire:click="delete" wire:confirm="Delete this lesson and its files?">Delete</button>@endif
                    <button type="button" class="btn btn-light" wire:click="$set('editing', false)">Close</button>
                    <button type="button" class="btn btn-light" wire:click="save(false)" wire:loading.attr="disabled" wire:target="save,file">Save draft</button>
                    <button type="button" class="btn btn-teal" wire:click="save(true)" wire:loading.attr="disabled" wire:target="save,file">Publish</button>
                </div>
            </section>
        @endif
    </div>
</div>
