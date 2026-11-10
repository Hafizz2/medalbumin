import { QueryClient } from '@tanstack/react-query';
import NetInfo from '@react-native-community/netinfo';
import * as Localization from 'expo-localization';
import { useEffect, useState } from 'react';
import { api, setContentLocaleHeader } from '../api/client';
import { qk } from '../api/hooks';
import type { AppConfig, User } from '../api/types';
import { applyLanguage } from '../i18n';
import { useAuth } from '../store/auth';
import { useSettings } from '../store/settings';

/**
 * Runs once at launch, before the splash screen hides:
 * reads the saved token, loads the profile and the app language.
 * Never blocks on the network — cached data is used when offline.
 */
export function useBootstrap(queryClient: QueryClient) {
  const [ready, setReady] = useState(false);

  useEffect(() => {
    let cancelled = false;
    (async () => {
      await useAuth.getState().load();
      await waitForSettings();

      const settings = useSettings.getState();
      // Offline (or a dead connection): open straight away from what is saved on the phone.
      const net = await NetInfo.fetch().catch(() => null);
      const online = net?.isConnected !== false && net?.isInternetReachable !== false;
      const quick = <T,>(path: string) => withTimeout(api<T>(path), 6000);

      const config = online ? await quick<AppConfig>('/config').catch(() => null) : null;
      if (config) queryClient.setQueryData(qk.config, config);

      // Language: chosen one, else the phone's if MedAlbumin offers it, else English.
      const live = config?.languages ?? [];
      const phone = Localization.getLocales()[0]?.languageCode ?? 'en';
      const code = settings.appLocale ?? (live.some((l) => l.code === phone) ? phone : 'en');
      await applyLanguage(code, live.find((l) => l.code === code)?.ui_version).catch(() => {});

      if (useAuth.getState().token && online) {
        try {
          const user = await quick<User>('/me');
          useAuth.getState().setUser(user);
          queryClient.setQueryData(qk.me, user);
          setContentLocaleHeader(user.content_locale);
          if (user.dark_mode && settings.themeMode === 'light') useSettings.getState().setThemeMode('dark');
        } catch {
          // Offline: keep the session with the saved profile (a 401 already signed the student out).
        }
      }
      const saved = useAuth.getState().user;
      if (saved) setContentLocaleHeader(saved.content_locale);
      if (!cancelled) setReady(true);
    })();

    return () => {
      cancelled = true;
    };
  }, [queryClient]);

  return ready;
}

function withTimeout<T>(p: Promise<T>, ms: number): Promise<T> {
  return new Promise((resolve, reject) => {
    const id = setTimeout(() => reject(new Error('timeout')), ms);
    p.then(
      (v) => (clearTimeout(id), resolve(v)),
      (e) => (clearTimeout(id), reject(e)),
    );
  });
}

/** zustand's persisted settings load asynchronously from AsyncStorage. */
function waitForSettings(): Promise<void> {
  const p = useSettings.persist;
  if (p.hasHydrated()) return Promise.resolve();
  return new Promise((resolve) => {
    const unsub = p.onFinishHydration(() => {
      unsub();
      resolve();
    });
  });
}
