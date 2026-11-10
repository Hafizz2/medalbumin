<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Notifications\Channels\ExpoPushChannel;
use App\Services\NotificationScheduler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_switches_control_push_and_promos(): void
    {
        $user = User::query()->forceCreate(['name' => 'A', 'email' => 'a@example.com', 'notification_prefs' => ['promos' => false, 'reminders' => false]]);

        $this->assertSame([], (new AppNotification('promo', 'Sale', 'x'))->via($user));
        $this->assertSame(['database'], AppNotification::studyReminder()->via($user));
        $this->assertContains(ExpoPushChannel::class, AppNotification::newExam(1, 'Mock', 'Sat')->via($user));
        // Account notices can't be switched off.
        $user->notification_prefs = ['account' => false];
        $this->assertContains(ExpoPushChannel::class, AppNotification::paymentRejected('x')->via($user));
    }

    public function test_settings_list_delete_and_test(): void
    {
        $this->seedContent(false);
        $user = $this->student();

        $this->patchJson('/api/v1/notification-settings', ['categories' => ['promos' => false], 'daily_goal' => 30, 'reminder_time' => '21:15'])
            ->assertOk()->assertJsonPath('categories.promos', false)->assertJsonPath('categories.exams', true)
            ->assertJsonPath('daily_goal', 30)->assertJsonPath('reminder_time', '21:15');

        $this->postJson('/api/v1/notifications/test')->assertOk();
        $list = $this->getJson('/api/v1/notifications')->assertOk()->assertJsonPath('unread', 1)->assertJsonPath('data.0.category', 'account');
        $this->deleteJson('/api/v1/notifications/'.$list->json('data.0.id'))->assertOk()->assertJsonPath('unread', 0);

        $this->getJson('/api/v1/home')->assertOk()->assertJsonPath('today.goal', 30)->assertJsonCount(7, 'week');
    }

    public function test_new_grand_mock_is_announced_once(): void
    {
        $this->seedContent(false);
        Notification::fake();
        $a = User::query()->forceCreate(['name' => 'A', 'email' => 'a@example.com']);
        User::query()->forceCreate(['name' => 'G', 'is_guest' => true]);

        Exam::query()->create(['title' => 'Grand Mock #13', 'type' => 'grand', 'status' => 'scheduled', 'starts_at' => now()->addDays(3), 'duration_minutes' => 240, 'question_count' => 200]);
        $scheduler = app(NotificationScheduler::class);

        $this->assertSame(1, $scheduler->announceExams());
        $this->assertSame(0, $scheduler->announceExams());
        Notification::assertSentTo($a, AppNotification::class, fn ($n) => $n->type === 'new_exam');
    }
}
