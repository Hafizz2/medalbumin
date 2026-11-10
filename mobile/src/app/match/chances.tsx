import { useLocalSearchParams, useRouter } from 'expo-router';
import { useEffect, useState } from 'react';
import { Alert, Pressable, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { ApiError } from '../../api/client';
import { useChances, useMatchProfile, useSaveChoices, useSpecialties } from '../../api/hooks';
import type { Chances } from '../../api/types';
import { Button } from '../../components/Button';
import { Icon } from '../../components/Icon';
import { EmptyState, ErrorState, Header, Screen, Skeleton } from '../../components/Screen';
import { Text } from '../../components/Text';
import { Card, ProgressBar, Segmented } from '../../components/ui';
import { fonts, useTheme } from '../../theme';

/** 17 · Match step 3 — chances per university for each picked specialty. */
export default function MatchChances() {
  const { specialty } = useLocalSearchParams<{ specialty?: string }>();
  const { t } = useTranslation();
  const { c } = useTheme();
  const router = useRouter();
  const profile = useMatchProfile();
  const specialties = useSpecialties();
  const ids = profile.data?.specialty_ids ?? [];
  const [active, setActive] = useState<number>(Number(specialty) || 0);

  useEffect(() => {
    if (!active && ids.length) setActive(ids[0]);
  }, [active, ids]);

  const q = useChances(active);
  const save = useSaveChoices();
  const [picked, setPicked] = useState<Record<number, number[]>>({});

  useEffect(() => {
    if (q.data && picked[q.data.specialty.id] === undefined) setPicked((p) => ({ ...p, [q.data!.specialty.id]: q.data!.picked }));
  }, [q.data, picked]);

  const names = Object.fromEntries((specialties.data?.data ?? []).map((s) => [s.id, s.name]));
  const mine = picked[active] ?? q.data?.picked ?? [];
  const max = q.data?.max ?? 3;
  const totalChosen = ids.reduce((n, id) => n + (picked[id]?.length ?? 0), 0);

  const toggle = (uid: number) => {
    const next = mine.includes(uid) ? mine.filter((x) => x !== uid) : mine.length >= max ? null : [...mine, uid];
    if (!next) return Alert.alert(t('match.uni_limit', 'Up to {n} universities', { n: max }), t('match.uni_limit_body', 'Remove one to add another.'));
    setPicked({ ...picked, [active]: next });
    save.mutate(
      { specialtyId: active, universityIds: next },
      {
        onError: (e) => {
          setPicked((p) => ({ ...p, [active]: mine }));
          Alert.alert(t('common.error', 'Something went wrong'), e instanceof ApiError ? e.message : '');
        },
      },
    );
  };

  return (
    <Screen
      onRefresh={() => q.refetch()}
      footer={
        <Button
          title={t('match.review_list', 'Review my choice list')}
          iconRight="arrowRight"
          disabled={!totalChosen && !mine.length}
          onPress={() => router.push('/match/choices')}
          trailing={
            <Text variant="mono" style={{ fontSize: 13 }} color="#A9BBD8">
              {mine.length} / {max}
            </Text>
          }
        />
      }
    >
      <Header
        title={t('match.step3', 'Step 3 of 3 · Your chances')}
        right={
          q.data ? (
            <Pressable onPress={() => router.navigate('/match')} accessibilityRole="button" accessibilityLabel={t('match.edit_score', 'Edit score')} hitSlop={8}>
              <Text variant="caption" muted>
                {t('match.score_edit', 'Score · edit')}
              </Text>
              <Text style={{ fontFamily: fonts.displayBold, fontSize: 18, textAlign: 'right' }} color={c.teal}>
                {q.data.score}
              </Text>
            </Pressable>
          ) : undefined
        }
      />
      <ProgressBar value={100} height={4} />

      {ids.length > 1 ? <Segmented<string> value={String(active)} onChange={(v) => setActive(Number(v))} options={ids.map((id) => ({ id: String(id), label: names[id] ?? '…' }))} /> : null}

      {!active && profile.data ? (
        <EmptyState icon="pin" title={t('match.pick_first', 'Pick a specialty first')} action={t('match.choose', 'Choose specialties')} onAction={() => router.replace('/match/specialties')} />
      ) : q.isError && !q.data ? (
        <ErrorState error={q.error} onRetry={() => q.refetch()} />
      ) : !q.data ? (
        <>
          <Skeleton height={90} />
          <Skeleton height={360} />
        </>
      ) : (
        <ChanceList data={q.data} mine={mine} max={max} onToggle={toggle} />
      )}
    </Screen>
  );
}

function ChanceList({ data, mine, max, onToggle }: { data: Chances; mine: number[]; max: number; onToggle: (id: number) => void }) {
  const { t } = useTranslation();
  const { c } = useTheme();
  const tier = {
    likely: [t('match.likely', 'Likely'), c.teal, c.tealSoft],
    possible: [t('match.possible', 'Possible'), c.sand, c.sandBg],
    reach: [t('match.reach', 'Reach'), c.danger, c.dangerBg],
  } as const;
  const range = data.years.length ? `${data.years[0]}–${data.years[data.years.length - 1]}` : '';

  return (
    <>
      <View style={{ gap: 4 }}>
        <Text variant="h1">{data.specialty.name}</Text>
        <Text variant="small" muted>
          {t('match.summary', 'Likely at {a} of {b} universities', { a: data.likely, b: data.universities.length })} · {t('match.data_years', '{range} data', { range })}
        </Text>
      </View>

      {data.more_likely_with_3 > 0 ? (
        <View style={{ flexDirection: 'row', gap: 10, alignItems: 'center', padding: 14, borderRadius: 16, backgroundColor: c.tealSoft }}>
          <Icon name="sparkle" size={16} color={c.teal} />
          <Text variant="small" style={{ flex: 1 }} color={c.tealInk}>
            {t('match.what_if', 'Score 3 points more and {n} more universities become likely.', { n: data.more_likely_with_3 })}
          </Text>
        </View>
      ) : null}

      {data.universities.length === 0 ? (
        <EmptyState icon="pin" title={t('match.no_unis', 'No university data for this specialty yet')} />
      ) : (
        <Card>
          {data.universities.map((u, i) => {
            const on = mine.includes(u.university_id);
            const [label, ink, bg] = tier[u.tier];
            const arrow = u.direction === 'up' ? '↑' : u.direction === 'down' ? '↓' : '→';
            const full = !on && mine.length >= max;
            return (
              <View key={u.university_id} style={{ flexDirection: 'row', alignItems: 'center', gap: 12, padding: 14, borderTopWidth: i ? 1 : 0, borderTopColor: c.divider, backgroundColor: on ? c.tealWash : 'transparent' }}>
                <Pressable
                  onPress={() => onToggle(u.university_id)}
                  accessibilityRole="checkbox"
                  accessibilityState={{ checked: on, disabled: full }}
                  accessibilityLabel={on ? t('match.remove', 'Remove {name}', { name: u.name }) : t('match.add', 'Add {name}', { name: u.name })}
                  style={{ width: 44, height: 44, borderRadius: 12, alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: on ? c.teal : c.field, backgroundColor: on ? c.teal : c.card, opacity: full ? 0.45 : 1 }}
                >
                  <Icon name={on ? 'check' : 'plus'} size={18} color={on ? '#FFFFFF' : c.text} />
                </Pressable>
                <View style={{ flex: 1, gap: 3 }}>
                  <Text variant="bodyStrong" style={{ fontSize: 14 }} numberOfLines={2}>
                    {u.name}
                  </Text>
                  <Text variant="mono" style={{ fontSize: 12 }} muted>
                    {u.cutoffs.map((x) => (x === null ? '–' : x)).join(' → ')} {arrow} · {t('match.seats', '{seats} seats', { seats: u.seats })}
                  </Text>
                </View>
                <View style={{ alignItems: 'flex-end', gap: 3 }}>
                  <Text style={{ fontFamily: fonts.displayBold, fontSize: 20 }} color={ink}>
                    {u.chance}%
                  </Text>
                  <View style={{ paddingHorizontal: 7, height: 20, borderRadius: 6, backgroundColor: bg, justifyContent: 'center' }}>
                    <Text style={{ fontFamily: fonts.bodySemibold, fontSize: 10 }} color={ink}>
                      {label}
                    </Text>
                  </View>
                </View>
              </View>
            );
          })}
        </Card>
      )}

      <Text variant="caption" muted align="center">
        {t('match.cutoff_note', 'Cut-offs shown for {years}.', { years: data.years.join(' → ') })} {data.disclaimer}
      </Text>
    </>
  );
}
