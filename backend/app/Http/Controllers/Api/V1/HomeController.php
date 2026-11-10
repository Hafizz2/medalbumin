<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Services\ProgressService;
use App\Services\StudyPlanGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    public function __invoke(Request $request, ProgressService $progress, StudyPlanGenerator $plans): JsonResponse
    {
        $user = $request->user();
        $totals = $progress->totals($user);

        $plan = $plans->activePlan($user);
        $today = $plan ? $plans->tasksFor($plan, today()) : collect();

        $nextExam = Exam::query()->where('type', 'grand')->whereIn('status', ['scheduled', 'live'])
            ->where(fn ($q) => $q->where('starts_at', '>=', now()->subHours(4)))
            ->orderBy('starts_at')->first();

        $dueCards = DB::table('flashcard_reviews')->where('user_id', $user->id)->where('due_at', '<=', now())->count();
        $mistakes = DB::table('user_question_stats')->where('user_id', $user->id)->where('last_correct', false)->count();

        $hour = (int) now()->format('G');

        // Last 7 days of activity (oldest first) for the streak strip and the home-screen widget.
        $activity = DB::table('daily_activities')->where('user_id', $user->id)->where('date', '>=', today()->subDays(6)->toDateString())
            ->get(['date', 'questions_answered', 'cards_reviewed', 'video_seconds'])->keyBy(fn ($r) => substr((string) $r->date, 0, 10));
        $week = collect(range(6, 0))->map(function ($ago) use ($activity) {
            $d = today()->subDays($ago);
            $row = $activity[$d->toDateString()] ?? null;

            return ['date' => $d->toDateString(), 'studied' => (bool) $row && ($row->questions_answered > 0 || $row->cards_reviewed > 0 || $row->video_seconds > 0)];
        })->values();
        $todayRow = $activity[today()->toDateString()] ?? null;

        return response()->json([
            'greeting' => $hour < 12 ? 'morning' : ($hour < 17 ? 'afternoon' : 'evening'),
            'first_name' => $user->name ? explode(' ', trim($user->name))[0] : null,
            'initials' => $user->initials(),
            'avatar_url' => $user->avatarUrl(),
            'readiness' => $progress->readiness($user),
            'stats' => [
                'answered' => $totals['answered'],
                'accuracy' => $totals['accuracy'],
                'streak' => $totals['streak'],
            ],
            'today' => [
                'answered' => (int) ($todayRow->questions_answered ?? 0),
                'goal' => (int) $user->daily_goal,
                'studied' => (bool) $week->last()['studied'],
            ],
            'week' => $week,
            'study_plan' => $plan ? [
                'sessions' => $today->count(),
                'done' => $today->whereNotNull('completed_at')->count(),
                'minutes' => (int) $today->sum('minutes'),
            ] : null,
            'counts' => [
                'cards_due' => $dueCards,
                'mistakes' => $mistakes,
            ],
            'next_exam' => $nextExam ? [
                'id' => $nextExam->id,
                'title' => $nextExam->title,
                'starts_at' => $nextExam->starts_at?->toIso8601String(),
                'registered' => $nextExam->registrations()->whereKey($user->id)->exists(),
            ] : null,
            'unread_notifications' => $user->unreadNotifications()->count(),
        ]);
    }
}
