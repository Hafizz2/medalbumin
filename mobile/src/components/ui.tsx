import { ReactNode } from 'react';
import { Pressable, ScrollView, StyleProp, View, ViewStyle } from 'react-native';
import Svg, { Circle, Text as SvgText } from 'react-native-svg';
import { fonts, radius, useTheme } from '../theme';
import { Icon, IconName } from './Icon';
import { Text } from './Text';

export function Card({ children, style, onPress, tone = 'card', accessibilityLabel }: { children: ReactNode; style?: StyleProp<ViewStyle>; onPress?: () => void; tone?: 'card' | 'navy' | 'teal' | 'plain'; accessibilityLabel?: string }) {
  const { c } = useTheme();
  const base: ViewStyle = {
    backgroundColor: tone === 'navy' ? c.navy : tone === 'teal' ? c.tealSoft : tone === 'plain' ? 'transparent' : c.card,
    borderRadius: radius.card,
    borderWidth: tone === 'card' ? 1 : 0,
    borderColor: c.line,
  };
  if (!onPress) return <View style={[base, style]}>{children}</View>;

  return (
    <Pressable accessibilityRole="button" accessibilityLabel={accessibilityLabel} onPress={onPress} style={({ pressed }) => [base, { opacity: pressed ? 0.88 : 1 }, style]}>
      {children}
    </Pressable>
  );
}

/** Rounded square with a stroke icon, as used for departments and quick actions. */
export function IconTile({ icon, bg, ink, size = 40, iconSize = 18, radius: r = 12 }: { icon: IconName | string; bg: string; ink: string; size?: number; iconSize?: number; radius?: number }) {
  return (
    <View style={{ width: size, height: size, borderRadius: r, backgroundColor: bg, alignItems: 'center', justifyContent: 'center' }}>
      <Icon name={icon} size={iconSize} color={ink} />
    </View>
  );
}

export function Chip({ label, selected, onPress, count, icon }: { label: string; selected?: boolean; onPress?: () => void; count?: number | string; icon?: IconName }) {
  const { c } = useTheme();
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityState={{ selected: !!selected }}
      onPress={onPress}
      style={{
        minHeight: 40,
        paddingHorizontal: 14,
        borderRadius: radius.pill,
        borderWidth: 1,
        borderColor: selected ? c.navy : c.field,
        backgroundColor: selected ? c.navy : c.card,
        flexDirection: 'row',
        alignItems: 'center',
        gap: 6,
      }}
    >
      {icon ? <Icon name={icon} size={15} color={selected ? c.onNavy : c.text} /> : null}
      <Text variant="bodyMedium" style={{ fontSize: 14 }} color={selected ? c.onNavy : c.text}>
        {label}
      </Text>
      {count !== undefined ? (
        <Text variant="mono" style={{ fontSize: 12 }} color={selected ? c.onNavySoft : c.muted}>
          {count}
        </Text>
      ) : null}
    </Pressable>
  );
}

/** Horizontally scrolling row of chips that runs edge to edge. */
export function ChipRow({ children }: { children: ReactNode }) {
  return (
    <ScrollView horizontal showsHorizontalScrollIndicator={false} style={{ marginHorizontal: -20, flexGrow: 0 }} contentContainerStyle={{ gap: 8, paddingHorizontal: 20 }}>
      {children}
    </ScrollView>
  );
}

/** The grey pill tab switcher used across screens (Overview / Subjects / Mock exams…). */
export function Segmented<T extends string>({ options, value, onChange, tone = 'light' }: { options: { id: T; label: string }[]; value: T; onChange: (v: T) => void; tone?: 'light' | 'navy' }) {
  const { c } = useTheme();
  const navy = tone === 'navy';
  return (
    <View accessibilityRole="tablist" style={{ flexDirection: 'row', backgroundColor: navy ? c.navy2 : c.chip, padding: 3, borderRadius: radius.md }}>
      {options.map((o) => {
        const on = o.id === value;
        return (
          <Pressable
            key={o.id}
            accessibilityRole="tab"
            accessibilityState={{ selected: on }}
            onPress={() => onChange(o.id)}
            style={{
              flex: 1,
              height: navy ? 30 : 40,
              minWidth: navy ? 40 : undefined,
              paddingHorizontal: 8,
              borderRadius: navy ? 8 : 11,
              alignItems: 'center',
              justifyContent: 'center',
              backgroundColor: on ? (navy ? c.page : c.card) : 'transparent',
              shadowColor: '#062A5E',
              shadowOpacity: on && !navy ? 0.08 : 0,
              shadowRadius: 2,
              shadowOffset: { width: 0, height: 1 },
              elevation: on && !navy ? 1 : 0,
            }}
          >
            <Text
              numberOfLines={1}
              style={{ fontFamily: on ? fonts.bodySemibold : fonts.bodyMedium, fontSize: navy ? 12 : 14 }}
              color={navy ? (on ? c.navy : c.onNavySoft) : on ? c.text : c.textSoft}
            >
              {o.label}
            </Text>
          </Pressable>
        );
      })}
    </View>
  );
}

export function ProgressBar({ value, color, height = 6, track }: { value: number; color?: string; height?: number; track?: string }) {
  const { c } = useTheme();
  const pct = Math.max(0, Math.min(100, value));
  return (
    <View style={{ height, borderRadius: 99, backgroundColor: track ?? c.divider, overflow: 'hidden' }} accessibilityRole="progressbar" accessibilityValue={{ min: 0, max: 100, now: Math.round(pct) }}>
      <View style={{ width: `${pct}%`, height: '100%', borderRadius: 99, backgroundColor: color ?? c.teal }} />
    </View>
  );
}

