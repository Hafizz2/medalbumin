import { useQueryClient } from '@tanstack/react-query';
import { Image } from 'expo-image';
import { useRouter } from 'expo-router';
import { useMemo, useState } from 'react';
import { Alert, Pressable, View } from 'react-native';
import ReanimatedSwipeable from 'react-native-gesture-handler/ReanimatedSwipeable';
import { useTranslation } from 'react-i18next';
import { deleteNotification, markAllRead, markRead, qk, useNotifications } from '../api/hooks';
import type { AppNotification, NotificationCategory } from '../api/types';
import { Button, IconButton } from '../components/Button';
import { Icon } from '../components/Icon';
import { EmptyState, ErrorState, Screen, Skeleton } from '../components/Screen';
import { Text } from '../components/Text';
import { Chip, ChipRow, IconTile } from '../components/ui';
import { fmtAgo } from '../lib/format';
import { notificationLook } from '../lib/notificationLook';
import { routeForNotification } from '../lib/notificationRoutes';
import { notificationText } from '../lib/notificationText';
import { fonts, useTheme } from '../theme';

type Filter = 'all' | 'unread' | 'exams' | 'study' | 'community' | 'news' | 'account';

const FILTER_CATEGORIES: Record<Exclude<Filter, 'all' | 'unread'>, NotificationCategory[]> = {
  exams: ['exams'],
  study: ['reminders'],
  community: ['community'],
  news: ['content', 'updates', 'promos'],
  account: ['account'],
};

/** Today / Yesterday / This week / Earlier. */
function sectionOf(iso: string): 0 | 1 | 2 | 3 {
  const d = new Date(iso);
  const start = new Date();
  start.setHours(0, 0, 0, 0);
  const day = 86400000;
  if (d.getTime() >= start.getTime()) return 0;
  if (d.getTime() >= start.getTime() - day) return 1;
  if (d.getTime() >= start.getTime() - 6 * day) return 2;
  return 3;
}

