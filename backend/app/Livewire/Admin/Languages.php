<?php

namespace App\Livewire\Admin;

use App\Models\FlashcardDeck;
use App\Models\Language;
use App\Models\Note;
use App\Models\Question;
use App\Models\UiTranslation;
use App\Models\User;
use App\Models\Video;
use App\Services\UiTranslationService;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;

/**
 * Admin → Languages.
 * Anyone with "edit content" can see coverage and translate.
 * Adding, activating, deactivating and removing languages is super-admin only and audit-logged.
 */
#[Title('Languages')]
class Languages extends AdminPage
{
    protected ?string $permission = 'edit content';

    public ?array $adding = null;
    public ?string $confirming = null;   // language code awaiting activate / deactivate / delete
    public string $confirmAction = '';
    public string $confirmText = '';

    private function authorizeManage(): void
    {
        abort_unless(auth()->user()->hasRole('Super admin'), 403, 'Only a super admin can manage languages.');
    }

    public function startAdd(): void
    {
        $this->authorizeManage();
        $this->adding = ['code' => '', 'native_name' => '', 'direction' => 'ltr'];
    }

    public function updatedAddingCode(string $code): void
    {
        if ($meta = config('languages.'.$code)) {
            $this->adding['native_name'] = $meta[1];
            $this->adding['direction'] = $meta[2];
        }
    }

    public function add(): void
    {
        $this->authorizeManage();
        $this->validate([
            'adding.code' => ['required', Rule::in(array_keys(config('languages'))), Rule::unique('languages', 'code')],
            'adding.native_name' => ['required', 'string', 'max:60'],
            'adding.direction' => ['required', 'in:ltr,rtl'],
        ], ['adding.code.in' => 'Choose a language from the list.', 'adding.code.unique' => 'This language is already added.'], ['adding.code' => 'language']);

        $code = $this->adding['code'];
        Language::query()->create([
            'code' => $code,
            'name' => config('languages.'.$code)[0],
            'native_name' => trim($this->adding['native_name']),
            'direction' => $this->adding['direction'],
            'is_active' => false,
            'sort' => (int) Language::query()->max('sort') + 1,
        ]);
        activity()->causedBy(auth()->user())->log("Added language {$code} (draft)");
        $this->adding = null;
        $this->toast('Language added as a draft. Editors can start translating; students will see it once you make it live.');
    }

    public function ask(string $code, string $action): void
    {
        $this->authorizeManage();
        abort_unless(in_array($action, ['activate', 'deactivate', 'delete'], true), 422);
        $lang = Language::query()->findOrFail($code);
        abort_if($lang->is_default, 422, 'English is the default language and is always live.');
        $this->confirming = $code;
        $this->confirmAction = $action;
        $this->confirmText = '';
    }

    public function confirm(): void
    {
        $this->authorizeManage();
        $lang = Language::query()->findOrFail($this->confirming);
        abort_if($lang->is_default, 422);

        if ($this->confirmAction === 'delete') {
            // Typing the code prevents accidental removal.
            if (strtolower(trim($this->confirmText)) !== $lang->code) {
                $this->addError('confirmText', 'Type “'.$lang->code.'” to confirm.');

                return;
            }
            abort_if($lang->is_active, 422, 'Make the language a draft before removing it.');
            UiTranslation::query()->where('locale', $lang->code)->delete();
            User::query()->where('app_locale', $lang->code)->update(['app_locale' => Language::DEFAULT]);
            User::query()->where('content_locale', $lang->code)->update(['content_locale' => Language::DEFAULT]);
            $lang->delete();
            activity()->causedBy(auth()->user())->log("Removed language {$lang->code}");
            $this->toast('Language removed. Translated content stays stored in case you add it again.');
        } else {
            $live = $this->confirmAction === 'activate';
            $lang->update(['is_active' => $live, 'activated_at' => $live ? now() : $lang->activated_at]);
            activity()->causedBy(auth()->user())->log(($live ? 'Made live' : 'Moved to draft').": language {$lang->code}");
            $this->toast($live ? $lang->native_name.' is now live in the app.' : $lang->native_name.' is hidden from students; they see English.');
        }
        app(UiTranslationService::class)->flush($lang->code);
        $this->confirming = null;
    }

    public function move(string $code, int $by): void
    {
        $this->authorizeManage();
        $list = Language::query()->orderByDesc('is_default')->orderBy('sort')->orderBy('name')->get()->values();
        $i = $list->search(fn ($l) => $l->code === $code);
        $j = $i + $by;
        if ($i === false || $j < 1 || $j >= $list->count() || $list[$i]->is_default) {
            return;
        }
        [$list[$i], $list[$j]] = [$list[$j], $list[$i]];
        foreach ($list as $pos => $l) {
            Language::query()->whereKey($l->code)->update(['sort' => $pos]);
        }
        Language::flush();
    }

    public function render(UiTranslationService $ui)
    {
        $q = Question::query()->published();
        $totals = [
            'questions' => (clone $q)->count(),
            'notes' => Note::query()->published()->count(),
            'decks' => FlashcardDeck::query()->where('is_official', true)->count(),
            'videos' => Video::query()->published()->count(),
        ];
        $coverage = $ui->coverage();

        $rows = Language::query()->orderByDesc('is_default')->orderBy('sort')->orderBy('name')->get()->map(function (Language $l) use ($q, $totals, $coverage) {
            $code = $l->code;
            $has = fn ($query, string $col) => $l->is_default ? $query : $query->whereNotNull($col.'->'.$code);

            return [
                'lang' => $l,
                'questions' => $l->is_default ? $totals['questions'] : (clone $q)->where('translation_status->'.$code, 'published')->count(),
                'drafts' => $l->is_default ? 0 : (clone $q)->where('translation_status->'.$code, 'draft')->count(),
                'notes' => $has(Note::query()->published(), 'body')->count(),
                'decks' => $has(FlashcardDeck::query()->where('is_official', true), 'title')->count(),
                'videos' => $has(Video::query()->published(), 'title')->count(),
                'ui' => $coverage['translated'][$code] ?? 0,
                'students' => User::query()->students()->where(fn ($w) => $w->where('content_locale', $code)->orWhere('app_locale', $code))->count(),
            ];
        });

        $existing = $rows->pluck('lang.code')->all();

        return view('livewire.admin.languages', [
            'rows' => $rows,
            'totals' => $totals,
            'uiTotal' => $coverage['total'],
            'available' => collect(config('languages'))->except($existing)->sortBy(fn ($m) => $m[0]),
            'canManage' => auth()->user()->hasRole('Super admin'),
            'pending' => $this->confirming ? Language::query()->find($this->confirming) : null,
        ]);
    }
}