/** Circular score ring (exam readiness, overall chance). */
export function Ring({ value, size = 74, stroke = 9, color, track, label, labelColor }: { value: number; size?: number; stroke?: number; color: string; track: string; label?: string; labelColor?: string }) {
  const r = (size - stroke) / 2;
  const circ = 2 * Math.PI * r;
  const pct = Math.max(0, Math.min(100, value));
  return (
    <Svg width={size} height={size} accessibilityLabel={label}>
      <Circle cx={size / 2} cy={size / 2} r={r} stroke={track} strokeWidth={stroke} fill="none" />
      <Circle
        cx={size / 2}
        cy={size / 2}
        r={r}
        stroke={color}
        strokeWidth={stroke}
        fill="none"
        strokeLinecap="round"
        strokeDasharray={`${(circ * pct) / 100} ${circ}`}
        transform={`rotate(-90 ${size / 2} ${size / 2})`}
      />
      {label ? (
        <SvgText x={size / 2} y={size / 2 + size * 0.09} textAnchor="middle" fontFamily={fonts.displayBold} fontSize={size * 0.26} fill={labelColor ?? '#FFFFFF'}>
          {label}
        </SvgText>
      ) : null}
    </Svg>
  );
}

type BadgeTone = 'ok' | 'muted' | 'warn' | 'danger' | 'sky' | 'plum' | 'navy' | 'sand';
export function Badge({ label, tone = 'muted' }: { label: string; tone?: BadgeTone }) {
  const { c } = useTheme();
  const map: Record<BadgeTone, [string, string]> = {
    ok: [c.tealSoft, c.teal],
    muted: [c.chip, c.textSoft],
    warn: [c.warnBg, c.warn],
    danger: [c.dangerBg, c.danger],
    sky: [c.skyBg, c.sky],
    plum: [c.plumBg, c.plumInk],
    navy: [c.navy, c.onNavy],
    sand: [c.sandBg, c.sand],
  };
  const [bg, fg] = map[tone];
  return (
    <View style={{ backgroundColor: bg, paddingHorizontal: 9, paddingVertical: 4, borderRadius: 99, alignSelf: 'flex-start' }}>
      <Text style={{ fontFamily: fonts.bodySemibold, fontSize: 11, lineHeight: 14 }} color={fg}>
        {label}
      </Text>
    </View>
  );
}

export function Switch({ value, onChange, label }: { value: boolean; onChange: (v: boolean) => void; label: string }) {
  const { c } = useTheme();
  return (
    <Pressable
      accessibilityRole="switch"
      accessibilityLabel={label}
      accessibilityState={{ checked: value }}
      onPress={() => onChange(!value)}
      hitSlop={8}
      style={{ width: 46, height: 28, borderRadius: 99, padding: 3, backgroundColor: value ? c.teal : '#CFD7E3', justifyContent: 'center', alignItems: value ? 'flex-end' : 'flex-start' }}
    >
      <View style={{ width: 22, height: 22, borderRadius: 11, backgroundColor: '#FFFFFF', shadowColor: '#000', shadowOpacity: 0.2, shadowRadius: 2, shadowOffset: { width: 0, height: 1 }, elevation: 2 }} />
    </Pressable>
  );
}

/** Checkbox square (quiz pools, study plan tasks). */
export function Check({ checked, size = 24 }: { checked: boolean; size?: number }) {
  const { c } = useTheme();
  return (
    <View
      style={{
        width: size,
        height: size,
        borderRadius: 8,
        backgroundColor: checked ? c.teal : c.card,
        borderWidth: checked ? 0 : 1.5,
        borderColor: c.fieldStrong,
        alignItems: 'center',
        justifyContent: 'center',
      }}
    >
      {checked ? <Icon name="check" size={14} color="#FFFFFF" strokeWidth={2.8} /> : null}
    </View>
  );
}

export function Divider({ inset = 0 }: { inset?: number }) {
  const { c } = useTheme();
  return <View style={{ height: 1, backgroundColor: c.divider, marginLeft: inset }} />;
}

/** Row inside a grouped card (settings, lists). */
export function Row({ title, subtitle, onPress, right, left, first, danger }: { title: string; subtitle?: string | null; onPress?: () => void; right?: ReactNode; left?: ReactNode; first?: boolean; danger?: boolean }) {
  const { c } = useTheme();
  const content = (
    <View style={{ minHeight: 60, paddingHorizontal: 16, paddingVertical: 10, flexDirection: 'row', alignItems: 'center', gap: 12, borderTopWidth: first ? 0 : 1, borderTopColor: c.divider }}>
      {left}
      <View style={{ flex: 1, gap: 2 }}>
        <Text variant="bodyStrong" color={danger ? c.stop : undefined}>
          {title}
        </Text>
        {subtitle ? (
          <Text variant="caption" muted>
            {subtitle}
          </Text>
        ) : null}
      </View>
      {right ?? (onPress ? <Icon name="chevronRight" size={18} color={c.muted} /> : null)}
    </View>
  );
  if (!onPress) return content;
  return (
    <Pressable accessibilityRole="button" onPress={onPress} style={({ pressed }) => ({ opacity: pressed ? 0.7 : 1 })}>
      {content}
    </Pressable>
  );
}

export function SectionTitle({ title, action, onAction }: { title: string; action?: string; onAction?: () => void }) {
  const { c } = useTheme();
  return (
    <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'baseline' }}>
      <Text variant="h2">{title}</Text>
      {action ? (
        <Pressable onPress={onAction} hitSlop={8} accessibilityRole="button">
          <Text variant="smallStrong" color={c.teal}>
            {action}
          </Text>
        </Pressable>
      ) : null}
    </View>
  );
}
