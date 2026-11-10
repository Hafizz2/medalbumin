<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Drug;
use App\Models\Flashcard;
use App\Models\FlashcardDeck;
use App\Models\LabValue;
use App\Models\Note;
use App\Models\Question;
use App\Models\Video;
use App\Services\ContentAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __construct(private readonly ContentAccess $access) {}

    /** Search everything. Translatable fields are JSON, so a LIKE matches any language. */
    public function search(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
            'type' => ['nullable', 'in:all,q,n,c,v'],
        ]);
        $user = $request->user();
        $term = '%'.addcslashes($data['q'], '%_\\').'%';
        $type = $data['type'] ?? 'all';

        $queries = [
            'q' => fn () => Question::query()->published()->with(['department', 'subcategory', 'paper'])
                ->where(fn (Builder $w) => $w->where('stem', 'like', $term)->orWhere('explanation', 'like', $term)),
            'n' => fn () => Note::query()->published()->with(['department', 'subcategory'])
                ->where(fn (Builder $w) => $w->where('title', 'like', $term)->orWhere('body', 'like', $term)),
            'c' => fn () => Flashcard::query()->with('deck')
                ->whereIn('flashcard_deck_id', FlashcardDeck::query()->availableTo($user)->select('id'))
                ->where(fn (Builder $w) => $w->where('front', 'like', $term)->orWhere('prompt', 'like', $term)->orWhere('back', 'like', $term)),
            'v' => fn () => Video::query()->published()->with(['department', 'subcategory'])
                ->where(fn (Builder $w) => $w->where('title', 'like', $term)->orWhere('description', 'like', $term)),
        ];

        $counts = collect($queries)->map(fn ($q) => $q()->count());
        $counts['img'] = Question::query()->published()->whereNotNull('image_path')->where(fn ($w) => $w->where('image_caption', 'like', $term)->orWhere('stem', 'like', $term))->count();

        $results = collect();
        foreach ($queries as $key => $q) {
            if ($type !== 'all' && $type !== $key) {
                continue;
            }
            $results = $results->concat($q()->limit($type === 'all' ? 8 : 40)->get()->map(fn ($m) => $this->present($key, $m, $user)));
        }

        return response()->json([
            'counts' => ['all' => $counts->only(['q', 'n', 'c', 'v'])->sum()] + $counts->all(),
            'data' => $results->values(),
        ]);
    }

    private function present(string $key, $m, $user): array
    {
        return match ($key) {
            'q' => [
                'type' => 'question', 'id' => $m->id, 'code' => 'Q',
                'title' => mb_strimwidth(strip_tags($m->getTranslation('stem', $m->displayLocale(app()->getLocale()))), 0, 140, '…'),
                'meta' => 'Question · '.trim(($m->department?->short_name ?: $m->department?->name).($m->sourceLabel() ? ' · '.$m->sourceLabel() : '')),
                'locked' => ! $this->access->canOpenQuestion($user, $m),
            ],
            'n' => [
                'type' => 'note', 'id' => $m->id, 'code' => 'NOTE', 'title' => $m->title,
                'meta' => 'Official note · '.$m->read_minutes.' min read',
                'locked' => ! $this->access->canOpenNote($user, $m),
            ],
            'c' => [
                'type' => 'card', 'id' => $m->id, 'deck_id' => $m->flashcard_deck_id, 'code' => 'CARD', 'title' => $m->front,
                'meta' => 'Flashcard · '.$m->deck?->title.' deck', 'locked' => false,
            ],
            'v' => [
                'type' => 'video', 'id' => $m->id, 'code' => 'VID', 'title' => $m->title,
                'meta' => 'Video · '.max(1, (int) round($m->duration_seconds / 60)).' min',
                'locked' => ! $this->access->canWatch($user, $m),
            ],
        };
    }

    /** ECGs, X-rays, slides — every question image. */
    public function imageBank(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['nullable', 'string', 'max:20'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $user = $request->user();

        $base = Question::query()->published()->whereNotNull('image_path');
        $page = (clone $base)
            ->when($data['type'] ?? null, fn ($q, $t) => $q->where('image_type', $t))
            ->when($data['q'] ?? null, fn ($q, $s) => $q->where(fn ($w) => $w->where('image_caption', 'like', "%{$s}%")->orWhere('stem', 'like', "%{$s}%")))
            ->with('department')->latest('id')->paginate(30);

        return response()->json([
            'total' => (clone $base)->count(),
            'types' => (clone $base)->whereNotNull('image_type')->groupBy('image_type')->selectRaw('image_type, COUNT(*) n')->pluck('n', 'image_type'),
            'data' => collect($page->items())->map(fn (Question $q) => [
                'question_id' => $q->id,
                'url' => $q->imageUrl(),
                'caption' => $q->image_caption,
                'type' => $q->image_type,
                'department' => $q->department?->short_name ?: $q->department?->name,
                'locked' => ! $this->access->canOpenQuestion($user, $q),
            ]),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage()],
        ]);
    }

    public function labValues(): JsonResponse
    {
        return response()->json([
            'data' => LabValue::query()->where('is_active', true)->orderBy('category')->orderBy('sort')->get()
                ->groupBy('category')->map(fn ($rows, $cat) => [
                    'category' => $cat,
                    'items' => $rows->map(fn ($l) => ['name' => $l->name, 'range' => $l->range, 'unit' => $l->unit])->values(),
                ])->values(),
        ]);
    }

    public function drugs(Request $request): JsonResponse
    {
        $q = $request->validate(['q' => ['nullable', 'string', 'max:100']])['q'] ?? null;

        return response()->json([
            'disclaimer' => 'Checked by our medical team against the national treatment guideline. For exam study, not patient care.',
            'data' => Drug::query()->where('is_active', true)
                ->when($q, fn ($w) => $w->where('name', 'like', "%{$q}%"))
                ->orderBy('sort')->get()
                ->map(fn ($d) => ['id' => $d->id, 'name' => $d->name, 'category' => $d->category, 'dose' => $d->dose, 'watch' => $d->watch]),
        ]);
    }
}
