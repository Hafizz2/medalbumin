import { isRunningInExpoGo } from 'expo';
import { Platform } from 'react-native';

type NotificationsModule = typeof import('expo-notifications');

/**
 * Expo Go on Android ships without the push native modules, and importing
 * expo-notifications there crashes. Everything notification-related goes through
 * this file, which loads the library only where it works: development builds,
 * store builds, and Expo Go on iOS.
 */
export const notificationsSupported = !(Platform.OS === 'android' && isRunningInExpoGo());

let cached: NotificationsModule | null | undefined;
export function notifications(): NotificationsModule | null {
  if (cached !== undefined) return cached;
  try {
    cached = notificationsSupported ? (require('expo-notifications') as NotificationsModule) : null;
  } catch {
    cached = null;
  }
  return cached;
}

/** Android channels — one per category, so students can also mute each one in system settings. Names match the backend. */
const CHANNELS: { id: string; name: string; description: string; importance: 'high' | 'default' | 'low' }[] = [
  { id: 'reminders', name: 'Study reminders', description: 'Daily reminder, streak, exam countdown, flashcards due', importance: 'high' },
  { id: 'exams', name: 'Exams & mocks', description: 'New grand mocks, reminders and results', importance: 'high' },
  { id: 'community', name: 'Discussions', description: 'Replies and faculty answers to your doubts', importance: 'default' },
  { id: 'news', name: 'News & offers', description: 'New papers, announcements and promotions', importance: 'default' },
  { id: 'account', name: 'Account & payments', description: 'Payments, plan status and security', importance: 'high' },
  { id: 'default', name: 'General', description: 'Other notifications', importance: 'default' },
];

export async function setupChannels(): Promise<void> {
  const N = notifications();
  if (!N || Platform.OS !== 'android') return;
  const level = { high: N.AndroidImportance.HIGH, default: N.AndroidImportance.DEFAULT, low: N.AndroidImportance.LOW };
  await Promise.all(
    CHANNELS.map((ch) =>
      N.setNotificationChannelAsync(ch.id, { name: ch.name, description: ch.description, importance: level[ch.importance], lightColor: '#14BCC4', vibrationPattern: [0, 200, 120, 200] }).catch(() => null),
    ),
  );
}

export type PermissionState = 'granted' | 'denied' | 'undetermined' | 'unavailable';

export async function permissionState(): Promise<PermissionState> {
  const N = notifications();
  if (!N) return 'unavailable';
  const p = await N.getPermissionsAsync().catch(() => null);
  if (!p) return 'unavailable';
  return p.granted ? 'granted' : p.canAskAgain ? 'undetermined' : 'denied';
}

export async function requestPermission(): Promise<PermissionState> {
  const N = notifications();
  if (!N) return 'unavailable';
  const p = await N.requestPermissionsAsync().catch(() => null);
  return p?.granted ? 'granted' : p?.canAskAgain === false ? 'denied' : 'undetermined';
}

export async function setBadge(count: number): Promise<void> {
  await notifications()?.setBadgeCountAsync(count).catch(() => false);
}