/** 26 · Notification centre */
export default function Notifications() {
  const { t } = useTranslation();
  const { c } = useTheme();
  const router = useRouter();
  const qc = useQueryClient();
  const q = useNotifications();
  const [filter, setFilter] = useState<Filter>('all');
  const [removed, setRemoved] = useState<Record<string, boolean>>({});

  const all = (q.data?.data ?? []).filter((n) => !removed[n.id]);
  const visible = useMemo(
    () => all.filter((n) => (filter === 'all' ? true : filter === 'unread' ? !n.read : FILTER_CATEGORIES[filter].includes(n.category))),
    [all, filter],
  );
  const unreadIn = (f: Filter) => all.filter((n) => !n.read && (f === 'all' || f === 'unread' || FILTER_CATEGORIES[f as keyof typeof FILTER_CATEGORIES]?.includes(n.category))).length;

  const sections = [t('notif.today', 'Today'), t('notif.yesterday', 'Yesterday'), t('notif.this_week', 'This week'), t('notif.earlier', 'Earlier')];
  const grouped = [0, 1, 2, 3].map((s) => visible.filter((n) => sectionOf(n.created_at) === s));

  const refresh = () => {
    qc.invalidateQueries({ queryKey: qk.notifications });
    qc.invalidateQueries({ queryKey: qk.home });
  };

  const open = async (n: AppNotification) => {
    if (!n.read) {
      await markRead(n.id).catch(() => {});
      refresh();
    }
    const href = routeForNotification(n.route, n.params);
    if (href && href !== '/notifications') router.push(href as never);
  };

  const remove = (n: AppNotification) => {
    setRemoved((r) => ({ ...r, [n.id]: true }));
    deleteNotification(n.id)
      .then(refresh)
      .catch(() => setRemoved((r) => ({ ...r, [n.id]: false })));
  };

  const readAll = async () => {
    await markAllRead().catch(() => {});
    refresh();
  };

  const filters: [Filter, string][] = [
    ['all', t('notif.all', 'All')],
    ['unread', t('notif.unread_filter', 'Unread')],
    ['exams', t('notif.f_exams', 'Exams')],
    ['study', t('notif.f_study', 'Study')],
    ['community', t('notif.f_community', 'Discussions')],
    ['news', t('notif.f_news', 'News & offers')],
    ['account', t('notif.f_account', 'Account')],
  ];

  return (
    <Screen onRefresh={() => q.refetch()}>
      <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' }}>
        <IconButton icon="back" label={t('common.back', 'Back')} onPress={() => (router.canGoBack() ? router.back() : router.replace('/'))} />
        <IconButton icon="settings" label={t('notif.settings', 'Notification settings')} onPress={() => router.push('/notification-settings' as never)} />
      </View>

      <View style={{ flexDirection: 'row', alignItems: 'flex-end', justifyContent: 'space-between', gap: 12 }}>
        <View style={{ gap: 2, flex: 1 }}>
          <Text variant="title" style={{ fontSize: 30 }}>
            {t('notif.title', 'Notifications')}
          </Text>
          <Text variant="small" muted>
            {q.data?.unread ? t('notif.unread', '{n} unread', { n: q.data.unread }) : t('notif.all_caught_up', 'You’re all caught up')}
          </Text>
        </View>
        {q.data?.unread ? (
          <Pressable onPress={readAll} accessibilityRole="button" hitSlop={8} style={{ flexDirection: 'row', alignItems: 'center', gap: 6, height: 34, paddingHorizontal: 12, borderRadius: 99, backgroundColor: c.tealSoft }}>
            <Icon name="check" size={14} color={c.teal} />
            <Text variant="smallStrong" color={c.teal}>
              {t('notif.read_all', 'Mark all read')}
            </Text>
          </Pressable>
        ) : null}
      </View>

      <ChipRow>
        {filters.map(([id, label]) => {
          const n = unreadIn(id);
          return <Chip key={id} label={label} selected={filter === id} count={n && id !== 'unread' ? n : id === 'unread' && n ? n : undefined} onPress={() => setFilter(id)} />;
        })}
      </ChipRow>

      {q.isError && !q.data ? (
        <ErrorState error={q.error} onRetry={() => q.refetch()} />
      ) : !q.data ? (
        <>
          <Skeleton height={76} />
          <Skeleton height={76} />
          <Skeleton height={180} />
        </>
      ) : visible.length === 0 ? (
        <EmptyState
          icon="bell"
          title={filter === 'all' ? t('notif.empty', 'No notifications yet') : t('notif.empty_filter', 'Nothing here')}
          body={filter === 'all' ? t('notif.empty_body', 'Payment updates, grand mock reminders and answers to your doubts appear here.') : t('notif.empty_filter_body', 'Try another filter.')}
        />
      ) : (
        grouped.map((items, s) =>
          items.length ? (
            <View key={s} style={{ gap: 8 }}>
              <Text variant="label" muted>
                {sections[s]}
              </Text>
              {items.map((n) => (
                <Row key={n.id} n={n} onOpen={() => open(n)} onDelete={() => remove(n)} />
              ))}
            </View>
          ) : null,
        )
      )}

      {all.length > 3 ? (
        <Text variant="caption" muted align="center">
          {t('notif.swipe_hint', 'Swipe left on a notification to delete it.')}
        </Text>
      ) : null}
    </Screen>
  );
}

