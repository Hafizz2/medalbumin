<?php

namespace App\Services;

use App\Models\Device;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends push notifications through Expo's push service (free, works for both iOS and Android).
 * https://docs.expo.dev/push-notifications/sending-notifications/
 */
class ExpoPushService
{
    private const ENDPOINT = 'https://exp.host/--/api/v2/push/send';

    /**
     * @param  array<int, string>  $tokens
     * @return int number of messages accepted by Expo
     */
    public function send(array $tokens, string $title, string $body, array $data = []): int
    {
        $tokens = array_values(array_unique(array_filter($tokens, fn ($t) => self::isExpoToken($t))));
        if (! $tokens) {
            return 0;
        }

        $accepted = 0;
        foreach (array_chunk($tokens, 100) as $chunk) {
            $messages = array_map(fn ($to) => array_filter([
                'to' => $to,
                'title' => $title,
                'body' => $body,
                'data' => (object) $data,
                'sound' => 'default',
                // Android notification channel; the app creates one per category so students can mute each in system settings.
                'channelId' => self::channelFor($data['category'] ?? null),
                'priority' => 'high',
                'richContent' => ! empty($data['image']) ? ['image' => $data['image']] : null,
            ], fn ($v) => $v !== null), $chunk);

            try {
                $request = Http::acceptJson()->asJson()->timeout(20);
                if ($token = config('services.expo.access_token')) {
                    $request = $request->withToken($token);
                }
                $response = $request->post(self::ENDPOINT, $messages);
                $tickets = $response->json('data') ?? [];
            } catch (Throwable $e) {
                Log::warning('Expo push failed: '.$e->getMessage());

                continue;
            }

            foreach ($tickets as $i => $ticket) {
                if (($ticket['status'] ?? null) === 'ok') {
                    $accepted++;
                } elseif (($ticket['details']['error'] ?? null) === 'DeviceNotRegistered') {
                    // The app was uninstalled — stop sending to this token.
                    Device::query()->where('push_token', $chunk[$i])->update(['push_token' => null]);
                }
            }
        }

        return $accepted;
    }

    /** Android channels created by the app (see mobile/src/lib/notifications.ts). */
    public static function channelFor(?string $category): string
    {
        return match ($category) {
            'reminders' => 'reminders',
            'exams' => 'exams',
            'community' => 'community',
            'promos', 'updates', 'content' => 'news',
            'account' => 'account',
            default => 'default',
        };
    }

    public static function isExpoToken(?string $token): bool
    {
        return is_string($token) && (bool) preg_match('/^Expo(nent)?PushToken\[.+\]$/', $token);
    }
}
