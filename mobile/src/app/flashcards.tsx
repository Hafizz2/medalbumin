import { useQueryClient } from '@tanstack/react-query';
import * as Haptics from 'expo-haptics';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { useEffect, useRef, useState } from 'react';
import { Animated, Pressable, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { qk, reviewCard, useFlashStudy } from '../api/hooks';
import { Button } from '../components/Button';
import { Icon } from '../components/Icon';
import { EmptyState, ErrorState, Header, Screen, Skeleton } from '../components/Screen';
import { Text } from '../components/Text';
import { Badge } from '../components/ui';
import { fonts, useTheme } from '../theme';

/** 08 · Flashcards — spaced repetition */
export default function Flashcards() {
  const { deck } = useLocalSearchParams<{ deck?: string }>();
  const deckId = deck ? Number(deck) : undefined;
  const { t } = useTranslation();
  const { c } = useTheme();
  const router = useRouter();
  const qc = useQueryClient();
  const q = useFlashStudy(deckId);
  const [i, setI] = useState(0);
  const [back, setBack] = useState(false);
  const [rating, setRating] = useState(false);
  const flip = useRef(new Animated.Value(0)).current;

  const cards = q.data?.data ?? [];
  const card = cards[i];

  useEffect(() => {
    Animated.spring(flip, { toValue: back ? 180 : 0, friction: 8, tension: 60, useNativeDriver: true }).start();
  }, [back, flip]);

  const rate = async (r: number) => {
    if (!card || rating) return;
    setRating(true);
    void Haptics.selectionAsync().catch(() => {});
    await reviewCard(card.id, r).catch(() => {});
    setRating(false);
    setBack(false);
    flip.setValue(0);
    setI((x) => x + 1);
  };

  const restart = async () => {
    setI(0);
    setBack(false);
    await q.refetch();
    qc.invalidateQueries({ queryKey: qk.decks });
    qc.invalidateQueries({ queryKey: qk.home });
  };

  const frontRotate = flip.interpolate({ inputRange: [0, 180], outputRange: ['0deg', '180deg'] });
  const backRotate = flip.interpolate({ inputRange: [0, 180], outputRange: ['180deg', '360deg'] });
  const due = Math.max(0, (q.data?.due_count ?? 0) - i);
  const path = q.data?.deck ? `${q.data.deck.path} › ${q.data.deck.title}` : card?.path ?? '';

  return (
    <Screen>
      <Header />
      <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-end' }}>
        <View style={{ flex: 1, gap: 2 }}>
          {path ? (
            <Text variant="caption" muted numberOfLines={1}>
              {path}
            </Text>
          ) : null}
          <Text variant="h1">{t('flash.title', 'Flashcards')}</Text>
        </View>
        {cards.length ? <Badge label={t('home.cards_due', '{count} due today', { count: due })} tone="danger" /> : null}
      </View>

      {q.isError && !q.data ? (
        <ErrorState error={q.error} onRetry={() => q.refetch()} />
      ) : !q.data ? (
        <Skeleton height={380} radius={28} />
      ) : !card ? (
        <View style={{ gap: 12 }}>
          <EmptyState
            icon="check"
            title={cards.length ? t('flash.done', 'All done for now') : t('flash.nothing_due', 'Nothing to review')}
            body={cards.length ? t('flash.done_body', 'You reviewed {n} cards. They come back when it’s time to remember them.', { n: cards.length }) : t('flash.nothing_body', 'New cards appear as decks are added. Check back later.')}
          />
          <Button title={t('flash.more', 'Load more cards')} variant="light" onPress={restart} />
          <Button title={t('flash.back_to_decks', 'Back to decks')} onPress={() => router.back()} />
        </View>
      ) : (
        <>
          <View style={{ flexDirection: 'row', gap: 4, flexWrap: 'wrap' }} accessibilityLabel={t('flash.position', 'Card {n} of {total}', { n: i + 1, total: cards.length })}>
            {cards.slice(0, 30).map((cd, k) => (
              <View key={cd.id} style={{ flex: 1, minWidth: 6, height: 5, borderRadius: 99, backgroundColor: k < i ? c.teal : k === i ? c.text : c.field }} />
            ))}
          </View>

          <View style={{ height: 380, marginTop: 6 }}>
            <View style={{ position: 'absolute', left: 24, right: 24, top: 18, bottom: -18, borderRadius: 28, backgroundColor: c.chip, opacity: 0.6 }} />
            <View style={{ position: 'absolute', left: 12, right: 12, top: 9, bottom: -9, borderRadius: 28, backgroundColor: c.chip }} />
            <Pressable style={{ flex: 1 }} onPress={() => setBack(!back)} accessibilityRole="button" accessibilityLabel={back ? t('flash.show_question', 'Show question') : t('flash.show_answer', 'Show answer')}>
              <Animated.View style={[cardStyle(c.card), { transform: [{ perspective: 1000 }, { rotateY: frontRotate }] }]}>
                <CardFace label={t('flash.question', 'Question')} pos={`${i + 1} / ${cards.length}`} main={card.front} sub={card.prompt} ink={c.text} soft={c.muted} hint={t('flash.tap', 'Tap card to flip')} />
              </Animated.View>
              <Animated.View style={[cardStyle('#062A5E'), { transform: [{ perspective: 1000 }, { rotateY: backRotate }] }]}>
                <CardFace label={t('flash.answer', 'Answer')} pos={`${i + 1} / ${cards.length}`} main={card.back} sub={card.detail} ink="#F3F6FA" soft="#A9BBD8" hint={t('flash.rate', 'How well did you know it?')} />
              </Animated.View>
            </Pressable>
          </View>

          <View style={{ flexDirection: 'row', gap: 8, marginTop: 14 }}>
            {card.buttons.map((b) => {
              const tone = b.rating === 1 ? [c.dangerBg, c.warn] : b.rating === 4 ? [c.tealSoft, c.teal] : [c.card, c.text];
              const label = { 1: t('flash.again', 'Again'), 2: t('flash.hard', 'Hard'), 3: t('flash.good', 'Good'), 4: t('flash.easy', 'Easy') }[b.rating];
              return (
                <Pressable
                  key={b.rating}
                  disabled={!back || rating}
                  accessibilityRole="button"
                  accessibilityLabel={`${label}, ${b.interval}`}
                  onPress={() => rate(b.rating)}
                  style={{ flex: 1, height: 60, borderRadius: 16, alignItems: 'center', justifyContent: 'center', gap: 2, backgroundColor: tone[0], borderWidth: 1, borderColor: tone[0] === c.card ? c.field : tone[0], opacity: back ? 1 : 0.45 }}
                >
                  <Text style={{ fontFamily: fonts.bodySemibold, fontSize: 14 }} color={tone[1]}>
                    {label}
                  </Text>
                  <Text variant="mono" style={{ fontSize: 11 }} color={tone[1]}>
                    {b.interval}
                  </Text>
                </Pressable>
              );
            })}
          </View>
        </>
      )}
    </Screen>
  );
}

const cardStyle = (bg: string) =>
  ({
    position: 'absolute',
    top: 0,
    left: 0,
    right: 0,
    bottom: 0,
    borderRadius: 28,
    padding: 22,
    backgroundColor: bg,
    backfaceVisibility: 'hidden',
    shadowColor: '#062A5E',
    shadowOpacity: 0.12,
    shadowRadius: 20,
    shadowOffset: { width: 0, height: 18 },
    elevation: 6,
  }) as const;

function CardFace({ label, pos, main, sub, ink, soft, hint }: { label: string; pos: string; main: string; sub: string | null; ink: string; soft: string; hint: string }) {
  return (
    <View style={{ flex: 1, gap: 12 }}>
      <View style={{ flexDirection: 'row', justifyContent: 'space-between' }}>
        <Text variant="label" color={soft}>
          {label}
        </Text>
        <Text variant="mono" style={{ fontSize: 12 }} color={soft}>
          {pos}
        </Text>
      </View>
      <View style={{ flex: 1, justifyContent: 'center', gap: 12 }}>
        <Text style={{ fontFamily: fonts.display, fontSize: 26, lineHeight: 31, letterSpacing: -0.5 }} color={ink}>
          {main}
        </Text>
        {sub ? (
          <Text style={{ fontSize: 16, lineHeight: 23 }} color={soft}>
            {sub}
          </Text>
        ) : null}
      </View>
      <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }}>
        <Icon name="refresh" size={14} color={soft} />
        <Text variant="caption" color={soft}>
          {hint}
        </Text>
      </View>
    </View>
  );
}
