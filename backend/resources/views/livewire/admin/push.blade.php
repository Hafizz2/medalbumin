<div class="flex flex-col gap-[22px]">
    <header class="flex flex-col gap-1">
        <span class="eyebrow">Reach students on their phones · sent in each student’s app language</span>
        <h1 class="h-title">Push notifications</h1>
    </header>

    <div class="grid gap-3.5 grid-cols-1 xl:grid-cols-[minmax(0,5fr)_minmax(0,6fr)] items-start">
        <form wire:submit="send" class="card card-pad flex flex-col gap-3.5">
            <h2 class="h-section">Send a notification</h2>
            <div class="seg self-start">
                <button type="button" class="seg-btn" aria-pressed="{{ $kind === 'announcement' ? 'true' : 'false' }}" wire:click="$set('kind', 'announcement')">Announcement</button>
                <button type="button" class="seg-btn" aria-pressed="{{ $kind === 'promo' ? 'true' : 'false' }}" wire:click="$set('kind', 'promo')">Promotion / offer</button>
            </div>
            <span class="text-xs text-muted -mt-2">{{ $kind === 'promo' ? 'Shown as a card with an image and button. Students who switched promotions off will not get it.' : 'News for students, e.g. new features or schedule changes.' }}</span>
            <div class="grid grid-cols-2 gap-3">
                <label class="label">Who
                    <select class="field" wire:model.live="audience.plan">
                        <option value="all">All students</option><option value="free">Free users</option><option value="premium">Premium subscribers</option>
                    </select>
                    @error('audience.plan')<span class="error">{{ $message }}</span>@enderror
                </label>
                <label class="label">Target exam
                    <select class="field" wire:model.live="audience.target_exam">
                        <option value="">Any</option><option value="exit">Exit exam</option><option value="qualification">Qualification</option><option value="licensing">Licensing</option><option value="residency">Residency</option>
                    </select>
                </label>
                <label class="label">University
                    <select class="field" wire:model.live="audience.university_id">
                        <option value="">Any</option>
                        @foreach ($universities as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
                    </select>
                </label>
                <label class="label">Not active for
                    <select class="field" wire:model.live="audience.inactive_days">
                        <option value="">Anyone</option><option value="3">3+ days</option><option value="7">7+ days</option><option value="14">14+ days</option><option value="30">30+ days</option>
                    </select>
                </label>
            </div>
            <div class="px-3.5 py-2.5 rounded-2xl bg-teal-wash text-sm text-teal-ink">{{ $audienceLabel }} · <strong>{{ number_format($reach) }}</strong> students</div>

            <x-lang-field model="title" label="Title" :values="$title" placeholder="Exit Exam 2025 paper is live"/>
            <x-lang-field model="body" label="Message" :values="$body" textarea placeholder="Try 20 free questions tonight."/>
            <span class="text-xs text-muted">Students get the text in their app language; languages left empty receive English.</span>
            <label class="label">Image (optional · wide, at least 1200×600, under 2 MB)
                <input type="file" accept="image/png,image/jpeg,image/webp" class="field" wire:model="image">
                @error('image')<span class="error">{{ $message }}</span>@enderror
            </label>
            @if ($image && method_exists($image, 'temporaryUrl'))
                <img src="{{ $image->temporaryUrl() }}" alt="" class="rounded-2xl max-h-40 object-cover">
            @endif
            <x-lang-field model="actionLabel" label="Button text (optional)" :values="$actionLabel" placeholder="Get 30% off"/>
            <label class="label">When tapped, open
                <select class="field" wire:model="link">
                    @foreach ($links as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach
                </select>
            </label>
            <div class="flex flex-wrap items-end gap-3">
                <div class="seg">
                    <button type="button" class="seg-btn" aria-pressed="{{ $when === 'now' ? 'true' : 'false' }}" wire:click="$set('when', 'now')">Send now</button>
                    <button type="button" class="seg-btn" aria-pressed="{{ $when === 'later' ? 'true' : 'false' }}" wire:click="$set('when', 'later')">Schedule</button>
                </div>
                @if ($when === 'later')
                    <input type="datetime-local" class="field !w-auto" wire:model="scheduledAt">
                @endif
            </div>
            @error('scheduledAt')<span class="error">{{ $message }}</span>@enderror
            <button class="btn btn-primary self-end" wire:loading.attr="disabled" wire:confirm="Send this notification to {{ number_format($reach) }} students?">{{ $when === 'now' ? 'Send' : 'Schedule' }}</button>
        </form>

        <section class="card overflow-hidden">
            <h2 class="h-section px-5 pt-4 pb-3">History</h2>
            @forelse ($campaigns as $c)
                <div class="px-5 py-3 border-t border-divider flex items-start justify-between gap-3" wire:key="c-{{ $c->id }}">
                    <div class="min-w-0">
                        <div class="text-sm font-semibold">@if ($c->kind === 'promo')<span class="badge badge-warn mr-1">Promo</span>@endif{{ $c->getTranslation('title', 'en') }}</div>
                        <div class="text-[13px] text-slate">{{ $c->getTranslation('body', 'en') }}</div>
                        <div class="text-xs text-muted mt-1">{{ $sender->audienceLabel($c->audience ?? []) }} · {{ number_format($c->recipients_count) }} students · {{ $c->creator?->shortName() }} · {{ ($c->sent_at ?? $c->scheduled_at)?->format('j M, H:i') }}</div>
                    </div>
                    <div class="flex flex-col items-end gap-1.5 shrink-0">
                        <span class="badge {{ ['sent' => 'badge-ok', 'scheduled' => 'badge-sky', 'sending' => 'badge-warn', 'failed' => 'badge-stop'][$c->status] }}">{{ ucfirst($c->status) }}</span>
                        @if ($c->status === 'scheduled')<button type="button" class="text-xs font-semibold text-stop" wire:click="cancel({{ $c->id }})">Cancel</button>@endif
                    </div>
                </div>
            @empty
                <p class="px-5 pb-6 text-sm text-muted">No notifications sent yet.</p>
            @endforelse
            <div class="px-4 py-3">{{ $campaigns->links(data: ['scrollTo' => false]) }}</div>
        </section>
    </div>
</div>
