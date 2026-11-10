import { Redirect } from 'expo-router';
import { useAuth } from '../store/auth';
import { useSettings } from '../store/settings';

/** Entry: language → sign in → app. */
export default function Index() {
  const token = useAuth((s) => s.token);
  const locale = useSettings((s) => s.appLocale);

  if (!locale) return <Redirect href="/welcome" />;
  if (!token) return <Redirect href="/sign-in" />;
  return <Redirect href="/(tabs)" />;
}
