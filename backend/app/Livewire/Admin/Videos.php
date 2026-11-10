<?php

namespace App\Livewire\Admin;

use App\Jobs\ProcessVideo;
use App\Models\DailyActivity;
use App\Models\Department;
use App\Models\Question;
use App\Models\Subcategory;
use App\Models\Topic;
use App\Models\Video;
use App\Support\Translations;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Title('Video library')]
class Videos extends AdminPage
{
    use WithFileUploads, WithPagination;

    protected ?string $permission = 'edit content';

    #[Url] public ?string $department = null;
    public ?int $selected = null;
    public bool $editing = false;
    public array $form = [];
    public $file;
    public $thumbnail;
    public array $captionFiles = [];   // locale => uploaded .vtt

    public function updatingDepartment(): void
    {
        $this->resetPage();
    }

    public function newVideo(): void
    {
        $this->selected = null;
        $this->editing = true;
        $this->form = [
            'title' => ['en' => ''], 'description' => [], 'lecturer' => '',
            'department_id' => Department::query()->orderBy('sort')->value('id'), 'subcategory_id' => null, 'topic_id' => null,
            'source' => 'upload', 'url' => '', 'access' => 'premium', 'chapters' => '', 'questions' => '', 'duration' => '',
        ];
        $this->reset(['file', 'thumbnail', 'captionFiles']);
        $this->resetValidation();
    }

    public function edit(int $id): void
    {
        $v = Video::query()->with(['chapters', 'questions'])->findOrFail($id);
        $this->selected = $v->id;
        $this->editing = true;
        $this->form = [
            'title' => $v->getTranslations('title') ?: ['en' => ''],
            'description' => $v->getTranslations('description') ?: [],
            'lecturer' => $v->lecturer ?? '',
            'department_id' => $v->department_id, 'subcategory_id' => $v->subcategory_id, 'topic_id' => $v->topic_id,
            'source' => $v->source,
            'url' => $v->source === 'youtube' ? 'https://youtu.be/'.$v->source_path : ($v->source === 'url' ? $v->source_path : ''),
            'access' => $v->access,
            'chapters' => $v->chapters->map(fn ($c) => sprintf('%02d:%02d', intdiv($c->start_seconds, 60), $c->start_seconds % 60).' '.$c->getTranslation('title', 'en'))->implode("\n"),
            'questions' => $v->questions->pluck('code')->implode(', '),
            'duration' => $v->duration_seconds ? sprintf('%d:%02d', intdiv($v->duration_seconds, 60), $v->duration_seconds % 60) : '',
        ];
        $this->reset(['file', 'thumbnail', 'captionFiles']);
        $this->resetValidation();
    }

    public function updatedFormDepartmentId(): void
    {
        $this->form['subcategory_id'] = null;
        $this->form['topic_id'] = null;
    }

    public static function youtubeId(string $url): ?string
    {
        return preg_match('~(?:youtu\.be/|youtube\.com/(?:watch\?v=|embed/|shorts/|live/))([A-Za-z0-9_-]{11})~', $url, $m) ? $m[1] : null;
    }

