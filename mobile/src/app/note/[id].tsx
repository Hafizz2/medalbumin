import { useLocalSearchParams } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';
import { useState } from 'react';
import { Pressable, TextInput, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { useConfig, useNote, useSaveMyNote } from '../../api/hooks';
import { Button } from '../../components/Button';
import { HtmlView } from '../../components/HtmlView';
import { Icon } from '../../components/Icon';
import { ErrorState, Header, Screen, Skeleton } from '../../components/Screen';
import { Text } from '../../components/Text';
import { Card } from '../../components/ui';
import { useQuizLauncher } from '../../hooks/useQuizLauncher';
import { fmtAgo } from '../../lib/format';
import { fonts, useTheme } from '../../theme';

/** 14 · Official note + my notes */
export default function NoteDetail() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const noteId = Number(id);
  const { t } = useTranslation();
  const { c } = useTheme();
  const q = useNote(noteId);
  const config = useConfig();
  const save = useSaveMyNote();
  const { launch, starting } = useQuizLauncher();
  const [locale, setLocale] = useState<string | null>(null);
  const [draft, setDraft] = useState('');

  const n = q.data;
  const shown = locale && n?.bodies[locale] ? locale : n?.locale ?? 'en';
  const html = n?.bodies[shown] ?? n?.body_html ?? '';
  const languages = config.data?.languages ?? [];
  const dir = languages.find((l) => l.code === shown)?.direction ?? 'ltr';
  const short = (code: string) => {
    const native = languages.find((l) => l.code === code)?.native_name ?? code;
    return /[^\u0000-ɏ]/.test(native) ? native.slice(0, 2) : code.toUpperCase();
  };

  const cycleLanguage = () => {
    if (!n || n.available_locales.length < 2) return;
    const idx = n.available_locales.indexOf(shown);
    setLocale(n.available_locales[(idx + 1) % n.available_locales.length]);
  };

  const addNote = () => {
    const body = draft.trim();
    if (!body) return;
    save.mutate({ note_id: noteId, body }, { onSuccess: () => setDraft('') });
  };

  return (
    <Screen
      onRefresh={() => q.refetch()}
      footer={
        n?.subcategory?.questions ? (
          <Button
            title={t('note.practise', 'Practise {name} questions', { name: n.subcategory.name })}
            loading={starting}
            onPress={() => launch({ source: 'subcategory', subcategory_ids: [n.subcategory!.id], size: 20, title: n.subcategory!.name })}
            trailing={
              <Text variant="mono" style={{ fontSize: 13 }} color="#A9BBD8">
                {n.subcategory.questions} Q
              </Text>
            }
          />
        ) : null
      }
    >
      <Header
        right={
          n && n.available_locales.length > 1 ? (
            <Pressable onPress={cycleLanguage} accessibilityRole="button" accessibilityLabel={t('note.language', 'Change language')} style={{ height: 44, paddingHorizontal: 12, borderRadius: 14, backgroundColor: c.card, borderWidth: 1, borderColor: c.line, alignItems: 'center', justifyContent: 'center' }}>
              <Text style={{ fontFamily: fonts.bodySemibold, fontSize: 13 }}>{n.available_locales.map(short).join(' · ')}</Text>
            </Pressable>
          ) : undefined
        }
      />

      {q.isError && !n ? (
        <ErrorState error={q.error} onRetry={() => q.refetch()} />
      ) : !n ? (
        <>
          <Skeleton height={80} />
          <Skeleton height={300} />
        </>
      ) : (
        <>
          <View style={{ gap: 8 }}>
            <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }}>
              <Icon name="shield" size={14} color={c.teal} />
              <Text variant="caption" color={c.teal} style={{ fontFamily: fonts.bodySemibold }}>
                {t('note.official', 'Official')} · {n.breadcrumb}
              </Text>
            </View>
            <Text variant="title" style={{ fontSize: 28, lineHeight: 32 }}>
              {n.title}
            </Text>
            <Text variant="caption" muted>
              {t('notes.read_min', '{n} min read', { n: n.read_minutes })} · {t('note.saved_offline', 'Saved for offline reading')}
            </Text>
          </View>

          <HtmlView html={html} dir={dir} />

          {n.attachment_url ? (
            <Button title={t('note.open_pdf', 'Open PDF attachment')} variant="light" size="md" icon="download" onPress={() => WebBrowser.openBrowserAsync(n.attachment_url!)} />
          ) : null}

          <Card tone="teal" style={{ padding: 16, gap: 10 }}>
            <View style={{ flexDirection: 'row', justifyContent: 'space-between' }}>
              <Text variant="smallStrong">{t('note.my_notes', 'My notes on this · {n}', { n: n.my_notes.length })}</Text>
              <Text variant="caption" muted>
                {t('note.private', 'Private')}
              </Text>
            </View>
            {n.my_notes.map((m) => (
              <View key={m.id} style={{ backgroundColor: c.card, borderRadius: 14, padding: 12, gap: 4 }}>
                <Text variant="small">{m.body}</Text>
                <Text variant="caption" muted>
                  {fmtAgo(m.updated_at)}
                </Text>
              </View>
            ))}
            <View style={{ flexDirection: 'row', gap: 8, alignItems: 'flex-end' }}>
              <TextInput
                value={draft}
                onChangeText={setDraft}
                placeholder={t('note.add_placeholder', 'Add your own note…')}
                placeholderTextColor={c.placeholder}
                multiline
                maxLength={20000}
                accessibilityLabel={t('note.add', 'Add a note')}
                style={{ flex: 1, minHeight: 48, maxHeight: 140, borderRadius: 14, backgroundColor: c.card, borderWidth: 1, borderColor: c.line, paddingHorizontal: 12, paddingVertical: 12, color: c.text, fontFamily: fonts.body, fontSize: 14 }}
              />
              <Button title={t('note.save', 'Save')} size="sm" style={{ height: 48 }} loading={save.isPending} disabled={!draft.trim()} onPress={addNote} />
            </View>
          </Card>
        </>
      )}
    </Screen>
  );
}
