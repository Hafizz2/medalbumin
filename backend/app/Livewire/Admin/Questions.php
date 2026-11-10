<?php

namespace App\Livewire\Admin;

use App\Models\Department;
use App\Models\Language;
use App\Models\Paper;
use App\Models\Question;
use App\Models\Subcategory;
use App\Models\Topic;
use App\Services\Importers\QuestionImporter;
use App\Support\Translations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Intervention\Image\Laravel\Facades\Image;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Spatie\SimpleExcel\SimpleExcelWriter;

#[Title('Question bank')]
class Questions extends AdminPage
{
    use WithFileUploads, WithPagination;

    protected ?string $permission = 'edit content';

    // Filters
    #[Url] public string $search = '';
    #[Url] public ?string $department = null;
    #[Url] public ?string $paper = null;
    #[Url] public ?string $year = null;
    #[Url] public ?string $status = null;
    #[Url] public bool $hasImage = false;
    #[Url] public ?string $missing = null;   // locale with no published translation

    // Editor
    #[Url] public ?int $selected = null;
    #[Url(as: 'new')] public bool $creating = false;
    public string $locale = 'en';
    public array $stem = [];
    public array $explanation = [];
    public array $options = [];           // ['A' => ['en' => '…', 'am' => '…'], …]
    public string $correct = 'A';
    public ?int $department_id = null;
    public ?int $subcategory_id = null;
    public ?int $topic_id = null;
    public ?int $paper_id = null;
    public ?int $exam_year = null;
    public string $difficulty = 'moderate';
    public bool $is_free = false;
    public array $translation_status = [];
    public ?string $image_caption = null;
    public ?string $image_type = null;
    public $image;                        // upload
    public bool $removeImage = false;

    // Import
    public bool $showImport = false;
    public $importFile;
    public string $importStatus = 'draft';
    public ?array $importResult = null;

    public function mount(): void
    {
        if ($this->creating) {
            $this->newQuestion();
        } elseif ($this->selected) {
            $this->select($this->selected);
        }
    }

    public function updating($name): void
    {
        if (in_array($name, ['search', 'department', 'paper', 'year', 'status', 'hasImage', 'missing'], true)) {
            $this->resetPage();
        }
    }

    public function updatedLocale(): void
    {
        if (! in_array($this->locale, Language::contentCodes(), true)) {
            $this->locale = 'en';
        }
    }

    public function updatedDepartmentId(): void
    {
        $this->subcategory_id = null;
        $this->topic_id = null;
    }

    public function updatedSubcategoryId(): void
    {
        $this->topic_id = null;
    }

    public function newQuestion(): void
    {
        $this->resetValidation();
        $this->selected = null;
        $this->creating = true;
        $this->locale = 'en';
        $this->stem = ['en' => ''];
        $this->explanation = ['en' => ''];
        $this->options = collect(['A', 'B', 'C', 'D', 'E'])->mapWithKeys(fn ($k) => [$k => ['en' => '']])->all();
        $this->correct = 'A';
        $this->department_id ??= Department::query()->orderBy('sort')->value('id');
        $this->subcategory_id = $this->topic_id = $this->paper_id = $this->exam_year = null;
        $this->difficulty = 'moderate';
        $this->is_free = false;
        $this->translation_status = [];
        $this->image_caption = $this->image_type = null;
        $this->image = null;
        $this->removeImage = false;
    }

    public function select(int $id): void
    {
        $q = Question::query()->with('options')->findOrFail($id);
        $this->resetValidation();
        $this->selected = $q->id;
        $this->creating = false;
        $this->stem = $q->getTranslations('stem');
        $this->explanation = $q->getTranslations('explanation');
        $this->options = collect(['A', 'B', 'C', 'D', 'E'])->mapWithKeys(fn ($k) => [$k => $q->options->firstWhere('key', $k)?->getTranslations('text') ?? ['en' => '']])->all();
        $this->correct = $q->correct_option;
        $this->department_id = $q->department_id;
        $this->subcategory_id = $q->subcategory_id;
        $this->topic_id = $q->topic_id;
        $this->paper_id = $q->paper_id;
        $this->exam_year = $q->exam_year;
        $this->difficulty = $q->difficulty;
        $this->is_free = $q->is_free;
        $this->translation_status = $q->translation_status ?? [];
        $this->image_caption = $q->image_caption;
        $this->image_type = $q->image_type;
        $this->image = null;
        $this->removeImage = false;
        if ($this->locale !== 'en' && $q->translationState($this->locale) === 'none') {
            $this->locale = 'en';
        }
    }

