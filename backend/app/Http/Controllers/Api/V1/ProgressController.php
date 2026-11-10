<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\QuizSession;
use App\Services\ProgressService;
use App\Services\QuizService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProgressController extends Controller
{
    public function __construct(private readonly ProgressService $progress) {}

    public function overview(Request $request): JsonResponse
    {
        $user = $request->user();
        $subjects = $this->subjectRows($user);

        return response()->json($this->progress->overview($user) + [
            'top_subjects' => $subjects->take(3)->values(),
            'last_seven_days' => $this->progress->lastSevenDays($user),
        ]);
    }

    public function subjects(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->subjectRows($request->user())->values()]);
    }

    private function subjectRows($user)
    {
        return $this->progress->departments($user)
            ->filter(fn ($d) => $d['accuracy'] !== null)
            ->sortByDesc('accuracy')
            ->map(fn ($d) => [
                'id' => $d['id'],
                'name' => $d['name'],
                'icon' => $d['icon'],
                'color_bg' => $d['color_bg'],
                'color_ink' => $d['color_ink'],
                'accuracy' => $d['accuracy'],
                'answered' => $d['answered'],
            ]);
    }

    public function mocks(Request $request, QuizService $quiz): JsonResponse
    {
        return response()->json([
            'data' => $this->progress->mocks($request->user())->map(function (QuizSession $s) use ($quiz) {
                [, , $top] = $quiz->rankFor($s);
                $avgSec = $s->question_count ? $s->time_spent_seconds / $s->question_count : 0;

                return [
                    'id' => $s->id,
                    'title' => $s->title,
                    'type' => $s->type,
                    'completed_at' => $s->completed_at?->toIso8601String(),
                    'score_pct' => (int) round((float) $s->score_pct),
                    'correct' => $s->correct_count,
                    'total' => $s->question_count,
                    'time_spent_seconds' => $s->time_spent_seconds,
                    'top_pct' => $top,
                    // Rough difficulty from how hard the mock was for everyone who took it.
                    'level' => $avgSec > 80 ? 'Hard' : ($avgSec > 50 ? 'Medium' : 'Easy'),
                ];
            })->values(),
        ]);
    }
}
