import * as Haptics from 'expo-haptics';
import { Redirect, Tabs } from 'expo-router';
import type { ComponentProps } from 'react';
import { Pressable, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useTranslation } from 'react-i18next';
import { Icon, IconName } from '../../components/Icon';
import { Text } from '../../components/Text';
import { useAuth } from '../../store/auth';
import { fonts, useTheme } from '../../theme';

const TABS: { name: string; icon: IconName; key: string; label: string }[] = [
  { name: 'index', icon: 'home', key: 'tabs.home', label: 'Home' },
  { name: 'study', icon: 'book', key: 'tabs.study', label: 'Study' },
  { name: 'progress', icon: 'chart', key: 'tabs.progress', label: 'Progress' },
  { name: 'notes', icon: 'note', key: 'tabs.notes', label: 'Notes' },
  { name: 'profile', icon: 'user', key: 'tabs.profile', label: 'Profile' },
];

type TabBarProps = Parameters<NonNullable<ComponentProps<typeof Tabs>['tabBar']>>[0];

/** Floating navy tab bar from the design. */
function TabBar({ state, emitter, navigateToTab }: TabBarProps) {
  const { c } = useTheme();
  const { t } = useTranslation();
  const insets = useSafeAreaInsets();
  const labels: Record<string, string> = {
    index: t('tabs.home', 'Home'),
    study: t('tabs.study', 'Study'),
    progress: t('tabs.progress', 'Progress'),
    notes: t('tabs.notes', 'Notes'),
    profile: t('tabs.profile', 'Profile'),
  };

  return (
    <View
      style={{
        position: 'absolute',
        left: 12,
        right: 12,
        bottom: Math.max(insets.bottom, 12) + 4,
        height: 68,
        borderRadius: 24,
        backgroundColor: '#062A5E',
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'space-around',
        paddingHorizontal: 6,
        shadowColor: '#062A5E',
        shadowOpacity: 0.25,
        shadowRadius: 16,
        shadowOffset: { width: 0, height: 8 },
        elevation: 10,
      }}
    >
      {state.routes.map((route, i) => {
        const tab = TABS.find((x) => x.name === route.name);
        if (!tab) return null;
        const focused = state.index === i;
        const label = labels[tab.name] ?? tab.label;
        return (
          <Pressable
            key={route.key}
            accessibilityRole="tab"
            accessibilityState={{ selected: focused }}
            accessibilityLabel={label}
            onPress={() => {
              const event = emitter.emit({ type: 'tabPress', target: route.key, canPreventDefault: true });
              if (!focused && !event.defaultPrevented) {
                void Haptics.selectionAsync().catch(() => {});
                navigateToTab(route.key);
              }
            }}
            style={{ minWidth: 56, height: 52, paddingHorizontal: 6, borderRadius: 16, alignItems: 'center', justifyContent: 'center', gap: 3, backgroundColor: focused ? '#12407F' : 'transparent' }}
          >
            <Icon name={tab.icon} size={20} color={focused ? c.accent : '#94A6C4'} />
            <Text style={{ fontFamily: focused ? fonts.bodySemibold : fonts.bodyMedium, fontSize: 11, lineHeight: 13 }} color={focused ? c.accent : '#94A6C4'} numberOfLines={1}>
              {label}
            </Text>
          </Pressable>
        );
      })}
    </View>
  );
}

export default function TabsLayout() {
  const token = useAuth((s) => s.token);
  const { c } = useTheme();
  if (!token) return <Redirect href="/sign-in" />;

  return (
    <Tabs tabBar={(props) => <TabBar {...props} />} screenOptions={{ headerShown: false, sceneStyle: { backgroundColor: c.page } }}>
      {TABS.map((tab) => (
        <Tabs.Screen key={tab.name} name={tab.name} />
      ))}
    </Tabs>
  );
}