    public function addTranslation(): void
    {
        $l = $this->locale;
        $this->stem[$l] = $this->stem['en'] ?? '';
        $this->explanation[$l] = $this->explanation['en'] ?? '';
        foreach ($this->options as $k => $t) {
            $this->options[$k][$l] = $t['en'] ?? '';
        }
        $this->translation_status[$l] = 'draft';
    }

    public function setTranslationStatus(string $status): void
    {
        abort_unless(in_array($status, ['draft', 'published'], true) && $this->locale !== 'en', 422);
        $this->translation_status[$this->locale] = $status;
    }

    /** $action: draft | review | publish */
    public function save(string $action = 'draft'): void
    {
        $user = auth()->user();
        if ($action === 'publish' && ! $user->can('publish content')) {
            $action = 'review';
        }

        $filledKeys = collect($this->options)->filter(fn ($t) => filled($t['en'] ?? null))->keys();
        $this->validate([
            'stem.en' => ['required', 'string', 'max:5000'],
            'explanation.en' => ['nullable', 'string', 'max:10000'],
            'options.A.en' => ['required', 'string', 'max:1000'],
            'options.B.en' => ['required', 'string', 'max:1000'],
            'options.*.*' => ['nullable', 'string', 'max:1000'],
            'correct' => ['required', Rule::in($filledKeys->all())],
            'department_id' => ['required', 'exists:departments,id'],
            'subcategory_id' => ['nullable', Rule::exists('subcategories', 'id')->where('department_id', $this->department_id)],
            'topic_id' => ['nullable', Rule::exists('topics', 'id')->where('subcategory_id', $this->subcategory_id)],
            'paper_id' => ['nullable', 'exists:papers,id'],
            'exam_year' => ['nullable', 'integer', 'between:1990,2100'],
            'difficulty' => ['required', 'in:easy,moderate,hard'],
            'image' => ['nullable', 'image', 'max:8192'],
            'image_caption' => ['nullable', 'string', 'max:190'],
            'image_type' => ['nullable', 'in:ecg,xray,ct,histology,photo,other'],
        ], ['correct.in' => 'The correct answer must be one of the filled options.']);

        $q = $this->selected ? Question::query()->findOrFail($this->selected) : new Question(['author_id' => $user->id]);
        $clean = fn (array $t) => Translations::clean($t);
        $this->translation_status = array_intersect_key($this->translation_status, array_flip(Language::contentCodes()));

        DB::transaction(function () use ($q, $action, $user, $filledKeys, $clean) {
            $paper = $this->paper_id ? Paper::query()->find($this->paper_id) : null;
            $q->fill([
                'department_id' => $this->department_id,
                'subcategory_id' => $this->subcategory_id,
                'topic_id' => $this->topic_id,
                'paper_id' => $this->paper_id,
                'exam_year' => $this->exam_year ?: $paper?->year,
                'difficulty' => $this->difficulty,
                'is_free' => $this->is_free,
                'correct_option' => $this->correct,
                'translation_status' => $this->translation_status ?: null,
                'image_caption' => $this->image_caption,
                'image_type' => $this->image_type,
                'updated_by' => $user->id,
                'status' => match ($action) {
                    'publish' => 'published',
                    'review' => 'in_review',
                    default => $q->exists && $q->status === 'published' ? 'published' : 'draft',
                },
            ]);
            $q->replaceTranslations('stem', $clean($this->stem));
            $q->replaceTranslations('explanation', $clean($this->explanation));
            if ($q->status === 'published' && ! $q->published_at) {
                $q->published_at = now();
            }
            if ($paper && ! $q->paper_position) {
                $q->paper_position = (int) $paper->questions()->max('paper_position') + 1;
            }

            if ($this->removeImage && $q->image_path) {
                Storage::disk('public')->delete($q->image_path);
                $q->image_path = null;
            }
            if ($this->image) {
                // Re-encode: strips metadata and keeps images light for slow connections.
                $path = 'questions/'.now()->format('Y/m').'/'.uniqid().'.jpg';
                Storage::disk('public')->put($path, (string) Image::read($this->image->getRealPath())->scaleDown(1600, 1600)->toJpeg(85));
                $q->image_path && Storage::disk('public')->delete($q->image_path);
                $q->image_path = $path;
            }
            $q->save();

            $q->options()->whereNotIn('key', $filledKeys)->delete();
            foreach ($filledKeys as $k) {
                $option = $q->options()->firstOrNew(['key' => $k]);
                $option->replaceTranslations('text', $clean($this->options[$k]));
                $option->save();
            }
        });

        $this->selected = $q->id;
        $this->creating = false;
        $this->image = null;
        $this->removeImage = false;
        $this->toast(match ($q->status) {
            'published' => 'Published — students can see it now.',
            'in_review' => 'Sent for review.',
            default => 'Draft saved.',
        });
    }

