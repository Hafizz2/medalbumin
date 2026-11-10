<div class="flex flex-col gap-[22px]">
    <header class="flex flex-wrap items-center justify-between gap-3.5">
        <div class="flex flex-col gap-1">
            <span class="eyebrow">Problems students found in questions · student ID checks</span>
            <h1 class="h-title">Reported</h1>
        </div>
        <div class="seg" role="tablist">
            <button type="button" role="tab" class="seg-btn" aria-selected="{{ $tab === 'questions' ? 'true' : 'false' }}" wire:click="$set('tab', 'questions')">Reported questions <span class="mono text-[11px] ml-1">{{ $openCount }}</span></button>
            <button type="button" role="tab" class="seg-btn" aria-selected="{{ $tab === 'ids' ? 'true' : 'false' }}" wire:click="$set('tab', 'ids')">Student IDs <span class="mono text-[11px] ml-1">{{ $idCount }}</span></button>
        </div>
    </header>

    @if ($tab === 'questions')
        <div class="grid gap-3.5 grid-cols-[repeat(auto-fill,minmax(min(380px,100%),1fr))]">
            @forelse ($grouped as $g)
                @php $q = $questions[$g->question_id] ?? null; $rows = $details[$g->question_id] ?? collect(); $top = $rows->groupBy('reason')->sortByDesc(fn ($r) => $r->count())->keys()->first(); @endphp
                <article class="card card-pad flex flex-col gap-3" wire:key="r-{{ $g->question_id }}">
                    <div class="flex items-center justify-between gap-2">
                        <span class="badge {{ $top === 'wrong_key' ? 'badge-danger' : ($top === 'translation' ? 'badge-plum' : 'badge-sky') }}">{{ $reasons[$top] ?? 'Other' }}</span>
                        <span class="mono text-xs text-muted">{{ $q?->code }} · {{ $g->total }} {{ Str::plural('report', $g->total) }}</span>
                    </div>
                    <span class="text-sm font-semibold line-clamp-2">{{ $q?->getTranslation('stem', 'en') ?? 'Deleted question' }}</span>
                    <div class="flex flex-col gap-1.5 max-h-40 overflow-auto">
                        @foreach ($rows as $r)
                            <div class="text-[13px] text-slate px-3 py-2 rounded-xl bg-page">
                                @if ($r->note)“{{ $r->note }}”@else<span class="text-muted">{{ $reasons[$r->reason] }}, no comment</span>@endif
                                <span class="block text-[11px] text-muted mt-0.5">{{ $r->user?->shortName() }} · {{ strtoupper($r->locale) }} · {{ $r->created_at->diffForHumans() }}</span>
                            </div>
                        @endforeach
                    </div>
                    <div class="flex flex-wrap gap-2 mt-auto">
                        @if ($q && ! $q->trashed())
                            <a href="{{ route('admin.questions', ['selected' => $q->id]) }}" wire:navigate class="btn btn-primary btn-sm">Open in editor</a>
                        @endif
                        <button type="button" class="btn btn-teal btn-sm" wire:click="close({{ $g->question_id }}, 'resolved')">Fixed</button>
                        <button type="button" class="btn btn-light btn-sm" wire:click="close({{ $g->question_id }}, 'dismissed')">Dismiss</button>
                    </div>
                </article>
            @empty
                <div class="card card-pad text-center text-sm text-muted py-14 col-span-full">No open reports. Students can report a question from the explanation screen.</div>
            @endforelse
        </div>
        {{ $grouped->links(data: ['scrollTo' => false]) }}
    @else
        <div class="grid gap-3.5 grid-cols-[repeat(auto-fill,minmax(min(340px,100%),1fr))]">
            @forelse ($ids as $v)
                <article class="card card-pad flex flex-col gap-3" wire:key="v-{{ $v->id }}">
                    <a href="{{ route('admin.verifications.image', $v) }}" target="_blank" class="block rounded-2xl overflow-hidden border border-line bg-page">
                        <img src="{{ route('admin.verifications.image', $v) }}" alt="Student ID" class="w-full h-48 object-cover">
                    </a>
                    <div>
                        <div class="font-semibold">{{ $v->user?->name ?? $v->user?->phone }}</div>
                        <div class="text-xs text-muted">{{ $v->user?->university?->name ?? 'No university set' }} · ID {{ $v->student_id_number ?? '—' }} · {{ $v->created_at->diffForHumans() }}</div>
                    </div>
                    <input class="field" placeholder="Note to student (optional)" wire:model="notes.{{ $v->id }}">
                    <div class="flex gap-2">
                        <button type="button" class="btn btn-danger btn-sm flex-1" wire:click="decideId({{ $v->id }}, 'rejected')">Reject</button>
                        <button type="button" class="btn btn-teal btn-sm flex-1" wire:click="decideId({{ $v->id }}, 'approved')">Approve</button>
                    </div>
                </article>
            @empty
                <div class="card card-pad text-center text-sm text-muted py-14 col-span-full">No student IDs waiting.</div>
            @endforelse
        </div>
        {{ $ids->links(data: ['scrollTo' => false]) }}
    @endif
</div>
