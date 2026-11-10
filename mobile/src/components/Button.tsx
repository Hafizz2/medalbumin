import * as Haptics from 'expo-haptics';
import { ReactNode } from 'react';
import { ActivityIndicator, Pressable, StyleProp, View, ViewStyle } from 'react-native';
import { fonts, radius, useTheme } from '../theme';
import { Icon, IconName } from './Icon';
import { Text } from './Text';

type Variant = 'primary' | 'teal' | 'accent' | 'light' | 'soft' | 'ghost' | 'danger';

type Props = {
  title: string;
  onPress?: () => void;
  variant?: Variant;
  icon?: IconName;
  iconRight?: IconName;
  loading?: boolean;
  disabled?: boolean;
  size?: 'lg' | 'md' | 'sm';
  style?: StyleProp<ViewStyle>;
  trailing?: ReactNode;
  accessibilityHint?: string;
};

export function Button({ title, onPress, variant = 'primary', icon, iconRight, loading, disabled, size = 'lg', style, trailing, accessibilityHint }: Props) {
  const { c } = useTheme();
  const palette: Record<Variant, { bg: string; fg: string; border?: string }> = {
    primary: { bg: c.navy, fg: c.onNavy },
    teal: { bg: c.teal, fg: '#FFFFFF' },
    accent: { bg: c.accent, fg: '#062A5E' },
    light: { bg: c.card, fg: c.text, border: c.field },
    soft: { bg: c.chip, fg: c.text },
    ghost: { bg: 'transparent', fg: c.teal },
    danger: { bg: c.card, fg: c.stop, border: c.stopBg },
  };
  const p = palette[variant];
  const height = size === 'lg' ? 56 : size === 'md' ? 48 : 40;
  const off = disabled || loading;

  return (
    <Pressable
      accessibilityRole="button"
      accessibilityState={{ disabled: !!off, busy: !!loading }}
      accessibilityHint={accessibilityHint}
      disabled={off}
      onPress={() => {
        void Haptics.selectionAsync().catch(() => {});
        onPress?.();
      }}
      style={({ pressed }) => [
        {
          height,
          borderRadius: size === 'sm' ? 12 : radius.lg,
          backgroundColor: p.bg,
          borderWidth: p.border ? 1 : 0,
          borderColor: p.border,
          paddingHorizontal: size === 'sm' ? 14 : 18,
          flexDirection: 'row',
          alignItems: 'center',
          justifyContent: 'center',
          gap: 8,
          opacity: off ? 0.55 : pressed ? 0.85 : 1,
          transform: [{ scale: pressed ? 0.985 : 1 }],
        },
        style,
      ]}
    >
      {loading ? (
        <ActivityIndicator color={p.fg} />
      ) : (
        <>
          {icon ? <Icon name={icon} size={size === 'sm' ? 16 : 18} color={p.fg} strokeWidth={2} /> : null}
          <Text style={{ fontFamily: fonts.bodySemibold, fontSize: size === 'lg' ? 16 : 14, color: p.fg }} numberOfLines={1}>
            {title}
          </Text>
          {iconRight ? <Icon name={iconRight} size={18} color={p.fg} strokeWidth={2} /> : null}
          {trailing ? <View>{trailing}</View> : null}
        </>
      )}
    </Pressable>
  );
}

/** Square 44×44 button used in headers (back, bookmark, share…). */
export function IconButton({
  icon,
  onPress,
  label,
  active,
  tone = 'card',
  badge,
  children,
}: {
  icon?: IconName;
  onPress?: () => void;
  label: string;
  active?: boolean;
  tone?: 'card' | 'navy';
  badge?: boolean;
  children?: ReactNode;
}) {
  const { c } = useTheme();
  const bg = active ? c.navy : tone === 'navy' ? c.navy2 : c.card;
  const fg = active ? c.accent : tone === 'navy' ? c.onNavy : c.text;

  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={label}
      accessibilityState={{ selected: !!active }}
      hitSlop={6}
      onPress={onPress}
      style={({ pressed }) => ({
        width: 44,
        height: 44,
        borderRadius: radius.md,
        backgroundColor: bg,
        borderWidth: tone === 'card' && !active ? 1 : 0,
        borderColor: c.line,
        alignItems: 'center',
        justifyContent: 'center',
        opacity: pressed ? 0.75 : 1,
      })}
    >
      {children ?? (icon ? <Icon name={icon} size={18} color={fg} strokeWidth={2} fill={active && icon === 'bookmark' ? fg : 'none'} /> : null)}
      {badge ? <View style={{ position: 'absolute', top: 9, right: 10, width: 8, height: 8, borderRadius: 4, backgroundColor: c.dot, borderWidth: 2, borderColor: c.card }} /> : null}
    </Pressable>
  );
}
