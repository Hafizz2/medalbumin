<div class="flex flex-col gap-[22px]">
    <header class="flex flex-wrap items-center justify-between gap-3.5">
        <div class="flex flex-col gap-1">
            <span class="eyebrow">Super admin only</span>
            <h1 class="h-title">Settings</h1>
        </div>
        <div class="seg flex-wrap" role="tablist" aria-label="Settings section">
            @foreach (['roles' => 'Roles & permissions', 'promo' => 'Promo codes & referrals', 'general' => 'General', 'audit' => 'Audit log'] as $k => $label)
                <button type="button" role="tab" class="seg-btn" aria-selected="{{ $tab === $k ? 'true' : 'false' }}" wire:click="$set('tab', '{{ $k }}')">{{ $label }}</button>
            @endforeach
        </div>
    </header>

    @if ($tab === 'roles')
        <section class="card overflow-hidden">
            <div class="flex items-center justify-between px-5 pt-4 pb-3"><h2 class="h-section">Roles &amp; permissions</h2><button type="button" class="btn btn-primary btn-sm" wire:click="startInvite">Invite admin</button></div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Permission</th>@foreach ($roleNames as $r)<th class="text-center">{{ $r }}<br><span class="font-normal">{{ $roleCounts[$r] }} people</span></th>@endforeach</tr></thead>
                    <tbody>
                    @foreach ($permissions as $perm => $label)
                        <tr>
                            <td class="font-medium">{{ $label }}</td>
                            @foreach ($roleNames as $r)
                                @php $locked = $r === 'Super admin'; $on = $locked || ($roles[$r]?->permissions->contains('name', $perm) ?? false); @endphp
                                <td class="text-center">
                                    <button type="button" role="checkbox" aria-checked="{{ $on ? 'true' : 'false' }}" aria-label="{{ $r }}: {{ $label }}" @if (! $locked) wire:click="togglePermission('{{ $r }}', '{{ $perm }}')" @endif
                                            class="w-[26px] h-[26px] rounded-lg inline-flex items-center justify-center {{ $on ? ($locked ? 'bg-slate text-white cursor-default' : 'bg-teal text-white cursor-pointer') : 'bg-white border-[1.5px] border-field-2 cursor-pointer' }}">
                                        @if ($on)<x-icon name="check" size="14" stroke="2.6"/>@endif
                                    </button>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card overflow-hidden">
            <h2 class="h-section px-5 pt-4 pb-3">Admin team</h2>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>2-step login</th><th>Last active</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($staff as $s)
                        <tr wire:key="st-{{ $s->id }}">
                            <td class="font-semibold">{{ $s->name }}</td>
                            <td class="text-slate">{{ $s->email }}</td>
                            <td>
                                <select class="field !h-9 !w-auto" wire:change="changeRole({{ $s->id }}, $event.target.value)">
                                    @foreach ($roleNames as $r)<option @selected($s->hasRole($r))>{{ $r }}</option>@endforeach
                                </select>
                            </td>
                            <td><span class="badge {{ $s->two_factor_confirmed_at ? 'badge-ok' : 'badge-warn' }}">{{ $s->two_factor_confirmed_at ? 'On' : 'Not set up' }}</span></td>
                            <td class="text-slate">{{ $s->last_active_at?->diffForHumans() ?? '—' }}</td>
                            <td class="text-right">@if ($s->id !== auth()->id())<button type="button" class="btn btn-danger btn-sm" wire:click="removeStaff({{ $s->id }})" wire:confirm="Remove admin access for {{ $s->name }}?">Remove</button>@endif</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        @if ($newPassword)
            <div class="card card-pad bg-teal-wash border-teal-soft text-sm flex flex-col gap-1">
                <strong>Share these sign-in details privately (shown once):</strong>
                <span>Sign-in page: <span class="mono">{{ route('login') }}</span></span>
                <span>Temporary password: <span class="mono text-base">{{ $newPassword }}</span></span>
                <span class="text-muted">They can change the password (and optionally turn on 2-step login) by clicking their name in the sidebar.</span>
            </div>
        @endif
    @elseif ($tab === 'promo')
        <div class="grid gap-3.5 grid-cols-1 xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)] items-start">
            <section class="card overflow-hidden">
                <div class="flex items-center justify-between px-5 pt-4 pb-3"><h2 class="h-section">Promo codes</h2><button type="button" class="btn btn-primary btn-sm" wire:click="editPromo">New code</button></div>
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead><tr><th>Code</th><th>Discount</th><th>Used</th><th>Ends</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                        @forelse ($codes as $c)
                            @php $live = $c->isLive(); @endphp
                            <tr wire:key="pc-{{ $c->id }}">
                                <td class="mono font-semibold">{{ $c->code }}</td>
                                <td>{{ $c->label() }}{{ $c->university ? ' · '.$c->university->short_name.' students' : '' }}{{ $c->plan ? ' · '.$c->plan->name : '' }}</td>
                                <td class="mono">{{ number_format($c->used_count) }}{{ $c->max_uses ? ' / '.number_format($c->max_uses) : '' }}</td>
                                <td>{{ $c->ends_at?->format('j M') ?? '—' }}</td>
                                <td><span class="badge {{ $live ? 'badge-ok' : 'badge-muted' }}">{{ $live ? 'Active' : 'Ended' }}</span></td>
                                <td class="text-right"><button type="button" class="btn btn-light btn-sm" wire:click="editPromo({{ $c->id }})">Edit</button></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-10">No promo codes yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
            <section class="card card-pad flex flex-col gap-3">
                <h2 class="h-section">Invite-a-friend reward</h2>
                <span class="text-sm text-slate">When an invited friend subscribes, both get <strong>{{ $general['referral_reward_days'] }} free days</strong>. Up to {{ $general['referral_max_rewards'] }} rewards per student.</span>
                <div class="grid grid-cols-2 gap-3">
                    <span class="flex flex-col rounded-2xl bg-page p-3"><span class="font-display text-2xl font-bold">{{ number_format($invites['month']) }}</span><span class="text-xs text-muted">friends joined this month</span></span>
                    <span class="flex flex-col rounded-2xl bg-page p-3"><span class="font-display text-2xl font-bold">{{ number_format($invites['paying']) }}</span><span class="text-xs text-muted">became paying students</span></span>
                </div>
                <button type="button" class="btn btn-light btn-sm self-start" wire:click="$set('tab', 'general')">Edit reward</button>
            </section>
        </div>
    @elseif ($tab === 'general')
        <form wire:submit="saveGeneral" class="card card-pad flex flex-col gap-4 max-w-3xl">
            <h2 class="h-section">General</h2>
            <div class="grid gap-3 grid-cols-[repeat(auto-fit,minmax(200px,1fr))]">
                <label class="label">Free questions for guests<input type="number" class="field" wire:model="general.guest_free_questions"></label>
                <label class="label">Devices per account<input type="number" class="field" wire:model="general.max_devices"></label>
                <label class="label">Pass mark (%)<input type="number" class="field" wire:model="general.pass_mark"></label>
                <label class="label">Seconds per question (timed)<input type="number" class="field" wire:model="general.seconds_per_question"></label>
                <label class="label">Referral reward (days)<input type="number" class="field" wire:model="general.referral_reward_days"></label>
                <label class="label">Max referral rewards<input type="number" class="field" wire:model="general.referral_max_rewards"></label>
            </div>
            <div class="flex flex-col gap-2">
                <div class="flex items-center justify-between"><h3 class="text-sm font-semibold">Bank accounts shown for transfers</h3><button type="button" class="btn btn-light btn-sm" wire:click="addBank">Add account</button></div>
                @forelse ($banks as $i => $b)
                    <div class="grid grid-cols-[1fr_1fr_1fr_auto] gap-2" wire:key="bank-{{ $i }}">
                        <input class="field" placeholder="Bank (e.g. CBE)" wire:model="banks.{{ $i }}.bank">
                        <input class="field" placeholder="Account name" wire:model="banks.{{ $i }}.account_name">
                        <input class="field mono" placeholder="Account number" wire:model="banks.{{ $i }}.account_number">
                        <button type="button" class="btn btn-danger btn-sm !h-11" wire:click="removeBank({{ $i }})">×</button>
                    </div>
                @empty
                    <span class="text-sm text-muted">No accounts yet — students need these to pay by bank transfer.</span>
                @endforelse
                @error('banks.*.bank')<span class="error">Fill in every bank account field.</span>@enderror
            </div>
            <button class="btn btn-primary self-end">Save settings</button>
        </form>
    @else
        <section class="card overflow-hidden">
            <h2 class="h-section px-5 pt-4 pb-3">Audit log · who changed what</h2>
            @foreach ($log as $l)
                <div class="flex flex-wrap items-center gap-3 px-5 min-h-[54px] border-t border-divider text-sm">
                    <span class="mono text-xs text-muted w-36 shrink-0">{{ $l->created_at->isToday() ? 'Today '.$l->created_at->format('H:i') : $l->created_at->format('j M H:i') }}</span>
                    <span class="font-semibold w-48 shrink-0 truncate">{{ $l->causer?->shortName() ?? 'System' }}{{ $l->causer?->getRoleNames()->first() ? ' · '.$l->causer->getRoleNames()->first() : '' }}</span>
                    <span class="text-slate flex-1 min-w-[200px]">
                        @if ($l->description === 'updated' || $l->description === 'created' || $l->description === 'deleted')
                            {{ ucfirst($l->description) }} {{ Str::headline(class_basename($l->subject_type)) }} #{{ $l->subject_id }}
                            @if ($l->properties['attributes'] ?? null)<span class="text-xs text-muted">· {{ collect($l->properties['attributes'])->keys()->implode(', ') }}</span>@endif
                        @else
                            {{ $l->description }}
                        @endif
                    </span>
                </div>
            @endforeach
            <div class="px-4 py-3">{{ $log->links(data: ['scrollTo' => false]) }}</div>
        </section>
    @endif

    @if ($invite)
        <div class="fixed inset-0 z-50 bg-navy/40 flex items-center justify-center p-4">
            <form wire:submit="sendInvite" class="card card-pad w-full max-w-md flex flex-col gap-3">
                <div class="flex items-center justify-between"><h2 class="h-section">Invite admin</h2><button type="button" wire:click="$set('invite', null)"><x-icon name="x"/></button></div>
                <label class="label">Name<input class="field" wire:model="invite.name">@error('invite.name')<span class="error">{{ $message }}</span>@enderror</label>
                <label class="label">Email<input type="email" class="field" wire:model="invite.email">@error('invite.email')<span class="error">{{ $message }}</span>@enderror</label>
                <label class="label">Role<select class="field" wire:model="invite.role">@foreach ($roleNames as $r)<option>{{ $r }}</option>@endforeach</select></label>
                <div class="flex justify-end gap-2"><button type="button" class="btn btn-light" wire:click="$set('invite', null)">Cancel</button><button class="btn btn-primary">Create account</button></div>
            </form>
        </div>
    @endif

    @if ($promo)
        <div class="fixed inset-0 z-50 bg-navy/40 flex items-center justify-center p-4">
            <form wire:submit="savePromo" class="card card-pad w-full max-w-lg flex flex-col gap-3">
                <div class="flex items-center justify-between"><h2 class="h-section">{{ $promo['id'] ? 'Edit code' : 'New promo code' }}</h2><button type="button" wire:click="$set('promo', null)"><x-icon name="x"/></button></div>
                <div class="grid grid-cols-2 gap-3">
                    <label class="label">Code<input class="field mono uppercase" wire:model="promo.code" placeholder="EXIT2026">@error('promo.code')<span class="error">{{ $message }}</span>@enderror</label>
                    <label class="label">Type<select class="field" wire:model.live="promo.type"><option value="percent">% off</option><option value="fixed">ETB off</option><option value="free_days">Free days</option></select></label>
                    <label class="label">{{ ['percent' => 'Percent', 'fixed' => 'Amount (ETB)', 'free_days' => 'Days'][$promo['type']] }}<input type="number" step="0.01" class="field" wire:model="promo.value">@error('promo.value')<span class="error">{{ $message }}</span>@enderror</label>
                    <label class="label">Max uses<input type="number" class="field" wire:model="promo.max_uses" placeholder="Unlimited"></label>
                    <label class="label">Only for university<select class="field" wire:model="promo.university_id"><option value="">Anyone</option>@foreach ($universities as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select></label>
                    <label class="label">Only for plan<select class="field" wire:model="promo.plan_id"><option value="">Any plan</option>@foreach ($plans as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select></label>
                    <label class="label">Ends<input type="date" class="field" wire:model="promo.ends_at"></label>
                    <label class="label">Note<input class="field" wire:model="promo.description"></label>
                </div>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="accent-teal w-4 h-4" wire:model="promo.is_active">Active</label>
                <div class="flex justify-end gap-2"><button type="button" class="btn btn-light" wire:click="$set('promo', null)">Cancel</button><button class="btn btn-primary">Save code</button></div>
            </form>
        </div>
    @endif
</div>
