import { Modal, Pressable, ScrollView, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useTranslation } from 'react-i18next';
import { fonts, useTheme } from '../theme';
import { Icon } from './Icon';
import { Text } from './Text';

export type PickerOption = { id: string; label: string; sub?: string };

/** Bottom sheet with a single-choice list (languages, universities, years…). */
export function PickerSheet({ visible, title, options, value, onPick, onClose }: { visible: boolean; title: string; options: PickerOption[]; value?: string | null; onPick: (id: string) => void; onClose: () => void }) {
  const { c } = useTheme();
  const { t } = useTranslation();
  const insets = useSafeAreaInsets();
  return (
    <Modal visible={visible} transparent animationType="slide" onRequestClose={onClose}>
      <Pressable style={{ flex: 1, backgroundColor: c.overlay }} onPress={onClose} accessibilityLabel={t('common.close', 'Close')} />
      <View style={{ maxHeight: '75%', backgroundColor: c.card, borderTopLeftRadius: 28, borderTopRightRadius: 28, paddingTop: 10, paddingBottom: insets.bottom + 12 }}>
        <View style={{ width: 40, height: 4, borderRadius: 99, backgroundColor: c.field, alignSelf: 'center', marginBottom: 12 }} />
        <Text variant="h2" style={{ paddingHorizontal: 22, marginBottom: 8 }}>
          {title}
        </Text>
        <ScrollView>
          {options.map((o, i) => {
            const on = o.id === value;
            return (
              <Pressable
                key={o.id}
                accessibilityRole="radio"
                accessibilityState={{ checked: on }}
                onPress={() => {
                  onPick(o.id);
                  onClose();
                }}
                style={({ pressed }) => ({ minHeight: 56, paddingHorizontal: 22, paddingVertical: 10, flexDirection: 'row', alignItems: 'center', gap: 12, borderTopWidth: i ? 1 : 0, borderTopColor: c.divider, backgroundColor: pressed ? c.chip2 : 'transparent' })}
              >
                <View style={{ flex: 1 }}>
                  <Text style={{ fontFamily: on ? fonts.bodySemibold : fonts.bodyMedium, fontSize: 16 }}>{o.label}</Text>
                  {o.sub ? (
                    <Text variant="caption" muted>
                      {o.sub}
                    </Text>
                  ) : null}
                </View>
                {on ? <Icon name="check" size={18} color={c.teal} strokeWidth={2.6} /> : null}
              </Pressable>
            );
          })}
        </ScrollView>
      </View>
    </Modal>
  );
}
