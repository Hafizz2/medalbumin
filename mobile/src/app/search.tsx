import { Image } from 'expo-image';
import { useRouter } from 'expo-router';
import { useEffect, useState } from 'react';
import { Pressable, TextInput, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { useImageBank, useSearch } from '../api/hooks';
import type { SearchResult } from '../api/types';
import { IconButton } from '../components/Button';
import { Icon } from '../components/Icon';
import { EmptyState, Loading, Screen } from '../components/Screen';
import { Text } from '../components/Text';
import { Card, Chip, ChipRow } from '../components/ui';
import { useQuizLauncher } from '../hooks/useQuizLauncher';
import { fmtNumber } from '../lib/format';
import { fonts, useTheme } from '../theme';

type Tab = 'all' | 'q' | 'n' | 'c' | 'v' | 'img';

/** 23 · Search & image bank */
export default function Search() {
  const { t } = useTranslation();
  const { c } = useTheme();
  const router = useRouter();
  const [text, setText] = useState('');
  const [term, setTerm] = useState('');
  const [tab, setTab] = useState<Tab>('all');
  const search = useSearch(term, tab === 'img' ? 'all' : tab);
  const images = useImageBank();
  const { launch } = useQuizLauncher();

  // Search as you type, after a short pause.
  useEffect(() => {
    const h = setTimeout(() => setTerm(text.trim()), 350);
    return () => clearTimeout(h);
  }, [text]);

  const tone: Record<SearchResult['type'], [string, string]> = {
    question: [c.tealSoft, c.teal],
    note: [c.sandBg, c.sand],
    video: [c.skyBg, c.sky],
    card: [c.chip, c.textSoft],
  };

  const open = (r: SearchResult) => {
    if (r.type === 'question') return launch({ source: 'questions', question_ids: [r.id], title: t('search.question', 'Question') });
    if (r.type === 'note') return router.push(`/note/${r.id}`);
    if (r.type === 'video') return router.push(`/video/${r.id}`);
    return router.push(`/flashcards?deck=${r.deck_id}`);
  };

  const counts = search.data?.counts;
  const tabs: [Tab, string][] = [
    ['all', t('search.all', 'All')],
    ['q', t('search.questions', 'Questions')],
    ['n', t('search.notes', 'Notes')],
    ['c', t('search.cards', 'Cards')],
    ['v', t('search.videos', 'Videos')],
    ['img', t('search.images', 'Images')],
  ];
  const imageGrid = (images.data?.data ?? []).slice(0, tab === 'img' ? 60 : 3);

  return (
    <Screen>
      <View style={{ flexDirection: 'row', gap: 10, alignItems: 'center' }}>
        <IconButton icon="back" label={t('common.back', 'Back')} onPress={() => router.back()} />
        <View style={{ flex: 1, height: 48, borderRadius: 14, backgroundColor: c.card, borderWidth: 1.5, borderColor: c.text, flexDirection: 'row', alignItems: 'center', gap: 10, paddingHorizontal: 14 }}>
          <Icon name="search" size={16} color={c.muted} />
          <TextInput
            value={text}
            onChangeText={setText}
            autoFocus
            placeholder={t('search.placeholder', 'Search everything')}
            placeholderTextColor={c.placeholder}
            returnKeyType="search"
            accessibilityLabel={t('search.placeholder', 'Search everything')}
            style={{ flex: 1, fontFamily: fonts.body, fontSize: 15, color: c.text }}
          />
        </View>
      </View>

      <ChipRow>
        {tabs.map(([id, label]) => (
          <Chip key={id} label={label} selected={tab === id} count={id === 'img' ? images.data?.total : counts ? counts[id] : undefined} onPress={() => setTab(id)} />
        ))}
      </ChipRow>

      {tab !== 'img' ? (
        term.length < 2 ? (
          <Text variant="small" muted>
            {t('search.hint', 'Type at least 2 letters — e.g. “pre-eclampsia”, “ECG”, “malaria”.')}
          </Text>
        ) : search.isFetching && !search.data ? (
          <Loading />
        ) : search.data?.data.length ? (
          <Card>
            {search.data.data.map((r, i) => (
              <Pressable key={`${r.type}-${r.id}`} accessibilityRole="button" onPress={() => open(r)} style={({ pressed }) => ({ flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 10, paddingHorizontal: 14, minHeight: 60, borderTopWidth: i ? 1 : 0, borderTopColor: c.divider, opacity: pressed ? 0.75 : 1 })}>
                <View style={{ minWidth: 44, height: 28, paddingHorizontal: 6, borderRadius: 8, backgroundColor: tone[r.type][0], alignItems: 'center', justifyContent: 'center' }}>
                  <Text style={{ fontFamily: fonts.bodySemibold, fontSize: 10 }} color={tone[r.type][1]}>
                    {r.code}
                  </Text>
                </View>
                <View style={{ flex: 1, gap: 2 }}>
                  <Text variant="bodyMedium" style={{ fontSize: 14 }} numberOfLines={2}>
                    {r.title}
                  </Text>
                  <Text variant="caption" muted numberOfLines={1}>
                    {r.meta}
                  </Text>
                </View>
                {r.locked ? <Icon name="lock" size={14} color={c.muted} /> : null}
              </Pressable>
            ))}
          </Card>
        ) : (
          <EmptyState icon="search" title={t('search.none', 'No results')} body={t('search.none_body', 'Try another word or check the spelling.')} />
        )
      ) : null}

      {images.data?.total ? (
        <View style={{ gap: 10, marginTop: 6 }}>
          {tab !== 'img' ? (
            <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'baseline' }}>
              <Text variant="h2">{t('search.image_bank', 'Image bank')}</Text>
              <Pressable onPress={() => setTab('img')} hitSlop={8}>
                <Text variant="caption" muted>
                  {t('search.image_meta', 'ECGs, X-rays, slides · {n}', { n: fmtNumber(images.data.total) })}
                </Text>
              </Pressable>
            </View>
          ) : null}
          <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 8 }}>
            {imageGrid.map((im) => (
              <Pressable
                key={im.question_id}
                accessibilityRole="imagebutton"
                accessibilityLabel={im.caption ?? t('search.image', 'Image')}
                onPress={() => launch({ source: 'questions', question_ids: [im.question_id] })}
                style={{ width: '31.8%', gap: 4 }}
              >
                <Image source={{ uri: im.url }} style={{ width: '100%', aspectRatio: 1, borderRadius: 14, backgroundColor: c.chip }} contentFit="cover" cachePolicy="disk" transition={150} />
                <Text style={{ fontSize: 11 }} muted numberOfLines={2}>
                  {im.caption ?? im.department ?? ''}
                </Text>
              </Pressable>
            ))}
          </View>
        </View>
      ) : tab === 'img' ? (
        <EmptyState icon="image" title={t('search.no_images', 'No images yet')} />
      ) : null}
    </Screen>
  );
}
