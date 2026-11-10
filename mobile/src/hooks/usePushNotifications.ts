import { useQueryClient } from '@tanstack/react-query';
import Constants from 'expo-constants';
import * as Device from 'expo-device';
import { useRouter } from 'expo-router';
import { useEffect } from 'react';
import { api } from '../api/client';
import { qk } from '../api/hooks';
import { notifications, setupChannels } from '../lib/notifications';
import { routeForNotification } from '../lib/notificationRoutes';
import { useAuth } from '../store/auth';
import { useToast } from '../store/toast';

/**
 * Registers this phone for push notifications once the student is signed in,
 * shows a designed in-app banner when one arrives while the app is open,
 * and opens the right screen when a notification is tapped.
 */
export function usePushNotifications() {
  const router = useRouter();
  const qc = useQueryClient();
  const token = useAuth((s) => s.token);
  const isGuest = useAuth((s) => s.user?.is_guest);

  useEffect(() => {
    const N = notifications();
    // While the app is open we show our own banner instead of the system one.
    N?.setNotificationHandler({
      handleNotification: async () => ({ shouldShowBanner: false, shouldShowList: true, shouldPlaySound: false, shouldSetBadge: true }),
    });
    void setupChannels();
  }, []);

  useEffect(() => {
    const N = notifications();
    if (!N || !token || isGuest) return;
    (async () => {
      try {
        if (!Device.isDevice) return;
        const current = await N.getPermissionsAsync();
        // Ask once, after sign-in; later the settings screen explains how to turn it on.
        const status = current.granted || !current.canAskAgain ? current : await N.requestPermissionsAsync();
        if (!status.granted) return;
        const projectId = Constants.expoConfig?.extra?.eas?.projectId ?? Constants.easConfig?.projectId;
        if (!projectId) return; // set by `eas init`
        const push = await N.getExpoPushTokenAsync({ projectId });
        await api('/me/push-token', { method: 'PUT', body: { push_token: push.data } });
      } catch {
        // Notifications are optional — never block the app.
      }
    })();
  }, [token, isGuest]);

  useEffect(() => {
    const N = notifications();
    if (!N) return;
    const received = N.addNotificationReceivedListener((n) => {
      const data = n.request.content.data as { route?: string; params?: Record<string, unknown>; category?: string; type?: string };
      qc.invalidateQueries({ queryKey: qk.notifications });
      qc.invalidateQueries({ queryKey: qk.home });
      useToast.getState().show({
        id: n.request.identifier,
        title: n.request.content.title ?? '',
        body: n.request.content.body ?? undefined,
        category: data?.category,
        type: data?.type,
        href: routeForNotification(data?.route, data?.params),
      });
    });
    const tapped = N.addNotificationResponseReceivedListener((response) => {
      const data = response.notification.request.content.data as { route?: string; params?: Record<string, unknown> };
      const href = routeForNotification(data?.route, data?.params);
      if (href) router.push(href as never);
    });
    return () => {
      received.remove();
      tapped.remove();
    };
  }, [router, qc]);
}
