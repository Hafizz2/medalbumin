import { useRouter } from 'expo-router';
import { useState } from 'react';
import { Pressable, View } from 'react-native';
import type { QuickAction } from '../hooks/useQuickActions';
import { fonts, useTheme } from '../theme';
import { Text } from './Text';
import { IconTile } from './ui';

const COLUMNS = 3;
const GAP = 8;

/** Three equal columns on every phone width — tile size comes from the measured row width. */
export function ActionGrid({ actions }: { actions: QuickAction[] }) {
  const { c } = useTheme();
  const router = useRouter();
  const [width, setWidth] = useState(0);
  const tile = width ? Math.floor((width - GAP * (COLUMNS - 1)) / COLUMNS) : 0;

  return (
    <View onLayout={(e) => setWidth(e.nativeEvent.layout.width)} style={{ flexDirection: 'row', flexWrap: 'wrap', gap: GAP, minHeight: 84 }}>
      {tile
        ? actions.map((a) => (
            <Pressable
              key={a.href}
              accessibilityRole="button"
              accessibilityLabel={`${a.label}, ${a.sub}`}
              onPress={() => router.push(a.href as never)}
              style={({ pressed }) => ({
                width: tile,
                minHeight: 92,
                backgroundColor: c.card,
                borderWidth: 1,
                borderColor: c.line,
                borderRadius: 16,
                padding: 11,
                justifyContent: 'space-between',
                gap: 8,
                opacity: pressed ? 0.85 : 1,
              })}
            >
              <IconTile icon={a.icon} bg={a.bg} ink={a.ink} size={34} iconSize={17} radius={10} />
              <View style={{ gap: 1 }}>
                <Text style={{ fontFamily: fonts.bodySemibold, fontSize: 13, lineHeight: 17 }} numberOfLines={2}>
                  {a.label}
                </Text>
                <Text style={{ fontSize: 11, lineHeight: 14 }} muted numberOfLines={2}>
                  {a.sub}
                </Text>
              </View>
            </Pressable>
          ))
        : null}
    </View>
  );
}
