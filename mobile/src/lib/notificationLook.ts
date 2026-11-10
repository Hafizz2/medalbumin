import type { IconName } from '../components/Icon';
import type { Palette } from '../theme';

/** Icon and colours for each notification type (shared by the list and the in-app banner). */
export function notificationLook(type: string | undefined, c: Palette): { icon: IconName; bg: string; ink: string } {
  const map: Record<string, [IconName, string, string]> = {
    reminder: ['calendar', c.tealSoft, c.teal],
    streak: ['flame', c.dangerBg, c.dot],
    exam_countdown: ['target', c.plumBg, c.plum],
    cards_due: ['cards', c.skyBg, c.sky],
    weekly_summary: ['chart', c.tealSoft, c.teal],
    new_exam: ['trophy', '#062A5E', c.accent],
    exam_reminder: ['timer', c.plumBg, c.plumInk],
    exam_results: ['trophy', c.sandBg, c.gold],
    new_content: ['note', c.sandBg, c.sand],
    faculty_answer: ['shield', '#062A5E', c.accent],
    reply: ['chat', c.skyBg, c.sky],
    announcement: ['bell', c.skyBg, c.sky],
    promo: ['gift', c.sandBg, c.sand],
    payment_approved: ['check', c.tealSoft, c.teal],
    payment_rejected: ['flag', c.dangerBg, c.danger],
    plan_expiring: ['timer', c.warnBg, c.warn],
    referral: ['gift', c.sandBg, c.sand],
    report_fixed: ['check', c.tealSoft, c.teal],
    verification: ['shield', c.tealSoft, c.teal],
    warning: ['flag', c.warnBg, c.warn],
    test: ['bell', c.tealSoft, c.teal],
  };
  const [icon, bg, ink] = map[type ?? ''] ?? ['bell', c.chip, c.text];
  return { icon, bg, ink };
}
