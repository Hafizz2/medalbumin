import { useLocalSearchParams, useRouter } from 'expo-router';
import { useEffect, useMemo, useState } from 'react';
import { Pressable, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { Button } from '../../components/Button';
import { Icon } from '../../components/Icon';
import { EmptyState, Header, Screen, Skeleton } from '../../components/Screen';
import { Text } from '../../components/Text';
import { Card, ProgressBar } from '../../components/ui';
import { loadPack, OfflinePack, useOffline } from '../../offline/store';
import { useTheme } from '../../theme';

/** One downloaded department: practise by topic, or read its notes — no internet needed. */
export default function OfflinePackScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const { t } = useTranslation();
  const { c } = useTheme();
  const router = useRouter();
  const answered = useOffline((s) => s.answered);
  const [pack, setPack] = useState<OfflinePack | null | undefined>(undefined);

  useEffect(() => {
    loadPack(Number(id)).then(setPack);
  }, [id]);

  const stats = useMemo(() => {
    const bySub: Record<string, { total: number; done: number }> = {};
    let done = 0;
    let correct = 0;
    let wrong = 0;
    for (const q of pack?.questions ?? []) {
      const key = String(q.subcategory_id ?? 0);
      bySub[key] ??= { total: 0, done: 0 };
      bySub[key].total++;
      if (q.id in answered) {
        done++;
        bySub[key].done++;
        answered[q.id] ? correct++ : wrong++;
      }
    }
    return { bySub, done, correct, wrong };
  }, [pack, answered]);

  if (pack === undefined) {
    return (
      <Screen>
        <Header />
        <Skeleton height={140} />
        <Skeleton height={260} />
      </Screen>
    );
  }
  if (!pack) {
    return (
      <Screen>
        <Header />
        <EmptyState icon="download" title={t('offline.missing', 'This download is no longer on your phone')} action={t('offline.open_downloads', 'Open my downloads')} onAction={() => router.replace('/downloads')} />
      </Screen>
    );
  }

  const total = pack.questions.length;
  const practise = (params: Record<string, string>) => router.push({ pathname: '/offline/quiz', params: { pack: String(pack.id), ...params } });
  const subs = [...pack.subcategories, ...(stats.bySub['0'] ? [{ id: 0, name: t('offline.other_topics', 'Other topics') }] : [])].filter((s) => stats.bySub[String(s.id)]);

  return (
    <Screen>
      <Header title={t('offline.badge', 'Available offline')} />
      <Text variant="title">{pack.name}</Text>

      <Card tone="navy" style={{ padding: 16, gap: 12 }}>
        <View style={{ flexDirection: 'row', justifyContent: 'space-between' }}>
          <Text variant="smallStrong" color={c.onNavy}>
            {t('offline.answered_of', '{d} of {n} answered', { d: stats.done, n: total })}
          </Text>
          {stats.done ? (
            <Text variant="smallStrong" color={c.accent}>
              {Math.round((stats.correct / stats.done) * 100)}%
            </Text>
          ) : null}
        </View>
        <ProgressBar value={total ? (stats.done / total) * 100 : 0} color={c.accent} track="rgba(255,255,255,0.14)" />
        <View style={{ flexDirection: 'row', gap: 8 }}>
          <Button title={t('offline.practise_20', 'Practise 20')} variant="accent" size="sm" style={{ flex: 1 }} disabled={!total} onPress={() => practise({ size: '20' })} />
          <Button title={t('offline.mistakes_n', 'My mistakes · {n}', { n: stats.wrong })} variant="light" size="sm" style={{ flex: 1 }} disabled={!stats.wrong} onPress={() => practise({ only: 'wrong', size: '40' })} />
        </View>
      </Card>

      {subs.length ? (
        <>
          <Text variant="h2">{t('offline.topics', 'Topics')}</Text>
          <Card>
            {subs.map((s, i) => {
              const st = stats.bySub[String(s.id)];
              return (
                <Pressable key={s.id} onPress={() => practise({ sub: String(s.id), size: '20' })} accessibilityRole="button" style={{ flexDirection: 'row', alignItems: 'center', gap: 12, padding: 14, borderTopWidth: i ? 1 : 0, borderTopColor: c.divider }}>
                  <View style={{ flex: 1, gap: 6 }}>
                    <Text variant="bodyMedium">{s.name}</Text>
                    <ProgressBar value={(st.done / st.total) * 100} height={4} />
                  </View>
                  <Text variant="mono" style={{ fontSize: 12 }} muted>
                    {st.done}/{st.total}
                  </Text>
                  <Icon name="chevronRight" size={16} color={c.muted} />
                </Pressable>
              );
            })}
          </Card>
        </>
      ) : null}

      {pack.notes.length ? (
        <>
          <Text variant="h2">{t('notes.official_notes', 'Official notes')}</Text>
          <Card>
            {pack.notes.map((n, i) => (
              <Pressable key={n.id} onPress={() => router.push({ pathname: '/offline/note', params: { pack: String(pack.id), note: String(n.id) } })} accessibilityRole="button" style={{ flexDirection: 'row', alignItems: 'center', gap: 12, padding: 14, borderTopWidth: i ? 1 : 0, borderTopColor: c.divider }}>
                <Icon name="note" size={18} color={c.sand} />
                <View style={{ flex: 1, gap: 2 }}>
                  <Text variant="bodyMedium">{n.title}</Text>
                  <Text variant="caption" muted>
                    {t('notes.read_min', '{n} min read', { n: n.read_minutes })}
                  </Text>
                </View>
                <Icon name="chevronRight" size={16} color={c.muted} />
              </Pressable>
            ))}
          </Card>
        </>
      ) : null}
    </Screen>
  );
}
