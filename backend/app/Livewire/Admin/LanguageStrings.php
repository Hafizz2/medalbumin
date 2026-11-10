<?php

namespace App\Livewire\Admin;

use App\Models\Language;
use App\Models\UiTranslation;
use App\Services\UiTranslationService;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * Admin → Languages → Translate app. English is the source; other languages are edited row by row
 * or imported/exported as JSON for outside translators.
 */
#[Title('Translate app')]
class LanguageStrings extends AdminPage
{
    use WithFileUploads, WithPagination;

    protected ?string $permission = 'edit content';

    public string $code;
    #[Url] public string $search = '';
    #[Url] public bool $missingOnly = false;
    public array $values = [];
    public $file;
    public bool $prune = false;
    public ?array $importResult = null;

    public function mount(string $code): void
    {
        abort_unless(in_array($code, Language::contentCodes(), true), 404);
        $this->code = $code;
    }

    public function updating($name): void
    {
        if (in_array($name, ['search', 'missingOnly'], true)) {
            $this->resetPage();
        }
    }

    public function saveRow(string $key, UiTranslationService $ui): void
    {
        // Keys contain dots (home.greeting), which Livewire stores as nested arrays.
        $ui->save($this->code, $key, (string) data_get($this->values, $key, ''), auth()->user());
        $this->resetErrorBag("values.{$key}");
        $this->dispatch('saved-row', key: $key);
    }

    public function import(UiTranslationService $ui): void
    {
        if ($this->code === Language::DEFAULT) {
            abort_unless(auth()->user()->hasRole('Super admin'), 403, 'Only a super admin can change the English source text.');
        }
        $this->validate(['file' => ['required', 'file', 'mimetypes:application/json,text/plain', 'max:2048']]);
        $data = json_decode((string) file_get_contents($this->file->getRealPath()), true);
        if (! is_array($data)) {
            throw ValidationException::withMessages(['file' => 'This is not a valid JSON file.']);
        }
        $this->importResult = $ui->import($this->code, $data, auth()->user(), $this->prune && $this->code === Language::DEFAULT);
        activity()->causedBy(auth()->user())->log("Imported {$this->importResult['saved']} app texts for {$this->code}");
        $this->file = null;
        $this->values = [];
        $this->toast($this->importResult['saved'].' texts saved.');
    }

    public function export(UiTranslationService $ui)
    {
        $data = $this->code === Language::DEFAULT
            ? $ui->source()
            : UiTranslation::query()->where('locale', $this->code)->orderBy('key')->pluck('value', 'key')->all();
        // Missing keys exported with English, so translators see what to replace.
        if ($this->code !== Language::DEFAULT) {
            $data = array_merge(array_map(fn () => '', $ui->source()), $data);
        }

        return response()->streamDownload(
            fn () => print(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            "medalbumin-app-{$this->code}.json",
            ['Content-Type' => 'application/json'],
        );
    }

    public function render(UiTranslationService $ui)
    {
        $lang = Language::query()->findOrFail($this->code);
        $translated = $this->code === Language::DEFAULT ? collect() : UiTranslation::query()->where('locale', $this->code)->pluck('value', 'key');

        $rows = UiTranslation::query()->where('locale', Language::DEFAULT)
            ->when($this->search, fn ($q, $s) => $q->where(fn ($w) => $w->where('key', 'like', "%{$s}%")->orWhere('value', 'like', "%{$s}%")))
            ->when($this->missingOnly && $this->code !== Language::DEFAULT, fn ($q) => $q->whereNotIn('key', $translated->keys()))
            ->orderBy('key')->paginate(40);

        foreach ($rows as $r) {
            if (! is_string(data_get($this->values, $r->key))) {
                data_set($this->values, $r->key, $translated[$r->key] ?? '');
            }
        }
        $coverage = $ui->coverage();

        return view('livewire.admin.language-strings', [
            'lang' => $lang,
            'rows' => $rows,
            'total' => $coverage['total'],
            'done' => $this->code === Language::DEFAULT ? $coverage['total'] : ($coverage['translated'][$this->code] ?? 0),
            'isSource' => $this->code === Language::DEFAULT,
        ]);
    }
}
