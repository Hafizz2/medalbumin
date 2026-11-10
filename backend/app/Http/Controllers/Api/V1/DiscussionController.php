<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CommentFlag;
use App\Models\Question;
use App\Models\QuestionComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DiscussionController extends Controller
{
    /** Comments under a question: verified faculty answer first, then Top or Newest. */
    public function index(Request $request, Question $question): JsonResponse
    {
        $data = $request->validate(['sort' => ['nullable', 'in:top,new'], 'page' => ['nullable', 'integer']]);
        $userId = $request->user()->id;

        $verified = QuestionComment::query()->visible()->where('question_id', $question->id)->where('is_verified', true)
            ->with('user')->latest()->first();

        $posts = QuestionComment::query()->visible()->where('question_id', $question->id)->whereNull('parent_id')
            ->when($verified, fn ($q) => $q->whereKeyNot($verified->id))
            ->with(['user', 'replies' => fn ($q) => $q->visible()->with('user')->oldest()])
            ->when(($data['sort'] ?? 'top') === 'top', fn ($q) => $q->orderByDesc('votes_count')->latest(), fn ($q) => $q->latest())
            ->paginate(20);

        $ids = collect($posts->items())->flatMap(fn ($c) => $c->replies->pluck('id')->push($c->id));
        $voted = DB::table('comment_votes')->where('user_id', $userId)->whereIn('comment_id', $ids)->pluck('comment_id')->flip();

        return response()->json([
            'question' => ['id' => $question->id, 'code' => $question->code, 'stem' => mb_strimwidth(strip_tags($question->getTranslation('stem', $question->displayLocale(app()->getLocale()))), 0, 120, '…')],
            'count' => QuestionComment::query()->visible()->where('question_id', $question->id)->count(),
            'verified' => $verified ? $this->present($verified, $userId, $voted) : null,
            'data' => collect($posts->items())->map(fn ($c) => $this->present($c, $userId, $voted) + [
                'replies' => $c->replies->map(fn ($r) => $this->present($r, $userId, $voted))->values(),
            ]),
            'meta' => ['current_page' => $posts->currentPage(), 'last_page' => $posts->lastPage()],
        ]);
    }

    private function present(QuestionComment $c, int $userId, $voted): array
    {
        $u = $c->user;

        return [
            'id' => $c->id,
            'kind' => $c->kind,
            'body' => $c->body,
            'author' => [
                'name' => $u?->trashed() ? 'Deleted user' : ($u?->shortName() ?? 'Student'),
                'initials' => $u?->initials() ?? '?',
                'role' => $c->is_faculty ? 'faculty' : null,
            ],
            'is_mine' => $c->user_id === $userId,
            'is_verified' => $c->is_verified,
            'votes' => $c->votes_count,
            'voted' => isset($voted[$c->id]),
            'created_at' => $c->created_at->toIso8601String(),
        ];
    }

    public function store(Request $request, Question $question): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:2000'],
            'kind' => ['nullable', 'in:doubt,tip'],
            'parent_id' => ['nullable', 'integer', Rule::exists('question_comments', 'id')->where('question_id', $question->id)->whereNull('parent_id')],
        ]);
        $user = $request->user();
        abort_if($user->is_guest, 403, 'Create a free account to join the discussion.');

        $comment = DB::transaction(function () use ($data, $question, $user) {
            $comment = QuestionComment::query()->create([
                'question_id' => $question->id,
                'user_id' => $user->id,
                'parent_id' => $data['parent_id'] ?? null,
                'kind' => isset($data['parent_id']) ? 'reply' : ($data['kind'] ?? (str_contains($data['body'], '?') ? 'doubt' : 'tip')),
                'body' => trim($data['body']),
                'is_faculty' => $user->is_staff && $user->can('answer doubts'),
            ]);
            if ($comment->parent_id) {
                QuestionComment::query()->whereKey($comment->parent_id)->increment('replies_count');
            }
            Question::query()->whereKey($question->id)->increment('comments_count');

            return $comment;
        });

        // Tell the author of the post someone replied (not when replying to yourself).
        if ($comment->parent_id) {
            $parent = QuestionComment::query()->with('user')->find($comment->parent_id);
            if ($parent?->user && $parent->user_id !== $user->id && ! $parent->user->is_staff) {
                $parent->user->notify(\App\Notifications\AppNotification::replied($question->id, $user->shortName() ?: 'A student', $comment->body));
            }
        }

        return response()->json($this->present($comment->load('user'), $user->id, collect()), 201);
    }

    public function vote(Request $request, QuestionComment $comment): JsonResponse
    {
        $userId = $request->user()->id;
        $exists = DB::table('comment_votes')->where('comment_id', $comment->id)->where('user_id', $userId)->exists();

        DB::transaction(function () use ($exists, $comment, $userId) {
            if ($exists) {
                DB::table('comment_votes')->where('comment_id', $comment->id)->where('user_id', $userId)->delete();
                $comment->decrement('votes_count');
            } else {
                DB::table('comment_votes')->insert(['comment_id' => $comment->id, 'user_id' => $userId, 'created_at' => now()]);
                $comment->increment('votes_count');
            }
        });

        return response()->json(['voted' => ! $exists, 'votes' => $comment->fresh()->votes_count]);
    }

    public function flag(Request $request, QuestionComment $comment): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', Rule::in(array_keys(CommentFlag::REASONS))]]);

        $flag = CommentFlag::query()->firstOrCreate(
            ['comment_id' => $comment->id, 'user_id' => $request->user()->id],
            ['reason' => $data['reason']],
        );
        if ($flag->wasRecentlyCreated) {
            $comment->increment('flags_count');
            $comment->update(['flags_resolved_at' => null]);
        }

        return response()->json(['message' => 'Thanks for reporting. A moderator will check it.']);
    }

    public function destroy(Request $request, QuestionComment $comment): JsonResponse
    {
        abort_unless($comment->user_id === $request->user()->id, 403);
        $comment->delete();
        Question::query()->whereKey($comment->question_id)->where('comments_count', '>', 0)->decrement('comments_count');

        return response()->json(['message' => 'Deleted.']);
    }
}
