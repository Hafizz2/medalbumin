import DateTimePicker, { DateTimePickerAndroid } from '@react-native-community/datetimepicker';
import { useQueryClient } from '@tanstack/react-query';
import { useRouter } from 'expo-router';
import { useState } from 'react';
import { Alert, Platform, Pressable, ScrollView, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { ApiError } from '../api/client';
import { qk, rebalancePlan, toggleTask, useCreatePlan, useStudyPlan } from '../api/hooks';
import type { StudyPlan as Plan } from '../api/types';
import { Button } from '../components/Button';
import { Icon } from '../components/Icon';
import { ErrorState, Header, Screen, Skeleton } from '../components/Screen';
import { Text } from '../components/Text';
import { Card, Check, Chip, ChipRow, ProgressBar } from '../components/ui';
import { useQuizLauncher } from '../hooks/useQuizLauncher';
import { fmtDate, fmtMinutes } from '../lib/format';
import { fonts, useTheme } from '../theme';

type Task = NonNullable<Plan['tasks']>[number];

const isoDay = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

/** 20 · AI study plan */
export default function StudyPlan() {
  const { t } = useTranslation();
  const [date, setDate] = useState<string | undefined>();
  const q = useStudyPlan(date);

  return (
    <Screen onRefresh={() => q.refetch()}>
      <Header />
      {q.isError && !q.data ? (
        <ErrorState error={q.error} onRetry={() => q.refetch()} />
      ) : !q.data ? (
        <>
          <Skeleton height={120} radius={22} />
          <Skeleton height={70} />
          <Skeleton height={300} />
        </>
      ) : !q.data.plan ? (
        <CreatePlan />
      ) : (
        <PlanView data={q.data} date={date} onDate={setDate} title={t('splan.title', 'AI study plan')} />
      )}
    </Screen>
  );
}

function CreatePlan({ onDone }: { onDone?: () => void }) {
  const { t } = useTranslation();
  const { c } = useTheme();
  const create = useCreatePlan();
  const min = new Date(Date.now() + 7 * 86400000);
  const [exam, setExam] = useState(new Date(Date.now() + 60 * 86400000));
  const [minutes, setMinutes] = useState(180);
  const [showIos, setShowIos] = useState(false);

  const pick = () => {
    if (Platform.OS === 'android') {
      DateTimePickerAndroid.open({ value: exam, minimumDate: min, mode: 'date', onChange: (_, d) => d && setExam(d) });
    } else {
      setShowIos((v) => !v);
    }
  };

  const submit = () =>
    create.mutate(
      { exam_date: isoDay(exam), daily_minutes: minutes, exam_label: t('splan.exit_exam', 'Exit Exam') },
      {
        onSuccess: onDone,
        onError: (e) => Alert.alert(t('splan.create_failed', 'Couldn’t build the plan'), e instanceof ApiError ? e.message : t('common.error_body', 'Please try again.')),
      },
    );

  const days = Math.max(0, Math.round((exam.getTime() - Date.now()) / 86400000));

  return (
    <>
      <View style={{ gap: 6 }}>
        <Text variant="title">{t('splan.title', 'AI study plan')}</Text>
        <Text variant="body" muted>
          {t('splan.intro', 'Tell us your exam date. We’ll split every department into daily tasks — learn, revise your weak areas, then mocks — and adjust when you miss a day.')}
        </Text>
      </View>

      <Card style={{ padding: 16, gap: 12 }}>
        <Text variant="label" muted>
          {t('splan.exam_date', 'Exam date')}
        </Text>
        <Pressable onPress={pick} accessibilityRole="button" style={{ height: 54, borderRadius: 14, borderWidth: 1, borderColor: c.field, paddingHorizontal: 14, flexDirection: 'row', alignItems: 'center', gap: 10 }}>
          <Icon name="calendar" size={18} color={c.teal} />
          <Text variant="bodyStrong" style={{ flex: 1 }}>
            {fmtDate(exam.toISOString(), { weekday: 'short', day: 'numeric', month: 'long', year: 'numeric' })}
          </Text>
          <Text variant="caption" muted>
            {t('splan.days_left', '{n} days', { n: days })}
          </Text>
        </Pressable>
        {Platform.OS === 'ios' && showIos ? <DateTimePicker value={exam} minimumDate={min} mode="date" display="inline" accentColor={c.teal} onChange={(_, d) => d && setExam(d)} /> : null}

        <Text variant="label" muted style={{ marginTop: 4 }}>
          {t('splan.daily_time', 'Study time per day')}
        </Text>
        <ChipRow>
          {[60, 120, 180, 240, 300].map((m) => (
            <Chip key={m} label={fmtMinutes(m)} selected={minutes === m} onPress={() => setMinutes(m)} />
          ))}
        </ChipRow>
      </Card>

      <Button title={t('splan.build', 'Build my plan')} icon="sparkle" loading={create.isPending} onPress={submit} />
    </>
  );
}

function PlanView({ data, date, onDate, title }: { data: Plan; date?: string; onDate: (d: string | undefined) => void; title: string }) {
  const { t } = useTranslation();
  const { c } = useTheme();
  const router = useRouter();
  const qc = useQueryClient();
  const { launch } = useQuizLauncher();
  const [rebalancing, setRebalancing] = useState(false);
  const [optimistic, setOptimistic] = useState<Record<number, boolean>>({});
  const plan = data.plan!;
  const tasks = data.tasks ?? [];
  const isDone = (task: Task) => optimistic[task.id] ?? task.done;
  const doneCount = tasks.filter(isDone).length;

  const tones: Record<Task['kind'], [string, string, string]> = {
    questions: [c.tealSoft, c.teal, t('splan.kind_questions', 'Questions')],
    read: [c.sandBg, c.sand, t('splan.kind_read', 'Read')],
    video: [c.skyBg, c.sky, t('splan.kind_video', 'Video')],
    cards: [c.chip, c.textSoft, t('splan.kind_cards', 'Cards')],
    mock: [c.plumBg, c.plumInk, t('splan.kind_mock', 'Mock')],
  };

  const refresh = () => qc.invalidateQueries({ queryKey: ['study-plan'] });

  const toggle = async (task: Task) => {
    const next = !isDone(task);
    setOptimistic((o) => ({ ...o, [task.id]: next }));
    try {
      const r = await toggleTask(task.id);
      setOptimistic((o) => ({ ...o, [task.id]: r.done }));
      qc.invalidateQueries({ queryKey: qk.home });
    } catch {
      setOptimistic((o) => ({ ...o, [task.id]: !next }));
    }
  };

  const rebalance = async () => {
    setRebalancing(true);
    try {
      const r = await rebalancePlan();
      Alert.alert(t('splan.rebalanced', 'Plan updated'), r.message);
      await refresh();
    } catch (e) {
      Alert.alert(t('common.error', 'Something went wrong'), e instanceof ApiError ? e.message : '');
    } finally {
      setRebalancing(false);
    }
  };

  const open = (task: Task) => {
    const target = (task.target ?? {}) as Record<string, number | number[] | string | undefined>;
    switch (task.kind) {
      case 'questions':
        return launch({
          source: target.subcategory_id ? 'subcategory' : 'builder',
          subcategory_ids: target.subcategory_id ? [Number(target.subcategory_id)] : undefined,
          department_ids: !target.subcategory_id && target.department_id ? [Number(target.department_id)] : undefined,
          size: target.size ? Number(target.size) : undefined,
          mode: 'practice',
          title: task.title,
        });
      case 'mock':
        return launch({
          source: 'builder',
          mode: 'timed',
          size: target.size ? Number(target.size) : 20,
          subcategory_ids: Array.isArray(target.subcategory_ids) ? target.subcategory_ids : undefined,
          title: task.title,
        });
      case 'read':
        return target.note_id ? router.push(`/note/${target.note_id}`) : router.push(target.subcategory_id ? `/notes-list?subcategory=${target.subcategory_id}` : '/notes');
      case 'video':
        return target.video_id ? router.push(`/video/${target.video_id}`) : router.push(target.subcategory_id ? `/videos?subcategory=${target.subcategory_id}` : '/videos');
      case 'cards':
        return router.push(target.deck_id ? `/flashcards?deck=${target.deck_id}` : '/flashcards');
    }
  };

  const phases = [t('splan.phase1', '1 · Learn all departments'), t('splan.phase2', '2 · Revise weak areas'), t('splan.phase3', '3 · Mocks')];
  const today = data.week?.find((d) => d.status === 'today')?.date;
  const selected = data.date ?? date ?? today;
  const dayLabel = selected && selected !== today ? fmtDate(selected, { weekday: 'long', day: 'numeric', month: 'short' }) : t('splan.today', 'Today');

  return (
    <>
      <Card tone="navy" style={{ padding: 18, gap: 12 }}>
        <Text variant="overline" color={c.onNavyLabel}>
          {[plan.exam_label, fmtDate(plan.exam_date, { weekday: 'short', day: 'numeric', month: 'short' })].filter(Boolean).join(' · ')}
        </Text>
        <View style={{ flexDirection: 'row', alignItems: 'flex-end', justifyContent: 'space-between', gap: 8 }}>
          <Text style={{ fontFamily: fonts.display, fontSize: 26 }} color={c.onNavy}>
            {title}
          </Text>
          <Text variant="mono" color={c.accent}>
            {t('splan.day_of', 'Day {d} of {n}', { d: plan.day, n: plan.total_days })}
          </Text>
        </View>
        <ProgressBar value={(plan.day / Math.max(1, plan.total_days)) * 100} color={c.accent} track="rgba(255,255,255,0.14)" />
        <View style={{ flexDirection: 'row', gap: 6 }}>
          {phases.map((p, i) => (
            <Text key={p} variant="caption" style={{ flex: 1 }} color={i + 1 === plan.phase ? c.accent : c.onNavyMuted}>
              {p}
            </Text>
          ))}
        </View>
      </Card>

      {data.week?.length ? (
        <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ gap: 6 }} style={{ marginHorizontal: -20 }}>
          <View style={{ width: 14 }} />
          {data.week.map((d) => {
            const on = d.date === selected;
            const dot = d.status === 'done' ? c.teal : d.status === 'missed' ? c.dot : d.status === 'mock' ? c.plum : d.status === 'today' ? c.accent : 'transparent';
            return (
              <Pressable
                key={d.date}
                onPress={() => onDate(d.status === 'today' ? undefined : d.date)}
                accessibilityRole="button"
                accessibilityState={{ selected: on }}
                style={{ width: 48, height: 64, borderRadius: 14, alignItems: 'center', justifyContent: 'center', gap: 3, borderWidth: 1, borderColor: on ? c.navy : c.line, backgroundColor: on ? c.navy : c.card }}
              >
                <Text variant="caption" color={on ? c.onNavySoft : c.muted}>
                  {d.label}
                </Text>
                <Text style={{ fontFamily: fonts.display, fontSize: 17 }} color={on ? c.onNavy : c.text}>
                  {d.day}
                </Text>
                <View style={{ width: 6, height: 6, borderRadius: 3, backgroundColor: dot }} />
              </Pressable>
            );
          })}
          <View style={{ width: 14 }} />
        </ScrollView>
      ) : null}

      {data.missed_days ? (
        <View style={{ backgroundColor: c.warnBg, borderRadius: 16, padding: 14, gap: 10 }}>
          <Text variant="small" color={c.warn}>
            {t('splan.behind', '{n} day(s) behind. We can spread the missed work over the next 5 days.', { n: data.missed_days })}
          </Text>
          <Button title={t('splan.rebalance', 'Rebalance')} size="sm" variant="primary" icon="refresh" loading={rebalancing} onPress={rebalance} style={{ alignSelf: 'flex-start' }} />
        </View>
      ) : null}

      <View style={{ flexDirection: 'row', alignItems: 'baseline', justifyContent: 'space-between' }}>
        <Text variant="h2">{dayLabel}</Text>
        <Text variant="caption" muted>
          {t('splan.done_of', '{d} of {n} done', { d: doneCount, n: tasks.length })} · {fmtMinutes(data.minutes ?? 0)}
        </Text>
      </View>

      {tasks.length === 0 ? (
        <Card style={{ padding: 16 }}>
          <Text variant="small" muted>
            {t('splan.rest_day', 'Nothing planned for this day. Rest, or review your flashcards.')}
          </Text>
        </Card>
      ) : (
        <Card>
          {tasks.map((task, i) => {
            const done = isDone(task);
            const [bg, ink, label] = tones[task.kind];
            return (
              <View key={task.id} style={{ flexDirection: 'row', alignItems: 'center', gap: 4, paddingLeft: 6, paddingRight: 14, minHeight: 66, borderTopWidth: i ? 1 : 0, borderTopColor: c.divider }}>
                <Pressable onPress={() => toggle(task)} accessibilityRole="checkbox" accessibilityState={{ checked: done }} accessibilityLabel={task.title} hitSlop={4} style={{ width: 44, height: 44, alignItems: 'center', justifyContent: 'center' }}>
                  <Check checked={done} size={26} />
                </Pressable>
                <Pressable onPress={() => open(task)} accessibilityRole="button" style={{ flex: 1, flexDirection: 'row', alignItems: 'center', gap: 10, paddingVertical: 10 }}>
                  <View style={{ flex: 1, gap: 2 }}>
                    <Text variant="bodyMedium" style={{ fontSize: 14, textDecorationLine: done ? 'line-through' : 'none' }} color={done ? c.muted : c.text}>
                      {task.title}
                    </Text>
                    <Text variant="caption" muted>
                      {[task.meta, fmtMinutes(task.minutes)].filter(Boolean).join(' · ')}
                    </Text>
                  </View>
                  <View style={{ paddingHorizontal: 8, height: 24, borderRadius: 8, backgroundColor: bg, justifyContent: 'center' }}>
                    <Text style={{ fontFamily: fonts.bodySemibold, fontSize: 11 }} color={ink}>
                      {label}
                    </Text>
                  </View>
                </Pressable>
              </View>
            );
          })}
        </Card>
      )}
    </>
  );
}
