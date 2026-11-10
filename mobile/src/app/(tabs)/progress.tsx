import { useRouter } from 'expo-router';
import { useState } from 'react';
import { View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { useMocks, useOverview, useSubjects } from '../../api/hooks';
import type { SubjectRow } from '../../api/types';
import { IconButton } from '../../components/Button';
import { Icon, IconName } from '../../components/Icon';
import { EmptyState, ErrorState, Screen, Skeleton } from '../../components/Screen';
import { Text } from '../../components/Text';
import { TrendChart } from '../../components/TrendChart';
import { Card, IconTile, ProgressBar, SectionTitle, Segmented } from '../../components/ui';
import { tileColors } from '../../lib/colors';
import { fmtDate, fmtDuration, fmtNumber } from '../../lib/format';
import { fonts, TAB_BAR_SPACE, useTheme } from '../../theme';

type Tab = 'overview' | 'subjects' | 'mocks';

/** 07 · Progress — overview, subjects, mock exams */
export default function Progress() {
  const { t } = useTranslation();
  const { c } = useTheme();
  const router = useRouter();
  const [tab, setTab] = useState<Tab>('overview');
  const overview = useOverview();
  const subjects = useSubjects();
  const mocks = useMocks();

  const refresh = () => Promise.all([overview.refetch(), subjects.refetch(), mocks.refetch()]);

  return (
    <Screen onRefresh={refresh} bottomInset={TAB_BAR_SPACE}>
      <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' }}>
        <Text variant="h1">{t('progress.title', 'Your progress')}</Text>
        <IconButton icon="calendar" label={t('progress.calendar', 'Study calendar')} onPress={() => router.push('/study-plan')} />
      </View>
      <Segmented<Tab>
        value={tab}
        onChange={setTab}
        options={[
          { id: 'overview', label: t('progress.overview', 'Overview') },
          { id: 'subjects', label: t('progress.subjects', 'Subjects') },
          { id: 'mocks', label: t('progress.mocks', 'Mock exams') },
        ]}
      />

      {tab === 'overview' ? <Overview q={overview} onSeeAll={() => setTab('subjects')} /> : null}
      {tab === 'subjects' ? (
        subjects.isError && !subjects.data ? (
          <ErrorState error={subjects.error} onRetry={() => subjects.refetch()} />
        ) : !subjects.data ? (
          <Skeleton height={300} />
        ) : subjects.data.data.length ? (
          <SubjectList rows={subjects.data.data} />
        ) : (
          <EmptyState icon="chart" title={t('progress.no_subjects', 'No results yet')} body={t('progress.no_subjects_body', 'Answer questions to see how you do in each subject.')} action={t('progress.start_practice', 'Start practising')} onAction={() => router.push('/quiz/builder')} />
        )
      ) : null}
      {tab === 'mocks' ? (
        mocks.isError && !mocks.data ? (
          <ErrorState error={mocks.error} onRetry={() => mocks.refetch()} />
        ) : !mocks.data ? (
          <Skeleton height={300} />
        ) : mocks.data.data.length ? (
          <View style={{ gap: 10 }}>
            {mocks.data.data.map((m) => (
              <Card key={m.id} onPress={() => router.push(`/result/${m.id}`)} style={{ padding: 14, gap: 6, borderRadius: 18 }}>
                <View style={{ flexDirection: 'row', justifyContent: 'space-between' }}>
                  <Text variant="caption" muted>
                    {fmtDate(m.completed_at)}
                  </Text>
                  <Text style={{ fontFamily: fonts.mono, fontSize: 15 }} color={m.score_pct >= 70 ? c.teal : c.sand}>
                    {m.score_pct}%
                  </Text>
                </View>
                <View style={{ flexDirection: 'row', justifyContent: 'space-between', gap: 8 }}>
                  <Text variant="bodyStrong" style={{ flex: 1 }} numberOfLines={1}>
                    {m.title}
                  </Text>
                  <Text variant="mono" muted>
                    {m.correct} / {m.total}
                  </Text>
                </View>
                <View style={{ flexDirection: 'row', gap: 12, alignItems: 'center' }}>
                  <Text variant="caption" muted>
                    {fmtDuration(m.time_spent_seconds)}
                  </Text>
                  <Text variant="caption" muted>
                    {m.level === 'Hard' ? t('quiz.hard', 'Hard') : m.level === 'Easy' ? t('quiz.easy', 'Easy') : t('progress.medium', 'Medium')}
                  </Text>
                  {m.top_pct ? (
                    <Text variant="caption" muted>
                      {t('result.top', 'Top {pct}%', { pct: m.top_pct })}
                    </Text>
                  ) : null}
                  <View style={{ flex: 1 }} />
                  <Text variant="smallStrong" color={c.teal}>
                    {t('progress.report', 'Detailed report ›')}
                  </Text>
                </View>
              </Card>
            ))}
          </View>
        ) : (
          <EmptyState icon="timer" title={t('progress.no_mocks', 'No mock exams yet')} body={t('progress.no_mocks_body', 'Take a timed quiz or a grand mock to see it here.')} action={t('progress.take_mock', 'See grand mocks')} onAction={() => router.push('/mock')} />
        )
      ) : null}
    </Screen>
  );
}

function Overview({ q, onSeeAll }: { q: ReturnType<typeof useOverview>; onSeeAll: () => void }) {
  const { t } = useTranslation();
  const { c } = useTheme();
  if (q.isError && !q.data) return <ErrorState error={q.error} onRetry={() => q.refetch()} />;
  if (!q.data) return <Skeleton height={420} />;
  const o = q.data;
  const trend = o.trend.map((p) => p.accuracy);
  const first = o.trend[0]?.week;
  const mid = o.trend[Math.floor(o.trend.length / 2)]?.week;
  const lastWeek = o.trend[o.trend.length - 1]?.week;
  const hours = Math.floor(o.totals.study_seconds / 3600);
  const mins = Math.floor((o.totals.study_seconds % 3600) / 60);

  const stats: { icon: IconName; label: string; value: string; delta?: string | null; bar?: number }[] = [
    { icon: 'check2', label: t('progress.answered', 'Questions answered'), value: fmtNumber(o.totals.answered), delta: o.questions_delta_pct !== null ? t('progress.this_month_pct', '{pct}% this month', { pct: o.questions_delta_pct }) : null },
    { icon: 'timer', label: t('progress.study_time', 'Study time'), value: `${hours}h ${String(mins).padStart(2, '0')}m`, delta: o.study_delta_pct !== null ? t('progress.this_month_pct', '{pct}% this month', { pct: o.study_delta_pct }) : null },
    { icon: 'target', label: t('progress.average_score', 'Average score'), value: o.average_score !== null ? `${o.average_score}%` : '—', delta: o.accuracy_delta !== null ? t('progress.points', '{n} points', { n: o.accuracy_delta }) : null },
    { icon: 'flag', label: t('progress.target_score', 'Target score'), value: `${o.target_score}%`, bar: o.average_score !== null ? Math.min(100, (o.average_score / o.target_score) * 100) : 0 },
  ];

  return (
    <View style={{ gap: 12 }}>
      <View style={{ backgroundColor: '#062A5E', borderRadius: 22, padding: 16, gap: 10 }}>
        <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start' }}>
          <View>
            <Text variant="small" color="#C5D3EA">
              {t('progress.overall', 'Overall performance')}
            </Text>
            <Text style={{ fontFamily: fonts.displayBold, fontSize: 34, letterSpacing: -1 }} color="#F3F6FA">
              {o.totals.accuracy}%
            </Text>
          </View>
          {o.accuracy_delta !== null ? (
            <View style={{ alignItems: 'flex-end' }}>
              <View style={{ backgroundColor: '#12407F', borderRadius: 99, paddingHorizontal: 9, paddingVertical: 4 }}>
                <Text style={{ fontFamily: fonts.bodySemibold, fontSize: 12 }} color={o.accuracy_delta >= 0 ? c.accent : '#FBBF77'}>
                  {o.accuracy_delta >= 0 ? '+' : ''}
                  {o.accuracy_delta}%
                </Text>
              </View>
              <Text style={{ fontSize: 11, marginTop: 3 }} color="#94A6C4">
                {t('progress.vs_last_month', 'vs last month')}
              </Text>
            </View>
          ) : null}
        </View>
        <TrendChart values={trend} color={c.accent} />
        <View style={{ flexDirection: 'row', justifyContent: 'space-between' }}>
          {[first, mid, lastWeek].map((w, i) => (
            <Text key={i} variant="mono" style={{ fontSize: 11 }} color="#94A6C4">
              {w ? fmtDate(w, { month: 'short', day: 'numeric' }) : ''}
            </Text>
          ))}
        </View>
      </View>

      <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 10 }}>
        {stats.map((s) => (
          <Card key={s.label} style={{ width: '48.4%', padding: 14, gap: 6, borderRadius: 18 }}>
            <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }}>
              <Icon name={s.icon} size={14} color={c.muted} />
              <Text variant="caption" muted numberOfLines={1} style={{ flex: 1 }}>
                {s.label}
              </Text>
            </View>
            <Text style={{ fontFamily: fonts.display, fontSize: 22 }}>{s.value}</Text>
            {s.bar !== undefined ? (
              <ProgressBar value={s.bar} />
            ) : s.delta ? (
              <Text style={{ fontFamily: fonts.bodySemibold, fontSize: 12 }} color={s.delta.startsWith('-') ? c.danger : c.teal}>
                {s.delta.startsWith('-') ? '▼ ' : '▲ '}
                {s.delta.replace(/^-/, '')}
              </Text>
            ) : (
              <Text variant="caption" muted>
                —
              </Text>
            )}
          </Card>
        ))}
      </View>

      {o.top_subjects.length ? (
        <>
          <SectionTitle title={t('progress.subject_performance', 'Subject performance')} action={t('common.see_all', 'See all')} onAction={onSeeAll} />
          <SubjectList rows={o.top_subjects} />
        </>
      ) : null}
    </View>
  );
}

function SubjectList({ rows }: { rows: SubjectRow[] }) {
  const { c, dark } = useTheme();
  const router = useRouter();
  return (
    <View style={{ gap: 8 }}>
      {rows.map((s) => {
        const tc = tileColors(s.color_bg, s.color_ink, dark, c);
        const weak = s.accuracy < 60;
        return (
          <Card key={s.id} onPress={() => router.push(`/department/${s.id}`)} style={{ padding: 12, flexDirection: 'row', alignItems: 'center', gap: 12, borderRadius: 18 }}>
            <IconTile icon={s.icon} bg={tc.bg} ink={tc.ink} size={40} />
            <View style={{ flex: 1, gap: 6 }}>
              <View style={{ flexDirection: 'row', justifyContent: 'space-between' }}>
                <Text variant="bodyStrong" numberOfLines={1} style={{ flex: 1 }}>
                  {s.name}
                </Text>
                <Text style={{ fontFamily: fonts.mono, fontSize: 13 }} color={weak ? c.danger : c.teal}>
                  {s.accuracy}%
                </Text>
              </View>
              <ProgressBar value={s.accuracy} color={weak ? c.danger2 : c.teal} />
            </View>
          </Card>
        );
      })}
    </View>
  );
}
