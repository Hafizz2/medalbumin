import { useState } from 'react';
import { Alert, Platform, Text as RNText, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { useHome } from '../api/hooks';
import { Button } from '../components/Button';
import { Icon } from '../components/Icon';
import { ErrorState, Header, Screen, Skeleton } from '../components/Screen';
import { Text } from '../components/Text';
import { Card, Segmented } from '../components/ui';
import { updateWidget, widgetPropsFrom, widgetSupported } from '../lib/widget';
import { fonts, useTheme } from '../theme';
import type { StreakWidgetProps } from '../widgets/types';

const NAVY = '#062A5E';
const TEAL = '#14BCC4';
const SOFT = '#A9BBD8';
const AMBER = '#FBBF77';
const FLAME = '#FF9F43';

/** Live preview of the home screen widget, and how to add it on iPhone and Android. */
export default function WidgetScreen() {
  const { t } = useTranslation();
  const { c } = useTheme();
  const home = useHome();
  const [platform, setPlatform] = useState<'ios' | 'android'>(Platform.OS === 'android' ? 'android' : 'ios');

  const props = home.data ? widgetPropsFrom(home.data) : null;

  const refresh = () => {
    if (!props) return;
    updateWidget(props);
    Alert.alert(t('widget.updated', 'Widget updated'), t('widget.updated_body', 'Your home screen widget now shows the latest numbers.'));
  };

  const iosSteps = [
    t('widget.ios1', 'Touch and hold an empty area of your home screen until the apps jiggle.'),
    t('widget.ios2', 'Tap “Edit” (or +) in the top corner, then search for MedAlbumin.'),
    t('widget.ios3', 'Pick the small or medium size and tap “Add Widget”.'),
    t('widget.ios4', 'For the lock screen: touch and hold the lock screen → Customize → Lock Screen → tap the widget area → MedAlbumin.'),
  ];
  const androidSteps = [
    t('widget.and1', 'Touch and hold an empty area of your home screen.'),
    t('widget.and2', 'Tap “Widgets”, then find MedAlbumin in the list.'),
    t('widget.and3', 'Touch and hold “MedAlbumin streak” and drop it on your home screen.'),
    t('widget.and4', 'Touch and hold the widget to resize it — wider shows more.'),
  ];

  return (
    <Screen onRefresh={() => home.refetch()}>
      <Header title={t('widget.title', 'Home screen widget')} />
      <View style={{ gap: 4 }}>
        <Text variant="h1">{t('widget.headline', 'Your streak, always in sight')}</Text>
        <Text variant="small" muted>
          {t('widget.intro', 'See your streak, today’s goal and days to your exam without opening the app. It updates every time you study.')}
        </Text>
      </View>

      {home.isError && !home.data ? (
        <ErrorState error={home.error} onRetry={() => home.refetch()} />
      ) : !props ? (
        <Skeleton height={330} radius={24} />
      ) : (
        <View style={{ gap: 14, alignItems: 'center', paddingVertical: 18, borderRadius: 24, backgroundColor: c.chip }}>
          <View style={{ flexDirection: 'row', gap: 14 }}>
            <SmallPreview p={props} />
            <LockPreview p={props} />
          </View>
          <MediumPreview p={props} />
          <Text variant="caption" muted>
            {t('widget.preview_note', 'Live preview with your real numbers')}
          </Text>
        </View>
      )}

      {widgetSupported ? (
        <Button title={t('widget.refresh', 'Update widget now')} icon="refresh" variant="soft" onPress={refresh} disabled={!props} />
      ) : (
        <Card tone="teal" style={{ padding: 14, flexDirection: 'row', gap: 10 }}>
          <Icon name="sparkle" size={18} color={c.teal} />
          <Text variant="small" style={{ flex: 1 }} color={c.tealInk}>
            {t('widget.expo_go', 'Widgets can’t run inside Expo Go. Install the MedAlbumin app build (development or store build) to add the widget — this preview shows exactly how it will look.')}
          </Text>
        </Card>
      )}

      <Text variant="h2">{t('widget.how', 'How to add it')}</Text>
      <Segmented
        value={platform}
        onChange={setPlatform}
        options={[
          { id: 'ios', label: 'iPhone' },
          { id: 'android', label: 'Android' },
        ]}
      />
      <Card style={{ padding: 16, gap: 12 }}>
        {(platform === 'ios' ? iosSteps : androidSteps).map((s, i) => (
          <View key={i} style={{ flexDirection: 'row', gap: 12 }}>
            <View style={{ width: 24, height: 24, borderRadius: 12, backgroundColor: c.navy, alignItems: 'center', justifyContent: 'center' }}>
              <Text style={{ fontFamily: fonts.bodySemibold, fontSize: 12 }} color={c.onNavy}>
                {i + 1}
              </Text>
            </View>
            <Text variant="small" style={{ flex: 1, lineHeight: 20 }}>
              {s}
            </Text>
          </View>
        ))}
      </Card>
    </Screen>
  );
}

function Flame({ size, on }: { size: number; on: boolean }) {
  return <RNText style={{ fontSize: size, opacity: on ? 1 : 0.55 }}>🔥</RNText>;
}

function WeekDots({ p, pills = false }: { p: StreakWidgetProps; pills?: boolean }) {
  if (pills) {
    // Android (Glance) style: the 7 days as round cells with the day letter inside.
    return (
      <View style={{ flexDirection: 'row', gap: 5 }}>
        {Array.from({ length: 7 }).map((_, i) => {
          const on = p.week[i] === '1';
          const today = i === 6;
          const bg = on ? (today ? FLAME : TEAL) : today ? '#2B5596' : '#123F80';
          return (
            <View key={i} style={{ width: 18, height: 18, borderRadius: 9, backgroundColor: bg, alignItems: 'center', justifyContent: 'center' }}>
              <RNText style={{ fontSize: 8, color: on ? NAVY : today ? '#FFFFFF' : SOFT, fontFamily: fonts.bodySemibold }}>{p.weekLetters[i] ?? ''}</RNText>
            </View>
          );
        })}
      </View>
    );
  }
  return (
    <View style={{ flexDirection: 'row', gap: 6 }}>
      {Array.from({ length: 7 }).map((_, i) => (
        <View key={i} style={{ alignItems: 'center', gap: 3 }}>
          <View style={{ width: 9, height: 9, borderRadius: 5, backgroundColor: p.week[i] === '1' ? (i === 6 ? FLAME : TEAL) : '#2B4C7E' }} />
          <RNText style={{ fontSize: 9, color: i === 6 ? '#FFFFFF' : SOFT, fontFamily: fonts.bodyMedium }}>{p.weekLetters[i] ?? ''}</RNText>
        </View>
      ))}
    </View>
  );
}

/** The goal progress bar from the Android widget. */
function GoalBar({ p, width }: { p: StreakWidgetProps; width: number }) {
  const goal = p.goal > 0 ? p.goal : 20;
  const answered = Math.max(0, Math.min(p.answered, goal));
  const fill = answered > 0 ? Math.max(6, Math.round((width * answered) / goal)) : 0;
  return (
    <View style={{ width, height: 6, borderRadius: 3, backgroundColor: '#1D4C8F' }}>
      {fill > 0 ? <View style={{ width: Math.min(fill, width), height: 6, borderRadius: 3, backgroundColor: answered >= goal ? TEAL : FLAME }} /> : null}
    </View>
  );
}

function SmallPreview({ p }: { p: StreakWidgetProps }) {
  return (
    <View style={{ width: 158, height: 158, borderRadius: 24, backgroundColor: NAVY, padding: 14, justifyContent: 'space-between' }} accessibilityLabel={`${p.streak} ${p.l.dayStreak}`}>
      <View>
        <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }}>
          <Flame size={22} on={p.studiedToday} />
          <RNText style={{ fontFamily: fonts.displayBold, fontSize: 34, color: '#FFFFFF' }}>{p.streak}</RNText>
        </View>
        <RNText style={{ fontFamily: fonts.bodySemibold, fontSize: 12, color: SOFT }}>{p.l.dayStreak}</RNText>
      </View>
      <View style={{ gap: 7 }}>
        <WeekDots p={p} pills />
        <RNText numberOfLines={1} style={{ fontFamily: fonts.bodySemibold, fontSize: 12, color: p.answered >= p.goal ? TEAL : '#FFFFFF' }}>
          {p.l.today} · {Math.min(p.answered, p.goal)}/{p.goal}
        </RNText>
        <GoalBar p={p} width={130} />
        <RNText numberOfLines={1} style={{ fontFamily: fonts.bodyMedium, fontSize: 11, color: p.studiedToday ? TEAL : AMBER }}>
          {p.studiedToday ? p.l.studied : p.l.keepIt}
        </RNText>
      </View>
    </View>
  );
}

