<?php

namespace App\Support;

use App\Services\UiTranslationService;

/**
 * Puts a notification's title and body into the student's app language for the push.
 * Uses the same text the app shows (keys `notif_msg.{type}_title` / `_body`,
 * translated in Admin → Languages), with `{name}` placeholders filled from `vars`.
 */
class NotificationText
{
    public function __construct(private readonly UiTranslationService $ui) {}

    /** @return array{0:string, 1:string} */
    public function render(?string $locale, array $payload): array
    {
        $title = (string) ($payload['title'] ?? '');
        $body = (string) ($payload['body'] ?? '');
        $type = $payload['type'] ?? null;
        if (! $locale || $locale === 'en' || ! $type) {
            return [$title, $body];
        }

        try {
            $strings = $this->ui->bundle($locale)['strings'];
        } catch (\Throwable) {
            return [$title, $body];
        }
        $vars = (array) ($payload['vars'] ?? []);
        $fill = fn (string $text) => preg_replace_callback('/\{(\w+)\}/', fn ($m) => array_key_exists($m[1], $vars) ? (string) $vars[$m[1]] : $m[0], $text);

        $t = $strings["notif_msg.{$type}_title"] ?? null;
        $b = $strings["notif_msg.{$type}_body"] ?? null;

        return [$t ? $fill($t) : $title, $b ? $fill($b) : $body];
    }
}
