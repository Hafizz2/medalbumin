<div class="flex flex-col gap-[22px]">
    <header class="flex flex-wrap items-center justify-between gap-3.5">
        <div class="flex flex-col gap-1">
            <span class="eyebrow">Manual receipts · online gateways · plans</span>
            <h1 class="h-title">Payments</h1>
        </div>
        <button type="button" class="btn btn-light" wire:click="export"><x-icon name="download" size="16"/>Export report</button>
    </header>

    <section class="grid gap-3.5 grid-cols-[repeat(auto-fit,minmax(min(200px,100%),1fr))]">
        @foreach (['telebirr' => ['Telebirr', '#0E6FB8'], 'cbe_birr' => ['CBE Birr', '#6B2C91'], 'chapa' => ['Chapa', '#3F7F2A'], 'bank_transfer' => ['Bank receipts', '#475569']] as $key => [$name, $color])
            @php $g = $gateways[$key] ?? null; @endphp
            <div class="card px-5 py-4 flex flex-col gap-1">
                <span class="flex items-center gap-2 text-[13px] font-semibold"><span class="w-2.5 h-2.5 rounded-full" style="background: {{ $color }}"></span>{{ $name }}</span>
                <span class="font-display text-[28px] font-bold tracking-[-0.03em]">ETB {{ number_format($g->total ?? 0) }}</span>
                <span class="text-xs text-muted">{{ number_format($g->n ?? 0) }} {{ $key === 'bank_transfer' ? 'approved' : 'payments' }} this month</span>
            </div>
        @endforeach
    </section>

    <div class="grid gap-3.5 grid-cols-1 xl:grid-cols-[minmax(0,5fr)_minmax(0,6fr)] items-start">
        <section class="card overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-2 px-[18px] pt-4 pb-3">
                <h2 class="h-section">Receipt queue <span class="text-sm font-sans font-medium text-muted ml-1">{{ $pending }} waiting</span></h2>
            </div>
            <div class="px-[18px] pb-3">
                <div class="seg w-fit" role="tablist">
                    @foreach (['pending' => 'Waiting', 'approved' => 'Approved', 'rejected' => 'Rejected', 'online' => 'Online'] as $k => $label)
                        <button type="button" role="tab" class="seg-btn" aria-selected="{{ $tab === $k ? 'true' : 'false' }}" wire:click="$set('tab', '{{ $k }}')">{{ $label }}</button>
                    @endforeach
                </div>
            </div>
            @forelse ($queue as $p)
                @php $badge = ['pending' => 'badge-warn', 'approved' => 'badge-ok', 'rejected' => 'badge-stop', 'failed' => 'badge-stop', 'cancelled' => 'badge-muted'][$p->status]; @endphp
                <button type="button" wire:click="$set('selected', {{ $p->id }})" wire:key="p-{{ $p->id }}"
                        class="w-full text-left flex items-center gap-3 px-[18px] py-3 border-t border-divider cursor-pointer {{ $current?->id === $p->id ? 'row-sel' : 'bg-white hover:bg-page' }}">
                    <span class="w-9 h-9 rounded-full bg-sky-bg text-sky flex items-center justify-center text-[13px] font-semibold shrink-0">{{ $p->user?->initials() }}</span>
                    <span class="flex-1 min-w-0 flex flex-col"><span class="text-sm font-semibold truncate">{{ $p->user?->name ?? $p->user?->phone }}</span><span class="text-xs text-muted">{{ $p->plan?->name }} · {{ $p->created_at->diffForHumans() }}</span></span>
                    <span class="badge {{ $badge }}">{{ ucfirst($p->status) }}</span>
                </button>
            @empty
                <p class="px-[18px] py-10 text-center text-sm text-muted border-t border-divider">Nothing here. 🎉</p>
            @endforelse
            <div class="px-4 py-3">{{ $queue->links(data: ['scrollTo' => false]) }}</div>
        </section>

        <section class="card card-pad flex flex-col gap-4" aria-label="Receipt detail">
            @if ($current)
                <div class="grid gap-4 sm:grid-cols-[220px_1fr]">
                    @if ($current->receipt_path)
                        <a href="{{ route('admin.payments.receipt', $current) }}" target="_blank" class="block rounded-2xl overflow-hidden border border-line bg-page" title="Open full size">
                            @if (str_ends_with($current->receipt_path, '.pdf'))
                                <div class="h-[260px] flex flex-col items-center justify-center gap-2 text-muted"><x-icon name="note" size="32"/>PDF receipt · open</div>
                            @else
                                <img src="{{ route('admin.payments.receipt', $current) }}" alt="Uploaded receipt" class="w-full h-[260px] object-cover">
                            @endif
                        </a>
                    @else
                        <div class="rounded-2xl bg-page h-[160px] flex flex-col items-center justify-center text-xs text-muted gap-1 p-3 text-center">
                            <span class="font-semibold">{{ $current->gatewayLabel() }}</span>
                            <span>Ref {{ $current->tx_ref }}</span>
                        </div>
                    @endif
                    <div class="flex flex-col gap-3">
                        <span class="badge {{ ['pending' => 'badge-warn', 'approved' => 'badge-ok', 'rejected' => 'badge-stop'][$current->status] ?? 'badge-muted' }} self-start">{{ ucfirst($current->status) }}</span>
                        <div>
                            <h2 class="h-section">{{ $current->user?->name ?? 'Unnamed student' }}</h2>
                            <span class="text-sm text-muted">{{ $current->user?->phone }}{{ $current->user?->university ? ' · '.$current->user->university->name : '' }}</span>
                        </div>
                        <dl class="grid grid-cols-[110px_1fr] gap-y-2 text-sm">
                            <dt class="text-muted">Plan</dt><dd class="font-medium">{{ $current->plan?->name }}</dd>
                            <dt class="text-muted">Expected</dt><dd class="mono">ETB {{ number_format((float) $current->plan?->price - (float) $current->discount, 2) }}</dd>
                            <dt class="text-muted">Amount</dt><dd class="mono">ETB {{ number_format((float) $current->amount, 2) }}</dd>
                            <dt class="text-muted">Paid via</dt><dd>{{ $current->gatewayLabel() }}</dd>
                            <dt class="text-muted">Reference</dt><dd class="mono">{{ $current->reference ?? '—' }}</dd>
                            @if ($current->promoCode)<dt class="text-muted">Promo</dt><dd class="mono">{{ $current->promoCode->code }}</dd>@endif
                            <dt class="text-muted">Submitted</dt><dd>{{ $current->created_at->format('d/m/Y H:i') }} · {{ $current->created_at->diffForHumans() }}</dd>
                            @if ($current->reviewer)<dt class="text-muted">Reviewed</dt><dd>{{ $current->reviewer->shortName() }} · {{ $current->reviewed_at?->diffForHumans() }}</dd>@endif
                            @if ($current->reject_reason)<dt class="text-muted">Reason</dt><dd>{{ $current->reject_reason }}</dd>@endif
                        </dl>
                    </div>
                </div>

                @if ($current->status === 'pending' && $current->gateway === 'bank_transfer')
                    @php $okAmount = $current->amountMatches(); $reused = $current->referenceReused(); @endphp
                    <div class="flex items-start gap-2.5 px-3.5 py-3 rounded-2xl text-sm {{ $okAmount && ! $reused ? 'bg-teal-wash text-teal-ink' : 'bg-warn-bg text-warn' }}">
                        <x-icon :name="$okAmount && ! $reused ? 'check' : 'flag'" size="18" class="shrink-0 mt-0.5"/>
                        <span>
                            {{ $okAmount ? 'Amount matches the plan price.' : 'Amount does not match the plan price — check the receipt.' }}
                            {{ $current->reference ? ($reused ? 'This reference was used on another payment!' : 'Reference not used before.') : 'No reference typed by the student — read it from the receipt.' }}
                        </span>
                    </div>

                    @if ($rejecting)
                        <div class="flex flex-col gap-2">
                            <label class="label">Why is it rejected? The student sees this.
                                <input class="field" wire:model="rejectReason" placeholder="e.g. Receipt amount is ETB 500, the plan costs ETB 899.">
                            </label>
                            @error('rejectReason')<span class="error">{{ $message }}</span>@enderror
                            <div class="flex gap-2 justify-end">
                                <button type="button" class="btn btn-light" wire:click="$set('rejecting', false)">Cancel</button>
                                <button type="button" class="btn btn-danger" wire:click="reject">Reject receipt</button>
                            </div>
                        </div>
                    @else
                        <div class="flex flex-wrap gap-2 justify-end">
                            <button type="button" class="btn btn-danger" wire:click="$set('rejecting', true)">Reject</button>
                            <button type="button" class="btn btn-teal" wire:click="approve" wire:loading.attr="disabled">Approve &amp; activate plan</button>
                        </div>
                    @endif
                    <span class="text-xs text-muted text-right">The student gets a push notification when you decide.</span>
                @endif
            @else
                <p class="text-sm text-muted text-center py-16">Select a payment.</p>
            @endif
        </section>
    </div>

    <section class="card overflow-hidden">
        <div class="flex items-center justify-between px-5 pt-4 pb-3">
            <h2 class="h-section">Subscription plans</h2>
            @can('manage plans')<button type="button" class="btn btn-light btn-sm" wire:click="editPlan">Add plan</button>@endcan
        </div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Plan</th><th>Duration</th><th>Price</th><th>Subscribers</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @foreach ($plans as $p)
                    <tr wire:key="plan-{{ $p->id }}">
                        <td class="font-semibold">{{ $p->name }} @if ($p->badge)<span class="badge-ok badge ml-1">{{ $p->badge }}</span>@endif</td>
                        <td>{{ $p->durationLabel() }}</td>
                        <td class="mono">ETB {{ number_format((float) $p->price, 2) }}</td>
                        <td class="mono">{{ number_format($subscribers[$p->id] ?? 0) }}</td>
                        <td><span class="badge {{ $p->is_active ? 'badge-ok' : 'badge-muted' }}">{{ $p->is_active ? 'Live' : 'Hidden' }}</span>@if ((float) $p->price <= 0)<span class="badge badge-warn ml-1">Set price</span>@endif</td>
                        <td class="text-right">@can('manage plans')<button type="button" class="btn btn-light btn-sm" wire:click="editPlan({{ $p->id }})"><x-icon name="edit" size="14"/>Edit</button>@endcan</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>

    @if ($planId !== null)
        <div class="fixed inset-0 z-50 bg-navy/40 flex items-center justify-center p-4">
            <form wire:submit="savePlan" class="card card-pad w-full max-w-lg flex flex-col gap-3">
                <div class="flex items-center justify-between"><h2 class="h-section">{{ $planId ? 'Edit plan' : 'New plan' }}</h2><button type="button" wire:click="$set('planId', null)" aria-label="Close"><x-icon name="x"/></button></div>
                <x-lang-field model="plan.name" label="Name" :values="$plan['name'] ?? []" placeholder="Semester"/>
                <div class="grid grid-cols-2 gap-3">
                    <x-lang-field model="plan.note" label="Short note" :values="$plan['note'] ?? []" placeholder="6 months"/>
                    <x-lang-field model="plan.badge" label="Badge" :values="$plan['badge'] ?? []" placeholder="Popular"/>
                </div>
                <x-lang-field model="plan.features" label="Benefits on the pricing page — one per line" :values="$plan['features'] ?? []" textarea rows="4" placeholder="All past papers with explanations&#10;Unlimited mock exams&#10;Offline notes on 2 phones"/>
                <div class="grid grid-cols-2 gap-3">
                    <label class="label">Price (ETB)<input type="number" step="0.01" class="field" wire:model="plan.price">@error('plan.price')<span class="error">{{ $message }}</span>@enderror</label>
                    <label class="label">Duration (days)<input type="number" class="field" wire:model="plan.duration_days" placeholder="e.g. 30">@error('plan.duration_days')<span class="error">{{ $message }}</span>@enderror</label>
                    <label class="label">Or fixed end date<input type="date" class="field" wire:model="plan.ends_on"></label>
                    <label class="label">Order<input type="number" class="field" wire:model="plan.sort"></label>
                </div>
                <label class="flex items-center gap-2.5 text-sm font-medium"><input type="checkbox" class="accent-teal w-4 h-4" wire:model="plan.is_active"> Show in the app</label>
                <div class="flex justify-end gap-2"><button type="button" class="btn btn-light" wire:click="$set('planId', null)">Cancel</button><button class="btn btn-primary">Save plan</button></div>
            </form>
        </div>
    @endif
</div>
