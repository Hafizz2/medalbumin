<?php

namespace App\Services;

use App\Models\PushCampaign;
use App\Models\User;
use App\Notifications\AppNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class PushCampaignSender
{
    public function __construct(private readonly ExpoPushService $push) {}

    public const AUDIENCE_PLANS = ['all' => 'All students', 'free' => 'Free users', 'premium' => 'Premium subscribers'];

    public function audienceQuery(array $a): Builder
    {
        return User::query()->students()->where('is_guest', false)->where('status', 'active')
            ->when(($a['plan'] ?? 'all') === 'premium', fn ($q) => $q->whereHas('subscriptions', fn ($s) => $s->current()))
            ->when(($a['plan'] ?? 'all') === 'free', fn ($q) => $q->whereDoesntHave('subscriptions', fn ($s) => $s->current()))
            ->when($a['target_exam'] ?? null, fn ($q, $t) => $q->where('target_exam', $t))
            ->when($a['university_id'] ?? null, fn ($q, $u) => $q->where('university_id', $u))
            ->when($a['inactive_days'] ?? null, fn ($q, $d) => $q->where(fn ($w) => $w->whereNull('last_active_at')->orWhere('last_active_at', '<', now()->subDays((int) $d))));
    }

    public function audienceLabel(array $a): string
    {
        $parts = [self::AUDIENCE_PLANS[$a['plan'] ?? 'all'] ?? 'All students'];
        if ($a['target_exam'] ?? null) {
            $parts[] = ucfirst($a['target_exam']).' exam target';
        }
        if ($a['university_id'] ?? null) {
            $parts[] = \App\Models\University::query()->find($a['university_id'])?->short_name ?? 'one university';
        }
        if ($a['inactive_days'] ?? null) {
            $parts[] = 'inactive '.$a['inactive_days'].'+ days';
        }

        return implode(' · ', $parts);
    }

    /**
     * Stores an in-app notification for everyone in the audience and pushes it,
     * each student in their own app language (falls back to English).
     */
    public function send(PushCampaign $campaign): void
    {
        $campaign->update(['status' => 'sending']);
        $count = 0;
        $type = $campaign->kind === 'promo' ? 'promo' : 'announcement';
        $category = AppNotification::category($type);
        $image = $campaign->image_path ? Storage::disk('public')->url($campaign->image_path) : null;

        try {
            $this->audienceQuery($campaign->audience)->select(['id', 'app_locale', 'notification_prefs'])
                ->chunkById(500, function ($users) use ($campaign, &$count, $type, $category, $image) {
                    $now = now();
                    $rows = [];
                    $tokensByLocale = [];
                    $devices = DB::table('devices')->whereIn('user_id', $users->pluck('id'))->whereNull('revoked_at')->whereNotNull('push_token')->get()->groupBy('user_id');

                    foreach ($users as $u) {
                        $wants = $u->wantsNotifications($category);
                        if (! $wants && $type === 'promo') {
                            continue; // promotions switched off
                        }
                        $loc = $u->app_locale ?: 'en';
                        $title = $campaign->getTranslation('title', $loc);
                        $body = $campaign->getTranslation('body', $loc);
                        $rows[] = [
                            'id' => (string) Str::uuid(),
                            'type' => AppNotification::class,
                            'notifiable_type' => $u->getMorphClass(),
                            'notifiable_id' => $u->id,
                            'data' => json_encode(array_filter([
                                'type' => $type, 'category' => $category, 'title' => $title, 'body' => $body, 'route' => $campaign->link,
                                'image' => $image, 'action' => $campaign->action_label ? $campaign->getTranslation('action_label', $loc) : null,
                            ])),
                            'read_at' => null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                        foreach ($wants ? $devices[$u->id] ?? [] : [] as $d) {
                            $tokensByLocale[$loc][] = $d->push_token;
                        }
                    }
                    $rows && DB::table('notifications')->insert($rows);
                    $count += count($rows);

                    foreach ($tokensByLocale as $loc => $tokens) {
                        $this->push->send($tokens, $campaign->getTranslation('title', $loc), $campaign->getTranslation('body', $loc), ['type' => $type, 'category' => $category, 'route' => $campaign->link, 'image' => $image]);
                    }
                });

            $campaign->update(['status' => 'sent', 'sent_at' => now(), 'recipients_count' => $count]);
        } catch (Throwable $e) {
            $campaign->update(['status' => 'failed', 'recipients_count' => $count]);
            throw $e;
        }
    }
}
