import { useRouter } from 'expo-router';
import { useState } from 'react';
import { Alert, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { useCardsFromMistakes, useDecks, useMyNotes, useOfficialNotes, useOverview } from '../../api/hooks';
import { Button, IconButton } from '../../components/Button';
import { Icon } from '../../components/Icon';
import { EmptyState, ErrorState, Screen, Skeleton } from '../../components/Screen';
import { Text } from '../../components/Text';
import { Badge, Card, IconTile, Segmented } from '../../components/ui';
import { fmtAgo, fmtNumber } from '../../lib/format';
import { fonts, TAB_BAR_SPACE, useTheme } from '../../theme';

type Tab = 'mine' | 'official' | 'cards';

/** 13 · Notes & flashcards */
export default function Notes() {
  const { t } = useTranslation();
  const { c } = useTheme();
  const router = useRouter();
  const [tab, setTab] = useState<Tab>('mine');
  const mine = useMyNotes();
  const official = useOfficialNotes();
  const decks = useDecks();
  const overview = useOverview();
  const fromMistakes = useCardsFromMistakes();

  const refresh = () => Promise.all([mine.refetch(), official.refetch(), decks.refetch(), overview.refetch()]);
  const tints = [
    [c.dangerBg, c.danger],
    [c.skyBg, c.sky],
    [c.plumBg, c.plumInk],
    [c.tealSoft, c.teal],
    [c.sandBg, c.sand],
  ];

  const days = overview.data?.last_seven_days ?? [];
  const maxDay = Math.max(1, ...days.map((d) => d.questions));

  return (
    <Screen onRefresh={refresh} bottomInset={TAB_BAR_SPACE}>
      <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' }}>
        <Text variant="h1">{t('notes.title', 'Notes & flashcards')}</Text>
        <IconButton icon="plus" label={t('notes.new', 'New note')} onPress={() => router.push('/my-note/new')} />
      </View>
      <Segmented<Tab>
        value={tab}
        onChange={setTab}
        options={[
          { id: 'mine', label: t('notes.mine', 'My notes') },
          { id: 'official', label: t('notes.official', 'Official') },
          { id: 'cards', label: t('notes.cards', 'Flashcards') },
        ]}
      />

      {tab === 'mine' ? (
        <View style={{ gap: 10 }}>
          <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'baseline' }}>
            <Text variant="h2">{t('notes.recent', 'Recent notes')}</Text>
            <Text variant="caption" muted>
              {t('notes.private', 'Only you can see these')}
            </Text>
          </View>
          {mine.isError && !mine.data ? (
            <ErrorState error={mine.error} onRetry={() => mine.refetch()} />
          ) : !mine.data ? (
            <Skeleton height={180} />
          ) : mine.data.data.length === 0 ? (
            <EmptyState icon="edit" title={t('notes.empty', 'No notes yet')} body={t('notes.empty_body', 'Save explanations from questions, or write your own notes.')} action={t('notes.write', 'Write a note')} onAction={() => router.push('/my-note/new')} />
          ) : (
            mine.data.data.slice(0, 20).map((n, i) => {
              const [bg, ink] = tints[i % tints.length];
              return (
                <Card key={n.id} onPress={() => router.push(`/my-note/${n.id}`)} style={{ padding: 12, flexDirection: 'row', alignItems: 'center', gap: 12, borderRadius: 18 }}>
                  <IconTile icon={n.context?.type === 'question' ? 'question' : n.context?.type === 'video' ? 'video' : 'note'} bg={bg} ink={ink} />
                  <View style={{ flex: 1, gap: 2 }}>
                    <Text variant="bodyStrong" numberOfLines={1}>
                      {n.title || n.body.split('\n')[0]}
                    </Text>
                    <Text variant="caption" muted numberOfLines={1}>
                      {n.subcategory ? [n.subcategory.department, n.subcategory.name].filter(Boolean).join(' · ') : n.body.replace(/\s+/g, ' ').slice(0, 60)}
                    </Text>
                  </View>
                  <Text variant="caption" muted>
                    {fmtAgo(n.updated_at)}
                  </Text>
                </Card>
              );
            })
          )}

          {decks.data ? (
            <Card onPress={() => router.push('/flashcards')} style={{ padding: 14, flexDirection: 'row', alignItems: 'center', gap: 12, borderRadius: 18 }}>
              <IconTile icon="cards" bg={c.chip} ink={c.text} />
              <View style={{ flex: 1, gap: 2 }}>
                <Text variant="bodyStrong">{t('notes.flashcards', 'Flashcards')}</Text>
                <Text variant="caption" muted>
                  {t('notes.flash_meta', '{cards} cards · {decks} decks · {due} due today', { cards: fmtNumber(decks.data.total_cards), decks: decks.data.data.length, due: decks.data.due_today })}
                </Text>
              </View>
              <Icon name="chevronRight" size={18} color={c.muted} />
            </Card>
          ) : null}

          {overview.data ? (
            <Card style={{ padding: 14, flexDirection: 'row', alignItems: 'center', gap: 12, borderRadius: 18 }}>
              <IconTile icon="flame" bg={c.dangerBg} ink={c.danger} />
              <View style={{ flex: 1, gap: 1 }}>
                <Text variant="caption" muted>
                  {t('notes.streak', 'Study streak')}
                </Text>
                <Text style={{ fontFamily: fonts.display, fontSize: 20 }}>{t('home.days', '{count} days', { count: overview.data.totals.streak })}</Text>
                <Text variant="caption" color={c.teal}>
                  {overview.data.totals.streak ? t('notes.keep_up', 'Keep it up!') : t('notes.start_today', 'Answer a question to start today')}
                </Text>
              </View>
              <View style={{ flexDirection: 'row', alignItems: 'flex-end', gap: 4, height: 42 }} accessibilityLabel={t('notes.last_7_days', 'Last 7 days')}>
                {days.map((d, i) => (
                  <View key={d.date} style={{ width: 7, height: Math.max(4, (d.questions / maxDay) * 40), borderRadius: 3, backgroundColor: i === days.length - 1 ? c.teal : '#9ADDE2' }} />
                ))}
              </View>
            </Card>
          ) : null}
        </View>
      ) : null}

      {tab === 'official' ? (
        <View style={{ gap: 10 }}>
          <Text variant="small" muted>
            {t('notes.official_by', 'Written and checked by the MedAlbumin medical team')}
          </Text>
          {official.isError && !official.data ? (
            <ErrorState error={official.error} onRetry={() => official.refetch()} />
          ) : !official.data ? (
            <Skeleton height={240} />
          ) : official.data.data.length === 0 ? (
            <EmptyState icon="note" title={t('notes.no_official', 'No official notes yet')} />
          ) : (
            official.data.data.map((n) => (
              <Card key={n.id} onPress={() => router.push(`/note/${n.id}`)} style={{ padding: 14, gap: 6, borderRadius: 18 }}>
                <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }}>
                  <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }}>
                    <Icon name="shield" size={14} color={c.teal} />
                    <Text variant="caption" color={c.teal} style={{ fontFamily: fonts.bodySemibold }}>
                      {t('notes.official_badge', 'Official · MedAlbumin team')}
                    </Text>
                  </View>
                  {n.locked ? <Badge label={t('papers.premium', 'Premium')} tone="navy" /> : n.is_new ? <Badge label={t('notes.new_badge', 'New')} tone="warn" /> : null}
                </View>
                <Text variant="bodyStrong">{n.title}</Text>
                <View style={{ flexDirection: 'row', justifyContent: 'space-between' }}>
                  <Text variant="caption" muted numberOfLines={1} style={{ flex: 1 }}>
                    {n.breadcrumb}
                  </Text>
                  <Text variant="caption" muted>
                    {t('notes.read_min', '{n} min read', { n: n.read_minutes })}
                  </Text>
                </View>
              </Card>
            ))
          )}
        </View>
      ) : null}

      {tab === 'cards' ? (
        <View style={{ gap: 10 }}>
          {decks.isError && !decks.data ? (
            <ErrorState error={decks.error} onRetry={() => decks.refetch()} />
          ) : !decks.data ? (
            <Skeleton height={240} />
          ) : (
            <>
              {decks.data.due_today > 0 ? <Button title={t('flash.review_due', 'Review {count} due cards', { count: decks.data.due_today })} onPress={() => router.push('/flashcards')} /> : null}
              {decks.data.data.map((d) => (
                <Card key={d.id} onPress={() => router.push(`/flashcards?deck=${d.id}`)} style={{ padding: 14, flexDirection: 'row', alignItems: 'center', gap: 12, borderRadius: 18 }}>
                  <View style={{ flex: 1, gap: 2 }}>
                    <Text variant="bodyStrong" numberOfLines={1}>
                      {d.title}
                    </Text>
                    <Text variant="caption" muted>
                      {t('notes.deck_meta', '{n} cards · {path}', { n: d.cards, path: d.path })}
                    </Text>
                  </View>
                  <Badge label={d.due ? t('notes.due', '{n} due', { n: d.due }) : d.new ? t('notes.new_cards', '{n} new', { n: d.new }) : t('notes.done_today', 'Done today')} tone={d.due ? 'danger' : 'ok'} />
                </Card>
              ))}
              <Button
                title={t('notes.from_mistakes', '+ Make cards from my mistakes')}
                variant="light"
                loading={fromMistakes.isPending}
                onPress={() =>
                  fromMistakes.mutate(undefined, {
                    onSuccess: (r) => Alert.alert(t('notes.cards', 'Flashcards'), r.added ? t('notes.added_cards', '{n} cards added to “My mistakes”.', { n: r.added }) : t('notes.no_new_mistakes', 'No new mistakes to turn into cards.')),
                  })
                }
              />
            </>
          )}
        </View>
      ) : null}
    </Screen>
  );
}
