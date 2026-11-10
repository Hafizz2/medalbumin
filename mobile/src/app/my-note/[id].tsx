import { useLocalSearchParams, useRouter } from 'expo-router';
import { useEffect, useState } from 'react';
import { Alert, TextInput } from 'react-native';
import { useTranslation } from 'react-i18next';
import { useDeleteMyNote, useMyNotes, useSaveMyNote } from '../../api/hooks';
import { Button, IconButton } from '../../components/Button';
import { Header, Screen } from '../../components/Screen';
import { Text } from '../../components/Text';
import { fmtAgo } from '../../lib/format';
import { fonts, useTheme } from '../../theme';

/** Write or edit a private note. /my-note/new creates one. */
export default function MyNote() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const isNew = id === 'new';
  const { t } = useTranslation();
  const { c } = useTheme();
  const router = useRouter();
  const notes = useMyNotes();
  const save = useSaveMyNote();
  const remove = useDeleteMyNote();
  const existing = isNew ? undefined : notes.data?.data.find((n) => n.id === Number(id));
  const [title, setTitle] = useState('');
  const [body, setBody] = useState('');

  useEffect(() => {
    if (existing) {
      setTitle(existing.title ?? '');
      setBody(existing.body);
    }
  }, [existing]);

  const submit = () =>
    save.mutate(
      { id: isNew ? undefined : Number(id), title: title.trim() || null, body: body.trim() },
      { onSuccess: () => router.back(), onError: (e) => Alert.alert(t('common.error_title', 'Something went wrong'), e.message) },
    );

  const del = () =>
    Alert.alert(t('mynote.delete_title', 'Delete this note?'), undefined, [
      { text: t('common.cancel', 'Cancel'), style: 'cancel' },
      { text: t('mynote.delete', 'Delete'), style: 'destructive', onPress: () => remove.mutate(Number(id), { onSuccess: () => router.back() }) },
    ]);

  return (
    <Screen footer={<Button title={t('note.save', 'Save')} onPress={submit} loading={save.isPending} disabled={!body.trim()} />}>
      <Header title={isNew ? t('mynote.new', 'New note') : t('mynote.edit', 'My note')} right={!isNew ? <IconButton icon="trash" label={t('mynote.delete', 'Delete')} onPress={del} /> : undefined} />
      {existing?.context ? (
        <Text variant="caption" muted>
          {t('mynote.on', 'On {title}', { title: existing.context.title })} · {fmtAgo(existing.updated_at)}
        </Text>
      ) : null}
      <TextInput
        value={title}
        onChangeText={setTitle}
        placeholder={t('mynote.title_placeholder', 'Title (optional)')}
        placeholderTextColor={c.placeholder}
        maxLength={190}
        style={{ fontFamily: fonts.display, fontSize: 24, color: c.text, paddingVertical: 6 }}
      />
      <TextInput
        value={body}
        onChangeText={setBody}
        placeholder={t('mynote.body_placeholder', 'Write your note…')}
        placeholderTextColor={c.placeholder}
        multiline
        autoFocus={isNew}
        maxLength={20000}
        textAlignVertical="top"
        style={{ minHeight: 320, fontFamily: fonts.body, fontSize: 16, lineHeight: 24, color: c.text, backgroundColor: c.card, borderRadius: 18, borderWidth: 1, borderColor: c.line, padding: 16 }}
      />
    </Screen>
  );
}
