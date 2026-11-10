<?php

namespace App\Livewire\Admin;

use App\Models\Department;
use App\Models\Note;
use App\Models\Question;
use App\Models\Subcategory;
use App\Models\Topic;
use App\Support\HtmlSanitizer;
use App\Support\Translations;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\WithFileUploads;

#[Title('Departments & notes')]
class Notes extends AdminPage
{
    use WithFileUploads;

    protected ?string $permission = 'edit content';

    public array $open = [];
    #[Url] public ?int $sub = null;
    #[Url] public ?int $note = null;
    public bool $creating = false;

    // Note editor
    public string $locale = 'en';
    public array $title = ['en' => ''];
    public array $body = ['en' => ''];
    public string $visibility = 'all';
    public ?int $topic_id = null;
    public $attachment;
    public bool $removeAttachment = false;
    public $editorImage;

    // Department / subcategory editors
    public ?array $dept = null;
    public ?array $subForm = null;

    public function mount(): void
    {
        if ($this->note && $n = Note::query()->find($this->note)) {
            $this->sub = $n->subcategory_id;
            $this->open[$n->department_id] = true;
            $this->openNote($n->id);
        } elseif ($this->sub && $s = Subcategory::query()->find($this->sub)) {
            $this->open[$s->department_id] = true;
        } else {
            $first = Department::query()->orderBy('sort')->first();
            if ($first) {
                $this->open[$first->id] = true;
                $this->sub = $first->subcategories()->value('id');
            }
        }
    }

    public function toggle(int $id): void
    {
        $this->open[$id] = ! ($this->open[$id] ?? false);
    }

    public function pickSub(int $id): void
    {
        $this->sub = $id;
        $this->note = null;
        $this->creating = false;
    }

    public function newNote(): void
    {
        $this->note = null;
        $this->creating = true;
        $this->locale = 'en';
        $this->title = ['en' => ''];
        $this->body = ['en' => ''];
        $this->visibility = 'all';
        $this->topic_id = null;
        $this->attachment = null;
        $this->removeAttachment = false;
        $this->resetValidation();
    }

    public function openNote(int $id): void
    {
        $n = Note::query()->findOrFail($id);
        $this->note = $n->id;
        $this->creating = false;
        $this->title = $n->getTranslations('title') + ['en' => ''];
        $this->body = $n->getTranslations('body') + ['en' => ''];
        $this->visibility = $n->visibility;
        $this->topic_id = $n->topic_id;
        $this->attachment = null;
        $this->removeAttachment = false;
        $this->resetValidation();
    }

    public function updatedEditorImage(): void
    {
        $this->validate(['editorImage' => ['image', 'max:6144']]);
        $path = 'notes/images/'.now()->format('Y/m').'/'.uniqid().'.jpg';
        Storage::disk('public')->put($path, (string) Image::read($this->editorImage->getRealPath())->scaleDown(1400, 1400)->toJpeg(82));
        $this->editorImage = null;
        $this->dispatch('note-image', url: Storage::disk('public')->url($path));
    }

    /** $action: draft | review | publish */
    public function saveNote(string $action = 'draft'): void
    {
        $user = auth()->user();
        if ($action === 'publish' && ! $user->can('publish content')) {
            $action = 'review';
        }
        $subcategory = Subcategory::query()->findOrFail($this->sub);
        $this->validate([
            'title.en' => ['required', 'string', 'max:190'],
            'body.en' => ['required', 'string'],
            'title.*' => ['nullable', 'string', 'max:190'],
            'visibility' => ['required', 'in:all,premium'],
            'topic_id' => ['nullable', 'exists:topics,id'],
            'attachment' => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
        ], [], ['title.en' => 'title', 'body.en' => 'content']);

        $n = $this->note ? Note::query()->findOrFail($this->note) : new Note(['author_id' => $user->id]);
        $bodies = collect($this->body)->only(\App\Models\Language::contentCodes())->map(fn ($h) => HtmlSanitizer::clean($h))->filter(fn ($h) => trim(strip_tags($h, '<img><table>')) !== '')->all();

        $n->fill([
            'department_id' => $subcategory->department_id,
            'subcategory_id' => $subcategory->id,
            'topic_id' => $this->topic_id,
            'visibility' => $this->visibility,
            'read_minutes' => Note::estimateMinutes($bodies['en'] ?? ''),
            'status' => match ($action) {
                'publish' => 'published',
                'review' => 'in_review',
                default => $n->exists && $n->status === 'published' ? 'published' : 'draft',
            },
        ]);
        $n->replaceTranslations('title', Translations::clean($this->title));
        $n->replaceTranslations('body', $bodies);
        if ($n->status === 'published' && ! $n->published_at) {
            $n->published_at = now();
        }
        if ($this->removeAttachment && $n->attachment_path) {
            Storage::disk('public')->delete($n->attachment_path);
            $n->attachment_path = null;
        }
        if ($this->attachment) {
            $n->attachment_path && Storage::disk('public')->delete($n->attachment_path);
            $n->attachment_path = $this->attachment->storeAs('notes/pdf', Str::slug($this->title['en']).'-'.uniqid().'.pdf', 'public');
        }
        $n->save();

        $this->note = $n->id;
        $this->creating = false;
        $this->attachment = null;
        $this->toast(match ($n->status) { 'published' => 'Published to students.', 'in_review' => 'Sent for review.', default => 'Draft saved.' });
    }

