import type { TFunction } from 'i18next';
import type { AppNotification } from '../api/types';

/**
 * Title and body of a notification in the app language.
 * The keys (notif_msg.*) are the same ones the server uses to translate pushes,
 * so translators edit each text once in Admin → Languages.
 * Admin-written messages (announcements, promotions) and free text are shown as sent.
 */
export function notificationText(n: Pick<AppNotification, 'type' | 'title' | 'body' | 'vars'>, t: TFunction): { title: string; body: string } {
  const v = n.vars ?? {};
  const has = (...keys: string[]) => keys.every((k) => v[k] !== undefined && v[k] !== null);
  const p = Object.fromEntries(Object.entries(v).map(([k, val]) => [k, String(val ?? '')]));

  switch (n.type) {
    case 'reminder':
      return { title: t('notif_msg.reminder_title', 'Time to study'), body: t('notif_msg.reminder_body', 'Your study plan for today is ready.') };
    case 'streak':
      return has('days')
        ? { title: t('notif_msg.streak_title', 'Streak at risk'), body: t('notif_msg.streak_body', 'Answer 10 questions before midnight to keep your {days}-day streak.', p) }
        : n;
    case 'exam_countdown':
      return has('days', 'exam')
        ? { title: t('notif_msg.exam_countdown_title', '{days} days to your {exam}', p), body: t('notif_msg.exam_countdown_body', 'Stay on plan: a timed mock this week shows where you stand.') }
        : n;
    case 'cards_due':
      return has('count')
        ? { title: t('notif_msg.cards_due_title', '{count} flashcards are due', p), body: t('notif_msg.cards_due_body', 'A 5-minute review now helps you remember them for the exam.') }
        : n;
    case 'weekly_summary':
      return has('answered', 'accuracy', 'streak')
        ? { title: t('notif_msg.weekly_summary_title', 'Your week in MedAlbumin'), body: t('notif_msg.weekly_summary_body', '{answered} questions · {accuracy}% correct · {streak}-day streak. Keep it going!', p) }
        : n;
    case 'new_exam':
      return has('title', 'when')
        ? { title: t('notif_msg.new_exam_title', 'New grand mock: {title}', p), body: t('notif_msg.new_exam_body', 'Starts {when}. Register free and compete nationally.', p) }
        : n;
    case 'exam_reminder':
      return has('title', 'when') ? { title: t('notif_msg.exam_reminder_title', '{title} {when}', p), body: t('notif_msg.exam_reminder_body', 'You are registered. Good luck!') } : n;
    case 'exam_results':
      return has('title', 'score')
        ? {
            title: t('notif_msg.exam_results_title', 'Results are out: {title}', p),
            body: v.rank ? t('notif_msg.exam_results_body', 'You scored {score}% and ranked #{rank}. See your full report.', p) : t('notif_msg.exam_results_body_norank', 'You scored {score}%. See your full report.', p),
          }
        : n;
    case 'new_content':
      return has('title') ? { title: t('notif_msg.new_content_title', 'New past paper: {title}', p), body: t('notif_msg.new_content_body', 'Practise it now with full explanations.') } : n;
    case 'reply':
      return has('name', 'excerpt') ? { title: t('notif_msg.reply_title', '{name} replied to you', p), body: t('notif_msg.reply_body', '“{excerpt}”', p) } : n;
    case 'faculty_answer':
      return has('excerpt') ? { title: t('notif_msg.faculty_answer_title', 'Faculty answered your doubt'), body: t('notif_msg.faculty_answer_body', '“{excerpt}”', p) } : n;
    case 'payment_approved':
      return has('plan', 'date') ? { title: t('notif_msg.payment_approved_title', 'Payment approved'), body: t('notif_msg.payment_approved_body', 'Your {plan} plan is active until {date}.', p) } : n;
    case 'payment_rejected':
      return { title: t('notif_msg.payment_rejected_title', 'Payment receipt not approved'), body: n.body };
    case 'plan_expiring':
      return has('date', 'days')
        ? { title: t('notif_msg.plan_expiring_title', 'Your premium plan ends soon'), body: t('notif_msg.plan_expiring_body', 'Premium ends on {date} ({days} days). Renew on the MedAlbumin website to keep full access.', p) }
        : n;
    case 'referral':
      return has('name') ? { title: t('notif_msg.referral_title', 'You earned a free month'), body: t('notif_msg.referral_body', '{name} subscribed with your invite. 1 month was added to your plan.', p) } : n;
    case 'test':
      return { title: t('notif_msg.test_title', 'Notifications are working'), body: t('notif_msg.test_body', 'This is a test from MedAlbumin. You’ll get reminders and exam news like this.') };
    default:
      return { title: n.title, body: n.body };
  }
}
