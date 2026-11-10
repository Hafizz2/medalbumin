<div class="flex flex-col gap-[22px]">
    <header class="flex flex-wrap items-center justify-between gap-3.5">
        <div class="flex flex-col gap-1">
            <span class="eyebrow">Student doubts under questions · {{ number_format($weekPosts) }} posts this week</span>
            <h1 class="h-title">Community</h1>
        </div>
        <div class="seg" role="tablist" aria-label="Queue">
            <button type="button" role="tab" class="seg-btn" aria-selected="{{ $tab === 'doubt' ? 'true' : 'false' }}" wire:click="$set('tab', 'doubt')">Unanswered doubts <span class="mono text-[11px] ml-1">{{ $counts['doubt'] }}</span></button>
            <button type="button" role="tab" class="seg-btn" aria-selected="{{ $tab === 'flag' ? 'true' : 'false' }}" wire:click="$set('tab', 'flag')">Flagged posts <span class="mono text-[11px] ml-1">{{ $counts['flag'] }}</span></button>
        </div>
    </header>

    <div class="grid gap-3.5 grid-cols-1 xl:grid-cols-[minmax(0,5fr)_minmax(0,6fr)] items-start">
        <section class="card overflow-hidden">
            @forelse ($items as $i)
                <button type="button" wire:click="$set('selected', {{ $i->id }})" wire:key="cm-{{ $i->id }}"
                        class="w-full text-left px-[18px] py-3.5 flex flex-col gap-1.5 border-b border-divider cursor-pointer {{ $selected === $i->id ? 'row-sel' : 'bg-white hover:bg-page' }}">
                    <span class="flex items-center justify-between gap-2">
                        <span class="mono text-xs text-muted">{{ $i->question?->code }}</span>
                        <span class="badge {{ $tab === 'doubt' ? 'badge-warn' : 'badge-danger' }}">{{ $tab === 'doubt' ? 'Needs answer' : $i->flags_count.' flags' }}</span>
                    </span>
                    <span class="text-sm font-medium line-clamp-2">{{ $i->body }}</span>
                    <span class="text-xs text-muted">{{ $i->user?->shortName() }}{{ $i->user?->university ? ' · '.$i->user->university->name : '' }} · {{ $i->created_at->diffForHumans() }}</span>
                </button>
            @empty
                <p class="px-5 py-12 text-center text-sm text-muted">{{ $tab === 'doubt' ? 'Every doubt has an answer.' : 'No flagged posts.' }}</p>
            @endforelse
            <div class="px-4 py-3">{{ $items->links(data: ['scrollTo' => false]) }}</div>
        </section>

        <section class="card card-pad flex flex-col gap-4">
            @if ($current)
                <span class="text-[13px] text-muted">On question {{ $current->question?->code }} ·
                    @can('edit content')<a href="{{ route('admin.questions', ['selected' => $current->question_id]) }}" wire:navigate>open in editor</a>@endcan
                </span>
                <div class="px-3.5 py-3 rounded-2xl bg-page text-sm">{{ $current->question?->getTranslation('stem', 'en') }}</div>
                <div class="flex items-start gap-3">
                    <span class="w-9 h-9 rounded-full bg-sky-bg text-sky flex items-center justify-center text-xs font-semibold shrink-0">{{ $current->user?->initials() }}</span>
                    <span class="flex flex-col gap-1"><span class="text-[13px] font-semibold">{{ $current->user?->shortName() }} <span class="text-muted font-normal">· {{ $current->created_at->diffForHumans() }}</span></span><span class="text-sm">{{ $current->body }}</span></span>
                </div>
                @foreach ($current->replies as $r)
                    <div class="ml-12 text-sm px-3 py-2 rounded-xl {{ $r->is_faculty ? 'bg-teal-wash' : 'bg-page' }}"><span class="font-semibold">{{ $r->user?->shortName() }}{{ $r->is_faculty ? ' · faculty' : '' }}:</span> {{ $r->body }}</div>
                @endforeach

                @if ($tab === 'doubt')
                    <label class="label">Faculty answer
                        <textarea rows="5" class="field" wire:model="answer" placeholder="Explain clearly, cite the guideline if useful."></textarea>
                        @error('answer')<span class="error">{{ $message }}</span>@enderror
                    </label>
                    <div class="flex flex-wrap gap-x-5 gap-y-2 text-sm">
                        <label class="flex items-center gap-2"><input type="checkbox" class="accent-teal w-4 h-4" wire:model="pin">Pin as verified answer</label>
                        @can('edit content')<label class="flex items-center gap-2"><input type="checkbox" class="accent-teal w-4 h-4" wire:model="addToExplanation">Also add to the explanation</label>@endcan
                        <label class="flex items-center gap-2"><input type="checkbox" class="accent-teal w-4 h-4" wire:model="notify">Notify the student</label>
                    </div>
                    <button type="button" class="btn btn-teal self-end" wire:click="postAnswer" wire:loading.attr="disabled">Post verified answer</button>
                @else
                    @php $reasons = $current->flags->groupBy('reason')->map->count(); @endphp
                    <div class="px-3.5 py-2.5 rounded-2xl bg-danger-bg text-sm text-warn">
                        Flagged by {{ $current->flags_count }} students · reason: {{ $reasons->keys()->map(fn ($k) => \App\Models\CommentFlag::REASONS[$k] ?? $k)->implode(', ') }}
                    </div>
                    <div class="flex flex-wrap gap-2 justify-end">
                        <button type="button" class="btn btn-light" wire:click="moderate('keep')">Keep post</button>
                        <button type="button" class="btn btn-light" wire:click="moderate('warn')">Warn user</button>
                        <button type="button" class="btn btn-danger" wire:click="moderate('hide')">Hide post</button>
                    </div>
                @endif
            @else
                <p class="text-sm text-muted text-center py-14">Nothing selected.</p>
            @endif
        </section>
    </div>
</div>
