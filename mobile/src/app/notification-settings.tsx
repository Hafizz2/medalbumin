import DateTimePicker, { DateTimePickerAndroid } from '@react-native-community/datetimepicker';
import { useQueryClient } from '@tanstack/react-query';
import { useFocusEffect } from 'expo-router';
import { useCallback, useState } from 'react';
import { Alert, Linking, Platform, Pressable, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { ApiError } from '../api/client';
import { qk, sendTestNotification, useNotificationSettings, useUpdateNotificationSettings } from '../api/hooks';
import type { NotificationSettings } from '../api/types';
import { Button } from '../components/Button';
import { Icon } from '../components/Icon';
import { ErrorState, Header, Screen, Skeleton } from '../components/Screen';
import { Text } from '../components/Text';
import { Card, Chip, ChipRow, IconTile, Switch } from '../components/ui';
import { notificationLook } from '../lib/notificationLook';
import { notifications, permissionState, PermissionState, requestPermission } from '../lib/notifications';
import { useTheme } from '../theme';

type Cat = keyof NotificationSettings['categories'];

/** Choose what MedAlbumin notifies about, the daily reminder time and the daily goal. */
export default function NotificationSettingsScreen() {
  const { t } = useTranslation();
  const { c } = useTheme();
  const qc = useQueryClient();
  const q = useNotificationSettings();
  const update = useUpdateNotificationSettings();
  const [perm, setPerm] = useState<PermissionState>('undetermined');
  const [showIosTime, setShowIosTime] = useState(false);
  const [testing, setTesting] = useState(false);

  useFocusEffect(
    useCallback(() => {
      permissionState().then(setPerm);
    }, []),
  );

  const s = q.data;
  const save = (body: Parameters<typeof update.mutate>[0]) =>
    update.mutate(body, { onError: (e) => Alert.alert(t('common.error', 'Something went wrong'), e instanceof ApiError ? e.message : '') });

  const toggleCategory = (cat: Cat, on: boolean) => {
    if (!s) return;
    // Show the change straight away.
    qc.setQueryData<NotificationSettings>(qk.notificationSettings, { ...s, categories: { ...s.categories, [cat]: on } });
    save({ categories: { [cat]: on } });
  };

  const timeAsDate = (hhmm: string) => {
    const [h, m] = hhmm.split(':').map(Number);
    const d = new Date();
    d.setHours(h || 20, m || 0, 0, 0);
    return d;
  };
  const fmtTime = (hhmm: string) => timeAsDate(hhmm).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
  const setTime = (d?: Date) => d && save({ reminder_time: `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}` });

  const pickTime = () => {
    if (!s) return;
    if (Platform.OS === 'android') DateTimePickerAndroid.open({ value: timeAsDate(s.reminder_time), mode: 'time', onChange: (_, d) => setTime(d) });
    else setShowIosTime((v) => !v);
  };

  const enable = async () => {
    if (perm === 'denied') return Linking.openSettings();
    setPerm(await requestPermission());
  };

  const testServer = async () => {
    setTesting(true);
    try {
      const r = await sendTestNotification();
      qc.invalidateQueries({ queryKey: qk.notifications });
      Alert.alert(
        t('notif.test_sent', 'Test sent'),
        r.has_push_device
          ? t('notif.test_sent_push', 'It should appear on your phone in a few seconds, and in your notification list.')
          : t('notif.test_sent_list', 'It’s in your notification list. Pushes to this phone need the installed app with notifications allowed.'),
      );
    } catch (e) {
      Alert.alert(t('common.error', 'Something went wrong'), e instanceof ApiError ? e.message : '');
    } finally {
      setTesting(false);
    }
  };

  // A local notification in 5 seconds: checks this phone's sound, banner and lock-screen look, even offline.
  const testLocal = async () => {
    const N = notifications();
    if (!N) return;
    if (perm !== 'granted') {
      const p = await requestPermission();
      setPerm(p);
      if (p !== 'granted') return;
    }
    await N.scheduleNotificationAsync({
      content: { title: t('notif_msg.streak_title', 'Streak at risk'), body: t('notif_msg.streak_body', 'Answer 10 questions before midnight to keep your {days}-day streak.', { days: 7 }), data: { route: 'QuizBuilder', type: 'streak', category: 'reminders' } },
      trigger: { type: N.SchedulableTriggerInputTypes.TIME_INTERVAL, seconds: 5, channelId: 'reminders' },
    });
    Alert.alert(t('notif.local_scheduled', 'Coming in 5 seconds'), t('notif.local_scheduled_body', 'Lock your phone or go to the home screen to see it.'));
  };

  const categories: { id: Cat; title: string; sub: string; type: string }[] = [
    { id: 'reminders', title: t('nset.reminders', 'Study reminders'), sub: t('nset.reminders_sub', 'Streak alerts, exam countdown, flashcards due, weekly summary'), type: 'streak' },
    { id: 'exams', title: t('nset.exams', 'Exams & grand mocks'), sub: t('nset.exams_sub', 'New mocks, reminders before they start, results'), type: 'new_exam' },
    { id: 'content', title: t('nset.content', 'New content'), sub: t('nset.content_sub', 'New past papers and study material'), type: 'new_content' },
    { id: 'community', title: t('nset.community', 'Discussions'), sub: t('nset.community_sub', 'Replies and faculty answers to your doubts'), type: 'reply' },
    { id: 'updates', title: t('nset.updates', 'MedAlbumin news'), sub: t('nset.updates_sub', 'New features and important announcements'), type: 'announcement' },
    { id: 'promos', title: t('nset.promos', 'Offers & promotions'), sub: t('nset.promos_sub', 'Discounts and special offers'), type: 'promo' },
  ];

  return (
    <Screen onRefresh={() => q.refetch()}>
      <Header title={t('notif.settings', 'Notification settings')} />

      {perm !== 'granted' ? (
        <Card tone="navy" style={{ padding: 16, gap: 12 }}>
          <View style={{ flexDirection: 'row', gap: 12, alignItems: 'center' }}>
            <IconTile icon="bell" bg="rgba(255,255,255,0.1)" ink={c.accent} />
            <View style={{ flex: 1, gap: 2 }}>
              <Text variant="bodyStrong" color={c.onNavy}>
                {perm === 'unavailable' ? t('nset.unavailable_title', 'Pushes need the installed app') : t('nset.off_title', 'Notifications are off on this phone')}
              </Text>
              <Text variant="caption" color={c.onNavySoft}>
                {perm === 'unavailable'
                  ? t('nset.unavailable_body', 'Expo Go on Android can’t show pushes. Your notification list still works, and pushes work in the real app.')
                  : t('nset.off_body', 'Turn them on so you don’t miss mock exams, results and your streak.')}
              </Text>
            </View>
          </View>
          {perm !== 'unavailable' ? <Button title={perm === 'denied' ? t('nset.open_settings', 'Open phone settings') : t('nset.turn_on', 'Turn on notifications')} variant="accent" size="md" onPress={enable} /> : null}
        </Card>
      ) : null}

      {q.isError && !s ? (
        <ErrorState error={q.error} onRetry={() => q.refetch()} />
      ) : !s ? (
        <>
          <Skeleton height={150} />
          <Skeleton height={320} />
        </>
      ) : (
        <>
          <Text variant="label" muted>
            {t('nset.daily', 'Daily study')}
          </Text>
          <Card>
            <View style={{ flexDirection: 'row', alignItems: 'center', gap: 12, padding: 14 }}>
              <View style={{ flex: 1, gap: 2 }}>
                <Text variant="bodyStrong">{t('profile.reminder', 'Daily study reminder')}</Text>
                <Text variant="caption" muted>
                  {t('nset.reminder_sub', 'Only if you haven’t studied yet that day')}
                </Text>
              </View>
              <Switch value={s.daily_reminder} onChange={(v) => save({ daily_reminder: v })} label={t('profile.reminder', 'Daily study reminder')} />
            </View>
            {s.daily_reminder ? (
              <Pressable onPress={pickTime} accessibilityRole="button" style={{ flexDirection: 'row', alignItems: 'center', gap: 12, paddingHorizontal: 14, minHeight: 52, borderTopWidth: 1, borderTopColor: c.divider }}>
                <Icon name="timer" size={18} color={c.teal} />
                <Text variant="bodyMedium" style={{ flex: 1 }}>
                  {t('profile.reminder_time', 'Reminder time')}
                </Text>
                <Text variant="bodyStrong" color={c.teal}>
                  {fmtTime(s.reminder_time)}
                </Text>
              </Pressable>
            ) : null}
            {Platform.OS === 'ios' && showIosTime ? <DateTimePicker value={timeAsDate(s.reminder_time)} mode="time" display="spinner" onChange={(_, d) => setTime(d)} /> : null}
            <View style={{ padding: 14, gap: 10, borderTopWidth: 1, borderTopColor: c.divider }}>
              <View style={{ gap: 2 }}>
                <Text variant="bodyStrong">{t('nset.goal', 'Daily goal')}</Text>
                <Text variant="caption" muted>
                  {t('nset.goal_sub', 'Questions per day — shown on your home screen widget')}
                </Text>
              </View>
              <ChipRow>
                {[10, 20, 30, 50, 100].map((g) => (
                  <Chip key={g} label={String(g)} selected={s.daily_goal === g} onPress={() => save({ daily_goal: g })} />
                ))}
              </ChipRow>
            </View>
          </Card>

          <Text variant="label" muted>
            {t('nset.what', 'What to notify me about')}
          </Text>
          <Card>
            {categories.map((cat, i) => {
              const look = notificationLook(cat.type, c);
              return (
                <View key={cat.id} style={{ flexDirection: 'row', alignItems: 'center', gap: 12, padding: 14, borderTopWidth: i ? 1 : 0, borderTopColor: c.divider }}>
                  <IconTile icon={look.icon} bg={look.bg} ink={look.ink} size={36} iconSize={16} />
                  <View style={{ flex: 1, gap: 2 }}>
                    <Text variant="bodyStrong" style={{ fontSize: 14 }}>
                      {cat.title}
                    </Text>
                    <Text variant="caption" muted>
                      {cat.sub}
                    </Text>
                  </View>
                  <Switch value={s.categories[cat.id]} onChange={(v) => toggleCategory(cat.id, v)} label={cat.title} />
                </View>
              );
            })}
            <View style={{ flexDirection: 'row', alignItems: 'center', gap: 12, padding: 14, borderTopWidth: 1, borderTopColor: c.divider, opacity: 0.75 }}>
              <IconTile icon="lock" bg={c.chip} ink={c.textSoft} size={36} iconSize={16} />
              <View style={{ flex: 1, gap: 2 }}>
                <Text variant="bodyStrong" style={{ fontSize: 14 }}>
                  {t('nset.account', 'Account & payments')}
                </Text>
                <Text variant="caption" muted>
                  {t('nset.account_sub', 'Always on — payment approvals, plan status and security')}
                </Text>
              </View>
            </View>
          </Card>

          <Text variant="label" muted>
            {t('nset.test', 'Test')}
          </Text>
          <View style={{ gap: 8 }}>
            <Button title={t('nset.test_server', 'Send me a test notification')} icon="bell" variant="soft" loading={testing} onPress={testServer} />
            {notifications() ? <Button title={t('nset.test_local', 'Preview on this phone (5 s)')} icon="timer" variant="light" onPress={testLocal} /> : null}
          </View>
        </>
      )}
    </Screen>
  );
}
