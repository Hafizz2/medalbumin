<?php

namespace App\Livewire\Admin;

use App\Models\Department;
use App\Models\Drug;
use App\Models\Flashcard;
use App\Models\FlashcardDeck;
use App\Models\LabValue;
use App\Models\Paper;
use App\Models\Subcategory;
use App\Models\University;
use App\Support\Translations;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

/**
 * Smaller content types: past papers, official flashcards, lab values, drugs, universities.
 */
#[Title('Papers, cards & tools')]
class Library extends AdminPage
{
    protected ?string $permission = 'edit content';

    #[Url] public string $tab = 'papers';
    public ?array $form = null;
    public ?int $deck = null;
    public ?array $card = null;

    public function updatingTab(): void
    {
        $this->form = null;
        $this->card = null;
    }

    public function edit(?int $id = null): void
    {
        $this->resetValidation();
        $this->form = match ($this->tab) {
            'papers' => $this->paperForm($id ? Paper::query()->findOrFail($id) : null),
            'decks' => $this->deckForm($id ? FlashcardDeck::query()->findOrFail($id) : null),
            'labs' => $this->labForm($id ? LabValue::query()->findOrFail($id) : null),
            'drugs' => $this->drugForm($id ? Drug::query()->findOrFail($id) : null),
            'universities' => $this->uniForm($id ? University::query()->findOrFail($id) : null),
        };
    }

    private function paperForm(?Paper $p): array
    {
        return ['id' => $p?->id, 'category' => $p?->category ?? 'exit', 'name' => $p?->name ?? 'National Exit Exam', 'year' => $p?->year, 'ec_label' => $p?->ec_label,
            'code' => $p?->code, 'description' => $p?->description, 'department_id' => $p?->department_id, 'time_limit_minutes' => $p?->time_limit_minutes,
            'is_premium' => $p?->is_premium ?? true, 'status' => $p?->status ?? 'draft', 'sort' => $p?->sort ?? 0];
    }

    private function deckForm(?FlashcardDeck $d): array
    {
        return ['id' => $d?->id, 'title' => $d?->getTranslations('title') ?: ['en' => ''],
            'department_id' => $d?->department_id, 'subcategory_id' => $d?->subcategory_id, 'status' => $d?->status ?? 'published', 'sort' => $d?->sort ?? 0];
    }

    private function labForm(?LabValue $l): array
    {
        return ['id' => $l?->id, 'category' => $l?->category ?? 'General', 'name' => $l?->getTranslations('name') ?: ['en' => ''], 'range' => $l?->range ?? '', 'unit' => $l?->unit, 'sort' => $l?->sort ?? 0, 'is_active' => $l?->is_active ?? true];
    }

    private function drugForm(?Drug $d): array
    {
        return ['id' => $d?->id, 'name' => $d?->getTranslations('name') ?: ['en' => ''], 'category' => $d?->getTranslations('category') ?: ['en' => ''],
            'dose' => $d?->getTranslations('dose') ?: ['en' => ''], 'watch' => $d?->getTranslations('watch') ?: [], 'sort' => $d?->sort ?? 0, 'is_active' => $d?->is_active ?? true];
    }

    private function uniForm(?University $u): array
    {
        return ['id' => $u?->id, 'name' => $u?->name ?? '', 'short_name' => $u?->short_name, 'city' => $u?->city, 'is_active' => $u?->is_active ?? true, 'sort' => $u?->sort ?? 0];
    }

    public function save(): void
    {
        $f = $this->form;
        match ($this->tab) {
            'papers' => $this->savePaper($f),
            'decks' => $this->saveDeck($f),
            'labs' => $this->saveLab($f),
            'drugs' => $this->saveDrug($f),
            'universities' => $this->saveUni($f),
        };
        $this->form = null;
        $this->toast('Saved.');
    }

    private function savePaper(array $f): void
    {
        $this->validate([
            'form.category' => ['required', 'in:exit,qualification,block'],
            'form.name' => ['required', 'string', 'max:120'],
            'form.year' => ['nullable', 'integer', 'between:1990,2100'],
            'form.ec_label' => ['nullable', 'string', 'max:20'],
            'form.code' => ['nullable', 'required_if:form.category,block', 'string', 'max:10'],
            'form.description' => ['nullable', 'string', 'max:120'],
            'form.department_id' => ['nullable', 'exists:departments,id'],
            'form.time_limit_minutes' => ['nullable', 'integer', 'min:5', 'max:600'],
            'form.status' => ['required', 'in:draft,published'],
        ], [], ['form.name' => 'name', 'form.code' => 'system code']);
        Paper::query()->updateOrCreate(['id' => $f['id']], collect($f)->except('id')->map(fn ($v) => $v === '' ? null : $v)->all());
    }

    private function saveDeck(array $f): void
    {
        $this->validate([...Translations::rules('form.title', 120), 'form.department_id' => ['nullable', 'exists:departments,id'], 'form.subcategory_id' => ['nullable', 'exists:subcategories,id']], [], ['form.title.en' => 'title']);
        $d = $f['id'] ? FlashcardDeck::query()->findOrFail($f['id']) : new FlashcardDeck(['is_official' => true]);
        $d->fill(['department_id' => $f['department_id'] ?: null, 'subcategory_id' => $f['subcategory_id'] ?: null, 'status' => $f['status'], 'sort' => (int) $f['sort']]);
        $d->replaceTranslations('title', Translations::clean($f['title']));
        $d->save();
        $this->deck = $d->id;
    }

