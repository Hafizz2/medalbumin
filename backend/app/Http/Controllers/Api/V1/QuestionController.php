<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\QuestionReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class QuestionController extends Controller
{
    public function bookmark(Request $request, Question $question): JsonResponse
    {
        $user = $request->user();
        $exists = $user->bookmarks()->whereKey($question->id)->exists();
        $exists
            ? $user->bookmarks()->detach($question->id)
            : $user->bookmarks()->attach($question->id, ['created_at' => now()]);

        return response()->json(['bookmarked' => ! $exists]);
    }

    public function report(Request $request, Question $question): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', Rule::in(array_keys(QuestionReport::REASONS))],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        QuestionReport::query()->updateOrCreate(
            ['question_id' => $question->id, 'user_id' => $request->user()->id, 'status' => 'open'],
            ['reason' => $data['reason'], 'note' => $data['note'] ?? null, 'locale' => app()->getLocale()],
        );

        return response()->json(['message' => 'Thanks — our medical team will review this question.'], 201);
    }

    public function reportReasons(): JsonResponse
    {
        return response()->json(['data' => collect(QuestionReport::REASONS)->map(fn ($label, $id) => ['id' => $id, 'label' => $label])->values()]);
    }
}
