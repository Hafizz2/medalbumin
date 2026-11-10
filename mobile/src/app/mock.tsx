import { useRouter } from 'expo-router';
import { useEffect, useMemo, useState } from 'react';
import { Alert, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import type { TFunction } from 'i18next';
import { ApiError } from '../api/client';
import { useExams, useLeaderboard, useMocks, useRegisterExam } from '../api/hooks';
import type { Exam } from '../api/types';
import { Button } from '../components/Button';
import { Icon, type IconName } from '../components/Icon';
import { EmptyState, ErrorState, Header, Screen, Skeleton } from '../components/Screen';
import { Text } from '../components/Text';
import { Badge, Card, Chip, ChipRow, IconTile, Segmented } from '../components/ui';
import { useQuizLauncher } from '../hooks/useQuizLauncher';
import { fmtDate, fmtDuration, fmtNumber } from '../lib/format';
import { fonts, useTheme } from '../theme';

type Tab = 'practice' | 'board' | 'results';
type Scope = 'national' | 'university' | 'friends';

function countdown(iso: string | null, t: TFunction): string | null {
  if (!iso) return null;
  const ms = new Date(iso).getTime() - Date.now();
  if (ms <= 0) return null;
  const d = Math.floor(ms / 86400000);
  const h = Math.floor((ms % 86400000) / 3600000);
  const m = Math.floor((ms % 3600000) / 60000);
  return d > 0 ? t('mock.starts_dh', 'starts in {d} d {h} h', { d, h }) : t('mock.starts_hm', 'starts in {h} h {m} m', { h, m });
}

/** 21 · Grand mock — registration, practice modes, leaderboard and past results. */
export default function Mock() {
  const { t } = useTranslation();
  const { c } = useTheme();
  const router = useRouter();
  const [tab, setTab] = useState<Tab>('practice');
  const [scope, setScope] = useState<Scope>('national');
  const exams = useExams();
  const mocks = useMocks();
  const register = useRegisterExam();
  const { launch, starting } = useQuizLauncher();
  const [, tick] = useState(0);

  // Refresh the countdown every minute.
  useEffect(() => {
    const h = setInterval(() => tick((n) => n + 1), 60000);
    return () => clearInterval(h);
  }, []);

  const list = exams.data?.data ?? [];
  const grand = useMemo(() => list.find((e) => e.type === 'grand' && ['scheduled', 'live'].includes(e.status)) ?? list.find((e) => e.type === 'grand'), [list]);
  const boardExam = useMemo(() => list.find((e) => e.type === 'grand' && e.leaderboard && e.my_session_id) ?? list.find((e) => e.leaderboard && e.my_session_id), [list]);
  const board = useLeaderboard(tab === 'board' ? boardExam?.id : undefined, scope);
  const others = list.filter((e) => e.id !== grand?.id && e.is_open);

  const toggleRegister = (e: Exam) =>
    register.mutate(e.id, {
      onError: (err) => Alert.alert(t('mock.register_failed', 'Couldn’t register'), err instanceof ApiError ? err.message : t('common.error_body', 'Please try again.')),
    });

  const take = (e: Exam) => {
    if (e.my_session_id) return router.push(`/result/${e.my_session_id}`);
    launch({ source: 'exam', exam_id: e.id, mode: 'timed' });
  };

  const modes: { title: string; sub: string; icon: IconName; bg: string; ink: string; onPress: () => void }[] = [
    {
      title: t('mock.full', 'Full mock exam'),
      sub: t('mock.full_sub', '200 questions · 4 hours · like the real exam'),
      icon: 'question',
      bg: '#062A5E',
      ink: c.accent,
      onPress: () => launch({ source: 'builder', mode: 'timed', size: 200, title: t('mock.full', 'Full mock exam') }),
    },
    { title: t('mock.by_dept', 'By department'), sub: t('mock.by_dept_sub', 'Choose departments and subcategories'), icon: 'book', bg: c.skyBg, ink: c.sky, onPress: () => router.push('/quiz/builder') },
    { title: t('mock.custom', 'Custom mock'), sub: t('mock.custom_sub', 'Set your own size, time and rules'), icon: 'settings', bg: c.plumBg, ink: c.plum, onPress: () => router.push('/quiz/builder?mode=timed') },
  ];

  return (
    <Screen onRefresh={() => Promise.all([exams.refetch(), mocks.refetch()])}>
      <Header />
      <Text variant="title">{t('mock.title', 'Grand mock')}</Text>

      {exams.isError && !exams.data ? (
        <ErrorState error={exams.error} onRetry={() => exams.refetch()} />
      ) : !exams.data ? (
        <Skeleton height={170} radius={22} />
      ) : grand ? (
        <Card tone="navy" style={{ padding: 18, gap: 12 }}>
          <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8 }}>
            <View style={{ width: 8, height: 8, borderRadius: 4, backgroundColor: grand.status === 'live' ? c.dot : c.accent }} />
            <Text variant="overline" color={c.onNavyLabel}>
              {grand.status === 'live' ? t('mock.live', 'Live now · national rank') : t('mock.upcoming', 'Upcoming · national rank')}
            </Text>
          </View>
          <View style={{ gap: 4 }}>
            <Text style={{ fontFamily: fonts.display, fontSize: 22 }} color={c.onNavy}>
              {grand.title}
            </Text>
            <Text variant="small" color={c.onNavySoft}>
              {[grand.starts_at ? fmtDate(grand.starts_at, { weekday: 'short', day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit' }) : null, t('mock.registered_n', '{n} registered', { n: fmtNumber(grand.registrations) }), countdown(grand.starts_at, t)]
                .filter(Boolean)
                .join(' · ')}
            </Text>
            <Text variant="caption" color={c.onNavyMuted}>
              {t('mock.exam_meta', '{q} questions · {time}', { q: grand.question_count, time: fmtDuration(grand.duration_minutes * 60) })}
            </Text>
          </View>
          {grand.is_open ? (
            <Button title={grand.my_session_id ? t('mock.see_result', 'See my result') : t('mock.start', 'Start the exam')} variant="accent" loading={starting} onPress={() => take(grand)} />
          ) : grand.can_take ? (
            <Button
              title={grand.registered ? t('mock.registered', 'Registered — we’ll remind you') : t('mock.register', 'Register for free')}
              icon={grand.registered ? 'check' : undefined}
              variant={grand.registered ? 'light' : 'accent'}
              loading={register.isPending}
              onPress={() => toggleRegister(grand)}
            />
          ) : (
            <Text variant="small" color={c.onNavySoft}>
              {t('mock.premium_only', 'This exam is for premium students. See your plan under Profile.')}
            </Text>
          )}
        </Card>
      ) : (
        <Card style={{ padding: 16 }}>
          <Text variant="small" muted>
            {t('mock.none_scheduled', 'No grand mock is scheduled yet. You’ll get a notification when one opens.')}
          </Text>
        </Card>
      )}

      <Segmented<Tab>
        value={tab}
        onChange={setTab}
        options={[
          { id: 'practice', label: t('mock.practice', 'Practice') },
          { id: 'board', label: t('mock.leaderboard', 'Leaderboard') },
          { id: 'results', label: t('mock.results', 'Results') },
        ]}
      />

      {tab === 'practice' ? (
        <>
          <View style={{ gap: 2 }}>
            <Text variant="h2">{t('mock.simulation', 'Real exam simulation')}</Text>
            <Text variant="small" muted>
              {t('mock.simulation_sub', 'Timed · mixed questions · full explanations')}
            </Text>
          </View>
          <Card>
            {modes.map((m, i) => (
              <Card key={m.title} tone="plain" onPress={m.onPress} style={{ flexDirection: 'row', alignItems: 'center', gap: 12, padding: 14, borderTopWidth: i ? 1 : 0, borderTopColor: c.divider, borderRadius: 0 }}>
                <IconTile icon={m.icon} bg={m.bg} ink={m.ink} />
                <View style={{ flex: 1, gap: 2 }}>
                  <Text variant="bodyStrong">{m.title}</Text>
                  <Text variant="caption" muted>
                    {m.sub}
                  </Text>
                </View>
                <Icon name="chevronRight" size={16} color={c.muted} />
              </Card>
            ))}
          </Card>
          {others.length ? (
            <>
              <Text variant="h2">{t('mock.open_exams', 'Open exams')}</Text>
              {others.map((e) => (
                <Card key={e.id} onPress={() => take(e)} style={{ padding: 14, flexDirection: 'row', alignItems: 'center', gap: 12 }}>
                  <IconTile icon="timer" bg={c.plumBg} ink={c.plum} />
                  <View style={{ flex: 1, gap: 2 }}>
                    <Text variant="bodyStrong">{e.title}</Text>
                    <Text variant="caption" muted>
                      {t('mock.exam_meta', '{q} questions · {time}', { q: e.question_count, time: fmtDuration(e.duration_minutes * 60) })}
                    </Text>
                  </View>
                  {e.my_session_id ? <Badge label={t('mock.done', 'Done')} tone="ok" /> : !e.can_take ? <Icon name="lock" size={14} color={c.muted} /> : null}
                </Card>
              ))}
            </>
          ) : null}
        </>
      ) : null}

      {tab === 'board' ? (
        !boardExam ? (
          <EmptyState icon="trophy" title={t('mock.no_board', 'No ranking yet')} body={t('mock.no_board_body', 'Take a grand mock to see where you stand nationally.')} />
        ) : (
          <>
            <ChipRow>
              <Chip label={t('mock.national', 'National')} selected={scope === 'national'} onPress={() => setScope('national')} />
              <Chip label={t('mock.my_uni', 'My university')} selected={scope === 'university'} onPress={() => setScope('university')} />
              <Chip label={t('mock.friends', 'Friends')} selected={scope === 'friends'} onPress={() => setScope('friends')} />
            </ChipRow>
            {board.isError && !board.data ? (
              <ErrorState error={board.error} onRetry={() => board.refetch()} />
            ) : !board.data ? (
              <Skeleton height={300} />
            ) : (
              <>
                {board.data.me ? (
                  <Card tone="navy" style={{ padding: 16, flexDirection: 'row', alignItems: 'center', gap: 14 }}>
                    <Text style={{ fontFamily: fonts.displayBold, fontSize: 30 }} color={c.accent}>
                      #{fmtNumber(board.data.me.rank)}
                    </Text>
                    <View style={{ flex: 1, gap: 2 }}>
                      <Text variant="smallStrong" color={c.onNavy}>
                        {t('mock.you_in', 'You · {exam}', { exam: board.data.exam.title })}
                      </Text>
                      <Text variant="caption" color={c.onNavySoft}>
                        {[
                          `${Math.round(board.data.me.score_pct)}%`,
                          board.data.me.top_pct !== null ? t('mock.top_of', 'top {p}% of {n}', { p: board.data.me.top_pct, n: fmtNumber(board.data.me.takers) }) : null,
                          board.data.me.places_moved ? (board.data.me.places_moved > 0 ? t('mock.up_places', 'up {n} places', { n: board.data.me.places_moved }) : t('mock.down_places', 'down {n} places', { n: -board.data.me.places_moved })) : null,
                        ]
                          .filter(Boolean)
                          .join(' · ')}
                      </Text>
                    </View>
                  </Card>
                ) : null}
                {board.data.data.length === 0 ? (
                  <EmptyState icon="trophy" title={t('mock.board_empty', 'Nobody here yet')} body={scope === 'friends' ? t('mock.friends_empty', 'Invite classmates to compare scores.') : undefined} />
                ) : (
                  <Card>
                    {board.data.data.map((b, i) => (
                      <View key={`${b.rank}-${i}`} style={{ flexDirection: 'row', alignItems: 'center', gap: 12, paddingHorizontal: 14, minHeight: 56, borderTopWidth: i ? 1 : 0, borderTopColor: c.divider, backgroundColor: b.is_me ? c.tealWash : 'transparent' }}>
                        <Text variant="mono" style={{ width: 34 }} color={b.rank <= 3 ? c.gold : c.muted}>
                          {b.rank}
                        </Text>
                        <View style={{ width: 32, height: 32, borderRadius: 16, backgroundColor: c.chip, alignItems: 'center', justifyContent: 'center' }}>
                          <Text style={{ fontFamily: fonts.bodySemibold, fontSize: 11 }} color={c.textSoft}>
                            {b.initials}
                          </Text>
                        </View>
                        <View style={{ flex: 1 }}>
                          <Text variant="smallStrong" numberOfLines={1}>
                            {b.is_me ? t('mock.you', 'You') : b.name}
                          </Text>
                          {b.university ? (
                            <Text variant="caption" muted numberOfLines={1}>
                              {b.university}
                            </Text>
                          ) : null}
                        </View>
                        <Text variant="mono">{Math.round(b.score_pct)}%</Text>
                      </View>
                    ))}
                  </Card>
                )}
              </>
            )}
          </>
        )
      ) : null}

      {tab === 'results' ? (
        mocks.isError && !mocks.data ? (
          <ErrorState error={mocks.error} onRetry={() => mocks.refetch()} />
        ) : !mocks.data ? (
          <Skeleton height={240} />
        ) : mocks.data.data.length === 0 ? (
          <EmptyState icon="timer" title={t('progress.no_mocks', 'No mock exams yet')} body={t('mock.results_empty', 'Your timed mocks and grand mocks will be listed here.')} />
        ) : (
          mocks.data.data.map((r) => (
            <Card key={r.id} onPress={() => router.push(`/result/${r.id}`)} style={{ padding: 14, gap: 8 }}>
              <View style={{ flexDirection: 'row', alignItems: 'center', gap: 10 }}>
                <View style={{ flex: 1, gap: 2 }}>
                  <Text variant="caption" muted>
                    {fmtDate(r.completed_at)} · {fmtDuration(r.time_spent_seconds)}
                  </Text>
                  <Text variant="bodyStrong">{r.title}</Text>
                </View>
                <View style={{ alignItems: 'flex-end' }}>
                  <Text style={{ fontFamily: fonts.displayBold, fontSize: 22 }} color={r.score_pct >= 70 ? c.teal : r.score_pct >= 50 ? c.text : c.danger}>
                    {Math.round(r.score_pct)}%
                  </Text>
                  <Text variant="caption" muted>
                    {r.correct} / {r.total}
                  </Text>
                </View>
              </View>
              <View style={{ flexDirection: 'row', gap: 6, flexWrap: 'wrap' }}>
                <Badge label={r.level} tone="muted" />
                {r.top_pct !== null ? <Badge label={t('mock.top', 'Top {p}%', { p: r.top_pct })} tone="ok" /> : null}
              </View>
            </Card>
          ))
        )
      ) : null}
    </Screen>
  );
}
