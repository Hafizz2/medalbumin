import AsyncStorage from '@react-native-async-storage/async-storage';
import { create } from 'zustand';
import { createJSONStorage, persist } from 'zustand/middleware';

type ThemeMode = 'light' | 'dark' | 'system';

type Settings = {
  /** Chosen on the welcome screen; null until the student picks one. */
  appLocale: string | null;
  themeMode: ThemeMode;
  /** "Aa" toggle on the question screen. */
  largeText: boolean;
  setAppLocale: (code: string) => void;
  setThemeMode: (mode: ThemeMode) => void;
  setLargeText: (on: boolean) => void;
};

export const useSettings = create<Settings>()(
  persist(
    (set) => ({
      appLocale: null,
      themeMode: 'light',
      largeText: false,
      setAppLocale: (appLocale) => set({ appLocale }),
      setThemeMode: (themeMode) => set({ themeMode }),
      setLargeText: (largeText) => set({ largeText }),
    }),
    { name: 'medalbumin.settings', storage: createJSONStorage(() => AsyncStorage) },
  ),
);
