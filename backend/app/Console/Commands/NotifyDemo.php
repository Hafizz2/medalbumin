<?php

namespace App\Console\Commands;

use App\Models\Exam;
use App\Models\User;
use App\Notifications\AppNotification;
use Illuminate\Console\Command;

/**
 * Sends sample notifications to one student so the notification centre,
 * pushes and the per-category settings can be tried on a phone.
 *
 *   php artisan medalbumin:notify-demo you@example.com            (one of each type)
 *   php artisan medalbumin:notify-demo you@example.com --type=streak
 */
class NotifyDemo extends Command
{
    protected $signature = 'medalbumin:notify-demo {user : Email, phone or user id} {--type=all : One type, or "all"}';

    protected $description = 'Send sample notifications to a student for testing';

    public function handle(): int
    {
        $key = $this->argument('user');
        $user = User::query()->where('email', $key)->orWhere('phone', $key)->orWhere('id', is_numeric($key) ? (int) $key : 0)->first();
        if (! $user) {
            $this->error("No user found for {$key}.");

            return self::FAILURE;
        }

        $exam = Exam::query()->where('type', 'grand')->latest('id')->first();
        $samples = [
            'reminder' => AppNotification::studyReminder(),
            'streak' => AppNotification::streakAtRisk(7),
            'exam_countdown' => AppNotification::examCountdown('exit exam', 30),
            'cards_due' => AppNotification::cardsDue(24),
            'weekly_summary' => AppNotification::weeklySummary(142, 78, 7),
            'new_exam' => AppNotification::newExam($exam->id ?? 1, $exam->title ?? 'Grand Mock #13', now()->addDays(5)->format('D j M, g:i A')),
            'exam_reminder' => AppNotification::examReminder($exam->id ?? 1, $exam->title ?? 'Grand Mock #13', 'tomorrow'),
            'new_content' => AppNotification::newPaper(1, 'National Exit Exam 2025'),
            'reply' => AppNotification::replied(1, 'Yonas G.', 'Right-sided leads help here — check V4R for RV involvement.'),
            'faculty_answer' => AppNotification::facultyAnswered(1, 'Exams expect RCA because the AV node is fed by the RCA in most people.'),
            'plan_expiring' => AppNotification::planExpiring(now()->addDays(3)->format('j M Y'), 3),
            'payment_approved' => AppNotification::paymentApproved('Premium 3 months', now()->addMonths(3)->format('j M Y')),
            'promo' => new AppNotification('promo', 'Exit exam season: 30% off', 'Get Premium for 3 months at 30% off until Friday. All past papers and mocks included.', 'Plans', action: 'See offer'),
            'announcement' => new AppNotification('announcement', 'Offline downloads are here', 'Download any department and practise without internet. Find it under Quick actions.', 'Downloads'),
        ];

        $type = $this->option('type');
        $send = $type === 'all' ? $samples : array_intersect_key($samples, [$type => true]);
        if (! $send) {
            $this->error('Unknown type. Use one of: all, '.implode(', ', array_keys($samples)));

            return self::FAILURE;
        }

        foreach ($send as $t => $n) {
            $user->notifyNow($n);
            $this->line("  sent {$t}");
        }
        $pushable = $user->devices()->active()->whereNotNull('push_token')->count();
        $this->info(count($send)." notification(s) sent to {$user->email}. Phones that can receive pushes: {$pushable}.");

        return self::SUCCESS;
    }
}
