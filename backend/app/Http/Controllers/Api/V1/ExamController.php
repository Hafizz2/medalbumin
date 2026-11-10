<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\QuizSession;
use App\Models\Referral;
use App\Services\QuizService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExamController extends Controller
{
    public function __construct(private readonly QuizService $quiz) {}

    /** Grand mock screen: upcoming / live national mocks and other open exams. */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $exams = Exam::query()->visible()
            ->where(fn ($q) => $q->whereIn('status', ['published', 'automatic'])->orWhere('starts_at', '>=', now()->subDay()))
            ->orderByRaw("CASE WHEN status IN ('scheduled','live') THEN 0 ELSE 1 END")->orderBy('starts_at')
            ->limit(30)->get();

        $registered = DB::table('exam_registrations')->where('user_id', $user->id)->whereIn('exam_id', $exams->pluck('id'))->pluck('exam_id')->flip();
        $taken = QuizSession::query()->where('user_id', $user->id)->whereIn('exam_id', $exams->pluck('id'))->where('status', 'completed')->pluck('id', 'exam_id');

        return response()->json([
            'data' => $exams->map(fn (Exam $e) => $this->present($e, $user, isset($registered[$e->id]), $taken[$e->id] ?? null))->values(),
        ]);
    }

    private function present(Exam $e, $user, bool $registered, ?int $sessionId): array
    {
        return [
            'id' => $e->id,
            'title' => $e->title,
            'description' => $e->description,
            'type' => $e->type,
            'status' => $e->status,
            'starts_at' => $e->starts_at?->toIso8601String(),
            'duration_minutes' => $e->duration_minutes,
            'question_count' => $e->question_count,
            'registrations' => $e->registrations_count,
            'registered' => $registered,
            'is_open' => $e->isOpen(),
            'can_take' => $e->canBeTakenBy($user),
            'access' => $e->access,
            'leaderboard' => $e->rule('leaderboard'),
            'my_session_id' => $sessionId,
        ];
    }

    public function show(Request $request, Exam $exam): JsonResponse
    {
        abort_unless(in_array($exam->status, ['scheduled', 'live', 'results_out', 'published', 'automatic'], true), 404);
        $user = $request->user();
        $registered = $exam->registrations()->whereKey($user->id)->exists();
        $session = QuizSession::query()->where('user_id', $user->id)->where('exam_id', $exam->id)->latest()->value('id');

        return response()->json($this->present($exam, $user, $registered, $session));
    }

    public function register(Request $request, Exam $exam): JsonResponse
    {
        abort_unless($exam->type === 'grand' && in_array($exam->status, ['scheduled', 'live'], true), 422, 'Registration is closed.');
        abort_unless($exam->canBeTakenBy($request->user()), 402, 'This exam is for premium students.');

        $user = $request->user();
        if ($exam->registrations()->whereKey($user->id)->exists()) {
            $exam->registrations()->detach($user->id);
            $registered = false;
        } else {
            $exam->registrations()->attach($user->id, ['created_at' => now()]);
            $registered = true;
        }
        $exam->forceFill(['registrations_count' => $exam->registrations()->count()])->saveQuietly();

        return response()->json(['registered' => $registered, 'registrations' => $exam->registrations_count]);
    }

    /**
     * Leaderboard. scope = national | university | friends (people you invited or who invited you).
     */
    public function leaderboard(Request $request, Exam $exam): JsonResponse
    {
        $data = $request->validate(['scope' => ['nullable', 'in:national,university,friends']]);
        abort_unless($exam->rule('leaderboard'), 404);
        $user = $request->user();
        $scope = $data['scope'] ?? 'national';

        $base = QuizSession::query()->where('exam_id', $exam->id)->where('status', 'completed')
            ->join('users', 'users.id', '=', 'quiz_sessions.user_id')->whereNull('users.deleted_at');

        if ($scope === 'university') {
            $base->where('users.university_id', $user->university_id ?: 0);
        } elseif ($scope === 'friends') {
            $friends = Referral::query()->where('referrer_id', $user->id)->pluck('referred_id')
                ->merge(Referral::query()->where('referred_id', $user->id)->pluck('referrer_id'))
                ->push($user->id);
            $base->whereIn('users.id', $friends);
        }

        $rows = (clone $base)->with('user.university')
            ->orderByDesc('score_pct')->orderBy('time_spent_seconds')
            ->limit(50)->get(['quiz_sessions.*']);

        $mine = QuizSession::query()->where('exam_id', $exam->id)->where('user_id', $user->id)->where('status', 'completed')->first();
        $me = null;
        if ($mine) {
            [$rank, $takers, $top] = $this->quiz->rankFor($mine);
            $previous = QuizSession::query()->where('user_id', $user->id)->where('status', 'completed')->whereNotNull('exam_id')
                ->where('id', '<', $mine->id)->whereHas('exam', fn ($q) => $q->where('type', 'grand'))->latest('id')->first();
            $prevRank = $previous ? $this->quiz->rankFor($previous)[0] : null;
            $me = [
                'rank' => $rank,
                'takers' => $takers,
                'top_pct' => $top,
                'score_pct' => (int) round($mine->score_pct),
                'places_moved' => $prevRank !== null ? $prevRank - $rank : null,
                'session_id' => $mine->id,
            ];
        }

        $rank = 0;
        $lastScore = null;

        return response()->json([
            'exam' => ['id' => $exam->id, 'title' => $exam->title],
            'me' => $me,
            'data' => $rows->map(function ($s, $i) use (&$rank, &$lastScore, $user) {
                if ($s->score_pct !== $lastScore) {
                    $rank = $i + 1;
                    $lastScore = $s->score_pct;
                }

                return [
                    'rank' => $rank,
                    'name' => $s->user->id === $user->id ? $s->user->shortName().' (you)' : $s->user->shortName(),
                    'initials' => $s->user->initials(),
                    'university' => $s->user->university?->name,
                    'score_pct' => (int) round($s->score_pct),
                    'is_me' => $s->user_id === $user->id,
                ];
            })->values(),
        ]);
    }
}
