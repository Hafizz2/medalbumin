<?php

namespace App\Http\Resources;

use App\Models\User;
use App\Services\ContentAccess;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $sub = $this->activeSubscription()->with('plan')->first();
        $until = $sub ? app(SubscriptionService::class)->premiumUntil($this->resource) : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'initials' => $this->initials(),
            'phone' => $this->phone,
            'email' => $this->email,
            'avatar_url' => $this->avatarUrl(),
            'gender' => $this->gender,
            'university' => $this->university ? ['id' => $this->university->id, 'name' => $this->university->name] : null,
            'program' => $this->program,
            'study_year' => $this->study_year,
            'target_exam' => $this->target_exam,
            'exam_date' => $this->exam_date?->toDateString(),
            'target_score' => $this->target_score,
            'app_locale' => $this->app_locale,
            'content_locale' => $this->content_locale,
            'dark_mode' => $this->dark_mode,
            'daily_reminder' => $this->daily_reminder,
            'reminder_time' => substr((string) $this->reminder_time, 0, 5),
            'is_guest' => $this->is_guest,
            'profile_complete' => (bool) ($this->name && $this->university_id && $this->study_year),
            'referral_code' => $this->referral_code,
            'premium' => [
                'active' => (bool) $sub,
                'plan' => $sub?->plan?->name,
                'until' => $until?->toIso8601String(),
            ],
            'free_questions_left' => app(ContentAccess::class)->guestQuotaLeft($this->resource),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