function Row({ n, onOpen, onDelete }: { n: AppNotification; onOpen: () => void; onDelete: () => void }) {
  const { t } = useTranslation();
  const { c } = useTheme();
  const look = notificationLook(n.type, c);
  const text = notificationText(n, t);
  const rich = n.type === 'promo' || !!n.image;

  const longPress = () =>
    Alert.alert(text.title, undefined, [
      { text: t('common.cancel', 'Cancel'), style: 'cancel' },
      { text: t('notif.delete', 'Delete'), style: 'destructive', onPress: onDelete },
    ]);

  const deleteAction = () => (
    <Pressable onPress={onDelete} accessibilityRole="button" accessibilityLabel={t('notif.delete', 'Delete')} style={{ width: 84, marginLeft: 8, borderRadius: 18, backgroundColor: c.danger, alignItems: 'center', justifyContent: 'center', gap: 4 }}>
      <Icon name="trash" size={18} color="#FFFFFF" />
      <Text variant="caption" color="#FFFFFF" style={{ fontFamily: fonts.bodySemibold }}>
        {t('notif.delete', 'Delete')}
      </Text>
    </Pressable>
  );

  const body = rich ? (
    <View style={{ borderRadius: 20, overflow: 'hidden', backgroundColor: c.card, borderWidth: 1, borderColor: n.read ? c.line : c.field }}>
      {n.image ? (
        <Image source={{ uri: n.image }} style={{ width: '100%', aspectRatio: 2 }} contentFit="cover" cachePolicy="disk" transition={150} accessibilityIgnoresInvertColors />
      ) : (
        <View style={{ height: 76, backgroundColor: '#062A5E', paddingHorizontal: 16, flexDirection: 'row', alignItems: 'center', gap: 12 }}>
          <IconTile icon={look.icon} bg="rgba(255,255,255,0.1)" ink={c.accent} />
          <Text variant="overline" color={c.onNavyLabel}>
            {n.type === 'promo' ? t('notif.offer', 'Special offer') : t('notif.announcement', 'Announcement')}
          </Text>
        </View>
      )}
      <View style={{ padding: 14, gap: 6 }}>
        <View style={{ flexDirection: 'row', alignItems: 'flex-start', gap: 8 }}>
          <Text variant="bodyStrong" style={{ flex: 1 }}>
            {text.title}
          </Text>
          <Text variant="caption" muted>
            {fmtAgo(n.created_at)}
          </Text>
          {!n.read ? <View style={{ width: 8, height: 8, borderRadius: 4, backgroundColor: c.dot, marginTop: 6 }} /> : null}
        </View>
        <Text variant="small" muted>
          {text.body}
        </Text>
        {n.route ? <Button title={n.action || t('notif.open', 'Open')} size="sm" variant={n.type === 'promo' ? 'accent' : 'soft'} onPress={onOpen} style={{ alignSelf: 'flex-start', marginTop: 4 }} /> : null}
      </View>
    </View>
  ) : (
    <View
      style={{
        flexDirection: 'row',
        alignItems: 'flex-start',
        gap: 12,
        paddingVertical: 12,
        paddingHorizontal: 14,
        borderRadius: 18,
        backgroundColor: c.card,
        borderWidth: 1,
        borderColor: n.read ? c.line : c.field,
        overflow: 'hidden',
      }}
    >
      {!n.read ? <View style={{ position: 'absolute', left: 0, top: 12, bottom: 12, width: 3, borderRadius: 2, backgroundColor: c.accent }} /> : null}
      <IconTile icon={look.icon} bg={look.bg} ink={look.ink} />
      <View style={{ flex: 1, gap: 3 }}>
        <View style={{ flexDirection: 'row', justifyContent: 'space-between', gap: 8 }}>
          <Text style={{ flex: 1, fontFamily: n.read ? fonts.bodyMedium : fonts.bodySemibold, fontSize: 14, lineHeight: 19 }}>{text.title}</Text>
          <Text variant="caption" muted>
            {fmtAgo(n.created_at)}
          </Text>
        </View>
        <Text variant="small" muted numberOfLines={3}>
          {text.body}
        </Text>
      </View>
      {!n.read ? <View style={{ width: 8, height: 8, borderRadius: 4, backgroundColor: c.dot, alignSelf: 'center' }} /> : null}
    </View>
  );

  return (
    <ReanimatedSwipeable friction={2} rightThreshold={60} overshootRight={false} renderRightActions={deleteAction}>
      <Pressable
        onPress={onOpen}
        onLongPress={longPress}
        accessibilityRole="button"
        accessibilityLabel={`${n.read ? '' : t('notif.unread_label', 'Unread') + '. '}${text.title}. ${text.body}`}
        accessibilityActions={[{ name: 'delete', label: t('notif.delete', 'Delete') }]}
        onAccessibilityAction={(e) => e.nativeEvent.actionName === 'delete' && onDelete()}
        style={({ pressed }) => ({ opacity: pressed ? 0.85 : 1 })}
      >
        {body}
      </Pressable>
    </ReanimatedSwipeable>
  );
}
