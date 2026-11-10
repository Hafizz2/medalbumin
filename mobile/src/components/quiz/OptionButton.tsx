import * as Haptics from 'expo-haptics';
import { Pressable, View } from 'react-native';
import type { QuestionOption } from '../../api/types';
import { fonts, useTheme } from '../../theme';
import { Icon } from '../Icon';
import { Text } from '../Text';

type State = 'idle' | 'selected' | 'correct' | 'wrong' | 'dim';

export function OptionButton({ option, state, onPress, large, disabled }: { option: QuestionOption; state: State; onPress: () => void; large?: boolean; disabled?: boolean }) {
  const { c } = useTheme();
  const styles: Record<State, { bg: string; border: string; badgeBg: string; badgeInk: string }> = {
    idle: { bg: c.card, border: c.line, badgeBg: c.chip2, badgeInk: c.text },
    dim: { bg: c.card, border: c.line, badgeBg: c.chip2, badgeInk: c.text },
    selected: { bg: c.card, border: c.text, badgeBg: c.navy, badgeInk: c.onNavy },
    correct: { bg: c.tealSoft, border: c.teal, badgeBg: c.teal, badgeInk: '#FFFFFF' },
    wrong: { bg: c.dangerBg, border: c.danger, badgeBg: c.danger, badgeInk: '#FFFFFF' },
  };
  const s = styles[state];

  return (
    <Pressable
      accessibilityRole="radio"
      accessibilityState={{ checked: state === 'selected' || state === 'correct' || state === 'wrong', disabled }}
      accessibilityLabel={`${option.label}. ${option.text}${state === 'correct' ? ', correct answer' : state === 'wrong' ? ', your answer, incorrect' : ''}`}
      disabled={disabled}
      onPress={() => {
        void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light).catch(() => {});
        onPress();
      }}
      style={({ pressed }) => ({
        minHeight: 52,
        borderRadius: 16,
        paddingVertical: 8,
        paddingLeft: 8,
        paddingRight: 14,
        flexDirection: 'row',
        alignItems: 'center',
        gap: 12,
        backgroundColor: s.bg,
        borderWidth: 1.5,
        borderColor: s.border,
        opacity: state === 'dim' ? 0.7 : pressed ? 0.85 : 1,
      })}
    >
      <View style={{ width: 34, height: 34, borderRadius: 11, backgroundColor: s.badgeBg, alignItems: 'center', justifyContent: 'center' }}>
        {state === 'correct' ? (
          <Icon name="check" size={16} color={s.badgeInk} strokeWidth={2.8} />
        ) : (
          <Text style={{ fontFamily: fonts.bodySemibold, fontSize: 14 }} color={s.badgeInk}>
            {option.label}
          </Text>
        )}
      </View>
      <Text style={{ flex: 1, fontFamily: fonts.bodyMedium, fontSize: large ? 17 : 15, lineHeight: large ? 23 : 21 }}>{option.text}</Text>
      {option.pick_pct !== null ? (
        <Text variant="mono" style={{ fontSize: 12 }} color={c.textSoft}>
          {option.pick_pct}%
        </Text>
      ) : null}
    </Pressable>
  );
}
