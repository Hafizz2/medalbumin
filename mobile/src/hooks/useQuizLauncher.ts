import { useRouter } from 'expo-router';
import { Alert } from 'react-native';
import { useTranslation } from 'react-i18next';
import { ApiError } from '../api/client';
import { StartQuiz, useStartQuiz } from '../api/hooks';

/** Starts a quiz session from anywhere and opens it; explains premium / empty cases. */
export function useQuizLauncher() {
  const { t } = useTranslation();
  const router = useRouter();
  const start = useStartQuiz();

  const launch = (body: StartQuiz) =>
    start.mutate(body, {
      onSuccess: (payload) => router.push(`/quiz/${payload.session.id}`),
      onError: (e) => {
        if (e instanceof ApiError && e.kind === 'premium') {
          Alert.alert(t('common.premium_title', 'Premium content'), e.message);
        } else {
          Alert.alert(t('quiz.cant_start', 'Can’t start yet'), e instanceof ApiError ? e.message : t('common.error_body', 'Please try again.'));
        }
      },
    });

  return { launch, starting: start.isPending, variables: start.variables };
}
