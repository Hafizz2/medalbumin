<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Note;
use App\Models\Paper;
use App\Models\Question;
use App\Models\QuizSession;
use App\Models\Video;
use App\Services\ContentAccess;
use App\Services\ProgressService;
use App\Services\QuizService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudyController extends Controller
{
    public function __construct(private readonly ProgressService $progress, private readonly ContentAccess $access) {}

    /** Study tab: all departments with the student's progress. */
    public function departments(Request $request): JsonResponse
    {
        $rows = $this->progress->departments($request->user());

        return response()->json([
            'total_questions' => $rows->sum('questions'),
            'data' => $rows->values(),
        ]);
    }

    /** Department → subcategories with questions, accuracy, topics, videos and notes. */
    public function department(Request $request, Department $department): JsonResponse
    {
        abort_unless($department->is_active, 404);
        $user = $request->user();
        $department->load(['subcategories' => fn ($q) => $q->where('is_active', true), 'subcategories.topics']);

        $qCounts = $this->progress->scopedQuestionCounts($user, 'subcategory_id');
        $perf = $this->progress->subjectPerformance($user, $department->id);
        $videoCounts = Video::query()->published()->where('department_id', $department->id)->groupBy('subcategory_id')->selectRaw('subcategory_id, COUNT(*) n')->pluck('n', 'subcategory_id');
        $noteCounts = Note::query()->published()->where('department_id', $department->id)->groupBy('subcategory_id')->selectRaw('subcategory_id, COUNT(*) n')->pluck('n', 'subcategory_id');

        $deptRow = $this->progress->departments($user)->firstWhere('id', $department->id);
        $deptPerf = $this->progress->subjectPerformance($user)[$department->id] ?? null;

        return response()->json([
            'id' => $department->id,
            'name' => $department->name,
            'icon' => $department->icon,
            'color_bg' => $department->color_bg,
            'color_ink' => $department->color_ink,
            'questions' => $deptRow['questions'] ?? 0,
            'done_pct' => $deptRow['done_pct'] ?? 0,
            'accuracy' => $deptPerf['accuracy'] ?? null,
            'subcategories' => $department->subcategories->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'questions' => (int) ($qCounts[$s->id] ?? 0),
                'accuracy' => $perf[$s->id]['accuracy'] ?? null,
                'answered' => $perf[$s->id]['answered'] ?? 0,
                'videos' => (int) ($videoCounts[$s->id] ?? 0),
                'notes' => (int) ($noteCounts[$s->id] ?? 0),
                'topics' => $s->topics->map(fn ($t) => ['id' => $t->id, 'name' => $t->name])->values(),
            ])->values(),
        ]);
    }

    /** Past exam papers by tab (exit / qualification / block), with the student's progress. */
    public function papers(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category' => ['nullable', 'in:exit,qualification,block'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $user = $request->user();
        $category = $data['category'] ?? 'exit';

        $papers = Paper::query()->published()->where('category', $category)
            ->when($data['q'] ?? null, fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('year', $s)->orWhere('code', 'like', "%{$s}%")->orWhere('description', 'like', "%{$s}%")))
            ->withCount(['questions' => fn ($q) => $q->published()])
            ->orderBy('sort')->orderByDesc('year')->get();

        $ids = $papers->pluck('id');
        $best = QuizSession::query()->where('user_id', $user->id)->whereIn('paper_id', $ids)->where('status', 'completed')
            ->groupBy('paper_id')->selectRaw('paper_id, MAX(score_pct) best')->pluck('best', 'paper_id');
        $answered = DB::table('user_question_stats as s')->join('questions as q', 'q.id', '=', 's.question_id')
            ->where('s.user_id', $user->id)->whereIn('q.paper_id', $ids)
            ->groupBy('q.paper_id')->selectRaw('q.paper_id, COUNT(*) n')->pluck('n', 'paper_id');
        $inProgress = QuizSession::query()->where('user_id', $user->id)->whereIn('paper_id', $ids)->where('status', 'in_progress')->pluck('paper_id')->flip();

        $row = fn (Paper $p) => [
            'id' => $p->id,
            'name' => $p->name,
            'year' => $p->year,
            'ec_label' => $p->ec_label,
            'code' => $p->code,
            'description' => $p->description,
            'questions' => $p->questions_count,
            'time_limit_minutes' => $p->time_limit_minutes,
            'locked' => ! $this->access->canOpenPaper($user, $p),
            'best_pct' => isset($best[$p->id]) ? (int) round($best[$p->id]) : null,
            'done_pct' => $p->questions_count ? (int) min(100, round(($answered[$p->id] ?? 0) / $p->questions_count * 100)) : 0,
            'in_progress' => isset($inProgress[$p->id]),
        ];

        if ($category !== 'block') {
            return response()->json(['category' => $category, 'data' => $papers->map($row)->values()]);
        }

        // Block exams are grouped by system code: "Cardiovascular system · 6 papers · 240 questions".
        $groups = $papers->groupBy(fn ($p) => $p->code ?: 'OTHER')->map(function ($group, $code) use ($row) {
            $items = $group->map($row);
            $total = $items->sum('questions');
            $bests = $items->pluck('best_pct')->filter(fn ($v) => $v !== null);

            return [
                'code' => $code,
                'name' => $group->first()->description ?: $group->first()->name,
                'papers_count' => $group->count(),
                'questions' => $total,
                'best_pct' => $bests->isNotEmpty() ? (int) round($bests->avg()) : null,
                'done_pct' => $total ? (int) round($items->sum(fn ($i) => $i['done_pct'] * $i['questions']) / $total) : 0,
                'papers' => $items->values(),
            ];
        })->values();

        return response()->json(['category' => $category, 'data' => $groups]);
    }

    /** Everything the custom quiz builder needs: subjects with counts and pool sizes. */
    public function builderOptions(Request $request, QuizService $quiz): JsonResponse
    {
        $user = $request->user();
        $base = fn () => $this->access->scopeQuestions(Question::query()->published(), $user);

        $deptCounts = $this->progress->scopedQuestionCounts($user, 'department_id');
        $subCounts = $this->progress->scopedQuestionCounts($user, 'subcategory_id');

        $departments = Department::query()->active()->with(['subcategories' => fn ($q) => $q->where('is_active', true)])->get()
            ->map(fn ($d) => [
                'id' => $d->id,
                'name' => $d->name,
                'short_name' => $d->short_name,
                'questions' => (int) ($deptCounts[$d->id] ?? 0),
                'subcategories' => $d->subcategories->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'questions' => (int) ($subCounts[$s->id] ?? 0)])
                    ->filter(fn ($s) => $s['questions'] > 0)->values(),
            ])->filter(fn ($d) => $d['questions'] > 0)->values();

        $all = $base()->count();
        $answered = DB::table('user_question_stats')->where('user_id', $user->id);

        return response()->json([
            'departments' => $departments,
            'sizes' => QuizService::SIZES,
            'seconds_per_question' => $quiz->secondsPerQuestion(),
            'pools' => [
                ['id' => 'unused', 'count' => max(0, $all - (clone $answered)->whereIn('question_id', $base()->select('id'))->count())],
                ['id' => 'wrong', 'count' => (clone $answered)->where('last_correct', false)->count()],
                ['id' => 'bookmarked', 'count' => DB::table('bookmarks')->where('user_id', $user->id)->count()],
                ['id' => 'all', 'count' => $all],
            ],
            'locked_questions' => $this->access->isPremium($user) ? 0 : max(0, Question::query()->published()->count() - $all),
        ]);
    }
}
