<?php

namespace App\Services;

use App\Models\Note;
use App\Models\Question;
use App\Models\StudyPlan;
use App\Models\StudyPlanTask;
use App\Models\Subcategory;
use App\Models\User;
use App\Models\Video;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Adaptive study plan. Days are generated lazily (when first viewed) so the
 * plan always reflects the student's latest weak areas.
 *
 * Phase 1 (first 60%): walk through every subcategory, least-covered first.
 * Phase 2 (next 25%): revise the weakest subcategories.
 * Phase 3 (last 15%): timed mocks plus weak-area review.
 */
class StudyPlanGenerator
{
    public function __construct(private readonly ProgressService $progress, private readonly ContentAccess $access) {}

    public function create(User $user, Carbon $examDate, int $dailyMinutes = 180, ?string $label = null): StudyPlan
    {
        return DB::transaction(function () use ($user, $examDate, $dailyMinutes, $label) {
            $user->studyPlans()->where('status', 'active')->update(['status' => 'archived']);
            $user->forceFill(['exam_date' => $examDate])->save();

            return $user->studyPlans()->create([
                'exam_label' => $label ?? $this->progress->examLabel($user->target_exam),
                'exam_date' => $examDate,
                'start_date' => today(),
                'daily_minutes' => $dailyMinutes,
            ]);
        });
    }

    public function activePlan(User $user): ?StudyPlan
    {
        return $user->studyPlans()->where('status', 'active')->latest()->first();
    }

    /** @return Collection<int, StudyPlanTask> */
    public function tasksFor(StudyPlan $plan, Carbon $date): Collection
    {
        $existing = $plan->tasks()->whereDate('date', $date)->get();
        if ($existing->isNotEmpty() || $date->lt(today()) || $date->lt($plan->start_date) || $date->gt($plan->exam_date)) {
            return $existing;
        }

        return $this->generateDay($plan, $date);
    }

    private function generateDay(StudyPlan $plan, Carbon $date): Collection
    {
        $user = $plan->user;
        $phase = $plan->phase($date);
        $dayIndex = (int) $plan->start_date->diffInDays($date);
        $isMockDay = $phase === 3 || ($dayIndex > 0 && $dayIndex % 7 === 5);

        $focus = $this->focusSubcategory($user, $phase, $dayIndex);
        $budget = $plan->daily_minutes;
        $tasks = [];

        if ($focus) {
            $qCount = $phase === 1 ? 40 : 30;
            $tasks[] = [
                'kind' => 'questions',
                'title' => "{$qCount} questions · {$focus->name}",
                'meta' => $focus->department->name.' · practice mode',
                'minutes' => (int) round($qCount * 1.2),
                'target' => ['department_id' => $focus->department_id, 'subcategory_id' => $focus->id, 'size' => $qCount],
            ];

            $note = Note::query()->published()->where('subcategory_id', $focus->id)
                ->whereNotExists(fn ($q) => $q->from('note_reads')->whereColumn('note_reads.note_id', 'notes.id')->where('note_reads.user_id', $user->id))
                ->when(! $this->access->isPremium($user), fn ($q) => $q->where('visibility', 'all'))
                ->orderBy('id')->first();
            if ($note) {
                $tasks[] = ['kind' => 'read', 'title' => $note->title, 'meta' => $note->read_minutes.' min read · official note', 'minutes' => $note->read_minutes, 'target' => ['note_id' => $note->id]];
            }

            $video = Video::query()->published()->where('subcategory_id', $focus->id)
                ->whereNotExists(fn ($q) => $q->from('video_progress')->whereColumn('video_progress.video_id', 'videos.id')->where('video_progress.user_id', $user->id)->whereNotNull('completed_at'))
                ->when(! $this->access->isPremium($user), fn ($q) => $q->where('access', 'free'))
                ->orderBy('id')->first();
            if ($video) {
                $min = max(1, (int) round($video->duration_seconds / 60));
                $tasks[] = ['kind' => 'video', 'title' => $video->title, 'meta' => $min.' min video', 'minutes' => $min, 'target' => ['video_id' => $video->id]];
            }
        }

        $due = DB::table('flashcard_reviews')->where('user_id', $user->id)->where('due_at', '<=', $date->copy()->endOfDay())->count();
        $tasks[] = [
            'kind' => 'cards',
            'title' => $due > 0 ? "{$due} flashcards due" : '20 new flashcards',
            'meta' => 'Spaced repetition review',
            'minutes' => max(10, (int) ceil(max($due, 20) / 3)),
            'target' => ['due' => $due],
        ];

        if ($isMockDay) {
            $weak = $this->progress->weakSubcategories($user, 2);
            $size = $phase === 3 ? 60 : 20;
            $tasks[] = [
                'kind' => 'mock',
                'title' => "Mini mock · {$size} Q timed",
                'meta' => $weak->isNotEmpty() ? 'Mixed: '.$weak->pluck('name')->implode(' + ').' (your weakest)' : 'Mixed departments',
                'minutes' => (int) round($size * 1.2),
                'target' => ['subcategory_ids' => $weak->pluck('id')->all(), 'size' => $size, 'mode' => 'timed'],
            ];
        }

        // Keep within the daily time budget (questions and cards always stay).
        $total = 0;
        $rows = [];
        foreach ($tasks as $i => $t) {
            if ($total + $t['minutes'] > $budget && ! in_array($t['kind'], ['questions', 'cards'], true)) {
                continue;
            }
            $total += $t['minutes'];
            $rows[] = $t + ['date' => $date->toDateString(), 'position' => $i];
        }

        foreach ($rows as $r) {
            $plan->tasks()->create($r);
        }

        return $plan->tasks()->whereDate('date', $date)->get();
    }

