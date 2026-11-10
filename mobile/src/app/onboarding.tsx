import { useRouter } from 'expo-router';
import { View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { ProfileForm } from '../components/ProfileForm';
import { Screen } from '../components/Screen';
import { Text } from '../components/Text';
import { useAuth } from '../store/auth';

/** After the first sign-in: a few details to personalise study plans and leaderboards. */
export default function Onboarding() {
  const { t } = useTranslation();
  const router = useRouter();
  const user = useAuth((s) => s.user);
  if (!user) return null;

  return (
    <Screen>
      <View style={{ gap: 6, marginTop: 10 }}>
        <Text variant="title">{t('onboarding.title', 'Welcome to MedAlbumin')}</Text>
        <Text muted>{t('onboarding.body', 'Tell us a little about you so we can build your study plan.')}</Text>
      </View>
      <ProfileForm user={user} submitLabel={t('onboarding.start', 'Start studying')} onSaved={() => router.replace('/(tabs)')} />
      <Text variant="caption" muted align="center" onPress={() => router.replace('/(tabs)')} accessibilityRole="button">
        {t('onboarding.skip', 'Skip for now')}
      </Text>
    </Screen>
  );
}
