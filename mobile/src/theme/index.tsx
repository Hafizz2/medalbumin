import { createContext, ReactNode, useContext, useMemo } from 'react';
import { useColorScheme } from 'react-native';
import { dark, light, Palette } from './colors';
import { useSettings } from '../store/settings';

export const fonts = {
  display: 'Outfit_600SemiBold',
  displayBold: 'Outfit_700Bold',
  displayMedium: 'Outfit_500Medium',
  body: 'Geist_400Regular',
  bodyMedium: 'Geist_500Medium',
  bodySemibold: 'Geist_600SemiBold',
  mono: 'GeistMono_500Medium',
};

export const radius = { sm: 10, md: 14, lg: 16, xl: 18, card: 22, sheet: 28, pill: 99 };
/** Bottom space on tab screens so content clears the floating tab bar. */
export const TAB_BAR_SPACE = 110;
export const space = { xs: 4, sm: 8, md: 12, lg: 16, xl: 20, xxl: 28 };

type Theme = { c: Palette; dark: boolean };

const ThemeContext = createContext<Theme>({ c: light, dark: false });

/** Dark mode follows the Profile switch; "system" follows the phone. */
export function ThemeProvider({ children }: { children: ReactNode }) {
  const system = useColorScheme();
  const mode = useSettings((s) => s.themeMode);
  const isDark = mode === 'dark' || (mode === 'system' && system === 'dark');
  const value = useMemo(() => ({ c: isDark ? dark : light, dark: isDark }), [isDark]);

  return <ThemeContext.Provider value={value}>{children}</ThemeContext.Provider>;
}

export const useTheme = () => useContext(ThemeContext);

export type { Palette };