    private function focusSubcategory(User $user, int $phase, int $dayIndex): ?Subcategory
    {
        if ($phase > 1) {
            $weak = $this->progress->weakSubcategories($user, 3);
            if ($weak->isNotEmpty()) {
                return Subcategory::with('department')->find($weak->values()[$dayIndex % $weak->count()]['id']);
            }
        }

        // Least covered subcategory that has questions the student can practise.
        $counts = $this->access->scopeQuestions(Question::query()->published()->whereNotNull('subcategory_id'), $user)
            ->groupBy('subcategory_id')->selectRaw('subcategory_id, COUNT(*) as n')->pluck('n', 'subcategory_id');
        if ($counts->isEmpty()) {
            return null;
        }
        $answered = DB::table('user_question_stats as s')->join('questions as q', 'q.id', '=', 's.question_id')
            ->where('s.user_id', $user->id)->groupBy('q.subcategory_id')->selectRaw('q.subcategory_id, COUNT(*) as n')->pluck('n', 'subcategory_id');

        $id = $counts->keys()->sortBy(fn ($id) => [($answered[$id] ?? 0) / $counts[$id], $id])->first();

        return Subcategory::with('department')->find($id);
    }

    /** Days in the last week that still have unfinished tasks. */
    public function missedDays(StudyPlan $plan): int
    {
        return $plan->tasks()->whereNull('completed_at')
            ->whereDate('date', '<', today())->whereDate('date', '>=', today()->subDays(7))
            ->distinct()->count('date');
    }

    /** Spreads unfinished tasks from the past week over the next five days. */
    public function rebalance(StudyPlan $plan): int
    {
        $missed = $plan->tasks()->whereNull('completed_at')
            ->whereDate('date', '<', today())->whereDate('date', '>=', today()->subDays(7))->get();
        $span = max(1, min(5, (int) today()->diffInDays($plan->exam_date) + 1));

        foreach ($missed->values() as $i => $task) {
            $day = today()->addDays($i % $span);
            $this->tasksFor($plan, $day); // make sure the regular day exists first
            $task->update(['date' => $day->toDateString(), 'position' => 10 + $i]);
        }

        return $missed->count();
    }

    /** Status of each day for the week strip: done, missed, today, mock or ''. */
    public function weekStatus(StudyPlan $plan, Carbon $weekStart): array
    {
        $tasks = $plan->tasks()->whereBetween('date', [$weekStart->toDateString(), $weekStart->copy()->addDays(6)->toDateString()])->get()
            ->groupBy(fn ($t) => $t->date->toDateString());

        return collect(range(0, 6))->map(function ($i) use ($weekStart, $tasks) {
            $d = $weekStart->copy()->addDays($i);
            $day = $tasks[$d->toDateString()] ?? collect();
            $status = match (true) {
                $d->isToday() => 'today',
                $day->isNotEmpty() && $day->every(fn ($t) => $t->completed_at) => 'done',
                $d->isPast() && $day->isNotEmpty() => 'missed',
                $day->contains('kind', 'mock') => 'mock',
                default => '',
            };

            return ['date' => $d->toDateString(), 'label' => $d->format('D'), 'day' => (int) $d->format('j'), 'status' => $status];
        })->all();
    }
}
