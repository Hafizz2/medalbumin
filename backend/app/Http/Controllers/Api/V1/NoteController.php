<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Note;
use App\Models\Question;
use App\Models\UserNote;
use App\Models\Video;
use App\Services\ContentAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class NoteController extends Controller
{
    public function __construct(private readonly ContentAccess $access) {}

    /** Official notes written by the MedAlbumin medical team. */
    public function official(Request $request): JsonResponse
    {
        $data = $request->validate([
            'department_id' => ['nullable', 'integer'],
            'subcategory_id' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $user = $request->user();

        $notes = Note::query()->published()->with(['department', 'subcategory'])
            ->when($data['department_id'] ?? null, fn ($q, $id) => $q->where('department_id', $id))
            ->when($data['subcategory_id'] ?? null, fn ($q, $id) => $q->where('subcategory_id', $id))
            ->when($data['q'] ?? null, fn ($q, $s) => $q->where('title', 'like', "%{$s}%"))
            ->latest('published_at')->paginate(30);

        $read = DB::table('note_reads')->where('user_id', $user->id)->whereIn('note_id', collect($notes->items())->pluck('id'))->pluck('note_id')->flip();

        return response()->json([
            'data' => collect($notes->items())->map(fn (Note $n) => [
                'id' => $n->id,
                'title' => $n->title,
                'breadcrumb' => $n->breadcrumb(),
                'read_minutes' => $n->read_minutes,
                'is_new' => ! isset($read[$n->id]) && $n->published_at?->gt(now()->subDays(14)),
                'locked' => ! $this->access->canOpenNote($user, $n),
                'published_at' => $n->published_at?->toIso8601String(),
            ]),
            'meta' => ['current_page' => $notes->currentPage(), 'last_page' => $notes->lastPage()],
        ]);
    }

    public function show(Request $request, Note $note): JsonResponse
    {
        abort_unless($note->status === 'published', 404);
        $user = $request->user();
        if (! $this->access->canOpenNote($user, $note)) {
            abort(402, 'This note is part of the premium plan.');
        }

        $first = ! DB::table('note_reads')->where('note_id', $note->id)->where('user_id', $user->id)->exists();
        DB::table('note_reads')->updateOrInsert(['note_id' => $note->id, 'user_id' => $user->id], ['read_at' => now()]);
        $first && $note->increment('reads_count');

        $note->load(['department', 'subcategory']);
        $locale = app()->getLocale();
        $available = collect(\App\Models\Language::activeCodes())->filter(fn ($l) => filled($note->getTranslation('body', $l, false)))->values();
        $questions = $note->subcategory_id
            ? $this->access->scopeQuestions(Question::query()->published()->where('subcategory_id', $note->subcategory_id), $user)->count()
            : 0;

        return response()->json([
            'id' => $note->id,
            'title' => $note->title,
            'body_html' => $note->body,
            'locale' => $available->contains($locale) ? $locale : 'en',
            'available_locales' => $available,
            'bodies' => $available->mapWithKeys(fn ($l) => [$l => $note->getTranslation('body', $l, false)]),
            'breadcrumb' => $note->breadcrumb(),
            'read_minutes' => $note->read_minutes,
            'attachment_url' => $note->attachment_path ? Storage::disk('public')->url($note->attachment_path) : null,
            'department_id' => $note->department_id,
            'subcategory' => $note->subcategory ? ['id' => $note->subcategory->id, 'name' => $note->subcategory->name, 'questions' => $questions] : null,
            'my_notes' => $note->userNotes()->where('user_id', $user->id)->latest()->get()->map(fn ($n) => $this->presentMine($n)),
        ]);
    }

    // ---- My notes (private) ----

    public function mine(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100']]);

        $notes = UserNote::query()->where('user_id', $request->user()->id)
            ->with(['subcategory.department', 'notable'])
            ->when($data['q'] ?? null, fn ($q, $s) => $q->where(fn ($w) => $w->where('title', 'like', "%{$s}%")->orWhere('body', 'like', "%{$s}%")))
            ->latest('updated_at')->paginate(30);

        return response()->json([
            'data' => collect($notes->items())->map(fn ($n) => $this->presentMine($n)),
            'meta' => ['current_page' => $notes->currentPage(), 'last_page' => $notes->lastPage(), 'total' => $notes->total()],
        ]);
    }

    private function presentMine(UserNote $n): array
    {
        $context = match (true) {
            $n->notable instanceof Note => ['type' => 'note', 'id' => $n->notable_id, 'title' => $n->notable->title],
            $n->notable instanceof Question => ['type' => 'question', 'id' => $n->notable_id, 'title' => $n->notable->code],
            $n->notable instanceof Video => ['type' => 'video', 'id' => $n->notable_id, 'title' => $n->notable->title],
            default => null,
        };

        return [
            'id' => $n->id,
            'title' => $n->title,
            'body' => $n->body,
            'subcategory' => $n->subcategory ? ['id' => $n->subcategory->id, 'name' => $n->subcategory->name, 'department' => $n->subcategory->department?->name] : null,
            'context' => $context,
            'video_seconds' => $n->video_seconds,
            'updated_at' => $n->updated_at->toIso8601String(),
        ];
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateMine($request);
        $note = new UserNote([
            'title' => $data['title'] ?? null,
            'body' => $data['body'],
            'subcategory_id' => $data['subcategory_id'] ?? null,
            'video_seconds' => $data['video_seconds'] ?? null,
        ]);
        $note->user_id = $request->user()->id;
        $this->attachContext($note, $data);
        $note->save();

        return response()->json($this->presentMine($note->load(['subcategory.department', 'notable'])), 201);
    }

    public function update(Request $request, UserNote $userNote): JsonResponse
    {
        abort_unless($userNote->user_id === $request->user()->id, 404);
        $userNote->update($request->validate([
            'title' => ['sometimes', 'nullable', 'string', 'max:190'],
            'body' => ['sometimes', 'string', 'max:20000'],
        ]));

        return response()->json($this->presentMine($userNote->load(['subcategory.department', 'notable'])));
    }

    public function destroy(Request $request, UserNote $userNote): JsonResponse
    {
        abort_unless($userNote->user_id === $request->user()->id, 404);
        $userNote->delete();

        return response()->json(['message' => 'Deleted.']);
    }

    private function validateMine(Request $request): array
    {
        return $request->validate([
            'title' => ['nullable', 'string', 'max:190'],
            'body' => ['required', 'string', 'max:20000'],
            'note_id' => ['nullable', 'integer', 'exists:notes,id'],
            'question_id' => ['nullable', 'integer', 'exists:questions,id'],
            'video_id' => ['nullable', 'integer', 'exists:videos,id'],
            'video_seconds' => ['nullable', 'integer', 'min:0'],
            'subcategory_id' => ['nullable', 'integer', 'exists:subcategories,id'],
        ]);
    }

    /** Links the note to what it was written on, and files it under that subcategory. */
    private function attachContext(UserNote $note, array $data): void
    {
        $target = match (true) {
            ! empty($data['note_id']) => Note::query()->find($data['note_id']),
            ! empty($data['question_id']) => Question::query()->find($data['question_id']),
            ! empty($data['video_id']) => Video::query()->find($data['video_id']),
            default => null,
        };

        if ($target) {
            $note->notable()->associate($target);
            $note->subcategory_id ??= $target->subcategory_id;
        }
        if (! $note->title && $target) {
            $note->title = $target instanceof Question ? 'Note on '.$target->code : (string) $target->title;
        }
    }
}
