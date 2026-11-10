import { useState } from 'react';
import { Modal, Pressable, TextInput, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useTranslation } from 'react-i18next';
import { reportQuestion } from '../../api/hooks';
import { useTheme } from '../../theme';
import { Button } from '../Button';
import { Text } from '../Text';

/** "Report a problem" with a question — goes to Admin → Reported. */
export function ReportSheet({ questionId, visible, onClose }: { questionId: number | null; visible: boolean; onClose: (sent?: boolean) => void }) {
  const { t } = useTranslation();
  const { c } = useTheme();
  const insets = useSafeAreaInsets();
  const [reason, setReason] = useState<string | null>(null);
  const [note, setNote] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const reasons: [string, string][] = [
    ['wrong_key', t('report.wrong_key', 'Wrong answer key')],
    ['image_unclear', t('report.image_unclear', 'Image unclear')],
    ['translation', t('report.translation', 'Translation problem')],
    ['typo', t('report.typo', 'Typo or unclear wording')],
    ['outdated', t('report.outdated', 'Outdated guideline')],
    ['other', t('report.other', 'Something else')],
  ];

  const send = async () => {
    if (!questionId || !reason) return;
    setBusy(true);
    setError(null);
    try {
      await reportQuestion(questionId, reason, note || undefined);
      setReason(null);
      setNote('');
      onClose(true);
    } catch (e) {
      setError(e instanceof Error ? e.message : String(e));
    } finally {
      setBusy(false);
    }
  };

  return (
    <Modal visible={visible} transparent animationType="slide" onRequestClose={() => onClose()}>
      <Pressable style={{ flex: 1, backgroundColor: c.overlay }} onPress={() => onClose()} accessibilityLabel={t('common.close', 'Close')} />
      <View style={{ backgroundColor: c.card, borderTopLeftRadius: 28, borderTopRightRadius: 28, padding: 22, paddingBottom: insets.bottom + 18, gap: 12 }}>
        <Text variant="h2">{t('report.title', 'Report a problem')}</Text>
        <Text variant="small" muted>
          {t('report.body', 'Our medical team reviews every report.')}
        </Text>
        <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 8 }}>
          {reasons.map(([id, label]) => (
            <Pressable
              key={id}
              accessibilityRole="radio"
              accessibilityState={{ checked: reason === id }}
              onPress={() => setReason(id)}
              style={{ paddingHorizontal: 12, minHeight: 38, justifyContent: 'center', borderRadius: 99, borderWidth: 1, borderColor: reason === id ? c.navy : c.field, backgroundColor: reason === id ? c.navy : c.card }}
            >
              <Text variant="small" color={reason === id ? c.onNavy : c.text}>
                {label}
              </Text>
            </Pressable>
          ))}
        </View>
        <TextInput
          value={note}
          onChangeText={setNote}
          placeholder={t('report.note_placeholder', 'What is wrong? (optional)')}
          placeholderTextColor={c.placeholder}
          multiline
          maxLength={1000}
          style={{ minHeight: 80, borderRadius: 14, borderWidth: 1, borderColor: c.field, padding: 12, color: c.text, textAlignVertical: 'top' }}
        />
        {error ? (
          <Text variant="small" color={c.danger}>
            {error}
          </Text>
        ) : null}
        <Button title={t('report.send', 'Send report')} onPress={send} loading={busy} disabled={!reason} />
      </View>
    </Modal>
  );
}
