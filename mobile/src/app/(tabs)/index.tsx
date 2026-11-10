import { useRouter } from 'expo-router';
import { Image, Pressable, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { useHome } from '../../api/hooks';
import { ActionGrid } from '../../components/ActionGrid';
import { Brand } from '../../components/Brand';
import { IconButton } from '../../components/Button';
import { Icon, IconName } from '../../components/Icon';
import { ErrorState, Screen, Skeleton } from '../../components/Screen';
import { Text } from '../../components/Text';
import { Card, IconTile, Ring, SectionTitle } from '../../components/ui';
import { fmtMinutes, fmtNumber } from '../../lib/format';
import { useQuickActions } from '../../hooks/useQuickActions';
import { useAuth } from '../../store/auth';
import { fonts, TAB_BAR_SPACE, useTheme } from '../../theme';

/** 02 · Home */
export default function Home() {
  const { t } = useTranslation();
  const { c, dark } = useTheme();
  const router = useRouter();
  const home = useHome();
  const user = useAuth((s) => s.user);
  const h = home.data;

  const greeting = h
    ? {
        morning: t('home.good_morning', 'Good morning, {name}', { name: h.first_name ?? '' }),
        afternoon: t('home.good_afternoon', 'Good afternoon, {name}', { name: h.first_name ?? '' }),
        evening: t('home.good_evening', 'Good evening, {name}', { name: h.first_name ?? '' }),
      }[h.greeting].replace(/,\s*$/, '')
    : '';

  const statusText = h
    ? { on_track: t('home.on_track', 'On track'), needs_work: t('home.needs_focus', 'Needs focus'), getting_started: t('home.getting_started', 'Getting started') }[h.readiness.status]
    : '';
  const weak = h?.readiness.weak_areas ?? [];
  const advice = !h
    ? ''
    : h.readiness.status === 'getting_started'
      ? t('home.advice_start', 'Answer a few questions to see where you stand.')
      : weak.length
        ? t('home.advice_focus', 'Focus on {areas} next.', { areas: weak.join(t('common.and_join', ' and ')) })
        : t('home.advice_keep', 'You’re improving. Keep going!');

  const tiles = useQuickActions(h?.counts).slice(0, 6);

  const exam = h?.next_exam;
  const examDate = exam?.starts_at ? new Date(exam.starts_at) : null;

  return (
    <Screen onRefresh={() => home.refetch()} bottomInset={TAB_BAR_SPACE} contentStyle={{ gap: 10 }}>
      <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' }}>
        <Brand size={40} onDark={dark} />
        <View style={{ flexDirection: 'row', gap: 6, alignItems: 'center' }}>
          <IconButton icon="bell" label={t('home.notifications', 'Notifications')} onPress={() => router.push('/notifications')} badge={!!h?.unread_notifications} />
          <Pressable accessibilityRole="button" accessibilityLabel={t('tabs.profile', 'Profile')} onPress={() => router.push('/(tabs)/profile')}>
            {h?.avatar_url ? (
              <Image source={{ uri: h.avatar_url }} style={{ width: 40, height: 40, borderRadius: 20 }} />
            ) : (
              <View style={{ width: 40, height: 40, borderRadius: 20, backgroundColor: c.skyBg, alignItems: 'center', justifyContent: 'center' }}>
                <Text style={{ fontFamily: fonts.bodySemibold, fontSize: 14 }} color={c.teal}>
                  {h?.initials ?? user?.initials ?? ''}
                </Text>
              </View>
            )}
          </Pressable>
        </View>
      </View>

      {home.isError && !h ? (
        <ErrorState error={home.error} onRetry={() => home.refetch()} />
      ) : !h ? (
        <>
          <Skeleton height={48} />
          <Skeleton height={190} radius={24} />
          <Skeleton height={64} />
          <Skeleton height={180} />
        </>
      ) : (
        <>
          <View style={{ gap: 2 }}>
            <Text variant="h1" style={{ fontSize: 24 }}>
              {greeting}
            </Text>
            <Text variant="small" muted>
              {t('home.subtitle', 'Your goal is within reach. Keep going!')}
            </Text>
          </View>

          {user?.is_guest ? (
            <Card tone="teal" onPress={() => router.push('/sign-in?mode=signup')} style={{ padding: 14, flexDirection: 'row', alignItems: 'center', gap: 12 }}>
              <Icon name="sparkle" size={20} color={c.teal} />
              <Text variant="smallStrong" style={{ flex: 1 }} color={c.tealInk}>
                {t('home.guest_banner', '{count} free questions left. Create a free account to save your progress.', { count: user.free_questions_left ?? 0 })}
              </Text>
              <Icon name="chevronRight" size={18} color={c.tealInk} />
            </Card>
          ) : null}

          {/* Exam readiness */}
          <View style={{ backgroundColor: '#062A5E', borderRadius: 24, paddingVertical: 15, paddingHorizontal: 16, gap: 11 }}>
            <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' }}>
              <Text variant="smallStrong" color="#C5D3EA">
                {t('home.readiness', 'Exam readiness')}
              </Text>
              <View style={{ backgroundColor: '#12407F', borderRadius: 99, paddingHorizontal: 10, paddingVertical: 5 }}>
                <Text style={{ fontFamily: fonts.bodyMedium, fontSize: 12 }} color="#A3E4E8">
                  {h.readiness.days_to_exam !== null
                    ? t('home.exam_in_days', '{exam} · {days} days', { exam: h.readiness.exam_label, days: h.readiness.days_to_exam })
                    : h.readiness.exam_label}
                </Text>
              </View>
            </View>
            <View style={{ flexDirection: 'row', alignItems: 'center', gap: 18 }}>
              <Ring value={h.readiness.score} size={74} color={c.accent} track="#1B4A8A" label={`${h.readiness.score}%`} />
              <View style={{ flex: 1, gap: 5 }}>
                <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }}>
                  <Text style={{ fontFamily: fonts.display, fontSize: 20 }} color={c.accent}>
                    {statusText}
                  </Text>
                  {h.readiness.status === 'on_track' ? <Icon name="arrowUpRight" size={16} color={c.accent} strokeWidth={2.4} /> : null}
                </View>
                <Text variant="small" color="#A9BBD8">
                  {advice}
                </Text>
              </View>
            </View>
            <View style={{ flexDirection: 'row', paddingTop: 10, borderTopWidth: 1, borderTopColor: '#1B4A8A' }}>
              {[
                { icon: 'check2' as IconName, v: fmtNumber(h.stats.answered), l: t('home.answered', 'answered'), ink: '#FFFFFF' },
                { icon: 'target' as IconName, v: `${h.stats.accuracy}%`, l: t('home.accuracy', 'accuracy'), ink: '#FFFFFF' },
                { icon: 'flame' as IconName, v: t('home.days', '{count} days', { count: h.stats.streak }), l: t('home.streak', 'streak'), ink: '#FBBF77' },
              ].map((s) => (
                <View key={s.l} style={{ flex: 1, gap: 3 }}>
                  <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }}>
                    <Icon name={s.icon} size={14} color={s.ink} strokeWidth={2} />
                    <Text variant="monoLarge" color={s.ink}>
                      {s.v}
                    </Text>
                  </View>
                  <Text style={{ fontSize: 11 }} color="#94A6C4">
                    {s.l}
                  </Text>
                </View>
              ))}
            </View>
          </View>

          {/* Study plan */}
          <Pressable
            accessibilityRole="button"
            onPress={() => router.push('/study-plan')}
            style={({ pressed }) => ({ backgroundColor: c.tealSoft, borderRadius: 18, paddingVertical: 12, paddingHorizontal: 14, flexDirection: 'row', alignItems: 'center', gap: 12, opacity: pressed ? 0.85 : 1 })}
          >
            <IconTile icon="calendarCheck" bg="#017F8E" ink="#FFFFFF" />
            <View style={{ flex: 1, gap: 2 }}>
              <Text variant="bodyStrong">{h.study_plan ? t('home.plan_today', 'Study plan for today') : t('home.plan_create', 'Create your study plan')}</Text>
              <Text variant="caption" color={c.tealInk}>
                {h.study_plan
                  ? t('home.plan_meta', '{sessions} sessions · {time} · made for you', { sessions: h.study_plan.sessions, time: fmtMinutes(h.study_plan.minutes) })
                  : t('home.plan_create_sub', 'A day-by-day plan to your exam, built from your weak areas')}
              </Text>
            </View>
            <Icon name="chevronRight" size={18} color={c.text} strokeWidth={2} />
          </Pressable>

          <SectionTitle title={t('home.quick_actions', 'Quick actions')} action={t('common.see_all', 'See all')} onAction={() => router.push('/actions')} />
          <ActionGrid actions={tiles} />

          {exam && examDate ? (
            <Card onPress={() => router.push('/mock')} style={{ paddingVertical: 9, paddingHorizontal: 14, flexDirection: 'row', alignItems: 'center', gap: 12, borderRadius: 18 }}>
              <View style={{ width: 42, height: 42, borderRadius: 12, backgroundColor: c.plumBg, alignItems: 'center', justifyContent: 'center' }}>
                <Text style={{ fontFamily: fonts.bodySemibold, fontSize: 9 }} color={c.plum}>
                  {examDate.toLocaleDateString(undefined, { weekday: 'short' }).toUpperCase()}
                </Text>
                <Text variant="mono" style={{ fontSize: 15, lineHeight: 16 }} color={c.plum}>
                  {examDate.getDate()}
                </Text>
              </View>
              <View style={{ flex: 1, gap: 2 }}>
                <Text variant="overline" muted>
                  {exam.registered ? t('home.registered', 'Registered') : t('home.upcoming', 'Upcoming')}
                </Text>
                <Text variant="bodyStrong" style={{ fontSize: 14 }} numberOfLines={1}>
                  {exam.title} · {examDate.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' })}
                </Text>
              </View>
              <Icon name="chevronRight" size={18} color={c.text} strokeWidth={2} />
            </Card>
          ) : null}
        </>
      )}
    </Screen>
  );
}
