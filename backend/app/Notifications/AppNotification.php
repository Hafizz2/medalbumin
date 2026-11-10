<?php

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Channels\ExpoPushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * One notification shape for the whole app. It is stored for the in-app list
 * and sent as a push. `type` picks the icon and category in the app,
 * `route`/`params` say which screen to open when tapped, and `vars` let the
 * app (and the push) show the text in the student's language.
 */
class AppNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** Category of each type. Students can switch categories off, except "account". */
    public const CATEGORIES = [
        'reminder' => 'reminders',
        'streak' => 'reminders',
        'exam_countdown' => 'reminders',
        'cards_due' => 'reminders',
        'weekly_summary' => 'reminders',
        'new_exam' => 'exams',
        'exam_reminder' => 'exams',
        'exam_results' => 'exams',
        'new_content' => 'content',
        'faculty_answer' => 'community',
        'reply' => 'community',
        'announcement' => 'updates',
        'promo' => 'promos',
        'payment_approved' => 'account',
        'payment_rejected' => 'account',
        'plan_expiring' => 'account',
        'referral' => 'account',
        'report_fixed' => 'account',
        'verification' => 'account',
        'warning' => 'account',
        'test' => 'account',
    ];

    /** Categories a student can turn off in the app. */
    public const OPTIONAL = ['reminders', 'exams', 'content', 'community', 'updates', 'promos'];

    public function __construct(
        public string $type,
        public string $title,
        public string $body,
        public ?string $route = null,
        public array $params = [],
        public bool $push = true,
        public array $vars = [],
        public ?string $image = null,
        public ?string $action = null,
    ) {}

    public static function category(string $type): string
    {
        return self::CATEGORIES[$type] ?? 'updates';
    }

    public function via(object $notifiable): array
    {
        $on = ! $notifiable instanceof User || $notifiable->wantsNotifications(self::category($this->type));
        if (! $on && self::category($this->type) === 'promos') {
            return []; // promotions switched off: don't even store them
        }

        return $this->push && $on ? ['database', ExpoPushChannel::class] : ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return array_filter([
            'type' => $this->type,
            'category' => self::category($this->type),
            'title' => $this->title,
            'body' => $this->body,
            'route' => $this->route,
            'params' => $this->params ?: null,
            'vars' => $this->vars ?: null,
            'image' => $this->image,
            'action' => $this->action,
        ], fn ($v) => $v !== null);
    }

    // ---- Named constructors. English text here; the app shows each in the student's language. ----

    public static function paymentApproved(string $planName, string $until): self
    {
        return new self('payment_approved', 'Payment approved', "Your {$planName} plan is active until {$until}.", 'Plans', vars: ['plan' => $planName, 'date' => $until]);
    }

    public static function paymentRejected(string $reason): self
    {
        return new self('payment_rejected', 'Payment receipt not approved', $reason, 'Plans');
    }

    public static function planExpiring(string $until, int $days): self
    {
        return new self('plan_expiring', 'Your premium plan ends soon', "Premium ends on {$until} ({$days} days). Renew on the MedAlbumin website to keep full access.", 'Plans', vars: ['date' => $until, 'days' => $days]);
    }

    public static function facultyAnswered(int $questionId, string $excerpt): self
    {
        $excerpt = mb_strimwidth($excerpt, 0, 80, '…');

        return new self('faculty_answer', 'Faculty answered your doubt', "“{$excerpt}”", 'Discussion', ['questionId' => $questionId], vars: ['excerpt' => $excerpt]);
    }

    public static function replied(int $questionId, string $name, string $excerpt): self
    {
        $excerpt = mb_strimwidth($excerpt, 0, 80, '…');

        return new self('reply', "{$name} replied to you", "“{$excerpt}”", 'Discussion', ['questionId' => $questionId], vars: ['name' => $name, 'excerpt' => $excerpt]);
    }

    public static function newExam(int $examId, string $title, string $when): self
    {
        return new self('new_exam', 'New grand mock: '.$title, "Starts {$when}. Register free and compete nationally.", 'LiveMock', ['examId' => $examId], vars: ['title' => $title, 'when' => $when], action: 'register');
    }

    public static function examReminder(int $examId, string $title, string $when): self
    {
        return new self('exam_reminder', $title.' '.$when, 'You are registered. Good luck!', 'LiveMock', ['examId' => $examId], vars: ['title' => $title, 'when' => $when]);
    }

    public static function examResults(int $examId, string $title, int $sessionId, ?int $rank, int $score): self
    {
        $body = $rank ? "You scored {$score}% and ranked #{$rank}. See your full report." : "You scored {$score}%. See your full report.";

        return new self('exam_results', 'Results are out: '.$title, $body, 'Result', ['sessionId' => $sessionId], vars: ['title' => $title, 'rank' => $rank, 'score' => $score]);
    }

    public static function newPaper(int $paperId, string $title): self
    {
        return new self('new_content', 'New past paper: '.$title, 'Practise it now with full explanations.', 'PastPapers', ['paperId' => $paperId], vars: ['title' => $title]);
    }

    public static function referralReward(string $friend): self
    {
        return new self('referral', 'You earned a free month', "{$friend} subscribed with your invite. 1 month was added to your plan.", 'Plans', vars: ['name' => $friend]);
    }

    public static function streakAtRisk(int $days): self
    {
        return new self('streak', 'Streak at risk', "Answer 10 questions before midnight to keep your {$days}-day streak.", 'QuizBuilder', vars: ['days' => $days]);
    }

    public static function studyReminder(): self
    {
        return new self('reminder', 'Time to study', 'Your study plan for today is ready.', 'StudyPlan');
    }

    public static function examCountdown(string $exam, int $days): self
    {
        return new self('exam_countdown', "{$days} days to your {$exam}", 'Stay on plan: a timed mock this week shows where you stand.', 'StudyPlan', vars: ['exam' => $exam, 'days' => $days]);
    }

    public static function weeklySummary(int $answered, int $accuracy, int $streak): self
    {
        return new self('weekly_summary', 'Your week in MedAlbumin', "{$answered} questions · {$accuracy}% correct · {$streak}-day streak. Keep it going!", 'Progress', vars: ['answered' => $answered, 'accuracy' => $accuracy, 'streak' => $streak]);
    }

    public static function cardsDue(int $count): self
    {
        return new self('cards_due', "{$count} flashcards are due", 'A 5-minute review now helps you remember them for the exam.', 'Flashcards', vars: ['count' => $count]);
    }

    public static function test(): self
    {
        return new self('test', 'Notifications are working', 'This is a test from MedAlbumin. You’ll get reminders and exam news like this.', 'Notifications');
    }
}
