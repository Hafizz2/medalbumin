<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\Video;
use App\Services\ContentAccess;
use App\Services\QuizService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VideoController extends Controller
{
    public function __construct(private readonly ContentAccess $access) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'department_id' => ['nullable', 'integer'],
            'subcategory_id' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $user = $request->user();

        $videos = Video::query()->published()->with(['department', 'subcategory', 'topic'])
            ->when($data['department_id'] ?? null, fn ($q, $id) => $q->where('department_id', $id))
            ->when($data['subcategory_id'] ?? null, fn ($q, $id) => $q->where('subcategory_id', $id))
            ->when($data['q'] ?? null, fn ($q, $s) => $q->where('title', 'like', "%{$s}%"))
            ->latest()->paginate(30);

        $progress = DB::table('video_progress')->where('user_id', $user->id)->whereIn('video_id', collect($videos->items())->pluck('id'))->get()->keyBy('video_id');

        return response()->json([
            'data' => collect($videos->items())->map(fn (Video $v) => $this->card($v, $user, $progress[$v->id] ?? null)),
            'meta' => ['current_page' => $videos->currentPage(), 'last_page' => $videos->lastPage()],
        ]);
    }

    private function card(Video $v, $user, ?object $p): array
    {
        return [
            'id' => $v->id,
            'title' => $v->title,
            'breadcrumb' => $v->breadcrumb(),
            'lecturer' => $v->lecturer,
            'duration_seconds' => $v->duration_seconds,
            'thumbnail_url' => $v->thumbnailUrl(),
            'views' => $v->views_count,
            'access' => $v->access,
            'locked' => ! $this->access->canWatch($user, $v),
            'position_seconds' => (int) ($p->position_seconds ?? 0),
            'completed' => (bool) ($p->completed_at ?? false),
        ];
    }

    public function show(Request $request, Video $video): JsonResponse
    {
        abort_unless($video->status === 'published', 404);
        $user = $request->user();
        $video->load(['department', 'subcategory', 'topic', 'chapters']);
        $p = DB::table('video_progress')->where('user_id', $user->id)->where('video_id', $video->id)->first();
        $locked = ! $this->access->canWatch($user, $video);

        $linked = $this->access->scopeQuestions(
            Question::query()->published()->whereIn('id', $video->questions()->select('questions.id')), $user
        )->pluck('id');

        return response()->json($this->card($video, $user, $p) + [
            'description' => $video->description,
            'streams' => $locked ? [] : $video->streams(),
            'captions' => $locked ? [] : $video->captionUrls(),
            'chapters' => $video->chapters->map(fn ($c) => ['start_seconds' => $c->start_seconds, 'title' => $c->title])->values(),
            'linked_question_ids' => $linked,
            'my_notes' => $video->morphMany(\App\Models\UserNote::class, 'notable')->where('user_id', $user->id)
                ->orderBy('video_seconds')->get(['id', 'body', 'video_seconds']),
        ]);
    }

    /** Saves the playback position; counts a view once and a completion at 90%. */
    public function progress(Request $request, Video $video, QuizService $quiz): JsonResponse
    {
        $data = $request->validate([
            'position_seconds' => ['required', 'integer', 'min:0'],
            'watched_seconds' => ['nullable', 'integer', 'min:0', 'max:600'],
        ]);
        $user = $request->user();
        abort_unless($this->access->canWatch($user, $video), 402);

        $row = DB::table('video_progress')->where('user_id', $user->id)->where('video_id', $video->id)->first();
        $pos = min($data['position_seconds'], max(1, $video->duration_seconds));
        $max = max($pos, (int) ($row->max_position_seconds ?? 0));
        $completed = $row->completed_at ?? null;
        $justCompleted = ! $completed && $video->duration_seconds > 0 && $max >= $video->duration_seconds * 0.9;

        DB::table('video_progress')->updateOrInsert(
            ['user_id' => $user->id, 'video_id' => $video->id],
            ['position_seconds' => $pos, 'max_position_seconds' => $max, 'completed_at' => $justCompleted ? now() : $completed, 'updated_at' => now()],
        );

        if (! $row) {
            $video->increment('views_count');
        }
        if ($justCompleted) {
            $video->increment('completions_count');
        }
        $watched = (int) ($data['watched_seconds'] ?? 0);
        if ($watched > 0) {
            Video::query()->whereKey($video->id)->increment('watch_seconds_total', $watched);
            $quiz->bumpActivity($user->id, ['video_seconds' => $watched]);
        }

        return response()->json(['position_seconds' => $pos, 'completed' => (bool) ($completed || $justCompleted)]);
    }
}
