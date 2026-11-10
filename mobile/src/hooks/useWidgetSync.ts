import { useQueryClient } from '@tanstack/react-query';
import { useEffect, useRef } from 'react';
import { qk } from '../api/hooks';
import type { Home } from '../api/types';
import { setBadge } from '../lib/notifications';
import { clearWidget, updateWidget, widgetPropsFrom } from '../lib/widget';
import { useAuth } from '../store/auth';

/**
 * Keeps the home screen widget and the app-icon badge in step with the latest
 * Home data — whenever it is fetched (app start, after a quiz, after an offline sync).
 */
export function useWidgetSync() {
  const qc = useQueryClient();
  const token = useAuth((s) => s.token);
  const last = useRef('');

  useEffect(() => {
    if (!token) {
      clearWidget();
      void setBadge(0);
      return;
    }
    const apply = (home: Home | undefined) => {
      if (!home) return;
      const props = widgetPropsFrom(home);
      const key = JSON.stringify(props) + home.unread_notifications;
      if (key === last.current) return;
      last.current = key;
      updateWidget(props);
      void setBadge(home.unread_notifications);
    };
    apply(qc.getQueryData<Home>(qk.home));
    return qc.getQueryCache().subscribe((e) => {
      if (e.type === 'updated' && e.query.queryKey[0] === qk.home[0]) apply(e.query.state.data as Home | undefined);
    });
  }, [qc, token]);
}
