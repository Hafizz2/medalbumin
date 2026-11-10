<?php

namespace App\Services;

use App\Models\DailyActivity;
use App\Models\Department;
use App\Models\Question;
use App\Models\QuizSession;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProgressService
{
    public function __construct(private readonly ContentAccess $access) {}

    public function totals(User $user): array
    {
        $row = DB::table('user_question_stats')->where('user_id', $user->id)
            ->selectRaw('COUNT(*) as distinct_q, COALESCE(SUM(attempts),0) as attempts, COALESCE(SUM(correct_count),0) as correct')
            ->first();

        $studySeconds = (int) DailyActivity::query()->where('user_id', $user->id)->sum(DB::raw('study_seconds + video_seconds'));

        return [
            'answered' => (int) $row->attempts,
            'distinct_answered' => (int) $row->distinct_q,
            'accuracy' => $row->attempts > 0 ? (int) round($row->correct / $row->attempts * 100) : 0,
            'study_seconds' => $studySeconds,
            'streak' => $this->streak($user),
        ];
    }

    /** Consecutive days with any study, counting today if already active, else ending yesterday. */
    public function streak(User $user): int
    {
        $days = DailyActivity::query()->where('user_id', $user->id)
            ->where(fn ($q) => $q->where('questions_answered', '>', 0)->orWhere('cards_reviewed', '>', 0)->orWhere('video_seconds', '>', 0))
            ->where('date', '>=', today()->subDays(400))
            ->orderByDesc('date')->pluck('date')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())->all();

        $set = array_flip($days);
        $cursor = isset($set[today()->toDateString()]) ? today() : today()->subDay();
        $streak = 0;
        while (isset($set[$cursor->toDateString()])) {
            $streak++;
            $cursor = $cursor->subDay();
        }

        return $streak;
    }

    /** Last 7 days of answered questions, oldest first — the streak bar chart. */
    public function lastSevenDays(User $user): array
    {
        $rows = DailyActivity::query()->where('user_id', $user->id)->where('date', '>=', today()->subDays(6))
            ->get()->keyBy(fn ($r) => $r->date->toDateString());

        return collect(range(6, 0))->map(function ($ago) use ($rows) {
            $d = today()->subDays($ago)->toDateString();

            return ['date' => $d, 'questions' => (int) ($rows[$d]->questions_answered ?? 0)];
        })->all();
    }

    /**
     * Per-department (or per-subcategory) accuracy for this student.
     *
     * @return Collection<int, array{id:int, name:string, answered:int, accuracy:?int}>
     */
    public function subjectPerformance(User $user, ?int $departmentId = null): Collection
    {
        $groupCol = $departmentId ? 'q.subcategory_id' : 'q.department_id';

        $rows = DB::table('user_question_stats as s')
            ->join('questions as q', 'q.id', '=', 's.question_id')
            ->where('s.user_id', $user->id)
            ->when($departmentId, fn ($q) => $q->where('q.department_id', $departmentId))
            ->groupBy($groupCol)
            ->selectRaw("{$groupCol} as gid, SUM(s.attempts) as attempts, SUM(s.correct_count) as correct, COUNT(*) as distinct_q")
            ->get()->keyBy('gid');

        return $rows->map(fn ($r) => [
            'id' => (int) $r->gid,
            'answered' => (int) $r->distinct_q,
            'attempts' => (int) $r->attempts,
            'accuracy' => $r->attempts > 0 ? (int) round($r->correct / $r->attempts * 100) : null,
        ]);
    }

    /**
     * Department list for the Study tab: topics done / total, and % done.
     */
    public function departments(User $user): Collection
    {
        $departments = Department::query()->active()->get();

        $totals = $this->scopedQuestionCounts($user, 'department_id');
        $done = DB::table('user_question_stats as s')->join('questions as q', 'q.id', '=', 's.question_id')
            ->where('s.user_id', $user->id)->where('q.status', 'published')->whereNull('q.deleted_at')
            ->groupBy('q.department_id')->selectRaw('q.department_id as id, COUNT(*) as n')->pluck('n', 'id');

        $topicTotals = DB::table('topics')->join('subcategories', 'subcategories.id', '=', 'topics.subcategory_id')
            ->groupBy('subcategories.department_id')->selectRaw('subcategories.department_id as id, COUNT(*) as n')->pluck('n', 'id');
        $topicsDone = $this->topicsDone($user)->groupBy('department_id')->map->count();
        $perf = $this->subjectPerformance($user);

        return $departments->map(function (Department $d) use ($totals, $done, $topicTotals, $topicsDone, $perf) {
            $total = (int) ($totals[$d->id] ?? 0);
            $answered = (int) ($done[$d->id] ?? 0);
            $pct = $total ? (int) min(100, round($answered / $total * 100)) : 0;

            return [
                'id' => $d->id,
                'name' => $d->name,
                'short_name' => $d->short_name,
                'icon' => $d->icon,
                'color_bg' => $d->color_bg,
                'color_ink' => $d->color_ink,
                'questions' => $total,
                'answered' => $answered,
                'topics_total' => (int) ($topicTotals[$d->id] ?? 0),
                'topics_done' => (int) ($topicsDone[$d->id] ?? 0),
                'done_pct' => $pct,
                'accuracy' => $perf[$d->id]['accuracy'] ?? null,
                'status' => $pct === 0 ? 'new' : ($pct >= 100 ? 'done' : 'prog'),
            ];
        });
    }

    /** Topics where the student has answered at least half of the questions. */
    public function topicsDone(User $user): Collection
    {
        return DB::table('questions as q')
            ->join('subcategories as sc', 'sc.id', '=', 'q.subcategory_id')
            ->leftJoin('user_question_stats as s', fn ($j) => $j->on('s.question_id', '=', 'q.id')->where('s.user_id', $user->id))
            ->where('q.status', 'published')->whereNull('q.deleted_at')->whereNotNull('q.topic_id')
            ->groupBy('q.topic_id', 'sc.department_id')
            ->selectRaw('q.topic_id, sc.department_id, COUNT(*) as total, COUNT(s.question_id) as done')
            ->havingRaw('COUNT(s.question_id) * 2 >= COUNT(*)')
            ->get();
    }

    /** Published question counts the student can see, grouped by a column. */
    public function scopedQuestionCounts(User $user, string $column): Collection
    {
        $q = Question::query()->published()->groupBy($column)->selectRaw("{$column} as gid, COUNT(*) as n");

        return $this->access->scopeQuestions($q, $user)->pluck('n', 'gid');
    }

    /**
     * Exam readiness, 0–100: recent accuracy weighted by how much of the bank the student has covered.
     */
    public function readiness(User $user): array
    {
        $recent = DB::table('user_question_stats')->where('user_id', $user->id)
            ->where('last_answered_at', '>=', now()->subDays(30))
            ->selectRaw('COALESCE(SUM(correct_count),0) as c, COALESCE(SUM(attempts),0) as a')->first();
        $accuracy = $recent->a > 0 ? $recent->c / $recent->a * 100 : 0;

        $bank = max(1, Question::query()->published()->count());
        $covered = DB::table('user_question_stats')->where('user_id', $user->id)->count();
        $coverage = min(100, $covered / $bank * 100 * 2); // covering half the bank counts as full coverage

        $score = (int) round($accuracy * 0.75 + $coverage * 0.25);

        $prevAcc = DB::table('quiz_answers as a')->join('quiz_sessions as s', 's.id', '=', 'a.quiz_session_id')
            ->where('s.user_id', $user->id)->whereNotNull('a.selected_option')
            ->whereBetween('a.answered_at', [now()->subDays(60), now()->subDays(30)])
            ->selectRaw('AVG(a.is_correct) * 100 as acc')->value('acc');
        $trend = $prevAcc !== null && $recent->a > 0 ? (int) round($accuracy - (float) $prevAcc) : null;

        $weak = $this->weakSubcategories($user, 2);
        $status = $recent->a < 20 ? 'getting_started' : ($score >= 60 || ($trend ?? 0) > 0 ? 'on_track' : 'needs_work');

        $days = $user->exam_date && $user->exam_date->isFuture() ? (int) today()->diffInDays($user->exam_date) : null;

        return [
            'score' => $score,
            'status' => $status,
            'trend' => $trend,
            'weak_areas' => $weak->pluck('name')->all(),
            'exam_label' => $this->examLabel($user->target_exam),
            'days_to_exam' => $days,
        ];
    }

    public function weakSubcategories(User $user, int $limit = 3): Collection
    {
        $rows = DB::table('user_question_stats as s')
            ->join('questions as q', 'q.id', '=', 's.question_id')
            ->where('s.user_id', $user->id)->whereNotNull('q.subcategory_id')
            ->groupBy('q.subcategory_id')
            ->havingRaw('SUM(s.attempts) >= 5')
            ->selectRaw('q.subcategory_id as id, SUM(s.correct_count) / SUM(s.attempts) as acc')
            ->orderBy('acc')->limit($limit)->get();

        $subs = \App\Models\Subcategory::query()->whereIn('id', $rows->pluck('id'))->get()->keyBy('id');

        return $rows->map(fn ($r) => [
            'id' => (int) $r->id,
            'name' => $subs[$r->id]?->name,
            'department_id' => $subs[$r->id]?->department_id,
            'accuracy' => (int) round($r->acc * 100),
        ])->filter(fn ($r) => $r['name']);
    }

    public function examLabel(?string $target): string
    {
        return match ($target) {
            'qualification' => 'Qualification exam',
            'licensing' => 'Licensing exam',
            'residency' => 'Residency exam',
            default => 'Exit exam',
        };
    }

    /**
     * Overview tab: weekly accuracy trend (12 points), totals and month-over-month deltas.
     */
    public function overview(User $user): array
    {
        $start = today()->subWeeks(11)->startOfWeek();
        $weekly = DailyActivity::query()->where('user_id', $user->id)->where('date', '>=', $start)->get()
            ->groupBy(fn ($r) => $r->date->copy()->startOfWeek()->toDateString());

        $trend = [];
        $last = null;
        for ($i = 0; $i < 12; $i++) {
            $wk = $start->copy()->addWeeks($i)->toDateString();
            $rows = $weekly[$wk] ?? collect();
            $a = $rows->sum('questions_answered');
            $value = $a > 0 ? (int) round($rows->sum('correct_answers') / $a * 100) : $last;
            $trend[] = ['week' => $wk, 'accuracy' => $value];
            $last = $value;
        }

        $month = fn (Carbon $from, Carbon $to) => DailyActivity::query()->where('user_id', $user->id)->whereBetween('date', [$from, $to])
            ->selectRaw('COALESCE(SUM(questions_answered),0) q, COALESCE(SUM(correct_answers),0) c, COALESCE(SUM(study_seconds + video_seconds),0) t')->first();
        $now = $month(today()->subDays(29), today());
        $prev = $month(today()->subDays(59), today()->subDays(30));
        $pct = fn ($a, $b) => $b > 0 ? (int) round(($a - $b) / $b * 100) : null;
        $accNow = $now->q > 0 ? $now->c / $now->q * 100 : null;
        $accPrev = $prev->q > 0 ? $prev->c / $prev->q * 100 : null;

        $avgScore = QuizSession::query()->where('user_id', $user->id)->where('status', 'completed')->avg('score_pct');

        return [
            'totals' => $this->totals($user),
            'trend' => $trend,
            'accuracy_delta' => $accNow !== null && $accPrev !== null ? (int) round($accNow - $accPrev) : null,
            'questions_delta_pct' => $pct((int) $now->q, (int) $prev->q),
            'study_delta_pct' => $pct((int) $now->t, (int) $prev->t),
            'average_score' => $avgScore !== null ? (int) round((float) $avgScore) : null,
            'target_score' => $user->target_score,
        ];
    }

    /** Mock exams tab: completed timed sessions, newest first. */
    public function mocks(User $user, int $limit = 30): Collection
    {
        return QuizSession::query()->where('user_id', $user->id)->where('status', 'completed')
            ->where(fn ($q) => $q->where('mode', 'timed')->orWhereIn('type', ['exam', 'past_paper']))
            ->latest('completed_at')->limit($limit)->get();
    }
}
