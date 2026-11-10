import { isRunningInExpoGo } from 'expo';
import i18n from 'i18next';
import { Platform } from 'react-native';
import type { Home } from '../api/types';
import type { StreakWidgetProps } from '../widgets/types';
import { emptyWidgetProps } from '../widgets/types';

type WidgetHandle = {
  updateSnapshot: (p: StreakWidgetProps) => void;
  updateTimeline: (e: { date: Date; props: StreakWidgetProps }[]) => void;
  reload: () => void;
};

/** The widget exists only in development / store builds (not Expo Go, not web). */
export const widgetSupported = Platform.OS !== 'web' && !isRunningInExpoGo();

let handle: WidgetHandle | null | undefined;
function widget(): WidgetHandle | null {
  if (handle !== undefined) return handle;
  try {
    handle = widgetSupported ? ((require('../widgets/StreakWidget').default as WidgetHandle | null) ?? null) : null;
  } catch {
    handle = null;
  }
  return handle;
}

function labels(): StreakWidgetProps['l'] {
  const t = i18n.t.bind(i18n);
  return {
    dayStreak: t('widget.day_streak', 'day streak'),
    studied: t('widget.studied', 'Studied today'),
    keepIt: t('widget.keep_it', 'Study today to keep it'),
    today: t('widget.today', 'Today'),
    daysTo: t('widget.days_to', 'days to'),
    noExam: t('widget.no_exam', 'Set your exam date'),
    signIn: t('widget.sign_in', 'Open MedAlbumin to start'),
  };
}

/** One-letter weekday names for the 7 days ending today, in the app language. */
function weekLetters(week: Home['week']): string {
  const loc = i18n.language === 'am' ? 'am-ET' : i18n.language;
  return week
    .map((d) => {
      try {
        return new Date(`${d.date}T12:00:00`).toLocaleDateString(loc, { weekday: 'narrow' }).slice(0, 1);
      } catch {
        return 'MTWTFSS'[(new Date(`${d.date}T12:00:00`).getDay() + 6) % 7];
      }
    })
    .join('');
}

export function widgetPropsFrom(home: Home): StreakWidgetProps {
  const week = home.week?.length ? home.week : [];
  return {
    signedIn: true,
    streak: home.stats.streak,
    studiedToday: home.today?.studied ?? false,
    answered: home.today?.answered ?? 0,
    goal: home.today?.goal ?? 20,
    daysToExam: home.readiness.days_to_exam,
    examLabel: home.readiness.exam_label,
    week: week.map((d) => (d.studied ? '1' : '0')).join('').padStart(7, '0'),
    weekLetters: week.length === 7 ? weekLetters(week) : 'MTWTFSS',
    l: labels(),
  };
}

/** Sends fresh numbers to the home screen widget. On iOS it also rolls the day over at midnight. */
export function updateWidget(props: StreakWidgetProps): void {
  const w = widget();
  if (!w) return;
  try {
    if (Platform.OS === 'ios' && props.signedIn) {
      const midnight = new Date();
      midnight.setHours(24, 0, 5, 0);
      // After midnight: a new day, nothing studied yet; the streak holds until the end of that day.
      const tomorrow: StreakWidgetProps = {
        ...props,
        studiedToday: false,
        answered: 0,
        daysToExam: props.daysToExam !== null ? Math.max(0, props.daysToExam - 1) : null,
        streak: props.studiedToday ? props.streak : 0,
        week: (props.week.slice(1) + '0').slice(-7),
        weekLetters: props.weekLetters.slice(1) + props.weekLetters.slice(0, 1),
      };
      w.updateTimeline([
        { date: new Date(), props },
        { date: midnight, props: tomorrow },
      ]);
    } else {
      w.updateSnapshot(props);
    }
  } catch {
    // Widgets are a nice extra — never break the app.
  }
}

export function clearWidget(): void {
  updateWidget({ ...emptyWidgetProps, l: labels() });
}
