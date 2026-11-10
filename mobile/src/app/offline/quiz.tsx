import { Image } from 'expo-image';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { useEffect, useRef, useState } from 'react';
import { View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { Button } from '../../components/Button';
import { OptionButton } from '../../components/quiz/OptionButton';
import { EmptyState, Header, Screen, Skeleton } from '../../components/Screen';
import { Text } from '../../components/Text';
import { Badge, Card, ProgressBar, Ring } from '../../components/ui';
import { loadPack, OfflineQuestion, recordAttempt, useOffline } from '../../offline/store';
import { useSettings } from '../../store/settings';
import { fonts, useTheme } from '../../theme';

/** Fisher–Yates shuffle. */
function shuffle<T>(list: T[]): T[] {
  const a = [...list];
  for (let i = a.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [a[i], a[j]] = [a[j], a[i]];
  }
  return a;
}

/** Practice from a downloaded pack. Works with no connection; answers sync later. */
export default function OfflineQuiz() {
  const params = useLocalSearchParams<{ pack: string; sub?: string; size?: string; only?: string }>();
  const { t } = useTranslation();
  const { c } = useTheme();
  const router = useRouter();
  const largeText = useSettings((s) => s.largeText);
  const [questions, setQuestions] = useState<OfflineQuestion[] | null>(null);
  const [index, setIndex] = useState(0);
  const [picked, setPicked] = useState<string | null>(null);
  const [score, setScore] = useState({ correct: 0, answered: 0 });
  const shownAt = useRef(Date.now());

  // Pick the set once: unseen questions first, then wrong ones, then the rest.
  useEffect(() => {
    loadPack(Number(params.pack)).then((pack) => {
      if (!pack) return setQuestions([]);
      const answered = useOffline.getState().answered;
      let pool = pack.questions;
      if (params.sub) pool = pool.filter((q) => String(q.subcategory_id ?? 0) === params.sub);
      if (params.only === 'wrong') pool = pool.filter((q) => answered[q.id] === false);
      const rank = (q: OfflineQuestion) => (!(q.id in answered) ? 0 : answered[q.id] ? 2 : 1);
      const ordered = [0, 1, 2].flatMap((r) => shuffle(pool.filter((q) => rank(q) === r)));
      setQuestions(ordered.slice(0, Number(params.size) || 20));
    });
  }, [params.pack, params.sub, params.size, params.only]);

  if (!questions) {
    return (
      <Screen>
        <Header />
        <Skeleton height={400} />
      </Screen>
    );
  }
  if (!questions.length) {
    return (
      <Screen>
        <Header />
        <EmptyState icon="question" title={t('offline.no_questions', 'No questions here')} body={t('offline.no_questions_body', 'Try another topic, or update this download.')} />
      </Screen>
    );
  }

  const done = index >= questions.length;
  if (done) {
    const pct = score.answered ? Math.round((score.correct / score.answered) * 100) : 0;
    return (
      <Screen>
        <Header backIcon="close" />
        <View style={{ alignItems: 'center', gap: 14, paddingTop: 20 }}>
          <Ring value={pct} size={120} stroke={12} color={pct >= 70 ? c.teal : pct >= 50 ? c.amber : c.danger} track={c.chip} label={`${pct}%`} />
          <Text variant="h1">{t('offline.done_title', 'Session complete')}</Text>
          <Text variant="body" muted align="center">
            {t('offline.done_body', '{c} of {n} correct. Your answers count toward your progress once you’re online.', { c: score.correct, n: score.answered })}
          </Text>
        </View>
        <Button title={t('offline.again', 'Practise another set')} onPress={() => router.replace({ pathname: '/offline/quiz', params })} />
        <Button title={t('offline.back_to_pack', 'Back to topics')} variant="soft" onPress={() => router.back()} />
      </Screen>
    );
  }

  const q = questions[index];
  const options = q.options.map((o, i) => ({ key: o.key, label: String.fromCharCode(65 + i), text: o.text, pick_pct: null }));
  const right = options.find((o) => o.key === q.correct);

  const pick = (key: string) => {
    if (picked) return;
    const correct = key === q.correct;
    setPicked(key);
    setScore((s) => ({ correct: s.correct + (correct ? 1 : 0), answered: s.answered + 1 }));
    recordAttempt(q.id, key, correct, Date.now() - shownAt.current);
  };

  const next = () => {
    setPicked(null);
    setIndex(index + 1);
    shownAt.current = Date.now();
  };

  const state = (key: string) => (!picked ? 'idle' : key === q.correct ? 'correct' : key === picked ? 'wrong' : 'dim');

  return (
    <Screen
      footer={
        picked ? (
          <Button title={index + 1 < questions.length ? t('quiz.next', 'Next →') : t('quiz.see_results', 'See results')} onPress={next} />
        ) : undefined
      }
    >
      <Header backIcon="close" title={t('offline.question_of', 'Question {n} of {total}', { n: index + 1, total: questions.length })} right={<Badge label={t('offline.badge_short', 'Offline')} tone="sky" />} />
      <ProgressBar value={(index / questions.length) * 100} height={4} />

      <View style={{ flexDirection: 'row', gap: 6 }}>
        {q.code ? <Badge label={q.code} tone="muted" /> : null}
        {q.year ? <Badge label={String(q.year)} tone="muted" /> : null}
      </View>

      <Text style={{ fontFamily: fonts.bodyMedium, fontSize: largeText ? 19 : 16, lineHeight: largeText ? 28 : 24 }}>{q.stem}</Text>
      {q.image ? <Image source={{ uri: q.image.url }} cachePolicy="disk" contentFit="contain" style={{ width: '100%', aspectRatio: 4 / 3, borderRadius: 16, backgroundColor: c.chip }} accessibilityLabel={q.image.caption ?? t('quiz.image', 'Question image')} /> : null}

      <View style={{ gap: 10 }}>
        {options.map((o) => (
          <OptionButton key={o.key} option={o} state={state(o.key)} large={largeText} disabled={!!picked} onPress={() => pick(o.key)} />
        ))}
      </View>

      {picked ? (
        <Card style={{ padding: 16, gap: 8 }}>
          <Text variant="smallStrong" color={picked === q.correct ? c.teal : c.danger}>
            {picked === q.correct ? t('quiz.correct', 'Correct — well done') : t('quiz.not_quite', 'Not quite · Answer is {key}', { key: right?.label ?? q.correct })}
          </Text>
          <Text variant="small" style={{ lineHeight: 21 }}>
            {q.explanation || t('quiz.no_explanation', 'No explanation yet for this question.')}
          </Text>
        </Card>
      ) : null}
    </Screen>
  );
}
