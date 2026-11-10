import * as Clipboard from 'expo-clipboard';
import { useState } from 'react';
import { Pressable, Share, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { useReferral } from '../api/hooks';
import { Button } from '../components/Button';
import { Icon } from '../components/Icon';
import { Header, Screen, Skeleton } from '../components/Screen';
import { Text } from '../components/Text';
import { Card } from '../components/ui';
import { fonts, useTheme } from '../theme';

/** Invite friends: when a friend subscribes, both get free days. */
export default function Invite() {
  const { t } = useTranslation();
  const { c } = useTheme();
  const q = useReferral();
  const [copied, setCopied] = useState(false);
  const r = q.data;

  return (
    <Screen onRefresh={() => q.refetch()}>
      <Header title={t('profile.invite', 'Invite friends')} />
      {!r ? (
        <Skeleton height={260} radius={24} />
      ) : (
        <>
          <View style={{ backgroundColor: '#062A5E', borderRadius: 24, padding: 22, gap: 12, alignItems: 'center' }}>
            <View style={{ width: 56, height: 56, borderRadius: 18, backgroundColor: '#12407F', alignItems: 'center', justifyContent: 'center' }}>
              <Icon name="gift" size={26} color={c.accent} />
            </View>
            <Text style={{ fontFamily: fonts.display, fontSize: 24, textAlign: 'center' }} color="#F3F6FA">
              {t('invite.title', 'You both get {days} free days', { days: r.reward_days })}
            </Text>
            <Text variant="small" color="#A9BBD8" align="center">
              {t('invite.body', 'Share your code. When a friend signs up with it and subscribes, you both get {days} free days. Up to {max} rewards.', { days: r.reward_days, max: r.max_rewards })}
            </Text>
            <Pressable
              accessibilityRole="button"
              accessibilityLabel={t('invite.copy', 'Copy code')}
              onPress={async () => {
                await Clipboard.setStringAsync(r.code);
                setCopied(true);
                setTimeout(() => setCopied(false), 1500);
              }}
              style={{ marginTop: 6, flexDirection: 'row', alignItems: 'center', gap: 10, backgroundColor: '#12407F', borderRadius: 16, paddingHorizontal: 18, height: 56 }}
            >
              <Text style={{ fontFamily: fonts.mono, fontSize: 22, letterSpacing: 3 }} color="#F3F6FA">
                {r.code}
              </Text>
              <Text variant="smallStrong" color={c.accent}>
                {copied ? t('invite.copied', 'Copied') : t('invite.copy', 'Copy code')}
              </Text>
            </Pressable>
          </View>
          <Button title={t('invite.share', 'Share invite')} icon="share" onPress={() => Share.share({ message: r.share_text }).catch(() => {})} />
          <Card style={{ flexDirection: 'row', padding: 16 }}>
            {[
              [r.joined, t('invite.joined', 'friends joined')],
              [r.rewarded, t('invite.rewarded', 'rewards earned')],
            ].map(([v, l]) => (
              <View key={String(l)} style={{ flex: 1, gap: 2 }}>
                <Text style={{ fontFamily: fonts.display, fontSize: 26 }}>{v}</Text>
                <Text variant="caption" muted>
                  {l}
                </Text>
              </View>
            ))}
          </Card>
        </>
      )}
    </Screen>
  );
}
