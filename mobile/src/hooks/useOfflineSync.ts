import { useQueryClient } from '@tanstack/react-query';
import { useEffect } from 'react';
import { AppState } from 'react-native';
import { qk } from '../api/hooks';
import { useOnline } from '../lib/network';
import { syncAttempts } from '../offline/store';
import { useAuth } from '../store/auth';

/** Uploads answers given offline whenever the phone is online: at start, on reconnect and on returning to the app. */
export function useOfflineSync() {
  const online = useOnline();
  const token = useAuth((s) => s.token);
  const qc = useQueryClient();

  useEffect(() => {
    if (!online || !token) return;
    const run = () =>
      syncAttempts().then((n) => {
        if (n > 0) {
          qc.invalidateQueries({ queryKey: qk.home });
          qc.invalidateQueries({ queryKey: ['progress'] });
        }
      });
    void run();
    const sub = AppState.addEventListener('change', (s) => s === 'active' && void run());
    return () => sub.remove();
  }, [online, token, qc]);
}
