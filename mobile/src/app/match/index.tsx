import { useRouter } from 'expo-router';
import { useEffect, useRef, useState } from 'react';
import { Alert, Pressable, TextInput, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { ApiError } from '../../api/client';
import { useMatchProfile, useUpdateMatchProfile } from '../../api/hooks';
import { Button } from '../../components/Button';
import { Icon } from '../../components/Icon';
import { EmptyState, ErrorState, Header, Screen, Skeleton } from '../../components/Screen';
import { Text } from '../../components/Text';
import { Card, ProgressBar, Switch } from '../../components/ui';
import { fonts, useTheme } from '../../theme';

const clamp = (n: number) => Math.min(100, Math.max(0, Math.round(n * 10) / 10));

/** 15 · Match step 1 — your score and official bonus points. */
export default function MatchScore() {
  const { t } = useTranslation();
  const { c } = useTheme();
  const router = useRouter();
  const q = useMatchProfile();
  const update = useUpdateMatchProfile();
  const [score, setScore] = useState(70);
  const [text, setText] = useState('70');
  const [female, setFemale] = useState(false);
  const [managerial, setManagerial] = useState(false);
  const loaded = useRef(false);
  const repeat = useRef<ReturnType<typeof setInterval> | null>(null);

  useEffect(() => {
    if (!q.data || loaded.current) return;
    loaded.current = true;
    const s = q.data.raw_score ?? q.data.predicted?.score ?? 70;
    setScore(s);
    setText(String(s));
    setFemale(q.data.is_female);
    setManagerial(q.data.has_managerial);
  }, [q.data]);

  useEffect(() => () => void (repeat.current && clearInterval(repeat.current)), []);

  const set = (n: number) => {
    const v = clamp(n);
    setScore(v);
    setText(String(v));
  };

  const step = (d: number) => setScore((s) => {
    const v = clamp(s + d);
    setText(String(v));
    return v;
  });

  const hold = (d: number) => {
    repeat.current = setInterval(() => step(d), 90);
  };
  const release = () => {
    if (repeat.current) clearInterval(repeat.current);
    repeat.current = null;
  };

  if (q.isError && !q.data) {
    return (
      <Screen>
        <Header />
        <ErrorState error={q.error} onRetry={() => q.refetch()} />
      </Screen>
    );
  }
  if (!q.data) {
    return (
      <Screen>
        <Header />
        <Skeleton height={60} />
        <Skeleton height={140} />
        <Skeleton height={200} />
      </Screen>
    );
  }

  const rules = q.data.rules;
  if (!q.data.has_data) {
    return (
      <Screen>
        <Header />
        <EmptyState icon="pin" title={t('match.no_data', 'Match data is coming soon')} body={t('match.no_data_body', 'We are loading past residency cut-offs. Check back shortly.')} />
      </Screen>
    );
  }

  const pct = (female ? rules.female_bonus_pct : 0) + (managerial ? rules.managerial_bonus_pct : 0);
  const final = clamp(Math.min(100, score * (1 + pct / 100)));
  const breakdown = [`${score}`, female ? `+${rules.female_bonus_pct}%` : null, managerial ? `+${rules.managerial_bonus_pct}%` : null].filter(Boolean).join(' ');

  const next = () =>
    update.mutate(
      { raw_score: score, is_female: female, has_managerial: managerial },
      {
        onSuccess: () => router.push('/match/specialties'),
        onError: (e) => Alert.alert(t('common.error', 'Something went wrong'), e instanceof ApiError ? e.message : ''),
      },
    );

  const bonuses = [
    { label: t('match.female', 'Female applicant'), sub: t('match.bonus', '+{p}% on your score', { p: rules.female_bonus_pct }), value: female, set: setFemale },
    { label: t('match.managerial', 'Managerial role'), sub: t('match.managerial_sub', '+{p}% · e.g. medical director, head of unit', { p: rules.managerial_bonus_pct }), value: managerial, set: setManagerial },
  ];

  return (
    <Screen footer={<Button title={t('match.next_specialties', 'Next: choose specialties')} iconRight="arrowRight" loading={update.isPending} onPress={next} />}>
      <Header title={t('match.step1', 'Step 1 of 3 · Your score')} />
      <ProgressBar value={33} height={4} />
      <View style={{ gap: 6 }}>
        <Text variant="title">{t('match.score_title', 'What’s your score?')}</Text>
        <Text variant="body" muted>
          {t('match.score_body', 'Your residency entrance exam score (average of Part I and II). Not taken it yet? Enter the score you expect.')}
        </Text>
      </View>

      <Card style={{ padding: 18, gap: 14, alignItems: 'center' }}>
        <View style={{ flexDirection: 'row', alignItems: 'center', gap: 16 }}>
          <Pressable onPress={() => step(-0.5)} onLongPress={() => hold(-0.5)} onPressOut={release} accessibilityRole="button" accessibilityLabel={t('match.decrease', 'Decrease score')} style={{ width: 52, height: 52, borderRadius: 16, backgroundColor: c.chip, alignItems: 'center', justifyContent: 'center' }}>
            <Text style={{ fontFamily: fonts.display, fontSize: 26 }}>−</Text>
          </Pressable>
          <View style={{ flexDirection: 'row', alignItems: 'baseline' }}>
            <TextInput
              value={text}
              onChangeText={(v) => {
                setText(v.replace(/[^0-9.]/g, ''));
                const n = Number(v);
                if (!Number.isNaN(n) && v !== '') setScore(clamp(n));
              }}
              onBlur={() => set(score)}
              keyboardType="decimal-pad"
              maxLength={5}
              accessibilityLabel={t('match.score', 'Score')}
              style={{ fontFamily: fonts.displayBold, fontSize: 56, color: c.text, minWidth: 120, textAlign: 'center', padding: 0 }}
            />
            <Text style={{ fontFamily: fonts.display, fontSize: 24 }} muted>
              %
            </Text>
          </View>
          <Pressable onPress={() => step(0.5)} onLongPress={() => hold(0.5)} onPressOut={release} accessibilityRole="button" accessibilityLabel={t('match.increase', 'Increase score')} style={{ width: 52, height: 52, borderRadius: 16, backgroundColor: c.chip, alignItems: 'center', justifyContent: 'center' }}>
            <Text style={{ fontFamily: fonts.display, fontSize: 26 }}>+</Text>
          </Pressable>
        </View>
        <View style={{ width: '100%' }}>
          <ProgressBar value={score} height={8} />
        </View>
      </Card>

      {q.data.predicted ? (
        <Pressable onPress={() => set(q.data!.predicted!.score)} accessibilityRole="button" style={{ flexDirection: 'row', alignItems: 'center', gap: 10, padding: 14, borderRadius: 16, backgroundColor: c.tealSoft }}>
          <Icon name="sparkle" size={18} color={c.teal} />
          <Text variant="smallStrong" style={{ flex: 1 }} color={c.tealInk}>
            {t('match.use_predicted', 'Use my predicted score from {n} mocks · {s}%', { n: q.data.predicted.from_mocks, s: q.data.predicted.score })}
          </Text>
        </Pressable>
      ) : null}

      <Text variant="label" muted>
        {t('match.extra', 'Extra points (official rules)')}
      </Text>
      <Card>
        {bonuses.map((b, i) => (
          <View key={b.label} style={{ flexDirection: 'row', alignItems: 'center', gap: 12, padding: 14, borderTopWidth: i ? 1 : 0, borderTopColor: c.divider }}>
            <View style={{ flex: 1, gap: 2 }}>
              <Text variant="bodyStrong">{b.label}</Text>
              <Text variant="caption" muted>
                {b.sub}
              </Text>
            </View>
            <Switch value={b.value} onChange={b.set} label={b.label} />
          </View>
        ))}
      </Card>

      <Card tone="navy" style={{ padding: 18, flexDirection: 'row', alignItems: 'center', gap: 12 }}>
        <View style={{ flex: 1, gap: 2 }}>
          <Text variant="smallStrong" color={c.onNavy}>
            {t('match.your_matching', 'Your matching score')}
          </Text>
          <Text variant="caption" color={c.onNavySoft}>
            {breakdown}
          </Text>
        </View>
        <Text style={{ fontFamily: fonts.displayBold, fontSize: 36 }} color={c.accent}>
          {final}
        </Text>
      </Card>
    </Screen>
  );
}
