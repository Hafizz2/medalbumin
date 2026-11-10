import { Alert } from 'react-native';
import { useTranslation } from 'react-i18next';
import { api } from '../../api/client';
import { useDevices } from '../../api/hooks';
import { Icon } from '../../components/Icon';
import { Header, Screen, Skeleton } from '../../components/Screen';
import { Text } from '../../components/Text';
import { Badge, Card, Row } from '../../components/ui';
import { fmtAgo } from '../../lib/format';
import { useTheme } from '../../theme';

/** Phones signed in to this account (limit 2). */
export default function Devices() {
  const { t } = useTranslation();
  const { c } = useTheme();
  const q = useDevices();

  const signOut = (id: number, name: string) =>
    Alert.alert(t('devices.sign_out_title', 'Sign out {name}?', { name }), t('devices.sign_out_body', 'That phone will need to sign in again.'), [
      { text: t('common.cancel', 'Cancel'), style: 'cancel' },
      { text: t('signin.sign_out_device', 'Sign out'), style: 'destructive', onPress: () => api(`/me/devices/${id}`, { method: 'DELETE' }).then(() => q.refetch()) },
    ]);

  return (
    <Screen onRefresh={() => q.refetch()}>
      <Header title={t('profile.devices', 'Devices')} />
      <Text variant="small" muted>
        {t('devices.body', 'Your account works on up to {n} phones. Sign out a phone you no longer use.', { n: q.data?.limit ?? 2 })}
      </Text>
      {!q.data ? (
        <Skeleton height={140} />
      ) : (
        <Card>
          {q.data.devices.map((d, i) => (
            <Row
              key={d.id}
              first={i === 0}
              left={<Icon name="phone" size={20} color={c.text} />}
              title={d.name || d.platform}
              subtitle={d.last_seen_at ? t('devices.last', 'Last used {ago}', { ago: fmtAgo(d.last_seen_at) }) : d.platform}
              right={d.current ? <Badge label={t('devices.this_phone', 'This phone')} tone="ok" /> : <Text variant="smallStrong" color={c.stop} onPress={() => signOut(d.id, d.name || d.platform)}>{t('signin.sign_out_device', 'Sign out')}</Text>}
            />
          ))}
        </Card>
      )}
    </Screen>
  );
}
