<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Flashcard;
use App\Models\FlashcardDeck;
use App\Models\Question;
use App\Services\FlashcardScheduler;
use App\Services\QuizService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FlashcardController extends Controller
{
    public function __construct(private readonly FlashcardScheduler $scheduler) {}

    public function decks(Request $request): JsonResponse
    {
        $user = $request->user();
        $decks = FlashcardDeck::query()->availableTo($user)->with(['department', 'subcategory'])
            ->where('cards_count', '>', 0)->orderBy('sort')->get();

        $due = DB::table('flashcard_reviews as r')->join('flashcards as c', 'c.id', '=', 'r.flashcard_id')
            ->where('r.user_id', $user->id)->where('r.due_at', '<=', now())
            ->groupBy('c.flashcard_deck_id')->selectRaw('c.flashcard_deck_id as deck, COUNT(*) n')->pluck('n', 'deck');
        $seen = DB::table('flashcard_reviews as r')->join('flashcards as c', 'c.id', '=', 'r.flashcard_id')
            ->where('r.user_id', $user->id)
            ->groupBy('c.flashcard_deck_id')->selectRaw('c.flashcard_deck_id as deck, COUNT(*) n')->pluck('n', 'deck');
        $reviewedToday = DB::table('flashcard_reviews')->where('user_id', $user->id)->where('last_reviewed_at', '>=', today())->count();

        return response()->json([
            'total_cards' => $decks->sum('cards_count'),
            'due_today' => (int) $due->sum(),
            'reviewed_today' => $reviewedToday,
            'data' => $decks->map(fn (FlashcardDeck $d) => [
                'id' => $d->id,
                'title' => $d->title,
                'path' => $d->pathLabel(),
                'cards' => $d->cards_count,
                'due' => (int) ($due[$d->id] ?? 0),
                'new' => max(0, $d->cards_count - (int) ($seen[$d->id] ?? 0)),
                'is_mine' => $d->user_id === $user->id,
            ])->values(),
        ]);
    }

    /**
     * Cards to study now: due reviews first, then up to `new` unseen cards.
     * Without deck_id it mixes all decks ("30 due today").
     */
    public function study(Request $request): JsonResponse
    {
        $data = $request->validate(['deck_id' => ['nullable', 'integer'], 'new' => ['nullable', 'integer', 'between:0,50']]);
        $user = $request->user();
        $deckIds = FlashcardDeck::query()->availableTo($user)
            ->when($data['deck_id'] ?? null, fn ($q, $id) => $q->whereKey($id))->pluck('id');
        abort_if(! empty($data['deck_id']) && $deckIds->isEmpty(), 404);

        $states = DB::table('flashcard_reviews')->where('user_id', $user->id)->get()->keyBy('flashcard_id');

        $due = Flashcard::query()->whereIn('flashcard_deck_id', $deckIds)
            ->whereIn('id', $states->filter(fn ($s) => $s->due_at && strtotime($s->due_at) <= time())->keys())
            ->with('deck.subcategory', 'deck.department')->limit(100)->get()
            ->sortBy(fn ($c) => $states[$c->id]->due_at);

        $fresh = Flashcard::query()->whereIn('flashcard_deck_id', $deckIds)->whereNotIn('id', $states->keys())
            ->with('deck.subcategory', 'deck.department')->orderBy('flashcard_deck_id')->orderBy('sort')
            ->limit((int) ($data['new'] ?? 10))->get();

        $cards = $due->concat($fresh)->values();
        $deck = ! empty($data['deck_id']) ? FlashcardDeck::query()->find($data['deck_id']) : null;

        return response()->json([
            'deck' => $deck ? ['id' => $deck->id, 'title' => $deck->title, 'path' => $deck->pathLabel()] : null,
            'due_count' => $due->count(),
            'data' => $cards->map(fn (Flashcard $c) => [
                'id' => $c->id,
                'deck_title' => $c->deck->title,
                'path' => $c->deck->pathLabel(),
                'front' => $c->front,
                'prompt' => $c->prompt,
                'back' => $c->back,
                'detail' => $c->detail,
                'is_new' => ! $states->has($c->id),
                'buttons' => $this->scheduler->previews($states[$c->id] ?? null),
            ]),
        ]);
    }

    public function review(Request $request, Flashcard $flashcard, QuizService $quiz): JsonResponse
    {
        $data = $request->validate(['rating' => ['required', 'integer', 'between:1,4']]);
        $user = $request->user();
        abort_unless(FlashcardDeck::query()->availableTo($user)->whereKey($flashcard->flashcard_deck_id)->exists(), 404);

        $next = $this->scheduler->review($user, $flashcard, (int) $data['rating']);
        $quiz->bumpActivity($user->id, ['cards_reviewed' => 1]);

        return response()->json([
            'next_in' => FlashcardScheduler::formatMinutes($next['interval_minutes']),
            'due_at' => now()->addMinutes($next['interval_minutes'])->toIso8601String(),
        ]);
    }

    /** "+ Make cards from my mistakes": one card per wrongly answered question. */
    public function fromMistakes(Request $request): JsonResponse
    {
        $user = $request->user();
        $deck = FlashcardDeck::query()->firstOrCreate(
            ['user_id' => $user->id, 'is_official' => false],
            ['title' => ['en' => 'My mistakes'], 'status' => 'published'],
        );

        $existing = $deck->cards()->whereNotNull('question_id')->pluck('question_id');
        $questions = Question::query()->published()->with('options')
            ->whereIn('id', DB::table('user_question_stats')->where('user_id', $user->id)->where('last_correct', false)->pluck('question_id'))
            ->whereNotIn('id', $existing)->limit(200)->get();

        foreach ($questions as $i => $q) {
            $correct = $q->options->firstWhere('key', $q->correct_option);
            $deck->cards()->create([
                'front' => ['en' => $q->code.' · '.($q->subcategory?->name ?? $q->department?->name)],
                'prompt' => $q->getTranslations('stem'),
                'back' => $correct ? $correct->getTranslations('text') : ['en' => $q->correct_option],
                'detail' => $q->getTranslations('explanation') ?: null,
                'question_id' => $q->id,
                'sort' => $existing->count() + $i,
            ]);
        }
        $deck->refreshCount();

        return response()->json([
            'deck_id' => $deck->id,
            'added' => $questions->count(),
            'message' => $questions->isEmpty() ? 'No new mistakes to turn into cards.' : $questions->count().' cards added to “My mistakes”.',
        ]);
    }
}
