import { useQueryClient } from '@tanstack/react-query';
import { useRouter } from 'expo-router';
import * as Updates from 'expo-updates';
import * as WebBrowser from 'expo-web-browser';
import { useState } from 'react';
import { Alert, Image, Linking, Pressable, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { api, setContentLocaleHeader } from '../../api/client';
import { qk, useConfig, useDevices, useMe, useStudyPlan, useUpdateMe } from '../../api/hooks';
import { Button, IconButton } from '../../components/Button';
import { Icon } from '../../components/Icon';
import { PickerSheet } from '../../components/PickerSheet';
import { Screen } from '../../components/Screen';
import { Text } from '../../components/Text';
import { Card, Row, Switch } from '../../components/ui';
import { applyLanguage, needsDirectionChange } from '../../i18n';
import { fmtDate } from '../../lib/format';
import { useAuth } from '../../store/auth';
import { useSettings } from '../../store/settings';
import { fonts, TAB_BAR_SPACE, useTheme } from '../../theme';

/** 10 · Profile & settings */
export default function Profile() {
  const { t } = useTranslation();
  const { c } = useTheme();
  const router = useRouter();
  const qc = useQueryClient();
  const me = useMe();
  const config = useConfig();
  const devices = useDevices();
  const plan = useStudyPlan();
  const update = useUpdateMe();
  const themeMode = useSettings((s) => s.themeMode);
  const setThemeMode = useSettings((s) => s.setThemeMode);
  const setAppLocale = useSettings((s) => s.setAppLocale);
  const signOut = useAuth((s) => s.signOut);
  const [picker, setPicker] = useState<'app' | 'content' | 'time' | null>(null);

  const user = me.data ?? useAuth.getState().user;
  const languages = config.data?.languages ?? [];
  const langName = (code?: string) => languages.find((l) => l.code === code)?.native_name ?? code ?? '';

  const setAppLanguage = async (code: string) => {
    const lang = languages.find((l) => l.code === code);
    setAppLocale(code);
    update.mutate({ app_locale: code });
    await applyLanguage(code, lang?.ui_version);
    if (lang && needsDirectionChange(lang.direction)) {
      Alert.alert(t('welcome.restart_title', 'Restarting'), t('welcome.restart_body', 'MedAlbumin will restart to show this language right to left.'), [
        { text: t('common.ok', 'OK'), onPress: () => Updates.reloadAsync().catch(() => {}) },
      ]);
    }
  };

  const setContentLanguage = (code: string) => {
    update.mutate(
      { content_locale: code },
      {
        onSuccess: () => {
          setContentLocaleHeader(code);
          // Questions and notes reload in the new language.
          qc.invalidateQueries();
        },
      },
    );
  };

  const toggleDark = (on: boolean) => {
    setThemeMode(on ? 'dark' : 'light');
    update.mutate({ dark_mode: on });
  };

  const logOut = () =>
    Alert.alert(t('profile.logout_title', 'Sign out?'), t('profile.logout_body_email', 'You can sign back in any time with your email, Telegram or Apple account.'), [
      { text: t('common.cancel', 'Cancel'), style: 'cancel' },
      {
        text: t('profile.logout', 'Log out'),
        style: 'destructive',
        onPress: async () => {
          await api('/auth/logout', { method: 'POST' }).catch(() => {});
          await signOut();
          qc.clear();
          router.replace('/sign-in');
        },
      },
    ]);

  const deleteAccount = () =>
    Alert.alert(t('profile.delete_title', 'Delete your account?'), t('profile.delete_body', 'Your profile, progress, notes and devices are permanently deleted. This cannot be undone.'), [
      { text: t('common.cancel', 'Cancel'), style: 'cancel' },
      {
        text: t('profile.delete_confirm', 'Delete forever'),
        style: 'destructive',
        onPress: async () => {
          try {
            await api('/me', { method: 'DELETE' });
            await signOut();
            qc.clear();
            router.replace('/sign-in');
          } catch (e) {
            Alert.alert(t('common.error_title', 'Something went wrong'), e instanceof Error ? e.message : '');
          }
        },
      },
    ]);

  if (!user) return null;

  const target = { exit: t('profile.target_exit', 'Exit exam'), qualification: t('profile.target_qualification', 'Qualification exam'), licensing: t('profile.target_licensing', 'Licensing exam'), residency: t('profile.target_residency', 'Residency exam') };
  const subtitle = [user.program, user.study_year, user.target_exam ? t('profile.target', 'Target: {exam}', { exam: target[user.target_exam] }) : null].filter(Boolean).join(' · ');
  const deviceCount = devices.data ? `${devices.data.devices.length} / ${devices.data.limit}` : '';
  const hours = Array.from({ length: 17 }, (_, i) => `${String(i + 6).padStart(2, '0')}:00`);

  return (
    <Screen onRefresh={() => Promise.all([me.refetch(), devices.refetch()])} bottomInset={TAB_BAR_SPACE}>
      <View style={{ flexDirection: 'row', alignItems: 'center', gap: 14 }}>
        {user.avatar_url ? (
          <Image source={{ uri: user.avatar_url }} style={{ width: 64, height: 64, borderRadius: 32 }} />
        ) : (
          <View style={{ width: 64, height: 64, borderRadius: 32, backgroundColor: c.skyBg, alignItems: 'center', justifyContent: 'center' }}>
            <Text style={{ fontFamily: fonts.display, fontSize: 22 }} color={c.teal}>
              {user.initials}
            </Text>
          </View>
        )}
        <View style={{ flex: 1, gap: 2 }}>
          <Text variant="h2" style={{ fontSize: 20 }} numberOfLines={1}>
            {user.is_guest ? t('profile.guest', 'Guest') : user.name || t('profile.no_name', 'Add your name')}
          </Text>
          <Text variant="small" muted numberOfLines={2}>
            {user.is_guest ? t('profile.guest_sub', 'Your progress is saved on this phone only.') : subtitle || user.phone || user.email || ''}
          </Text>
        </View>
        {!user.is_guest ? <IconButton icon="edit" label={t('profile.edit', 'Edit profile')} onPress={() => router.push('/profile/edit')} /> : null}
      </View>

      {user.is_guest ? (
        <Card tone="navy" style={{ padding: 16, gap: 10 }}>
          <Text variant="h3" color="#F3F6FA">
            {t('profile.guest_cta_title', 'Create your free account')}
          </Text>
          <Text variant="small" color="#A9BBD8">
            {t('profile.guest_cta_body', 'Keep your progress, use 2 phones, join grand mocks and discussions.')}
          </Text>
          <Button title={t('profile.guest_cta', 'Sign in')} variant="accent" size="md" onPress={() => router.push('/sign-in?mode=signup')} />
        </Card>
      ) : (
        <Card tone={user.premium.active ? 'navy' : 'teal'} onPress={() => router.push('/plan')} style={{ padding: 16, flexDirection: 'row', alignItems: 'center', gap: 12 }}>
          <View style={{ flex: 1, gap: 3 }}>
            <Text style={{ fontFamily: fonts.display, fontSize: 18 }} color={user.premium.active ? '#F3F6FA' : c.text}>
              {user.premium.active ? t('profile.premium', 'Premium') : t('profile.free_plan', 'Free plan')}
            </Text>
            <Text variant="small" color={user.premium.active ? c.accent : c.tealInk}>
              {user.premium.active && user.premium.until
                ? t('profile.premium_until', 'Active until {date}', { date: fmtDate(user.premium.until) })
                : t('profile.free_sub', 'Free questions every day')}
            </Text>
          </View>
          <Icon name="chevronRight" size={18} color={user.premium.active ? '#F3F6FA' : c.text} />
        </Card>
      )}

      <Card>
        <Row first title={t('profile.app_language', 'App language')} right={<Value text={langName(user.app_locale)} />} onPress={() => setPicker('app')} />
        <Row title={t('profile.content_language', 'Content language')} subtitle={t('profile.content_language_sub', 'Questions, explanations and notes')} right={<Value text={langName(user.content_locale)} />} onPress={() => setPicker('content')} />
        <Row title={t('profile.dark_mode', 'Dark mode')} subtitle={t('profile.dark_sub', 'Easier on the eyes at night')} right={<Switch value={themeMode === 'dark'} onChange={toggleDark} label={t('profile.dark_mode', 'Dark mode')} />} />
        {!user.is_guest ? (
          <>
            <Row
              title={t('notif.settings_short', 'Notifications')}
              subtitle={user.daily_reminder ? t('profile.reminder_at', 'Every day at {time}', { time: String(user.reminder_time).slice(0, 5) }) : t('nset.row_sub', 'Reminders, exams, offers')}
              onPress={() => router.push('/notification-settings' as never)}
            />
            <Row title={t('widget.title', 'Home screen widget')} subtitle={t('widget.row_sub', 'Your streak and exam countdown at a glance')} onPress={() => router.push('/widget' as never)} />
          </>
        ) : null}
      </Card>

      {!user.is_guest ? (
        <Card>
          <Row
            first
            title={t('profile.study_plan', 'Study plan')}
            right={<Value text={plan.data?.plan ? t('profile.plan_day', 'Day {day} of {total}', { day: plan.data.plan.day, total: plan.data.plan.total_days }) : t('profile.plan_none', 'Set up')} />}
            onPress={() => router.push('/study-plan')}
          />
          <Row title={t('profile.invite', 'Invite friends')} right={<Value text={t('profile.invite_sub', 'You both get 1 month')} />} onPress={() => router.push('/invite')} />
          <Row title={t('offline.title', 'Offline downloads')} subtitle={t('profile.offline_sub', 'Study without internet')} onPress={() => router.push('/downloads')} />
          <Row title={t('profile.devices', 'Devices')} right={<Value text={deviceCount} />} onPress={() => router.push('/profile/devices')} />
          <Row title={t('profile.help', 'Help & support')} right={<Value text="Telegram" />} onPress={() => config.data && Linking.openURL(config.data.support.telegram)} />
          <Row title={t('profile.privacy', 'Privacy policy')} onPress={() => config.data && WebBrowser.openBrowserAsync(config.data.legal.privacy_url)} />
          <Row title={t('profile.terms', 'Terms of use')} onPress={() => config.data && WebBrowser.openBrowserAsync(config.data.legal.terms_url)} />
        </Card>
      ) : null}

      <View style={{ flexDirection: 'row', gap: 10 }}>
        <Button title={t('profile.logout', 'Log out')} variant="light" style={{ flex: 1 }} onPress={logOut} />
        {!user.is_guest ? <Button title={t('profile.delete', 'Delete account')} variant="danger" style={{ flex: 1 }} onPress={deleteAccount} /> : null}
      </View>

      <PickerSheet
        visible={picker === 'app' || picker === 'content'}
        title={picker === 'app' ? t('profile.app_language', 'App language') : t('profile.content_language', 'Content language')}
        options={languages.map((l) => ({ id: l.code, label: l.native_name, sub: l.name }))}
        value={picker === 'app' ? user.app_locale : user.content_locale}
        onPick={(code) => (picker === 'app' ? setAppLanguage(code) : setContentLanguage(code))}
        onClose={() => setPicker(null)}
      />
      <PickerSheet
        visible={picker === 'time'}
        title={t('profile.reminder_time', 'Reminder time')}
        options={hours.map((h) => ({ id: h, label: h }))}
        value={user.reminder_time}
        onPick={(time) => update.mutate({ reminder_time: time })}
        onClose={() => setPicker(null)}
      />
    </Screen>
  );
}

function Value({ text }: { text: string }) {
  const { c } = useTheme();
  return (
    <Pressable style={{ flexDirection: 'row', alignItems: 'center', gap: 4 }} pointerEvents="none">
      <Text variant="small" muted>
        {text}
      </Text>
      <Icon name="chevronRight" size={16} color={c.muted} />
    </Pressable>
  );
}
