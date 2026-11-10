import i18n from '../i18n';

export const fmtNumber = (n: number) => new Intl.NumberFormat(i18n.language === 'am' ? 'en' : i18n.language).format(n);

/** 6300 → "1 h 45 m", 300 → "5 m" */
export function fmtMinutes(min: number): string {
  const h = Math.floor(min / 60);
  const m = Math.round(min % 60);
  return h ? `${h} h${m ? ` ${m} m` : ''}` : `${m} m`;
}

/** Seconds → "3h 12m" or "12:05" (clock style for timers). */
export function fmtDuration(sec: number, clock = false): string {
  const s = Math.max(0, Math.floor(sec));
  const h = Math.floor(s / 3600);
  const m = Math.floor((s % 3600) / 60);
  const r = s % 60;
  if (clock) return h ? `${h}:${String(m).padStart(2, '0')}:${String(r).padStart(2, '0')}` : `${m}:${String(r).padStart(2, '0')}`;
  return h ? `${h}h ${String(m).padStart(2, '0')}m` : `${m}m`;
}

export function fmtDate(iso: string | null | undefined, opts: Intl.DateTimeFormatOptions = { day: 'numeric', month: 'short', year: 'numeric' }): string {
  if (!iso) return '';
  try {
    return new Date(iso).toLocaleDateString(undefined, opts);
  } catch {
    return iso.slice(0, 10);
  }
}

/** "5 min", "2 h", "Yesterday", "Oct 9" */
export function fmtAgo(iso: string): string {
  const diff = (Date.now() - new Date(iso).getTime()) / 1000;
  if (diff < 60) return i18n.t('time.now', 'now');
  if (diff < 3600) return i18n.t('time.minutes', '{n} min', { n: Math.floor(diff / 60) });
  if (diff < 86400) return i18n.t('time.hours', '{n} h', { n: Math.floor(diff / 3600) });
  if (diff < 172800) return i18n.t('time.yesterday', 'Yesterday');
  if (diff < 7 * 86400) return new Date(iso).toLocaleDateString(undefined, { weekday: 'short' });
  return new Date(iso).toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
}