    public function delete(): void
    {
        if ($this->selected) {
            Question::query()->findOrFail($this->selected)->delete();
            $this->toast('Question deleted.');
            $this->selected = null;
            $this->newQuestion();
            $this->creating = false;
        }
    }

    public function import(QuestionImporter $importer): void
    {
        $this->validate([
            'importFile' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:20480'],
            'importStatus' => ['required', 'in:draft,in_review,published'],
        ]);
        $status = $this->importStatus === 'published' && ! auth()->user()->can('publish content') ? 'in_review' : $this->importStatus;
        $ext = strtolower($this->importFile->getClientOriginalExtension()) === 'xlsx' ? 'xlsx' : 'csv';

        $this->importResult = $importer->import($this->importFile->getRealPath(), auth()->user(), $status, $ext);
        $this->importFile = null;
        $this->toast($this->importResult['created'].' questions imported.');
    }

    public function downloadTemplate()
    {
        $path = storage_path('app/private/question-import-template.csv');
        @mkdir(dirname($path), 0775, true);
        // Header row comes from the keys; one example row the editors can overwrite.
        $writer = SimpleExcelWriter::create($path);
        $writer->addRow(array_combine(QuestionImporter::COLUMNS, [
            'Internal Medicine', 'Cardiology', 'Acute coronary syndrome', '', '2024', 'moderate',
            'Example stem…', 'Option A', 'Option B', 'Option C', 'Option D', '', 'C', 'Why C is correct…', 'no',
            '', '', '', '', '', '', '',
        ]));
        $writer->close();

        return response()->download($path, 'medalbumin-questions-template.csv');
    }

    public function render()
    {
        $questions = Question::query()->with(['department', 'subcategory', 'paper'])
            ->withCount(['reports as open_reports_count' => fn ($q) => $q->where('status', 'open')])
            ->when($this->search, fn ($q, $s) => $q->where(fn ($w) => $w->where('code', 'like', "%{$s}%")->orWhere('stem', 'like', "%{$s}%")))
            ->when($this->department, fn ($q, $d) => $q->where('department_id', $d))
            ->when($this->paper, fn ($q, $p) => $q->where('paper_id', $p))
            ->when($this->year, fn ($q, $y) => $q->where('exam_year', $y))
            ->when($this->status === 'reported', fn ($q) => $q->whereHas('reports', fn ($r) => $r->where('status', 'open')))
            ->when($this->status && $this->status !== 'reported', fn ($q) => $q->where('status', $this->status))
            ->when($this->hasImage, fn ($q) => $q->whereNotNull('image_path'))
            ->when($this->missing, fn ($q, $l) => $q->where(fn ($w) => $w->whereNull('translation_status->'.$l)->orWhere('translation_status->'.$l, '!=', 'published')))
            ->latest('id')->paginate(20);

        $current = $this->selected ? Question::query()->with(['editor', 'author'])->find($this->selected) : null;

        return view('livewire.admin.questions', [
            'questions' => $questions,
            'current' => $current,
            'counts' => ['questions' => Question::query()->count(), 'papers' => Paper::query()->count()],
            'departments' => Department::query()->orderBy('sort')->get(),
            'subcategories' => $this->department_id ? Subcategory::query()->where('department_id', $this->department_id)->orderBy('sort')->get() : collect(),
            'topics' => $this->subcategory_id ? Topic::query()->where('subcategory_id', $this->subcategory_id)->orderBy('sort')->get() : collect(),
            'papers' => Paper::query()->orderBy('category')->orderByDesc('year')->get(),
            'years' => Question::query()->whereNotNull('exam_year')->distinct()->orderByDesc('exam_year')->pluck('exam_year'),
            'locales' => Language::options(),
        ]);
    }
}
