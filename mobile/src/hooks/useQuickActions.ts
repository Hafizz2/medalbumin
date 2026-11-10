import { useTranslation } from 'react-i18next';
import type { IconName } from '../components/Icon';
import { useTheme } from '../theme';

export type QuickAction = { icon: IconName; label: string; sub: string; bg: string; ink: string; href: string; group: 'practice' | 'learn' | 'plan' };

/** Every shortcut in the app. Home shows the first six; "See all" shows the full list. */
export function useQuickActions(counts?: { cards_due?: number; mistakes?: number }): QuickAction[] {
  const { t } = useTranslation();
  const { c } = useTheme();

  return [
    { group: 'practice', icon: 'question', label: t('home.practice', 'Practice'), sub: t('home.practice_sub', 'Smart quiz'), bg: c.skyBg, ink: c.sky, href: '/quiz/builder' },
    { group: 'practice', icon: 'note', label: t('home.past_papers', 'Past papers'), sub: t('home.past_papers_sub', 'Real exam style'), bg: c.tealSoft, ink: c.teal, href: '/papers' },
    { group: 'practice', icon: 'timer', label: t('home.grand_mock', 'Grand mock'), sub: t('home.grand_mock_sub', 'Timed exam'), bg: c.plumBg, ink: c.plum, href: '/mock' },
    {
      group: 'learn',
      icon: 'cards',
      label: t('home.flashcards', 'Flashcards'),
      sub: counts?.cards_due ? t('home.cards_due', '{count} due today', { count: counts.cards_due }) : t('home.flashcards_sub', 'Spaced repetition'),
      bg: c.chip,
      ink: c.text,
      href: '/flashcards',
    },
    {
      group: 'practice',
      icon: 'refresh',
      label: t('home.mistakes', 'Mistakes'),
      sub: counts?.mistakes ? t('home.mistakes_count', '{count} to review', { count: counts.mistakes }) : t('home.mistakes_none', 'None yet'),
      bg: c.dangerBg,
      ink: c.danger,
      href: '/quiz/builder?source=mistakes',
    },
    { group: 'plan', icon: 'pin', label: t('home.match', 'Match'), sub: t('home.match_sub', 'Uni predictor'), bg: '#062A5E', ink: c.accent, href: '/match' },
    { group: 'plan', icon: 'calendar', label: t('actions.study_plan', 'Study plan'), sub: t('actions.study_plan_sub', 'Daily tasks to exam day'), bg: c.tealSoft, ink: c.teal, href: '/study-plan' },
    { group: 'learn', icon: 'video', label: t('actions.videos', 'Video lessons'), sub: t('actions.videos_sub', 'Short lectures'), bg: c.skyBg, ink: c.sky, href: '/videos' },
    { group: 'learn', icon: 'book', label: t('actions.notes', 'Notes'), sub: t('actions.notes_sub', 'Official summaries'), bg: c.sandBg, ink: c.sand, href: '/(tabs)/notes' },
    { group: 'practice', icon: 'bookmark', label: t('actions.bookmarks', 'Bookmarks'), sub: t('actions.bookmarks_sub', 'Saved questions'), bg: c.plumBg, ink: c.plum, href: '/quiz/builder?source=bookmarks' },
    { group: 'learn', icon: 'image', label: t('actions.search', 'Search & images'), sub: t('actions.search_sub', 'ECGs, X-rays, slides'), bg: c.chip, ink: c.text, href: '/search' },
    { group: 'learn', icon: 'flask', label: t('actions.tools', 'Lab values & tools'), sub: t('actions.tools_sub', 'Calculators, drugs'), bg: c.tealSoft, ink: c.teal, href: '/tools' },
    { group: 'learn', icon: 'download', label: t('offline.title', 'Offline downloads'), sub: t('profile.offline_sub', 'Study without internet'), bg: '#062A5E', ink: c.accent, href: '/downloads' },
    { group: 'plan', icon: 'chart', label: t('actions.progress', 'My progress'), sub: t('actions.progress_sub', 'Scores by subject'), bg: c.skyBg, ink: c.sky, href: '/(tabs)/progress' },
    { group: 'plan', icon: 'gift', label: t('actions.invite', 'Invite friends'), sub: t('actions.invite_sub', 'Earn free days'), bg: c.sandBg, ink: c.sand, href: '/invite' },
  ];
}
