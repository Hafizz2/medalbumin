import Constants from 'expo-constants';
import { getToken, useAuth } from '../store/auth';

/**
 * Backend address. Set EXPO_PUBLIC_API_URL in mobile/.env, e.g.
 *   EXPO_PUBLIC_API_URL=http://192.168.168.87:8000   (your PC on the same Wi-Fi)
 *   EXPO_PUBLIC_API_URL=https://medalbumin.com         (production)
 */
export const API_URL: string = (process.env.EXPO_PUBLIC_API_URL || (Constants.expoConfig?.extra?.apiUrl as string) || 'https://medalbumin.com').replace(/\/$/, '');

export type ApiErrorKind = 'validation' | 'unauthenticated' | 'premium' | 'device_limit' | 'suspended' | 'not_found' | 'locked' | 'offline' | 'server';

export class ApiError extends Error {
  constructor(
    message: string,
    public kind: ApiErrorKind,
    public status: number,
    public data: Record<string, any> = {},
  ) {
    super(message);
  }

  /** First validation message for a field, e.g. err.field('phone'). */
  field(name: string): string | undefined {
    return this.data?.errors?.[name]?.[0];
  }
}

let contentLocale: string | null = null;
/** Content language to request (follows the app language until the student picks another). */
export const setContentLocaleHeader = (code: string | null) => {
  contentLocale = code;
};

type Options = { method?: string; body?: unknown; form?: FormData; signal?: AbortSignal; headers?: Record<string, string> };

export async function api<T = any>(path: string, opts: Options = {}): Promise<T> {
  const token = getToken();
  const headers: Record<string, string> = { Accept: 'application/json', ...opts.headers };
  if (token) headers.Authorization = `Bearer ${token}`;
  if (contentLocale) headers['X-Content-Locale'] = contentLocale;
  if (opts.body !== undefined) headers['Content-Type'] = 'application/json';

  let res: Response;
  try {
    res = await fetch(`${API_URL}/api/v1${path}`, {
      method: opts.method ?? (opts.body !== undefined || opts.form ? 'POST' : 'GET'),
      headers,
      body: opts.form ?? (opts.body !== undefined ? JSON.stringify(opts.body) : undefined),
      signal: opts.signal,
    });
  } catch {
    throw new ApiError('You are offline. Check your connection and try again.', 'offline', 0);
  }

  if (res.status === 204 || res.status === 304) return undefined as T;

  let data: any = {};
  try {
    data = await res.json();
  } catch {
    data = {};
  }

  if (res.ok) return data as T;

  const message: string = data?.message || 'Something went wrong. Please try again.';
  switch (true) {
    case res.status === 401:
      // Token revoked (signed out from another device) — return to sign-in.
      if (token) void useAuth.getState().signOut();
      throw new ApiError('Please sign in again.', 'unauthenticated', 401, data);
    case res.status === 409 && data?.code === 'DEVICE_LIMIT':
      throw new ApiError(message, 'device_limit', 409, data);
    case res.status === 403 && data?.code === 'ACCOUNT_SUSPENDED':
      void useAuth.getState().signOut();
      throw new ApiError(message, 'suspended', 403, data);
    case res.status === 402:
      throw new ApiError(message, 'premium', 402, data);
    case res.status === 422:
      throw new ApiError(firstError(data) ?? message, 'validation', 422, data);
    case res.status === 423:
      throw new ApiError(message, 'locked', 423, data);
    case res.status === 404:
      throw new ApiError(message, 'not_found', 404, data);
    default:
      throw new ApiError(message, 'server', res.status, data);
  }
}

function firstError(data: any): string | undefined {
  const errors = data?.errors;
  if (!errors) return undefined;
  const first = Object.values(errors)[0];
  return Array.isArray(first) ? (first[0] as string) : undefined;
}
