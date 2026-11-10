<?php

namespace App\Services;

use App\Models\DailyActivity;
use App\Models\Exam;
use App\Models\Paper;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\QuizAnswer;
use App\Models\QuizSession;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class QuizService
{
    public const SIZES = [10, 20, 40, 60];

    public const POOLS = ['unused', 'wrong', 'bookmarked', 'all'];

    public function __construct(private readonly ContentAccess $access) {}

    /**
     * Starts a new session.
     *
     * @param  array<string, mixed>  $p  validated request data (see StartQuizRequest)
     */
    public function start(User $user, array $p): QuizSession
    {
        if ($user->is_guest && $this->access->guestQuotaLeft($user) === 0) {
            throw new HttpException(402, 'You have used your free questions. Create a free account to keep practising.');
        }

        return match ($p['source']) {
            'paper' => $this->startPaper($user, Paper::query()->findOrFail($p['paper_id']), $p['mode'] ?? 'practice'),
            'exam' => $this->startExam($user, Exam::query()->findOrFail($p['exam_id'])),
            'retry' => $this->startRetry($user, QuizSession::query()->where('user_id', $user->id)->findOrFail($p['session_id'])),
            'questions' => $this->createSession($user, 'practice', 'practice', 'Practice', $this->pickByIds($user, $p['question_ids'])),
            default => $this->startCustom($user, $p),
        };
    }

    /** Quiz builder, subcategory practice, mistakes and bookmarks all use this path. */
    private function startCustom(User $user, array $p): QuizSession
    {
        $size = (int) ($p['size'] ?? 20);
        $mode = $p['mode'] ?? 'practice';
        $pools = $p['pools'] ?? ['all'];
        if ($p['source'] === 'mistakes') {
            $pools = ['wrong'];
        } elseif ($p['source'] === 'bookmarks') {
            $pools = ['bookmarked'];
        }

        $q = $this->access->scopeQuestions(Question::query()->published(), $user)->select('questions.id');
        if (! empty($p['department_ids']) || ! empty($p['subcategory_ids'])) {
            $q->where(function (Builder $w) use ($p) {
                if (! empty($p['department_ids'])) {
                    $w->orWhereIn('questions.department_id', $p['department_ids']);
                }
                if (! empty($p['subcategory_ids'])) {
                    $w->orWhereIn('questions.subcategory_id', $p['subcategory_ids']);
                }
            });
        }
        if (! empty($p['topic_id'])) {
            $q->where('questions.topic_id', $p['topic_id']);
        }
        if (! empty($p['difficulty'])) {
            $q->where('questions.difficulty', $p['difficulty']);
        }
        $this->applyPools($q, $user, $pools);

        $ids = $q->inRandomOrder()->limit($size)->pluck('id');
        if ($ids->isEmpty()) {
            throw ValidationException::withMessages(['pools' => 'No questions match these choices yet. Try more subjects or another question pool.']);
        }

        $title = match ($p['source']) {
            'mistakes' => 'Mistakes review',
            'bookmarks' => 'Bookmarked questions',
            default => $p['title'] ?? $this->titleFor($p),
        };

        return $this->createSession($user, $p['source'] === 'mistakes' ? 'review' : 'practice', $mode, $title, $ids, [
            'department_ids' => $p['department_ids'] ?? [],
            'subcategory_ids' => $p['subcategory_ids'] ?? [],
            'pools' => $pools,
        ], $mode === 'timed' ? (int) round($ids->count() * $this->secondsPerQuestion()) : null);
    }

    private function applyPools(Builder $q, User $user, array $pools): void
    {
        if (in_array('all', $pools, true) || ! $pools) {
            return;
        }
        $q->where(function (Builder $w) use ($user, $pools) {
            if (in_array('unused', $pools, true)) {
                $w->orWhereNotExists(fn ($s) => $s->from('user_question_stats')->whereColumn('user_question_stats.question_id', 'questions.id')->where('user_question_stats.user_id', $user->id));
            }
            if (in_array('wrong', $pools, true)) {
                $w->orWhereExists(fn ($s) => $s->from('user_question_stats')->whereColumn('user_question_stats.question_id', 'questions.id')->where('user_question_stats.user_id', $user->id)->where('last_correct', false));
            }
            if (in_array('bookmarked', $pools, true)) {
                $w->orWhereExists(fn ($s) => $s->from('bookmarks')->whereColumn('bookmarks.question_id', 'questions.id')->where('bookmarks.user_id', $user->id));
            }
        });
    }

    private function titleFor(array $p): string
    {
        $subs = \App\Models\Subcategory::query()->whereIn('id', $p['subcategory_ids'] ?? [])->get()->pluck('name');
        $depts = \App\Models\Department::query()->whereIn('id', $p['department_ids'] ?? [])->get()->map(fn ($d) => $d->short_name ?: $d->name);
        $names = $depts->merge($subs)->filter()->values();

        return match (true) {
            $names->isEmpty() => 'Mixed practice',
            $names->count() <= 2 => $names->implode(' + '),
            default => $names->take(2)->implode(' + ').' +'.($names->count() - 2),
        };
    }

    private function startPaper(User $user, Paper $paper, string $mode): QuizSession
    {
        abort_unless($paper->status === 'published', 404);
        if (! $this->access->canOpenPaper($user, $paper)) {
            throw new HttpException(402, 'This past paper is part of the premium plan.');
        }

        // Resume an unfinished attempt instead of starting over.
        $open = QuizSession::query()->where('user_id', $user->id)->where('paper_id', $paper->id)->where('status', 'in_progress')->latest()->first();
        if ($open && ! $open->isExpired()) {
            return $open;
        }

        $ids = $paper->questions()->published()->pluck('id');
        abort_if($ids->isEmpty(), 422, 'This paper has no questions yet.');

        $limit = $mode === 'timed' ? ($paper->time_limit_minutes ? $paper->time_limit_minutes * 60 : (int) round($ids->count() * $this->secondsPerQuestion())) : null;

        return $this->createSession($user, 'past_paper', $mode, $paper->label(), $ids, ['paper_id' => $paper->id], $limit, paperId: $paper->id, shuffle: false);
    }

    private function startExam(User $user, Exam $exam): QuizSession
    {
        if (! $exam->isOpen()) {
            throw new HttpException(423, $exam->starts_at && $exam->starts_at->isFuture()
                ? 'This exam opens on '.$exam->starts_at->format('D j M, g:i A').'.'
                : 'This exam is closed.');
        }
        if (! $exam->canBeTakenBy($user)) {
            throw new HttpException(402, 'This exam is only for '.($exam->access === 'premium' ? 'premium students.' : 'students of '.$exam->university?->name.'.'));
        }

        $existing = QuizSession::query()->where('user_id', $user->id)->where('exam_id', $exam->id)->latest()->first();
        // Ranked exams can be taken once; daily minis and published papers can be repeated.
        if ($existing && ($existing->status === 'in_progress' || $exam->type === 'grand')) {
            return $existing;
        }

        $ids = $exam->questions()->pluck('questions.id');
        if ($exam->type === 'daily') {
            $ids = $this->dailyMiniQuestions($user, $exam->question_count ?: 20);
        }
        abort_if($ids->isEmpty(), 422, 'This exam has no questions yet.');

        if ($exam->type === 'grand') {
            $exam->registrations()->syncWithoutDetaching([$user->id => ['created_at' => now()]]);
        }

        return $this->createSession($user, 'exam', 'timed', $exam->title, $ids, ['exam_id' => $exam->id], $exam->duration_minutes * 60,
            examId: $exam->id, shuffle: $exam->rule('shuffle'), shuffleOptions: $exam->rule('shuffle_options'));
    }

    /** "Daily mini mock · weak-topic mix": half from the student's weakest subcategories, half unused. */
    public function dailyMiniQuestions(User $user, int $count): Collection
    {
        $weak = DB::table('user_question_stats as s')
            ->join('questions as q', 'q.id', '=', 's.question_id')
            ->where('s.user_id', $user->id)->whereNotNull('q.subcategory_id')
            ->groupBy('q.subcategory_id')
            ->havingRaw('SUM(s.attempts) >= 5')
            ->orderByRaw('SUM(s.correct_count) / SUM(s.attempts)')
            ->limit(2)->pluck('q.subcategory_id');

        $base = fn () => $this->access->scopeQuestions(Question::query()->published(), $user);
        $ids = $weak->isNotEmpty()
            ? $base()->whereIn('subcategory_id', $weak)->inRandomOrder()->limit(intdiv($count, 2))->pluck('id')
            : collect();
        $rest = $base()->whereNotIn('id', $ids)->inRandomOrder()->limit($count - $ids->count())->pluck('id');

        return $ids->merge($rest)->shuffle()->values();
    }

    private function startRetry(User $user, QuizSession $from): QuizSession
    {
        $ids = $from->answers()->where(fn ($q) => $q->where('is_correct', false)->orWhereNull('selected_option'))->pluck('question_id');
        if ($ids->isEmpty()) {
            throw ValidationException::withMessages(['session_id' => 'Nothing to retry — you got every question right.']);
        }
        $ids = $this->access->scopeQuestions(Question::query()->published()->whereIn('id', $ids), $user)->pluck('id');

        return $this->createSession($user, 'review', 'practice', 'Retry: '.$from->title, $ids, ['retry_of' => $from->id]);
    }

    private function pickByIds(User $user, array $ids): Collection
    {
        $allowed = $this->access->scopeQuestions(Question::query()->published()->whereIn('id', $ids), $user)->pluck('id');
        if ($allowed->isEmpty()) {
            throw new HttpException(402, 'These questions are part of the premium plan.');
        }

        return collect($ids)->filter(fn ($id) => $allowed->contains($id))->values();
    }

    private function createSession(
        User $user, string $type, string $mode, string $title, Collection $ids, array $config = [],
        ?int $timeLimit = null, ?int $examId = null, ?int $paperId = null, bool $shuffle = false, bool $shuffleOptions = false,
    ): QuizSession {
        return DB::transaction(function () use ($user, $type, $mode, $title, $ids, $config, $timeLimit, $examId, $paperId, $shuffle, $shuffleOptions) {
            $ids = $shuffle ? $ids->shuffle()->values() : $ids->values();
            $session = QuizSession::query()->create([
                'user_id' => $user->id,
                'type' => $type,
                'mode' => $mode,
                'title' => mb_strimwidth($title, 0, 250),
                'exam_id' => $examId,
                'paper_id' => $paperId,
                'config' => $config,
                'question_count' => $ids->count(),
                'time_limit_seconds' => $mode === 'timed' ? $timeLimit : null,
                'started_at' => now(),
            ]);

            $optionKeys = $shuffleOptions
                ? QuestionOption::query()->whereIn('question_id', $ids)->get(['question_id', 'key'])->groupBy('question_id')
                : collect();

            QuizAnswer::query()->insert($ids->map(fn ($id, $i) => [
                'quiz_session_id' => $session->id,
                'question_id' => $id,
                'position' => $i + 1,
                'option_order' => $shuffleOptions && $optionKeys->has($id)
                    ? json_encode($optionKeys[$id]->pluck('key')->shuffle()->values())
                    : null,
            ])->all());

            return $session;
        });
    }

    /**
     * Records an answer. In practice mode the result is returned straight away
     * and statistics are updated; in timed mode results stay hidden until finish.
     */
    public function answer(QuizSession $session, int $questionId, ?string $option, int $timeMs, bool $flagged = false): QuizAnswer
    {
        if ($session->status !== 'in_progress') {
            throw ValidationException::withMessages(['session' => 'This session has already ended.']);
        }
        if ($session->isTimed() && $session->isExpired()) {
            $this->finish($session);
            throw ValidationException::withMessages(['session' => 'Time is up. Your answers were submitted.']);
        }

        $user = $session->user;
        if ($user->is_guest && $this->access->guestQuotaLeft($user) === 0) {
            throw new HttpException(402, 'You have used your free questions. Create a free account to keep practising.');
        }

        /** @var QuizAnswer $answer */
        $answer = $session->answers()->where('question_id', $questionId)->firstOrFail();
        $question = Question::withTrashed()->with('options')->findOrFail($questionId);

        if ($option !== null && ! $question->options->contains('key', $option)) {
            throw ValidationException::withMessages(['option' => 'Unknown option.']);
        }

        // Practice answers are final once given.
        if ($session->mode === 'practice' && $answer->answered_at) {
            return $answer;
        }

        $timeMs = max(0, min($timeMs, 30 * 60 * 1000));

        DB::transaction(function () use ($session, $answer, $question, $option, $timeMs, $flagged) {
            $answer->fill([
                'selected_option' => $option,
                'is_correct' => $option === null ? null : $option === $question->correct_option,
                'time_spent_ms' => $answer->time_spent_ms + $timeMs,
                'is_flagged' => $flagged,
                'answered_at' => $option === null ? $answer->answered_at : now(),
            ])->save();

            if ($session->mode === 'practice' && $option !== null) {
                $this->recordStats($session->user_id, $question, $option, $timeMs);
            }

            $session->forceFill([
                'answered_count' => $session->answers()->whereNotNull('selected_option')->count(),
                'correct_count' => $session->answers()->where('is_correct', true)->count(),
            ])->save();
        });

        return $answer->refresh();
    }

    /** Updates global question stats, the student's own stats and today's activity. */
    private function recordStats(int $userId, Question $question, string $option, int $timeMs): void
    {
        $correct = $option === $question->correct_option;

        Question::withTrashed()->whereKey($question->id)->update([
            'attempts_count' => DB::raw('attempts_count + 1'),
            'correct_count' => DB::raw('correct_count + '.($correct ? 1 : 0)),
            'total_time_ms' => DB::raw('total_time_ms + '.(int) $timeMs),
        ]);
        QuestionOption::query()->where('question_id', $question->id)->where('key', $option)->increment('pick_count');

        $existing = DB::table('user_question_stats')->where('user_id', $userId)->where('question_id', $question->id)->first();
        if ($existing) {
            DB::table('user_question_stats')->where('user_id', $userId)->where('question_id', $question->id)->update([
                'attempts' => $existing->attempts + 1,
                'correct_count' => $existing->correct_count + ($correct ? 1 : 0),
                'last_correct' => $correct,
                'last_answered_at' => now(),
            ]);
        } else {
            DB::table('user_question_stats')->insert([
                'user_id' => $userId,
                'question_id' => $question->id,
                'attempts' => 1,
                'correct_count' => $correct ? 1 : 0,
                'last_correct' => $correct,
                'last_answered_at' => now(),
            ]);
        }

        $this->bumpActivity($userId, ['questions_answered' => 1, 'correct_answers' => $correct ? 1 : 0, 'study_seconds' => intdiv($timeMs, 1000)]);
    }

    public function bumpActivity(int $userId, array $increments): void
    {
        $key = ['user_id' => $userId, 'date' => today()->toDateString()];
        DB::table('daily_activities')->insertOrIgnore($key);
        $updates = collect($increments)->filter(fn ($by) => $by > 0)->map(fn ($by, $col) => DB::raw("{$col} + ".(int) $by))->all();
        if ($updates) {
            DB::table('daily_activities')->where($key)->update($updates);
        }
        User::query()->whereKey($userId)->update(['last_active_at' => now()]);
    }

    public function finish(QuizSession $session): QuizSession
    {
        if ($session->status !== 'in_progress') {
            return $session;
        }

        return DB::transaction(function () use ($session) {
            $session = QuizSession::query()->lockForUpdate()->findOrFail($session->id);
            if ($session->status !== 'in_progress') {
                return $session;
            }

            $answers = $session->answers()->with('question')->get();
            if ($session->mode === 'timed') {
                foreach ($answers->whereNotNull('selected_option') as $a) {
                    $a->question && $this->recordStats($session->user_id, $a->question, $a->selected_option, $a->time_spent_ms);
                }
            }

            $elapsed = (int) $session->started_at->diffInSeconds(now());
            $correct = $answers->where('is_correct', true)->count();

            $session->forceFill([
                'status' => 'completed',
                'completed_at' => now(),
                'answered_count' => $answers->whereNotNull('selected_option')->count(),
                'correct_count' => $correct,
                'score_pct' => $session->question_count ? round($correct / $session->question_count * 100, 2) : 0,
                'time_spent_seconds' => $session->time_limit_seconds ? min($elapsed, $session->time_limit_seconds) : $elapsed,
            ])->save();

            return $session;
        });
    }

    /**
     * Stores answers given offline as one completed practice session, and counts them
     * in the student's statistics. The same batch is never imported twice.
     *
     * @param  array<int, array{question_id:int, option:string, time_ms?:?int, answered_at?:?string}>  $attempts
     */
    public function importOffline(User $user, array $attempts, string $batchId): ?QuizSession
    {
        $existing = QuizSession::query()->where('user_id', $user->id)->where('config->offline_batch', $batchId)->first();
        if ($existing) {
            return $existing;
        }

        $attempts = collect($attempts)->unique('question_id')->values();
        $questions = $this->access->scopeQuestions(Question::query()->published()->whereIn('id', $attempts->pluck('question_id')), $user)
            ->with('options')->get()->keyBy('id');
        $valid = $attempts
            ->filter(fn ($a) => isset($questions[$a['question_id']]) && $questions[$a['question_id']]->options->contains('key', $a['option']))
            ->values();
        if ($valid->isEmpty()) {
            return null;
        }

        $when = fn ($a) => isset($a['answered_at']) ? \Illuminate\Support\Carbon::parse($a['answered_at'])->min(now()) : now();

        return DB::transaction(function () use ($user, $valid, $questions, $batchId, $when) {
            $startedAt = $valid->map($when)->min();
            $session = QuizSession::query()->create([
                'user_id' => $user->id,
                'type' => 'practice',
                'mode' => 'practice',
                'title' => 'Offline practice',
                'config' => ['offline_batch' => $batchId],
                'question_count' => $valid->count(),
                'started_at' => $startedAt,
            ]);

            $correct = 0;
            $time = 0;
            foreach ($valid as $i => $a) {
                $q = $questions[$a['question_id']];
                $ms = max(0, min((int) ($a['time_ms'] ?? 0), 30 * 60 * 1000));
                $isCorrect = $a['option'] === $q->correct_option;
                $correct += $isCorrect ? 1 : 0;
                $time += $ms;
                QuizAnswer::query()->create([
                    'quiz_session_id' => $session->id,
                    'question_id' => $q->id,
                    'position' => $i + 1,
                    'selected_option' => $a['option'],
                    'is_correct' => $isCorrect,
                    'time_spent_ms' => $ms,
                    'answered_at' => $when($a),
                ]);
                $this->recordStats($user->id, $q, $a['option'], $ms);
            }

            $session->forceFill([
                'status' => 'completed',
                'completed_at' => now(),
                'answered_count' => $valid->count(),
                'correct_count' => $correct,
                'score_pct' => round($correct / $valid->count() * 100, 2),
                'time_spent_seconds' => intdiv($time, 1000),
            ])->save();

            return $session;
        });
    }

    public function secondsPerQuestion(): float
    {
        return (float) Setting::get('seconds_per_question', 72);
    }

    public function passMark(): int
    {
        return (int) Setting::get('pass_mark', 50);
    }

    /**
     * Result summary for the mock-result screen.
     */
    public function result(QuizSession $session): array
    {
        $answers = $session->answers()->with(['question.department', 'question.subcategory'])->get();

        $bySystem = $answers->groupBy(fn ($a) => $a->question?->subcategory_id
            ? 's'.$a->question->subcategory_id
            : 'd'.$a->question?->department_id)
            ->map(function (Collection $group) {
                $q = $group->first()->question;
                $total = $group->count();
                $right = $group->where('is_correct', true)->count();

                return [
                    'name' => $q?->subcategory?->name ?? $q?->department?->name ?? 'Other',
                    'department_id' => $q?->department_id,
                    'subcategory_id' => $q?->subcategory_id,
                    'correct' => $right,
                    'total' => $total,
                    'pct' => $total ? (int) round($right / $total * 100) : 0,
                ];
            })->sortBy('pct')->values();

        $previous = QuizSession::query()
            ->where('user_id', $session->user_id)->where('status', 'completed')
            ->where('id', '<', $session->id)->where('type', $session->type)
            ->latest('completed_at')->value('score_pct');

        [$rank, $takers, $topPct] = $this->rankFor($session);

        return [
            'score_pct' => (int) round((float) $session->score_pct),
            'correct' => $session->correct_count,
            'total' => $session->question_count,
            'answered' => $session->answered_count,
            'wrong' => $session->question_count - $session->correct_count,
            'passed' => (float) $session->score_pct >= $this->passMark(),
            'pass_mark' => $this->passMark(),
            'delta' => $previous !== null ? (int) round((float) $session->score_pct - (float) $previous) : null,
            'rank' => $rank,
            'takers' => $takers,
            'top_pct' => $topPct,
            'time_spent_seconds' => $session->time_spent_seconds,
            'time_limit_seconds' => $session->time_limit_seconds,
            'seconds_per_question' => $session->answered_count ? (int) round($answers->sum('time_spent_ms') / 1000 / max(1, $session->answered_count)) : 0,
            'by_system' => $bySystem,
        ];
    }

    /**
     * Rank among everyone who took the same exam or paper. Returns [rank, takers, top %].
     *
     * @return array{0:?int, 1:?int, 2:?int}
     */
    public function rankFor(QuizSession $session): array
    {
        $base = QuizSession::query()->where('status', 'completed');
        if ($session->exam_id) {
            $base->where('exam_id', $session->exam_id);
        } elseif ($session->paper_id) {
            $base->where('paper_id', $session->paper_id)->where('mode', $session->mode);
        } else {
            return [null, null, null];
        }

        $takers = (clone $base)->distinct()->count('user_id');
        if ($takers < 2) {
            return [1, $takers, null];
        }
        $better = (clone $base)->where('score_pct', '>', $session->score_pct)->distinct()->count('user_id');
        $rank = $better + 1;

        return [$rank, $takers, max(1, (int) ceil($rank / $takers * 100))];
    }
}
