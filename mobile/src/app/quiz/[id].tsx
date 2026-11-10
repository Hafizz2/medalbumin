import { useQueryClient } from '@tanstack/react-query';
import { Image } from 'expo-image';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { ComponentRef, useEffect, useMemo, useRef, useState } from 'react';
import { Alert, Modal, Pressable, ScrollView, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useTranslation } from 'react-i18next';
import { ApiError } from '../../api/client';
import { answerQuestion, qk, toggleBookmark, useConfig, useFinishQuiz, useSaveMyNote, useSession } from '../../api/hooks';
import type { Question, SessionPayload } from '../../api/types';
import { Button, IconButton } from '../../components/Button';
import { Icon } from '../../components/Icon';
import { OptionButton } from '../../components/quiz/OptionButton';
import { ReportSheet } from '../../components/quiz/ReportSheet';
import { ErrorState, Loading } from '../../components/Screen';
import { Text } from '../../components/Text';
import { fmtDuration } from '../../lib/format';
import { useSettings } from '../../store/settings';
import { fonts, useTheme } from '../../theme';

/** 05 · Question & explanation — practice, timed exam and review in one screen. */
export default function Quiz() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const sessionId = Number(id);
  const { t } = useTranslation();
  const { c } = useTheme();
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const qc = useQueryClient();
  const session = useSession(sessionId);
  const finish = useFinishQuiz();
  const saveNote = useSaveMyNote();
  const config = useConfig();
  const largeText = useSettings((s) => s.largeText);
  const setLargeText = useSettings((s) => s.setLargeText);

  const [index, setIndex] = useState<number | null>(null);
  const [sending, setSending] = useState(false);
  const [sheet, setSheet] = useState(false);
  const [expLocale, setExpLocale] = useState<string | null>(null);
  const [zoom, setZoom] = useState(false);
  const [report, setReport] = useState(false);
  const [saved, setSaved] = useState<Record<number, boolean>>({});
  const [now, setNow] = useState(Date.now());
  const shownAt = useRef(Date.now());
  const scroll = useRef<ComponentRef<typeof ScrollView>>(null);

  const data = session.data;
  const s = data?.session;
  const items = data?.questions ?? [];
  const review = s?.status === 'completed';
  const timed = s?.mode === 'timed' && !review;

  // Start at the first unanswered question (resume), or the first one in review.
  useEffect(() => {
    if (data && index === null) {
      const first = review ? 0 : items.findIndex((q) => !q.answered);
      setIndex(first === -1 ? 0 : first);
    }
  }, [data, index, review, items]);

  // Clock tick for the timer.
  useEffect(() => {
    if (!s || review) return;
    const h = setInterval(() => setNow(Date.now()), 1000);
    return () => clearInterval(h);
  }, [s, review]);

  const deadline = s?.deadline ? new Date(s.deadline).getTime() : null;
  const secondsLeft = deadline ? Math.max(0, Math.round((deadline - now) / 1000)) : null;
  const elapsed = s?.started_at ? Math.round((now - new Date(s.started_at).getTime()) / 1000) : 0;

  const done = async () => {
    if (finish.isPending) return;
    try {
      await finish.mutateAsync(sessionId);
      router.replace(`/result/${sessionId}`);
    } catch (e) {
      Alert.alert(t('common.error_title', 'Something went wrong'), e instanceof ApiError ? e.message : '');
    }
  };

  // Time's up → submit automatically.
  useEffect(() => {
    if (timed && secondsLeft === 0) void done();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [timed, secondsLeft]);

  const item = index !== null ? items[index] : undefined;
  const question = item?.question;
  const revealed = !!question?.correct_option;

  useEffect(() => {
    shownAt.current = Date.now();
    setSheet(revealed && !timed);
    setExpLocale(null);
    scroll.current?.scrollTo({ y: 0, animated: false });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [index]);

  /** Replace one question in the cached session after answering. */
  const patch = (position: number, change: (q: SessionPayload['questions'][number]) => SessionPayload['questions'][number]) =>
    qc.setQueryData<SessionPayload>(qk.session(sessionId), (old) =>
      old ? { ...old, questions: old.questions.map((q) => (q.position === position ? change(q) : q)) } : old,
    );

  const pick = async (key: string) => {
    if (!item || !question || review || sending) return;
    if (!timed && item.answered) return;
    setSending(true);
    try {
      const res = await answerQuestion(sessionId, { question_id: question.id, option: key, time_ms: Date.now() - shownAt.current });
      shownAt.current = Date.now();
      patch(item.position, (q) => ({
        ...q,
        answered: true,
        is_correct: res.is_correct ?? null,
        question: res.question ? { ...res.question, bookmarked: q.question.bookmarked, selected: key } : { ...q.question, selected: key },
      }));
      if (!timed) setSheet(true);
    } catch (e) {
      if (e instanceof ApiError && e.kind === 'premium') {
        Alert.alert(t('quiz.free_used_title', 'Free questions used'), e.message, [{ text: t('common.ok', 'OK'), onPress: () => router.replace('/sign-in?mode=signup') }]);
      } else {
        Alert.alert(t('common.error_title', 'Something went wrong'), e instanceof ApiError ? e.message : '');
      }
    } finally {
      setSending(false);
    }
  };

  const go = (to: number) => {
    if (to >= items.length) {
      if (review) return router.back();
      return confirmFinish();
    }
    setIndex(Math.max(0, to));
  };

  const confirmFinish = () => {
    const unanswered = items.filter((q) => !q.answered).length;
    Alert.alert(
      t('quiz.finish_title', 'Finish this session?'),
      unanswered ? t('quiz.finish_unanswered', '{count} questions are not answered yet.', { count: unanswered }) : t('quiz.finish_body', 'You will see your results.'),
      [
        { text: t('common.cancel', 'Cancel'), style: 'cancel' },
        { text: t('quiz.finish', 'Finish'), onPress: done },
      ],
    );
  };

  const close = () => {
    if (review || !s) return router.back();
    if (!items.some((q) => q.answered)) return router.back();
    Alert.alert(t('quiz.end_title', 'End session?'), t('quiz.end_body', 'Your answers are saved. You can finish now to see results, or continue later.'), [
      { text: t('quiz.keep_going', 'Keep going'), style: 'cancel' },
      { text: t('quiz.later', 'Continue later'), onPress: () => router.back() },
      { text: t('quiz.finish', 'Finish'), style: 'destructive', onPress: done },
    ]);
  };

  const bookmark = async () => {
    if (!item || !question) return;
    const res = await toggleBookmark(question.id).catch(() => null);
    if (res) patch(item.position, (q) => ({ ...q, question: { ...q.question, bookmarked: res.bookmarked } }));
  };

  const saveToNotes = (q: Question) => {
    if (saved[q.id]) return;
    const right = q.options.find((o) => o.key === q.correct_option);
    const body = `${q.stem}\n\n${t('quiz.answer_line', 'Answer')}: ${right ? `${right.label}. ${right.text}` : q.correct_option}${q.explanation ? `\n\n${q.explanation}` : ''}`;
    saveNote.mutate({ question_id: q.id, title: q.breadcrumb, body: body.slice(0, 19000) }, { onSuccess: () => setSaved((m) => ({ ...m, [q.id]: true })) });
  };

  const languages = config.data?.languages ?? [];
  const explanations = question?.explanations ?? {};
  const expCodes = Object.keys(explanations);
  const shownExpLocale = expLocale && explanations[expLocale] ? expLocale : question?.locale ?? 'en';
  const explanation = explanations[shownExpLocale] ?? question?.explanation ?? '';
  const shortLabel = (code: string) => {
    const native = languages.find((l) => l.code === code)?.native_name ?? code;
    return /[^\u0000-ɏ]/.test(native) ? native.slice(0, 2) : code.toUpperCase();
  };

  const optionState = (key: string): 'idle' | 'selected' | 'correct' | 'wrong' | 'dim' => {
    const sel = question?.selected;
    if (revealed) {
      if (key === question?.correct_option) return 'correct';
      if (key === sel) return 'wrong';
      return 'dim';
    }
    return key === sel ? 'selected' : 'idle';
  };

  const answeredCount = useMemo(() => items.filter((q) => q.answered).length, [items]);

  if (session.isError && !data) {
    return (
      <View style={{ flex: 1, backgroundColor: c.page, paddingTop: insets.top + 40 }}>
        <ErrorState error={session.error} onRetry={() => session.refetch()} />
      </View>
    );
  }
  if (!data || index === null || !question || !item) {
    return (
      <View style={{ flex: 1, backgroundColor: c.page, paddingTop: insets.top + 80 }}>
        <Loading />
      </View>
    );
  }

  const progress = ((index + 1) / items.length) * 100;
  const clock = timed && secondsLeft !== null ? fmtDuration(secondsLeft, true) : review ? null : fmtDuration(elapsed, true);
  const canBack = !timed || s.allow_back;
  const verdict = item.is_correct ? t('quiz.correct', 'Correct — well done') : t('quiz.not_quite', 'Not quite · Answer is {key}', { key: question.options.find((o) => o.key === question.correct_option)?.label ?? question.correct_option });

  return (
    <View style={{ flex: 1, backgroundColor: c.page }}>
      <ScrollView ref={scroll} contentContainerStyle={{ paddingTop: insets.top + 10, paddingHorizontal: 20, paddingBottom: sheet ? 380 : insets.bottom + 120, gap: 14 }} showsVerticalScrollIndicator={false}>
        {/* Top bar */}
        <View style={{ flexDirection: 'row', alignItems: 'center', gap: 10 }}>
          <IconButton icon="close" label={t('quiz.end_session', 'End session')} onPress={close} />
          <View style={{ flex: 1, gap: 6 }}>
            <View style={{ flexDirection: 'row', justifyContent: 'space-between' }}>
              <Text style={{ fontFamily: fonts.bodySemibold, fontSize: 12 }}>
                {index + 1} <Text style={{ fontFamily: fonts.bodyMedium, fontSize: 12 }} muted>/ {items.length}</Text>
              </Text>
              {clock ? (
                <Text variant="mono" style={{ fontSize: 12 }} color={timed && (secondsLeft ?? 99) < 60 ? c.danger : c.muted} accessibilityLabel={t('quiz.time_left', 'Time left')}>
                  {clock}
                </Text>
              ) : null}
            </View>
            <View style={{ height: 6, borderRadius: 99, backgroundColor: c.line, overflow: 'hidden' }}>
              <View style={{ width: `${progress}%`, height: '100%', backgroundColor: c.text, borderRadius: 99 }} />
            </View>
          </View>
          <IconButton label={t('quiz.text_size', 'Text size')} active={largeText} onPress={() => setLargeText(!largeText)}>
            <Text style={{ fontFamily: fonts.displayBold, fontSize: 15 }} color={largeText ? c.accent : c.text}>
              Aa
            </Text>
          </IconButton>
          <IconButton icon="flask" label={t('quiz.tools', 'Lab values and calculators')} onPress={() => router.push('/tools')} />
          <IconButton icon="bookmark" label={t('quiz.bookmark', 'Bookmark question')} active={!!question.bookmarked} onPress={bookmark} />
        </View>

        {/* Tags */}
        <View style={{ flexDirection: 'row', gap: 6, flexWrap: 'wrap' }}>
          {question.breadcrumb ? <Tag label={question.breadcrumb} bg={c.tealSoft} ink={c.teal} /> : null}
          {question.source ? <Tag label={question.source} bg={c.chip} ink={c.textSoft} /> : null}
          <Tag label={{ easy: t('quiz.easy', 'Easy'), moderate: t('quiz.moderate', 'Moderate'), hard: t('quiz.hard', 'Hard') }[question.difficulty]} bg={c.chip} ink={c.textSoft} />
        </View>

        <Text style={{ fontSize: largeText ? 18 : 16, lineHeight: largeText ? 27 : 23 }} selectable>
          {question.stem}
        </Text>

        {question.image ? (
          <Pressable accessibilityRole="imagebutton" accessibilityLabel={question.image.caption ?? t('quiz.image', 'Question image')} onPress={() => setZoom(true)} style={{ backgroundColor: c.card, borderWidth: 1, borderColor: c.line, borderRadius: 16, padding: 8, gap: 6 }}>
            <Image source={{ uri: question.image.url }} style={{ width: '100%', height: 180, borderRadius: 10 }} contentFit="contain" cachePolicy="disk" transition={150} />
            <View style={{ flexDirection: 'row', justifyContent: 'space-between' }}>
              <Text style={{ fontSize: 11, flex: 1 }} muted numberOfLines={1}>
                {question.image.caption ?? ''}
              </Text>
              <Text style={{ fontSize: 11 }} muted>
                {t('quiz.tap_zoom', 'Tap to zoom')}
              </Text>
            </View>
          </Pressable>
        ) : null}

        <View style={{ gap: 8 }} accessibilityRole="radiogroup">
          {question.options.map((o) => (
            <OptionButton key={o.key} option={o} state={optionState(o.key)} large={largeText} disabled={review || (revealed && !timed) || sending} onPress={() => pick(o.key)} />
          ))}
        </View>

        {revealed && !sheet ? (
          <Button title={t('quiz.show_explanation', 'Show explanation')} variant="light" size="md" onPress={() => setSheet(true)} />
        ) : null}
      </ScrollView>

      {/* Timed / review navigation */}
      {!sheet ? (
        <View style={{ position: 'absolute', left: 0, right: 0, bottom: 0, paddingHorizontal: 20, paddingTop: 10, paddingBottom: insets.bottom + 12, backgroundColor: c.page, borderTopWidth: 1, borderTopColor: c.divider, gap: 10 }}>
          {timed ? (
            <Text variant="caption" muted align="center">
              {t('quiz.answered_of', '{answered} of {total} answered', { answered: answeredCount, total: items.length })}
            </Text>
          ) : null}
          <View style={{ flexDirection: 'row', gap: 8 }}>
            {canBack ? <Button title="←" variant="light" style={{ width: 64 }} disabled={index === 0} onPress={() => go(index - 1)} accessibilityHint={t('quiz.previous', 'Previous question')} /> : null}
            {timed && index === items.length - 1 ? (
              <Button title={t('quiz.submit', 'Submit exam')} variant="teal" style={{ flex: 1 }} loading={finish.isPending} onPress={confirmFinish} />
            ) : (
              <Button
                title={review && index === items.length - 1 ? t('quiz.done_review', 'Done') : t('quiz.next', 'Next →')}
                style={{ flex: 1 }}
                disabled={!timed && !review && !item.answered}
                onPress={() => go(index + 1)}
              />
            )}
          </View>
        </View>
      ) : null}

      {/* Explanation sheet */}
      {sheet ? (
        <View style={{ position: 'absolute', left: 0, right: 0, bottom: 0, maxHeight: '62%', backgroundColor: '#062A5E', borderTopLeftRadius: 28, borderTopRightRadius: 28, paddingTop: 10, paddingHorizontal: 20, paddingBottom: insets.bottom + 16, gap: 12, shadowColor: '#062A5E', shadowOpacity: 0.25, shadowRadius: 40, elevation: 16 }}>
          <Pressable onPress={() => setSheet(false)} accessibilityRole="button" accessibilityLabel={t('quiz.hide_explanation', 'Hide explanation')} hitSlop={12} style={{ alignSelf: 'center' }}>
            <View style={{ width: 40, height: 4, borderRadius: 99, backgroundColor: '#3D63A0' }} />
          </Pressable>
          <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 8 }}>
            <Text style={{ fontFamily: fonts.display, fontSize: 20, flex: 1 }} color={item.is_correct ? c.accent : '#FBBF77'} accessibilityLiveRegion="polite">
              {review && !item.answered ? t('quiz.not_answered', 'Not answered · Answer is {key}', { key: question.options.find((o) => o.key === question.correct_option)?.label ?? '' }) : verdict}
            </Text>
            {expCodes.length > 1 ? (
              <View accessibilityRole="tablist" style={{ flexDirection: 'row', backgroundColor: '#12407F', borderRadius: 10, padding: 3 }}>
                {expCodes.map((code) => {
                  const on = code === shownExpLocale;
                  return (
                    <Pressable key={code} accessibilityRole="tab" accessibilityState={{ selected: on }} onPress={() => setExpLocale(code)} style={{ height: 30, minWidth: 40, paddingHorizontal: 10, borderRadius: 8, alignItems: 'center', justifyContent: 'center', backgroundColor: on ? '#F3F6FA' : 'transparent' }}>
                      <Text style={{ fontFamily: fonts.bodySemibold, fontSize: 12 }} color={on ? '#062A5E' : '#A9BBD8'}>
                        {shortLabel(code)}
                      </Text>
                    </Pressable>
                  );
                })}
              </View>
            ) : null}
          </View>
          <ScrollView style={{ flexGrow: 0 }} showsVerticalScrollIndicator={false}>
            <Text style={{ fontSize: 14, lineHeight: 22 }} color="#C5D3EA" selectable>
              {explanation || t('quiz.no_explanation', 'No explanation yet for this question.')}
            </Text>
            <Pressable onPress={() => setReport(true)} hitSlop={8} style={{ marginTop: 10 }} accessibilityRole="button">
              <Text variant="caption" color="#94A6C4">
                {t('quiz.report', 'Something wrong? Report this question')}
              </Text>
            </Pressable>
          </ScrollView>
          <View style={{ flexDirection: 'row', gap: 8 }}>
            <Pressable
              accessibilityRole="button"
              accessibilityState={{ selected: !!saved[question.id] }}
              onPress={() => saveToNotes(question)}
              style={{ height: 52, paddingHorizontal: 12, borderRadius: 16, backgroundColor: '#12407F', flexDirection: 'row', alignItems: 'center', gap: 6 }}
            >
              <Icon name="bookmark" size={16} color={saved[question.id] ? c.accent : '#F3F6FA'} fill={saved[question.id] ? c.accent : 'none'} strokeWidth={2} />
              <Text style={{ fontFamily: fonts.bodySemibold, fontSize: 13 }} color={saved[question.id] ? c.accent : '#F3F6FA'}>
                {saved[question.id] ? t('quiz.saved', 'Saved') : t('quiz.save_notes', 'Save to notes')}
              </Text>
            </Pressable>
            <Pressable
              accessibilityRole="button"
              onPress={() => router.push(`/discussion/${question.id}`)}
              style={{ height: 52, paddingHorizontal: 12, borderRadius: 16, backgroundColor: '#12407F', flexDirection: 'row', alignItems: 'center', gap: 8 }}
            >
              <Icon name="chat" size={16} color="#F3F6FA" strokeWidth={2} />
              <Text style={{ fontFamily: fonts.bodySemibold, fontSize: 14 }} color="#F3F6FA">
                {t('quiz.discuss', 'Discuss')}
                {question.comments_count ? ` · ${question.comments_count}` : ''}
              </Text>
            </Pressable>
            <Pressable
              accessibilityRole="button"
              onPress={() => go(index + 1)}
              style={({ pressed }) => ({ flex: 1, height: 52, borderRadius: 16, backgroundColor: c.accent, alignItems: 'center', justifyContent: 'center', opacity: pressed ? 0.85 : 1 })}
            >
              <Text style={{ fontFamily: fonts.bodySemibold, fontSize: 15 }} color="#062A5E">
                {index === items.length - 1 ? (review ? t('quiz.done_review', 'Done') : t('quiz.see_results', 'See results')) : t('quiz.next', 'Next →')}
              </Text>
            </Pressable>
          </View>
        </View>
      ) : null}

      {/* Full-screen image */}
      <Modal visible={zoom} transparent={false} animationType="fade" onRequestClose={() => setZoom(false)}>
        <View style={{ flex: 1, backgroundColor: '#000' }}>
          <ScrollView maximumZoomScale={4} minimumZoomScale={1} centerContent contentContainerStyle={{ flexGrow: 1, justifyContent: 'center' }}>
            {question.image ? <Image source={{ uri: question.image.url }} style={{ width: '100%', aspectRatio: 1 }} contentFit="contain" /> : null}
          </ScrollView>
          <View style={{ position: 'absolute', top: insets.top + 10, right: 16 }}>
            <IconButton icon="close" label={t('common.close', 'Close')} tone="navy" onPress={() => setZoom(false)} />
          </View>
        </View>
      </Modal>

      <ReportSheet
        questionId={question.id}
        visible={report}
        onClose={(sent) => {
          setReport(false);
          if (sent) Alert.alert(t('report.thanks', 'Thank you'), t('report.thanks_body', 'Our medical team will review this question.'));
        }}
      />
    </View>
  );
}

function Tag({ label, bg, ink }: { label: string; bg: string; ink: string }) {
  return (
    <View style={{ backgroundColor: bg, borderRadius: 99, paddingHorizontal: 10, paddingVertical: 5 }}>
      <Text style={{ fontFamily: fonts.bodyMedium, fontSize: 12 }} color={ink} numberOfLines={1}>
        {label}
      </Text>
    </View>
  );
}
