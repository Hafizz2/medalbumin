import { useLocalSearchParams } from 'expo-router';
import { ReactNode, useMemo, useState } from 'react';
import { Pressable, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { useBuilderOptions } from '../../api/hooks';
import { Button } from '../../components/Button';
import { ErrorState, Header, Screen, Skeleton } from '../../components/Screen';
import { Text } from '../../components/Text';
import { Card, Check, Chip } from '../../components/ui';
import { useQuizLauncher } from '../../hooks/useQuizLauncher';
import { fmtNumber } from '../../lib/format';
import { fonts, useTheme } from '../../theme';

type Pool = 'unused' | 'wrong' | 'bookmarked' | 'all';

/** 04 · Custom quiz builder */
export default function QuizBuilder() {
  const params = useLocalSearchParams<{ source?: string }>();
  const { t } = useTranslation();
  const { c } = useTheme();
  const q = useBuilderOptions();
  const { launch, starting } = useQuizLauncher();

  const initialPools: Pool[] = params.source === 'mistakes' ? ['wrong'] : params.source === 'bookmarks' ? ['bookmarked'] : ['unused', 'wrong'];
  const [depts, setDepts] = useState<number[]>([]);
  const [subs, setSubs] = useState<number[]>([]);
  const [size, setSize] = useState(20);
  const [mode, setMode] = useState<'practice' | 'timed'>('practice');
  const [pools, setPools] = useState<Pool[]>(initialPools);

  const opts = q.data;
  const selectedDepts = useMemo(() => opts?.departments.filter((d) => depts.includes(d.id)) ?? [], [opts, depts]);

  const toggle = <T,>(list: T[], v: T) => (list.includes(v) ? list.filter((x) => x !== v) : [...list, v]);
  const reset = () => {
    setDepts([]);
    setSubs([]);
    setSize(20);
    setMode('practice');
    setPools(['unused', 'wrong']);
  };

  const togglePool = (p: Pool) => {
    if (p === 'all') return setPools(pools.includes('all') ? ['unused'] : ['all']);
    const next = toggle(pools.filter((x) => x !== 'all'), p);
    setPools(next.length ? next : ['all']);
  };

  const start = () =>
    launch({
      source: params.source === 'mistakes' ? 'mistakes' : params.source === 'bookmarks' ? 'bookmarks' : 'builder',
      size,
      mode,
      pools,
      // A refined subcategory replaces its whole department.
      department_ids: depts.filter((d) => !selectedDepts.find((x) => x.id === d)?.subcategories.some((s) => subs.includes(s.id))),
      subcategory_ids: subs,
    });

  const minutes = Math.round((size * (opts?.seconds_per_question ?? 72)) / 60);
  const poolLabels: Record<Pool, string> = {
    unused: t('builder.pool_unused', 'Unused'),
    wrong: t('builder.pool_wrong', 'Answered wrong'),
    bookmarked: t('builder.pool_bookmarked', 'Bookmarked'),
    all: t('builder.pool_all', 'All questions'),
  };

  return (
    <Screen
      onRefresh={() => q.refetch()}
      footer={
        <Button
          title={t('builder.start', 'Start {count} questions', { count: size })}
          loading={starting}
          disabled={!opts}
          onPress={start}
          trailing={
            <Text variant="small" color="#A9BBD8">
              {t('builder.minutes', '≈ {min} min', { min: minutes })}
            </Text>
          }
        />
      }
    >
      <Header
        title={t('builder.title', 'Build a quiz')}
        right={
          <Pressable onPress={reset} hitSlop={8} accessibilityRole="button" style={{ width: 44, alignItems: 'flex-end' }}>
            <Text variant="smallStrong" color={c.teal}>
              {t('builder.reset', 'Reset')}
            </Text>
          </Pressable>
        }
      />

      {q.isError && !opts ? (
        <ErrorState error={q.error} onRetry={() => q.refetch()} />
      ) : !opts ? (
        <>
          <Skeleton height={140} />
          <Skeleton height={60} />
          <Skeleton height={100} />
        </>
      ) : (
        <>
          <Section title={t('builder.subjects', 'Subjects')} aside={depts.length ? t('builder.selected', '{count} selected', { count: depts.length }) : t('builder.all_subjects', 'All subjects')}>
            <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 8 }}>
              {opts.departments.map((d) => (
                <Chip key={d.id} label={d.short_name || d.name} count={fmtNumber(d.questions)} selected={depts.includes(d.id)} onPress={() => setDepts(toggle(depts, d.id))} />
              ))}
            </View>
            {selectedDepts.length ? (
              <View style={{ gap: 6, marginTop: 4 }}>
                <Text variant="caption" muted>
                  {t('builder.refine', 'Narrow down (optional)')}
                </Text>
                <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 6 }}>
                  {selectedDepts.flatMap((d) => d.subcategories).map((s) => (
                    <Chip key={s.id} label={s.name} count={s.questions} selected={subs.includes(s.id)} onPress={() => setSubs(toggle(subs, s.id))} />
                  ))}
                </View>
              </View>
            ) : null}
          </Section>

          <Section title={t('builder.how_many', 'Number of questions')}>
            <View style={{ flexDirection: 'row', gap: 8 }}>
              {opts.sizes.map((n) => {
                const on = n === size;
                return (
                  <Pressable
                    key={n}
                    accessibilityRole="radio"
                    accessibilityState={{ checked: on }}
                    onPress={() => setSize(n)}
                    style={{ flex: 1, height: 48, borderRadius: 14, alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: on ? c.teal : c.field, backgroundColor: on ? c.teal : c.card }}
                  >
                    <Text variant="monoLarge" color={on ? '#FFFFFF' : c.text}>
                      {n}
                    </Text>
                  </Pressable>
                );
              })}
            </View>
          </Section>

          <Section title={t('builder.mode', 'Mode')}>
            <View style={{ flexDirection: 'row', gap: 8 }}>
              {(
                [
                  ['practice', t('builder.practice', 'Practice'), t('builder.practice_desc', 'See the answer and explanation after each question.')],
                  ['timed', t('builder.timed', 'Timed exam'), t('builder.timed_desc', 'Countdown timer. Results and review at the end.')],
                ] as const
              ).map(([id, label, desc]) => {
                const on = id === mode;
                return (
                  <Pressable
                    key={id}
                    accessibilityRole="radio"
                    accessibilityState={{ checked: on }}
                    onPress={() => setMode(id)}
                    style={{ flex: 1, minHeight: 96, padding: 14, borderRadius: 18, gap: 6, borderWidth: 1, borderColor: on ? c.navy : c.field, backgroundColor: on ? c.navy : c.card }}
                  >
                    <Text style={{ fontFamily: fonts.bodySemibold, fontSize: 15 }} color={on ? c.onNavy : c.text}>
                      {label}
                    </Text>
                    <Text variant="caption" color={on ? c.onNavySoft : c.muted}>
                      {desc}
                    </Text>
                  </Pressable>
                );
              })}
            </View>
          </Section>

          <Section title={t('builder.pool', 'Question pool')}>
            <Card>
              {opts.pools.map((p, i) => {
                const on = pools.includes(p.id);
                return (
                  <Pressable
                    key={p.id}
                    accessibilityRole="checkbox"
                    accessibilityState={{ checked: on }}
                    onPress={() => togglePool(p.id)}
                    style={{ minHeight: 60, paddingHorizontal: 16, paddingVertical: 8, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', borderTopWidth: i ? 1 : 0, borderTopColor: c.divider }}
                  >
                    <View style={{ gap: 2 }}>
                      <Text variant="bodyStrong">{poolLabels[p.id]}</Text>
                      <Text variant="caption" muted>
                        {t('builder.pool_count', '{n} questions', { n: fmtNumber(p.count) })}
                      </Text>
                    </View>
                    <Check checked={on} />
                  </Pressable>
                );
              })}
            </Card>
            {opts.locked_questions > 0 ? (
              <Text variant="caption" muted>
                {t('builder.locked', '{n} more questions are part of the premium plan.', { n: fmtNumber(opts.locked_questions) })}
              </Text>
            ) : null}
          </Section>
        </>
      )}
    </Screen>
  );
}

function Section({ title, aside, children }: { title: string; aside?: string; children: ReactNode }) {
  return (
    <View style={{ gap: 10, marginTop: 6 }}>
      <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'baseline' }}>
        <Text variant="h2">{title}</Text>
        {aside ? (
          <Text variant="caption" muted>
            {aside}
          </Text>
        ) : null}
      </View>
      {children}
    </View>
  );
}
