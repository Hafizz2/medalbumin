import { Text as RNText, TextProps, TextStyle } from 'react-native';
import { fonts, useTheme } from '../theme';

type Variant = 'title' | 'h1' | 'h2' | 'h3' | 'body' | 'bodyMedium' | 'bodyStrong' | 'small' | 'smallStrong' | 'caption' | 'label' | 'mono' | 'monoLarge' | 'overline';

const variants: Record<Variant, TextStyle> = {
  title: { fontFamily: fonts.display, fontSize: 32, lineHeight: 35, letterSpacing: -0.9 },
  h1: { fontFamily: fonts.display, fontSize: 26, lineHeight: 31, letterSpacing: -0.6 },
  h2: { fontFamily: fonts.display, fontSize: 18, lineHeight: 23, letterSpacing: -0.2 },
  h3: { fontFamily: fonts.display, fontSize: 16, lineHeight: 21 },
  body: { fontFamily: fonts.body, fontSize: 15, lineHeight: 22 },
  bodyMedium: { fontFamily: fonts.bodyMedium, fontSize: 15, lineHeight: 21 },
  bodyStrong: { fontFamily: fonts.bodySemibold, fontSize: 15, lineHeight: 21 },
  small: { fontFamily: fonts.body, fontSize: 13, lineHeight: 18 },
  smallStrong: { fontFamily: fonts.bodySemibold, fontSize: 13, lineHeight: 18 },
  caption: { fontFamily: fonts.body, fontSize: 12, lineHeight: 16 },
  label: { fontFamily: fonts.bodySemibold, fontSize: 12, lineHeight: 16, letterSpacing: 0.9, textTransform: 'uppercase' },
  overline: { fontFamily: fonts.bodySemibold, fontSize: 11, lineHeight: 14, letterSpacing: 0.7, textTransform: 'uppercase' },
  mono: { fontFamily: fonts.mono, fontSize: 13, lineHeight: 18 },
  monoLarge: { fontFamily: fonts.mono, fontSize: 16, lineHeight: 20 },
};

type Props = TextProps & { variant?: Variant; color?: string; muted?: boolean; align?: TextStyle['textAlign'] };

/** Text using the design's type scale (Outfit headings, Geist body, Geist Mono numbers). */
export function Text({ variant = 'body', color, muted, align, style, ...rest }: Props) {
  const { c } = useTheme();
  return (
    <RNText
      maxFontSizeMultiplier={1.4}
      {...rest}
      style={[variants[variant], { color: color ?? (muted ? c.muted : c.text), textAlign: align }, style]}
    />
  );
}
