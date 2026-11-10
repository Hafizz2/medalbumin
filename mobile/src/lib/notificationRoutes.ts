/** Maps the backend's notification `route` names to app screens. */
export function routeForNotification(route?: string | null, params?: Record<string, unknown> | null): string | null {
  switch (route) {
    case 'Plans':
      return '/plan';
    case 'LiveMock':
      return '/mock';
    case 'Discussion':
      return params?.questionId ? `/discussion/${params.questionId}` : '/notifications';
    case 'Result':
      return params?.sessionId ? `/result/${params.sessionId}` : '/mock';
    case 'QuizBuilder':
      return '/quiz/builder';
    case 'PastPapers':
      return '/papers';
    case 'Flashcards':
      return '/flashcards';
    case 'Notes':
      return '/(tabs)/notes';
    case 'StudyPlan':
      return '/study-plan';
    case 'Progress':
      return '/(tabs)/progress';
    case 'Downloads':
      return '/downloads';
    case 'Invite':
      return '/invite';
    case 'MatchScore':
    case 'MatchChoices':
      return '/match';
    case 'Profile':
      return '/(tabs)/profile';
    case 'Notifications':
      return '/notifications';
    default:
      return null;
  }
}
