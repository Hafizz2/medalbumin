import { View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { useHome } from '../api/hooks';
import { ActionGrid } from '../components/ActionGrid';
import { Header, Screen } from '../components/Screen';
import { Text } from '../components/Text';
import { useQuickActions } from '../hooks/useQuickActions';

/** "See all" from Home — every shortcut, grouped. */
export default function AllActions() {
  const { t } = useTranslation();
  const home = useHome();
  const actions = useQuickActions(home.data?.counts);

  const groups = [
    { id: 'practice', title: t('actions.practice', 'Practice & exams') },
    { id: 'learn', title: t('actions.learn', 'Learn & review') },
    { id: 'plan', title: t('actions.plan', 'Plan & track') },
  ] as const;

  return (
    <Screen>
      <Header />
      <Text variant="title">{t('home.quick_actions', 'Quick actions')}</Text>
      {groups.map((g) => (
        <View key={g.id} style={{ gap: 10 }}>
          <Text variant="label" muted>
            {g.title}
          </Text>
          <ActionGrid actions={actions.filter((a) => a.group === g.id)} />
        </View>
      ))}
    </Screen>
  );
}
