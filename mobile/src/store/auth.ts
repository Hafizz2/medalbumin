import AsyncStorage from '@react-native-async-storage/async-storage';
import * as SecureStore from 'expo-secure-store';
import { create } from 'zustand';
import type { User } from '../api/types';

const TOKEN_KEY = 'medalbumin.token';
// Last known profile, so the app opens signed-in with no connection.
const USER_KEY = 'medalbumin.user';
const saveUser = (user: User | null) => void (user ? AsyncStorage.setItem(USER_KEY, JSON.stringify(user)) : AsyncStorage.removeItem(USER_KEY)).catch(() => {});

type AuthState = {
  token: string | null;
  user: User | null;
  /** True once the stored token has been read at launch. */
  ready: boolean;
  load: () => Promise<void>;
  signIn: (token: string, user: User) => Promise<void>;
  setUser: (user: User) => void;
  signOut: () => Promise<void>;
};

/** The bearer token lives in the iOS Keychain / Android Keystore, never in plain storage. */
export const useAuth = create<AuthState>((set) => ({
  token: null,
  user: null,
  ready: false,
  load: async () => {
    const token = await SecureStore.getItemAsync(TOKEN_KEY).catch(() => null);
    const raw = token ? await AsyncStorage.getItem(USER_KEY).catch(() => null) : null;
    set({ token, user: raw ? (JSON.parse(raw) as User) : null, ready: true });
  },
  signIn: async (token, user) => {
    await SecureStore.setItemAsync(TOKEN_KEY, token);
    saveUser(user);
    set({ token, user });
  },
  setUser: (user) => {
    saveUser(user);
    set({ user });
  },
  signOut: async () => {
    await SecureStore.deleteItemAsync(TOKEN_KEY).catch(() => {});
    saveUser(null);
    set({ token: null, user: null });
  },
}));

export const getToken = () => useAuth.getState().token;
