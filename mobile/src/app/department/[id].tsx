import { useLocalSearchParams, useRouter } from 'expo-router';
import { useState } from 'react';
import { LayoutAnimation, Pressable, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { useDepartment } from '../../api/hooks';
import { Button, IconButton } from '../../components/Button';
import { Icon } from '../../components/Icon';
import { ErrorState, Header, Screen, Skeleton } from '../../components/Screen';
import { Text } from '../../components/Text';
import { Card } from '../../components/ui';
import { fmtNumber } from '../../lib/format';
import { useQuizLauncher } from '../../hooks/useQuizLauncher';
import { fonts, useTheme } from '../../theme';

/** 12 · Department → subcategories → questions */
export default function Department() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const { t } = useTranslation();
  const { c } = useTheme();
  const router = useRouter();
  const q = useDepartment(Number(id));
  const { launch, starting, variables } = useQuizLauncher();
  const [open, setOpen] = useState<number | null>(null);
  const d = q.data;

  const toggle = (sid: number) => {
    LayoutAnimation.configureNext(LayoutAnimation.Presets.easeInEaseOut);
    setOpen(open === sid ? null : sid);
  };

  return (
    <Screen onRefresh={() => q.refetch()}>
      <Header title={t('department.crumb', 'Study › Department')} right={<IconButton icon="note" label={t('department.notes', 'Department notes')} onPress={() => router.push(`/notes-list?department=${id}`)} />} />

      {q.isError && !d ? (
        <ErrorState error={q.error} onRetry={() => q.refetch()} />
      ) : !d ? (
        <>
          <Skeleton height={70} />
          <Skeleton height={400} />
        </>
      ) : (
        <>
          <View style={{ gap: 8 }}>
            <Text variant="title" style={{ fontSize: 30 }}>
              {d.name}
            </Text>
            <View style={{ flexDirection: 'row', gap: 16, flexWrap: 'wrap' }}>
              {[
                [fmtNumber(d.questions), t('department.questions', 'questions')],
                [`${d.done_pct}%`, t('department.done', 'done')],
                [d.accuracy !== null ? `${d.accuracy}%` : '—', t('department.accuracy', 'accuracy')],
              ].map(([v, l]) => (
                <Text key={l} variant="small" muted>
                  <Text variant="mono" style={{ fontSize: 14 }}>
                    {v}
                  </Text>{' '}
                  {l}
                </Text>
              ))}
            </View>
          </View>

          <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'baseline' }}>
            <Text variant="h2">{t('department.subcategories', '{count} subcategories', { count: d.subcategories.length })}</Text>
            <Text variant="caption" muted>
              {t('department.tap_to_open', 'Tap to open')}
            </Text>
          </View>

          <Card style={{ overflow: 'hidden' }}>
            {d.subcategories.map((s, i) => {
              const isOpen = open === s.id;
              const acc = s.accuracy;
              const dot = acc === null ? c.fieldStrong : acc < 60 ? c.danger2 : c.teal;
              return (
                <View key={s.id} style={{ borderTopWidth: i ? 1 : 0, borderTopColor: c.divider, backgroundColor: isOpen ? c.tealWash : 'transparent' }}>
                  <Pressable
                    accessibilityRole="button"
                    accessibilityState={{ expanded: isOpen }}
                    accessibilityLabel={`${s.name}, ${s.questions} ${t('department.questions', 'questions')}`}
                    onPress={() => toggle(s.id)}
                    style={{ minHeight: 56, paddingHorizontal: 16, flexDirection: 'row', alignItems: 'center', gap: 10 }}
                  >
                    <View style={{ width: 8, height: 8, borderRadius: 4, backgroundColor: dot }} />
                    <Text style={{ flex: 1, fontFamily: isOpen ? fonts.bodySemibold : fonts.bodyMedium, fontSize: 15 }} numberOfLines={1}>
                      {s.name}
                    </Text>
                    <Text variant="mono" style={{ fontSize: 12 }} muted>
                      {s.questions} Q
                    </Text>
                    <Text style={{ fontFamily: fonts.mono, fontSize: 12, width: 38, textAlign: 'right' }} color={acc === null ? c.muted : acc < 60 ? c.danger : c.teal}>
                      {acc !== null ? `${acc}%` : '—'}
                    </Text>
                    <View style={{ transform: [{ rotate: isOpen ? '180deg' : '0deg' }] }}>
                      <Icon name="chevronDown" size={16} color={c.muted} />
                    </View>
                  </Pressable>

                  {isOpen ? (
                    <View style={{ paddingHorizontal: 16, paddingBottom: 14, gap: 12 }}>
                      <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 6 }}>
                        {s.videos ? (
                          <Pressable onPress={() => router.push(`/videos?subcategory=${s.id}&title=${encodeURIComponent(s.name)}`)} style={{ backgroundColor: c.navy, borderRadius: 99, paddingHorizontal: 10, paddingVertical: 6 }}>
                            <Text style={{ fontFamily: fonts.bodySemibold, fontSize: 12 }} color={c.onNavy}>
                              ▶ {t('department.videos', '{count} videos', { count: s.videos })}
                            </Text>
                          </Pressable>
                        ) : null}
                        {s.topics.map((tp) => (
                          <Pressable
                            key={tp.id}
                            accessibilityRole="button"
                            accessibilityHint={t('department.topic_hint', 'Practise this topic')}
                            onPress={() => launch({ source: 'subcategory', subcategory_ids: [s.id], topic_id: tp.id, size: 20, title: tp.name })}
                            style={{ backgroundColor: c.card, borderRadius: 99, paddingHorizontal: 10, paddingVertical: 6, borderWidth: 1, borderColor: c.line }}
                          >
                            <Text variant="caption" style={{ fontFamily: fonts.bodyMedium }}>
                              {tp.name}
                            </Text>
                          </Pressable>
                        ))}
                      </View>
                      <View style={{ flexDirection: 'row', gap: 8 }}>
                        <Button
                          title={t('department.practise', 'Practise {count}', { count: Math.min(s.questions, 20) })}
                          size="sm"
                          style={{ flex: 1.3 }}
                          disabled={!s.questions}
                          loading={starting && variables?.subcategory_ids?.[0] === s.id && !variables?.topic_id}
                          onPress={() => launch({ source: 'subcategory', subcategory_ids: [s.id], size: 20, title: s.name })}
                        />
                        <Button title={t('department.by_year', 'By year')} size="sm" variant="light" style={{ flex: 1 }} onPress={() => router.push('/papers')} />
                        <Button
                          title={t('department.notes_count', 'Notes · {count}', { count: s.notes })}
                          size="sm"
                          variant="light"
                          style={{ flex: 1.1 }}
                          disabled={!s.notes}
                          onPress={() => router.push(`/notes-list?subcategory=${s.id}&title=${encodeURIComponent(s.name)}`)}
                        />
                      </View>
                    </View>
                  ) : null}
                </View>
              );
            })}
          </Card>
        </>
      )}
    </Screen>
  );
}
