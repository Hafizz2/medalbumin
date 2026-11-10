import { Image, View } from 'react-native';
import Svg, { Circle, Path } from 'react-native-svg';
import { useTranslation } from 'react-i18next';
import { fonts } from '../theme';
import { Text } from './Text';

/** Logo mark in a white rounded tile + "MedAlbumin" wordmark. */
export function Brand({ size = 48, onDark = true, tagline = true }: { size?: number; onDark?: boolean; tagline?: boolean }) {
  const { t } = useTranslation();
  return (
    <View style={{ flexDirection: 'row', alignItems: 'center', gap: 10 }} accessible accessibilityLabel="MedAlbumin">
      <View style={{ width: size, height: size, borderRadius: size * 0.29, backgroundColor: '#FFFFFF', alignItems: 'center', justifyContent: 'center', borderWidth: onDark ? 0 : 1, borderColor: '#E0E6EE' }}>
        <Image source={require('../../assets/mark.png')} style={{ width: size * 0.7, height: size * 0.82 }} resizeMode="contain" />
      </View>
      <View style={{ gap: 2 }}>
        <Text style={{ fontFamily: fonts.displayBold, fontSize: size * 0.48, lineHeight: size * 0.56, letterSpacing: -0.3 }}>
          <Text style={{ fontFamily: fonts.displayBold }} color={onDark ? '#FFFFFF' : '#062A5E'}>
            Med
          </Text>
          <Text style={{ fontFamily: fonts.displayBold }} color={onDark ? '#14BCC4' : '#017F8E'}>
            Albumin
          </Text>
        </Text>
        {tagline ? (
          <Text variant="caption" color={onDark ? '#A9BBD8' : '#56657D'} numberOfLines={1}>
            {t('brand.tagline', 'Connect Knowledge. Master Medicine.')}
          </Text>
        ) : null}
      </View>
    </View>
  );
}

/** The heartbeat line from the welcome and sign-in screens. */
export function EcgLine({ width = 390, accent = '#14BCC4' }: { width?: number; accent?: string }) {
  const s = width / 390;
  return (
    <Svg width={width} height={420 * s} viewBox="0 0 390 420" style={{ position: 'absolute', top: 0, left: 0 }} pointerEvents="none">
      <Path d="M0 250 H110 L128 215 L146 290 L166 150 L186 320 L204 250 H390" fill="none" stroke="#1B4A8A" strokeWidth={2} />
      <Path d="M0 250 H110 L128 215 L146 290 L166 150 L186 320 L204 250 H250" fill="none" stroke={accent} strokeWidth={2.5} strokeLinecap="round" strokeLinejoin="round" />
      <Circle cx={250} cy={250} r={5} fill={accent} />
      <Circle cx={250} cy={250} r={14} fill="none" stroke={accent} strokeOpacity={0.35} />
    </Svg>
  );
}
