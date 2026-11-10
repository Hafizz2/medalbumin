<div class="flex flex-col gap-[22px]">
    <header class="flex flex-wrap items-center justify-between gap-3.5">
        <div class="flex flex-col gap-1">
            <span class="eyebrow">Past placement results that power the student Match predictor</span>
            <h1 class="h-title">Match predictor data</h1>
        </div>
        <button type="button" class="btn btn-light" wire:click="downloadTemplate"><x-icon name="download" size="16"/>Download template</button>
    </header>

    <section class="grid gap-3.5 grid-cols-[repeat(auto-fit,minmax(min(200px,100%),1fr))]">
        @foreach ([['Years of data', $stats['range']], ['Universities · specialties', $stats['unis'].' · '.$stats['specs']], ['Predictions this month', number_format($stats['predictions'])]] as [$l, $v])
            <div class="card px-5 py-4 flex flex-col gap-1"><span class="text-[13px] text-muted">{{ $l }}</span><span class="font-display text-[28px] font-bold tracking-[-0.03em]">{{ $v }}</span></div>
        @endforeach
    </section>

    <div class="grid gap-3.5 grid-cols-1 2xl:grid-cols-[minmax(0,7fr)_minmax(0,4fr)] items-start">
        <section class="card overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-3 px-5 pt-4 pb-3">
                <div class="seg" role="tablist" aria-label="Year">
                    @foreach ($years as $y)
                        <button type="button" role="tab" class="seg-btn" aria-selected="{{ (int) $year === (int) $y ? 'true' : 'false' }}" wire:click="$set('year', {{ $y }})">{{ $y }}</button>
                    @endforeach
                </div>
                <div class="flex items-center gap-2">
                    <select class="field !h-10 !w-auto" wire:model.live="specialty">
                        @foreach ($specialties as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                    </select>
                    <button type="button" class="btn btn-light btn-sm" wire:click="editRow">Add row</button>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>University</th><th>Seats</th><th>Applicants</th><th>Accepted</th><th>Approval rate</th><th>Cut-off</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($rows as $r)
                        @php $rate = $r->approvalRate(); @endphp
                        <tr wire:key="mr-{{ $r->id }}">
                            <td class="font-semibold">{{ $r->university?->name }}</td>
                            <td class="mono">{{ $r->seats }}</td>
                            <td class="mono">{{ $r->applicants ?: '—' }}</td>
                            <td class="mono">{{ $r->accepted ?: '—' }}</td>
                            <td>@if ($rate !== null)<span class="flex items-center gap-2"><span class="bar w-16"><span style="width: {{ min(100, $rate * 2) }}%"></span></span><span class="mono text-xs">{{ $rate }}%</span></span>@else — @endif</td>
                            <td class="mono font-semibold">{{ rtrim(rtrim(number_format($r->cutoff, 2), '0'), '.') }}%</td>
                            <td class="text-right whitespace-nowrap"><button type="button" class="p-1.5 text-muted hover:text-navy" wire:click="editRow({{ $r->id }})" aria-label="Edit row"><x-icon name="edit" size="15"/></button><button type="button" class="p-1.5 text-muted hover:text-stop" wire:click="deleteRow({{ $r->id }})" wire:confirm="Delete this row?" aria-label="Delete row"><x-icon name="trash" size="15"/></button></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-10">No results for this specialty and year. Add rows or upload a file.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="flex flex-wrap items-end gap-2 px-5 py-4 border-t border-divider bg-page">
                <label class="label">Upload year<input type="number" class="field !w-28" wire:model="uploadYear"></label>
                <label class="label flex-1 min-w-[200px]">Excel / CSV file<input type="file" accept=".csv,.xlsx" class="field !h-auto py-2" wire:model="upload"></label>
                <button type="button" class="btn btn-primary" wire:click="import" wire:loading.attr="disabled" wire:target="import,upload"><x-icon name="upload" size="16"/>Upload year</button>
                @error('upload')<span class="error w-full">{{ $message }}</span>@enderror
                @if ($uploadResult)
                    <div class="w-full text-sm"><strong>{{ $uploadResult['ok'] }} rows imported.</strong>
                        @foreach ($uploadResult['errors'] as $line => $msg)<span class="block text-xs text-danger">Row {{ $line }}: {{ $msg }}</span>@endforeach
                    </div>
                @endif
            </div>
        </section>

        <form wire:submit="saveRules" class="card card-pad flex flex-col gap-3">
            <h2 class="h-section">How chances are calculated</h2>
            <span class="text-[13px] text-muted">Matching score = residency entrance exam (Part I + II average), plus official bonuses. Chance per university follows a curve around the cut-off of recent years.</span>
            <div class="grid grid-cols-2 gap-3">
                <label class="label">Female bonus (%)<input type="number" step="0.5" class="field" wire:model="rules.female_bonus_pct"></label>
                <label class="label">Managerial post bonus (%)<input type="number" step="0.5" class="field" wire:model="rules.managerial_bonus_pct"></label>
                <label class="label">Minimum cut-off<input type="number" step="0.1" class="field" wire:model="rules.minimum_cutoff" placeholder="Set by SSG"></label>
                <label class="label">Years to use<input type="number" class="field" wire:model="rules.years"></label>
                <label class="label">Specialties allowed<input type="number" class="field" wire:model="rules.max_specialties"></label>
                <label class="label">Universities each<input type="number" class="field" wire:model="rules.max_universities"></label>
            </div>
            <label class="label">Show predictor to
                <select class="field" wire:model="rules.audience"><option value="final_year">Final-year students and interns</option><option value="all">All students</option><option value="premium">Premium only</option></select>
            </label>
            <label class="label">Matching timeline (one step per line)<textarea rows="5" class="field" wire:model="rules.timeline"></textarea></label>
            <label class="label">Current step (0 = first)<input type="number" class="field" wire:model="rules.timeline_step"></label>
            <div class="px-3.5 py-2.5 rounded-2xl bg-page text-[13px]">Students always see: “An estimate from past data — not an official result.”</div>
            <button class="btn btn-primary">Save &amp; update predictions</button>
        </form>
    </div>

    @if ($row)
        <div class="fixed inset-0 z-50 bg-navy/40 flex items-center justify-center p-4">
            <form wire:submit="saveRow" class="card card-pad w-full max-w-md flex flex-col gap-3">
                <div class="flex items-center justify-between"><h2 class="h-section">{{ $row['id'] ? 'Edit result' : 'Add result' }} · {{ $year }}</h2><button type="button" wire:click="$set('row', null)"><x-icon name="x"/></button></div>
                <label class="label">University<select class="field" wire:model="row.university_id"><option value="">Choose…</option>@foreach ($universities as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select>@error('row.university_id')<span class="error">{{ $message }}</span>@enderror</label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="label">Seats<input type="number" class="field" wire:model="row.seats">@error('row.seats')<span class="error">{{ $message }}</span>@enderror</label>
                    <label class="label">Cut-off (%)<input type="number" step="0.01" class="field" wire:model="row.cutoff">@error('row.cutoff')<span class="error">{{ $message }}</span>@enderror</label>
                    <label class="label">Applicants<input type="number" class="field" wire:model="row.applicants"></label>
                    <label class="label">Accepted<input type="number" class="field" wire:model="row.accepted"></label>
                </div>
                <div class="flex justify-end gap-2"><button type="button" class="btn btn-light" wire:click="$set('row', null)">Cancel</button><button class="btn btn-primary">Save</button></div>
            </form>
        </div>
    @endif
</div>