    public function deleteNote(): void
    {
        Note::query()->findOrFail($this->note)->delete();
        $this->note = null;
        $this->toast('Note deleted.');
    }

    // ---- Departments ----

    public function editDept(?int $id = null): void
    {
        $d = $id ? Department::query()->findOrFail($id) : null;
        $this->dept = [
            'id' => $d?->id,
            'name' => $d?->getTranslations('name') ?? ['en' => ''],
            'short_name' => $d?->short_name ?? '',
            'icon' => $d?->icon ?? 'steth',
            'color_bg' => $d?->color_bg ?? '#DDF3F5',
            'color_ink' => $d?->color_ink ?? '#017F8E',
            'is_active' => $d?->is_active ?? true,
        ];
    }

    public function saveDept(): void
    {
        $this->validate([
            'dept.name.en' => ['required', 'string', 'max:80'],
            'dept.name.*' => ['nullable', 'string', 'max:80'],
            'dept.short_name' => ['nullable', 'string', 'max:30'],
            'dept.icon' => ['required', 'in:steth,blade,baby,female,globe,eye,heart,brain,kidney'],
            'dept.color_bg' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'dept.color_ink' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ], [], ['dept.name.en' => 'name']);
        $d = $this->dept['id'] ? Department::query()->findOrFail($this->dept['id']) : new Department(['slug' => Str::slug($this->dept['name']['en']).'-'.Str::lower(Str::random(4)), 'sort' => Department::query()->max('sort') + 1]);
        $d->fill(collect($this->dept)->only(['short_name', 'icon', 'color_bg', 'color_ink', 'is_active'])->all());
        $d->replaceTranslations('name', Translations::clean($this->dept['name']));
        $d->save();
        $this->open[$d->id] = true;
        $this->dept = null;
        $this->toast('Department saved.');
    }

    public function editSub(?int $id = null, ?int $departmentId = null): void
    {
        $s = $id ? Subcategory::query()->with('topics')->findOrFail($id) : null;
        $this->subForm = [
            'id' => $s?->id,
            'department_id' => $s?->department_id ?? $departmentId,
            'name' => $s?->getTranslations('name') ?? ['en' => ''],
            'topics' => $s ? $s->topics->map(fn ($t) => $t->getTranslation('name', 'en'))->implode("\n") : '',
        ];
    }

    public function saveSub(): void
    {
        $this->validate([
            'subForm.department_id' => ['required', 'exists:departments,id'],
            'subForm.name.en' => ['required', 'string', 'max:80'],
            'subForm.name.*' => ['nullable', 'string', 'max:80'],
            'subForm.topics' => ['nullable', 'string', 'max:5000'],
        ], [], ['subForm.name.en' => 'name']);
        $s = $this->subForm['id'] ? Subcategory::query()->findOrFail($this->subForm['id'])
            : new Subcategory(['department_id' => $this->subForm['department_id'], 'slug' => Str::slug($this->subForm['name']['en']).'-'.Str::lower(Str::random(4)), 'sort' => Subcategory::query()->where('department_id', $this->subForm['department_id'])->max('sort') + 1]);
        $s->replaceTranslations('name', Translations::clean($this->subForm['name']));
        $s->save();

        // Topics: one per line. Existing topics are matched by name so question links survive.
        $names = collect(preg_split('/\r?\n/', (string) $this->subForm['topics']))->map(fn ($t) => trim($t))->filter()->unique()->values();
        $existing = $s->topics()->get();
        foreach ($names as $i => $name) {
            $t = $existing->first(fn ($t) => $t->getTranslation('name', 'en') === $name) ?? new Topic(['subcategory_id' => $s->id]);
            $t->setTranslation('name', 'en', $name);
            $t->sort = $i;
            $t->save();
        }
        $existing->filter(fn ($t) => ! $names->contains($t->getTranslation('name', 'en')))->each(fn ($t) => $t->delete());

        $this->sub = $s->id;
        $this->open[$s->department_id] = true;
        $this->subForm = null;
        $this->toast('Subcategory saved.');
    }

    public function deleteSub(): void
    {
        $s = Subcategory::query()->findOrFail($this->subForm['id']);
        if ($s->questions()->exists() || $s->notes()->exists()) {
            $this->addError('subForm.name.en', 'Move or delete its questions and notes first.');

            return;
        }
        $s->delete();
        $this->subForm = null;
        $this->sub = null;
        $this->toast('Subcategory deleted.');
    }

    public function render()
    {
        $departments = Department::query()->orderBy('sort')->withCount('questions')
            ->with(['subcategories' => fn ($q) => $q->withCount('notes')])->get();
        $subcategory = $this->sub ? Subcategory::query()->with(['department', 'topics'])->find($this->sub) : null;
        $current = $this->note ? Note::query()->withCount('userNotes')->find($this->note) : null;

        return view('livewire.admin.notes', [
            'departments' => $departments,
            'subcategory' => $subcategory,
            'notes' => $subcategory ? Note::query()->where('subcategory_id', $subcategory->id)->latest('updated_at')->get() : collect(),
            'current' => $current,
            'topicQuestions' => $this->topic_id ? Question::query()->published()->where('topic_id', $this->topic_id)->count() : null,
        ]);
    }
}
