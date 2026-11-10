import { useState } from 'react';
import { Alert, LayoutAnimation, Pressable, TextInput, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { usePapers } from '../api/hooks';
import type { Paper, PaperGroup } from '../api/types';
import { Icon } from '../components/Icon';
import { EmptyState, ErrorState, Header, Screen, Skeleton } from '../components/Screen';
import { Text } from '../components/Text';
import { Card, Segmented } from '../components/ui';
import { useQuizLauncher } from '../hooks/useQuizLauncher';
import { fonts, radius, useTheme } from '../theme';

type Cat = 'exit' | 'qualification' | 'block';

/** 03 · Past exam papers */
export default function Papers() {
  const { t } = useTranslation();
  const { c } = useTheme();
  const [cat, setCat] = useState<Cat>('exit');
  const [search, setSearch] = useState('');
  const [open, setOpen] = useState<string | null>(null);
  const q = usePapers(cat, search.trim().length >= 2 ? search.trim() : '');
  const { launch, starting } = useQuizLauncher();

  const openPaper = (p: Paper) => {
    if (p.locked) {
      Alert.alert(t('common.premium_title', 'Premium content'), t('papers.locked', 'This past paper is part of the premium plan. Your plan status is under Profile.'));
      return;
    }
    if (p.in_progress) return launch({ source: 'paper', paper_id: p.id });
    Alert.alert(p.year ? `${p.name} ${p.year}` : p.name, t('papers.mode_question', 'How do you want to take it?'), [
      { text: t('builder.practice', 'Practice'), onPress: () => launch({ source: 'paper', paper_id: p.id, mode: 'practice' }) },
      { text: t('papers.timed', 'Timed, like the exam'), onPress: () => launch({ source: 'paper', paper_id: p.id, mode: 'timed' }) },
      { text: t('common.cancel', 'Cancel'), style: 'cancel' },
    ]);
  };

  const items = q.data?.data ?? [];

  return (
    <Screen onRefresh={() => q.refetch()}>
      <Header />
      <View style={{ gap: 2 }}>
        <Text variant="label" muted>
          {t('papers.overline', 'Previous year questions')}
        </Text>
        <Text variant="title" style={{ fontSize: 30 }}>
          {t('papers.title', 'Past exam papers')}
        </Text>
      </View>

      <View style={{ height: 48, borderRadius: 14, backgroundColor: c.card, borderWidth: 1, borderColor: c.line, flexDirection: 'row', alignItems: 'center', gap: 10, paddingHorizontal: 14 }}>
        <Icon name="search" size={16} color={c.muted} />
        <TextInput
          value={search}
          onChangeText={setSearch}
          placeholder={t('papers.search', 'Search year, exam or topic')}
          placeholderTextColor={c.placeholder}
          accessibilityLabel={t('papers.search_label', 'Search papers')}
          returnKeyType="search"
          style={{ flex: 1, fontFamily: fonts.body, fontSize: 15, color: c.text }}
        />
      </View>

      <Segmented<Cat>
        value={cat}
        onChange={(v) => {
          setCat(v);
          setOpen(null);
        }}
        options={[
          { id: 'exit', label: t('papers.exit', 'Exit exam') },
          { id: 'qualification', label: t('papers.qualification', 'Qualification') },
          { id: 'block', label: t('papers.block', 'Block exams') },
        ]}
      />

      {q.data ? (
        <Text variant="small" muted>
          {t('papers.available', '{count} papers available', { count: items.length })}
        </Text>
      ) : null}

      {q.isError && !q.data ? (
        <ErrorState error={q.error} onRetry={() => q.refetch()} />
      ) : !q.data ? (
        [0, 1, 2, 3].map((i) => <Skeleton key={i} height={84} />)
      ) : items.length === 0 ? (
        <EmptyState icon="note" title={t('papers.empty', 'No papers here yet')} body={t('papers.empty_body', 'New papers appear here as soon as they are published.')} />
      ) : cat === 'block' ? (
        (items as PaperGroup[]).map((g) => {
          const isOpen = open === g.code;
          return (
            <View key={g.code} style={{ gap: 8 }}>
              <PaperCard
                top={g.code}
                bottom={t('papers.block_label', 'Block')}
                title={g.name}
                meta={t('papers.group_meta', '{papers} papers · {questions} questions', { papers: g.papers_count, questions: g.questions })}
                score={g.best_pct !== null ? `${g.best_pct}%` : t('papers.new', 'New')}
                done={g.done_pct}
                complete={g.done_pct >= 100}
                onPress={() => {
                  LayoutAnimation.configureNext(LayoutAnimation.Presets.easeInEaseOut);
                  setOpen(isOpen ? null : g.code);
                }}
              />
              {isOpen
                ? g.papers.map((p) => (
                    <View key={p.id} style={{ marginLeft: 22 }}>
                      <PaperRow paper={p} onPress={() => openPaper(p)} disabled={starting} />
                    </View>
                  ))
                : null}
            </View>
          );
        })
      ) : (
        (items as Paper[]).map((p) => <PaperRow key={p.id} paper={p} onPress={() => openPaper(p)} disabled={starting} />)
      )}
    </Screen>
  );
}

function PaperRow({ paper: p, onPress, disabled }: { paper: Paper; onPress: () => void; disabled?: boolean }) {
  const { t } = useTranslation();
  const score = p.locked
    ? t('papers.premium', 'Premium')
    : p.in_progress
      ? t('papers.in_progress', 'In progress')
      : p.best_pct !== null
        ? t('papers.best', 'Best {pct}%', { pct: p.best_pct })
        : t('papers.new', 'New');
  const meta = p.in_progress || (p.done_pct > 0 && p.done_pct < 100)
    ? t('papers.meta_progress', '{count} questions · {pct}% done', { count: p.questions, pct: p.done_pct })
    : `${t('papers.meta_count', '{count} questions', { count: p.questions })}${p.description ? ` · ${p.description}` : ''}`;
  return (
    <PaperCard
      top={p.year ? String(p.year) : p.code ?? '—'}
      bottom={p.ec_label ?? ''}
      title={p.name}
      meta={meta}
      score={score}
      done={p.done_pct}
      complete={p.best_pct !== null && !p.in_progress}
      locked={p.locked}
      onPress={onPress}
      disabled={disabled}
    />
  );
}

function PaperCard({ top, bottom, title, meta, score, done, complete, locked, onPress, disabled }: { top: string; bottom: string; title: string; meta: string; score: string; done: number; complete: boolean; locked?: boolean; onPress: () => void; disabled?: boolean }) {
  const { c } = useTheme();
  const scoreIsResult = /\d/.test(score) && !locked;
  return (
    <Card onPress={disabled ? undefined : onPress} accessibilityLabel={`${title} ${top}. ${score}`} style={{ padding: 12, flexDirection: 'row', alignItems: 'center', gap: 12, borderRadius: radius.xl }}>
      <View style={{ width: 58, height: 58, borderRadius: 16, backgroundColor: complete ? '#062A5E' : c.tealSoft, alignItems: 'center', justifyContent: 'center' }}>
        <Text style={{ fontFamily: fonts.mono, fontSize: 15 }} color={complete ? '#F3F6FA' : c.teal}>
          {top}
        </Text>
        {bottom ? (
          <Text style={{ fontSize: 10 }} color={complete ? '#A9BBD8' : c.teal} numberOfLines={1}>
            {bottom}
          </Text>
        ) : null}
      </View>
      <View style={{ flex: 1, gap: 4 }}>
        <View style={{ flexDirection: 'row', justifyContent: 'space-between', gap: 8 }}>
          <Text variant="bodyStrong" numberOfLines={1} style={{ flex: 1 }}>
            {title}
          </Text>
          <View style={{ flexDirection: 'row', alignItems: 'center', gap: 4 }}>
            {locked ? <Icon name="lock" size={13} color={c.muted} /> : null}
            <Text style={{ fontFamily: fonts.bodySemibold, fontSize: 13 }} color={scoreIsResult ? c.teal : c.muted}>
              {score}
            </Text>
          </View>
        </View>
        <Text variant="caption" muted numberOfLines={1}>
          {meta}
        </Text>
        <View style={{ height: 4, borderRadius: 99, backgroundColor: c.divider, overflow: 'hidden' }}>
          <View style={{ width: `${done}%`, height: '100%', backgroundColor: c.teal }} />
        </View>
      </View>
    </Card>
  );
}
