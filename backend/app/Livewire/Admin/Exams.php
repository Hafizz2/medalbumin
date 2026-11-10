<?php

namespace App\Livewire\Admin;

use App\Jobs\SendPushCampaign;
use App\Models\Department;
use App\Models\Exam;
use App\Models\Paper;
use App\Models\PushCampaign;
use App\Models\University;
use App\Services\ExamBuilder;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

#[Title('Exam builder')]
class Exams extends AdminPage
{
    protected ?string $permission = 'edit content';

    #[Url] public ?int $selected = null;
    public array $form = [];
    public array $blueprint = [];
    public array $rules = [];
    public bool $notify = true;

    public function mount(): void
    {
        $this->selected ? $this->select($this->selected) : $this->newExam();
    }

    public function newExam(): void
    {
        $next = (int) Exam::query()->where('type', 'grand')->max('sequence_no') + 1;
        $this->selected = null;
        $this->form = [
            'title' => "Grand Mock #{$next} — Exit Exam",
            'description' => '',
            'type' => 'grand',
            'sequence_no' => $next,
            'starts_at' => now()->next('Saturday')->setTime(9, 0)->format('Y-m-d\TH:i'),
            'duration_minutes' => 240,
            'access' => 'all',
            'university_id' => null,
            'paper_id' => null,
            'question_count' => 200,
        ];
        $this->blueprint = Department::query()->orderBy('sort')->pluck('id')->mapWithKeys(fn ($id) => [$id => 0])->all();
        $this->rules = Exam::DEFAULT_RULES;
        $this->resetValidation();
    }

    public function select(int $id): void
    {
        $e = Exam::query()->findOrFail($id);
        $this->selected = $e->id;
        $this->form = [
            'title' => $e->title,
            'description' => $e->description,
            'type' => $e->type,
            'sequence_no' => $e->sequence_no,
            'starts_at' => $e->starts_at?->format('Y-m-d\TH:i'),
            'duration_minutes' => $e->duration_minutes,
            'access' => $e->access,
            'university_id' => $e->university_id,
            'paper_id' => $e->paper_id,
            'question_count' => $e->question_count,
        ];
        $this->blueprint = Department::query()->orderBy('sort')->pluck('id')->mapWithKeys(fn ($id) => [$id => (int) ($e->blueprint[$id] ?? 0)])->all();
        $this->rules = ($e->rules ?? []) + Exam::DEFAULT_RULES;
        $this->resetValidation();
    }

    public function step(int $deptId, int $by): void
    {
        $this->blueprint[$deptId] = max(0, (int) ($this->blueprint[$deptId] ?? 0) + $by);
    }

    public function toggleRule(string $key): void
    {
        $this->rules[$key] = ! ($this->rules[$key] ?? false);
    }

    /** $action: draft | schedule */
    public function save(string $action, ExamBuilder $builder): void
    {
        $this->validate([
            'form.title' => ['required', 'string', 'max:150'],
            'form.type' => ['required', 'in:grand,past_paper,department,daily'],
            'form.starts_at' => ['nullable', 'required_if:form.type,grand,department', 'date'],
            'form.duration_minutes' => ['required', 'integer', 'between:5,600'],
            'form.access' => ['required', 'in:all,premium,university'],
            'form.university_id' => ['nullable', 'required_if:form.access,university', 'exists:universities,id'],
            'form.paper_id' => ['nullable', 'required_if:form.type,past_paper', 'exists:papers,id'],
            'form.question_count' => ['nullable', 'integer', 'between:5,400'],
        ], [], ['form.title' => 'exam name', 'form.starts_at' => 'date & time', 'form.paper_id' => 'paper', 'form.university_id' => 'university']);

        $exam = $this->selected ? Exam::query()->findOrFail($this->selected) : new Exam(['created_by' => auth()->id()]);
        $wasScheduled = in_array($exam->status, ['scheduled', 'live'], true);
        $exam->fill([
            'title' => $this->form['title'],
            'description' => $this->form['description'] ?: null,
            'type' => $this->form['type'],
            'sequence_no' => $this->form['type'] === 'grand' ? ($this->form['sequence_no'] ?: null) : null,
            'starts_at' => $this->form['starts_at'] ? Carbon::parse($this->form['starts_at']) : null,
            'duration_minutes' => $this->form['duration_minutes'],
            'access' => $this->form['access'],
            'university_id' => $this->form['access'] === 'university' ? $this->form['university_id'] : null,
            'paper_id' => $this->form['type'] === 'past_paper' ? $this->form['paper_id'] : null,
            'blueprint' => array_filter($this->blueprint, fn ($n) => (int) $n > 0),
            'rules' => $this->rules,
            'question_count' => $this->form['type'] === 'daily' ? ($this->form['question_count'] ?: 20) : $exam->question_count,
        ]);
        if ($action === 'draft' && ! $exam->exists) {
            $exam->status = 'draft';
        }
        $exam->save();

        if ($action === 'schedule') {
            if (in_array($exam->type, ['grand', 'department'], true) && array_sum($this->blueprint) === 0) {
                $this->addError('blueprint', 'Add questions to the blueprint first.');

                return;
            }
            $count = $builder->generate($exam);
            $exam->update(['status' => match ($exam->type) {
                'past_paper' => 'published',
                'daily' => 'automatic',
                default => 'scheduled',
            }]);

            if ($this->notify && ! $wasScheduled && $exam->type === 'grand' && $exam->starts_at) {
                $campaign = PushCampaign::query()->create([
                    'title' => ['en' => $exam->title],
                    'body' => ['en' => $exam->starts_at->format('l j M, g:i A').' · '.$count.' questions. Register now to get your national rank.'],
                    'audience' => array_filter(['plan' => $exam->access === 'premium' ? 'premium' : 'all', 'university_id' => $exam->university_id]),
                    'link' => 'LiveMock',
                    'scheduled_at' => now(),
                    'created_by' => auth()->id(),
                ]);
                SendPushCampaign::dispatch($campaign->id);
            }
            $this->toast($exam->status === 'scheduled' ? "Scheduled with {$count} questions." : 'Published.');
        } else {
            $this->toast('Draft saved.');
        }

        $this->selected = $exam->id;
    }

    public function publishResults(): void
    {
        Exam::query()->whereKey($this->selected)->update(['status' => 'results_out']);
        $this->toast('Results are visible to students.');
    }

    public function delete(): void
    {
        $exam = Exam::query()->findOrFail($this->selected);
        if ($exam->sessions()->exists()) {
            $this->toast('Students have taken this exam — it cannot be deleted.', 'error');

            return;
        }
        $exam->delete();
        $this->newExam();
        $this->toast('Exam deleted.');
    }

    public function render(ExamBuilder $builder)
    {
        $exams = Exam::query()->withCount(['sessions as completed_count' => fn ($q) => $q->where('status', 'completed')])
            ->withAvg(['sessions as avg_score' => fn ($q) => $q->where('status', 'completed')], 'score_pct')
            ->orderByRaw("CASE status WHEN 'draft' THEN 1 WHEN 'scheduled' THEN 0 WHEN 'live' THEN 0 ELSE 2 END")
            ->latest('starts_at')->latest()->limit(60)->get();

        return view('livewire.admin.exams', [
            'exams' => $exams,
            'current' => $this->selected ? Exam::query()->find($this->selected) : null,
            'departments' => Department::query()->orderBy('sort')->get(),
            'available' => $builder->available(),
            'papers' => Paper::query()->published()->orderByDesc('year')->get(),
            'universities' => University::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'total' => array_sum(array_map('intval', $this->blueprint)),
        ]);
    }
}
