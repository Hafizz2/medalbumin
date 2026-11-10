import { useRouter } from 'expo-router';
import { useEffect, useState } from 'react';
import { Alert, Pressable, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { ApiError } from '../../api/client';
import { useMatchProfile, useSpecialties, useUpdateMatchProfile } from '../../api/hooks';
import type { SpecialtyCard } from '../../api/types';
import { Button } from '../../components/Button';
import { ErrorState, Header, Screen, Skeleton } from '../../components/Screen';
import { Text } from '../../components/Text';
import { Check, ProgressBar } from '../../components/ui';
import { fonts, useTheme } from '../../theme';

/** 16 · Match step 2 — pick up to 2 specialties. */
export default function MatchSpecialties() {
  const { t } = useTranslation();
  const { c } = useTheme();
  const router = useRouter();
  const q = useSpecialties();
  const profile = useMatchProfile();
  const update = useUpdateMatchProfile();
  const [picked, setPicked] = useState<number[]>([]);

  useEffect(() => {
    if (profile.data) setPicked(profile.data.specialty_ids);
  }, [profile.data]);

  const max = q.data?.max ?? 2;
  const toggle = (id: number) => {
    if (picked.includes(id)) return setPicked(picked.filter((p) => p !== id));
    if (picked.length >= max) return Alert.alert(t('match.limit', 'Up to {n} specialties', { n: max }), t('match.limit_body', 'This is the official limit. Remove one to pick another.'));
    setPicked([...picked, id]);
  };

  const level: Record<SpecialtyCard['competition'], [string, string, string]> = {
    low: [t('match.low', 'Low competition'), c.tealSoft, c.teal],
    medium: [t('match.medium', 'Medium'), c.sandBg, c.sand],
    high: [t('match.high', 'High competition'), c.dangerBg, c.danger],
    very_high: [t('match.very_high', 'Very high'), c.stopBg, c.stop],
  };

  const next = () =>
    update.mutate(
      { specialty_ids: picked },
      {
        onSuccess: () => router.push(`/match/chances?specialty=${picked[0]}`),
        onError: (e) => Alert.alert(t('common.error', 'Something went wrong'), e instanceof ApiError ? e.message : ''),
      },
    );

  return (
    <Screen
      onRefresh={() => q.refetch()}
      footer={
        <Button title={t('match.see_chances', 'See my chances')} iconRight="arrowRight" disabled={!picked.length} loading={update.isPending} onPress={next} />
      }
    >
      <Header title={t('match.step2', 'Step 2 of 3 · Specialties')} />
      <ProgressBar value={66} height={4} />
      <View style={{ flexDirection: 'row', alignItems: 'flex-end', gap: 12 }}>
        <View style={{ flex: 1, gap: 4 }}>
          <Text variant="h1">{t('match.pick_title', 'Pick up to {n} specialties', { n: max })}</Text>
          <Text variant="small" muted>
            {t('match.picked', '{a} of {b} selected · official limit', { a: picked.length, b: max })}
          </Text>
        </View>
        {q.data ? (
          <Pressable onPress={() => router.back()} style={{ alignItems: 'flex-end' }} accessibilityRole="button" accessibilityLabel={t('match.edit_score', 'Edit score')}>
            <Text variant="caption" muted>
              {t('match.your_score', 'Your score')}
            </Text>
            <Text style={{ fontFamily: fonts.displayBold, fontSize: 22 }} color={c.teal}>
              {q.data.score}
            </Text>
          </Pressable>
        ) : null}
      </View>

      {q.isError && !q.data ? (
        <ErrorState error={q.error} onRetry={() => q.refetch()} />
      ) : !q.data ? (
        <Skeleton height={400} />
      ) : (
        <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 10 }}>
          {q.data.data.map((s) => {
            const on = picked.includes(s.id);
            const [lvl, bg, ink] = level[s.competition];
            const fit = s.gap === null ? null : s.gap >= 0 ? t('match.above', '+{n} above last year', { n: s.gap.toFixed(1) }) : t('match.below', '{n} below last year', { n: s.gap.toFixed(1) });
            return (
              <Pressable
                key={s.id}
                onPress={() => toggle(s.id)}
                accessibilityRole="checkbox"
                accessibilityState={{ checked: on }}
                accessibilityLabel={`${s.name}. ${lvl}. ${fit ?? ''}`}
                style={{ width: '48.4%', borderRadius: 18, padding: 14, gap: 8, borderWidth: on ? 2 : 1, borderColor: on ? c.teal : c.line, backgroundColor: on ? c.tealWash : c.card }}
              >
                <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' }}>
                  <View style={{ paddingHorizontal: 8, height: 22, borderRadius: 7, backgroundColor: bg, justifyContent: 'center' }}>
                    <Text style={{ fontFamily: fonts.bodySemibold, fontSize: 10 }} color={ink}>
                      {lvl}
                    </Text>
                  </View>
                  <Check checked={on} size={22} />
                </View>
                <Text variant="bodyStrong" style={{ fontSize: 15 }} numberOfLines={2}>
                  {s.name}
                </Text>
                <Text variant="caption" muted>
                  {s.lowest_matched !== null
                    ? t('match.lowest', 'Lowest matched {min} · {seats} seats', { min: s.lowest_matched, seats: s.seats })
                    : t('match.seats', '{seats} seats', { seats: s.seats })}
                </Text>
                {fit ? (
                  <Text variant="caption" style={{ fontFamily: fonts.bodySemibold }} color={s.gap! >= 0 ? c.teal : c.danger}>
                    {fit}
                  </Text>
                ) : null}
              </Pressable>
            );
          })}
        </View>
      )}
    </Screen>
  );
}
