import { useRouter } from 'expo-router';
import { useTranslation } from 'react-i18next';
import { useMe } from '../../api/hooks';
import { ProfileForm } from '../../components/ProfileForm';
import { Header, Screen } from '../../components/Screen';
import { useAuth } from '../../store/auth';

export default function EditProfile() {
  const { t } = useTranslation();
  const router = useRouter();
  const me = useMe();
  const user = me.data ?? useAuth.getState().user;
  if (!user) return null;

  return (
    <Screen>
      <Header title={t('profile.edit', 'Edit profile')} />
      <ProfileForm user={user} submitLabel={t('note.save', 'Save')} onSaved={() => router.back()} />
    </Screen>
  );
}
