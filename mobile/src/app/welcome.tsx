import { useRouter } from 'expo-router';
import * as Updates from 'expo-updates';
import { useEffect, useState } from 'react';
import { Alert, Pressable, ScrollView, useWindowDimensions, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useTranslation } from 'react-i18next';
import { useConfig } from '../api/hooks';
import type { Language } from '../api/types';
import { Brand, EcgLine } from '../components/Brand';
import { Button } from '../components/Button';
import { Icon } from '../components/Icon';
import { Loading } from '../components/Screen';
import { Text } from '../components/Text';
import { applyLanguage, needsDirectionChange } from '../i18n';
import { useAuth } from '../store/auth';
import { useSettings } from '../store/settings';
import { fonts, useTheme } from '../theme';

/** 01 · Welcome & language. Languages come from Admin → Languages (only live ones). */
export default function Welcome() {
  const { t, i18n } = useTranslation();
  const { c } = useTheme();
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const { width } = useWindowDimensions();
  const config = useConfig();
  const setAppLocale = useSettings((s) => s.setAppLocale);
  const signedIn = useAuth((s) => !!s.token);
  const [picked, setPicked] = useState(i18n.language || 'en');

  const languages: Language[] = config.data?.languages ?? [{ code: 'en', name: 'English', native_name: 'English', direction: 'ltr', ui_version: '' }];

  // Preview the screen in the language being chosen.
  useEffect(() => {
    const lang = languages.find((l) => l.code === picked);
    void applyLanguage(picked, lang?.ui_version);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [picked]);

  const proceed = async () => {
    setAppLocale(picked);
    const lang = languages.find((l) => l.code === picked);
    if (lang && needsDirectionChange(lang.direction)) {
      Alert.alert(t('welcome.restart_title', 'Restarting'), t('welcome.restart_body', 'MedAlbumin will restart to show this language right to left.'), [
        { text: t('common.ok', 'OK'), onPress: () => Updates.reloadAsync().catch(() => {}) },
      ]);
      return;
    }
    router.replace(signedIn ? '/(tabs)' : '/sign-in');
  };

  const dir = languages.find((l) => l.code === picked)?.direction ?? 'ltr';

  return (
    <View style={{ flex: 1, backgroundColor: c.navy }}>
      <EcgLine width={width} />
      <View style={{ paddingTop: insets.top + 24, paddingHorizontal: 28, minHeight: 330, justifyContent: 'space-between', paddingBottom: 22 }}>
        <Brand size={56} />
        <Text style={{ fontFamily: fonts.display, fontSize: 38, lineHeight: 41, letterSpacing: -1.1, writingDirection: dir }} color="#F3F6FA">
          {t('welcome.title', 'Pass your medical exams with confidence.')}
        </Text>
      </View>

      <View style={{ flex: 1, backgroundColor: c.page, borderTopLeftRadius: 32, borderTopRightRadius: 32, paddingTop: 26, paddingHorizontal: 22, paddingBottom: insets.bottom + 18, gap: 16 }}>
        <Text style={{ fontSize: 15, lineHeight: 22, writingDirection: dir }} color={c.textSoft}>
          {t('welcome.subtitle', 'Past papers, mock exams, notes and flashcards — in one calm, focused place.')}
        </Text>
        <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'baseline' }}>
          <Text variant="label" muted>
            {t('welcome.app_language', 'App language')}
          </Text>
          <Text variant="caption" muted>
            {t('welcome.change_anytime', 'Change anytime')}
          </Text>
        </View>

        {config.isLoading && !config.data ? (
          <Loading />
        ) : (
          <ScrollView style={{ flexGrow: 0 }} contentContainerStyle={{ gap: 8 }} showsVerticalScrollIndicator={false}>
            {languages.map((l) => {
              const on = l.code === picked;
              return (
                <Pressable
                  key={l.code}
                  accessibilityRole="radio"
                  accessibilityState={{ checked: on }}
                  accessibilityLabel={`${l.native_name} (${l.name})`}
                  onPress={() => setPicked(l.code)}
                  style={{
                    minHeight: 56,
                    borderRadius: 14,
                    paddingVertical: 8,
                    paddingHorizontal: 16,
                    backgroundColor: c.card,
                    borderWidth: 1.5,
                    borderColor: on ? c.text : c.line,
                    flexDirection: 'row',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                  }}
                >
                  <View style={{ gap: 1 }}>
                    <Text style={{ fontFamily: fonts.bodySemibold, fontSize: 16 }}>{l.native_name}</Text>
                    <Text variant="caption" muted>
                      {l.name}
                    </Text>
                  </View>
                  <View style={{ width: 20, height: 20, borderRadius: 10, borderWidth: on ? 6 : 1.5, borderColor: on ? c.navy : c.fieldStrong, backgroundColor: on ? c.accent : 'transparent' }} />
                </Pressable>
              );
            })}
          </ScrollView>
        )}

        <View style={{ flex: 1 }} />
        <Button title={t('welcome.continue', 'Continue')} onPress={proceed} trailing={<Icon name="arrowRight" size={18} color={c.onNavy} strokeWidth={2} />} />
      </View>
    </View>
  );
}
