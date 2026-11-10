import { Geist_400Regular } from '@expo-google-fonts/geist/400Regular';
import { Geist_500Medium } from '@expo-google-fonts/geist/500Medium';
import { Geist_600SemiBold } from '@expo-google-fonts/geist/600SemiBold';
import { GeistMono_500Medium } from '@expo-google-fonts/geist-mono/500Medium';
import { Outfit_500Medium } from '@expo-google-fonts/outfit/500Medium';
import { Outfit_600SemiBold } from '@expo-google-fonts/outfit/600SemiBold';
import { Outfit_700Bold } from '@expo-google-fonts/outfit/700Bold';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { createAsyncStoragePersister } from '@tanstack/query-async-storage-persister';
import { QueryClient } from '@tanstack/react-query';
import { PersistQueryClientProvider } from '@tanstack/react-query-persist-client';
import { useFonts } from 'expo-font';
import { DarkTheme, DefaultTheme, Stack, ThemeProvider as NavigationThemeProvider } from 'expo-router';
import * as SplashScreen from 'expo-splash-screen';
import { StatusBar } from 'expo-status-bar';
import * as SystemUI from 'expo-system-ui';
import { useEffect, useMemo, useState } from 'react';
import { View } from 'react-native';
import { GestureHandlerRootView } from 'react-native-gesture-handler';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { ApiError } from '../api/client';
import { NotificationToast } from '../components/NotificationToast';
import { useBootstrap } from '../hooks/useBootstrap';
import { useOfflineSync } from '../hooks/useOfflineSync';
import { usePushNotifications } from '../hooks/usePushNotifications';
import { useWidgetSync } from '../hooks/useWidgetSync';
import '../lib/network';
import '../i18n';
import { ThemeProvider, useTheme } from '../theme';

SplashScreen.preventAutoHideAsync().catch(() => {});

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 60_000,
      gcTime: 30 * 24 * 3600_000, // keep everything opened for offline use
      retry: (count, error) => !(error instanceof ApiError && ['premium', 'not_found', 'unauthenticated', 'validation', 'locked'].includes(error.kind)) && count < 2,
      networkMode: 'offlineFirst',
    },
    mutations: { networkMode: 'offlineFirst' },
  },
});

// Everything the student has opened stays readable offline (notes, papers, progress…).
const persister = createAsyncStoragePersister({ storage: AsyncStorage, key: 'medalbumin.cache', throttleTime: 2000 });

function AppStack() {
  const { c, dark } = useTheme();
  usePushNotifications();
  useOfflineSync();
  useWidgetSync();

  // The window, the navigator and every screen share the page colour, so no white shows during transitions in dark mode.
  useEffect(() => {
    SystemUI.setBackgroundColorAsync(c.page).catch(() => {});
  }, [c.page]);
  const navTheme = useMemo(() => {
    const base = dark ? DarkTheme : DefaultTheme;
    return { ...base, dark, colors: { ...base.colors, background: c.page, card: c.page, text: c.text, border: c.line, primary: c.teal } };
  }, [dark, c]);

  return (
    <NavigationThemeProvider value={navTheme}>
      <StatusBar style={dark ? 'light' : 'dark'} />
      <View style={{ flex: 1, backgroundColor: c.page }}>
      <Stack screenOptions={{ headerShown: false, contentStyle: { backgroundColor: c.page }, animation: 'slide_from_right' }}>
        <Stack.Screen name="index" options={{ animation: 'none' }} />
        <Stack.Screen name="welcome" options={{ animation: 'fade' }} />
        <Stack.Screen name="sign-in" options={{ animation: 'fade' }} />
        <Stack.Screen name="(tabs)" options={{ animation: 'fade' }} />
        <Stack.Screen name="quiz/[id]" options={{ gestureEnabled: false }} />
        <Stack.Screen name="result/[id]" options={{ animation: 'fade_from_bottom', gestureEnabled: false }} />
      </Stack>
      <NotificationToast />
      </View>
    </NavigationThemeProvider>
  );
}

export default function RootLayout() {
  const [fontsLoaded] = useFonts({
    Outfit_500Medium,
    Outfit_600SemiBold,
    Outfit_700Bold,
    Geist_400Regular,
    Geist_500Medium,
    Geist_600SemiBold,
    GeistMono_500Medium,
  });
  const ready = useBootstrap(queryClient);
  const [hidden, setHidden] = useState(false);

  useEffect(() => {
    if (fontsLoaded && ready && !hidden) {
      SplashScreen.hideAsync().catch(() => {});
      setHidden(true);
    }
  }, [fontsLoaded, ready, hidden]);

  if (!fontsLoaded || !ready) return null;

  return (
    <GestureHandlerRootView style={{ flex: 1 }}>
      <SafeAreaProvider>
        <PersistQueryClientProvider client={queryClient} persistOptions={{ persister, maxAge: 30 * 24 * 3600_000, buster: 'v1' }}>
          <ThemeProvider>
            <AppStack />
          </ThemeProvider>
        </PersistQueryClientProvider>
      </SafeAreaProvider>
    </GestureHandlerRootView>
  );
}
