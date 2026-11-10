import * as Application from 'expo-application';
import * as Crypto from 'expo-crypto';
import * as Device from 'expo-device';
import * as SecureStore from 'expo-secure-store';
import { Platform } from 'react-native';

const KEY = 'medalbumin.device_id';

/**
 * Stable id for this install, used for the 2-device limit.
 * Stored in the keychain so it survives app updates.
 */
export async function deviceId(): Promise<string> {
  const saved = await SecureStore.getItemAsync(KEY).catch(() => null);
  if (saved) return saved;

  let id: string | null = null;
  try {
    id = Platform.OS === 'android' ? Application.getAndroidId() : await Application.getIosIdForVendorAsync();
  } catch {
    id = null;
  }
  id = `${Platform.OS}-${id || Crypto.randomUUID()}`;
  await SecureStore.setItemAsync(KEY, id).catch(() => {});
  return id;
}

/** Device fields every sign-in request sends. */
export async function deviceInfo() {
  return {
    device_id: await deviceId(),
    device_name: Device.deviceName ?? Device.modelName ?? undefined,
    platform: Platform.OS === 'ios' ? 'ios' : 'android',
    app_version: Application.nativeApplicationVersion ?? undefined,
  };
}
