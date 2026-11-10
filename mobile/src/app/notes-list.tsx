import { useQuery } from '@tanstack/react-query';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { api } from '../api/client';
import type { OfficialNoteRow, Paginated } from '../api/types';
import { EmptyState, ErrorState, Header, Screen, Skeleton } from '../components/Screen';
import { Text } from '../components/Text';
import { Badge, Card } from '../components/ui';

/** Official notes for one subcategory or department. */
export default function NotesList() {
  const { subcategory, department, title } = useLocalSearchParams<{ subcategory?: string; department?: string; title?: string }>();
  const { t } = useTranslation();
  const router = useRouter();
  const query = subcategory ? `subcategory_id=${subcategory}` : department ? `department_id=${department}` : '';
  const q = useQuery({ queryKey: ['notes', query], queryFn: () => api<Paginated<OfficialNoteRow>>(`/notes?${query}`) });

  return (
    <Screen onRefresh={() => q.refetch()}>
      <Header title={title ?? t('notes.official', 'Official')} />
      <Text variant="h1">{t('notes.official_notes', 'Official notes')}</Text>
      {q.isError && !q.data ? (
        <ErrorState error={q.error} onRetry={() => q.refetch()} />
      ) : !q.data ? (
        <Skeleton height={240} />
      ) : q.data.data.length === 0 ? (
        <EmptyState icon="note" title={t('notes.no_official', 'No official notes yet')} />
      ) : (
        <View style={{ gap: 10 }}>
          {q.data.data.map((n) => (
            <Card key={n.id} onPress={() => router.push(`/note/${n.id}`)} style={{ padding: 14, gap: 6, borderRadius: 18 }}>
              <View style={{ flexDirection: 'row', justifyContent: 'space-between', gap: 8 }}>
                <Text variant="bodyStrong" style={{ flex: 1 }}>
                  {n.title}
                </Text>
                {n.locked ? <Badge label={t('papers.premium', 'Premium')} tone="navy" /> : n.is_new ? <Badge label={t('notes.new_badge', 'New')} tone="warn" /> : null}
              </View>
              <Text variant="caption" muted>
                {n.breadcrumb} · {t('notes.read_min', '{n} min read', { n: n.read_minutes })}
              </Text>
            </Card>
          ))}
        </View>
      )}
    </Screen>
  );
}
