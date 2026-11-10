<?php

namespace App\Services;

use App\Models\Flashcard;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * SM-2 style spaced repetition. Ratings: 1 Again, 2 Hard, 3 Good, 4 Easy.
 * New cards follow the steps shown on the buttons in the design (1m / 8m / 1d / 4d).
 */
class FlashcardScheduler
{
    public const RATINGS = [1 => 'Again', 2 => 'Hard', 3 => 'Good', 4 => 'Easy'];

    private const DAY = 1440;

    /**
     * @return array{repetitions:int, interval_minutes:int, ease:float, lapses:int}
     */
    public function next(?object $state, int $rating): array
    {
        $reps = (int) ($state->repetitions ?? 0);
        $interval = (int) ($state->interval_minutes ?? 0);
        $ease = (float) ($state->ease ?? 2.5);
        $lapses = (int) ($state->lapses ?? 0);

        if ($rating === 1) {
            return ['repetitions' => 0, 'interval_minutes' => 1, 'ease' => max(1.3, $ease - 0.2), 'lapses' => $reps > 0 ? $lapses + 1 : $lapses];
        }

        if ($reps === 0) {
            $interval = [2 => 8, 3 => self::DAY, 4 => 4 * self::DAY][$rating];
        } else {
            $base = max(self::DAY, $interval);
            $interval = (int) round(match ($rating) {
                2 => $base * 1.2,
                3 => $base * $ease,
                4 => $base * $ease * 1.3,
            });
        }

        $ease = match ($rating) {
            2 => max(1.3, $ease - 0.15),
            4 => $ease + 0.15,
            default => $ease,
        };

        return [
            'repetitions' => $rating === 2 && $reps === 0 ? 0 : $reps + 1,
            'interval_minutes' => min($interval, 365 * self::DAY),
            'ease' => round($ease, 2),
            'lapses' => $lapses,
        ];
    }

    /** Human-readable intervals for the four buttons on a given card. */
    public function previews(?object $state): array
    {
        $out = [];
        foreach (self::RATINGS as $r => $label) {
            $out[] = ['rating' => $r, 'label' => $label, 'interval' => self::formatMinutes($this->next($state, $r)['interval_minutes'])];
        }

        return $out;
    }

    public function review(User $user, Flashcard $card, int $rating): array
    {
        $state = DB::table('flashcard_reviews')->where('user_id', $user->id)->where('flashcard_id', $card->id)->first();
        $next = $this->next($state, $rating);

        DB::table('flashcard_reviews')->updateOrInsert(
            ['user_id' => $user->id, 'flashcard_id' => $card->id],
            $next + [
                'last_rating' => $rating,
                'due_at' => now()->addMinutes($next['interval_minutes']),
                'last_reviewed_at' => now(),
            ],
        );

        return $next;
    }

    public static function formatMinutes(int $m): string
    {
        return match (true) {
            $m < 60 => $m.'m',
            $m < self::DAY => round($m / 60).'h',
            $m < 30 * self::DAY => round($m / self::DAY).'d',
            $m < 365 * self::DAY => round($m / (30 * self::DAY)).'mo',
            default => round($m / (365 * self::DAY)).'y',
        };
    }
}
