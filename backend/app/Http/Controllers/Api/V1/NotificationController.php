<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Notifications\AppNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $page = $user->notifications()->latest()->paginate(30);

        return response()->json([
            'unread' => $user->unreadNotifications()->count(),
            'data' => collect($page->items())->map(fn ($n) => [
                'id' => $n->id,
                'type' => $type = $n->data['type'] ?? 'announcement',
                'category' => $n->data['category'] ?? AppNotification::category($type),
                'title' => $n->data['title'] ?? '',
                'body' => $n->data['body'] ?? '',
                'route' => $n->data['route'] ?? null,
                'params' => $n->data['params'] ?? null,
                'vars' => $n->data['vars'] ?? null,
                'image' => $n->data['image'] ?? null,
                'action' => $n->data['action'] ?? null,
                'read' => (bool) $n->read_at,
                'created_at' => $n->created_at->toIso8601String(),
            ]),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage()],
        ]);
    }

    public function read(Request $request, string $id): JsonResponse
    {
        $request->user()->notifications()->whereKey($id)->firstOrFail()->markAsRead();

        return response()->json(['unread' => $request->user()->unreadNotifications()->count()]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['unread' => 0]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $request->user()->notifications()->whereKey($id)->delete();

        return response()->json(['unread' => $request->user()->unreadNotifications()->count()]);
    }

    public function settings(Request $request): JsonResponse
    {
        $u = $request->user();

        return response()->json([
            'categories' => collect(AppNotification::OPTIONAL)->mapWithKeys(fn ($c) => [$c => $u->wantsNotifications($c)]),
            'daily_reminder' => $u->daily_reminder,
            'reminder_time' => substr((string) $u->reminder_time, 0, 5),
            'daily_goal' => $u->daily_goal,
            'has_push_device' => $u->devices()->active()->whereNotNull('push_token')->exists(),
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'categories' => ['sometimes', 'array'],
            'categories.*' => ['boolean'],
            'daily_reminder' => ['sometimes', 'boolean'],
            'reminder_time' => ['sometimes', 'date_format:H:i'],
            'daily_goal' => ['sometimes', 'integer', Rule::in([10, 20, 30, 50, 100])],
        ]);
        $u = $request->user();

        if (isset($data['categories'])) {
            $prefs = $u->notification_prefs ?? [];
            foreach ($data['categories'] as $cat => $on) {
                if (in_array($cat, AppNotification::OPTIONAL, true)) {
                    $prefs[$cat] = (bool) $on;
                }
            }
            $u->notification_prefs = $prefs;
        }
        foreach (['daily_reminder', 'daily_goal'] as $k) {
            if (array_key_exists($k, $data)) {
                $u->{$k} = $data[$k];
            }
        }
        if (isset($data['reminder_time'])) {
            $u->reminder_time = $data['reminder_time'].':00';
        }
        $u->save();

        return $this->settings($request);
    }

    /** "Send me a test notification" in the app's notification settings. */
    public function test(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if(RateLimiter::tooManyAttempts('notify-test:'.$user->id, 5), 429, 'Please wait a minute before sending another test.');
        RateLimiter::hit('notify-test:'.$user->id, 60);

        $user->notifyNow(AppNotification::test());

        return response()->json([
            'sent' => true,
            'has_push_device' => $user->devices()->active()->whereNotNull('push_token')->exists(),
        ]);
    }
}
