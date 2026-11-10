<?php

namespace App\Services;

use App\Models\Device;
use App\Models\Payment;
use App\Models\Question;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    public function kpis(): array
    {
        $dauToday = DB::table('daily_activities')->where('date', today()->toDateString())->distinct()->count('user_id');
        $dauPrevMonth = DB::table('daily_activities')->whereBetween('date', [today()->subDays(59), today()->subDays(30)])
            ->selectRaw('COUNT(DISTINCT user_id) / 30.0 as avg')->value('avg');
        $dauMonth = DB::table('daily_activities')->whereBetween('date', [today()->subDays(29), today()])
            ->selectRaw('COUNT(DISTINCT user_id) / 30.0 as avg')->value('avg');

        // Of students who joined 8–14 days ago, how many were active in the last 7 days.
        $cohort = User::query()->students()->where('is_guest', false)->whereBetween('created_at', [now()->subDays(14), now()->subDays(8)])->pluck('id');
        $retained = $cohort->isEmpty() ? 0 : DB::table('daily_activities')->whereIn('user_id', $cohort)->where('date', '>=', today()->subDays(6))->distinct()->count('user_id');

        $minutes = DB::table('daily_activities')->where('date', '>=', today()->subDays(6))->where('study_seconds', '>', 0)
            ->orderBy('study_seconds')->pluck('study_seconds');
        $median = $minutes->isEmpty() ? 0 : (int) round($minutes[intdiv($minutes->count(), 2)] / 60);

        $students = User::query()->students()->where('is_guest', false)->count();
        $paid = Payment::query()->where('status', 'approved')->where('amount', '>', 0)->distinct()->count('user_id');

        return [
            ['Daily active students', number_format($dauToday), $dauPrevMonth > 0 ? (($dauMonth >= $dauPrevMonth ? '+' : '').round(($dauMonth - $dauPrevMonth) / $dauPrevMonth * 100).'% vs last month') : '30-day avg '.number_format((float) $dauMonth, 0)],
            ['Still active after 7 days', $cohort->isEmpty() ? '—' : round($retained / $cohort->count() * 100).'%', 'Students who joined last week'],
            ['Study time per day', $median.' min', 'Median, active students'],
            ['Free → paid', $students ? round($paid / $students * 100, 1).'%' : '—', number_format($paid).' paying students'],
        ];
    }

    /** Daily active students for the last 30 days. */
    public function dau(): array
    {
        $rows = DB::table('daily_activities')->where('date', '>=', today()->subDays(29))
            ->groupBy('date')->selectRaw('date, COUNT(DISTINCT user_id) n')->pluck('n', 'date')
            ->mapWithKeys(fn ($n, $d) => [substr((string) $d, 0, 10) => (int) $n]);

        return collect(range(29, 0))->map(function ($ago) use ($rows) {
            $d = today()->subDays($ago);

            return ['date' => $d->toDateString(), 'label' => $d->format('M j'), 'value' => $rows[$d->toDateString()] ?? 0, 'weekend' => $d->isWeekend()];
        })->all();
    }

    public function funnel(): array
    {
        $installs = Device::query()->distinct()->count('device_id');
        $accounts = User::query()->students()->where('is_guest', false)->count();
        $engaged = DB::table('daily_activities')->groupBy('user_id')->havingRaw('SUM(questions_answered) >= 10')->select('user_id')->get()->count();
        $subscribed = Payment::query()->where('status', 'approved')->distinct()->count('user_id');
        $base = max(1, $installs, $accounts);

        return [
            ['Installed the app', $installs, 100],
            ['Created an account', $accounts, round($accounts / $base * 100)],
            ['Answered first 10 questions', $engaged, round($engaged / $base * 100)],
            ['Subscribed', $subscribed, round($subscribed / $base * 100, 1)],
        ];
    }

    /**
     * Computes "top students correct" and a quality flag for every question with enough attempts.
     * Top students = the best 27% by overall accuracy among students with 50+ answers.
     */
    public function refreshQuestionQuality(int $minAttempts = 20): int
    {
        $users = DB::table('user_question_stats')->groupBy('user_id')->havingRaw('SUM(attempts) >= 50')
            ->selectRaw('user_id, SUM(correct_count) / SUM(attempts) acc')->orderByDesc('acc')->get();
        $top = $users->take(max(1, (int) ceil($users->count() * 0.27)))->pluck('user_id');

        $topStats = $top->isEmpty() ? collect() : DB::table('user_question_stats')->whereIn('user_id', $top)
            ->groupBy('question_id')->selectRaw('question_id, SUM(correct_count) c, SUM(attempts) a')->get()->keyBy('question_id');

        $n = 0;
        Question::query()->where('attempts_count', '>=', $minAttempts)->select(['id', 'attempts_count', 'correct_count'])
            ->chunkById(500, function ($qs) use ($topStats, &$n) {
                foreach ($qs as $q) {
                    $pct = $q->correct_count / $q->attempts_count * 100;
                    $t = $topStats[$q->id] ?? null;
                    $topPct = $t && $t->a >= 5 ? $t->c / $t->a * 100 : null;
                    $flag = match (true) {
                        $pct >= 90 => 'too_easy',
                        $topPct !== null && $topPct < 55 => 'ambiguous',
                        $pct < 35 => 'too_hard',
                        default => 'good',
                    };
                    Question::query()->whereKey($q->id)->update(['top_correct_pct' => $topPct !== null ? round($topPct, 2) : null, 'quality_flag' => $flag]);
                    $n++;
                }
            });

        return $n;
    }

    public function qualityRows(string $filter): Collection
    {
        return Question::query()->where('attempts_count', '>=', 20)->whereNotNull('quality_flag')
            ->when($filter === 'flag', fn ($q) => $q->where('quality_flag', '!=', 'good'))
            ->when($filter === 'amb', fn ($q) => $q->where('quality_flag', 'ambiguous'))
            ->orderByRaw("CASE quality_flag WHEN 'ambiguous' THEN 0 WHEN 'too_hard' THEN 1 WHEN 'too_easy' THEN 2 ELSE 3 END")
            ->orderByDesc('attempts_count')->limit(50)->get();
    }
}
