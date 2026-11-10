<?php

namespace App\Notifications\Channels;

use App\Models\User;
use App\Notifications\AppNotification;
use App\Services\ExpoPushService;
use App\Support\NotificationText;

class ExpoPushChannel
{
    public function __construct(
        private readonly ExpoPushService $push,
        private readonly NotificationText $text,
    ) {}

    public function send(User $notifiable, AppNotification $notification): void
    {
        $tokens = $notifiable->devices()->active()->whereNotNull('push_token')->pluck('push_token')->all();
        if (! $tokens) {
            return;
        }
        $payload = $notification->toArray($notifiable);
        [$title, $body] = $this->text->render($notifiable->app_locale, $payload);

        $this->push->send($tokens, $title, $body, [
            'type' => $payload['type'],
            'category' => $payload['category'],
            'route' => $payload['route'] ?? null,
            'params' => $payload['params'] ?? null,
        ]);
    }
}
