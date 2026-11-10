import { useLocalSearchParams } from 'expo-router';
import { useEffect, useState } from 'react';
import { View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { HtmlView } from '../../components/HtmlView';
import { EmptyState, Header, Screen, Skeleton } from '../../components/Screen';
import { Text } from '../../components/Text';
import { Badge } from '../../components/ui';
import { loadPack, OfflineNote } from '../../offline/store';

/** An official note read from a downloaded pack. */
export default function OfflineNoteScreen() {
  const { pack, note } = useLocalSearchParams<{ pack: string; note: string }>();
  const { t } = useTranslation();
  const [n, setN] = useState<OfflineNote | null | undefined>(undefined);

  useEffect(() => {
    loadPack(Number(pack)).then((p) => setN(p?.notes.find((x) => x.id === Number(note)) ?? null));
  }, [pack, note]);

  return (
    <Screen>
      <Header />
      {n === undefined ? (
        <Skeleton height={400} />
      ) : !n ? (
        <EmptyState icon="note" title={t('offline.note_missing', 'Note not found in this download')} />
      ) : (
        <>
          <View style={{ gap: 8 }}>
            <View style={{ flexDirection: 'row', gap: 6 }}>
              <Badge label={t('note.official', 'Official')} tone="ok" />
              <Badge label={t('offline.badge', 'Available offline')} tone="sky" />
            </View>
            <Text variant="h1">{n.title}</Text>
            <Text variant="caption" muted>
              {t('notes.read_min', '{n} min read', { n: n.read_minutes })}
            </Text>
          </View>
          <HtmlView html={n.body_html} />
        </>
      )}
    </Screen>
  );
}
