import { useQuery } from '@tanstack/react-query';
import { useRouter } from 'expo-router';
import { useState } from 'react';
import { Alert, Pressable, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { api, ApiError } from '../api/client';
import { Button } from '../components/Button';
import { Icon } from '../components/Icon';
import { EmptyState, Header, Screen, Skeleton } from '../components/Screen';
import { Text } from '../components/Text';
import { Card, IconTile } from '../components/ui';
import { fmtDate } from '../lib/format';
import { useOnline } from '../lib/network';
import { downloadPack, PackInfo, pendingCount, removePack, syncAttempts, useOffline } from '../offline/store';
import { useAuth } from '../store/auth';
import { useTheme } from '../theme';

const fmtSize = (kb: number) => (kb >= 1024 ? `${(kb / 1024).toFixed(1)} MB` : `${Math.max(1, Math.round(kb))} KB`);

/** Download departments to study fully offline — questions with explanations, and official notes. */
export default function Downloads() {
  const { t } = useTranslation();
  const { c } = useTheme();
  const router = useRouter();
  const online = useOnline();
  const isGuest = useAuth((s) => s.user?.is_guest ?? false);
  const local = useOffline((s) => s.packs);
  const pending = useOffline(pendingCount);
  const [busy, setBusy] = useState<Record<number, boolean>>({});
  const [syncing, setSyncing] = useState(false);

  const q = useQuery({ queryKey: ['offline-packs'], queryFn: () => api<{ data: PackInfo[] }>('/offline/packs'), enabled: !isGuest });

  const server = q.data?.data ?? [];
  const rows: (PackInfo & { local?: (typeof local)[number] })[] = server.length
    ? server.map((p) => ({ ...p, local: local[p.id] }))
    : Object.values(local).map((l) => ({ id: l.id, name: l.name, questions: l.questions, notes: l.notes, size_kb: l.bytes / 1024, version: l.version, local: l }));
  const usedKb = Object.values(local).reduce((n, p) => n + p.bytes / 1024, 0);

  const download = async (p: PackInfo) => {
    setBusy((b) => ({ ...b, [p.id]: true }));
    try {
      await downloadPack(p);
    } catch (e) {
      Alert.alert(t('offline.download_failed', 'Download failed'), e instanceof ApiError ? e.message : t('common.error_body', 'Please try again.'));
    } finally {
      setBusy((b) => ({ ...b, [p.id]: false }));
    }
  };

  const remove = (p: PackInfo) =>
    Alert.alert(t('offline.remove_title', 'Remove {name}?', { name: p.name }), t('offline.remove_body', 'It will no longer be available offline. Your progress is kept.'), [
      { text: t('common.cancel', 'Cancel'), style: 'cancel' },
      { text: t('offline.remove', 'Remove'), style: 'destructive', onPress: () => void removePack(p.id) },
    ]);

  const syncNow = async () => {
    setSyncing(true);
    await syncAttempts();
    setSyncing(false);
  };

  if (isGuest) {
    return (
      <Screen>
        <Header />
        <EmptyState icon="download" title={t('offline.guest_title', 'Study offline with a free account')} body={t('offline.guest_body', 'Create a free account to download departments and practise without internet.')} action={t('signin.tab_signup', 'Create account')} onAction={() => router.push('/sign-in?mode=signup')} />
      </Screen>
    );
  }

  return (
    <Screen onRefresh={() => q.refetch()}>
      <Header />
      <View style={{ gap: 4 }}>
        <Text variant="title">{t('offline.title', 'Offline downloads')}</Text>
        <Text variant="small" muted>
          {t('offline.intro', 'Download only what you need. Downloaded departments work fully without internet, and your answers sync when you’re back online.')}
        </Text>
      </View>

      <Card tone="navy" style={{ padding: 16, flexDirection: 'row', alignItems: 'center', gap: 14 }}>
        <IconTile icon="download" bg="rgba(255,255,255,0.1)" ink={c.accent} />
        <View style={{ flex: 1, gap: 2 }}>
          <Text variant="smallStrong" color={c.onNavy}>
            {t('offline.storage', '{n} downloaded · {size}', { n: Object.keys(local).length, size: fmtSize(usedKb) })}
          </Text>
          <Text variant="caption" color={c.onNavySoft}>
            {pending ? t('offline.pending', '{n} answers waiting to sync', { n: pending }) : t('offline.all_synced', 'All answers are synced')}
          </Text>
        </View>
        {pending && online ? <Button title={t('offline.sync', 'Sync')} size="sm" variant="accent" loading={syncing} onPress={syncNow} /> : null}
      </Card>

      {!rows.length && q.isLoading ? (
        <Skeleton height={300} />
      ) : !rows.length ? (
        <EmptyState icon="download" title={online ? t('offline.none', 'Nothing to download yet') : t('offline.none_offline', 'No downloads yet')} body={online ? undefined : t('offline.connect_first', 'Connect to the internet once to download departments.')} />
      ) : (
        <Card>
          {rows.map((p, i) => {
            const have = !!p.local;
            const outdated = have && p.local!.version !== p.version && server.length > 0;
            return (
              <View key={p.id} style={{ padding: 14, gap: 10, borderTopWidth: i ? 1 : 0, borderTopColor: c.divider }}>
                <Pressable disabled={!have} onPress={() => router.push(`/offline/${p.id}` as never)} accessibilityRole="button" style={{ flexDirection: 'row', alignItems: 'center', gap: 12 }}>
                  <IconTile icon={have ? 'check' : 'book'} bg={have ? c.tealSoft : c.chip} ink={have ? c.teal : c.textSoft} />
                  <View style={{ flex: 1, gap: 2 }}>
                    <Text variant="bodyStrong">{p.name}</Text>
                    <Text variant="caption" muted>
                      {t('offline.pack_meta', '{q} questions · {n} notes · {size}', { q: p.questions, n: p.notes, size: fmtSize(p.size_kb) })}
                    </Text>
                    {have ? (
                      <Text variant="caption" color={outdated ? c.warn : c.teal}>
                        {outdated ? t('offline.update_available', 'Update available') : t('offline.saved_on', 'Saved {date}', { date: fmtDate(p.local!.downloaded_at, { day: 'numeric', month: 'short' }) })}
                      </Text>
                    ) : null}
                  </View>
                  {have ? <Icon name="chevronRight" size={16} color={c.muted} /> : null}
                </Pressable>
                <View style={{ flexDirection: 'row', gap: 8 }}>
                  {!have || outdated ? (
                    <Button
                      title={outdated ? t('offline.update', 'Update') : t('offline.download', 'Download')}
                      icon="download"
                      size="sm"
                      variant={outdated ? 'soft' : 'primary'}
                      disabled={!online}
                      loading={!!busy[p.id]}
                      onPress={() => download(p)}
                      style={{ flex: 1 }}
                    />
                  ) : (
                    <Button title={t('offline.open', 'Study offline')} size="sm" variant="soft" onPress={() => router.push(`/offline/${p.id}` as never)} style={{ flex: 1 }} />
                  )}
                  {have ? (
                    <Pressable onPress={() => remove(p)} accessibilityRole="button" accessibilityLabel={t('offline.remove', 'Remove')} style={{ width: 44, height: 40, borderRadius: 12, borderWidth: 1, borderColor: c.line, alignItems: 'center', justifyContent: 'center' }}>
                      <Icon name="trash" size={16} color={c.danger} />
                    </Pressable>
                  ) : null}
                </View>
              </View>
            );
          })}
        </Card>
      )}

      <Text variant="caption" muted align="center">
        {t('offline.auto_note', 'Everything else you open online — notes, papers, your progress — is also kept on this phone and stays readable offline.')}
      </Text>
    </Screen>
  );
}
