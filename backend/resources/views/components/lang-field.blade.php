@props(['model', 'label', 'values' => [], 'textarea' => false, 'rows' => 2, 'placeholder' => '', 'live' => false])
@php
    // One input per configured language (active and draft), switched with small tabs.
    // Binds to "{model}.{code}", e.g. dept.name.en, dept.name.am
    $languages = \App\Models\Language::cached();
@endphp
<div {{ $attributes->merge(['class' => 'flex flex-col gap-1.5']) }} x-data="{ lang: 'en' }">
    <div class="flex items-center justify-between gap-2">
        <span class="text-[13px] font-semibold text-slate">{{ $label }}</span>
        @if ($languages->count() > 1)
            <span class="flex flex-wrap gap-1" role="tablist" aria-label="{{ $label }} language">
                @foreach ($languages as $l)
                    <button type="button" role="tab" @click="lang = '{{ $l->code }}'" :aria-selected="lang === '{{ $l->code }}'"
                            class="h-6 px-2 rounded-md text-[11px] font-semibold flex items-center gap-1"
                            :class="lang === '{{ $l->code }}' ? 'bg-navy text-page' : 'bg-chip text-slate'">
                        <span class="w-1.5 h-1.5 rounded-full {{ filled($values[$l->code] ?? null) ? 'bg-accent' : 'bg-field-2' }}"></span>{{ strtoupper($l->code) }}
                    </button>
                @endforeach
            </span>
        @endif
    </div>
    @foreach ($languages as $l)
        <div x-show="lang === '{{ $l->code }}'" @if (! $loop->first) x-cloak @endif>
            @if ($textarea)
                <textarea rows="{{ $rows }}" dir="{{ $l->direction }}" class="field" placeholder="{{ $l->code === 'en' ? $placeholder : $l->native_name.' (optional — falls back to English)' }}"
                          wire:model{{ $live ? '.live' : '' }}="{{ $model }}.{{ $l->code }}"></textarea>
            @else
                <input dir="{{ $l->direction }}" class="field" placeholder="{{ $l->code === 'en' ? $placeholder : $l->native_name.' (optional — falls back to English)' }}"
                       wire:model{{ $live ? '.live' : '' }}="{{ $model }}.{{ $l->code }}">
            @endif
        </div>
    @endforeach
    @error($model.'.en')<span class="error">{{ $message }}</span>@enderror
    @error($model.'.*')<span class="error">{{ $message }}</span>@enderror
</div>
