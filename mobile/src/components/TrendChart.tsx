import { useState } from 'react';
import { View } from 'react-native';
import Svg, { Circle, Defs, LinearGradient, Path, Polyline, Stop } from 'react-native-svg';

/** Small area + line chart (weekly accuracy trend). Null points carry the last value. */
export function TrendChart({ values, color, height = 72 }: { values: (number | null)[]; color: string; height?: number }) {
  const [width, setWidth] = useState(0);
  const pts = values.map((v) => v ?? 0);
  const has = values.some((v) => v !== null);
  const min = has ? Math.max(0, Math.min(...pts) - 5) : 0;
  const max = has ? Math.min(100, Math.max(...pts) + 5) : 100;
  const span = Math.max(1, max - min);

  const coords = width
    ? pts.map((v, i) => [4 + (i * (width - 8)) / Math.max(1, pts.length - 1), height - 6 - ((v - min) / span) * (height - 14)])
    : [];
  const line = coords.map((p) => p.join(',')).join(' ');
  const last = coords[coords.length - 1];

  return (
    <View style={{ height }} onLayout={(e) => setWidth(e.nativeEvent.layout.width)} accessibilityRole="image" accessibilityLabel={`Trend: ${values.filter((v) => v !== null).join(', ')}`}>
      {width && has ? (
        <Svg width={width} height={height}>
          <Defs>
            <LinearGradient id="area" x1="0" y1="0" x2="0" y2="1">
              <Stop offset="0" stopColor={color} stopOpacity={0.28} />
              <Stop offset="1" stopColor={color} stopOpacity={0} />
            </LinearGradient>
          </Defs>
          <Path d={`M${coords.map((p) => p.join(',')).join(' L')} L${width - 4},${height} L4,${height} Z`} fill="url(#area)" />
          <Polyline points={line} fill="none" stroke={color} strokeWidth={2.5} strokeLinejoin="round" strokeLinecap="round" />
          {last ? <Circle cx={last[0]} cy={last[1]} r={4} fill={color} /> : null}
        </Svg>
      ) : null}
    </View>
  );
}
