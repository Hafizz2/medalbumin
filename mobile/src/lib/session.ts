import { QueryClient } from '@tanstack/react-query';
import { api, setContentLocaleHeader } from '../api/client';
import { qk } from '../api/hooks';
import type { AuthResponse, User } from '../api/types';
import { useAuth } from '../store/auth';
import { useSettings } from '../store/settings';

/**
 * Finishes any sign-in: stores the token, keeps the language chosen on the
 * welcome screen, and tells the caller where to go next.
 */
export async function completeSignIn(res: AuthResponse, queryClient: QueryClient): Promise<'/onboarding' | '/(tabs)'> {
  await useAuth.getState().signIn(res.token, res.user);
  queryClient.clear();

  let user: User = res.user;
  const chosen = useSettings.getState().appLocale;
  if (chosen && chosen !== user.app_locale) {
    // New accounts read content in the language they picked; existing ones keep their choice.
    const body = res.is_new ? { app_locale: chosen, content_locale: chosen } : { app_locale: chosen };
    user = await api<User>('/me', { method: 'PATCH', body }).catch(() => user);
  }
  useAuth.getState().setUser(user);
  queryClient.setQueryData(qk.me, user);
  setContentLocaleHeader(user.content_locale);

  return !user.is_guest && !user.profile_complete ? '/onboarding' : '/(tabs)';
}