    private function saveLab(array $f): void
    {
        $this->validate([...Translations::rules('form.name', 80), 'form.range' => ['required', 'string', 'max:60'], 'form.category' => ['required', 'string', 'max:60'], 'form.unit' => ['nullable', 'string', 'max:30']], [], ['form.name.en' => 'name']);
        $l = $f['id'] ? LabValue::query()->findOrFail($f['id']) : new LabValue;
        $l->fill(['category' => $f['category'], 'range' => $f['range'], 'unit' => $f['unit'], 'sort' => (int) $f['sort'], 'is_active' => (bool) $f['is_active']]);
        $l->replaceTranslations('name', Translations::clean($f['name']));
        $l->save();
    }

    private function saveDrug(array $f): void
    {
        $this->validate([
            ...Translations::rules('form.name', 80), ...Translations::rules('form.category', 120),
            ...Translations::rules('form.dose', 1000), ...Translations::rules('form.watch', 1000, false),
        ], [], ['form.name.en' => 'name', 'form.category.en' => 'use', 'form.dose.en' => 'dose']);
        $d = $f['id'] ? Drug::query()->findOrFail($f['id']) : new Drug;
        $d->fill(['sort' => (int) $f['sort'], 'is_active' => (bool) $f['is_active']]);
        foreach (['name', 'category', 'dose', 'watch'] as $k) {
            $d->replaceTranslations($k, Translations::clean($f[$k]));
        }
        $d->save();
    }

    private function saveUni(array $f): void
    {
        $this->validate(['form.name' => ['required', 'string', 'max:120'], 'form.short_name' => ['nullable', 'string', 'max:30'], 'form.city' => ['nullable', 'string', 'max:80']], [], ['form.name' => 'name']);
        University::query()->updateOrCreate(['id' => $f['id']], collect($f)->except('id')->all());
    }

    public function delete(int $id): void
    {
        $model = match ($this->tab) {
            'papers' => Paper::query()->findOrFail($id),
            'decks' => FlashcardDeck::query()->findOrFail($id),
            'labs' => LabValue::query()->findOrFail($id),
            'drugs' => Drug::query()->findOrFail($id),
            'universities' => University::query()->findOrFail($id),
        };
        if ($model instanceof Paper && $model->questions()->exists()) {
            $this->toast('This paper still has questions. Unpublish it instead.', 'error');

            return;
        }
        if ($model instanceof University && $model->users()->exists()) {
            $this->toast('Students belong to this university. Hide it instead.', 'error');

            return;
        }
        $model->delete();
        $this->toast('Deleted.');
    }

    // ---- Flashcards inside a deck ----

    public function editCard(?int $id = null): void
    {
        $c = $id ? Flashcard::query()->findOrFail($id) : null;
        $this->card = ['id' => $c?->id];
        foreach (['front', 'prompt', 'back', 'detail'] as $k) {
            $this->card[$k] = $c?->getTranslations($k) ?: ['en' => ''];
        }
    }

    public function saveCard(): void
    {
        $this->validate([
            ...Translations::rules('card.front', 190), ...Translations::rules('card.back', 190),
            ...Translations::rules('card.prompt', 300, false), ...Translations::rules('card.detail', 1000, false),
        ], [], ['card.front.en' => 'front', 'card.back.en' => 'answer']);
        $c = $this->card['id'] ? Flashcard::query()->findOrFail($this->card['id']) : new Flashcard(['flashcard_deck_id' => $this->deck, 'sort' => Flashcard::query()->where('flashcard_deck_id', $this->deck)->max('sort') + 1]);
        foreach (['front', 'prompt', 'back', 'detail'] as $k) {
            $c->replaceTranslations($k, Translations::clean($this->card[$k]));
        }
        $c->save();
        $this->editCard();
        $this->toast('Card saved. Add the next one.');
    }

    public function deleteCard(int $id): void
    {
        Flashcard::query()->where('flashcard_deck_id', $this->deck)->findOrFail($id)->delete();
    }

    public function render()
    {
        return view('livewire.admin.library', [
            'papers' => $this->tab === 'papers' ? Paper::query()->withCount('questions')->orderBy('category')->orderBy('sort')->orderByDesc('year')->get() : collect(),
            'decks' => $this->tab === 'decks' ? FlashcardDeck::query()->where('is_official', true)->with(['department', 'subcategory'])->orderBy('sort')->get() : collect(),
            'cards' => $this->tab === 'decks' && $this->deck ? Flashcard::query()->where('flashcard_deck_id', $this->deck)->orderBy('sort')->get() : collect(),
            'labs' => $this->tab === 'labs' ? LabValue::query()->orderBy('category')->orderBy('sort')->get() : collect(),
            'drugs' => $this->tab === 'drugs' ? Drug::query()->orderBy('sort')->get() : collect(),
            'universities' => $this->tab === 'universities' ? University::query()->withCount('users')->orderBy('sort')->orderBy('name')->get() : collect(),
            'departments' => Department::query()->orderBy('sort')->get(),
            'subcategories' => ($this->form['department_id'] ?? null) ? Subcategory::query()->where('department_id', $this->form['department_id'])->orderBy('sort')->get() : collect(),
        ]);
    }
}
