<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\QuestionResource;
use App\Http\Resources\QuizSessionResource;
use App\Models\Question;
use App\Models\QuizSession;
use App\Services\QuizService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class QuizController extends Controller
{
    public function __construct(private readonly QuizService $quiz) {}

    private function owned(Request $request, int $id): QuizSession
    {
        return QuizSession::query()->where('user_id', $request->user()->id)->findOrFail($id);
    }

    /**
     * Start a session.
     *
     * source = builder | subcategory | mistakes | bookmarks | paper | exam | retry | questions
     */
    public function start(Request $request): JsonResponse
    {
        $data = $request->validate([
            'source' => ['required', Rule::in(['builder', 'subcategory', 'mistakes', 'bookmarks', 'paper', 'exam', 'retry', 'questions'])],
            'mode' => ['nullable', 'in:practice,timed'],
            'size' => ['nullable', 'integer', 'min:5', 'max:200'],
            'department_ids' => ['nullable', 'array', 'max:30'],
            'department_ids.*' => ['integer'],
            'subcategory_ids' => ['nullable', 'array', 'max:60'],
            'subcategory_ids.*' => ['integer'],
            'topic_id' => ['nullable', 'integer'],
            'difficulty' => ['nullable', 'in:easy,moderate,hard'],
            'pools' => ['nullable', 'array'],
            'pools.*' => [Rule::in(QuizService::POOLS)],
            'paper_id' => ['required_if:source,paper', 'integer'],
            'exam_id' => ['required_if:source,exam', 'integer'],
            'session_id' => ['required_if:source,retry', 'integer'],
            'question_ids' => ['required_if:source,questions', 'array', 'max:200'],
            'question_ids.*' => ['integer'],
            'title' => ['nullable', 'string', 'max:120'],
        ]);

        $session = $this->quiz->start($request->user(), $data);

        return $this->payload($request, $session, $session->wasRecentlyCreated ? 201 : 200);
    }

    /** The session with all its questions (answers only revealed where allowed). */
    public function show(Request $request, int $session): JsonResponse
    {
        $model = $this->owned($request, $session);
        if ($model->status === 'in_progress' && $model->isTimed() && $model->isExpired()) {
            $model = $this->quiz->finish($model);
        }

        return $this->payload($request, $model);
    }

    private function payload(Request $request, QuizSession $session, int $status = 200): JsonResponse
    {
        $session->loadMissing('exam');
        $answers = $session->answers()->with(['question' => fn ($q) => $q->withTrashed()->with(['options', 'department', 'subcategory', 'paper'])])->get();
        $bookmarked = DB::table('bookmarks')->where('user_id', $request->user()->id)->whereIn('question_id', $answers->pluck('question_id'))->pluck('question_id')->flip();

        $finished = $session->status === 'completed';
        $showAnswers = ! $session->exam || $session->exam->rule('show_answers');

        return response()->json([
            'session' => new QuizSessionResource($session),
            'questions' => $answers->filter(fn ($a) => $a->question)->map(function ($a) use ($session, $bookmarked, $finished, $showAnswers, $request) {
                $reveal = ($session->mode === 'practice' && $a->answered_at) || ($finished && $showAnswers);

                return [
                    'position' => $a->position,
                    'answered' => $a->selected_option !== null,
                    'is_correct' => $reveal ? $a->is_correct : null,
                    'is_flagged' => $a->is_flagged,
                    'question' => (new QuestionResource($a->question))
                        ->reveal($reveal)
                        ->withOptionOrder($a->option_order)
                        ->withUserState(['bookmarked' => isset($bookmarked[$a->question_id]), 'selected' => $a->selected_option])
                        ->resolve($request),
                ];
            })->values(),
        ], $status);
    }

    /**
     * Answer one question. Practice mode returns the verdict, explanation and how others answered.
     */
    public function answer(Request $request, int $session): JsonResponse
    {
        $data = $request->validate([
            'question_id' => ['required', 'integer'],
            'option' => ['nullable', 'string', 'size:1'],
            'time_ms' => ['nullable', 'integer', 'min:0'],
            'flagged' => ['nullable', 'boolean'],
        ]);
        $model = $this->owned($request, $session);
        $answer = $this->quiz->answer($model, $data['question_id'], $data['option'] ?? null, (int) ($data['time_ms'] ?? 0), (bool) ($data['flagged'] ?? false));
        $model->refresh();

        $out = [
            'answered_count' => $model->answered_count,
            'selected' => $answer->selected_option,
        ];

        if ($model->mode === 'practice' && $answer->answered_at) {
            $question = Question::withTrashed()->with(['options', 'department', 'subcategory', 'paper'])->find($answer->question_id);
            $out += [
                'is_correct' => $answer->is_correct,
                'correct_count' => $model->correct_count,
                'question' => (new QuestionResource($question))->reveal()->withOptionOrder($answer->option_order)->resolve($request),
            ];
        }

        return response()->json($out);
    }

    public function finish(Request $request, int $session): JsonResponse
    {
        $model = $this->quiz->finish($this->owned($request, $session));

        return response()->json([
            'session' => new QuizSessionResource($model),
            'result' => $this->quiz->result($model),
        ]);
    }

    public function result(Request $request, int $session): JsonResponse
    {
        $model = $this->owned($request, $session);
        abort_unless($model->status === 'completed', 422, 'Finish the session first.');

        return response()->json([
            'session' => new QuizSessionResource($model),
            'result' => $this->quiz->result($model),
        ]);
    }

    /** Recent and unfinished sessions. */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['status' => ['nullable', 'in:in_progress,completed']]);

        return response()->json([
            'data' => QuizSessionResource::collection(
                QuizSession::query()->where('user_id', $request->user()->id)
                    ->when($data['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
                    ->latest()->limit(30)->get()
            ),
        ]);
    }

    public function abandon(Request $request, int $session): JsonResponse
    {
        $model = $this->owned($request, $session);
        if ($model->status === 'in_progress') {
            $model->answered_count > 0 ? $this->quiz->finish($model) : $model->update(['status' => 'abandoned']);
        }

        return response()->json(['message' => 'Session closed.']);
    }
}
