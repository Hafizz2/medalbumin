import * as Haptics from 'expo-haptics';
import { useRouter } from 'expo-router';
import { useEffect, useRef } from 'react';
import { Animated, PanResponder, Pressable, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useTranslation } from 'react-i18next';
import { notificationLook } from '../lib/notificationLook';
import { useToast } from '../store/toast';
import { fonts, useTheme } from '../theme';
import { Icon } from './Icon';
import { Text } from './Text';
import { IconTile } from './ui';

/** Designed banner for a notification that arrives while the app is open. Tap to open, swipe up to dismiss. */
export function NotificationToast() {
  const toast = useToast((s) => s.current);
  const hide = useToast((s) => s.hide);
  const { c } = useTheme();
  const { t } = useTranslation();
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const y = useRef(new Animated.Value(-160)).current;
  const timer = useRef<ReturnType<typeof setTimeout> | null>(null);

  const dismiss = () => {
    if (timer.current) clearTimeout(timer.current);
    Animated.timing(y, { toValue: -160, duration: 220, useNativeDriver: true }).start(() => hide());
  };

  useEffect(() => {
    if (!toast) return;
    void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success).catch(() => {});
    y.setValue(-160);
    Animated.spring(y, { toValue: 0, useNativeDriver: true, damping: 18, stiffness: 180 }).start();
    timer.current = setTimeout(dismiss, 5000);
    return () => {
      if (timer.current) clearTimeout(timer.current);
    };
  }, [toast?.id]); // eslint-disable-line react-hooks/exhaustive-deps

  const pan = useRef(
    PanResponder.create({
      onMoveShouldSetPanResponder: (_, g) => g.dy < -6,
      onPanResponderMove: (_, g) => g.dy < 0 && y.setValue(g.dy),
      onPanResponderRelease: (_, g) => (g.dy < -30 ? dismiss() : Animated.spring(y, { toValue: 0, useNativeDriver: true }).start()),
    }),
  ).current;

  if (!toast) return null;
  const look = notificationLook(toast.type, c);

  return (
    <Animated.View {...pan.panHandlers} style={{ position: 'absolute', left: 12, right: 12, top: insets.top + 6, zIndex: 100, transform: [{ translateY: y }] }} accessibilityLiveRegion="polite">
      <Pressable
        onPress={() => {
          dismiss();
          router.push((toast.href ?? '/notifications') as never);
        }}
        accessibilityRole="button"
        accessibilityLabel={`${toast.title}. ${toast.body ?? ''}`}
        style={{
          flexDirection: 'row',
          alignItems: 'center',
          gap: 12,
          padding: 12,
          borderRadius: 20,
          backgroundColor: '#062A5E',
          shadowColor: '#000',
          shadowOpacity: 0.25,
          shadowRadius: 16,
          shadowOffset: { width: 0, height: 8 },
          elevation: 10,
        }}
      >
        <IconTile icon={look.icon} bg="rgba(255,255,255,0.1)" ink={c.accent} />
        <View style={{ flex: 1, gap: 2 }}>
          <Text style={{ fontFamily: fonts.bodySemibold, fontSize: 14 }} color="#F3F6FA" numberOfLines={1}>
            {toast.title}
          </Text>
          {toast.body ? (
            <Text variant="caption" color="#A9BBD8" numberOfLines={2}>
              {toast.body}
            </Text>
          ) : null}
        </View>
        <Pressable onPress={dismiss} hitSlop={10} accessibilityRole="button" accessibilityLabel={t('common.close', 'Close')}>
          <Icon name="close" size={16} color="#A9BBD8" />
        </Pressable>
      </Pressable>
    </Animated.View>
  );
}
