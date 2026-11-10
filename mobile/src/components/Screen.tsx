import { useRouter } from 'expo-router';
import { ReactNode, useState } from 'react';
import { ActivityIndicator, KeyboardAvoidingView, Platform, Pressable, RefreshControl, ScrollView, StyleProp, View, ViewStyle } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useTranslation } from 'react-i18next';
import { ApiError } from '../api/client';
import { useOnline } from '../lib/network';
import { useTheme } from '../theme';
import { Button, IconButton } from './Button';
import { Icon, IconName } from './Icon';
import { Text } from './Text';

type ScreenProps = {
  children: ReactNode;
  /** Pull-to-refresh handler (usually refetch of the screen's queries). */
  onRefresh?: () => Promise<unknown>;
  scroll?: boolean;
  /** Extra bottom space, e.g. above the floating tab bar or a sticky button. */
  bottomInset?: number;
  contentStyle?: StyleProp<ViewStyle>;
  footer?: ReactNode;
  background?: string;
  /** Shows the slim "offline" bar at the top (default on). */
  offlineBar?: boolean;
};

/** Page wrapper: safe areas, scrolling, pull-to-refresh and keyboard handling. */
export function Screen({ children, onRefresh, scroll = true, bottomInset = 24, contentStyle, footer, background, offlineBar = true }: ScreenProps) {
  const { c } = useTheme();
  const insets = useSafeAreaInsets();
  const [refreshing, setRefreshing] = useState(false);
  const online = useOnline();
  const showBar = offlineBar && !online;

  const padding: ViewStyle = { paddingTop: showBar ? 12 : insets.top + 10, paddingHorizontal: 20, paddingBottom: insets.bottom + bottomInset, gap: 14 };

  return (
    <KeyboardAvoidingView style={{ flex: 1, backgroundColor: background ?? c.page }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      {showBar ? <OfflineBar top={insets.top} /> : null}
      {scroll ? (
        <ScrollView
          contentContainerStyle={[padding, contentStyle]}
          keyboardShouldPersistTaps="handled"
          showsVerticalScrollIndicator={false}
          refreshControl={
            onRefresh ? (
              <RefreshControl
                refreshing={refreshing}
                tintColor={c.teal}
                colors={[c.teal]}
                onRefresh={async () => {
                  setRefreshing(true);
                  await onRefresh().catch(() => {});
                  setRefreshing(false);
                }}
              />
            ) : undefined
          }
        >
          {children}
        </ScrollView>
      ) : (
        <View style={[{ flex: 1 }, padding, contentStyle]}>{children}</View>
      )}
      {footer ? <View style={{ paddingHorizontal: 20, paddingTop: 10, paddingBottom: insets.bottom + 12, backgroundColor: background ?? c.page }}>{footer}</View> : null}
    </KeyboardAvoidingView>
  );
}

/** "You're offline" strip — saved content keeps working; tap to open downloads. */
function OfflineBar({ top }: { top: number }) {
  const { t } = useTranslation();
  const router = useRouter();
  return (
    <Pressable
      onPress={() => router.push('/downloads')}
      accessibilityRole="button"
      accessibilityLiveRegion="polite"
      style={{ paddingTop: top + 6, paddingBottom: 8, paddingHorizontal: 20, backgroundColor: '#062A5E', flexDirection: 'row', alignItems: 'center', gap: 8 }}
    >
      <View style={{ width: 8, height: 8, borderRadius: 4, backgroundColor: '#FBBF77' }} />
      <Text variant="caption" color="#F3F6FA" style={{ flex: 1 }}>
        {t('offline.bar', 'Offline · showing saved content')}
      </Text>
      <Text variant="caption" color="#14BCC4" style={{ fontFamily: 'Geist_600SemiBold' }}>
        {t('offline.downloads', 'Downloads')}
      </Text>
    </Pressable>
  );
}

/** Back button + centered title + optional right action, as in the design headers. */
export function Header({ title, right, onBack, backIcon = 'back' }: { title?: string; right?: ReactNode; onBack?: () => void; backIcon?: IconName }) {
  const router = useRouter();
  const { t } = useTranslation();
  return (
    <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 10 }}>
      <IconButton icon={backIcon} label={t('common.back', 'Back')} onPress={onBack ?? (() => (router.canGoBack() ? router.back() : router.replace('/')))} />
      {title ? (
        <Text variant="bodyStrong" style={{ flex: 1, textAlign: 'center', fontSize: 14 }} numberOfLines={1}>
          {title}
        </Text>
      ) : (
        <View style={{ flex: 1 }} />
      )}
      {right ?? <View style={{ width: 44 }} />}
    </View>
  );
}

export function Loading({ label }: { label?: string }) {
  const { c } = useTheme();
  return (
    <View style={{ paddingVertical: 60, alignItems: 'center', gap: 12 }}>
      <ActivityIndicator color={c.teal} size="large" />
      {label ? <Text muted>{label}</Text> : null}
    </View>
  );
}

/** Placeholder block shown while content loads. */
export function Skeleton({ height = 80, radius = 18, style }: { height?: number; radius?: number; style?: StyleProp<ViewStyle> }) {
  const { c } = useTheme();
  return <View style={[{ height, borderRadius: radius, backgroundColor: c.chip, opacity: 0.6 }, style]} />;
}

export function EmptyState({ icon = 'sparkle', title, body, action, onAction }: { icon?: IconName; title: string; body?: string; action?: string; onAction?: () => void }) {
  const { c } = useTheme();
  return (
    <View style={{ alignItems: 'center', paddingVertical: 36, paddingHorizontal: 16, gap: 10 }}>
      <View style={{ width: 56, height: 56, borderRadius: 18, backgroundColor: c.tealSoft, alignItems: 'center', justifyContent: 'center' }}>
        <Icon name={icon} size={24} color={c.teal} />
      </View>
      <Text variant="h3" align="center">
        {title}
      </Text>
      {body ? (
        <Text variant="small" muted align="center">
          {body}
        </Text>
      ) : null}
      {action ? <Button title={action} onPress={onAction} variant="light" size="md" style={{ marginTop: 6, alignSelf: 'center' }} /> : null}
    </View>
  );
}

/** Friendly error with retry; premium errors explain without linking to purchase (store rules). */
export function ErrorState({ error, onRetry }: { error: unknown; onRetry?: () => void }) {
  const { t } = useTranslation();
  const router = useRouter();
  const err = error instanceof ApiError ? error : null;
  if (err?.kind === 'offline') {
    return (
      <EmptyState
        icon="download"
        title={t('common.offline_title', 'You are offline')}
        body={t('offline.not_saved', 'This page wasn’t saved yet. Pages you open online stay readable offline, and downloaded departments work fully without internet.')}
        action={t('offline.open_downloads', 'Open my downloads')}
        onAction={() => router.push('/downloads')}
      />
    );
  }
  if (err?.kind === 'premium') {
    return <EmptyState icon="lock" title={t('common.premium_title', 'Premium content')} body={t('common.premium_body', 'This is part of the MedAlbumin premium plan. Your plan status is under Profile.')} />;
  }
  return (
    <EmptyState
      icon="flag"
      title={t('common.error_title', 'Something went wrong')}
      body={err?.message ?? t('common.error_body', 'Please try again.')}
      action={onRetry ? t('common.retry', 'Try again') : undefined}
      onAction={onRetry}
    />
  );
}
