<?php

namespace App\Http\Resources;

use App\Models\QuizSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin QuizSession */
class QuizSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $deadline = $this->deadline();

        return [
            'id' => $this->id,
            'type' => $this->type,
            'mode' => $this->mode,
            'title' => $this->title,
            'status' => $this->status,
            'exam_id' => $this->exam_id,
            'paper_id' => $this->paper_id,
            'question_count' => $this->question_count,
            'answered_count' => $this->answered_count,
            'correct_count' => $this->status === 'completed' || $this->mode === 'practice' ? $this->correct_count : null,
            'score_pct' => $this->score_pct !== null ? (int) round($this->score_pct) : null,
            'time_limit_seconds' => $this->time_limit_seconds,
            'time_spent_seconds' => $this->time_spent_seconds,
            'started_at' => $this->started_at?->toIso8601String(),
            'deadline' => $deadline?->toIso8601String(),
            'seconds_left' => $deadline ? max(0, (int) now()->diffInSeconds($deadline, false)) : null,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'allow_back' => $this->exam ? $this->exam->rule('allow_back') : true,
        ];
    }
}
