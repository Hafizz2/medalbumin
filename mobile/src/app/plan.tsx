import { useQuery } from '@tanstack/react-query';
import { View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { api } from '../api/client';
import { useSubscription } from '../api/hooks';
import { Icon } from '../components/Icon';
import { Header, Screen, Skeleton } from '../components/Screen';
import { Text } from '../components/Text';
import { Badge, Card } from '../components/ui';
import { fmtDate } from '../lib/format';
import { fonts, useTheme } from '../theme';

/**
 * 09 · Your plan — status only.
 * Plans are bought on the MedAlbumin website, so (per App Store / Google Play rules)
 * the app shows no prices, purchase buttons or links to buy.
 */
export default function Plan() {
  const { t } = useTranslation();
  const { c } = useTheme();
  const sub = useSubscription();
  const payments = useQuery({ queryKey: ['payments'], queryFn: () => api<{ data: { id: number; plan: string | null; gateway: string; amount: number; currency: string; status: string; reject_reason: string | null; created_at: string }[] }>('/payments') });
  const s = sub.data;

  const perks = [
    t('plan.perk_papers', 'Every past paper with full explanations'),
    t('plan.perk_mocks', 'Unlimited practice and timed mock exams'),
    t('plan.perk_notes', 'All official notes, flashcards and video lessons'),
    t('plan.perk_devices', 'Use on up to 2 phones'),
  ];

  return (
    <Screen onRefresh={() => Promise.all([sub.refetch(), payments.refetch()])}>
      <Header title={t('plan.title', 'Your plan')} />
      {!s ? (
        <Skeleton height={180} radius={24} />
      ) : (
        <View style={{ backgroundColor: '#062A5E', borderRadius: 24, padding: 20, gap: 10 }}>
          <Text variant="label" color={s.active ? c.accent : '#A9BBD8'}>
            {s.active ? t('plan.active', 'Active') : t('plan.free', 'Free plan')}
          </Text>
          <Text style={{ fontFamily: fonts.display, fontSize: 30, lineHeight: 34 }} color="#F3F6FA">
            {s.active ? s.plan ?? t('profile.premium', 'Premium') : t('plan.free_title', 'Studying free')}
          </Text>
          <Text variant="small" color="#A9BBD8">
            {s.active && s.until ? t('plan.until', 'Premium until {date}', { date: fmtDate(s.until) }) : t('plan.free_body', 'You can practise free questions every day.')}
          </Text>
          {s.pending_payment ? (
            <View style={{ backgroundColor: '#12407F', borderRadius: 14, padding: 12 }}>
              <Text variant="small" color="#FBBF77">
                {t('plan.pending', 'Your payment receipt is being checked. You’ll get a notification when it’s approved.')}
              </Text>
            </View>
          ) : null}
        </View>
      )}

      <Card style={{ padding: 16, gap: 12 }}>
        <Text variant="h3">{t('plan.premium_includes', 'Premium includes')}</Text>
        {perks.map((p) => (
          <View key={p} style={{ flexDirection: 'row', gap: 10, alignItems: 'flex-start' }}>
            <Icon name="check" size={18} color={c.teal} strokeWidth={2.4} />
            <Text variant="small" style={{ flex: 1 }}>
              {p}
            </Text>
          </View>
        ))}
        <Text variant="caption" muted>
          {t('plan.managed', 'Your plan is linked to your MedAlbumin account and works on every phone you sign in on.')}
        </Text>
      </Card>

      {payments.data?.data.length ? (
        <Card>
          {payments.data.data.map((p, i) => (
            <View key={p.id} style={{ paddingHorizontal: 16, paddingVertical: 12, borderTopWidth: i ? 1 : 0, borderTopColor: c.divider, gap: 4 }}>
              <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }}>
                <Text variant="bodyStrong">{p.plan}</Text>
                <Badge label={{ approved: t('plan.approved', 'Approved'), pending: t('plan.pending_short', 'Pending'), rejected: t('plan.rejected', 'Rejected') }[p.status] ?? p.status} tone={p.status === 'approved' ? 'ok' : p.status === 'pending' ? 'warn' : 'danger'} />
              </View>
              <Text variant="caption" muted>
                {fmtDate(p.created_at)}
              </Text>
              {p.reject_reason ? (
                <Text variant="caption" color={c.danger}>
                  {p.reject_reason}
                </Text>
              ) : null}
            </View>
          ))}
        </Card>
      ) : null}
    </Screen>
  );
}
