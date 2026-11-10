<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\StudyPlanTask;
use App\Services\StudyPlanGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class StudyPlanController extends Controller
{
    public function __construct(private readonly StudyPlanGenerator $plans) {}

    /** The plan for one day plus the week strip. Defaults to today. */
    public function show(Request $request): JsonResponse
    {
        $data = $request->validate(['date' => ['nullable', 'date']]);
        $plan = $this->plans->activePlan($request->user());
        if (! $plan) {
            return response()->json(['plan' => null]);
        }

        $date = isset($data['date']) ? Carbon::parse($data['date'])->startOfDay() : today();
        $tasks = $this->plans->tasksFor($plan, $date);
        $weekStart = $date->copy()->startOfWeek();

        return response()->json([
            'plan' => [
                'id' => $plan->id,
                'exam_label' => $plan->exam_label,
                'exam_date' => $plan->exam_date->toDateString(),
                'day' => $plan->dayNumber(),
                'total_days' => $plan->totalDays(),
                'phase' => $plan->phase(),
                'daily_minutes' => $plan->daily_minutes,
            ],
            'date' => $date->toDateString(),
            'week' => $this->plans->weekStatus($plan, $weekStart),
            'missed_days' => $this->plans->missedDays($plan),
            'tasks' => $tasks->map(fn (StudyPlanTask $t) => [
                'id' => $t->id,
                'kind' => $t->kind,
                'title' => $t->title,
                'meta' => $t->meta,
                'minutes' => $t->minutes,
                'target' => $t->target,
                'done' => (bool) $t->completed_at,
            ])->values(),
            'minutes' => (int) $tasks->sum('minutes'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'exam_date' => ['required', 'date', 'after:today'],
            'daily_minutes' => ['nullable', 'integer', 'between:30,600'],
            'exam_label' => ['nullable', 'string', 'max:80'],
        ]);
        $plan = $this->plans->create($request->user(), Carbon::parse($data['exam_date']), $data['daily_minutes'] ?? 180, $data['exam_label'] ?? null);

        return response()->json(['id' => $plan->id], 201);
    }

    public function toggle(Request $request, StudyPlanTask $task): JsonResponse
    {
        abort_unless($task->plan->user_id === $request->user()->id, 404);
        $task->update(['completed_at' => $task->completed_at ? null : now()]);

        return response()->json(['done' => (bool) $task->completed_at]);
    }

    public function rebalance(Request $request): JsonResponse
    {
        $plan = $this->plans->activePlan($request->user());
        abort_unless($plan, 404);
        $moved = $this->plans->rebalance($plan);

        return response()->json(['moved' => $moved, 'message' => $moved ? "{$moved} tasks spread over the next days." : 'You are on schedule.']);
    }
}
