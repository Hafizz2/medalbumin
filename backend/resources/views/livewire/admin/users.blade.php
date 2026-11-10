<div class="flex flex-col gap-[22px]">
    <header class="flex flex-wrap items-center justify-between gap-3.5">
        <div class="flex flex-col gap-1">
            <span class="eyebrow">{{ number_format($counts['all']) }} students · {{ number_format($counts['subscribers']) }} subscribed</span>
            <h1 class="h-title">Users</h1>
        </div>
        <div class="flex flex-wrap gap-2.5">
            <label class="h-11 min-w-[260px] rounded-[14px] bg-white border border-line flex items-center gap-2.5 px-3.5">
                <x-icon name="search" size="16" class="text-muted"/>
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Name, phone or email" class="flex-1 border-0 outline-0 bg-transparent text-sm">
            </label>
            @can('view analytics')<button type="button" class="btn btn-light" wire:click="export"><x-icon name="download" size="16"/>Export CSV</button>@endcan
        </div>
    </header>

    <div class="grid gap-3.5 grid-cols-1 2xl:grid-cols-[minmax(0,1fr)_400px] items-start">
        <section class="card overflow-hidden">
            <div class="px-5 pt-4 pb-3">
                <div class="seg w-fit flex-wrap" role="tablist" aria-label="User filter">
                    @foreach (['all' => 'All', 'subscribers' => 'Subscribers', 'pending' => 'Pending approval', 'suspended' => 'Suspended'] as $k => $label)
                        <button type="button" role="tab" class="seg-btn" aria-selected="{{ $tab === $k ? 'true' : 'false' }}" wire:click="$set('tab', '{{ $k }}')">{{ $label }} <span class="mono text-[11px] text-muted ml-1">{{ number_format($counts[$k]) }}</span></button>
                    @endforeach
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Student</th><th>University</th><th>Plan</th><th>Devices</th><th>Last active</th><th>Status</th></tr></thead>
                    <tbody>
                    @forelse ($users as $u)
                        @php
                            $status = $u->status === 'suspended' ? ['Suspended', 'badge-stop'] : ($u->has_pending_payment ? ['Pending', 'badge-warn'] : ($u->activeSubscription ? ['Active', 'badge-ok'] : ['Free', 'badge-muted']));
                        @endphp
                        <tr wire:key="u-{{ $u->id }}" wire:click="$set('selected', {{ $u->id }})" class="cursor-pointer {{ $selected === $u->id ? 'row-sel' : 'hover:bg-page' }}">
                            <td>
                                <span class="flex items-center gap-2.5">
                                    <span class="w-8 h-8 rounded-full bg-sky-bg text-sky flex items-center justify-center text-xs font-semibold shrink-0">{{ $u->initials() }}</span>
                                    <span class="flex flex-col"><span class="font-semibold">{{ $u->name ?: $u->phone }}</span><span class="text-xs text-muted">{{ collect([$u->program, $u->study_year])->filter()->implode(' · ') ?: '—' }}</span></span>
                                </span>
                            </td>
                            <td class="text-slate">{{ $u->university?->name ?? '—' }}</td>
                            <td>{{ $u->has_pending_payment && ! $u->activeSubscription ? 'Awaiting receipt' : ($u->activeSubscription?->plan?->name ?? ($u->activeSubscription ? 'Premium' : 'Free')) }}</td>
                            <td class="mono {{ $u->active_devices_count > $maxDevices ? 'text-danger' : '' }}">{{ $u->active_devices_count }}/{{ $maxDevices }}</td>
                            <td class="text-slate whitespace-nowrap">{{ $u->last_active_at?->diffForHumans() ?? 'Never' }}</td>
                            <td><span class="badge {{ $status[1] }}">{{ $status[0] }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-10">No students found.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3">{{ $users->links(data: ['scrollTo' => false]) }}</div>
        </section>

        <section class="card card-pad flex flex-col gap-4 2xl:sticky 2xl:top-6">
            @if ($current)
                <div class="flex items-center gap-3">
                    <span class="w-12 h-12 rounded-full bg-sky-bg text-sky flex items-center justify-center font-semibold">{{ $current->initials() }}</span>
                    <div class="min-w-0">
                        <h2 class="h-section truncate">{{ $current->name ?: 'Unnamed student' }}</h2>
                        <span class="text-sm text-muted">{{ $current->phone ?? $current->email }}</span>
                    </div>
                </div>
                <dl class="grid grid-cols-[110px_1fr] gap-y-1.5 text-sm">
                    <dt class="text-muted">University</dt><dd>{{ $current->university?->name ?? '—' }}</dd>
                    <dt class="text-muted">Program</dt><dd>{{ collect([$current->program, $current->study_year])->filter()->implode(' · ') ?: '—' }}</dd>
                    <dt class="text-muted">Target</dt><dd>{{ $current->target_exam ? ucfirst($current->target_exam).' exam' : '—' }}{{ $current->exam_date ? ' · '.$current->exam_date->format('j M Y') : '' }}</dd>
                    <dt class="text-muted">Premium</dt><dd>{{ $premiumUntil ? 'Until '.$premiumUntil->format('j M Y') : 'Free plan' }}</dd>
                    <dt class="text-muted">Joined</dt><dd>{{ $current->created_at->format('j M Y') }}</dd>
                    <dt class="text-muted">Activity</dt><dd>{{ number_format($stats['answered']) }} answered · {{ $stats['accuracy'] }}% · {{ $stats['streak'] }}-day streak</dd>
                </dl>

                <div class="flex flex-col gap-2">
                    <h3 class="text-sm font-semibold">Signed-in devices <span class="text-muted font-normal">({{ $current->devices->count() }}/{{ $maxDevices }})</span></h3>
                    @forelse ($current->devices as $d)
                        <div class="flex items-center justify-between gap-2 px-3 py-2 rounded-xl bg-page text-sm">
                            <span><span class="font-medium">{{ $d->name ?: ucfirst($d->platform) }}</span> <span class="text-xs text-muted">· {{ $d->platform }} · {{ $d->last_seen_at?->diffForHumans() ?? '—' }}</span></span>
                            <button type="button" class="text-xs font-semibold text-stop" wire:click="revokeDevice({{ $d->id }})" wire:confirm="Sign this device out?">Sign out</button>
                        </div>
                    @empty
                        <span class="text-sm text-muted">No devices.</span>
                    @endforelse
                </div>

                @if ($payments->isNotEmpty())
                    <div class="flex flex-col gap-1.5">
                        <h3 class="text-sm font-semibold">Recent payments</h3>
                        @foreach ($payments as $p)
                            <a href="{{ route('admin.payments', ['selected' => $p->id, 'tab' => $p->status === 'pending' ? 'pending' : $p->status]) }}" wire:navigate class="flex justify-between text-sm no-underline text-navy px-3 py-1.5 rounded-lg hover:bg-page">
                                <span>{{ $p->plan?->name }} · {{ $p->gatewayLabel() }}</span><span class="text-muted">{{ ucfirst($p->status) }}</span>
                            </a>
                        @endforeach
                    </div>
                @endif

                @can('approve payments')
                    <div class="flex items-end gap-2 pt-3 border-t border-divider">
                        <label class="label flex-1">Give free premium days<input type="number" class="field" wire:model="grantDays"></label>
                        <button type="button" class="btn btn-light" wire:click="grant" wire:confirm="Give {{ $current->name }} free premium days?">Grant</button>
                    </div>
                @endcan

                <div class="flex flex-col gap-2 pt-3 border-t border-divider">
                    @if ($current->status === 'suspended')
                        <span class="text-sm text-stop">Suspended: {{ $current->suspended_reason }}</span>
                        <button type="button" class="btn btn-light" wire:click="unsuspend">Re-activate account</button>
                    @else
                        <label class="label">Suspend account (e.g. shared on too many devices)
                            <input class="field" wire:model="suspendReason" placeholder="Reason the student will see">
                        </label>
                        @error('suspendReason')<span class="error">{{ $message }}</span>@enderror
                        <button type="button" class="btn btn-danger" wire:click="suspend" wire:confirm="Suspend this account and sign it out everywhere?">Suspend</button>
                    @endif
                </div>
            @else
                <p class="text-sm text-muted text-center py-12">Select a student to see details.</p>
            @endif
        </section>
    </div>
</div>
