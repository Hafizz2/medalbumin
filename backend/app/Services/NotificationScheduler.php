<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\Paper;
use App\Models\QuizSession;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\AppNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Automatic notifications, run from the scheduler (routes/console.php).
 * Each one marks what it sent so nothing is announced twice.
 */
class NotificationScheduler
{
    public function __construct(private readonly ProgressService $progress) {}

    /** Active, signed-up students. */
    private function students(): Builder
    {
        return User::query()->students()->where('is_guest', false)->where('status', 'active');
    }

    private function toEveryone(AppNotification $n, ?callable $filter = null): int
    {
        $count = 0;
        $this->students()->when($filter, $filter)->chunkById(500, function ($users) use ($n, &$count) {
            Notification::send($users, $n);
            $count += $users->count();
        });

        return $count;
    }

    /** A new grand mock was scheduled: invite everyone to register. */
    public function announceExams(): int
    {
        $sent = 0;
        Exam::query()->where('type', 'grand')->whereIn('status', ['scheduled', 'live'])->whereNull('announced_at')
            ->get()->each(function (Exam $e) use (&$sent) {
                $e->forceFill(['announced_at' => now()])->save();
                if (! $e->starts_at || $e->starts_at->isPast()) {
                    return;
                }
                $when = $e->starts_at->format('D j M, g:i A');
                $sent += $this->toEveryone(AppNotification::newExam($e->id, $e->title, $when));
            });

        return $sent;
    }

    /** Grand mock results are out: each taker gets their score and rank. */
    public function examResults(): int
    {
        $sent = 0;
        Exam::query()->where('status', 'results_out')->whereNull('results_notified_at')->get()->each(function (Exam $e) use (&$sent) {
            $e->forceFill(['results_notified_at' => now()])->save();
            $sessions = QuizSession::query()->where('exam_id', $e->id)->where('status', 'completed')
                ->orderByDesc('score_pct')->get(['id', 'user_id', 'score_pct'])->unique('user_id')->values();
            $users = User::query()->whereIn('id', $sessions->pluck('user_id'))->get()->keyBy('id');
            foreach ($sessions as $i => $s) {
                $better = $sessions->where('score_pct', '>', $s->score_pct)->count();
                $users[$s->user_id]?->notify(AppNotification::examResults($e->id, $e->title, $s->id, $e->rule('leaderboard') ? $better + 1 : null, (int) round($s->score_pct)));
                $sent++;
            }
        });

        return $sent;
    }

    /** New past papers. Papers published more than 2 days ago are only marked, not announced. */
    public function announcePapers(): int
    {
        $sent = 0;
        Paper::query()->where('status', 'published')->whereNull('announced_at')->get()->each(function (Paper $p) use (&$sent) {
            $p->forceFill(['announced_at' => now()])->save();
            if ($p->updated_at->lt(now()->subDays(2))) {
                return;
            }
            $sent += $this->toEveryone(AppNotification::newPaper($p->id, trim($p->name.($p->year ? " {$p->year}" : ""))));
        });

        return $sent;
    }

    /** Premium ends in 3 days and no later plan is lined up. */
    public function planExpiring(): int
    {
        $sent = 0;
        Subscription::query()->with('user')->where('status', 'active')->whereNull('expiry_reminded_at')
            ->whereBetween('ends_at', [now(), now()->addDays(3)])->get()
            ->each(function (Subscription $s) use (&$sent) {
                $s->forceFill(['expiry_reminded_at' => now()])->save();
                $later = Subscription::query()->where('user_id', $s->user_id)->where('status', 'active')->where('ends_at', '>', $s->ends_at)->exists();
                if ($later || ! $s->user) {
                    return;
                }
                $s->user->notify(AppNotification::planExpiring($s->ends_at->format('j M Y'), max(1, (int) ceil(now()->diffInDays($s->ends_at)))));
                $sent++;
            });

        return $sent;
    }

    /** Milestones before the student's own exam date. */
    public function examCountdown(): int
    {
        $sent = 0;
        $labels = ['exit' => 'exit exam', 'qualification' => 'qualification exam', 'licensing' => 'licensing exam', 'residency' => 'residency exam'];
        foreach ([60, 30, 14, 7, 3, 1] as $days) {
            $this->students()->whereDate('exam_date', today()->addDays($days))->chunkById(500, function ($users) use ($days, $labels, &$sent) {
                foreach ($users as $u) {
                    $u->notify(AppNotification::examCountdown($labels[$u->target_exam] ?? 'exam', $days));
                    $sent++;
                }
            });
        }

        return $sent;
    }

    /** Sunday evening: the week's numbers, for students who studied this week. */
    public function weeklySummary(): int
    {
        $sent = 0;
        $from = today()->subDays(6)->toDateString();
        $rows = DB::table('daily_activities')->where('date', '>=', $from)->groupBy('user_id')
            ->selectRaw('user_id, SUM(questions_answered) answered, SUM(correct_answers) correct')->having('answered', '>', 0)->get()->keyBy('user_id');

        $this->students()->whereIn('id', $rows->keys())->chunkById(500, function ($users) use ($rows, &$sent) {
            foreach ($users as $u) {
                $r = $rows[$u->id];
                $u->notify(AppNotification::weeklySummary((int) $r->answered, (int) round($r->correct / max(1, $r->answered) * 100), $this->progress->streak($u)));
                $sent++;
            }
        });

        return $sent;
    }

    /** Many flashcards waiting and none reviewed today. */
    public function cardsDue(int $min = 20): int
    {
        $sent = 0;
        $due = DB::table('flashcard_reviews')->where('due_at', '<=', now())->groupBy('user_id')
            ->selectRaw('user_id, COUNT(*) n')->having('n', '>=', $min)->pluck('n', 'user_id');
        $reviewedToday = DB::table('daily_activities')->where('date', today()->toDateString())->where('cards_reviewed', '>', 0)->pluck('user_id')->flip();

        $this->students()->whereIn('id', $due->keys())->chunkById(500, function ($users) use ($due, $reviewedToday, &$sent) {
            foreach ($users as $u) {
                if (! isset($reviewedToday[$u->id])) {
                    $u->notify(AppNotification::cardsDue((int) $due[$u->id]));
                    $sent++;
                }
            }
        });

        return $sent;
    }
}
