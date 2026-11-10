import { useRouter } from 'expo-router';
import { useState } from 'react';
import { View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { useDepartments } from '../../api/hooks';
import { IconButton } from '../../components/Button';
import { Icon } from '../../components/Icon';
import { EmptyState, ErrorState, Screen, Skeleton } from '../../components/Screen';
import { Text } from '../../components/Text';
import { Card, Chip, ChipRow, IconTile, ProgressBar } from '../../components/ui';
import { tileColors } from '../../lib/colors';
import { fmtNumber } from '../../lib/format';
import { fonts, TAB_BAR_SPACE, useTheme } from '../../theme';

type Filter = 'all' | 'prog' | 'new' | 'done';

/** 11 · Study — departments */
export default function Study() {
  const { t } = useTranslation();
  const { c, dark } = useTheme();
  const router = useRouter();
  const q = useDepartments();
  const [filter, setFilter] = useState<Filter>('all');

  const all = q.data?.data ?? [];
  const list = all.filter((d) => filter === 'all' || d.status === filter);

  return (
    <Screen onRefresh={() => q.refetch()} bottomInset={TAB_BAR_SPACE}>
      <View style={{ flexDirection: 'row', alignItems: 'flex-start', justifyContent: 'space-between' }}>
        <View style={{ gap: 2, flex: 1 }}>
          <Text variant="h1">{t('study.title', 'Study')}</Text>
          {q.data ? (
            <Text variant="small" muted>
              {t('study.subtitle', '{departments} departments · {questions} questions', { departments: all.length, questions: fmtNumber(q.data.total_questions) })}
            </Text>
          ) : null}
        </View>
        <View style={{ flexDirection: 'row', gap: 6 }}>
          <IconButton icon="note" label={t('study.past_papers', 'Past papers by year')} onPress={() => router.push('/papers')} />
          <IconButton icon="search" label={t('study.search', 'Search')} onPress={() => router.push('/search')} />
        </View>
      </View>

      <ChipRow>
        {(
          [
            ['all', t('study.filter_all', 'All')],
            ['prog', t('study.filter_progress', 'In progress')],
            ['new', t('study.filter_new', 'Not started')],
            ['done', t('study.filter_done', 'Completed')],
          ] as [Filter, string][]
        ).map(([id, label]) => (
          <Chip key={id} label={label} selected={filter === id} onPress={() => setFilter(id)} />
        ))}
      </ChipRow>

      {q.isError && !q.data ? (
        <ErrorState error={q.error} onRetry={() => q.refetch()} />
      ) : !q.data ? (
        [0, 1, 2, 3].map((i) => <Skeleton key={i} height={86} />)
      ) : list.length === 0 ? (
        <EmptyState icon="book" title={t('study.empty', 'Nothing here yet')} body={t('study.empty_body', 'Try another filter.')} />
      ) : (
        <View style={{ gap: 10 }}>
          {list.map((d) => {
            const tc = tileColors(d.color_bg, d.color_ink, dark, c);
            return (
              <Card key={d.id} onPress={() => router.push(`/department/${d.id}`)} accessibilityLabel={`${d.name}, ${d.done_pct}%`} style={{ padding: 14, flexDirection: 'row', alignItems: 'center', gap: 14, borderRadius: 20 }}>
                <IconTile icon={d.icon} bg={tc.bg} ink={tc.ink} size={48} iconSize={22} radius={14} />
                <View style={{ flex: 1, gap: 6 }}>
                  <Text variant="bodyStrong" numberOfLines={1}>
                    {d.name}
                  </Text>
                  <View style={{ flexDirection: 'row', justifyContent: 'space-between' }}>
                    <Text variant="caption" muted>
                      {d.topics_total
                        ? t('study.topics', '{done} / {total} topics', { done: d.topics_done, total: d.topics_total })
                        : t('study.questions_count', '{n} questions', { n: fmtNumber(d.questions) })}
                    </Text>
                    <Text style={{ fontFamily: fonts.mono, fontSize: 12 }} color={d.done_pct === 0 ? c.muted : c.teal}>
                      {d.done_pct}%
                    </Text>
                  </View>
                  <ProgressBar value={d.done_pct} color={d.done_pct < 30 ? c.accent : c.teal} />
                </View>
                <Icon name="chevronRight" size={18} color={c.muted} />
              </Card>
            );
          })}
        </View>
      )}
    </Screen>
  );
}
