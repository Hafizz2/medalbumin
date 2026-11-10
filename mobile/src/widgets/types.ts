/** Everything the home screen widget shows. Labels are passed in, already in the app language. */
export type StreakWidgetProps = {
  signedIn: boolean;
  streak: number;
  studiedToday: boolean;
  answered: number;
  goal: number;
  daysToExam: number | null;
  examLabel: string;
  /** Last 7 days, oldest first: "1" studied, "0" not. */
  week: string;
  /** Single-letter day names for the same 7 days, e.g. "TWTFSSM". */
  weekLetters: string;
  l: {
    dayStreak: string;
    studied: string;
    keepIt: string;
    today: string;
    daysTo: string;
    noExam: string;
    signIn: string;
  };
};

export const emptyWidgetProps: StreakWidgetProps = {
  signedIn: false,
  streak: 0,
  studiedToday: false,
  answered: 0,
  goal: 20,
  daysToExam: null,
  examLabel: '',
  week: '0000000',
  weekLetters: 'MTWTFSS',
  l: { dayStreak: 'day streak', studied: 'Studied today', keepIt: 'Study today to keep it', today: 'Today', daysTo: 'days to', noExam: 'Set your exam date', signIn: 'Open MedAlbumin to start' },
};
