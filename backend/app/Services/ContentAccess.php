<?php

namespace App\Services;

use App\Models\Note;
use App\Models\Paper;
use App\Models\Question;
use App\Models\Setting;
use App\Models\User;
use App\Models\Video;
use Illuminate\Database\Eloquent\Builder;

/**
 * Decides what free, guest and premium students can open.
 * Free students get the questions marked "free"; guests get a small trial.
 */
class ContentAccess
{
    /** @var array<int, bool> */
    private array $premiumCache = [];

    public function isPremium(User $user): bool
    {
        return $this->premiumCache[$user->id] ??= $user->isPremium();
    }

    public function guestQuestionLimit(): int
    {
        return (int) Setting::get('guest_free_questions', 20);
    }

    /** Restricts a question query to what this student may practise. */
    public function scopeQuestions(Builder $query, User $user): Builder
    {
        return $this->isPremium($user) ? $query : $query->where('questions.is_free', true);
    }

    public function canOpenQuestion(User $user, Question $q): bool
    {
        return $q->is_free || $this->isPremium($user);
    }

    public function canOpenPaper(User $user, Paper $paper): bool
    {
        return ! $paper->is_premium || $this->isPremium($user);
    }

    public function canOpenNote(User $user, Note $note): bool
    {
        return $note->visibility === 'all' || $this->isPremium($user);
    }

    public function canWatch(User $user, Video $video): bool
    {
        return $video->access === 'free' || $this->isPremium($user);
    }

    /** Guests may answer a limited number of questions before signing up. */
    public function guestQuotaLeft(User $user): ?int
    {
        if (! $user->is_guest) {
            return null;
        }
        $used = (int) $user->dailyActivities()->sum('questions_answered');

        return max(0, $this->guestQuestionLimit() - $used);
    }
}
