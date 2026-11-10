<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Note;
use App\Models\Question;
use App\Services\ContentAccess;
use App\Services\QuizService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Offline packs: a student downloads one department (questions with answers and
 * explanations, plus official notes) and studies it without internet.
 * Answers given offline are uploaded later through sync().
 */
class OfflineController extends Controller
{
    public const MAX_QUESTIONS = 3000;

    public function __construct(
        private readonly ContentAccess $access,
        private readonly QuizService $quiz,
    ) {}

    private function assertNotGuest($user): void
    {
        abort_if($user->is_guest, 403, 'Create a free account to download for offline use.');
    }

    /** What can be downloaded, with a version so the app knows when a pack is out of date. */
    public function packs(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->assertNotGuest($user);
        $premium = $this->access->isPremium($user);
        $locale = app()->getLocale();

        $departments = Department::query()->active()->orderBy('sort')->get();
        $questions = $this->access->scopeQuestions(Question::query()->published(), $user)
            ->groupBy('department_id')->selectRaw('department_id, COUNT(*) n, MAX(updated_at) changed')->get()->keyBy('department_id');
        $notes = Note::query()->published()->when(! $premium, fn ($q) => $q->where('visibility', 'all'))
            ->groupBy('department_id')->selectRaw('department_id, COUNT(*) n, MAX(updated_at) changed')->get()->keyBy('department_id');

        return response()->json([
            'data' => $departments->map(function (Department $d) use ($questions, $notes, $premium, $locale) {
                $q = $questions[$d->id] ?? null;
                $n = $notes[$d->id] ?? null;
                $count = min((int) ($q->n ?? 0), self::MAX_QUESTIONS);

                return [
                    'id' => $d->id,
                    'name' => $d->name,
                    'questions' => $count,
                    'notes' => (int) ($n->n ?? 0),
                    // Rough size: ~1.5 KB per question, ~6 KB per note.
                    'size_kb' => (int) ceil($count * 1.5 + ($n->n ?? 0) * 6),
                    'version' => substr(md5(implode('|', [$q->changed ?? '', $n->changed ?? '', $count, $premium ? 'p' : 'f', $locale])), 0, 12),
                ];
            })->filter(fn ($d) => $d['questions'] || $d['notes'])->values(),
        ]);
    }

    public function pack(Request $request, Department $department): JsonResponse
    {
        $user = $request->user();
        $this->assertNotGuest($user);
        abort_unless($department->is_active, 404);
        $premium = $this->access->isPremium($user);
        $locale = app()->getLocale();

        $questions = $this->access->scopeQuestions(Question::query()->published()->where('department_id', $department->id), $user)
            ->with('options')->orderBy('id')->limit(self::MAX_QUESTIONS)->get();
        $notes = Note::query()->published()->where('department_id', $department->id)
            ->when(! $premium, fn ($q) => $q->where('visibility', 'all'))->orderBy('id')->get();

        return response()->json([
            'id' => $department->id,
            'name' => $department->name,
            'locale' => $locale,
            'generated_at' => now()->toIso8601String(),
            'subcategories' => $department->subcategories()->where('is_active', true)->orderBy('sort')->get()
                ->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->values(),
            'questions' => $questions->map(function (Question $q) use ($locale) {
                $loc = $q->displayLocale($locale);

                return [
                    'id' => $q->id,
                    'code' => $q->code,
                    'subcategory_id' => $q->subcategory_id,
                    'difficulty' => $q->difficulty,
                    'year' => $q->exam_year,
                    'stem' => $q->getTranslation('stem', $loc),
                    'image' => $q->image_path ? ['url' => $q->imageUrl(), 'caption' => $q->image_caption] : null,
                    'options' => $q->options->sortBy('key')->values()->map(fn ($o) => ['key' => $o->key, 'text' => $o->getTranslation('text', $loc)]),
                    'correct' => $q->correct_option,
                    'explanation' => $q->getTranslation('explanation', $loc),
                ];
            })->values(),
            'notes' => $notes->map(fn (Note $n) => [
                'id' => $n->id,
                'title' => $n->title,
                'subcategory_id' => $n->subcategory_id,
                'read_minutes' => $n->read_minutes,
                'body_html' => $n->body,
            ])->values(),
        ]);
    }

    /** Uploads answers given offline. Each batch id is processed once, so retries are safe. */
    public function sync(Request $request): JsonResponse
    {
        $data = $request->validate([
            'batch_id' => ['required', 'string', 'max:64'],
            'attempts' => ['required', 'array', 'max:500'],
            'attempts.*.question_id' => ['required', 'integer'],
            'attempts.*.option' => ['required', 'string', 'size:1'],
            'attempts.*.time_ms' => ['nullable', 'integer', 'min:0'],
            'attempts.*.answered_at' => ['nullable', 'date'],
        ]);
        $user = $request->user();

        $session = $this->quiz->importOffline($user, $data['attempts'], $data['batch_id']);

        return response()->json(['synced' => $session?->answered_count ?? 0, 'session_id' => $session?->id]);
    }
}