function MediumPreview({ p }: { p: StreakWidgetProps }) {
  return (
    <View style={{ width: 330, height: 158, borderRadius: 24, backgroundColor: NAVY, padding: 14, justifyContent: 'space-between' }}>
      <View style={{ flexDirection: 'row', gap: 12, flex: 1, alignItems: 'center' }}>
        <View style={{ width: 120 }}>
          <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }}>
            <Flame size={22} on={p.studiedToday} />
            <RNText style={{ fontFamily: fonts.displayBold, fontSize: 34, color: '#FFFFFF' }}>{p.streak}</RNText>
          </View>
          <RNText style={{ fontFamily: fonts.bodySemibold, fontSize: 12, color: SOFT }}>{p.l.dayStreak}</RNText>
          <RNText numberOfLines={1} style={{ fontFamily: fonts.body, fontSize: 11, color: p.studiedToday ? TEAL : AMBER, marginTop: 6 }}>
            {p.studiedToday ? p.l.studied : p.l.keepIt}
          </RNText>
        </View>
        <View style={{ flex: 1, borderRadius: 18, backgroundColor: '#0D3A7A', paddingHorizontal: 12, paddingVertical: 12 }}>
          <RNText style={{ fontFamily: fonts.bodySemibold, fontSize: 11, color: SOFT }}>{p.l.today}</RNText>
          <RNText style={{ fontFamily: fonts.displayBold, fontSize: 22, color: p.answered >= p.goal ? TEAL : '#FFFFFF' }}>
            {Math.min(p.answered, p.goal)}/{p.goal}
          </RNText>
          <View style={{ marginTop: 5 }}>
            <GoalBar p={p} width={146} />
          </View>
        </View>
      </View>
      <View style={{ alignItems: 'center', marginTop: 10 }}>
        <View style={{ flexDirection: 'row', gap: 14 }}>
          <WeekDots p={p} pills />
        </View>
      </View>
    </View>
  );
}

/** iPhone lock-screen style (monochrome). */
function LockPreview({ p }: { p: StreakWidgetProps }) {
  return (
    <View style={{ width: 158, height: 158, borderRadius: 24, backgroundColor: '#1B2433', alignItems: 'center', justifyContent: 'center', gap: 10 }}>
      <RNText style={{ fontFamily: fonts.displayMedium, fontSize: 30, color: '#FFFFFF' }}>9:41</RNText>
      <View style={{ width: 54, height: 54, borderRadius: 27, backgroundColor: 'rgba(255,255,255,0.18)', alignItems: 'center', justifyContent: 'center' }}>
        <RNText style={{ fontSize: 13 }}>🔥</RNText>
        <RNText style={{ fontFamily: fonts.displayBold, fontSize: 16, color: '#FFFFFF' }}>{p.streak}</RNText>
      </View>
    </View>
  );
}
