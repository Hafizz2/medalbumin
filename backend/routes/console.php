<?php

use App\Jobs\SendPushCampaign;
use App\Models\Exam;
use App\Models\OtpCode;
use App\Models\PushCampaign;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Services\AnalyticsService;
use App\Services\NotificationScheduler;
use App\Services\ProgressService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

/*
| Run the scheduler every minute on the server:
|   * * * * * cd /path/to/backend && php artisan schedule:run >> /dev/null 2>&1
| and a queue worker:  php artisan queue:work --tries=1 --timeout=3600
*/

// Scheduled push notifications from Admin → Push notifications.
Schedule::call(function () {
    PushCampaign::query()->where('status', 'scheduled')->where('scheduled_at', '<=', now())
        ->each(fn ($c) => SendPushCampaign::dispatch($c->id));
})->name('push:scheduled')->everyMinute()->withoutOverlapping();

// Grand mocks: go live at start, results out when the window ends, reminders before.
Schedule::call(function () {
    Exam::query()->where('status', 'scheduled')->where('starts_at', '<=', now())->update(['status' => 'live']);
    Exam::query()->where('status', 'live')->get()
        ->filter(fn ($e) => now()->greaterThan($e->ends_at ?? $e->starts_at->copy()->addMinutes($e->duration_minutes + 60)))
        ->each(fn ($e) => $e->update(['status' => 'results_out']));

    foreach (['day' => [now()->addDay()->subMinutes(5), now()->addDay(), 'tomorrow'], 'hour' => [now()->addHour()->subMinutes(5), now()->addHour(), 'in 1 hour']] as $kind => [$from, $to, $when]) {
        Exam::query()->where('status', 'scheduled')->whereBetween('starts_at', [$from, $to])->get()->each(function (Exam $exam) use ($kind, $when) {
            $col = 'reminded_'.$kind.'_at';
            $userIds = DB::table('exam_registrations')->where('exam_id', $exam->id)->whereNull($col)->pluck('user_id');
            User::query()->whereIn('id', $userIds)->each(fn ($u) => $u->notify(AppNotification::examReminder($exam->id, $exam->title, $when)));
            DB::table('exam_registrations')->where('exam_id', $exam->id)->whereIn('user_id', $userIds)->update([$col => now()]);
        });
    }
})->name('exams:tick')->everyFiveMinutes()->withoutOverlapping();

// Daily study reminder at each student's chosen time (checked every 15 minutes).
Schedule::call(function () {
    $from = now()->subMinutes(15)->format('H:i:s');
    $to = now()->format('H:i:s');
    User::query()->students()->where('is_guest', false)->where('status', 'active')->where('daily_reminder', true)
        ->whereTime('reminder_time', '>', $from)->whereTime('reminder_time', '<=', $to)
        ->whereDoesntHave('dailyActivities', fn ($q) => $q->where('date', today()->toDateString())->where('questions_answered', '>', 0))
        ->chunkById(500, fn ($users) => $users->each(fn ($u) => $u->notify(AppNotification::studyReminder())));
})->name('reminders:study')->everyFifteenMinutes()->withoutOverlapping();

// Streak at risk: students with a 3+ day streak who have not studied today.
Schedule::call(function (ProgressService $progress) {
    User::query()->students()->where('is_guest', false)->where('status', 'active')
        ->whereHas('dailyActivities', fn ($q) => $q->where('date', today()->subDay()->toDateString())->where('questions_answered', '>', 0))
        ->whereDoesntHave('dailyActivities', fn ($q) => $q->where('date', today()->toDateString())->where('questions_answered', '>', 0))
        ->chunkById(500, function ($users) use ($progress) {
            foreach ($users as $u) {
                $streak = $progress->streak($u);
                if ($streak >= 3) {
                    $u->notify(AppNotification::streakAtRisk($streak));
                }
            }
        });
})->name('reminders:streak')->dailyAt('19:30');

// Automatic notifications (see App\Services\NotificationScheduler).
Schedule::call(function (NotificationScheduler $n) {
    $n->announceExams();
    $n->examResults();
    $n->announcePapers();
})->name('notify:content')->everyFiveMinutes()->withoutOverlapping();
Schedule::call(fn (NotificationScheduler $n) => $n->examCountdown())->name('notify:countdown')->dailyAt('08:00');
Schedule::call(fn (NotificationScheduler $n) => $n->planExpiring())->name('notify:plan-expiring')->dailyAt('10:00');
Schedule::call(fn (NotificationScheduler $n) => $n->cardsDue())->name('notify:cards-due')->dailyAt('17:00');
Schedule::call(fn (NotificationScheduler $n) => $n->weeklySummary())->name('notify:weekly')->weeklyOn(0, '18:00');

Schedule::call(fn (AnalyticsService $a) => $a->refreshQuestionQuality())->name('questions:quality')->dailyAt('02:30');

Schedule::call(fn () => OtpCode::query()->where('created_at', '<', now()->subDay())->delete())->name('otp:prune')->daily();
Schedule::command('sanctum:prune-expired --hours=24')->daily();
Schedule::command('queue:prune-failed --hours=168')->daily();
