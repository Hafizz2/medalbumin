import AsyncStorage from '@react-native-async-storage/async-storage';
import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';
import { I18nManager } from 'react-native';
import { api } from '../api/client';
import en from './en.json';

/**
 * App text.
 * - Every string in code is written t('key', 'English text'); `npm run i18n` extracts them into en.json.
 * - en.json is uploaded to Admin → Languages → English; translators work there.
 * - Other languages are downloaded from /api/v1/i18n/{code} and cached on the phone,
 *   so a language added in the admin appears without an app update.
 */
i18n.use(initReactI18next).init({
  resources: { en: { translation: en } },
  lng: 'en',
  fallbackLng: 'en',
  returnNull: false,
  interpolation: { escapeValue: false, prefix: '{', suffix: '}' },
  compatibilityJSON: 'v4',
});

const cacheKey = (code: string) => `medalbumin.i18n.${code}`;

/** Switches language using the cached copy first, then refreshes from the server if the version changed. */
export async function applyLanguage(code: string, version?: string) {
  if (code !== 'en') {
    const cached = await AsyncStorage.getItem(cacheKey(code)).catch(() => null);
    let bundle: { version: string; strings: Record<string, unknown> } | null = cached ? JSON.parse(cached) : null;

    if (!bundle || (version && bundle.version !== version)) {
      try {
        const fresh = await api<{ version: string; strings: Record<string, unknown> }>(`/i18n/${code}`);
        if (fresh?.strings) {
          bundle = { version: fresh.version, strings: fresh.strings };
          await AsyncStorage.setItem(cacheKey(code), JSON.stringify(bundle)).catch(() => {});
        }
      } catch {
        // Offline: keep the cached copy (or English) — never block the app.
      }
    }
    if (bundle) i18n.addResourceBundle(code, 'translation', bundle.strings, true, true);
  }
  await i18n.changeLanguage(code);
}

/**
 * Right-to-left languages need the layout flipped, which React Native applies after a reload.
 * Returns true when a reload is required.
 */
export function needsDirectionChange(direction: 'ltr' | 'rtl'): boolean {
  const rtl = direction === 'rtl';
  if (I18nManager.isRTL === rtl) return false;
  I18nManager.allowRTL(rtl);
  I18nManager.forceRTL(rtl);
  return true;
}

export default i18n;