    public function save(bool $publish = false): void
    {
        $this->validate([
            ...Translations::rules('form.title', 190),
            ...Translations::rules('form.description', 2000, false),
            'form.department_id' => ['required', 'exists:departments,id'],
            'form.subcategory_id' => ['nullable', 'exists:subcategories,id'],
            'form.topic_id' => ['nullable', 'exists:topics,id'],
            'form.source' => ['required', 'in:upload,youtube,url'],
            'form.url' => ['nullable', 'required_if:form.source,youtube,url', 'url', 'max:500'],
            'form.access' => ['required', 'in:free,premium'],
            'form.lecturer' => ['nullable', 'string', 'max:120'],
            'form.duration' => ['nullable', 'regex:/^\d{1,3}:\d{2}$/'],
            'file' => [$this->selected ? 'nullable' : 'required_if:form.source,upload', 'nullable', 'file', 'mimetypes:video/mp4,video/quicktime,video/x-matroska,video/webm', 'max:2097152'],
            'thumbnail' => ['nullable', 'image', 'max:4096'],
            'captionFiles' => ['array'],
            'captionFiles.*' => ['nullable', 'file', 'max:1024'],
        ], [], ['form.title.en' => 'title', 'form.url' => 'video link', 'form.duration' => 'length']);

        $ytId = $this->form['source'] === 'youtube' ? self::youtubeId($this->form['url']) : null;
        if ($this->form['source'] === 'youtube' && ! $ytId) {
            $this->addError('form.url', 'That does not look like a YouTube link.');

            return;
        }

        $v = $this->selected ? Video::query()->findOrFail($this->selected) : new Video;
        $v->fill([
            'department_id' => $this->form['department_id'],
            'subcategory_id' => $this->form['subcategory_id'] ?: null,
            'topic_id' => $this->form['topic_id'] ?: null,
            'lecturer' => $this->form['lecturer'] ?: null,
            'source' => $this->form['source'],
            'access' => $this->form['access'],
        ]);
        $v->replaceTranslations('title', Translations::clean($this->form['title']));
        $v->replaceTranslations('description', Translations::clean($this->form['description']));
        if ($this->form['duration']) {
            [$m, $s] = explode(':', $this->form['duration']);
            $v->duration_seconds = (int) $m * 60 + (int) $s;
        }

        $needsProcessing = false;
        if ($this->form['source'] === 'youtube') {
            $v->source_path = $ytId;
        } elseif ($this->form['source'] === 'url') {
            $v->source_path = $this->form['url'];
        } elseif ($this->file) {
            $v->source_path = $this->file->storeAs('videos/originals', Str::uuid().'.'.$this->file->getClientOriginalExtension(), 'public');
            $v->variants = null;
            $needsProcessing = true;
        }
        $v->status = $needsProcessing ? 'processing' : ($publish ? 'published' : ($v->status === 'published' ? 'published' : 'draft'));
        $v->save();

        if ($this->thumbnail) {
            $v->update(['thumbnail_path' => $this->thumbnail->store('videos/'.$v->id, 'public')]);
        }
        $captions = $v->captions ?? [];
        foreach ($this->captionFiles as $loc => $file) {
            if ($file && in_array($loc, \App\Models\Language::contentCodes(), true)) {
                $captions[$loc] = $file->storeAs('videos/'.$v->id, 'captions-'.$loc.'.vtt', 'public');
            }
        }
        $v->update(['captions' => $captions ?: null]);

        // Chapters: one per line, "mm:ss Title"
        $v->chapters()->delete();
        foreach (preg_split('/\r?\n/', (string) $this->form['chapters']) as $line) {
            if (preg_match('/^\s*(?:(\d+):)?(\d{1,2}):(\d{2})\s+(.+)$/', $line, $m)) {
                $v->chapters()->create(['start_seconds' => (int) $m[1] * 3600 + (int) $m[2] * 60 + (int) $m[3], 'title' => ['en' => trim($m[4])]]);
            }
        }

        $codes = collect(preg_split('/[\s,]+/', strtoupper((string) $this->form['questions'])))->filter();
        $v->questions()->sync(Question::query()->whereIn('code', $codes)->pluck('id'));

        if ($needsProcessing) {
            ProcessVideo::dispatch($v->id, $publish);
        }

        $this->selected = $v->id;
        $this->file = null;
        $this->toast($needsProcessing ? 'Uploaded — compressing to 720p and 360p in the background.' : 'Saved.');
    }

    public function delete(): void
    {
        $v = Video::query()->findOrFail($this->selected);
        Storage::disk('public')->deleteDirectory('videos/'.$v->id);
        if ($v->source === 'upload' && $v->source_path) {
            Storage::disk('public')->delete($v->source_path);
        }
        $v->delete();
        $this->editing = false;
        $this->selected = null;
        $this->toast('Video deleted.');
    }

    public function render()
    {
        $all = Video::query();
        $hours = (int) round((clone $all)->sum('duration_seconds') / 3600);
        $watchThisMonth = (int) DailyActivity::query()->where('date', '>=', now()->startOfMonth())->sum('video_seconds');
        $watchLastMonth = (int) DailyActivity::query()->whereBetween('date', [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()])->sum('video_seconds');
        $views = (int) (clone $all)->sum('views_count');
        $done = (int) (clone $all)->sum('completions_count');

        return view('livewire.admin.videos', [
            'kpis' => [
                ['Lessons', number_format((clone $all)->count()), $hours.' hours of video'],
                ['Watch hours this month', number_format($watchThisMonth / 3600, 0), $watchLastMonth ? (($watchThisMonth >= $watchLastMonth ? '+' : '').round(($watchThisMonth - $watchLastMonth) / $watchLastMonth * 100).'% vs last month') : 'First month'],
                ['Watched to the end', ($views ? round($done / $views * 100) : 0).'%', 'Across all lessons'],
                ['Storage used', number_format((clone $all)->sum('size_bytes') / 1073741824, 1).' GB', 'Uploaded lessons'],
            ],
            'videos' => Video::query()->with(['department', 'subcategory'])->when($this->department, fn ($q, $d) => $q->where('department_id', $d))->latest()->paginate(20),
            'departments' => Department::query()->orderBy('sort')->get(),
            'subcategories' => ($this->form['department_id'] ?? null) ? Subcategory::query()->where('department_id', $this->form['department_id'])->orderBy('sort')->get() : collect(),
            'topics' => ($this->form['subcategory_id'] ?? null) ? Topic::query()->where('subcategory_id', $this->form['subcategory_id'])->orderBy('sort')->get() : collect(),
            'current' => $this->selected ? Video::query()->find($this->selected) : null,
        ]);
    }
}
